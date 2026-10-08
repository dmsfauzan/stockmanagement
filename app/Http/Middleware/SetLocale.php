<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = (array) config('app.available_locales', ['id', 'en']);
        $locale = null;

        if (auth()->check() && in_array(auth()->user()->locale, $available, true)) {
            $locale = auth()->user()->locale;
        } elseif ($request->hasSession() && in_array($request->session()->get('locale'), $available, true)) {
            $locale = $request->session()->get('locale');
        }

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
