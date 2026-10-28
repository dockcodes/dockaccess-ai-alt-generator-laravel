<?php

return [
    'validation' => [
        'not_a_file' => 'Pole :attribute musi zawierać przesłany obrazek.',
        'not_allowed' => 'Nie udało się odczytać pliku z pola :attribute.',
        'empty' => 'Plik z pola :attribute jest pusty.',
        'too_large' => 'Plik z pola :attribute jest za duży.',
        'unsupported_format' => 'Pole :attribute musi zawierać obrazek JPEG, PNG, GIF, WebP, AVIF albo HEIC.',
        'malformed' => 'Plik z pola :attribute nie jest poprawnym obrazkiem.',
        'bad_dimensions' => 'Obrazek z pola :attribute ma za duże wymiary.',
        'embedded_code' => 'Plik z pola :attribute zawiera kod i został odrzucony.',
        'cannot_sanitize' => 'Nie udało się bezpiecznie przetworzyć pliku z pola :attribute.',
    ],
];
