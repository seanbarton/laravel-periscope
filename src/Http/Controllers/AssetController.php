<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function __invoke(string $asset): BinaryFileResponse
    {
        abort_unless(in_array($asset, ['periscope-logo.png', 'periscope-favicon.png'], true), 404);

        return response()->file(__DIR__.'/../../../resources/assets/'.$asset, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => 'image/png',
        ]);
    }
}
