<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'role' => $user->role->value,
                'reputation' => $user->reputation,
                'avatar_path' => $user->avatar_path,
                'locale_preference' => $user->locale_preference,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'locale_preference' => ['required', 'string', 'in:'.implode(',', SetLocale::SUPPORTED)],
        ]);

        $request->user()->update($data);

        // La nouvelle langue s'applique tout de suite : le cookie de /locale/{code}
        // (prioritaire dans SetLocale) est aligné sur le choix du profil.
        $locale = $data['locale_preference'];
        app()->setLocale($locale);

        return back()
            ->with('success', __('auth.profile_updated'))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
