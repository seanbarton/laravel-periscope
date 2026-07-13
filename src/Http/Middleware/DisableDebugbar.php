<?php

namespace TortoiseIT\LaravelPeriscope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DisableDebugbar
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('periscope.disable_debugbar', true)) {
            return $next($request);
        }

        config(['debugbar.enabled' => false]);

        try {
            if (app()->bound('debugbar')) {
                app('debugbar')->disable();
            }
        } catch (Throwable) {
            // Debugbar is optional; Periscope should not fail if its binding changes.
        }

        return $next($request);
    }
}
