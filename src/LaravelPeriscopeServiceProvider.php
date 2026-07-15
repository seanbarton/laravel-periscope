<?php

namespace TortoiseIT\LaravelPeriscope;

use TortoiseIT\LaravelPeriscope\Http\Middleware\Authorize;
use TortoiseIT\LaravelPeriscope\Http\Middleware\DisableDebugbar;
use TortoiseIT\LaravelPeriscope\Http\Middleware\DisableTelescopeRecording;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Telescope;

class LaravelPeriscopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/periscope.php', 'periscope');

        $this->ignorePeriscopeRequestsInTelescope();
        $this->rejectPeriscopeEntriesInTelescope();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'periscope');

        $this->ignoreDashboardRequestsInDebugbar();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/periscope.php' => config_path('periscope.php'),
            ], 'periscope-config');
        }

        if (! config('periscope.enabled')) {
            return;
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::group([
            'domain' => config('periscope.domain'),
            'prefix' => config('periscope.path'),
            'middleware' => array_merge(config('periscope.middleware', ['web']), [DisableTelescopeRecording::class, DisableDebugbar::class, Authorize::class]),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    private function ignorePeriscopeRequestsInTelescope(): void
    {
        if (! config('periscope.exclude_from_telescope', true)) {
            return;
        }

        $paths = $this->periscopeTelescopeIgnorePaths();

        if ($paths === []) {
            return;
        }

        config([
            'telescope.ignore_paths' => array_values(array_unique(array_merge(
                (array) config('telescope.ignore_paths', []),
                $paths,
            ))),
        ]);
    }

    private function rejectPeriscopeEntriesInTelescope(): void
    {
        if (! config('periscope.exclude_from_telescope', true)) {
            return;
        }

        Telescope::filter(function (): bool {
            if ($this->app->runningInConsole() || ! $this->app->bound('request')) {
                return true;
            }

            return ! $this->app['request']->is($this->periscopeTelescopeIgnorePaths());
        });
    }

    private function periscopeTelescopeIgnorePaths(): array
    {
        $path = trim((string) config('periscope.path', 'periscope'), '/');

        if ($path === '') {
            return [];
        }

        return [$path, $path.'*'];
    }

    private function ignoreDashboardRequestsInDebugbar(): void
    {
        if (! config('periscope.disable_debugbar', true)) {
            return;
        }

        $paths = collect([
            config('periscope.path', 'periscope'),
            config('telescope.path', 'telescope'),
        ])
            ->map(fn ($path) => trim((string) $path, '/'))
            ->filter()
            ->flatMap(fn (string $path) => [$path, $path.'*'])
            ->all();

        if ($paths === []) {
            return;
        }

        config([
            'debugbar.except' => array_values(array_unique(array_merge(
                (array) config('debugbar.except', []),
                $paths,
            ))),
        ]);
    }
}
