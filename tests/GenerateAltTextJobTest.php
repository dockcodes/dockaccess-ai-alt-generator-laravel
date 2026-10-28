<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Laravel\Events\AltTextFailed;
use Dock\AltGenerator\Laravel\Events\AltTextGenerated;
use Dock\AltGenerator\Laravel\Facades\AltText;
use Dock\AltGenerator\Laravel\Jobs\GenerateAltText;
use Dock\AltGenerator\Laravel\Testing\FakeTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

final class GenerateAltTextJobTest extends TestCase
{
    public function test_a_generation_is_announced_with_the_context(): void
    {
        AltText::fake('A wooden chair');
        Storage::fake('public')->put('chairs/1.png', self::png());
        Event::fake([AltTextGenerated::class]);

        GenerateAltText::dispatchSync('chairs/1.png', 'public', ['language' => 'en'], ['product_id' => 7]);

        Event::assertDispatched(AltTextGenerated::class, function (AltTextGenerated $event): bool {
            return $event->alt === 'A wooden chair'
                && $event->locale === 'en'
                && $event->context === ['product_id' => 7]
                && $event->disk === 'public'
                && $event->alts === [['text' => 'A wooden chair', 'locale' => 'en']];
        });
    }

    public function test_a_spent_limit_fails_at_once(): void
    {
        AltText::fake()->respond('/api/generate', FakeTransport::json(
            ['detail' => "You've reached your monthly limit."],
            429,
            ['X-Alt-Reason' => 'limit_reached', 'X-Alt-Daily-Limit' => '2000', 'X-Alt-Limit-Period' => 'month'],
        ));
        Storage::fake('public')->put('chairs/1.png', self::png());
        Event::fake([AltTextFailed::class, AltTextGenerated::class]);

        GenerateAltText::dispatchSync('chairs/1.png', 'public', [], ['product_id' => 7]);

        Event::assertNotDispatched(AltTextGenerated::class);
        Event::assertDispatched(AltTextFailed::class, fn (AltTextFailed $event) => $event->reason === 'limit_reached' && $event->context === ['product_id' => 7]);
    }

    public function test_the_per_minute_cap_waits_as_long_as_asked(): void
    {
        AltText::fake()->respond('/api/generate', FakeTransport::json(['detail' => 'Too many requests'], 429, ['X-Alt-Reason' => 'rate_limited', 'Retry-After' => '17']));
        Storage::fake('public')->put('chairs/1.png', self::png());

        $job = (new GenerateAltText('chairs/1.png', 'public'))->withFakeQueueInteractions();
        $job->handle(app(\Dock\AltGenerator\Laravel\AltText::class), app('events'));

        $job->assertReleased(17);
    }

    public function test_a_refused_image_is_not_retried(): void
    {
        AltText::fake();
        Storage::fake('public')->put('shell.png', self::phpPretendingToBePng());

        $job = (new GenerateAltText('shell.png', 'public'))->withFakeQueueInteractions();
        $job->handle(app(\Dock\AltGenerator\Laravel\AltText::class), app('events'));

        $job->assertFailed();
        $job->assertNotReleased();
    }
}
