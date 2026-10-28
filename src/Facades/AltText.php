<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Facades;

use Dock\AltGenerator\Connection\ConnectionClient;
use Dock\AltGenerator\Http\TransportInterface;
use Dock\AltGenerator\Laravel\AltText as AltTextService;
use Dock\AltGenerator\Laravel\ConnectionGuard;
use Dock\AltGenerator\Laravel\Testing\FakeTransport;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Dock\AltGenerator\Result\GenerationResult generate(\Dock\AltGenerator\Image\ImageSource|\Illuminate\Http\UploadedFile|\SplFileInfo|string $image, \Dock\AltGenerator\GenerateOptions|array<string, mixed>|null $options = null)
 * @method static \Dock\AltGenerator\Result\GenerationResult generateFromDisk(string $path, ?string $disk = null, \Dock\AltGenerator\GenerateOptions|array<string, mixed>|null $options = null)
 * @method static \Dock\AltGenerator\Image\PreparedImage prepare(\Dock\AltGenerator\Image\ImageSource|\Illuminate\Http\UploadedFile|\SplFileInfo|string $image)
 * @method static \Dock\AltGenerator\Result\Usage usage()
 * @method static \Dock\AltGenerator\GenerateOptions options(\Dock\AltGenerator\GenerateOptions|array<string, mixed>|null $options = null)
 * @method static \Dock\AltGenerator\Image\ImageSource source(\Dock\AltGenerator\Image\ImageSource|\Illuminate\Http\UploadedFile|\SplFileInfo|string $image)
 * @method static \Dock\AltGenerator\Image\ImageSource sourceFromDisk(string $path, ?string $disk = null)
 * @method static ConnectionGuard guard()
 *
 * @see AltTextService
 */
final class AltText extends Facade
{
    /**
     * Answers every generation with the given alts (in turn, the last one repeated) instead of
     * calling dockaccess.org, and skips the connection check. Images are still verified and
     * sanitized as in production, so a test feeding a broken file still sees InvalidImageException.
     */
    public static function fake(string ...$alts): FakeTransport
    {
        $app = static::getFacadeApplication() ?? throw new \RuntimeException('AltText::fake() needs a booted application.');
        $transport = FakeTransport::alts(...($alts === [] ? ['An image'] : $alts));

        $app->instance(TransportInterface::class, $transport);
        $config = $app->make('config');
        $config->set('dockaccess-alt.account_key', $config->get('dockaccess-alt.account_key') ?: 'fake-account-key');
        $config->set('dockaccess-alt.verify.enabled', false);

        foreach ([AltTextService::class, ConnectionGuard::class, ConnectionClient::class, \Dock\AltGenerator\AltGenerator::class] as $abstract) {
            $app->forgetInstance($abstract);
        }

        static::clearResolvedInstance(AltTextService::class);

        return $transport;
    }

    protected static function getFacadeAccessor(): string
    {
        return AltTextService::class;
    }
}
