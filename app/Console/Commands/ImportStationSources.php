<?php

namespace App\Console\Commands;

use App\Services\RouteDiagramStations;
use App\Services\SectionResolver;
use App\Services\WikimediaClient;
use App\Services\WikipediaLineParser;
use App\Support\StationNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;

/**
 * Importe les gares et haltes (actuelles ET disparues) de chaque source dans
 * `station_sources`, telles que décrites par la source, sans fusion.
 *
 *   - communes : contours Etalab 100 m (géocodage inverse, filtre France) ;
 *   - sncf     : `sncf_gares` (liste-des-gares, déjà importée) agrégée par UIC,
 *                + jeu gares-de-voyageurs (source « sncf_voyageurs ») ;
 *   - osm      : Overpass par région (railway=station|halt, variantes
 *                disused/abandoned/razed/demolished/was/historic:railway,
 *                historic=railway_station, building=train_station nommés) ;
 *   - ohm      : OpenHistoricalMap (gares datées start_date / end_date) ;
 *   - wikidata : gares françaises (classes « gare ferroviaire » et
 *                sous-classes) via CirrusSearch + wbgetentities (P625, P1619,
 *                P3999, P576, P571, P722, P5817, P81) ;
 *   - pk       : gares PAR LIGNE des listes historiques (`source_stations` :
 *                lignes-oubliees ; schémas de ligne Wikipédia : articles de
 *                ligne et de réseau), posées sur le tracé de leur ligne par
 *                SectionResolver::placeStations() : gare connue homonyme, sinon
 *                PK calé comme les portions des événements, sinon commune
 *                homonyme traversée (source « wikipedia_schema » pour les schémas).
 *
 * Toutes les réponses brutes sont mises en cache dans
 * storage/app/sources/stations ; --from-cache rejoue sans réseau. Idempotent
 * (upsert sur source + source_ref) ; les objets d'une source qui ne sont plus
 * renvoyés sont supprimés à la fin d'un import complet de cette source.
 *
 *   php artisan vff:import-station-sources                 # tout
 *   php artisan vff:import-station-sources --source=osm --region=FR-BRE
 *   php artisan vff:import-station-sources --from-cache
 */
class ImportStationSources extends Command
{
    protected $signature = 'vff:import-station-sources
        {--source=* : communes | sncf | osm | ohm | wikidata | pk (défaut : toutes, dans cet ordre)}
        {--from-cache : Rejoue les réponses déjà téléchargées, sans requête réseau}
        {--region=* : (osm) Limite aux régions ISO 3166-2 données, ex. FR-BRE}
        {--pause=20 : Pause (s) entre deux requêtes Overpass}
        {--line=* : (pk) Limite aux lignes données (id ou code), sans supprimer les autres}
        {--dry-run : (pk) Place les gares et affiche le bilan, sans rien écrire}
        {--validate : (pk) Validation croisée des calages propres aux schémas (plus lent)}';

    protected $description = 'Gares multi-sources (SNCF, OSM, OHM, Wikidata) → station_sources (brut, avec provenance)';

    private const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    private const OVERPASS = 'https://overpass-api.de/api/interpreter';

    private const OHM_OVERPASS = 'https://overpass-api.openhistoricalmap.org/api/interpreter';

    private const COMMUNES_URL = 'https://etalab-datasets.geo.data.gouv.fr/contours-administratifs/2025/geojson/communes-100m.geojson.gz';

    /** Régions métropolitaines (ISO 3166-2, admin_level=4 dans OSM). */
    private const REGIONS = [
        'FR-ARA', 'FR-BFC', 'FR-BRE', 'FR-CVL', 'FR-20R', 'FR-GES', 'FR-HDF',
        'FR-IDF', 'FR-NOR', 'FR-NAQ', 'FR-OCC', 'FR-PDL', 'FR-PAC',
    ];

    /**
     * Classes Wikidata retenues : gare ferroviaire (Q55488), halte (Q55678),
     * ancienne gare (Q4663385), gare abandonnée (Q106772341), station de RER,
     * et les sous-classes « gare de… » présentes en France. Exclues : métro,
     * tramway, gares de marchandises (sites distincts des gares voyageurs).
     */
    private const WD_CLASSES = [
        'Q55488', 'Q55678', 'Q4663385', 'Q106772341', 'Q1793804', 'Q55485', 'Q55491',
        'Q1402443', 'Q11606300', 'Q55677', 'Q58484445', 'Q332496', 'Q1567913', 'Q55484',
        'Q1147171', 'Q55490', 'Q28837381', 'Q118386331', 'Q106997999', 'Q8015548',
        'Q15855903', 'Q1335652', 'Q13433889', 'Q2142091', 'Q61755440', 'Q125695092',
    ];

    /** Lignes de métro / tram : un élément qui n'est QUE ça est écarté. */
    private const WD_EXCLUDED_ONLY = ['Q928830', 'Q63979268', 'Q2175765', 'Q55493'];

    private bool $fromCache = false;

    public function handle(): int
    {
        ini_set('memory_limit', '2G');
        $this->fromCache = (bool) $this->option('from-cache');
        @mkdir($this->cacheDir(), 0775, true);

        $sources = (array) $this->option('source') ?: ['communes', 'sncf', 'osm', 'ohm', 'wikidata', 'pk'];
        // --dry-run, --line et --validate ne valent que pour la source pk : jamais d'écriture des autres sources.
        if ($this->option('dry-run') || $this->option('line') || $this->option('validate')) {
            if ($this->option('source') && $sources !== ['pk']) {
                $this->error('--dry-run, --line et --validate : seulement avec --source=pk');

                return self::FAILURE;
            }
            $sources = ['pk'];
        }
        foreach ($sources as $source) {
            $method = 'import'.ucfirst($source);
            if (! method_exists($this, $method)) {
                $this->error("Source inconnue : {$source}");

                return self::FAILURE;
            }
            $this->info("── {$source}");
            $this->{$method}();
        }

        $this->table(['source', 'objets', 'datés (ouverture)', 'datés (fermeture)'], array_map(fn ($r) => (array) $r, DB::select(
            'SELECT source, COUNT(*) AS n, COUNT(opened_on) AS o, COUNT(closed_on) AS c FROM station_sources GROUP BY source ORDER BY source'
        )));

        return self::SUCCESS;
    }

    // ── communes ────────────────────────────────────────────────────────────

    private function importCommunes(): void
    {
        $raw = $this->cachedHttp('communes-100m.geojson.gz', fn () => Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(300)->get(self::COMMUNES_URL));
        if ($raw === null) {
            return;
        }
        $data = json_decode(gzdecode($raw), true, 512, JSON_THROW_ON_ERROR);
        unset($raw);
        $n = 0;
        foreach (array_chunk($data['features'], 200) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $f) {
                $p = $f['properties'];
                if (empty($p['code']) || empty($f['geometry'])) {
                    continue;
                }
                $values[] = '(?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), NOW(), NOW())';
                array_push($bindings, $p['code'], $p['nom'] ?? $p['code'], $p['departement'] ?? null, json_encode($f['geometry']));
            }
            if ($values) {
                DB::statement('INSERT INTO commune_contours (code_insee, nom, departement, geom, created_at, updated_at) VALUES '
                    .implode(',', $values)
                    .' ON CONFLICT (code_insee) DO UPDATE SET nom = EXCLUDED.nom, departement = EXCLUDED.departement, geom = EXCLUDED.geom, updated_at = NOW()',
                    $bindings);
                $n += count($values);
            }
        }
        $this->line("  {$n} communes");
    }

    // ── SNCF ────────────────────────────────────────────────────────────────

    /**
     * Une gare SNCF = un code UIC ; elle apparaît une fois par ligne qui la
     * dessert dans liste-des-gares (code ligne + PK + rang de tronçon).
     */
    private function importSncf(): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT code_uic,
                   MODE() WITHIN GROUP (ORDER BY libelle) AS libelle,
                   MODE() WITHIN GROUP (ORDER BY commune) AS commune,
                   MODE() WITHIN GROUP (ORDER BY department) AS department,
                   BOOL_OR(voyageurs) AS voyageurs, BOOL_OR(fret) AS fret,
                   AVG(lon) AS lon, AVG(lat) AS lat,
                   MAX(ST_Distance(ST_MakePoint(lon, lat)::geography,
                       ST_MakePoint(c.clon, c.clat)::geography)) AS spread_m,
                   JSON_AGG(JSON_BUILD_OBJECT('code_ligne', code_ligne, 'pk_m', pk_m, 'rg_troncon', rg_troncon,
                       'voyageurs', voyageurs, 'fret', fret) ORDER BY code_ligne) AS lines
            FROM sncf_gares g
            JOIN LATERAL (SELECT AVG(lon) AS clon, AVG(lat) AS clat FROM sncf_gares g2 WHERE g2.code_uic = g.code_uic) c ON TRUE
            WHERE code_uic IS NOT NULL AND lat IS NOT NULL AND lon IS NOT NULL
            GROUP BY code_uic
        SQL);

        $records = [];
        foreach ($rows as $r) {
            $records[] = [
                'source_ref' => $r->code_uic,
                'name' => StationNames::prettify($r->libelle),
                'kind' => null,
                'lifecycle' => ($r->voyageurs || $r->fret) ? 'active' : 'disused',
                'uic_code' => $r->code_uic,
                'passenger' => (bool) $r->voyageurs,
                'freight' => (bool) $r->fret,
                'properties' => [
                    'commune' => $r->commune,
                    'department' => $r->department,
                    'lines' => json_decode($r->lines, true),
                    'position_spread_m' => (int) round((float) $r->spread_m),
                ],
                'lon' => (float) $r->lon,
                'lat' => (float) $r->lat,
            ];
        }
        $this->upsertSource('sncf', $records, true);

        // Complément : gares-de-voyageurs (toutes les gares ouvertes aux
        // voyageurs, dont celles absentes de liste-des-gares, ex. Arcachon).
        $raw = $this->cachedHttp('sncf/gares-de-voyageurs.json', fn () => Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(120)->retry(3, 5000)
            ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/gares-de-voyageurs/exports/json'));
        $records = [];
        foreach (json_decode($raw ?? '[]', true) ?: [] as $g) {
            $pos = $g['position_geographique'] ?? null;
            $uic = preg_replace('/\D/', '', explode(';', (string) ($g['codes_uic'] ?? ''))[0]);
            if (! $pos || ! $uic || empty($g['nom'])) {
                continue;
            }
            $records[$uic] = [
                'source_ref' => $uic,
                'name' => $g['nom'],
                'lifecycle' => 'active',
                'uic_code' => $uic,
                'passenger' => true,
                'properties' => ['segment_drg' => $g['segment_drg'] ?? null, 'codeinsee' => $g['codeinsee'] ?? null,
                    'libellecourt' => $g['libellecourt'] ?? null, 'codes_uic' => $g['codes_uic'] ?? null],
                'lon' => (float) $pos['lon'],
                'lat' => (float) $pos['lat'],
            ];
        }
        $this->upsertSource('sncf_voyageurs', array_values($records), $records !== []);
    }

    // ── OpenStreetMap ───────────────────────────────────────────────────────

    private function importOsm(): void
    {
        $regions = array_values(array_unique(array_map(
            fn ($r) => preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string) $r)),
            (array) $this->option('region') ?: self::REGIONS,
        )));
        $complete = $regions === self::REGIONS;
        $records = [];
        $pause = (int) $this->option('pause');
        foreach ($regions as $i => $region) {
            $q = <<<QL
                [out:json][timeout:600];
                area["ISO3166-2"="{$region}"]["admin_level"="4"]->.a;
                (
                  nwr["railway"~"^(station|halt)$"](area.a);
                  nwr["disused:railway"~"^(station|halt)$"](area.a);
                  nwr["abandoned:railway"~"^(station|halt)$"](area.a);
                  nwr["razed:railway"~"^(station|halt)$"](area.a);
                  nwr["demolished:railway"~"^(station|halt)$"](area.a);
                  nwr["was:railway"~"^(station|halt)$"](area.a);
                  nwr["historic:railway"~"^(station|halt)$"](area.a);
                  nwr["historic"="railway_station"](area.a);
                  nwr["building"="train_station"]["name"](area.a);
                );
                out center tags;
                QL;
            $cached = is_file($this->cacheDir()."/osm/{$region}.json");
            $raw = $this->cachedHttp("osm/{$region}.json", fn () => $this->overpass(self::OVERPASS, $q));
            if ($raw === null) {
                $this->warn("  {$region} : échec, région ignorée");
                $complete = false;

                continue;
            }
            $els = json_decode($raw, true)['elements'] ?? [];
            $kept = 0;
            foreach ($els as $e) {
                if ($rec = $this->parseOsmLike($e, 'osm')) {
                    $records[$rec['source_ref']] = $rec;
                    $kept++;
                }
            }
            $this->line(sprintf('  %s : %d objets, %d retenus', $region, count($els), $kept));
            if (! $cached && ! $this->fromCache && $i < count($regions) - 1) {
                Sleep::sleep($pause);
            }
        }
        $this->upsertSource('osm', array_values($records), $complete);
    }

    // ── OpenHistoricalMap ───────────────────────────────────────────────────

    private function importOhm(): void
    {
        // Emprise métropole découpée en 4 tuiles ; filtre France par les
        // contours communaux après import.
        $tiles = ['nw' => [46.2, -5.3, 51.2, 2.2], 'ne' => [46.2, 2.2, 51.2, 9.7],
            'sw' => [41.3, -5.3, 46.2, 2.2], 'se' => [41.3, 2.2, 46.2, 9.7]];
        $records = [];
        $complete = true;
        foreach ($tiles as $name => [$s, $w, $n, $e]) {
            $bb = "{$s},{$w},{$n},{$e}";
            $q = <<<QL
                [out:json][timeout:300];
                (
                  nwr["railway"~"^(station|halt)$"]({$bb});
                  nwr["disused:railway"~"^(station|halt)$"]({$bb});
                  nwr["abandoned:railway"~"^(station|halt)$"]({$bb});
                  nwr["historic"="railway_station"]({$bb});
                  nwr["building"="train_station"]["name"]({$bb});
                );
                out center tags;
                QL;
            $cached = is_file($this->cacheDir()."/ohm/{$name}.json");
            $raw = $this->cachedHttp("ohm/{$name}.json", fn () => $this->overpass(self::OHM_OVERPASS, $q));
            if ($raw === null) {
                $this->warn("  tuile {$name} : échec");
                $complete = false;

                continue;
            }
            $els = json_decode($raw, true)['elements'] ?? [];
            foreach ($els as $el) {
                if ($rec = $this->parseOsmLike($el, 'ohm')) {
                    $records[$rec['source_ref']] = $rec;
                }
            }
            $this->line("  tuile {$name} : ".count($els).' objets');
            if (! $cached && ! $this->fromCache) {
                Sleep::sleep(5);
            }
        }
        // Une tuile en échec : ses gares ne sont pas « disparues » (avant : supprimées).
        $this->upsertSource('ohm', array_values($records), $complete);

        // Hors de France (Allemagne, Belgique, Suisse, Italie, Espagne…).
        if (DB::table('commune_contours')->exists()) {
            $del = DB::delete(<<<'SQL'
                DELETE FROM station_sources ss
                WHERE ss.source = 'ohm'
                  AND NOT EXISTS (SELECT 1 FROM commune_contours c
                                  WHERE c.geom && ST_Expand(ss.geom, 0.003) AND ST_DWithin(c.geom, ss.geom, 0.003))
            SQL);
            $this->line("  {$del} objets OHM hors de France écartés");
        }
    }

    /**
     * Normalise un élément OSM / OHM (tags « cycle de vie » compris).
     *
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>|null
     */
    /**
     * Objet OSM d'un autre mode que le train (métro, tram, funiculaire, monorail, train
     * miniature), y compris désaffecté (« disused:funicular=yes », Saint-Hilaire-du-Touvet).
     *
     * @param  array<string, mixed>  $t
     */
    public static function isOffNetworkOsm(array $t): bool
    {
        foreach (['', 'disused:', 'abandoned:'] as $prefix) {
            if (in_array($t[$prefix.'station'] ?? null, ['subway', 'tram', 'light_rail', 'funicular', 'monorail', 'miniature'], true)) {
                return true;
            }
            // Funiculaire, monorail : jamais une gare de train (« disused:train=yes » y désigne la cabine).
            foreach (['funicular', 'monorail'] as $mode) {
                if (($t[$prefix.$mode] ?? null) === 'yes') {
                    return true;
                }
            }
            // Métro, tram : sauf gare commune avec le train (RER, tram-train).
            foreach (['subway', 'tram', 'light_rail'] as $mode) {
                if (($t[$prefix.$mode] ?? null) === 'yes' && ($t[$prefix.'train'] ?? $t['train'] ?? null) !== 'yes') {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseOsmLike(array $e, string $source): ?array
    {
        $t = $e['tags'] ?? [];
        $lat = $e['lat'] ?? $e['center']['lat'] ?? null;
        $lon = $e['lon'] ?? $e['center']['lon'] ?? null;
        if ($lat === null || $lon === null) {
            return null;
        }

        // Écarte métro, tram, funiculaire, train miniature.
        if (self::isOffNetworkOsm($t)) {
            return null;
        }
        if (isset($t['aerialway']) || ($t['amenity'] ?? null) === 'ferry_terminal') {
            return null;
        }

        $kind = null;
        $lifecycle = null;
        $prefixes = ['' => 'active', 'disused:' => 'disused', 'abandoned:' => 'abandoned', 'razed:' => 'razed',
            'demolished:' => 'razed', 'was:' => 'razed', 'historic:' => 'historic'];
        foreach ($prefixes as $prefix => $state) {
            $v = $t[$prefix.'railway'] ?? null;
            if (in_array($v, ['station', 'halt'], true)) {
                $kind = $v === 'halt' ? 'halte' : 'gare';
                $lifecycle = $state;
                break;
            }
        }
        if ($kind === null && ($t['historic'] ?? null) === 'railway_station') {
            [$kind, $lifecycle] = ['gare', 'historic'];
        }
        if ($kind === null && ($t['building'] ?? null) === 'train_station') {
            [$kind, $lifecycle] = ['gare', 'unknown'];
        }
        if ($kind === null) {
            return null;
        }
        // « railway=station » resté en place à côté de « disused:railway=station » (Saint-Hilaire-du-Touvet) :
        // l'étiquette désaffectée, plus précise, l'emporte.
        if ($lifecycle === 'active' && in_array($t['disused:railway'] ?? $t['abandoned:railway'] ?? null, ['station', 'halt'], true)) {
            $lifecycle = isset($t['disused:railway']) ? 'disused' : 'abandoned';
        }
        if ($lifecycle === 'active') {
            if (($t['disused'] ?? null) === 'yes') {
                $lifecycle = 'disused';
            } elseif (($t['abandoned'] ?? null) === 'yes') {
                $lifecycle = 'abandoned';
            }
        }

        $name = $t['name'] ?? $t['old_name'] ?? $t['was:name'] ?? $t['name:fr'] ?? null;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $opened = $this->parseDate($t['start_date'] ?? $t['opening_date'] ?? $t['railway:start_date'] ?? null);
        $closed = $this->parseDate($t['end_date'] ?? $t['railway:end_date'] ?? $t['disused:start_date'] ?? null);
        // OHM : un objet sans end_date existe toujours (ou date de fin inconnue).
        if ($source === 'ohm') {
            $lifecycle = $closed ? 'historic' : ($lifecycle === 'active' ? 'unknown' : $lifecycle);
        }

        // uic_ref peut lister plusieurs codes (« 8731605;8731606 ») : on garde le premier.
        $uic = isset($t['uic_ref']) ? substr(preg_replace('/\D/', '', preg_split('/[;,\s]+/', trim((string) $t['uic_ref']))[0]), 0, 10) : null;
        $wd = isset($t['wikidata']) && preg_match('/^Q\d+$/', $t['wikidata']) ? $t['wikidata'] : null;

        return [
            'source_ref' => substr($e['type'], 0, 1).$e['id'],
            'name' => $name,
            'kind' => $kind,
            'lifecycle' => $lifecycle,
            'uic_code' => $uic ?: null,
            'wikidata_id' => $wd,
            'opened_on' => $opened[0] ?? null,
            'opened_precision' => $opened[1] ?? null,
            'closed_on' => $closed[0] ?? null,
            'closed_precision' => $closed[1] ?? null,
            'closed_scope' => $closed ? 'all' : null,
            'properties' => $t,
            'lon' => (float) $lon,
            'lat' => (float) $lat,
        ];
    }

    // ── Wikidata ────────────────────────────────────────────────────────────

    private function importWikidata(): void
    {
        // 1. Liste des éléments par classe (CirrusSearch, paginé par 500).
        $ids = [];
        $complete = true;
        foreach (self::WD_CLASSES as $cls) {
            $offset = 0;
            do {
                $raw = $this->cachedHttp("wikidata/search-{$cls}-{$offset}.json", fn () => Http::withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(60)->retry(3, 5000)->get('https://www.wikidata.org/w/api.php', [
                        'action' => 'query', 'list' => 'search', 'format' => 'json', 'srprop' => '',
                        'srsearch' => "haswbstatement:P31={$cls} haswbstatement:P17=Q142",
                        'srlimit' => 500, 'sroffset' => $offset,
                    ]));
                $complete = $complete && $raw !== null;
                $d = $raw ? json_decode($raw, true) : [];
                foreach ($d['query']['search'] ?? [] as $hit) {
                    $ids[$hit['title']] = true;
                }
                $offset = $d['continue']['sroffset'] ?? null;
            } while ($offset !== null && $offset < 10000);
        }
        $ids = array_keys($ids);
        $this->line('  '.count($ids).' éléments Wikidata');

        // 2. Déclarations complètes par lots de 50 (qualificatifs + précisions).
        $records = [];
        $stateLabels = [];
        foreach (array_chunk($ids, 50) as $i => $batch) {
            $key = md5(implode('|', $batch));
            $raw = $this->cachedHttp("wikidata/entities-{$key}.json", function () use ($batch) {
                Sleep::usleep(300000);

                return Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(90)->retry(3, 5000)
                    ->get('https://www.wikidata.org/w/api.php', [
                        'action' => 'wbgetentities', 'format' => 'json', 'ids' => implode('|', $batch),
                        'props' => 'labels|aliases|claims|sitelinks', 'languages' => 'fr|en', 'sitefilter' => 'frwiki',
                    ]);
            });
            $complete = $complete && $raw !== null;
            foreach (json_decode($raw ?? '{}', true)['entities'] ?? [] as $qid => $ent) {
                if ($rec = $this->parseWikidata($qid, $ent)) {
                    $records[] = $rec;
                }
            }
        }
        // Liste ou lot d'entités en échec : rien n'est supprimé (avant : gares « disparues »).
        $this->upsertSource('wikidata', $records, $complete);
    }

    /**
     * @param  array<string, mixed>  $ent
     * @return array<string, mixed>|null
     */
    private function parseWikidata(string $qid, array $ent): ?array
    {
        $claims = $ent['claims'] ?? [];
        $coord = $this->wdValues($claims, 'P625')[0] ?? null;
        if (! $coord || ($coord['value']['globe'] ?? '') !== 'http://www.wikidata.org/entity/Q2') {
            return null;
        }
        $p31 = array_map(fn ($v) => $v['value']['id'] ?? null, $this->wdValues($claims, 'P31'));
        if (! array_intersect($p31, self::WD_CLASSES) || (array_intersect($p31, self::WD_EXCLUDED_ONLY) && ! array_intersect($p31, ['Q55488', 'Q55678', 'Q1793804']))) {
            return null;
        }
        $name = $ent['labels']['fr']['value'] ?? $ent['labels']['en']['value'] ?? null;
        if (! $name) {
            return null;
        }

        // Événements datés, qualificatif P518 (« s'applique à ») conservé :
        // permet de distinguer fermeture aux voyageurs / au fret.
        $events = [];
        $add = function (string $prop, string $type) use ($claims, &$events) {
            foreach ($this->wdValues($claims, $prop, true) as $st) {
                $d = $this->wdTime($st['value'] ?? null);
                if (! $d) {
                    continue;
                }
                $parts = array_values(array_filter(array_map(fn ($q) => $q['datavalue']['value']['id'] ?? null, $st['qualifiers']['P518'] ?? [])));
                $t = $type;
                if ($type === 'closure' && array_intersect($parts, ['Q1433891', 'Q2072431'])) {
                    $t = 'closure_passenger';
                } elseif ($type === 'closure' && in_array('Q2234623', $parts, true)) {
                    $t = 'closure_freight';
                } elseif ($type === 'opening' && in_array('Q16571590', $parts, true)) {
                    $t = 'reopening';
                }
                $events[] = ['type' => $t, 'date' => $d[0], 'precision' => $d[1], 'prop' => $prop, 'applies_to' => $parts];
            }
        };
        $add('P1619', 'opening');
        $add('P3999', 'closure');
        $add('P576', 'demolition');
        if (! array_filter($events, fn ($e) => $e['type'] === 'opening')) {
            $add('P571', 'opening');   // « date de fondation » à défaut d'ouverture officielle
        }
        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        $openings = array_values(array_filter($events, fn ($e) => in_array($e['type'], ['opening', 'reopening'], true)));
        $closures = array_values(array_filter($events, fn ($e) => in_array($e['type'], ['closure', 'closure_passenger', 'demolition'], true)));
        $state = $this->wdValues($claims, 'P5817')[0]['value']['id'] ?? null;
        $lastClose = end($closures) ?: null;
        $lastOpen = end($openings) ?: null;
        $isClosed = $lastClose && (! $lastOpen || $lastClose['date'] >= $lastOpen['date']);
        // P5817 « état d'utilisation » → cycle de vie.
        $lifecycle = [
            'Q55654238' => 'active', 'Q109551035' => 'active', 'Q11639308' => 'disused', 'Q55570340' => 'disused',
            'Q104664889' => 'disused', 'Q56651571' => 'disused', 'Q110435753' => 'disused', 'Q56556915' => 'razed',
            'Q63065035' => 'abandoned', 'Q811683' => 'planned', 'Q12377751' => 'planned', 'Q30108381' => 'planned',
        ][$state] ?? (in_array('Q4663385', $p31, true) || in_array('Q106772341', $p31, true) || $isClosed ? 'disused' : 'unknown');

        $uic = $this->wdValues($claims, 'P722')[0]['value'] ?? null;

        return [
            'source_ref' => $qid,
            'name' => $name,
            'kind' => in_array('Q55678', $p31, true) ? 'halte' : 'gare',
            'lifecycle' => $lifecycle,
            'uic_code' => $uic ? substr(preg_replace('/\D/', '', (string) $uic), 0, 10) : null,
            'wikidata_id' => $qid,
            'opened_on' => $openings[0]['date'] ?? null,
            'opened_precision' => $openings[0]['precision'] ?? null,
            'closed_on' => $isClosed ? $lastClose['date'] : null,
            'closed_precision' => $isClosed ? $lastClose['precision'] : null,
            'closed_scope' => $isClosed ? ($lastClose['type'] === 'closure_passenger' ? 'passenger' : 'all') : null,
            'events' => $events ?: null,
            'properties' => [
                'p31' => $p31,
                'state_of_use' => $state,
                'lines' => array_values(array_filter(array_map(fn ($v) => $v['value']['id'] ?? null, $this->wdValues($claims, 'P81')))),
                'aliases' => array_map(fn ($a) => $a['value'], $ent['aliases']['fr'] ?? []),
                'frwiki' => $ent['sitelinks']['frwiki']['title'] ?? null,
            ],
            'lon' => (float) $coord['value']['longitude'],
            'lat' => (float) $coord['value']['latitude'],
        ];
    }

    /**
     * Valeurs d'une propriété (rang « deprecated » ignoré, « preferred » d'abord).
     *
     * @return array<int, array<string, mixed>>
     */
    private function wdValues(array $claims, string $prop, bool $withQualifiers = false): array
    {
        $out = [];
        foreach ($claims[$prop] ?? [] as $c) {
            if (($c['rank'] ?? '') === 'deprecated' || ($c['mainsnak']['snaktype'] ?? '') !== 'value') {
                continue;
            }
            $row = ['value' => $c['mainsnak']['datavalue']['value'] ?? null, 'rank' => $c['rank']];
            if ($withQualifiers) {
                $row['qualifiers'] = $c['qualifiers'] ?? [];
            }
            $out[] = $row;
        }
        usort($out, fn ($a, $b) => ($b['rank'] === 'preferred') <=> ($a['rank'] === 'preferred'));

        return $out;
    }

    /**
     * @return array{0: string, 1: string}|null [Y-m-d, precision]
     */
    private function wdTime(?array $v): ?array
    {
        if (! $v || ! isset($v['time']) || ($v['precision'] ?? 0) < 9) {
            return null;
        }
        if (! preg_match('/^\+(\d{4})-(\d{2})-(\d{2})/', $v['time'], $m)) {
            return null;
        }
        $prec = match (true) {
            $v['precision'] >= 11 => 'day',
            $v['precision'] === 10 => 'month',
            default => 'year',
        };
        $mo = $prec === 'year' || $m[2] === '00' ? '01' : $m[2];
        $d = $prec !== 'day' || $m[3] === '00' ? '01' : $m[3];

        return ["{$m[1]}-{$mo}-{$d}", $prec];
    }

    // ── Gares par PK (listes historiques par ligne) ──────────────────────────

    /** Source des gares tirées des schémas de ligne Wikipédia. */
    public const WIKIPEDIA_SCHEMA = 'wikipedia_schema';

    /**
     * Gares PAR LIGNE des listes historiques, posées sur le tracé de leur ligne
     * par SectionResolver::placeStations() (même calage des PK que les portions
     * des événements) :
     *   - `source_stations` : lignes-oubliees (fiche, branche) ;
     *   - `wikipedia_line_stations` : schéma de l'article de la ligne (gares à PK) ;
     *   - schémas des articles en cache (articles de réseaux secondaires
     *     storage/app/sources/secondary-networks/pages.json, articles de ligne
     *     storage/app/sources/stations/wikipedia) : une série de PK cohérente
     *     (RouteDiagramStations) est rattachée à une ligne quand elle nomme ses
     *     DEUX terminus (nom de la ligne), ou quand l'article est celui de la ligne.
     *
     * Position : gare connue homonyme (jamais de doublon), sinon PK calé, sinon
     * commune homonyme traversée (precision_m, placement et note dans
     * properties) ; une gare impossible à placer n'est pas importée (comptée).
     */
    private function importPk(): void
    {
        $byLine = $this->pkInputs();
        $only = array_filter((array) $this->option('line'));
        if ($only) {
            $ids = DB::table('lines')->where(fn ($w) => $w->whereIn('code', $only)->orWhereIn('id', array_map('intval', array_filter($only, 'ctype_digit'))))->pluck('id')->all();
            $byLine = array_intersect_key($byLine, array_flip($ids));
        }
        $resolver = app(SectionResolver::class);
        $resolver->prepare();
        $records = [];
        $stats = ['entrées' => 0, 'placées' => 0];
        $methods = [];
        $residuals = [];
        $loo = [];
        $bar = $this->output->createProgressBar(count($byLine));
        foreach ($byLine as $lineId => $inputs) {
            $bar->advance();
            $stats['entrées'] += count($inputs);
            $res = $resolver->placeStations((int) $lineId, array_map(fn ($in) => array_intersect_key($in, array_flip(['key', 'name', 'pk', 'group'])), $inputs),
                (bool) $this->option('validate'));
            array_push($residuals, ...$res['residuals']);
            array_push($loo, ...$res['loo']);
            foreach ($inputs as $in) {
                $p = $res['placed'][$in['key']] ?? null;
                if ($this->output->isVerbose()) {
                    $this->line(sprintf('  [%s] %s · %s · PK %s · %s', $in['line_code'] ?? "#{$lineId}", $in['name'], $in['group'],
                        $in['pk'] ?? '-', $p ? sprintf('%s (%s m) %.5f %.5f · %s', $p['method'], $p['precision_m'] ?? '=', $p['lon'], $p['lat'], $p['note'])
                            : 'non placée ('.($res['groups'][$in['group']] ?? '?').')'));
                }
                if (! $p) {
                    continue;
                }
                $stats['placées']++;
                $methods[$in['source'].' / '.$p['method']] = ($methods[$in['source'].' / '.$p['method']] ?? 0) + 1;
                $records[$in['source']][$in['source_ref']] = [
                    'source_ref' => $in['source_ref'],
                    'name' => $in['name'],
                    'kind' => 'gare',
                    'lifecycle' => 'unknown',
                    'properties' => $in['properties'] + [
                        'line_id' => (int) $lineId,
                        'line_code' => $in['line_code'],
                        // PK sur la ligne : celui de la source (lignes-oubliees), ou recalé dans le repère officiel
                        // de la ligne (schéma Wikipédia), sinon inconnu (projeté par vff:attach-stations).
                        'pk_m' => $in['source'] === self::WIKIPEDIA_SCHEMA ? $p['pk_m'] : $in['pk'],
                        'pk_source_m' => $in['pk'],
                        'group' => $in['group'],
                        'geocoding' => $p['method'],
                        'precision_m' => $p['precision_m'],
                        'placement' => $p['note'],
                        'calibration' => $res['groups'][$in['group']] ?? null,
                    ],
                    'lon' => $p['lon'],
                    'lat' => $p['lat'],
                ];
            }
        }
        $bar->finish();
        $this->newLine();
        sort($residuals);
        $q = fn (float $f) => $residuals ? $residuals[min(count($residuals) - 1, (int) floor($f * count($residuals)))] : null;
        $this->line(sprintf('  %d / %d gares placées (%s)', $stats['placées'], $stats['entrées'],
            implode(', ', array_map(fn ($k, $v) => "{$k} : {$v}", array_keys($methods), $methods))));
        $this->line(sprintf('  Contrôle (gare connue homonyme et PK calé) : %d gares, écart médian %s m, 90 %% < %s m',
            count($residuals), $q(0.5) ?? '-', $q(0.9) ?? '-'));
        foreach ([150 => 'sur des gares', 500 => 'sur des communes'] as $base => $label) {
            $e = array_values(array_map(fn ($x) => $x[0], array_filter($loo, fn ($x) => $x[1] === $base)));
            sort($e);
            if ($e) {
                $within = count(array_filter($loo, fn ($x) => $x[1] === $base && $x[0] <= $x[2]));
                $this->line(sprintf('  Validation croisée, calage propre au schéma %s : %d gares, écart médian %d m, 75 %% < %d m, 90 %% < %d m ; %d %% dans la précision annoncée',
                    $label, count($e), $e[intdiv(count($e), 2)], $e[(int) floor(0.75 * count($e))], $e[(int) floor(0.9 * count($e))], round(100 * $within / count($e))));
                if ($this->output->isVeryVerbose()) {
                    foreach (array_filter($loo, fn ($x) => $x[1] === $base && $x[0] > $x[2]) as $x) {
                        $this->line("    hors précision : {$x[3]} écart {$x[0]} m, annoncé {$x[2]} m");
                    }
                }
            }
        }
        if ($this->option('dry-run')) {
            $this->warn('  --dry-run : rien n\'est écrit');

            return;
        }
        foreach (array_unique(array_merge(array_keys($records), [self::WIKIPEDIA_SCHEMA, ...DB::table('source_stations')->distinct()->pluck('source')->all()])) as $source) {
            $this->upsertSource($source, array_values($records[$source] ?? []), ! $only);
        }
    }

    /**
     * Gares nommées à placer, par ligne.
     *
     * @return array<int, list<array{key: string, name: string, pk: ?int, group: string, source: string, source_ref: string, line_code: ?string, properties: array<string, mixed>}>>
     */
    private function pkInputs(): array
    {
        $codes = DB::table('lines')->pluck('code', 'id')->all();
        $out = [];
        // 1. lignes-oubliees (et autres listes de source_stations).
        if (Schema::hasTable('source_stations')) {
            foreach (DB::select('SELECT id, source, source_ref, name, pk_m, line_id, properties FROM source_stations WHERE line_id IS NOT NULL ORDER BY id') as $r) {
                $props = json_decode($r->properties ?? '{}', true) ?: [];
                $out[(int) $r->line_id][] = ['key' => 'ss:'.$r->id, 'name' => $r->name, 'pk' => $r->pk_m !== null ? (int) $r->pk_m : null,
                    'group' => $r->source.':'.($props['page'] ?? '').':'.($props['branch'] ?? ''), 'source' => $r->source,
                    'source_ref' => mb_substr($r->source_ref, 0, 64), 'line_code' => $codes[$r->line_id] ?? null,
                    'properties' => $props + ['source_station_id' => (int) $r->id]];
            }
        }
        // 2. Schéma de l'article de chaque ligne (gares à PK, vff:import-wikipedia-chronology).
        $seen = []; // ligne → titres déjà lus
        if (Schema::hasTable('wikipedia_line_stations')) {
            foreach (DB::select('SELECT id, line_id, name, pk_m, icon, source_url FROM wikipedia_line_stations ORDER BY line_id, pk_m, id') as $r) {
                $title = self::titleFromUrl($r->source_url);
                $seen[(int) $r->line_id][$title] = true;
                $out[(int) $r->line_id][] = $this->schemaInput((int) $r->line_id, $codes[$r->line_id] ?? null, $title, $r->name, (int) $r->pk_m,
                    'wls:'.$r->source_url, ['page' => $title, 'source_url' => $r->source_url, 'icon' => $r->icon]);
            }
        }
        // 3. Schémas des articles en cache (réseaux secondaires, articles de ligne).
        $parser = app(WikipediaLineParser::class);
        $diagrams = new RouteDiagramStations($parser);
        $ends = [];
        $byTitle = [];
        foreach (DB::table('lines')->get(['id', 'name', 'wikipedia_title', 'network']) as $l) {
            if ($l->wikipedia_title) {
                $byTitle[$l->wikipedia_title][] = (int) $l->id;
            }
            $e = array_map(fn ($x) => $parser->norm($x), $parser->lineEnds(preg_replace('/^(Ligne|Raccordement) militaire /u', '$1 ', (string) $l->name)));
            if (count($e) === 2 && $e[0] !== '' && $e[1] !== '' && $e[0] !== $e[1] && $l->network !== 'militaire') {
                $ends[(int) $l->id] = $e;
            }
        }
        $matches = fn (string $a, string $b) => $a === $b || str_starts_with($a, $b.' ') || str_starts_with($b, $a.' ');
        foreach ($this->cachedArticles() as $title => [$content, $revid]) {
            foreach ($diagrams->parse($content) as $d) {
                if (count($d['stations']) < 2) {
                    continue;
                }
                $names = array_map(fn ($s) => $parser->norm($s['name']), $d['stations']);
                // Ligne → [première, dernière] gare du schéma qui la concerne : tout le schéma pour
                // l'article de la ligne, sinon les gares entre ses deux terminus (schéma de réseau :
                // les autres lignes dessinées dans le même schéma ne sont pas les siennes).
                $lines = [];
                foreach ($byTitle[$title] ?? [] as $lid) {
                    // Article de plusieurs lignes (infobox « numéro » par code) : les gares de CETTE ligne.
                    $own = array_column(RouteDiagramStations::forCode($d['stations'], $content, $codes[$lid] ?? null, $parser), 'row');
                    $rows = array_column($d['stations'], 'row');
                    $lines[$lid] = [(int) array_search($own[0], $rows, true), (int) array_search(end($own), $rows, true)];
                }
                foreach ($ends as $lid => [$a, $b]) {
                    if (isset($lines[$lid])) {
                        continue;
                    }
                    $ia = array_keys(array_filter($names, fn ($n) => $matches($n, $a)));
                    $ib = array_keys(array_filter($names, fn ($n) => $matches($n, $b)));
                    $best = null;
                    foreach ($ia as $i) {
                        foreach ($ib as $j) {
                            if ($i !== $j && (! $best || abs($i - $j) < $best[1] - $best[0])) {
                                $best = [min($i, $j), max($i, $j)];
                            }
                        }
                    }
                    if ($best) {
                        $lines[$lid] = $best;
                    }
                }
                foreach ($lines as $lid => [$from, $to]) {
                    if (isset($seen[$lid][$title])) {
                        continue; // déjà lu par vff:import-wikipedia-chronology (même article)
                    }
                    $url = WikimediaClient::permalink($title, $revid);
                    foreach (array_slice($d['stations'], $from, $to - $from + 1) as $s) {
                        $out[$lid][] = $this->schemaInput($lid, $codes[$lid] ?? null, $title, $s['name'], $s['pk_m'],
                            "wp:{$title}#{$d['table']}.{$d['run']}", ['page' => $title, 'source_url' => $url, 'icon' => $s['icon']]);
                    }
                }
            }
        }

        // Même gare citée deux fois dans un groupe (schéma recopié) : une seule entrée. Points qui ne sont
        // pas des gares (« Bifurcation », « Bifurcation de Beaune », « Frontière ») : écartés.
        foreach ($out as $lid => $list) {
            $u = [];
            foreach ($list as $in) {
                if (preg_match(RouteDiagramStations::NOT_A_STATION, $in['name'])) {
                    continue;
                }
                $u[$in['source'].'|'.$in['source_ref']] ??= $in;
            }
            $out[$lid] = array_values($u);
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{key: string, name: string, pk: ?int, group: string, source: string, source_ref: string, line_code: ?string, properties: array<string, mixed>}
     */
    private function schemaInput(int $lineId, ?string $code, string $title, string $name, ?int $pk, string $group, array $props): array
    {
        // Précision coupée par le schéma (« Mougins (PN 6 ») : le nom seul.
        $name = trim((string) preg_replace('/\s*\([^)]*$/u', '', $name)) ?: $name;
        $ref = 'L'.$lineId.':'.substr(sha1($title.'|'.StationNames::normalize($name).'|'.($pk ?? '')), 0, 20);

        return ['key' => $group.'|'.$ref, 'name' => $name, 'pk' => $pk, 'group' => $group, 'source' => self::WIKIPEDIA_SCHEMA,
            'source_ref' => $ref, 'line_code' => $code, 'properties' => $props];
    }

    /** Titre d'un permalien Wikipédia (« …index.php?title=Ligne_de_X&oldid=1 » → « Ligne de X »). */
    public static function titleFromUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $t = is_string($q['title'] ?? null) ? $q['title'] : rawurldecode((string) basename((string) parse_url($url, PHP_URL_PATH)));

        return str_replace('_', ' ', $t);
    }

    /**
     * Articles Wikipédia en cache (hors articles de gare) : titre → [wikitexte, revid].
     *
     * @return array<string, array{0: string, 1: int}>
     */
    private function cachedArticles(): array
    {
        $out = [];
        $net = storage_path('app/sources/secondary-networks/pages.json');
        if (is_file($net)) {
            foreach (json_decode((string) file_get_contents($net), true) ?: [] as $title => $p) {
                if (! empty($p['content'])) {
                    $out[(string) ($p['title'] ?? $title)] = [(string) $p['content'], (int) ($p['revid'] ?? 0)];
                }
            }
        }
        foreach (glob($this->cacheDir().'/wikipedia/*.json') ?: [] as $f) {
            $p = json_decode((string) file_get_contents($f), true);
            $title = (string) ($p['title'] ?? '');
            if ($title === '' || empty($p['content']) || preg_match('/^(Gare|Halte|Station) /u', $title)) {
                continue;
            }
            $out[$title] ??= [(string) $p['content'], (int) ($p['revid'] ?? 0)];
        }
        ksort($out);

        return $out;
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /**
     * Dates OSM / OHM : « 1865 », « 1865-06 », « 1865-06-12 », « ~1865 »,
     * EDTF « 1912/1916 » ou « 1912..1916 » (premier terme retenu).
     *
     * @return array{0: string, 1: string}|null
     */
    private function parseDate(?string $raw): ?array
    {
        if (! $raw || ! preg_match('/(1[6-9]\d\d|20\d\d)(?:-(\d{2}))?(?:-(\d{2}))?/', $raw, $m)) {
            return null;
        }
        $y = (int) $m[1];
        if ($y > (int) date('Y') + 15) {
            return null;
        }
        if (! empty($m[3]) && checkdate((int) $m[2], (int) $m[3], $y)) {
            return ["{$m[1]}-{$m[2]}-{$m[3]}", 'day'];
        }
        if (! empty($m[2]) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return ["{$m[1]}-{$m[2]}-01", 'month'];
        }

        return ["{$m[1]}-01-01", 'year'];
    }

    /**
     * Upsert des enregistrements d'une source ; si $complete, supprime les
     * objets de cette source qui n'ont pas été revus.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    private function upsertSource(string $source, array $records, bool $complete): void
    {
        $startedAt = now()->subSecond();
        $cols = ['source', 'source_ref', 'name', 'name_norm', 'kind', 'lifecycle', 'uic_code', 'wikidata_id',
            'opened_on', 'opened_precision', 'closed_on', 'closed_precision', 'closed_scope',
            'passenger', 'freight', 'events', 'properties', 'seen_at', 'created_at', 'updated_at', 'geom'];
        foreach (array_chunk($records, 500) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $r) {
                $values[] = '('.implode(',', array_fill(0, count($cols) - 1, '?')).', ST_SetSRID(ST_MakePoint(?, ?), 4326))';
                array_push($bindings,
                    $source, $r['source_ref'], mb_substr($r['name'], 0, 255), mb_substr((string) StationNames::normalize($r['name']), 0, 255) ?: null,
                    $r['kind'] ?? null, $r['lifecycle'] ?? 'unknown', $r['uic_code'] ?? null, $r['wikidata_id'] ?? null,
                    $r['opened_on'] ?? null, $r['opened_precision'] ?? null, $r['closed_on'] ?? null, $r['closed_precision'] ?? null,
                    $r['closed_scope'] ?? null,
                    isset($r['passenger']) ? ($r['passenger'] ? 'true' : 'false') : null,
                    isset($r['freight']) ? ($r['freight'] ? 'true' : 'false') : null,
                    isset($r['events']) ? json_encode($r['events']) : null,
                    json_encode($r['properties'] ?? null, JSON_UNESCAPED_UNICODE),
                    now(), now(), now(), $r['lon'], $r['lat'],
                );
            }
            $update = implode(', ', array_map(fn ($c) => "{$c} = EXCLUDED.{$c}",
                array_diff($cols, ['source', 'source_ref', 'created_at'])));
            DB::statement('INSERT INTO station_sources ('.implode(',', $cols).') VALUES '.implode(',', $values)
                ." ON CONFLICT (source, source_ref) DO UPDATE SET {$update}", $bindings);
        }
        $gone = 0;
        if ($complete) {
            $gone = DB::table('station_sources')->where('source', $source)->where('seen_at', '<', $startedAt)->delete();
        }
        $this->info(sprintf('  ✓ %s : %d objets enregistrés%s', $source, count($records), $gone ? ", {$gone} disparus de la source supprimés" : ''));
    }

    private function cacheDir(): string
    {
        return storage_path('app/sources/stations');
    }

    /**
     * Lit la réponse en cache ou exécute la requête et la met en cache.
     */
    private function cachedHttp(string $rel, callable $request): ?string
    {
        $path = $this->cacheDir().'/'.$rel;
        if (is_file($path) && filesize($path) > 0) {
            return file_get_contents($path);
        }
        if ($this->fromCache) {
            $this->warn("  absent du cache : {$rel}");

            return null;
        }
        try {
            $res = $request();
        } catch (\Throwable $e) {
            $this->warn('  '.$e->getMessage());

            return null;
        }
        // Overpass en échec après ses reprises : null (avant : appel sur null, la commande plantait).
        $body = match (true) {
            $res === null => null,
            is_string($res) => $res,
            default => $res->successful() ? $res->body() : null,
        };
        if ($body === null || $body === '') {
            return null;
        }
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path.'.part', $body);
        rename($path.'.part', $path);

        return $body;
    }

    /**
     * Requête Overpass (POST) avec backoff exponentiel quand le serveur est
     * saturé (429 / 502 / 503 / 504, « runtime error » dans le corps).
     */
    private function overpass(string $endpoint, string $query): ?string
    {
        $wait = 30;
        for ($attempt = 1; $attempt <= 7; $attempt++) {
            try {
                $res = Http::withHeaders(['User-Agent' => self::USER_AGENT])->asForm()->timeout(700)
                    ->post($endpoint, ['data' => $query]);
                $body = $res->body();
                if ($res->successful() && str_starts_with(ltrim($body), '{') && ! str_contains(substr($body, 0, 2000), 'runtime error')) {
                    return $body;
                }
                $this->warn("  Overpass HTTP {$res->status()} (tentative {$attempt}), nouvel essai dans {$wait} s");
            } catch (\Throwable $e) {
                $this->warn("  Overpass : {$e->getMessage()} (tentative {$attempt})");
            }
            if ($attempt < 7) {
                Sleep::sleep($wait);
            }
            $wait = min($wait * 2, 600);
        }

        return null;
    }
}
