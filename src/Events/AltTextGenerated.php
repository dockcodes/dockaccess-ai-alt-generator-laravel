<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Events;

/**
 * A queued generation succeeded. Listen for it to store the alt where it belongs:
 *
 *     Event::listen(function (AltTextGenerated $event) {
 *         Product::whereKey($event->context['product_id'])->update(['image_alt' => $event->alt]);
 *     });
 *
 * The alt is plain text already; escape it where it is printed, as any other input.
 */
final class AltTextGenerated
{
    /**
     * @param list<array{text: string, locale: string}> $alts every variant, the first one is `$alt`
     * @param array<string, mixed> $context what was passed to the job, untouched
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $disk,
        public readonly string $alt,
        public readonly string $locale,
        public readonly array $alts,
        public readonly string $imageUuid,
        public readonly string $setUuid,
        public readonly array $context,
    ) {}
}
