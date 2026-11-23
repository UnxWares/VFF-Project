<?php

namespace App\Services;

use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres d'ensemble du site : compteurs (lignes, gares, matériels, ères cartographiées, kilomètres exploités)
 * et profil du réseau à chaque ère. Une seule construction pour la page d'accueil et pour l'API publique
 * (GET /api/v1/stats, requête GraphQL `stats`). Le cache est versionné par l'empreinte des données : dès qu'un
 * import ou le moteur décennal écrit des lignes, des segments ou des gares, la clé change.
 *
 * Périmètres (alignés sur les libellés lang/{fr,en,de}/home.php) :
 *   - lines_count : lignes recensées (en service ET disparues) ;
 *   - stations_count : gares recensées ;
 *   - materials_count : séries de matériel roulant ;
 *   - eras_count : décennies réellement cartographiées (et non les ères seulement déclarées) ;
 *   - total_km : kilomètres de lignes exploitées aujourd'hui (formes SNCF Réseau « Exploitée »).
 */
final class SiteStats
{
    /** Empreinte des données, calculée une seule fois par instance (counts() puis profile() dans la même réponse). */
    private ?string $fingerprint = null;

    public function __construct(private readonly NetworkStats $network) {}

    private function fingerprint(): string
    {
        return $this->fingerprint ??= $this->network->dataFingerprint();
    }

    /** @return array{lines_count: int, stations_count: int, materials_count: int, eras_count: int, total_km: int} */
    public function counts(): array
    {
        return Cache::remember('home_stats:'.$this->fingerprint(), 600, fn (): array => [
            'lines_count' => Line::count(),
            'stations_count' => Station::count(),
            'materials_count' => Material::count(),
            'eras_count' => $this->network->mappedErasCount(),
            'total_km' => (int) round($this->network->totalRouteKm()),
        ]);
    }

    /**
     * Profil du réseau : longueur des lignes ouvertes (segments en service ou à voie unique) et nombre de lignes
     * à chaque décennie. Somme de length_meters (environ 0,3 s sans cache ; ST_Length sur toute la géographie
     * prenait 18 s pour les mêmes valeurs à 1 km près), ST_Length seulement pour les rares segments sans longueur.
     * La longueur de chaque tronçon est lue avant le regroupement par ère (sous-requête fermée par OFFSET 0) : sans
     * cela, le planificateur gardait le tracé de chaque tronçon, nécessaire au seul repli ST_Length, dans les lignes
     * triées pour le COUNT(DISTINCT) (2,3 s et 280 Mo écrits sur disque ; 0,3 à 0,8 s ainsi, lecture de la table).
     *
     * @return list<array{year: int, km: int, lines: int, current: bool}>
     */
    public function profile(): array
    {
        return Cache::remember('home_profile:'.$this->fingerprint(), 86400, fn (): array => collect(DB::select(
            'SELECT e.year, e.is_current, COALESCE(ROUND(s.m / 1000), 0)::int AS km, COALESCE(s.n, 0)::int AS lines
             FROM eras e
             LEFT JOIN (SELECT era_id, SUM(m) AS m, COUNT(DISTINCT line_id) AS n
                        FROM (SELECT era_id, line_id, COALESCE(length_meters, ST_Length(geom::geography)) AS m
                              FROM line_segments WHERE status IN (?, ?) OFFSET 0) seg
                        GROUP BY era_id) s ON s.era_id = e.id
             ORDER BY e.year',
            NetworkStats::ACTIVE_STATUSES,
        ))->map(fn ($r): array => [
            'year' => (int) $r->year,
            'km' => (int) $r->km,
            'lines' => (int) $r->lines,
            'current' => (bool) $r->is_current,
        ])->values()->all());
    }
}
