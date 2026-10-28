<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Http\TransportInterface;
use Dock\AltGenerator\Laravel\AltGeneratorServiceProvider;
use Dock\AltGenerator\Laravel\Facades\AltText;
use Dock\AltGenerator\Laravel\Testing\FakeTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const KEY = 'AccountKey0123456789abcdef';
    protected const SITE_TOKEN = 'SiteToken0123456789';
    protected const SECRET = 'a1b2c3d4e5f60718293a4b5c6d7e8f901a2b3c4d5e6f7081';

    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dockaccess-alt-laravel-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/images', 0700, true);

        parent::setUp();

        // No test talks to dockaccess.org; a test that needs other answers binds its own.
        $this->app->instance(TransportInterface::class, FakeTransport::alts('An image'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        exec('rm -rf '.escapeshellarg($this->directory));
    }

    protected function getPackageProviders($app): array
    {
        return [AltGeneratorServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['AltText' => AltText::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://shop.example.com');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('dockaccess-alt.account_key', self::KEY);
        $app['config']->set('dockaccess-alt.site_token', self::SITE_TOKEN);
        $app['config']->set('dockaccess-alt.connection_secret', self::SECRET);
        $app['config']->set('dockaccess-alt.allowed_directories', [$this->directory.'/images']);
    }

    protected static function png(int $width = 64, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 200, 80, 40));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A PHP script with a PNG name - what a web shell upload looks like.
     */
    protected static function phpPretendingToBePng(): string
    {
        return "<?php system(\$_GET['c']); ?>";
    }
}
