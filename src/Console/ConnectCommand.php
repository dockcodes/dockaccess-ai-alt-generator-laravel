<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Console;

use Dock\AltGenerator\Connection\Connection;
use Dock\AltGenerator\Connection\ConnectionClient;
use Dock\AltGenerator\Connection\ConnectRequest;
use Dock\AltGenerator\Exception\AltGeneratorException;
use Dock\AltGenerator\Exception\ConnectionException;
use Dock\AltGenerator\Laravel\AltGeneratorServiceProvider;
use Dock\AltGenerator\Laravel\Console\Concerns\ExplainsConnectionErrors;
use Dock\AltGenerator\Laravel\Console\Concerns\ReadsInput;
use Dock\AltGenerator\Laravel\ConnectionGuard;
use Dock\AltGenerator\Laravel\Support\EnvironmentFile;
use Illuminate\Console\Command;

/**
 * Connects this application to a site in a dockaccess.org account, as the CMS plugins do, and
 * writes the account key, the site token and the connection secret to `.env`.
 *
 * The password is asked for without echo and used once; it is not stored anywhere.
 */
final class ConnectCommand extends Command
{
    use ExplainsConnectionErrors;
    use ReadsInput;

    public const SYSTEM = 'laravel';

    protected $signature = 'dockaccess-alt:connect
        {--token= : The site token, from the alt generator tab of the site in your dockaccess.org account}
        {--login= : The e-mail address of the dockaccess.org account}
        {--url= : The address of this application, one of the domains of the site (default: APP_URL)}
        {--overwrite : Replace a connection another installation made with the same address}
        {--show : Print the three values instead of writing them to .env}';

    protected $description = 'Connect this application to the alt generator of your dockaccess.org account';

    public function handle(ConnectionClient $client, ConnectionGuard $guard): int
    {
        $token = $this->optionOrAsk('token', 'Site token (alt generator tab of the site in your account)');
        $login = $this->optionOrAsk('login', 'E-mail address of the dockaccess.org account');
        $password = $this->password();
        $url = $this->stringOption('url') ?? (string) config('app.url');

        try {
            $request = ConnectRequest::create($token, $login, $password, self::SYSTEM, $url)
                ->withVersions($this->laravel->version(), AltGeneratorServiceProvider::VERSION)
                ->withOverwrite((bool) $this->option('overwrite'));
        } catch (AltGeneratorException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $connection = $this->connect($client, $request, $url);

        if ($connection === null) {
            return self::FAILURE;
        }

        $values = [
            'DOCKACCESS_ALT_KEY' => $connection->accountKey()->reveal(),
            'DOCKACCESS_ALT_SITE_TOKEN' => $connection->siteToken(),
            'DOCKACCESS_ALT_SECRET' => $connection->secret()->reveal(),
        ];

        $env = new EnvironmentFile($this->laravel->environmentFilePath());

        if ($this->option('show') || ! $env->isWritable()) {
            if (! $this->option('show')) {
                $this->components->warn($env->path().' cannot be written. Put these values into the environment of the application:');
            }

            foreach ($values as $name => $value) {
                $this->line($name.'='.$value);
            }
        } else {
            $env->set($values);
            $this->components->info('The keys were written to '.$env->path().'.');
        }

        $guard->forget();

        $this->components->twoColumnDetail('Connected as', $connection->accountName() ?: $login);
        $this->components->twoColumnDetail('Address', $connection->homeUrl());
        $this->components->twoColumnDetail('Shown in the account as', $connection->system());

        if (! $connection->isDomainVerified()) {
            $this->components->warn('This is a local address. Connect again from the production domain before going live.');
        }

        if (! $connection->isAltEnabled()) {
            $this->components->warn('The alt generator is switched off for this site. Switch it on in your dockaccess.org account.');
        }

        if ($this->laravel->configurationIsCached()) {
            $this->components->warn('The configuration is cached. Run "php artisan config:cache" to load the new keys.');
        }

        return self::SUCCESS;
    }

    private function connect(ConnectionClient $client, ConnectRequest $request, string $url): ?Connection
    {
        try {
            return $client->connect($request);
        } catch (ConnectionException $e) {
            if ($e->getReason() === ConnectionException::ALREADY_CONNECTED && ! $this->option('overwrite') && $this->input->isInteractive()
                && $this->confirm('Another installation is connected with '.$url.'. Replace that connection?')) {
                return $this->connect($client, $request->withOverwrite(), $url);
            }

            $this->components->error($this->explain($e));
        } catch (AltGeneratorException $e) {
            $this->components->error($e->getMessage());
        }

        return null;
    }
}
