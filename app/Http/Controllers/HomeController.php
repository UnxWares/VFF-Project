<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Media;
use App\Services\SiteStats;
use App\Support\Advocacy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(SiteStats $siteStats): Response
    {
        // Compteurs et profil du réseau : mêmes chiffres que l'API publique (App\Services\SiteStats).
        $stats = $siteStats->counts();

        // Nombre d'images d'archives (sommaire de l'accueil). Prop à part : la forme de `stats` est
        // vérifiée clé par clé par d'autres tests. L'empreinte ne suit pas les médias : 10 min.
        // (int) : un nombre seul revient du cache Valkey sous forme de chaîne.
        $mediaCount = (int) Cache::remember('home_media_count', 600, fn () => Media::count());

        $profile = $siteStats->profile();

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
