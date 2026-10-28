<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Laravel\Rules\GenuineImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final class GenuineImageTest extends TestCase
{
    public function test_a_genuine_image_passes(): void
    {
        $validator = Validator::make(['photo' => UploadedFile::fake()->createWithContent('kot.png', self::png())], ['photo' => [new GenuineImage]]);

        $this->assertTrue($validator->passes());
    }

    public function test_a_script_with_an_image_name_fails(): void
    {
        $validator = Validator::make(['photo' => UploadedFile::fake()->createWithContent('kot.png', self::phpPretendingToBePng())], ['photo' => [new GenuineImage]]);

        $this->assertTrue($validator->fails());
        $this->assertStringStartsWith('The photo ', (string) $validator->errors()->first('photo'));
    }

    public function test_the_message_is_translated(): void
    {
        $this->app->setLocale('pl');

        $validator = Validator::make(['photo' => 'not a file'], ['photo' => [new GenuineImage]]);

        $this->assertSame('Pole photo musi zawierać przesłany obrazek.', $validator->errors()->first('photo'));
    }

    public function test_both_languages_carry_the_same_messages(): void
    {
        $this->assertSame(
            array_keys(require __DIR__.'/../lang/en/messages.php'),
            array_keys(require __DIR__.'/../lang/pl/messages.php'),
        );
        $this->assertSame(
            array_keys((require __DIR__.'/../lang/en/messages.php')['validation']),
            array_keys((require __DIR__.'/../lang/pl/messages.php')['validation']),
        );
    }
}
