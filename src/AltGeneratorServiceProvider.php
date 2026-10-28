<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel;

use Dock\AltGenerator\AltGenerator;
use Dock\AltGenerator\Connection\ConnectionClient;
use Dock\AltGenerator\Http\TransportInterface;
use Dock\AltGenerator\Laravel\Console\ConnectCommand;
use Dock\AltGenerator\Laravel\Console\DisconnectCommand;
use Dock\AltGenerator\Laravel\Console\GenerateCommand;
use Dock\AltGenerator\Laravel\Console\StatusCommand;
use Dock\AltGenerator\Laravel\Support\ConfigFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Wires the core into Laravel. Nothing here talks to dockaccess.org at boot: the generator is
 * built on first use, so an application that is not connected yet still boots and can run
 * `dockaccess-alt:connect`.
 */
final class AltGeneratorServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dockaccess-alt.php', 'dockaccess-alt');

        $this->app->singleton(AltGenerator::class, static fn (Application $app): AltGenerator => new AltGenerator(
            ConfigFactory::make(self::settings($app), self::userAgent($app)),
            self::transport($app),
        ));

        // With the key when there is one (alt updates need it), without it for connecting.
        $this->app->singleton(ConnectionClient::class, static fn (Application $app): ConnectionClient => new ConnectionClient(
            ConfigFactory::make(self::settings($app), self::userAgent($app)),
            self::transport($app),
        ));

        $this->app->singleton(ConnectionGuard::class, static function (Application $app): ConnectionGuard {
            $settings = self::settings($app);

            return new ConnectionGuard(
                static fn (): ConnectionClient => $app->make(ConnectionClient::class),
                $app->make(CacheFactory::class)->store(ConfigFactory::string($settings['verify']['cache_store'] ?? null)),
                $settings,
                $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $this->app->singleton(AltText::class, static function (Application $app): AltText {
            $settings = self::settings($app);

            return new AltText(
                static fn (): AltGenerator => $app->make(AltGenerator::class),
                $app->make(ConnectionGuard::class),
                $app->make(Storage::class),
                ConfigFactory::make($settings, self::userAgent($app), false)->imagePolicy(),
                $settings,
                static fn (): string => $app->getLocale(),
            );
        });

        $this->app->alias(AltText::class, 'dockaccess-alt');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'dockaccess-alt');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/dockaccess-alt.php' => $this->app->configPath('dockaccess-alt.php')], 'dockaccess-alt-config');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/dockaccess-alt')], 'dockaccess-alt-lang');

        $this->commands([ConnectCommand::class, StatusCommand::class, DisconnectCommand::class, GenerateCommand::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function settings(Application $app): array
    {
        return (array) $app->make('config')->get('dockaccess-alt', []);
    }

    private static function userAgent(Application $app): string
    {
        return 'laravel/'.$app->version().' dockaccess-alt-laravel/'.self::VERSION;
    }

    /**
     * A transport bound in the container (AltText::fake(), or one going through the application's
     * proxy setup) replaces cURL; it must keep the guarantees listed in the core's README.
     */
    private static function transport(Application $app): ?TransportInterface
    {
        return $app->bound(TransportInterface::class) ? $app->make(TransportInterface::class) : null;
    }
}
