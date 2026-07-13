# Laravel Periscope

Laravel Periscope is a lightweight companion UI for [Laravel Telescope](https://laravel.com/docs/telescope). It does not collect application telemetry and it does not replace Telescope. It reads Telescope's existing database tables and provides a denser interface for filtering, searching, drilling into entries, and following the lifecycle of a request.

Copyright 2026 Sean Barton, Tortoise IT Limited.

## What It Does

- Adds a `/periscope` dashboard for Telescope entries.
- Uses the existing `telescope_entries` and `telescope_entries_tags` tables.
- Supports URL-driven saved searches using query parameters.
- Adds date range filtering, live list watch mode, type filters, text search, status/method/path filters, and an errors-only scan.
- Provides detail views for requests, logs, mail, queries, jobs, gates, exceptions, views, models, cache, events, and HTTP client entries.
- Provides a lifecycle view for a request batch so related Telescope entries can be inspected in sequence.
- Suppresses Periscope/Telescope dashboard noise from Laravel Debugbar by default.

## Security

Periscope deliberately inherits Telescope's authorization.

The package route middleware calls `Laravel\Telescope\Telescope::check($request)`. That means the same logic that decides whether the current request can view Telescope also decides whether it can view Periscope. If a user cannot view Telescope, they cannot view Periscope.

Periscope does not define a separate gate, role, policy, user list, password, token, or bypass. The package ships with the normal Laravel `web` middleware by default, then applies the Telescope authorization check.

For production use, configure Telescope's own authorization as you normally would in your application, usually in `app/Providers/TelescopeServiceProvider.php`.

```php
use Laravel\Telescope\Telescope;

Telescope::auth(function ($request) {
    return $request->user()?->can('viewTelescope') === true;
});
```

If that callback returns `false`, Periscope returns `403`.

## Installation

Install the package with Composer:

```bash
composer require seanbarton/laravel-periscope
```

Then clear Laravel's cached package/config state if needed:

```bash
php artisan optimize:clear
```

Visit:

```text
/periscope
```

## Local Path Install

During development, you can include Periscope without copying it into your application by using a Composer path repository:

```bash
composer config repositories.periscope path ../path/to/laravel-periscope
composer require 'seanbarton/laravel-periscope:*@dev'
php artisan optimize:clear
```

This keeps Periscope as a separate package while making it available to the Laravel app through `vendor/`.

## Configuration

The package works without publishing its config. Publish it only when you need to change defaults:

```bash
php artisan vendor:publish --tag=periscope-config
```

Available options:

```php
return [
    'enabled' => env('PERISCOPE_ENABLED', true),
    'name' => env('PERISCOPE_NAME', 'Periscope'),
    'path' => env('PERISCOPE_PATH', 'periscope'),
    'domain' => env('PERISCOPE_DOMAIN'),
    'middleware' => ['web'],
    'connection' => env('PERISCOPE_DB_CONNECTION', env('TELESCOPE_DB_CONNECTION')),
    'per_page' => 100,
    'max_per_page' => 200,
    'default_hours' => 24,
    'error_scan_timeout_ms' => env('PERISCOPE_ERROR_SCAN_TIMEOUT_MS', 1500),
    'error_scan_max_entries' => env('PERISCOPE_ERROR_SCAN_MAX_ENTRIES', 10000),
    'exclude_from_telescope' => env('PERISCOPE_EXCLUDE_FROM_TELESCOPE', true),
    'disable_debugbar' => env('PERISCOPE_DISABLE_DEBUGBAR', true),
    'exclude_debugbar_entries' => env('PERISCOPE_EXCLUDE_DEBUGBAR_ENTRIES', true),
];
```

`PERISCOPE_ENABLED=false` disables the Periscope routes.

`PERISCOPE_PATH=internal/periscope` moves the dashboard to a different URL.

`PERISCOPE_DB_CONNECTION` can be used when Telescope stores entries on a non-default database connection.

`PERISCOPE_EXCLUDE_FROM_TELESCOPE=true` automatically adds the Periscope path to Telescope's ignored paths.

`PERISCOPE_DISABLE_DEBUGBAR=true` automatically disables Debugbar while Periscope is rendering and adds the Periscope/Telescope dashboard paths to Debugbar's ignored paths.

## URL Searches

Periscope search state lives in the URL. Copying the URL is the saved search.

Common parameters:

- `type=request`
- `types[]=request&types[]=log`
- `from=2026-07-13 09:00`
- `to=2026-07-13 11:00`
- `q=checkout`
- `tag=Auth:1`
- `method=POST`
- `status=500`
- `path=/api/orders`
- `errors=1`
- `per_page=100`

Local browser-only filters, such as ignored entry patterns and selected all-entry subtypes, are stored in local storage.

## Error Filtering

The `errors=1` filter first applies the normal time/type/search filters, then scans matching Telescope entries for error signals. Requests are included when their Telescope batch contains an error-like entry.

To protect large datasets, the scan is capped by:

- `PERISCOPE_ERROR_SCAN_TIMEOUT_MS`
- `PERISCOPE_ERROR_SCAN_MAX_ENTRIES`

This keeps the filter useful for everyday debugging without turning a broad date range into an unbounded database operation.

## Keeping Telescope Clean

Periscope tries to avoid polluting Telescope automatically.

By default, the service provider adds the configured Periscope path to `telescope.ignore_paths`:

```php
'ignore_paths' => [
    'periscope*',
],
```

It also disables Laravel Debugbar on Periscope routes and adds both the Periscope and Telescope dashboard paths to Debugbar's ignored path list. Existing `debugbar` Telescope entries are hidden from Periscope lists and counts by default.

This behaviour can be disabled with:

```env
PERISCOPE_EXCLUDE_FROM_TELESCOPE=false
PERISCOPE_DISABLE_DEBUGBAR=false
PERISCOPE_EXCLUDE_DEBUGBAR_ENTRIES=false
```

`telescope.ignore_paths` is enough to suppress the Periscope request entries themselves. In some applications, Telescope may still record query, model, view, cache, or log entries generated while serving the Periscope page. If you need Telescope to be completely isolated from Periscope-generated traffic, add a request-path guard to the host application's `app/Providers/TelescopeServiceProvider.php`.

Keep your existing filter logic, but return `false` before it when the current request is for Periscope:

```php
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

Telescope::filter(function (IncomingEntry $entry) {
    if (request()->is('periscope*')) {
        return false;
    }

    return app()->environment('local')
        || $entry->isReportableException()
        || $entry->isFailedRequest()
        || $entry->isFailedJob()
        || $entry->isScheduledTask()
        || $entry->hasMonitoredTag();
});
```

If Periscope is mounted at a custom path, match that path instead:

```php
if (request()->is('internal/periscope*')) {
    return false;
}
```

This host-application guard prevents Telescope from recording requests, queries, views, model events, logs, and similar entries generated while Periscope pages are being served. It only affects Periscope dashboard traffic; normal application traffic is still handled by your existing Telescope filter.

## Publishing

For open source distribution, the normal route is:

1. Create a Git repository, for example `github.com/seanbarton/laravel-periscope`.
2. Push this package code to that repository.
3. Add a Git tag such as `v0.1.0`.
4. Submit the repository to [Packagist](https://packagist.org/packages/submit).
5. In consuming projects, run `composer require seanbarton/laravel-periscope`.

Packagist reads `composer.json`, so the package name, author, license, autoloading, and Laravel service provider discovery all come from this repository.

Private distribution is also possible using a private Git repository plus a Composer repository entry, Private Packagist, Satis, or a path repository for local/internal projects.

## License

Laravel Periscope is open-sourced software licensed under the MIT license.
