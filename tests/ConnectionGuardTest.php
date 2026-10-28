<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Exception\TransportException;
use Dock\AltGenerator\Http\TransportInterface;
use Dock\AltGenerator\Laravel\AltText as AltTextService;
use Dock\AltGenerator\Laravel\ConnectionGuard;
use Dock\AltGenerator\Laravel\Exceptions\GeneratorUnavailableException;
use Dock\AltGenerator\Laravel\Facades\AltText;
use Dock\AltGenerator\Laravel\Testing\FakeTransport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final class ConnectionGuardTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::alts('Rudy kot');
        $this->app->instance(TransportInterface::class, $this->transport);
    }

    public function test_it_verifies_once_and_remembers_the_answer(): void
    {
        $this->generate();
        $this->generate();

        $this->assertSame(1, $this->requestsTo('/integrations/verify'));
        $this->transport->assertGenerated(2);
    }

    public function test_a_disconnected_application_stops_generating(): void
    {
        $this->transport->respond('/integrations/verify', FakeTransport::json(['success' => false, 'reason' => 'disconnected', 'detail' => 'Disconnected.'], 403));

        foreach ([1, 2] as $attempt) {
            try {
                $this->generate();
                $this->fail('A disconnected application generated.');
            } catch (GeneratorUnavailableException $e) {
                $this->assertSame(GeneratorUnavailableException::DISCONNECTED, $e->getReason());
            }
        }

        $this->transport->assertNothingGenerated();
        // The answer is remembered: the account is not asked again on every image.
        $this->assertSame(1, $this->requestsTo('/integrations/verify'));
    }

    public function test_a_paused_plan_stops_generating(): void
    {
        $this->transport->respond('/integrations/verify', FakeTransport::json(['alt_enabled' => false, 'token' => ['alt_enabled' => true, 'alt_paused' => true]]));

        $this->expectExceptionObject(new GeneratorUnavailableException(GeneratorUnavailableException::PAUSED));

        $this->generate();
    }

    public function test_an_outage_of_the_account_does_not_stop_the_site(): void
    {
        Log::spy();
        $this->transport->respond('/integrations/verify', new TransportException('Could not reach the alt generator: timeout (cURL error 28).', true, 28));

        $this->assertSame('Rudy kot', $this->generate());

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_verification_can_be_switched_off(): void
    {
        config(['dockaccess-alt.verify.enabled' => false]);
        $this->app->forgetInstance(ConnectionGuard::class);
        $this->app->forgetInstance(AltTextService::class);

        $this->generate();

        $this->assertSame(0, $this->requestsTo('/integrations/verify'));
    }

    public function test_without_a_stored_connection_there_is_nothing_to_verify(): void
    {
        config(['dockaccess-alt.connection_secret' => null]);
        $this->app->forgetInstance(ConnectionGuard::class);
        $this->app->forgetInstance(AltTextService::class);

        $this->generate();

        $this->assertSame(0, $this->requestsTo('/integrations/verify'));
    }

    private function generate(): string
    {
        return AltText::generate(UploadedFile::fake()->createWithContent('kot.png', self::png()))->alt()->text();
    }

    private function requestsTo(string $path): int
    {
        return count(array_filter($this->transport->requests(), static fn ($request) => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), $path)));
    }
}
