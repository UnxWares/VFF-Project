<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Importe les tronçons ferroviaires de l'IGN BD TOPO (couche
 * `BDTOPO_V3:troncon_de_voie_ferree`) dans `source_traces` (source = 'bdtopo').
 *
 * Pourquoi la BD TOPO : les formes SNCF du RFN (`sncf_line_geometries`) sont
 * généralisées (écarts de 15 à 165 m) et OSM est incomplet par endroits. La
 * BD TOPO est saisie à 2,5 m (precision_planimetrique), couvre tout le
 * territoire de façon homogène et décrit l'état de chaque tronçon (En service,
 * Non exploité, En construction, En projet) : c'est la meilleure base pour un
 * tracé JUSTE du réseau actuel. Licence Ouverte 2.0 (Etalab), crédit « IGN -
 * BD TOPO ».
 *
 * Ce qui est importé : toutes les natures ferroviaires utiles - LGV, Voie
 * ferrée principale, Voie de service, Funiculaire ou crémaillère et « Sans
 * objet » (c'est la nature de TOUS les tronçons « Non exploité » : lignes
 * fermées dont la voie existe encore, précieuses pour les lignes neutralisées).
 * Métro et Tramway (transport urbain) sont exclus mais comptés dans le rapport.
 *
 * Modélisation BD TOPO à connaître : une ligne à 2-4 voies parallèles est UN
 * seul tronçon (attribut nombre_de_voies) ; les voies de service (faisceaux,
 * garages, embranchements) sont des tronçons à part, nature « Voie de service ».
 *
 * Mapping : source_ref = cleabs (identifiant pérenne IGN), layer = nature,
 * name = cpx_toponyme, properties = tous les attributs IGN tels quels.
 * Idempotent : ON CONFLICT met à jour la géométrie et les attributs ; seen_at
 * date le dernier passage. Les tronçons que l'IGN ne sert plus sont signalés
 * (supprimés avec --prune, seulement après un import complet). Le rattachement
 * aux lignes RFN est fait ensuite par `vff:match-current-network`.
 *
 *   php artisan vff:import-bdtopo-rail
 *   php artisan vff:import-bdtopo-rail --bbox=-1.8,48.0,-1.5,48.2   # test (Rennes)
 *   php artisan vff:import-bdtopo-rail --prune
 */
class ImportBdtopoRail extends Command
{
    protected $signature = 'vff:import-bdtopo-rail
        {--bbox= : emprise lon_min,lat_min,lon_max,lat_max (WGS84) pour un import partiel}
        {--page-size=5000 : objets par requête WFS (5000 = maximum du Géoplateforme)}
        {--prune : supprime les tronçons bdtopo absents du dernier import complet}';

    protected $description = 'Importe les tronçons ferroviaires IGN BD TOPO (WFS Géoplateforme) dans source_traces.';

    private const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    private const TYPENAME = 'BDTOPO_V3:troncon_de_voie_ferree';

    /** Natures de transport urbain : comptées, jamais importées. */
    private const EXCLUDED_NATURES = ['Métro', 'Tramway'];

    public function handle(): int
    {
        $bbox = $this->parseBbox();
        if ($bbox === false) {
            return self::FAILURE;
        }
        $pageSize = max(1, min(5000, (int) $this->option('page-size')));
        $startedAt = now()->startOfSecond();

        $filter = $this->cqlFilter(sprintf(
            'nature NOT IN (%s)',
            implode(',', array_map(fn ($n) => "'{$n}'", self::EXCLUDED_NATURES)),
        ), $bbox);

        $total = null;
        $received = 0;
        $start = 0;
        $bar = null;
        do {
            $page = $this->getFeatures($filter, $start, $pageSize);
            $total ??= (int) ($page['numberMatched'] ?? $page['totalFeatures'] ?? 0);
            if (! $bar) {
                $this->line("→ {$total} tronçons BD TOPO à importer (hors métro/tramway).");
                $bar = $this->output->createProgressBar($total);
                $bar->start();
            }
            $features = $page['features'] ?? [];
            if ($features) {
                $this->upsert($features, $startedAt);
            }
            $received += count($features);
            $start += count($features);
            $bar->setProgress(min($received, $total));
        } while (count($features) === $pageSize && $received < $total);
        $bar?->finish();
        $this->newLine();

        if ($received < $total) {
            $this->warn("Seulement {$received}/{$total} tronçons reçus : relancer l'import.");
        }

        $this->report($startedAt, $bbox);
        $this->handleStale($startedAt, $bbox, $received >= $total);

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null|false
     */
    private function parseBbox(): array|null|false
    {
        $raw = $this->option('bbox');
        if (! $raw) {
            return null;
        }
        $parts = array_map('trim', explode(',', (string) $raw));
        if (count($parts) !== 4 || array_filter($parts, fn ($p) => ! is_numeric($p))) {
            $this->error('--bbox attend lon_min,lat_min,lon_max,lat_max');

            return false;
        }

        return array_map('floatval', $parts);
    }

    private function cqlFilter(string $base, ?array $bbox): string
    {
        if (! $bbox) {
            return $base;
        }

        return sprintf(
            "%s AND BBOX(geometrie,%F,%F,%F,%F,'urn:ogc:def:crs:OGC:1.3:CRS84')",
            $base, $bbox[0], $bbox[1], $bbox[2], $bbox[3],
        );
    }

    /**
     * Une page WFS, triée par cleabs pour que la pagination soit stable.
     * Retry avec backoff (le Géoplateforme renvoie parfois des 502/503).
     *
     * @return array<string, mixed>
     */
    private function getFeatures(string $filter, int $start, int $count, int $maxAttempts = 5): array
    {
        $params = [
            'SERVICE' => 'WFS',
            'VERSION' => '2.0.0',
            'REQUEST' => 'GetFeature',
            'TYPENAMES' => self::TYPENAME,
            'OUTPUTFORMAT' => 'application/json',
            'SORTBY' => 'cleabs',
            'STARTINDEX' => $start,
            'COUNT' => $count,
            'CQL_FILTER' => $filter,
        ];

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->http()->timeout(180)->get(self::WFS_URL, $params)->throw()->json();
            } catch (ConnectionException|RequestException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $wait = 5 * 2 ** ($attempt - 1);
                $this->warn("  WFS indisponible (STARTINDEX={$start}, tentative {$attempt}) : nouvel essai dans {$wait}s");
                // Sleep (et non sleep()) : simulable dans les tests (Sleep::fake).
                Sleep::for($wait)->seconds();
            }
        }
    }

    private function hits(string $filter): int
    {
        $xml = $this->http()->timeout(60)->get(self::WFS_URL, [
            'SERVICE' => 'WFS',
            'VERSION' => '2.0.0',
            'REQUEST' => 'GetFeature',
            'TYPENAMES' => self::TYPENAME,
            'RESULTTYPE' => 'hits',
            'CQL_FILTER' => $filter,
        ])->throw()->body();

        return preg_match('/numberMatched="(\d+)"/', $xml, $m) ? (int) $m[1] : 0;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)')]);
    }

    /**
     * Upsert d'une page entière en une requête : PostgreSQL déplie le JSON
     * (jsonb_array_elements), ce qui évite 5 000 allers-retours par page.
     * La coordonnée Z de la BD TOPO (-1000 = « pas de Z ») est supprimée.
     *
     * @param  array<int, array<string, mixed>>  $features
     */
    private function upsert(array $features, \DateTimeInterface $seenAt): void
    {
        $rows = [];
        foreach ($features as $f) {
            $props = $f['properties'] ?? [];
            if (empty($props['cleabs']) || empty($f['geometry']['coordinates'])) {
                continue;
            }
            $rows[] = ['p' => $props, 'g' => $f['geometry']];
        }
        if (! $rows) {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, seen_at, created_at, updated_at)
            SELECT 'bdtopo', r->'p'->>'cleabs', LEFT(r->'p'->>'nature', 40), NULLIF(r->'p'->>'cpx_toponyme', ''),
                   r->'p', g, ROUND(ST_Length(g::geography))::int, :seen, NOW(), NOW()
            FROM jsonb_array_elements(CAST(:rows AS jsonb)) r
            CROSS JOIN LATERAL (
                SELECT ST_Multi(ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON(r->>'g'), 4326))) AS g
            ) t
            ON CONFLICT (source, source_ref) DO UPDATE
                SET layer = EXCLUDED.layer, name = EXCLUDED.name, properties = EXCLUDED.properties,
                    geom = EXCLUDED.geom, length_m = EXCLUDED.length_m,
                    seen_at = EXCLUDED.seen_at, updated_at = NOW()
            SQL, [
            'rows' => json_encode($rows, JSON_UNESCAPED_UNICODE),
            'seen' => $seenAt,
        ]);
    }

    private function report(\DateTimeInterface $startedAt, ?array $bbox): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT layer, properties->>'etat_de_l_objet' AS etat, COUNT(*) AS n,
                   ROUND(SUM(length_m) / 1000.0) AS km
            FROM source_traces
            WHERE source = 'bdtopo' AND seen_at >= ?
            GROUP BY 1, 2 ORDER BY 1, 2
            SQL, [$startedAt]);
        $this->table(['Nature', 'État', 'Tronçons', 'km'],
            array_map(fn ($r) => [$r->layer, $r->etat, $r->n, $r->km], $rows));

        $excluded = [];
        foreach (self::EXCLUDED_NATURES as $nature) {
            $excluded[] = "{$nature} : ".$this->hits($this->cqlFilter("nature = '{$nature}'", $bbox));
        }
        $this->line('Exclus (transport urbain, non importés) : '.implode(', ', $excluded));
    }

    private function handleStale(\DateTimeInterface $startedAt, ?array $bbox, bool $complete): void
    {
        $stale = DB::table('source_traces')->where('source', 'bdtopo')->where('seen_at', '<', $startedAt);
        $n = $stale->count();
        if (! $n) {
            return;
        }
        if ($this->option('prune') && $complete && ! $bbox) {
            $stale->delete();
            $this->warn("{$n} tronçons bdtopo absents du WFS supprimés.");
        } elseif ($this->option('prune')) {
            $this->warn("--prune ignoré : import partiel (bbox ou pagination incomplète). {$n} tronçons non revus.");
        } else {
            $this->line("{$n} tronçons bdtopo non revus lors de cet import (--prune après un import complet pour les supprimer).");
        }
    }
}
