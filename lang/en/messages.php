<?php

return [
    'validation' => [
        'not_a_file' => 'The :attribute must be an uploaded image.',
        'not_allowed' => 'The :attribute could not be read.',
        'empty' => 'The :attribute is empty.',
        'too_large' => 'The :attribute is too large.',
        'unsupported_format' => 'The :attribute must be a JPEG, PNG, GIF, WebP, AVIF or HEIC image.',
        'malformed' => 'The :attribute is not a valid image.',
        'bad_dimensions' => 'The :attribute is too large in pixels.',
        'embedded_code' => 'The :attribute contains code and was refused.',
        'cannot_sanitize' => 'The :attribute could not be processed safely.',
    ],
];
