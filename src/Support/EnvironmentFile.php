<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Support;

use RuntimeException;

/**
 * Sets and removes variables in a `.env` file, leaving every other line as it was - the way
 * `php artisan key:generate` writes APP_KEY.
 *
 * @internal
 */
final class EnvironmentFile
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function isWritable(): bool
    {
        return is_file($this->path) && is_writable($this->path);
    }

    /**
     * @param array<string, string> $values
     */
    public function set(array $values): void
    {
        $content = $this->read();

        foreach ($values as $name => $value) {
            $line = $name.'='.self::quote($value);
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace_callback($pattern, static fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\n").($content === '' ? '' : "\n").$line."\n";
            }
        }

        $this->write($content);
    }

    /**
     * @param list<string> $names
     */
    public function remove(array $names): void
    {
        $content = $this->read();

        foreach ($names as $name) {
            $content = (string) preg_replace('/^'.preg_quote($name, '/').'=.*(\R|$)/m', '', $content);
        }

        $this->write($content);
    }

    private function read(): string
    {
        if (! $this->isWritable()) {
            throw new RuntimeException(sprintf('%s does not exist or cannot be written.', $this->path));
        }

        $content = file_get_contents($this->path);

        if ($content === false) {
            throw new RuntimeException(sprintf('%s cannot be read.', $this->path));
        }

        return $content;
    }

    private function write(string $content): void
    {
        if (file_put_contents($this->path, $content, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('%s cannot be written.', $this->path));
        }
    }

    /**
     * The keys are URL-unreserved characters and go unquoted; anything else is double-quoted.
     */
    private static function quote(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9._~\/:-]*$/D', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
