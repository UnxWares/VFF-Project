<?php

namespace App\Services\TraceExport;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Support\SourceRights;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tracé d'une ligne à une ère pour les exports (GeoJSON, GPX, KML) : lecture des tronçons et
 * application de la règle des droits.
 *
 * Règle : la géométrie d'un tronçon n'est exportée que si son fournisseur (registre
 * database/data/source_rights.php, table `line_segments`) a une licence ouverte écrite et prouvée
 * (statut « sur », voir SourceRights::allowsRedistribution). Une source non commerciale, sous accord
 * oral, de statut incertain ou absente du registre n'est jamais redistribuée ; les tronçons
 * concernés sont comptés dans `withheld` pour que la réponse le dise.
 */
final class LineTraceExport
{
    /** Table du registre des droits qui désigne le fournisseur de la géométrie d'un tronçon. */
    public const TABLE = 'line_segments';

    public function __construct(private readonly SourceRights $rights) {}

    /** Ère demandée (404 si ce n'est pas une décennie de la carte) ; à défaut l'ère courante. */
    public function era(?int $year): Era
    {
        return $year !== null
            ? Era::where('year', $year)->firstOrFail()
            : Era::where('is_current', true)->first() ?? Era::orderByDesc('year')->firstOrFail();
    }

    /**
     * Tronçons d'une ligne à une ère, dans l'ordre des PK. Avec `$withGeometry`, la colonne calculée
     * `geojson` porte la géométrie GeoJSON (6 décimales) : c'est au lecteur de ne l'exposer que pour
     * les tronçons dont `redistributable()` est vrai.
     *
     * @return Collection<int, LineSegment>
     */
    public function segments(Line $line, Era $era, bool $withGeometry): Collection
    {
        return $line->segments()->where('era_id', $era->id)
            ->select(['id', 'pk_start_m', 'pk_end_m', 'length_meters', 'status', 'traffic_type', 'is_heritage', 'heritage_kind',
                'electrified', 'voltage_v', 'electrified_year', 'electrification_source', 'max_speed_kmh', 'max_speed_source',
                'gauge_mm', 'gauge_class', 'signalling', 'train_protection', 'operator', 'dismantled_at', 'reuse', 'source'])
            ->when($withGeometry, fn ($q) => $q->selectRaw('ST_AsGeoJSON(geom, 6) AS geojson'))
            ->orderByRaw('pk_start_m NULLS LAST, id')
            ->get();
    }

    /** Fournisseur de la géométrie d'un tronçon d'après sa colonne `source` (null : source inconnue du registre). */
    public function provider(?string $source): ?string
    {
        return $this->rights->providerFor(self::TABLE, $source);
    }

    /** La géométrie d'un tronçon de cette source peut-elle être redistribuée ? */
    public function redistributable(?string $source): bool
    {
        return $this->rights->allowsRedistribution($this->provider($source));
    }

    /**
     * Tracé exportable de la ligne à l'ère (géométrie décodée des seuls tronçons redistribuables).
     */
    public function trace(Line $line, Era $era): LineTrace
    {
        $segments = $this->segments($line, $era, true);
        $parts = [];
        $providers = [];
        foreach ($segments as $segment) {
            $source = $segment->source;
            if (! $this->redistributable($source)) {
                continue;
            }
            $provider = (string) $this->provider($source);
            $providers[$provider] = true;
            foreach ($this->lineStrings((string) $segment->getAttribute('geojson')) as $coordinates) {
                $parts[] = new TracePart(
                    id: (int) $segment->id,
                    pkStartM: $segment->pk_start_m,
                    pkEndM: $segment->pk_end_m,
                    status: $segment->getRawOriginal('status'),
                    provider: $provider,
                    coordinates: $coordinates,
                );
            }
        }

        return new LineTrace($line, $era, $segments->count(), $parts, array_keys($providers), $this->withheld($segments));
    }

    /**
     * Fournisseurs des tronçons dont la géométrie peut être redistribuée, dans l'ordre de première apparition.
     *
     * @param  iterable<LineSegment>  $segments
     * @return list<string>
     */
    public function exportedProviders(iterable $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            if ($this->redistributable($segment->source)) {
                $out[(string) $this->provider($segment->source)] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Résumé des tronçons dont la géométrie n'est pas redistribuable, groupés par fournisseur.
     *
     * @param  iterable<LineSegment>  $segments
     * @return list<array{provider: ?string, name: ?string, license: ?string, reason: string, sections: int, length_m: int}>
     */
    public function withheld(iterable $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            if ($this->redistributable($segment->source)) {
                continue;
            }
            $provider = $this->provider($segment->source);
            $key = $provider ?? '';
            $info = $provider !== null ? $this->rights->provider($provider) : null;
            $out[$key] ??= [
                'provider' => $provider,
                'name' => isset($info['nom']) ? (string) $info['nom'] : null,
                'license' => isset($info['licence']) ? (string) $info['licence'] : null,
                'reason' => $provider === null ? LineTrace::UNKNOWN_SOURCE : LineTrace::NOT_OPEN,
                'sections' => 0,
                'length_m' => 0,
            ];
            $out[$key]['sections']++;
            $out[$key]['length_m'] += (int) $segment->length_meters;
        }

        return array_values($out);
    }

    /**
     * Années des ères où la ligne a au moins un tronçon exportable (liens de téléchargement de la fiche).
     *
     * @return list<int>
     */
    public function exportableEraYears(Line $line): array
    {
        $open = $this->rights->sourcesWithStatus(self::TABLE, SourceRights::SAFE);
        if ($open === []) {
            return [];
        }

        return Era::query()
            ->whereIn('id', $line->segments()->whereIn('source', $open)->select('era_id'))
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    /**
     * Lignes brisées d'une géométrie GeoJSON (une par partie d'une géométrie multiple).
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    private function lineStrings(string $geojson): array
    {
        $geometry = json_decode($geojson, true);
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? []) : [];

        return match (is_array($geometry) ? ($geometry['type'] ?? null) : null) {
            'LineString' => $coordinates === [] ? [] : [$this->points($coordinates)],
            'MultiLineString' => array_values(array_filter(array_map($this->points(...), $coordinates))),
            default => [],
        };
    }

    /**
     * @param  array<int, array<int, float|int>>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    private function points(array $coordinates): array
    {
        return array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], array_values($coordinates));
    }
}
