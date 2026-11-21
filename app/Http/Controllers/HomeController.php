<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Media;
use App\Services\HomeArchives;
use App\Services\SiteStats;
use App\Support\Advocacy;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(SiteStats $siteStats, HomeArchives $homeArchives): Response
    {
        // Compteurs et profil du réseau : mêmes chiffres que l'API publique (App\Services\SiteStats).
        $stats = $siteStats->counts();

        // Nombre d'images d'archives (sommaire de l'accueil). Prop à part : la forme de `stats` est
        // vérifiée clé par clé par d'autres tests. L'empreinte ne suit pas les médias : 10 min.
        // (int) : un nombre seul revient du cache Valkey sous forme de chaîne.
        $mediaCount = (int) Cache::remember('home_media_count', 600, fn () => Media::count());

        $profile = $siteStats->profile();

        $archives = $homeArchives->current();

        // Lignes soutenues par VFF (menacées, ou campagne de réouverture) → encarts de l'accueil. Ordre et
        // forme (encart complet ou mention courte) viennent du message de soutien (Advocacy::home) : rang
        // croissant, puis les lignes sans rang, menacées d'abord, par code.
        $locale = app()->getLocale();
        $threatened = Line::whereNotNull('advocacy')
            ->get(['slug', 'name', 'code', 'is_threatened', 'advocacy'])
            ->map(fn (Line $l) => [
                'line' => $l,
                'home' => Advocacy::home($l->advocacy, $locale),
            ])
            ->sort(fn (array $a, array $b) => [$a['home']['rank'] ?? PHP_INT_MAX, ! $a['line']->is_threatened, $a['line']->code]
                <=> [$b['home']['rank'] ?? PHP_INT_MAX, ! $b['line']->is_threatened, $b['line']->code])
            ->map(fn (array $item) => [
                'slug' => $item['line']->slug,
                'name' => $item['line']->name,
                // Titre, message et bouton dans la langue de la page (repli français signalé).
                'advocacy' => Advocacy::localized($item['line']->advocacy, $locale),
                'home' => $item['home'],
            ])->values();

        return Inertia::render('Home', [
            'stats' => $stats,
            'threatened' => $threatened,
            'profile' => $profile,
            'archives' => $archives,
            'mediaCount' => $mediaCount,
        ]);
    }
}
