<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Laravel\AltGeneratorServiceProvider;
use Dock\AltGenerator\Laravel\Facades\AltText;
use Dock\AltGenerator\Laravel\Testing\FakeTransport;
use Illuminate\Support\Facades\Artisan;

final class CommandsTest extends TestCase
{
    private const NEW_KEY = 'NewAccountKey0123456789';
    private const NEW_SECRET = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa9988';

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents($this->directory.'/.env', "APP_NAME=Shop\n");
        $this->app->useEnvironmentPath($this->directory);
        $this->transport = AltText::fake('Rudy kot');
        // fake() switches verification off; the commands talk to the account directly.
        config(['dockaccess-alt.verify.enabled' => true]);
    }

    public function test_connect_writes_the_keys_to_the_environment_file(): void
    {
        $this->transport->respond('/integrations/connect', $this->connected());

        $this->artisan('dockaccess-alt:connect', ['--token' => self::SITE_TOKEN, '--login' => 'anna@example.com'])
            ->expectsQuestion('Password', 'Hunter2!')
            ->expectsOutputToContain('Anna Nowak')
            ->assertSuccessful();

        $env = (string) file_get_contents($this->directory.'/.env');
        $this->assertStringContainsString('DOCKACCESS_ALT_KEY='.self::NEW_KEY."\n", $env);
        $this->assertStringContainsString('DOCKACCESS_ALT_SITE_TOKEN='.self::SITE_TOKEN."\n", $env);
        $this->assertStringContainsString('DOCKACCESS_ALT_SECRET='.self::NEW_SECRET."\n", $env);
        $this->assertStringStartsWith("APP_NAME=Shop\n", $env);

        $payload = json_decode((string) $this->transport->requests()[0]->body(), true);
        $this->assertSame('laravel', $payload['system']);
        $this->assertSame('https://shop.example.com', $payload['home_url']);
        $this->assertSame(AltGeneratorServiceProvider::VERSION, $payload['module_version']);
        $this->assertSame('Hunter2!', $payload['password']);
    }

    public function test_connect_asks_before_replacing_another_installation(): void
    {
        $this->transport->respond(
            '/integrations/connect',
            FakeTransport::json(['success' => false, 'reason' => 'already_connected', 'detail' => 'Already connected.'], 409),
            $this->connected(),
        );

        $this->artisan('dockaccess-alt:connect', ['--token' => self::SITE_TOKEN, '--login' => 'anna@example.com'])
            ->expectsQuestion('Password', 'Hunter2!')
            ->expectsConfirmation('Another installation is connected with https://shop.example.com. Replace that connection?', 'yes')
            ->assertSuccessful();

        $second = json_decode((string) $this->transport->requests()[1]->body(), true);
        $this->assertTrue($second['overwrite']);
    }

    public function test_connect_explains_a_refusal(): void
    {
        $this->transport->respond('/integrations/connect', FakeTransport::json(['success' => false, 'reason' => 'domain_not_allowed', 'detail' => 'Token is not assigned to this domain.'], 403));

        $this->artisan('dockaccess-alt:connect', ['--token' => self::SITE_TOKEN, '--login' => 'anna@example.com', '--url' => 'https://other.example.com'])
            ->expectsQuestion('Password', 'Hunter2!')
            ->expectsOutputToContain('not one of the domains of the site')
            ->assertFailed();

        $this->assertStringNotContainsString('DOCKACCESS_ALT_KEY', (string) file_get_contents($this->directory.'/.env'));
    }

    public function test_connect_can_print_the_keys_instead(): void
    {
        $this->transport->respond('/integrations/connect', $this->connected());

        $this->artisan('dockaccess-alt:connect', ['--token' => self::SITE_TOKEN, '--login' => 'anna@example.com', '--show' => true])
            ->expectsQuestion('Password', 'Hunter2!')
            ->expectsOutputToContain('DOCKACCESS_ALT_KEY='.self::NEW_KEY)
            ->assertSuccessful();

        $this->assertSame("APP_NAME=Shop\n", file_get_contents($this->directory.'/.env'));
    }

    public function test_status_shows_the_connection_and_the_monthly_usage(): void
    {
        $this->transport->withUsage(15, 2000, 'month');

        $this->assertSame(0, Artisan::call('dockaccess-alt:status'));

        $output = Artisan::output();
        $this->assertStringContainsString('active', $output);
        $this->assertMatchesRegularExpression('/Alts this month \.+ 15 \/ 2000 \(1985 left\)/', $output);
    }

    public function test_status_says_when_the_account_disconnected_the_application(): void
    {
        $this->transport->respond('/integrations/verify', FakeTransport::json(['success' => false, 'reason' => 'disconnected', 'detail' => 'Disconnected.'], 403));

        $this->artisan('dockaccess-alt:status')
            ->expectsOutputToContain('connection was removed in the account')
            ->assertFailed();
    }

    public function test_disconnect_removes_the_keys(): void
    {
        file_put_contents($this->directory.'/.env', "APP_NAME=Shop\nDOCKACCESS_ALT_KEY=".self::KEY."\nDOCKACCESS_ALT_SITE_TOKEN=".self::SITE_TOKEN."\nDOCKACCESS_ALT_SECRET=".self::SECRET."\n");

        $this->artisan('dockaccess-alt:disconnect', ['--login' => 'anna@example.com'])
            ->expectsQuestion('Password', 'Hunter2!')
            ->assertSuccessful();

        $this->assertSame("APP_NAME=Shop\n", file_get_contents($this->directory.'/.env'));
        $this->assertStringEndsWith('/integrations/disconnect', $this->transport->requests()[0]->url());
    }

    public function test_generate_prints_the_alts(): void
    {
        file_put_contents($this->directory.'/images/kot.png', self::png());

        $this->artisan('dockaccess-alt:generate', ['path' => $this->directory.'/images/kot.png', '--language' => 'pl'])
            ->expectsOutputToContain('[pl] Rudy kot')
            ->assertSuccessful();
    }

    private function connected(): \Dock\AltGenerator\Http\Response
    {
        return FakeTransport::json([
            'success' => true,
            'external_token' => self::NEW_KEY,
            'user' => ['display_name' => 'Anna Nowak'],
            'token' => ['token' => self::SITE_TOKEN, 'alt_enabled' => true],
            'integration' => ['id' => 42, 'system' => 'laravel', 'home_url' => 'https://shop.example.com', 'secret' => self::NEW_SECRET, 'domain_verified' => true],
        ]);
    }
}
