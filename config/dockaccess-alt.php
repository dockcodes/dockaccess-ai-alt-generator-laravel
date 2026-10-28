<?php

/*
|--------------------------------------------------------------------------
| dockaccess.org alt generator
|--------------------------------------------------------------------------
|
| The three secrets come from `php artisan dockaccess-alt:connect`, which
| connects this application to a site in your dockaccess.org account and
| writes them to `.env`. Keep them out of version control.
|
*/

return [

    // The key the generator is called with (the account's key, not a site token).
    'account_key' => env('DOCKACCESS_ALT_KEY'),

    // The site in the account and the connection secret, used to check that the
    // connection is still on before generating (see `verify` below).
    'site_token' => env('DOCKACCESS_ALT_SITE_TOKEN'),
    'connection_secret' => env('DOCKACCESS_ALT_SECRET'),

    /*
    | Before generating, the package asks dockaccess.org whether the generator is
    | still on for the site, and remembers the answer for `ttl` seconds. When the
    | owner disconnects the application in the account, switches the generator off
    | or the plan pauses it, generation stops within that time. If dockaccess.org
    | cannot be reached, generation goes on: the generator itself still enforces
    | the account's limits.
    */
    'verify' => [
        'enabled' => (bool) env('DOCKACCESS_ALT_VERIFY', true),
        'ttl' => (int) env('DOCKACCESS_ALT_VERIFY_TTL', 300),
        // A cache store name from config/cache.php; null uses the default store.
        'cache_store' => env('DOCKACCESS_ALT_CACHE_STORE'),
    ],

    // What every generation asks for unless the call says otherwise.
    'defaults' => [
        // A locale ("pl_PL", "de-AT"); null follows app()->getLocale().
        'language' => env('DOCKACCESS_ALT_LANGUAGE'),
        // Used when the locale is not a supported language.
        'fallback_language' => 'en',
        // One of Dock\AltGenerator\Tone::ALL.
        'tone' => env('DOCKACCESS_ALT_TONE', 'neutral'),
        // 1 to 3 alternatives per image.
        'variants' => 1,
        // Groups the images of this application into one set in the account; null uses the site token.
        'set_ref' => env('DOCKACCESS_ALT_SET_REF'),
    ],

    /*
    | Absolute paths are read only inside these directories (symlinks resolved).
    | Name the narrowest directories that hold images, never the project root.
    | Files on a Storage disk (`AltText::generateFromDisk()`) are read through the
    | disk and do not need to be listed.
    */
    'allowed_directories' => [
        storage_path('app/public'),
    ],

    'image' => [
        // auto: re-encode with GD, strip metadata byte by byte where GD cannot;
        // reencode: re-encode or refuse; strip: never decode.
        'mode' => env('DOCKACCESS_ALT_IMAGE_MODE', 'auto'),
        // Largest file read, in bytes (at most 10 MiB).
        'max_bytes' => 10 * 1024 * 1024,
        // Longest side of the image that is sent, in pixels.
        'max_edge' => 2048,
    ],

    // The longest alt handed back, in characters (16 to 2000).
    'max_alt_length' => 2000,

    'http' => [
        'connect_timeout' => 5000, // ms
        'timeout' => 90000, // ms - a generation includes an AI model call
        'connect_retries' => 1, // only failures before anything was sent
        // An outgoing proxy, "host:port" or "http://user:pass@host:port".
        'proxy' => env('DOCKACCESS_ALT_PROXY'),
        // A CA bundle for hosts whose system store is outdated. Verification is never switched off.
        'ca_bundle' => env('DOCKACCESS_ALT_CA_BUNDLE'),
    ],

    // Where `GenerateAltText` jobs go; null uses the defaults of config/queue.php.
    'queue' => [
        'connection' => env('DOCKACCESS_ALT_QUEUE_CONNECTION'),
        'queue' => env('DOCKACCESS_ALT_QUEUE'),
    ],

    // Only for tests against a staging environment.
    'base_url' => env('DOCKACCESS_ALT_URL', 'https://alt.dockaccess.org'),
    'connection_url' => env('DOCKACCESS_ALT_CONNECTION_URL', 'https://dockaccess.org/fast-api'),

];
