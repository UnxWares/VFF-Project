<?php

namespace App\Services\Materials;

use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Carte des lignes parcourues par une série : une entrée par ligne, avec son tracé allégé (une seule géométrie
 * par ligne, toutes ères confondues), le niveau de confiance du lien, sa source et les périodes datées.
 *
 * Le tracé est simplifié à ~40 m (assez pour situer la ligne sur la carte d'une fiche, pas pour la mesurer) et la
 * réponse est gardée en cache : l'affichage de la fiche ne lit jamais les tronçons. Règle des droits des tracés
 * (LineTraceExport) : la géométrie d'une source qui n'autorise pas la redistribution n'est pas servie par l'API ;
 * `$display` (carte de la fiche du site, comme les tuiles vectorielles) la dessine quand même.
 */
final class MaterialLinesMap
{
    /** Tolérance de simplification (degrés, environ 40 m). */
    private const SIMPLIFY_DEG = 0.0004;

    /** Décimales des coordonnées (4 = environ 11 m). */
    private const DECIMALS = 4;

    /** Lignes lues au plus pour une série (la plus citée en compte 58). */
    private const MAX_LINES = 300;

    /** Durée du cache (s) ; la clé porte aussi l'état des liens, qui l'invalide avant. */
    public const CACHE_TTL = 86_400;

    /** Ordre des fiabilités, du lien le plus sûr au moins sûr. */
    private const CONFIDENCE_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    public function __construct(private readonly LineTraceExport $rights) {}

    /**
     * @return array{material: array{slug: string, name: string, in_service_from: int|null, in_service_to: int|null}, lines: list<array<string, mixed>>}
     */
    public function for(Material $material, bool $display = false): array
    {
        $state = DB::selectOne('SELECT count(*) AS n, max(updated_at) AS u FROM line_materials WHERE material_id = ?', [$material->id]);
        $periods = DB::selectOne('SELECT count(*) AS n, max(p.updated_at) AS u FROM line_material_periods p JOIN line_materials m ON m.id = p.line_material_id WHERE m.material_id = ?', [$material->id]);
        $key = 'material-lines-map:'.md5(implode('|', [$display, $material->id, $state->n, $state->u, $periods->n, $periods->u, $material->updated_at]));

        return Cache::remember($key, self::CACHE_TTL, fn (): array => $this->build($material, $display));
    }

    /** @return array{material: array<string, mixed>, lines: list<array<string, mixed>>} */
    private function build(Material $material, bool $display): array
    {
        $links = $material->lineLinks()
            ->with(['line:id,slug,code,name', 'shownPeriods'])
            ->join('lines', 'lines.id', '=', 'line_materials.line_id')
            ->orderBy('lines.name')
            ->select('line_materials.*')
            ->limit(self::MAX_LINES)
            ->get();

        $geometries = $this->geometries($links->pluck('line_id')->unique()->all(), $display);

        $lines = $links->groupBy('line_id')->map(function ($group, $lineId) use ($geometries): array {
            /** @var LineMaterial $link */
            $link = $group->sortBy(fn (LineMaterial $l): int => self::CONFIDENCE_RANK[$l->confidence] ?? 3)->first();
            $periods = $group->flatMap(fn (LineMaterial $l) => $l->shownPeriods)
                ->sortBy(fn (LineMaterialPeriod $p): string => ($p->start_date ?? $p->end_date)?->toDateString() ?? '')
                ->map(fn (LineMaterialPeriod $p): array => [
                    'start_date' => $p->start_date?->toDateString(),
                    'start_precision' => $p->start_precision,
                    'end_date' => $p->end_date?->toDateString(),
                    'end_precision' => $p->end_precision,
                    'source_url' => $p->source_url,
                ])->values()->all();
            $shape = $geometries[$lineId] ?? ['geometry' => null, 'withheld' => false];

            return [
                'slug' => $link->line->slug,
                'code' => $link->line->code,
                'name' => $link->line->name,
                'confidence' => $link->confidence,
                'kind' => $link->evidence[0]['kind'] ?? null,
                'source' => $link->source,
                'source_url' => $link->source_url,
                'article' => $link->evidence[0]['article'] ?? null,
                'quote' => $link->evidence[0]['quote'] ?? null,
                'periods' => $periods,
                'geometry' => $shape['geometry'],
                'geometry_withheld' => $shape['withheld'],
            ];
        })->values()->all();

        return [
            'material' => [
                'slug' => $material->slug,
                'name' => $material->name,
                'in_service_from' => $material->in_service_from,
                'in_service_to' => $material->in_service_to,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * Un tracé allégé par ligne : tronçons distincts de toutes les ères réunis (sans union), simplifiés. Les
     * tronçons dont la source n'autorise pas la redistribution sont écartés ; `withheld` dit qu'il en manque.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{geometry: array<string, mixed>|null, withheld: bool}>
     */
    private function geometries(array $lineIds, bool $display): array
    {
        if ($lineIds === []) {
            return [];
        }
        $open = $display ? 'TRUE' : $this->rights->sqlRedistributable('source');
        $marks = implode(',', array_fill(0, count($lineIds), '?'));
        $rows = DB::select(
            "WITH s AS (
                SELECT DISTINCT ON (line_id, md5(ST_AsEWKB(geom))) line_id, geom,
                       COALESCE({$open}, FALSE) AS ok
                FROM line_segments WHERE line_id IN ({$marks})
                ORDER BY line_id, md5(ST_AsEWKB(geom)), id)
             SELECT line_id, bool_or(NOT ok) AS withheld,
                    ST_AsGeoJSON(ST_Simplify(ST_Collect(geom) FILTER (WHERE ok), ?, TRUE), ?) AS gj
             FROM s GROUP BY line_id",
            [...$lineIds, self::SIMPLIFY_DEG, self::DECIMALS],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = ['geometry' => $r->gj === null ? null : json_decode($r->gj, true), 'withheld' => (bool) $r->withheld];
        }

        return $out;
    }
}
