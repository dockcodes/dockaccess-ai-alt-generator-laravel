<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Rules;

use Closure;
use Dock\AltGenerator\Exception\InvalidImageException;
use Dock\AltGenerator\Laravel\AltText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * The upload is a genuine image the generator will accept - checked by its content with the same
 * inspection the generator runs before sending, not by its extension or MIME type:
 *
 *     $request->validate(['photo' => ['required', 'file', new GenuineImage]]);
 *
 * A PHP file renamed to .jpg, a JPEG with code in a comment, an SVG or a decompression bomb fails
 * here. No connection to dockaccess.org is needed and nothing is sent.
 */
final class GenuineImage implements ValidationRule
{
    public function __construct(private readonly ?AltText $altText = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('dockaccess-alt::messages.validation.not_a_file')->translate();

            return;
        }

        try {
            ($this->altText ?? app(AltText::class))->prepare($value);
        } catch (InvalidImageException $e) {
            $fail('dockaccess-alt::messages.validation.'.$e->getReason())->translate();
        }
    }
}
