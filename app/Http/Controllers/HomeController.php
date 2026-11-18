<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Services\NetworkStats;
use App\Support\Advocacy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $fingerprint = $networkStats->dataFingerprint();
        $key = 'home_stats:'.$fingerprint;

        $stats = Cache::remember($key, 600, fn () => [
            'lines_count' => Line::count(),
            'stations_count' => Station::count(),
            'materials_count' => Material::count(),
            'eras_count' => $networkStats->mappedErasCount(),
            'total_km' => (int) round($networkStats->totalRouteKm()),
        ]);

        // Nombre d'images d'archives (sommaire de l'accueil). Prop à part : la forme de `stats` est
        // vérifiée clé par clé par d'autres tests. L'empreinte ne suit pas les médias : 10 min.
        // (int) : un nombre seul revient du cache Valkey sous forme de chaîne.
        $mediaCount = (int) Cache::remember('home_media_count', 600, fn () => Media::count());

        // Profil du réseau : longueur des lignes ouvertes (segments en service ou à voie unique) et
        // nombre de lignes à chaque décennie. Somme de length_meters (environ 0,3 s sans cache ;
        // ST_Length sur toute la géographie prenait 18 s pour les mêmes valeurs à 1 km près),
        // ST_Length seulement pour les rares segments sans longueur enregistrée.
        $profile = Cache::remember('home_profile:'.$fingerprint, 86400, fn () => collect(DB::select(
            'SELECT e.year, e.is_current,
                    COALESCE(ROUND(SUM(COALESCE(s.length_meters, ST_Length(s.geom::geography))) / 1000), 0)::int AS km,
                    COUNT(DISTINCT s.line_id)::int AS lines
             FROM eras e
             LEFT JOIN line_segments s ON s.era_id = e.id AND s.status IN (?, ?)
             GROUP BY e.id, e.year, e.is_current
             ORDER BY e.year',
            NetworkStats::ACTIVE_STATUSES,
        ))->map(fn ($r) => [
            'year' => (int) $r->year,
            'km' => (int) $r->km,
            'lines' => (int) $r->lines,
            'current' => (bool) $r->is_current,
        ])->values()->all());

        $archives = Cache::remember('home_archives:7:'.now()->toDateString(), 86400, fn () => $this->archives());

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

    /**
     * Sept images de Wikimedia Commons mises en avant (media.is_featured), rattachées à une ligne :
     * une par période d'abord (avant 1915, 1915-1945, 1946-1970, 1971-1990, 1991-2010, après 2010),
     * puis complétées dans le même ordre. Le tirage change chaque jour (empreinte de l'identifiant et
     * de la date) et reste stable dans la journée. Rangées par année représentée.
     *
     * @return list<array<string, mixed>>
     */
    private function archives(): array
    {
        $rows = DB::select(
            "SELECT * FROM (
                SELECT m.id, m.depicted_year, m.caption, m.author, m.license, m.license_url, m.source_url,
                       m.thumb_url, m.display_url, m.width, m.height,
                       m.provider_meta->>'date_precision' AS date_precision,
                       l.slug AS line_slug, l.name AS line_name,
                       ROW_NUMBER() OVER (
                           PARTITION BY CASE
                               WHEN m.depicted_year < 1915 THEN 1 WHEN m.depicted_year < 1946 THEN 2
                               WHEN m.depicted_year < 1971 THEN 3 WHEN m.depicted_year < 1991 THEN 4
                               WHEN m.depicted_year < 2011 THEN 5 ELSE 6 END
                           ORDER BY md5(m.id::text || ?)
                       ) AS rank_in_period,
                       CASE
                           WHEN m.depicted_year < 1915 THEN 1 WHEN m.depicted_year < 1946 THEN 2
                           WHEN m.depicted_year < 1971 THEN 3 WHEN m.depicted_year < 1991 THEN 4
                           WHEN m.depicted_year < 2011 THEN 5 ELSE 6 END AS period
                FROM media m
                JOIN lines l ON l.id = m.mediable_id
                WHERE m.mediable_type = ? AND m.is_featured
                  AND m.provider = ? AND m.depicted_year IS NOT NULL
                  AND m.thumb_url IS NOT NULL AND m.source_url IS NOT NULL
                  AND m.caption IS NOT NULL AND m.license IS NOT NULL
                  AND m.width >= 1200 AND m.width > m.height
            ) ranked
            ORDER BY rank_in_period, period
            LIMIT 7",
            [now()->toDateString(), Line::class, Media::PROVIDER_COMMONS],
        );

        return collect($rows)
            ->sortBy('depicted_year')
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'year' => (int) $m->depicted_year,
                'decade' => $m->date_precision === 'decade',
                'caption' => $m->caption,
                'author' => $m->author,
                'license' => $m->license,
                'license_url' => $m->license_url,
                'source_url' => $m->source_url,
                'thumb_url' => $m->thumb_url,
                'display_url' => $m->display_url,
                'width' => (int) $m->width,
                'height' => (int) $m->height,
                'line' => ['slug' => $m->line_slug, 'name' => $m->line_name],
            ])->values()->all();
    }
}
