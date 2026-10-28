# Changelog

## 1.0.0 - unreleased

First version of the Laravel integration, on the PHP core 1.1.

- `dockaccess-alt:connect`, `status`, `generate` and `disconnect` Artisan commands; the connection
  appears in the dockaccess.org account as `laravel`.
- `AltText` facade: `generate()` for uploads, absolute paths and `ImageSource`, `generateFromDisk()`
  for any Storage disk, `prepare()`, `usage()` with the day or month window of the plan.
- A connection check before generating, cached, that stops on a disconnected, switched off or
  paused site and lets generation through when dockaccess.org cannot be reached.
- `GenuineImage` validation rule, messages in English and Polish.
- `GenerateAltText` queued job with `AltTextGenerated` and `AltTextFailed` events; retries only
  what can succeed later.
- `AltText::fake()` for application tests.
