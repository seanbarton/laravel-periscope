<?php

namespace TortoiseIT\LaravelPeriscope;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use TortoiseIT\LaravelPeriscope\Http\Middleware\Authorize;
use TortoiseIT\LaravelPeriscope\Http\Middleware\DisableDebugbar;
use TortoiseIT\LaravelPeriscope\Http\Middleware\DisableTelescopeRecording;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\CommandWatcher;

class LaravelPeriscopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        $this->mergeConfigFrom(__DIR__.'/../config/periscope.php', 'periscope');

        $this->disableTelescopeCommandWatcher();
        $this->recordConsoleCommandDurationsForTelescope();
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

    private function disableTelescopeCommandWatcher(): void
    {
        $watchers = [];

        foreach ((array) config('telescope.watchers', []) as $watcher => $configuration) {
            if (is_int($watcher) && is_string($configuration)) {
                $watchers[$configuration] = true;

                continue;
            }

            $watchers[$watcher] = $configuration;
        }

        if ($watchers === []) {
            return;
        }

        $watchers[CommandWatcher::class] = false;

        config([
            'telescope.watchers' => $watchers,
        ]);
    }

    private function recordConsoleCommandDurationsForTelescope(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $startedAt = [];

        Event::listen(CommandStarting::class, function (CommandStarting $event) use (&$startedAt): void {
            $startedAt[spl_object_id($event->input)] = hrtime(true);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event) use (&$startedAt): void {
            $key = spl_object_id($event->input);

            if (! isset($startedAt[$key])) {
                return;
            }

            $durationMs = (hrtime(true) - $startedAt[$key]) / 1_000_000;
            unset($startedAt[$key]);

            Telescope::recordCommand(
                IncomingEntry::make([
                    'command' => $event->command,
                    'exit_code' => $event->exitCode,
                    'arguments' => $event->input->getArguments(),
                    'options' => $event->input->getOptions(),
                    'hostname' => gethostname(),
                    'duration_ms' => round($durationMs, 2),
                ])
            );
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
