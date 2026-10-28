# dockaccess alt generator for Laravel

Alt text for your images from the [dockaccess.org](https://dockaccess.org) AI alt generator, in a
Laravel application: a connection to your dockaccess.org account, a facade, a validation rule, a
queued job and Artisan commands.

It is built on the [PHP core](https://github.com/dockcodes/dockaccess-ai-alt-generator), so it
inherits what the core guarantees:

- **only genuine images leave the server.** Every file is identified from its content and rebuilt
  before it is sent. A `shell.php` renamed to `photo.png`, PHP hidden in a JPEG comment, an SVG
  with a script or a decompression bomb is refused locally.
- **no metadata leaks.** GPS positions, camera serials and author names stay on the server.
- **the account key does not leak** into exceptions, logs, dumps or serialized jobs.

## Requirements

- PHP 8.2 or newer, 64-bit, with `ext-curl` and `ext-json`
- Laravel 12 or 13 (Composer refuses to install 10 and 11: they carry unpatched security advisories)
- `ext-gd` with JPEG, PNG, GIF and WebP support, strongly recommended (see the core's README)
- a dockaccess.org account with the alt generator switched on for the site (the free plan works:
  1 site, 10 alts a day)

## Installation

```bash
composer require dockcodes/dockaccess-alt-generator-laravel
php artisan dockaccess-alt:connect
```

`dockaccess-alt:connect` asks for:

1. the **site token** - in your dockaccess.org account, open the site, then the *ALT generator* tab;
2. the e-mail address and the password of the account. The password is used once and is not stored.

It connects this application as `laravel`, the way the WordPress, PrestaShop and Joomla plugins
connect, and writes three values to `.env`:

```dotenv
DOCKACCESS_ALT_KEY=...
DOCKACCESS_ALT_SITE_TOKEN=...
DOCKACCESS_ALT_SECRET=...
```

The address of the application (`APP_URL`, or `--url`) has to be one of the domains of the site in
the account. A local address (`localhost`, a private IP) is accepted too, for development.

The connected application then appears in the account, in the *ALT generator* tab of the site,
with the time it last checked in. The owner can disconnect it there.

Run `php artisan dockaccess-alt:status` to see the connection and how many alts are left.

On servers where `.env` is not writable, or for a secrets manager, use `--show` to print the
values instead. In CI, pass `--no-interaction` with `--token`, `--login` and the password in the
`DOCKACCESS_PASSWORD` environment variable.

## Generating

```php
use Dock\AltGenerator\Laravel\Facades\AltText;

// An upload
$alt = AltText::generate($request->file('photo'))->alt()->text();

// A file on any Storage disk (local, S3, ...)
$alt = AltText::generateFromDisk('products/42.jpg', 's3')->alt()->text();

// With options
$result = AltText::generate($request->file('photo'), [
    'language' => 'de_AT',       // default: the application locale
    'tone' => 'descriptive',     // Dock\AltGenerator\Tone::ALL
    'context' => 'Product: oak dining table, 180 cm',
    'variants' => 3,             // 1 to 3 alternatives
]);

foreach ($result->alts() as $alt) {
    echo e($alt->text());
}
```

The alt is already plain text (no markup, control characters or bidirectional overrides), but it
is text from outside: escape it wherever it is printed, as Blade's `{{ }}` does.

Absolute paths are read only inside `allowed_directories` (default `storage/app/public`); files
on a Storage disk are read through the disk.

### In the background

```php
use Dock\AltGenerator\Laravel\Jobs\GenerateAltText;

GenerateAltText::dispatch('products/42.jpg', 's3', ['context' => $product->name], ['product_id' => $product->id]);
```

Listen for the result:

```php
use Dock\AltGenerator\Laravel\Events\AltTextGenerated;
use Dock\AltGenerator\Laravel\Events\AltTextFailed;

Event::listen(function (AltTextGenerated $event) {
    Product::whereKey($event->context['product_id'])->update(['image_alt' => $event->alt]);
});

Event::listen(function (AltTextFailed $event) {
    Log::notice('No alt for '.$event->path.': '.$event->reason);
});
```

The job retries only what can succeed later: the per minute cap waits as long as the generator
asks, an outage backs off. A spent limit, a refused image or a disconnected application fail at
once with `AltTextFailed`.

### Validating uploads

```php
use Dock\AltGenerator\Laravel\Rules\GenuineImage;

$request->validate(['photo' => ['required', 'file', new GenuineImage]]);
```

The rule runs the core's inspection: the file is checked by its content, not by its extension or
MIME type. No connection is needed and nothing is sent. Messages are in English and Polish
(`php artisan vendor:publish --tag=dockaccess-alt-lang` to change them).

## Limits

The free plan counts alts per UTC day, paid plans per UTC calendar month (a month's alts can go in
one day).

```php
$usage = AltText::usage();

$usage->used();       // 15
$usage->limit();      // 2000
$usage->remaining();  // 1985
$usage->isMonthly();  // true on a paid plan
$usage->resetsAt();   // DateTimeImmutable, UTC
```

A spent limit throws `DailyLimitReachedException` (the name predates monthly plans;
`getPeriod()` says `day` or `month`).

## The connection check

Before generating, the package asks dockaccess.org whether the generator is still on for the site
and remembers the answer for 5 minutes (`DOCKACCESS_ALT_VERIFY_TTL`). Generation stops with
`GeneratorUnavailableException` when:

| `getReason()` | What happened | What to do |
|---|---|---|
| `disconnected` | The owner disconnected the application in the account | `php artisan dockaccess-alt:connect` |
| `alt_disabled` | The generator was switched off for the site | Switch it on in the account |
| `alt_paused` | The plan paused it (e.g. more sites than the plan allows) | Upgrade the plan |

When dockaccess.org cannot be reached, generation goes on (a warning is logged): the generator
itself still enforces the account's limits. Set `DOCKACCESS_ALT_VERIFY=false` to skip the check.

## Errors

Every exception implements `Dock\AltGenerator\Exception\AltGeneratorException`.

| Exception | Meaning |
|---|---|
| `InvalidImageException` | Refused locally, nothing was sent; `getReason()` says why |
| `GeneratorUnavailableException` | The account says no (see above), nothing was sent |
| `ConfigurationException` | Not connected, or a setting is invalid |
| `AuthenticationException` | The account key was not accepted - connect again |
| `DailyLimitReachedException` | The day's (free) or month's (paid) limit is spent |
| `RateLimitedException` | Too many requests per minute; `getRetryAfter()` |
| `ImageRejectedException` | The generator refused the image |
| `ServerException`, `TransportException` | Try again later |

## Commands

| Command | |
|---|---|
| `dockaccess-alt:connect` | Connect the application and write the keys to `.env` (`--overwrite` replaces another installation's connection, `--show` prints the keys) |
| `dockaccess-alt:status` | The connection, the sites with the generator and the usage |
| `dockaccess-alt:generate {path} [--disk=]` | One generation, to try the setup |
| `dockaccess-alt:disconnect` | Disconnect and remove the keys from `.env` |

## Configuration

```bash
php artisan vendor:publish --tag=dockaccess-alt-config
```

Everything has an environment variable; see `config/dockaccess-alt.php`. The ones you may need:

| Variable | Default | |
|---|---|---|
| `DOCKACCESS_ALT_LANGUAGE` | the app locale | A locale for every generation |
| `DOCKACCESS_ALT_TONE` | `neutral` | |
| `DOCKACCESS_ALT_SET_REF` | the site token | The image set in the account |
| `DOCKACCESS_ALT_IMAGE_MODE` | `auto` | `reencode` sends only GD-written bytes |
| `DOCKACCESS_ALT_PROXY` | | An outgoing proxy |
| `DOCKACCESS_ALT_QUEUE`, `DOCKACCESS_ALT_QUEUE_CONNECTION` | | Where the jobs go |
| `DOCKACCESS_ALT_VERIFY`, `DOCKACCESS_ALT_VERIFY_TTL` | `true`, `300` | The connection check |

## Testing your application

```php
use Dock\AltGenerator\Laravel\Facades\AltText;

$fake = AltText::fake('A red cat on a windowsill');

$this->post('/products', ['photo' => UploadedFile::fake()->createWithContent('cat.png', $png)]);

$fake->assertGenerated(1);
```

`fake()` answers with the given alts instead of calling dockaccess.org and skips the connection
check. Images are still inspected as in production, so a test feeding a broken file still gets
`InvalidImageException`. `$fake->respond('/api/generate', FakeTransport::json([...], 429, [...]))`
simulates a spent limit or another refusal.

## Development

```bash
composer install
composer test
composer analyse
```

## License

MIT, see [LICENSE](LICENSE).
