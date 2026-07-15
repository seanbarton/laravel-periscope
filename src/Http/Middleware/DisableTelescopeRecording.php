<?php

namespace TortoiseIT\LaravelPeriscope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;

class DisableTelescopeRecording
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('periscope.exclude_from_telescope', true)) {
            return $next($request);
        }

        $this->flushTelescopeEntries();

        return Telescope::withoutRecording(function () use ($next, $request): Response {
            try {
                return $next($request);
            } finally {
                $this->flushTelescopeEntries();
            }
        });
    }

    private function flushTelescopeEntries(): void
    {
        Telescope::flushEntries();
        Telescope::$updatesQueue = [];
    }
}
