<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales the interface is translated into.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [
        'km' => 'ខ្មែរ',
        'en' => 'English',
    ];

    /**
     * Apply the locale stored in the session, falling back to the configured
     * default. Kept in the session rather than the URL so every existing route
     * keeps working unchanged.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! is_string($locale) || ! array_key_exists($locale, self::SUPPORTED)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
