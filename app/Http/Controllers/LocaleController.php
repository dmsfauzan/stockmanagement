<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $available = (array) config('app.available_locales', ['id', 'en']);

        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $available)],
        ]);

        $request->session()->put('locale', $data['locale']);

        if ($request->user() !== null) {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }

        app()->setLocale($data['locale']);

        return back();
    }
}
