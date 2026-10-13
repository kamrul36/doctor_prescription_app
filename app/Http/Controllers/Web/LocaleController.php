<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

/** The বাংলা | EN switch in the header and on the login page. */
class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated()['locale'];

        $request->session()->put(SetLocale::SESSION_KEY, $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
