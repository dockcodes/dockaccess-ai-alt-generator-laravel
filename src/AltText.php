<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel;

use Closure;
use Dock\AltGenerator\AltGenerator;
use Dock\AltGenerator\Exception\InvalidImageException;
use Dock\AltGenerator\GenerateOptions;
use Dock\AltGenerator\Image\ImagePolicy;
use Dock\AltGenerator\Image\ImageSanitizer;
use Dock\AltGenerator\Image\ImageSource;
use Dock\AltGenerator\Image\PreparedImage;
use Dock\AltGenerator\Language;
use Dock\AltGenerator\Laravel\Support\ConfigFactory;
use Dock\AltGenerator\Result\GenerationResult;
use Dock\AltGenerator\Result\Usage;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use SplFileInfo;

/**
 * The generator as a Laravel service - what the `AltText` facade calls.
 *
 *     $alt = AltText::generate($request->file('photo'))->alt()->text();
 *     $alt = AltText::generateFromDisk('products/42.jpg', 's3', ['context' => $product->name])->alt()->text();
 *
 * Every image is verified and sanitized by the core before it is sent; a file that is not a genuine
 * image is refused with InvalidImageException and never leaves the server.
 */
class AltText
{
    private ?AltGenerator $generator = null;

    /**
     * @param Closure(): AltGenerator $generatorFactory resolved on first use, so the package works before it is connected
     * @param array<string, mixed> $settings
     * @param Closure(): string $locale
     */
    public function __construct(
        private readonly Closure $generatorFactory,
        private readonly ConnectionGuard $guard,
        private readonly Storage $storage,
        private readonly ImagePolicy $policy,
        private readonly array $settings,
        private readonly Closure $locale,
    ) {}

    /**
     * Generates alt text for an uploaded file, an absolute path inside `allowed_directories`, a
     * File, or an ImageSource built by hand.
     *
     * @param GenerateOptions|array{language?: string, tone?: string, context?: string, variants?: int, set_ref?: string}|null $options
     *
     * @throws \Dock\AltGenerator\Exception\AltGeneratorException see the core's README, plus GeneratorUnavailableException
     */
    public function generate(ImageSource|UploadedFile|SplFileInfo|string $image, GenerateOptions|array|null $options = null): GenerationResult
    {
        $source = $this->source($image);
        // Built first: an application that was never connected hears that, not a verification failure.
        $generator = $this->generator();

        $this->guard->ensureActive();

        return $generator->generate($source, $this->options($options));
    }

    /**
     * Generates alt text for a file on a Storage disk - local, S3 or any other.
     *
     * @param GenerateOptions|array{language?: string, tone?: string, context?: string, variants?: int, set_ref?: string}|null $options
     */
    public function generateFromDisk(string $path, ?string $disk = null, GenerateOptions|array|null $options = null): GenerationResult
    {
        return $this->generate($this->sourceFromDisk($path, $disk), $options);
    }

    /**
     * Verifies and sanitizes without sending anything - no connection needed. For refusing a bad
     * file at upload time; the `GenuineImage` rule does this.
     *
     * @throws InvalidImageException
     */
    public function prepare(ImageSource|UploadedFile|SplFileInfo|string $image): PreparedImage
    {
        return (new ImageSanitizer($this->policy))->prepare($this->source($image));
    }

    /**
     * The account's usage in the current window: the day on the free plan, the month on paid plans.
     */
    public function usage(): Usage
    {
        return $this->generator()->usage();
    }

    /**
     * The configured defaults with the given overrides applied. The language follows the
     * application locale unless the configuration or the call names one.
     *
     * @param GenerateOptions|array{language?: string, tone?: string, context?: string, variants?: int, set_ref?: string}|null $options
     */
    public function options(GenerateOptions|array|null $options = null): GenerateOptions
    {
        if ($options instanceof GenerateOptions) {
            return $options;
        }

        $defaults = (array) ($this->settings['defaults'] ?? []);
        $options = array_merge(array_filter($defaults, static fn ($value) => $value !== null && $value !== ''), $options ?? []);

        $locale = ConfigFactory::string($options['language'] ?? null) ?? ($this->locale)();
        $fallback = ConfigFactory::string($defaults['fallback_language'] ?? null) ?? 'en';

        $result = GenerateOptions::create()
            ->withLanguage(Language::normalize($locale, $fallback))
            ->withTone((string) ($options['tone'] ?? 'neutral'))
            ->withVariants((int) ($options['variants'] ?? 1));

        if (($context = ConfigFactory::string($options['context'] ?? null)) !== null) {
            $result = $result->withContext($context);
        }

        // The site token keeps every image of this application in one set of the account, as the CMS plugins do.
        $setRef = ConfigFactory::string($options['set_ref'] ?? null) ?? ConfigFactory::string($this->settings['site_token'] ?? null);

        return $setRef === null ? $result : $result->withSetRef($setRef);
    }

    /**
     * @throws InvalidImageException
     */
    public function source(ImageSource|UploadedFile|SplFileInfo|string $image): ImageSource
    {
        if ($image instanceof ImageSource) {
            return $image;
        }

        $maxBytes = $this->policy->maxBytes();

        if ($image instanceof UploadedFile) {
            if (! $image->isValid()) {
                throw new InvalidImageException(InvalidImageException::EMPTY_FILE, 'The upload did not arrive in full.');
            }

            // Read through the framework (fake uploads in tests are not is_uploaded_file()), size checked first.
            if ($image->getSize() > $maxBytes) {
                throw new InvalidImageException(InvalidImageException::TOO_LARGE, 'The image is larger than allowed.');
            }

            return ImageSource::fromBytes((string) $image->get(), $image->getClientOriginalName(), $maxBytes);
        }

        $path = $image instanceof SplFileInfo ? $image->getPathname() : $image;

        return ImageSource::fromFile($path, $this->allowedDirectories(), $maxBytes);
    }

    /**
     * @throws InvalidImageException
     */
    public function sourceFromDisk(string $path, ?string $disk = null): ImageSource
    {
        $filesystem = $this->storage->disk($disk);
        $maxBytes = $this->policy->maxBytes();

        try {
            if (! $filesystem->exists($path)) {
                throw new InvalidImageException(InvalidImageException::NOT_ALLOWED, 'The image does not exist on the disk.');
            }

            if ($filesystem->size($path) > $maxBytes) {
                throw new InvalidImageException(InvalidImageException::TOO_LARGE, 'The image is larger than allowed.');
            }

            $bytes = $filesystem->get($path);
        } catch (FileNotFoundException) {
            throw new InvalidImageException(InvalidImageException::NOT_ALLOWED, 'The image does not exist on the disk.');
        }

        return ImageSource::fromBytes((string) $bytes, basename($path), $maxBytes);
    }

    public function guard(): ConnectionGuard
    {
        return $this->guard;
    }

    private function generator(): AltGenerator
    {
        return $this->generator ??= ($this->generatorFactory)();
    }

    /**
     * @return list<string>
     */
    private function allowedDirectories(): array
    {
        return array_values(array_filter(
            array_map(static fn ($dir) => ConfigFactory::string($dir), (array) ($this->settings['allowed_directories'] ?? [])),
            static fn (?string $dir) => $dir !== null,
        ));
    }
}
