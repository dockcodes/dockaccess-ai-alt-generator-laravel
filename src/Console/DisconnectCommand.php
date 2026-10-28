<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Console;

use Dock\AltGenerator\Connection\ConnectionClient;
use Dock\AltGenerator\Exception\AltGeneratorException;
use Dock\AltGenerator\Exception\ConnectionException;
use Dock\AltGenerator\Laravel\Console\Concerns\ExplainsConnectionErrors;
use Dock\AltGenerator\Laravel\ConnectionGuard;
use Dock\AltGenerator\Laravel\Support\EnvironmentFile;
use Illuminate\Console\Command;

/**
 * Disconnects this application from the account and removes its keys from `.env`. The account
 * can do the same from its side (alt generator tab of the site), without the password.
 */
final class DisconnectCommand extends Command
{
    use ExplainsConnectionErrors;

    protected $signature = 'dockaccess-alt:disconnect
        {--login= : The e-mail address of the dockaccess.org account}
        {--url= : The address this application was connected with (default: APP_URL)}
        {--keep-env : Keep the keys in .env}';

    protected $description = 'Disconnect this application from the alt generator of your dockaccess.org account';

    private const VARIABLES = ['DOCKACCESS_ALT_KEY', 'DOCKACCESS_ALT_SITE_TOKEN', 'DOCKACCESS_ALT_SECRET'];

    public function handle(ConnectionClient $client, ConnectionGuard $guard): int
    {
        $token = (string) config('dockaccess-alt.site_token');

        if ($token === '') {
            $this->components->error('This application is not connected.');

            return self::FAILURE;
        }

        $login = trim((string) ($this->option('login') ?: $this->ask('E-mail address of the dockaccess.org account')));
        $password = (string) (getenv('DOCKACCESS_PASSWORD') ?: ($this->input->isInteractive() ? $this->secret('Password') : ''));
        $url = (string) ($this->option('url') ?: config('app.url'));

        try {
            $client->disconnect($token, $login, $password, $url);
        } catch (ConnectionException $e) {
            // Gone on their side already: forget it here as well.
            if ($e->getReason() !== ConnectionException::NOT_CONNECTED) {
                $this->components->error($this->explain($e));

                return self::FAILURE;
            }
        } catch (AltGeneratorException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $guard->forget();
        $this->components->info('Disconnected.');

        $env = new EnvironmentFile($this->laravel->environmentFilePath());

        if (! $this->option('keep-env') && $env->isWritable()) {
            $env->remove(self::VARIABLES);
            $this->components->info('The keys were removed from '.$env->path().'.');
        } elseif (! $this->option('keep-env')) {
            $this->components->warn('Remove '.implode(', ', self::VARIABLES).' from the environment of the application.');
        }

        return self::SUCCESS;
    }
}
