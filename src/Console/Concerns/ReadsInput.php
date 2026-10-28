<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Console\Concerns;

/**
 * Arguments and options as the types the command needs. Console input is typed as anything
 * (an option can be an array, a flag a bool), so it is checked here instead of cast.
 *
 * @internal
 */
trait ReadsInput
{
    /**
     * The option's text, trimmed; null when it is not given, empty or not text.
     */
    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * DOCKACCESS_PASSWORD (CI), otherwise asked without echo; empty without a terminal to ask on.
     */
    protected function password(): string
    {
        $password = getenv('DOCKACCESS_PASSWORD');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        $answer = $this->input->isInteractive() ? $this->secret('Password') : null;

        return is_string($answer) ? $answer : '';
    }

    /**
     * A non-empty answer from the option, or from the prompt when the option is missing.
     */
    protected function optionOrAsk(string $name, string $question): string
    {
        $value = $this->stringOption($name) ?? $this->ask($question);

        return is_string($value) ? trim($value) : '';
    }
}
