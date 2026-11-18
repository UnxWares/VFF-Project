<?php

namespace App\Console\Commands;

use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Importe les vitesses maximales officielles SNCF Réseau par tronçon
 * (dataset `vitesse-maximale-nominale-sur-ligne`) et écrase
 * `line_segments.max_speed_kmh` avec les vraies valeurs.
 *
 * OSM mappe la vitesse par tag `maxspeed` sur chaque way, ce qui inclut
 * souvent des limitations temporaires ou des sections de manœuvre, pas
 * la vitesse nominale officielle : il ne sert qu'en DERNIER RECOURS, aux tronçons sans
 * vitesse SNCF (osmFallback). La provenance est notée dans line_segments.max_speed_source.
 *
 * Durabilité : line_segments est régénéré par vff:build-era-segments, qui laisse la vitesse
 * vide. Chaque téléchargement complet est donc mis en cache
 * (storage/app/sources/sncf-vmax/<jeu>.json) : avec --from-cache, vff:rebuild-history
 * reconstitue les vitesses sans réseau ; avec --cache-only (relance par
 * vff:build-era-segments --line), le réseau n'est jamais interrogé.
 *
 * Chaque tronçon prend la vitesse de l'enregistrement SNCF du même code ligne qui en recouvre
 * la plus grande longueur (formes généralisées : tolérance SNCF_TOL_M), à défaut le plus proche.
 * Le jeu décrit le réseau au jour même : appliqué à l'ère courante, et avec --previous à
 * l'ère précédente (vitesses nominales supposées inchangées depuis la décennie écoulée),
 * SAUF sur les portions modifiées depuis le 31/12 de cette ère (règle validée par
 * l'utilisateur le 20/10/2026) : ouverture, réouverture, fermeture, électrification ou
 * relèvement de vitesse (modernisation dont le texte parle de vitesse) datés après cette date
 * et au plus tard aujourd'hui. Sur ces portions, la vitesse de l'ère passée reste vide (une
 * vitesse posée par un passage précédent est effacée). Portion de l'événement : sa géométrie
 * résolue ; « toute la ligne » ou aucune portion citée : toute la ligne (vide plutôt que faux) ;
 * portion non résolue bornée par des PK : placée par PK sur les tronçons SNCF du même code ;
 * portion nommée introuvable sur le tracé : écartée (elle est le plus souvent sur une autre ligne).
 *
 *   php artisan vff:import-sncf-vmax --from-cache --previous
 *   php artisan vff:import-sncf-vmax --line=752000 --cache-only --previous
 */
class ImportSncfVmax extends Command
{
    protected $signature = 'vff:import-sncf-vmax {--era=}
        {--previous : applique aussi les vitesses à l\'ère qui précède l\'ère choisie (décennie écoulée)}
        {--line= : id ou code d\'une ligne, ou plusieurs séparés par des virgules}
        {--from-cache : jeu SNCF du cache local s\'il existe (téléchargé et mis en cache sinon) : étape hors ligne de vff:rebuild-history}
        {--cache-only : cache local seulement, jamais de réseau ; sans cache, rien n\'est modifié (succès)}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-vmax)}';

    protected $description = 'Importe les vitesses max officielles SNCF.';

    private const DATASET = 'vitesse-maximale-nominale-sur-ligne';

    private const API = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/';

    /** Écart max (m) entre un tronçon et la forme généralisée SNCF dont il prend la vitesse. */
    private const SNCF_TOL_M = 200;

    /** Événements qui modifient une portion (et sa vitesse) : pas de vitesse du jour avant eux. */
    public const CHANGE_TYPES = ['opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger',
        'reopening_freight', 'closure', 'closure_passenger', 'closure_freight', 'electrification'];

    /** Modernisation qui relève la vitesse : texte (description ou citation) qui parle de vitesse. */
    public const SPEED_CHANGE_RX = 'vitesse|km ?/ ?h|relèvement|relevement';

    /** Tolérance (m) autour de la portion d'un événement, et recouvrement minimal d'un tronçon (m). */
    private const CHANGE_TOL_M = 30;

    private const CHANGE_MIN_OVERLAP_M = 100;

    /** Écart max (m) entre un tronçon et la voie OSM dont il prend le maxspeed (dernier recours). */
    private const OSM_TOL_M = 10;

    /** Colonne max_speed_source présente (migration du 2026-10-20 appliquée) : sinon ni provenance ni OSM. */
    private bool $hasSource = true;

    /** Affectation de la provenance, vide sans la colonne. */
    private function src(string $value): string
    {
        return $this->hasSource ? ", max_speed_source = {$value}" : '';
    }

    public function handle(): int
    {
        $this->hasSource = Schema::hasColumn('line_segments', 'max_speed_source');
        $era = $this->option('era')
            ? DB::table('eras')->where('year', $this->option('era'))->first(['id', 'year'])
            : DB::table('eras')->where('is_current', true)->first(['id', 'year']);

        // Ère inconnue (--era=1855) ou aucune ère courante : erreur propre, aucune écriture.
        if (! $era) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }
        $eraIds = [(int) $era->id];
        if ($this->option('previous') && ($prev = DB::table('eras')->where('year', '<', $era->year)->orderByDesc('year')->value('id'))) {
            $eraIds[] = (int) $prev;
        }

        $lineIds = null;
        if ($opt = $this->option('line')) {
            $refs = array_values(array_filter(array_map('trim', explode(',', $opt)), fn ($r) => $r !== ''));
            $ids = array_map('intval', array_filter($refs, fn ($r) => ctype_digit($r) && strlen($r) < 6));
            $lineIds = DB::table('lines')->where(fn ($w) => $w->whereIn('code', $refs)->orWhereIn('id', $ids ?: [0]))->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($lineIds === []) {
                $this->warn('Aucune ligne trouvée pour --line : rien à faire.');

                return self::SUCCESS;
            }
        }

        $this->info('Import SNCF vmax (ères '.DB::table('eras')->whereIn('id', $eraIds)->orderByDesc('year')->pluck('year')->implode(', ').')…');
        try {
            $records = $this->records();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage().' : aucune vitesse modifiée.');

            return self::FAILURE;
        }
        if ($records === null) {
            $this->warn('Pas de cache local des vitesses SNCF (--cache-only) : aucune vitesse modifiée.');

            return self::SUCCESS;
        }

        // Codes des lignes visées : seuls leurs enregistrements sont chargés (relance par ligne rapide).
        $codes = $lineIds === null ? null
            : array_flip(DB::table('lines')->whereIn('id', $lineIds)->whereNotNull('code')->pluck('code')->all());

        DB::statement('DROP TABLE IF EXISTS tmp_sncf_vmax');
        DB::statement('CREATE TEMP TABLE tmp_sncf_vmax (code_ligne VARCHAR(10), v_max INT, geom GEOMETRY(LineString, 4326), buf GEOMETRY, pkd_m INT, pkf_m INT, d_lon FLOAT, d_lat FLOAT)');
        $rows = [];
        foreach ($records as $r) {
            $code = $r['code_ligne'] ?? null;
            $vmax = isset($r['v_max']) && is_numeric($r['v_max']) ? (int) $r['v_max'] : null;
            $shape = $r['geo_shape']['geometry'] ?? null;
            if (! $code || ! $vmax || ! $shape || ($shape['type'] ?? '') !== 'LineString') {
                continue;
            }
            if ($codes !== null && ! isset($codes[$code])) {
                continue;
            }
            $rows[] = [$code, $vmax, json_encode($shape), self::pk($r['pkd'] ?? null), self::pk($r['pkf'] ?? null),
                $r['c_geo_d']['lon'] ?? null, $r['c_geo_d']['lat'] ?? null];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::insert('INSERT INTO tmp_sncf_vmax (code_ligne, v_max, geom, pkd_m, pkf_m, d_lon, d_lat) VALUES '.implode(', ', array_fill(0, count($chunk), '(?, ?, ST_GeomFromGeoJSON(?), ?, ?, ?, ?)')),
                array_merge(...$chunk));
        }
        DB::statement('UPDATE tmp_sncf_vmax SET buf = ST_Buffer(geom::geography, ?)::geometry', [self::SNCF_TOL_M]);
        DB::statement('CREATE INDEX ON tmp_sncf_vmax USING gist (buf)');
        $this->line('  '.count($rows).' enregistrements SNCF');

        // Pour chaque tronçon : l'enregistrement du même code qui en recouvre la plus grande
        // longueur (à égalité, le plus proche de son milieu). Ères passées : hors portions
        // modifiées depuis leur 31/12 (vitesse effacée).
        $lineFilter = $lineIds === null ? '' : 'AND ls2.line_id IN ('.implode(',', $lineIds).')';
        [$eraFilter, $cleared] = $this->pastEraExclusions($eraIds, $lineIds, $lineFilter);
        $updated = DB::update("
            UPDATE line_segments ls
            SET max_speed_kmh = best.v_max{$this->src("'sncf'")}, updated_at = NOW()
            FROM (
                SELECT DISTINCT ON (ls2.id) ls2.id AS seg_id, sv.v_max
                FROM line_segments ls2
                JOIN lines l ON l.id = ls2.line_id
                JOIN tmp_sncf_vmax sv ON sv.code_ligne = l.code AND ST_Intersects(ls2.geom, sv.buf)
                WHERE {$eraFilter} {$lineFilter}
                ORDER BY ls2.id, ST_Length(ST_Intersection(ls2.geom, sv.buf)::geography) DESC,
                         ST_Distance(ST_Centroid(ls2.geom)::geography, sv.geom::geography) ASC
            ) best
            WHERE ls.id = best.seg_id AND (ls.max_speed_kmh IS DISTINCT FROM best.v_max".($this->hasSource ? " OR ls.max_speed_source IS DISTINCT FROM 'sncf'" : '').')
        ');
        $osm = $this->hasSource ? $this->osmFallback($eraFilter, $lineFilter) : 0;

        $this->info("    ✓ {$updated} segments mis à jour");
        if ($osm > 0) {
            $this->line("    {$osm} segments sans vitesse SNCF : maxspeed OSM (dernier recours, max_speed_source = osm)");
        }
        if ($cleared > 0) {
            $this->line("    {$cleared} vitesses d'ère passée effacées (portions modifiées depuis)");
        }
        DB::statement('DROP TABLE IF EXISTS tmp_sncf_vmax');
        DB::statement('DROP TABLE IF EXISTS tmp_vmax_changed');

        return self::SUCCESS;
    }

    /**
     * Ères passées visées (--previous, ou --era hors ère courante) : le jeu du jour ne vaut pas
     * pour les portions modifiées depuis le 31/12 de l'ère. Construit tmp_vmax_changed (portions
     * modifiées, tampon CHANGE_TOL_M ; NULL = toute la ligne), efface la vitesse des tronçons
     * concernés et renvoie le filtre SQL des tronçons à remplir (alias ls2) et le nombre effacé.
     *
     * @param  list<int>  $eraIds
     * @param  list<int>|null  $lineIds
     * @return array{0: string, 1: int}
     */
    private function pastEraExclusions(array $eraIds, ?array $lineIds, string $lineFilter): array
    {
        $past = DB::table('eras')->whereIn('id', $eraIds)->where('is_current', false)->pluck('year', 'id')->all();
        if ($past === []) {
            return ['ls2.era_id IN ('.implode(',', $eraIds).')', 0];
        }
        $types = "'".implode("', '", self::CHANGE_TYPES)."'";
        $eventLines = $lineIds === null ? '' : 'AND e.line_id IN ('.implode(',', $lineIds).')';
        DB::statement('DROP TABLE IF EXISTS tmp_vmax_changed');
        DB::statement(<<<SQL
            CREATE TEMP TABLE tmp_vmax_changed AS
            WITH ev AS (
                SELECT e.line_id, e.event_date, e.section_geom, e.section_pk_start_m AS a, e.section_pk_end_m AS b, l.code,
                       CASE WHEN e.section_resolution = 'whole_line' THEN 'whole'
                            WHEN e.section_geom IS NOT NULL THEN 'geom'
                            WHEN e.section_pk_start_m IS NOT NULL AND e.section_pk_end_m IS NOT NULL THEN 'pk'
                            WHEN e.section_from IS NULL AND e.section_to IS NULL THEN 'whole'
                            ELSE 'named' END AS kind
                FROM line_events e JOIN lines l ON l.id = e.line_id
                WHERE e.event_date <= CURRENT_DATE {$eventLines}
                  AND (e.type IN ({$types})
                       OR (e.type = 'modernization' AND (e.description || ' ' || COALESCE(e.source_quote, '')) ~* ?))
            )
            SELECT line_id, event_date, kind, CASE kind WHEN 'whole' THEN NULL ELSE ST_Buffer(g::geography, ?)::geometry END AS buf
            FROM (
                SELECT ev.*, CASE ev.kind WHEN 'geom' THEN ev.section_geom WHEN 'pk' THEN (
                    -- Portion non résolue mais bornée par ses PK : placée sur les tronçons SNCF du même
                    -- code (PK de début / fin de chaque enregistrement, interpolés le long de sa forme).
                    SELECT ST_Collect(ST_LineSubstring(sv.geom, LEAST(f0, f1), GREATEST(f0, f1)))
                    FROM tmp_sncf_vmax sv,
                         LATERAL (SELECT ST_Distance(ST_StartPoint(sv.geom), ST_SetSRID(ST_MakePoint(sv.d_lon, sv.d_lat), 4326))
                                       > ST_Distance(ST_EndPoint(sv.geom), ST_SetSRID(ST_MakePoint(sv.d_lon, sv.d_lat), 4326)) AS rev) o,
                         LATERAL (SELECT GREATEST(0, LEAST(1, (LEAST(ev.a, ev.b) - sv.pkd_m)::float / (sv.pkf_m - sv.pkd_m))) AS p0,
                                         GREATEST(0, LEAST(1, (GREATEST(ev.a, ev.b) - sv.pkd_m)::float / (sv.pkf_m - sv.pkd_m))) AS p1) p,
                         LATERAL (SELECT CASE WHEN o.rev THEN 1 - p.p0 ELSE p.p0 END AS f0, CASE WHEN o.rev THEN 1 - p.p1 ELSE p.p1 END AS f1) f
                    WHERE sv.code_ligne = ev.code AND sv.pkf_m > sv.pkd_m AND sv.d_lon IS NOT NULL
                      AND sv.pkd_m < GREATEST(ev.a, ev.b) AND sv.pkf_m > LEAST(ev.a, ev.b)
                ) END AS g
                FROM ev
            ) x
            -- Portion nommée introuvable sur le tracé, ou PK hors des tronçons SNCF : on ne sait pas où
            -- elle est (souvent une autre ligne) ; écartée plutôt que d'effacer toute la ligne.
            WHERE kind = 'whole' OR g IS NOT NULL
            SQL, [self::SPEED_CHANGE_RX, self::CHANGE_TOL_M]);
        $modified = fn (string $cutoff) => "EXISTS (SELECT 1 FROM tmp_vmax_changed c WHERE c.line_id = ls2.line_id AND c.event_date > DATE '{$cutoff}'
            AND (c.buf IS NULL OR (ST_Intersects(ls2.geom, c.buf)
                 AND ST_Length(ST_Intersection(ls2.geom, c.buf)::geography) >= LEAST(".self::CHANGE_MIN_OVERLAP_M.', 0.5 * ST_Length(ls2.geom::geography)))))';
        $parts = [];
        $cleared = 0;
        foreach (array_diff($eraIds, array_keys($past)) as $id) {
            $parts[] = "ls2.era_id = {$id}";
        }
        foreach ($past as $id => $year) {
            $cond = $modified(sprintf('%04d-12-31', (int) $year));
            $parts[] = "(ls2.era_id = {$id} AND NOT {$cond})";
            $cleared += DB::update("UPDATE line_segments ls2 SET max_speed_kmh = NULL{$this->src('NULL')}, updated_at = NOW()
                WHERE ls2.era_id = {$id} AND ls2.max_speed_kmh IS NOT NULL {$lineFilter} AND {$cond}");
        }

        return ['('.implode(' OR ', $parts).')', $cleared];
    }

    /**
     * Dernier recours, tronçons en service encore sans vitesse SNCF (code absent du jeu : Corse,
     * ligne de Sceaux, codes hors RFN ; portion à plus de SNCF_TOL_M de la forme SNCF) : tag
     * maxspeed (nombre seul) des voies OSM posées à moins de OSM_TOL_M, valeur qui recouvre le plus
     * de longueur, et au moins la moitié du tronçon. Jamais 250 km/h ou plus hors ligne LGV (le
     * signal vitesse de App\Support\Lgv peindrait la ligne en LGV). Une vitesse OSM déjà posée est
     * reprise à chaque passage (rejouable) ; une vitesse SNCF n'est jamais remplacée.
     */
    private function osmFallback(string $eraFilter, string $lineFilter): int
    {
        $lgv = Lgv::sql('l', null);

        return DB::update("
            UPDATE line_segments ls
            SET max_speed_kmh = best.v, max_speed_source = 'osm', updated_at = NOW()
            FROM (
                SELECT DISTINCT ON (seg_id) seg_id, v FROM (
                    SELECT ls2.id AS seg_id, (st.properties->>'maxspeed')::int AS v, ST_Length(ls2.geom::geography) AS len,
                           SUM(ST_Length(ST_Intersection(st.geom, ST_Buffer(ls2.geom::geography, ".self::OSM_TOL_M.")::geometry)::geography)) AS ov
                    FROM line_segments ls2
                    JOIN lines l ON l.id = ls2.line_id
                    JOIN source_traces st ON st.source = 'osm' AND st.layer IN ('rail', 'narrow_gauge', 'light_rail')
                         AND st.geom && ST_Expand(ls2.geom, 0.0002) AND ST_DWithin(st.geom::geography, ls2.geom::geography, ".self::OSM_TOL_M.")
                         AND st.properties->>'maxspeed' ~ '^[0-9]{2,3}$'
                    WHERE {$eraFilter} {$lineFilter}
                      AND ls2.status IN ('in_service', 'single_track') AND NOT ls2.is_heritage
                      AND (ls2.max_speed_kmh IS NULL OR ls2.max_speed_source = 'osm')
                      AND ((st.properties->>'maxspeed')::int < ".Lgv::MIN_SPEED_KMH." OR {$lgv})
                    GROUP BY ls2.id, v, ls2.geom
                ) c
                WHERE ov >= 0.5 * len
                ORDER BY seg_id, ov DESC, v ASC
            ) best
            WHERE ls.id = best.seg_id AND (ls.max_speed_kmh IS DISTINCT FROM best.v OR ls.max_speed_source IS DISTINCT FROM 'osm')
        ");
    }

    /** PK SNCF « 629+739 » en mètres. */
    private static function pk(mixed $pk): ?int
    {
        return is_string($pk) && preg_match('/^(\d+)\+(\d{1,3})$/', trim($pk), $m) ? (int) $m[1] * 1000 + (int) $m[2] : null;
    }

    /**
     * Enregistrements du jeu SNCF : cache local (--from-cache, --cache-only) ou téléchargement
     * complet, mis en cache. null : --cache-only sans cache.
     *
     * @return list<array<string, mixed>>|null
     */
    private function records(): ?array
    {
        // Tests : jamais le vrai cache (des données factices y serviraient ensuite au pipeline).
        $dir = $this->option('cache-dir') ?: (app()->runningUnitTests() ? null : storage_path('app/sources/sncf-vmax'));
        $cache = $dir === null ? null : rtrim($dir, '/').'/'.self::DATASET.'.json';
        if ($cache !== null && ($this->option('from-cache') || $this->option('cache-only')) && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached['records'] ?? null) && $cached['records'] !== []) {
                $this->line('  cache du '.($cached['fetched_at'] ?? '?').' ('.count($cached['records']).' enregistrements)');

                return $cached['records'];
            }
        }
        if ($this->option('cache-only')) {
            return null;
        }
        $all = $this->download();
        if ($cache !== null && $all !== []) {
            @mkdir(dirname($cache), 0775, true);
            file_put_contents($cache, json_encode(['dataset' => self::DATASET, 'fetched_at' => now()->toDateTimeString(), 'records' => $all],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $all;
    }

    /** @return list<array<string, mixed>> */
    private function download(): array
    {
        $limit = 100;
        $offset = 0;
        $all = [];
        do {
            try {
                $response = Http::timeout(60)->withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')
                    ->get(self::API.self::DATASET.'/records', ['limit' => $limit, 'offset' => $offset]);
            } catch (ConnectionException) {
                throw new \RuntimeException('SNCF injoignable');
            }
            // Source en panne : échec franc, aucune vitesse modifiée.
            if (! $response->successful()) {
                throw new \RuntimeException("SNCF HTTP {$response->status()}");
            }
            $records = $response->json('results') ?? [];
            array_push($all, ...$records);
            $offset += $limit;
        } while (count($records) === $limit && $offset < 10000);

        return $all;
    }
}
