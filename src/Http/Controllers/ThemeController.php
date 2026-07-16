<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ThemeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $allowedThemes = ['default', 'harbor', 'meadow', 'submarine', 'abyss'];

        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:'.implode(',', $allowedThemes)],
        ]);

        $request->session()->put('periscope.theme', strtolower($validated['theme']));

        return redirect()->back();
    }
}
