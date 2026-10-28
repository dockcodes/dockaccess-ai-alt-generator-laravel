<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Events;

/**
 * A queued generation gave up. `$reason` is stable (see Support\FailureReason and the core's
 * InvalidImageException reasons); `$message` is meant for logs, not for end users.
 */
final class AltTextFailed
{
    /**
     * @param array<string, mixed> $context what was passed to the job, untouched
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $disk,
        public readonly string $reason,
        public readonly string $message,
        public readonly array $context,
    ) {}
}
