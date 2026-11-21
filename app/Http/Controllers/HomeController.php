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

        // Lignes soutenues par VFF (menacées, ou campagne de réouverture) → encart de mobilisation.
        $threatened = Line::whereNotNull('advocacy')
            ->orderByDesc('is_threatened')
            ->get(['slug', 'name', 'advocacy'])
            ->map(fn ($l) => [
                'slug' => $l->slug,
                'name' => $l->name,
                // Titre, message et bouton dans la langue de la page (repli français signalé).
                'advocacy' => Advocacy::localized($l->advocacy, app()->getLocale()),
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
