<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Get locale from various sources
        $locale = $this->detectLocale($request);

        // Validate locale
        if (in_array($locale, config('translatable.locales'))) {
            App::setLocale($locale);
        } else {
            App::setLocale(config('translatable.fallback_locale'));
        }

        return $next($request);
    }

    protected function detectLocale(Request $request): string
    {
        // 1. Check URL parameter
        if ($request->has('lang')) {
            return $request->get('lang');
        }

        // 2. Check Accept-Language header
        if ($request->hasHeader('Accept-Language')) {
            $header = $request->header('Accept-Language');
            $locale = substr($header, 0, 2);
            if (in_array($locale, config('translatable.locales'))) {
                return $locale;
            }
        }

        // 3. Check authenticated user preference
        if ($request->user() && isset($request->user()->preferred_language)) {
            return $request->user()->preferred_language;
        }

        // 4. Default to Arabic
        return config('translatable.fallback_locale');
    }
}