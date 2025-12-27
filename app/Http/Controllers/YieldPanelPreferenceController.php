<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class YieldPanelPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $pref = [
            'colors' => (bool) $request->boolean('colors'),
            'font' => (bool) $request->boolean('font'),
            'icons' => (bool) $request->boolean('icons'),
        ];

        session(['yieldpanel' => $pref]);

        return back();
    }
}
