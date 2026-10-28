<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Console;

use Dock\AltGenerator\Exception\AltGeneratorException;
use Dock\AltGenerator\Laravel\AltText;
use Dock\AltGenerator\Laravel\Console\Concerns\ReadsInput;
use Illuminate\Console\Command;

/**
 * One generation from the command line - to try the connection and the settings on a real image.
 */
final class GenerateCommand extends Command
{
    use ReadsInput;

    protected $signature = 'dockaccess-alt:generate
        {path : A path on --disk, or an absolute path inside allowed_directories}
        {--disk= : A Storage disk to read the image from}
        {--language= : A locale, e.g. pl_PL (default: the application locale)}
        {--tone= : One of Dock\\AltGenerator\\Tone::ALL}
        {--context= : What the image is about, up to 500 characters}
        {--variants=1 : 1 to 3 alternatives}';

    protected $description = 'Generate alt text for one image';

    public function handle(AltText $altText): int
    {
        $variants = $this->stringOption('variants');
        $options = array_filter([
            'language' => $this->stringOption('language'),
            'tone' => $this->stringOption('tone'),
            'context' => $this->stringOption('context'),
        ], static fn (?string $value) => $value !== null) + ['variants' => is_numeric($variants) ? (int) $variants : 1];

        $path = $this->stringArgument('path');
        $disk = $this->stringOption('disk');

        try {
            $result = $disk !== null
                ? $altText->generateFromDisk($path, $disk, $options)
                : $altText->generate($path, $options);
        } catch (AltGeneratorException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->alts() as $alt) {
            $this->line('['.$alt->locale().'] '.$alt->text());
        }

        $this->newLine();
        $this->components->twoColumnDetail('Image in the account', $result->imageUuid());
        $this->components->twoColumnDetail('Sent', sprintf(
            '%s %d×%d, %s',
            $result->image()->format(),
            $result->image()->width(),
            $result->image()->height(),
            $result->image()->method()
        ));

        return self::SUCCESS;
    }
}
