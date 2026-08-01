<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the interface language and return to the previous page.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, SetLocale::SUPPORTED), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
