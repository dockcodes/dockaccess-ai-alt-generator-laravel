<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Jobs;

use Dock\AltGenerator\Exception\RateLimitedException;
use Dock\AltGenerator\Laravel\AltText;
use Dock\AltGenerator\Laravel\Events\AltTextFailed;
use Dock\AltGenerator\Laravel\Events\AltTextGenerated;
use Dock\AltGenerator\Laravel\Support\FailureReason;
use Dock\AltGenerator\Result\Alt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Generates alt text in the background and announces the result with AltTextGenerated (or
 * AltTextFailed):
 *
 *     GenerateAltText::dispatch('products/42.jpg', 's3', ['context' => $product->name], ['product_id' => $product->id]);
 *
 * Only what can succeed later is tried again: the per minute cap waits as long as the generator
 * asks, an outage backs off. A spent limit, a refused image or a disconnected application fail at
 * once - retrying them would only spend time (and, for a limit, nothing would change until the
 * next day or month).
 */
final class GenerateAltText implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /**
     * @param string $path a path on `$disk`, or an absolute path inside `allowed_directories` when `$disk` is null
     * @param array<string, mixed> $options language, tone, context, variants, set_ref
     * @param array<string, mixed> $context anything the listener needs to find the record (an id, not a model)
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $disk = null,
        public readonly array $options = [],
        public readonly array $context = [],
    ) {
        $this->onConnection(config('dockaccess-alt.queue.connection'));
        $this->onQueue(config('dockaccess-alt.queue.queue'));
    }

    public function handle(AltText $altText, Dispatcher $events): void
    {
        try {
            $result = $this->disk === null
                ? $altText->generate($this->path, $this->options)
                : $altText->generateFromDisk($this->path, $this->disk, $this->options);
        } catch (Throwable $e) {
            if (! FailureReason::isTemporary($e) || $this->attempts() >= $this->tries) {
                $this->fail($e);

                return;
            }

            $this->release($e instanceof RateLimitedException ? $e->getRetryAfter() : $this->backoffFor($this->attempts()));

            return;
        }

        $events->dispatch(new AltTextGenerated(
            $this->path,
            $this->disk,
            $result->alt()->text(),
            $result->alt()->locale(),
            array_values(array_map(static fn (Alt $alt) => ['text' => $alt->text(), 'locale' => $alt->locale()], $result->alts())),
            $result->imageUuid(),
            $result->setUuid(),
            $this->context,
        ));
    }

    public function failed(?Throwable $e): void
    {
        event(new AltTextFailed(
            $this->path,
            $this->disk,
            $e === null ? FailureReason::ERROR : FailureReason::of($e),
            $e?->getMessage() ?? 'The job failed.',
            $this->context,
        ));
    }

    private function backoffFor(int $attempt): int
    {
        return min(3600, 60 * (2 ** max(0, $attempt - 1)));
    }
}
