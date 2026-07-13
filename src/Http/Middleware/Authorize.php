<?php

namespace TortoiseIT\LaravelPeriscope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Telescope::check($request)) {
            return $next($request);
        }

        abort(403);
    }
}
