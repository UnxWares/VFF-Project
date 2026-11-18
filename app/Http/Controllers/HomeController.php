<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\NetworkStats;
use App\Support\Advocacy;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(NetworkStats $networkStats): Response
    {
        // Cache versionné par une empreinte des données : dès qu'un import ou le
        // moteur décennal écrit des lignes/segments/gares, la clé change et les
        // compteurs sont recalculés (avant : clé fixe 'home_stats' jamais
        // invalidée → compteurs figés à 0 après recréation de la base).
        //
        // Périmètres (alignés sur les libellés lang/*/home.php) :
        //   - lines_count    : lignes recensées (en service ET disparues)
        //   - stations_count : gares recensées
        //   - eras_count     : décennies réellement cartographiées (≠ ères seedées)
        //   - total_km       : km de lignes exploitées aujourd'hui (route-km SNCF)
        $key = 'home_stats:'.$networkStats->dataFingerprint();

        $stats = Cache::remember($key, 600, fn () => [
            'lines_count' => Line::count(),
            'stations_count' => Station::count(),
            'materials_count' => Material::count(),
            'eras_count' => $networkStats->mappedErasCount(),
            'total_km' => (int) round($networkStats->totalRouteKm()),
        ]);

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
        ]);
    }
}
