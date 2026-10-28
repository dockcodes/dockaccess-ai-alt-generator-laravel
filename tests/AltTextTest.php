<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Exception\ConfigurationException;
use Dock\AltGenerator\Exception\InvalidImageException;
use Dock\AltGenerator\Laravel\AltText as AltTextService;
use Dock\AltGenerator\Laravel\Facades\AltText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class AltTextTest extends TestCase
{
    public function test_it_generates_alt_text_for_an_upload(): void
    {
        $fake = AltText::fake('Rudy kot na parapecie');
        $this->app->setLocale('pl_PL');

        $result = AltText::generate(UploadedFile::fake()->createWithContent('kot.png', self::png()));

        $this->assertSame('Rudy kot na parapecie', $result->alt()->text());
        $fake->assertGenerated(1);

        parse_str((string) parse_url($fake->requests()[0]->url(), PHP_URL_QUERY), $query);
        $this->assertSame('pl', $query['lang']);
        // Every image of the application lands in one set of the account, named by the site token.
        $this->assertSame(self::SITE_TOKEN, $query['set_ref']);
        $this->assertSame(self::KEY, $query['key']);
    }

    public function test_the_call_overrides_the_configuration_and_the_configuration_the_locale(): void
    {
        config(['dockaccess-alt.defaults.language' => 'de_AT', 'dockaccess-alt.defaults.tone' => 'formal']);
        $this->app->forgetInstance(AltTextService::class);

        $options = AltText::options(['tone' => 'friendly', 'context' => 'Oak table']);

        $this->assertSame('de', $options->language());
        $this->assertSame('friendly', $options->tone());
        $this->assertSame('Oak table', $options->context());
    }

    public function test_an_unsupported_locale_falls_back(): void
    {
        $this->app->setLocale('xx');

        $this->assertSame('en', AltText::options()->language());
    }

    public function test_it_reads_images_from_a_storage_disk(): void
    {
        $fake = AltText::fake('A product photo');
        Storage::fake('s3')->put('products/42.png', self::png());

        $this->assertSame('A product photo', AltText::generateFromDisk('products/42.png', 's3')->alt()->text());
        $fake->assertGenerated(1);
    }

    public function test_a_missing_file_on_a_disk_is_refused_locally(): void
    {
        $fake = AltText::fake();
        Storage::fake('s3');

        $this->expectException(InvalidImageException::class);

        try {
            AltText::generateFromDisk('missing.png', 's3');
        } finally {
            $fake->assertNothingGenerated();
        }
    }

    public function test_absolute_paths_are_read_only_inside_the_allowed_directories(): void
    {
        $fake = AltText::fake('Inside');
        file_put_contents($this->directory.'/images/a.png', self::png());
        file_put_contents($this->directory.'/outside.png', self::png());

        $this->assertSame('Inside', AltText::generate($this->directory.'/images/a.png')->alt()->text());

        try {
            AltText::generate($this->directory.'/images/../outside.png');
            $this->fail('A file outside the allowed directories was read.');
        } catch (InvalidImageException $e) {
            $this->assertSame(InvalidImageException::NOT_ALLOWED, $e->getReason());
        }

        $fake->assertGenerated(1);
    }

    public function test_code_dressed_as_an_image_never_leaves_the_server(): void
    {
        $fake = AltText::fake();

        try {
            AltText::generate(UploadedFile::fake()->createWithContent('photo.png', self::phpPretendingToBePng()));
            $this->fail('A PHP file was accepted as an image.');
        } catch (InvalidImageException) {
            $fake->assertNothingGenerated();
        }
    }

    public function test_checking_an_image_needs_no_connection(): void
    {
        config(['dockaccess-alt.account_key' => null]);
        $this->app->forgetInstance(AltTextService::class);

        $prepared = AltText::prepare(UploadedFile::fake()->createWithContent('kot.png', self::png(3000, 1000)));

        // Scaled to the longest side the generator gets.
        $this->assertSame(2048, max($prepared->width(), $prepared->height()));
    }

    public function test_generating_without_a_connection_says_so(): void
    {
        config(['dockaccess-alt.account_key' => null]);
        $this->app->forgetInstance(AltTextService::class);
        $this->app->forgetInstance(\Dock\AltGenerator\AltGenerator::class);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Connect the site');

        AltText::generate(UploadedFile::fake()->createWithContent('kot.png', self::png()));
    }

    public function test_it_reads_a_monthly_usage(): void
    {
        AltText::fake()->withUsage(1990, 2000, 'month');

        $usage = AltText::usage();

        $this->assertTrue($usage->isMonthly());
        $this->assertSame(10, $usage->remaining());
    }
}
