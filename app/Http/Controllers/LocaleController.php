<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,vi'],
        ]);

        return back()->cookie(
            'locale',
            $validated['locale'],
            60 * 24 * 365
        );
    }
}
