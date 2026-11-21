<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Models\Media;
use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Photos, cartes postales, plans et archives de chaque ligne depuis
 * Wikimedia Commons - SANS copier les fichiers.
 *
 * Pourquoi : l'encyclopédie doit montrer « à quoi ressemblait la ligne »
 * (gares disparues, cartes postales anciennes, viaducs, plans), mais VFF ne
 * veut ni héberger ni redistribuer des œuvres de tiers. Commons fournit, pour
 * chaque fichier, une licence libre vérifiée par sa communauté et des
 * miniatures que Wikimedia autorise à afficher en lien direct (hotlink). On
 * stocke donc uniquement les métadonnées, les URL de miniatures et surtout
 * les DROITS fichier par fichier (licence d'origine, auteur, lien), dans
 * `media` (provider = commons).
 *
 * Sources de rattachement, de la plus sûre à la moins sûre :
 *   1. Wikidata P18 (image de référence de l'élément « ligne ») → à la une ;
 *   2. catégorie Commons de la ligne (Wikidata P373 ou lien interwiki
 *      commonswiki, élément trouvé par son code RFN P1671 ou par la colonne
 *      wikidata de `lines` si elle existe ; à défaut « Category:<nom> ») :
 *      niveau 0 = tout le contenu, niveaux 1-2 = sous-catégories filtrées
 *      (gares, ouvrages d'art, plans, cartes postales…) ;
 *   3. recherche géographique (`geosearch`) le long du tracé (géométries
 *      SNCF, segments, traces sources rattachées) : on ne garde que les
 *      fichiers à ≤ N m du tracé ET dont le titre/les catégories parlent de
 *      chemin de fer (une photo de mairie à 80 m de la voie n'est pas une
 *      archive de la ligne).
 *
 * Idempotente : upsert sur (provider, external_id, mediable). `--prune`
 * retire les médias Commons de la ligne qui ne sont plus retenus. Jamais de
 * suppression d'autres données.
 *
 * Exemples :
 *   vff:import-commons-media --line=956000
 *   vff:import-commons-media --line=rfn-485000 --depth=2
 *   vff:import-commons-media --limit=50 --only-missing --no-geosearch
 */
class ImportCommonsMedia extends Command
{
    protected $signature = 'vff:import-commons-media
        {--line=* : Slug ou code RFN de la/des ligne(s) (défaut : toutes)}
        {--limit= : Nombre maximal de lignes traitées}
        {--depth=2 : Profondeur de parcours des sous-catégories (0-2)}
        {--per-category=200 : Fichiers max retenus par catégorie}
        {--max-per-line=1500 : Fichiers max par ligne}
        {--no-geosearch : Désactive la recherche géographique le long du tracé}
        {--geo-step=1000 : Pas d\'échantillonnage du tracé (m)}
        {--geo-radius=750 : Rayon de chaque recherche géographique (m, ≤ 10000)}
        {--geo-distance=150 : Distance max d\'un fichier géolocalisé au tracé (m)}
        {--geo-max=400 : Fichiers géolocalisés max par ligne (les plus proches du tracé)}
        {--only-missing : Ignore les lignes ayant déjà des médias Commons}
        {--prune : Supprime les médias Commons de la ligne qui ne sont plus retenus}
        {--dry-run : N\'écrit rien en base}';

    protected $description = 'Wikimedia Commons → médias (photos, cartes postales, plans) de chaque ligne, métadonnées et droits seulement (hotlink)';

    /** Termes ferroviaires « forts » exigés pour un fichier trouvé par geosearch. */
    private const RAIL_STRONG = '/\b(gares?|haltes?|ferroviaires?|chemins? de fer|voies? ferrées?|railways?|railroads?|rail transport|train stations?|railway stations?|trains?|locomotives?|autorails?|draisines?|SNCF|RFF|PLM|PO-Midi|TER|TGV|RER|Transilien|viaducs?|viaducts?|passages? à niveau|level crossings?|postes? d\'aiguillage|signal box(es)?|maisons? de garde-barrière|garde-barrières?|Bahnhof|Eisenbahn|rolling stock|X ?\d{4}|BB ?\d{4,5}|CC ?\d{4,5}|Z ?\d{4,5}|Y ?\d{4,5}|A1A)\b/iu';

    /** Sous-catégories acceptées : infrastructure, archives, documents. */
    private const SUBCAT_INFRA = '/\b(gares?|haltes?|stations?|bahnhofs?|viaducs?|viaducts?|tunnels?|ponts?|bridges?|dépôts?|depots?|passages? à niveau|level crossings?|maisons? de garde|garde-barrières?|postes? d\'aiguillage|signal box(es)?|quais?|platforms?|bâtiments? voyageurs|tranchées?|raccordements?|embranchements?|triages?|marshalling|halles? (à|aux) marchandises|freight|maps?|plans?|postcards?|cartes postales|lignes?|railways?|railway lines?|chemins? de fer|ferroviaires?|voies?|tracks?|construction of)\b/iu';

    /** Matériel roulant : accepté seulement si la catégorie nomme aussi la ligne. */
    private const SUBCAT_ROLLING = '/\b(trains?|locomotives?|autorails?|rames?|draisines?|X ?\d{3,4}|BB ?\d{4,5}|CC ?\d{4,5}|Z ?\d{4,5}|Y ?\d{4,5}|A1A|TER|TGV|RER|SNCF|steam|vapeur)\b/iu';

    /** Catégories de maintenance / d'utilisateurs, jamais parcourues. */
    private const SUBCAT_SKIP = '/^Category:(Videos?|Audio|Quality images|Featured pictures|Valued images|Photographs by|Photos by|Files by|Images by|Taken with|Uploaded|Media needing|Panoramics|Self-published)/i';

    /** Logos, icônes, pictogrammes : hors sujet pour une galerie d'archives. */
    private const NOT_ARCHIVE = '/\b(logos?|icons?|icônes?|pictogram(me)?s?|pictos?|symbols?|wappen|blasons?|coats? of arms|signatures?|rdt icons|BSicon)\b/iu';

    /** Géosearch : fichiers de métro/tram sans mention ferroviaire « lourde ». */
    private const URBAN_ONLY = '/\b(m[ée]tro|tramway|\btram\b|funicul|téléphérique)/iu';

    /** Géosearch : bus, signalétique urbaine… (le nom d'arrêt « Gare » ne suffit pas). */
    private const GEO_EXCLUDE = '/\b(arrêts? (de )?bus|bus stops?|autobus|bus|buses|gare routière|Citaro|Urbanway|Heuliez|Irisbus|Iveco|Mercedes-Benz|Agora|trolleybus|taxis?|vélib|plaques? de rue|street name signs?|totems?|ascenseurs?|elevators?)\b/iu';

    /** Sous-catégories hors sujet malgré un mot ferroviaire (jardin sur l'ancienne gare, boutiques…). */
    private const SUBCAT_EXCLUDE = '/\b(jardins?|gardens?|parks?|parcs?|squares?|shops?|workshops?|boutiques?|restaurants?|cafés?|hôtels?|hotels?|people|personnes|events?|événements|concerts?|street art|graffiti|bus|buses|autobus|tramway|métro|metro)\b/iu';

    private const HEAVY_RAIL = '/\b(gares?|SNCF|RER|Transilien|railway stations?|train stations?|chemins? de fer|ferroviaires?|TER|TGV)\b/iu';

    private const STOPWORDS = ['ligne', 'lignes', 'gare', 'gares', 'chemin', 'chemins', 'fer', 'voie', 'voies', 'railway', 'line', 'saint', 'sainte', 'sous', 'sur', 'les', 'des', 'aux', 'grande', 'ceinture', 'petite', 'nord', 'sud', 'est', 'ouest', 'bois', 'pont', 'ponts', 'bourg', 'ville', 'paris'];

    private CommonsClient $client;

    /** @var array<int, array<string, mixed>> cache pageid → métadonnées normalisées (partagé entre lignes) */
    private array $infoCache = [];

    /** @var array<int, array{qids: list<string>, cats: list<string>, images: list<string>}> */
    private array $wikidata = [];

    /** @var array<string, int> */
    private array $totals = ['lines' => 0, 'lines_with_media' => 0, 'upserted' => 0, 'pruned' => 0, 'errors' => 0];

    public function handle(): int
    {
        $this->client = new CommonsClient;
        $lines = $this->selectLines();
        if ($lines->isEmpty()) {
            $this->warn('Aucune ligne sélectionnée.');

            return self::SUCCESS;
        }
        $this->info($lines->count().' ligne(s) à traiter.');

        $this->info('→ Wikidata : éléments « ligne » par code RFN (P1671), catégories Commons (P373), images (P18)…');
        try {
            $this->wikidata = $this->loadWikidata($lines);
            $this->line('  '.count($this->wikidata).' ligne(s) reliées à Wikidata');
        } catch (Throwable $e) {
            // Wikidata indisponible : on continue avec les catégories trouvées par le nom
            // de la ligne (avant : toute la commande s'arrêtait sur l'exception).
            $this->wikidata = [];
            $this->warn('  Wikidata indisponible ('.mb_strimwidth($e->getMessage(), 0, 120, '…').') : catégories par le nom seulement.');
        }

        $fallbacks = $this->fallbackCategories($lines);

        foreach ($lines as $line) {
            $this->totals['lines']++;
            try {
                $this->processLine($line, $fallbacks[$line->id] ?? null);
            } catch (Throwable $e) {
                $this->totals['errors']++;
                $this->error("  ✗ {$line->code} {$line->name} : ".$e->getMessage());
            }
        }

        $this->summary();

        return self::SUCCESS;
    }

    /** @return Collection<int, Line> */
    private function selectLines()
    {
        $q = Line::query()->orderBy('code')->orderBy('id');
        $only = array_filter((array) $this->option('line'));
        if ($only !== []) {
            $q->where(fn ($w) => $w->whereIn('slug', $only)->orWhereIn('code', $only));
        }
        if ($this->option('only-missing')) {
            $q->whereNotExists(fn ($s) => $s->from('media')
                ->whereColumn('media.mediable_id', 'lines.id')
                ->where('media.mediable_type', Line::class)
                ->where('media.provider', Media::PROVIDER_COMMONS));
        }
        if ($this->option('limit')) {
            $q->limit((int) $this->option('limit'));
        }

        return $q->get();
    }

    private function processLine(Line $line, ?string $fallbackCategory): void
    {
        $wd = $this->wikidata[$line->id] ?? ['qids' => [], 'cats' => [], 'images' => []];
        $cats = $wd['cats'];
        if ($cats === [] && $fallbackCategory) {
            $cats = [$fallbackCategory];
        }
        $this->line("<info>■ {$line->code} - {$line->name}</info>".($wd['qids'] ? ' ['.implode(',', $wd['qids']).']' : ''));

        /** @var array<int, array<string, mixed>> $cands pageid → origine */
        $cands = [];

        // 1. Image de référence Wikidata (P18).
        if ($wd['images'] !== []) {
            foreach ($this->client->resolveFileTitles($wd['images']) as $title => $pageId) {
                $cands[$pageId] = ['via' => 'wikidata_p18', 'rank' => 0, 'featured' => true, 'qid' => $wd['qids'][0] ?? null];
            }
        }

        // 2. Catégories Commons (BFS niveaux 0..depth).
        $tokens = $this->lineTokens($line, $cats);
        $catStats = $this->walkCategories($cats, $tokens, $cands);

        // 3. Recherche géographique le long du tracé.
        $geoStats = ['points' => 0, 'found' => 0, 'near' => 0, 'railway' => 0];
        if (! $this->option('no-geosearch')) {
            $geoStats = $this->geosearch($line, $cands);
        }

        // Plafond par ligne : priorité P18 > catégorie (niveau croissant) > geosearch (distance).
        $max = (int) $this->option('max-per-line');
        if (count($cands) > $max) {
            uasort($cands, fn ($a, $b) => [$a['rank'], $a['distance_m'] ?? 0] <=> [$b['rank'], $b['distance_m'] ?? 0]);
            $cands = array_slice($cands, 0, $max, true);
        }

        $infos = $this->fetchInfos(array_keys($cands));

        $rows = [];
        $rejected = ['mime' => 0, 'logo' => 0, 'off_topic' => 0, 'missing' => 0];
        foreach ($cands as $pageId => $origin) {
            $meta = $infos[$pageId] ?? null;
            if ($meta === null) {
                $rejected['missing']++;

                continue;
            }
            $reason = $this->rejectReason($meta, $origin);
            if ($reason !== null) {
                $rejected[$reason]++;

                continue;
            }
            $rows[] = $this->row($line, $pageId, $meta, $origin);
        }

        $byVia = array_count_values(array_map(fn ($r) => json_decode($r['provider_meta'], true)['via'] ?? '?', $rows));
        $this->line(sprintf(
            '  catégories : %d parcourue(s) (%d écartée(s)) · géo : %d point(s), %d fichier(s) à ≤ %d m dont %d ferroviaire(s) · candidats %d → retenus %d [%s] (rejets : %s)',
            $catStats['visited'], $catStats['skipped'], $geoStats['points'], $geoStats['near'], (int) $this->option('geo-distance'),
            $geoStats['railway'], count($cands), count($rows), http_build_query($byVia, '', ', '), http_build_query($rejected, '', ', '),
        ));

        if ($this->option('dry-run')) {
            foreach (array_slice($rows, 0, 8) as $r) {
                $this->line("    · [{$r['type']}] ".($r['depicted_year'] ?? '????')." {$r['title']} - {$r['attribution']}");
            }

            return;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('media')->upsert(
                $chunk,
                ['provider', 'external_id', 'mediable_type', 'mediable_id'],
                ['type', 'title', 'caption', 'author', 'attribution', 'license', 'license_url', 'rights_status',
                    'commercial_ok', 'share_alike', 'mime_type', 'width', 'height', 'depicted_year', 'captured_at',
                    'is_featured', 'source_url', 'thumb_url', 'display_url', 'location', 'provider_meta', 'updated_at'],
            );
        }
        $this->totals['upserted'] += count($rows);
        if ($rows !== []) {
            $this->totals['lines_with_media']++;
        }

        if ($this->option('prune')) {
            $keep = array_map(fn ($r) => $r['external_id'], $rows);
            $q = DB::table('media')
                ->where('provider', Media::PROVIDER_COMMONS)
                ->where('mediable_type', Line::class)
                ->where('mediable_id', $line->id);
            if ($keep !== []) {
                $q->whereNotIn('external_id', $keep);
            }
            // On ne retire que ce que ce passage a pu réévaluer.
            if ($this->option('no-geosearch')) {
                $q->whereRaw("COALESCE(provider_meta->>'via', '') <> 'geosearch'");
            }
            $this->totals['pruned'] += $q->delete();
        }
    }

    /**
     * Parcours en largeur des catégories : niveau 0 = tout, niveaux ≥ 1 =
     * sous-catégories pertinentes uniquement.
     *
     * @param  list<string>  $roots
     * @param  list<string>  $tokens
     * @param  array<int, array<string, mixed>>  $cands
     * @return array{visited: int, skipped: int}
     */
    private function walkCategories(array $roots, array $tokens, array &$cands): array
    {
        $depthMax = max(0, min(2, (int) $this->option('depth')));
        $perCat = (int) $this->option('per-category');
        $visited = [];
        $skipped = 0;
        $queue = array_map(fn ($c) => [$this->catTitle($c), 0], $roots);

        while ($queue !== []) {
            [$cat, $depth] = array_shift($queue);
            if (isset($visited[$cat])) {
                continue;
            }
            $visited[$cat] = true;

            $members = $this->client->categoryMembers($cat, 'file|subcat', $perCat + 500);
            $files = array_values(array_filter($members, fn ($m) => $m['type'] === 'file'));
            foreach (array_slice($files, 0, $perCat) as $f) {
                $rank = 1 + $depth;
                if (! isset($cands[$f['pageid']]) || $cands[$f['pageid']]['rank'] > $rank) {
                    $cands[$f['pageid']] = [
                        'via' => 'category', 'rank' => $rank, 'category' => substr($cat, 9), 'depth' => $depth,
                        'featured' => $cands[$f['pageid']]['featured'] ?? false,
                    ];
                }
            }

            if ($depth >= $depthMax) {
                continue;
            }
            $subcats = array_values(array_filter($members, fn ($m) => $m['type'] === 'subcat'));
            $candidates = [];
            foreach ($subcats as $s) {
                if (isset($visited[$s['title']])) {
                    continue;
                }
                if ($this->subcatRelevant($s['title'], $tokens)) {
                    $candidates[] = $s['title'];
                } else {
                    $skipped++;
                }
            }
            if ($candidates === []) {
                continue;
            }
            // Catégories trop vastes (> 3000 fichiers) = génériques, pas propres à la ligne.
            $info = $this->client->categoryInfo($candidates);
            foreach ($candidates as $c) {
                if (($info[$c]['files'] ?? 0) > 3000) {
                    $skipped++;

                    continue;
                }
                $queue[] = [$c, $depth + 1];
            }
        }

        return ['visited' => count($visited), 'skipped' => $skipped];
    }

    /** @param  list<string>  $tokens */
    private function subcatRelevant(string $title, array $tokens): bool
    {
        if (preg_match(self::SUBCAT_SKIP, $title) || preg_match(self::SUBCAT_EXCLUDE, substr($title, 9))) {
            return false;
        }
        $name = substr($title, 9);
        if (preg_match(self::SUBCAT_INFRA, $name)) {
            return true;
        }
        $mentionsLine = false;
        foreach ($tokens as $t) {
            if (mb_stripos($name, $t) !== false) {
                $mentionsLine = true;
                break;
            }
        }

        return $mentionsLine && (bool) preg_match(self::SUBCAT_ROLLING, $name);
    }

    /**
     * Recherche géographique Commons le long du tracé, puis filtrage par
     * distance réelle au tracé (PostGIS, géographie).
     *
     * @param  array<int, array<string, mixed>>  $cands
     * @return array{points: int, found: int, near: int}
     */
    private function geosearch(Line $line, array &$cands): array
    {
        [$geomSql, $bindings] = $this->geometrySql($line);
        $step = max(200, (int) $this->option('geo-step'));
        $radius = max(50, min(10000, (int) $this->option('geo-radius')));
        $maxDist = max(10, (int) $this->option('geo-distance'));

        $points = DB::select(
            "WITH g AS ({$geomSql}),
                  parts AS (SELECT (ST_Dump(geom)).geom AS part FROM g WHERE geom IS NOT NULL)
             SELECT ST_X(p) AS lon, ST_Y(p) AS lat FROM (
                 SELECT (ST_DumpPoints(ST_LineInterpolatePoints(part,
                         LEAST(1.0, ? / GREATEST(ST_Length(part::geography), ?)), true))).geom AS p FROM parts
                 UNION ALL SELECT ST_StartPoint(part) FROM parts
             ) x",
            [...$bindings, $step, $step],
        );

        // Dédoublonnage des points proches (tracés sources superposés).
        $seen = [];
        $samples = [];
        $cell = $step / 2 / 111_000;
        foreach ($points as $p) {
            $key = round($p->lat / $cell).':'.round($p->lon / ($cell / max(0.2, cos(deg2rad($p->lat)))));
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $samples[] = $p;
            }
        }

        $found = [];
        $failed = 0;
        foreach ($samples as $p) {
            try {
                $hits = $this->client->geosearch((float) $p->lat, (float) $p->lon, $radius);
            } catch (Throwable $e) {
                // Un point en échec (recherche Commons surchargée) n'invalide pas la ligne.
                $failed++;

                continue;
            }
            foreach ($hits as $g) {
                $found[$g['pageid']] ??= $g;
            }
        }
        if ($failed > 0) {
            $this->warn("  géo : {$failed} point(s) sur ".count($samples).' en échec (recherche Commons indisponible)');
        }
        if ($found === []) {
            return ['points' => count($samples), 'found' => 0, 'near' => 0, 'railway' => 0];
        }

        $ids = array_keys($found);
        $dist = DB::select(
            "WITH g AS ({$geomSql})
             SELECT c.id, ST_Distance(ST_SetSRID(ST_MakePoint(c.lon, c.lat), 4326)::geography, g.geom::geography) AS d
             FROM unnest(?::bigint[], ?::float8[], ?::float8[]) AS c(id, lon, lat), g",
            [...$bindings, '{'.implode(',', $ids).'}',
                '{'.implode(',', array_map(fn ($i) => $found[$i]['lon'], $ids)).'}',
                '{'.implode(',', array_map(fn ($i) => $found[$i]['lat'], $ids)).'}'],
        );

        /** @var array<int, int> $near pageid → distance au tracé (m) */
        $near = [];
        foreach ($dist as $r) {
            if ($r->d === null || (float) $r->d > $maxDist) {
                continue;
            }
            $id = (int) $r->id;
            if (isset($cands[$id])) {
                $cands[$id]['distance_m'] = (int) round((float) $r->d);

                continue;
            }
            $near[$id] = (int) round((float) $r->d);
        }

        // Pré-filtre peu coûteux : titre ferroviaire, sinon catégories visibles
        // (requête légère) - l'extmetadata n'est demandée que pour les retenus.
        $railway = [];
        $unknown = [];
        foreach ($near as $id => $d) {
            $name = CommonsMetadata::titleFromFile($found[$id]['title']);
            if (preg_match(self::GEO_EXCLUDE, $name)) {
                continue;
            }
            if (preg_match(self::RAIL_STRONG, $name)) {
                $railway[$id] = $d;
            } else {
                $unknown[] = $id;
            }
        }
        if ($unknown !== []) {
            foreach ($this->client->categoriesOf($unknown) as $id => $cats) {
                $hay = implode(' | ', $cats);
                if (preg_match(self::RAIL_STRONG, $hay) && ! preg_match(self::GEO_EXCLUDE, $hay)
                    && ! (preg_match(self::URBAN_ONLY, $hay) && ! preg_match(self::HEAVY_RAIL, $hay))) {
                    $railway[$id] = $near[$id];
                }
            }
        }

        asort($railway);
        $railway = array_slice($railway, 0, max(0, (int) $this->option('geo-max')), true);
        foreach ($railway as $id => $d) {
            $cands[$id] = [
                'via' => 'geosearch', 'rank' => 9, 'distance_m' => $d,
                'lat' => $found[$id]['lat'], 'lon' => $found[$id]['lon'], 'featured' => false,
            ];
        }

        return ['points' => count($samples), 'found' => count($found), 'near' => count($near), 'railway' => count($railway)];
    }

    /**
     * Géométrie de la ligne : union de toutes les sources disponibles
     * (tracé SNCF par code, segments VFF, traces sources rattachées…).
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function geometrySql(Line $line): array
    {
        $parts = ['SELECT geom FROM line_segments WHERE line_id = ?'];
        $bindings = [$line->id];
        if ($line->code) {
            $parts[] = 'SELECT geom FROM sncf_line_geometries WHERE code_ligne = ?';
            $bindings[] = $line->code;
        }
        foreach (['source_traces', 'line_geometries', 'line_reference_geometries'] as $table) {
            if (Schema::hasTable($table)) {
                $parts[] = "SELECT ST_Force2D(geom) FROM {$table} WHERE line_id = ?";
                $bindings[] = $line->id;
            }
        }

        $sql = 'SELECT ST_Multi(ST_CollectionExtract(ST_Collect(geom), 2)) AS geom FROM ('
            .implode(' UNION ALL ', array_map(fn ($p) => "({$p})", $parts)).') s';

        return [$sql, $bindings];
    }

    /**
     * @param  list<int>  $pageIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchInfos(array $pageIds): array
    {
        // Cache borné (les fichiers partagés entre lignes voisines sont proches dans l'ordre de traitement).
        if (count($this->infoCache) > 20_000) {
            $this->infoCache = array_slice($this->infoCache, -10_000, null, true);
        }
        $missing = array_values(array_filter($pageIds, fn ($id) => ! array_key_exists($id, $this->infoCache)));
        if ($missing !== []) {
            foreach ($this->client->fileInfo($missing) as $id => $page) {
                $this->infoCache[$id] = CommonsMetadata::normalize($page);
            }
            foreach ($missing as $id) {
                $this->infoCache[$id] ??= null;
            }
        }

        return array_filter(array_intersect_key($this->infoCache, array_flip($pageIds)));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $origin
     */
    private function rejectReason(array $meta, array $origin): ?string
    {
        if (! in_array($meta['mime'], CommonsMetadata::ACCEPTED_MIMES, true)) {
            return 'mime';
        }
        if ($meta['mime'] !== 'image/svg+xml' && ($meta['width'] ?? 0) > 0 && $meta['width'] < 150) {
            return 'logo';
        }
        $fileName = CommonsMetadata::titleFromFile($meta['file_title']);
        $visibleCats = implode(' | ', array_slice($meta['categories'], 0, 40));
        if (preg_match(self::NOT_ARCHIVE, $fileName) || preg_match('/\b(Logos|Icons|Pictograms)\b/u', $visibleCats)) {
            return 'logo';
        }
        if (($origin['via'] ?? null) === 'geosearch') {
            $hay = $fileName.' | '.$visibleCats.' | '.($meta['caption'] ?? '');
            if (! preg_match(self::RAIL_STRONG, $hay)) {
                return 'off_topic';
            }
            if (preg_match(self::GEO_EXCLUDE, $fileName.' | '.$visibleCats)
                || (preg_match(self::URBAN_ONLY, $hay) && ! preg_match(self::HEAVY_RAIL, $hay))) {
                return 'off_topic';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $origin
     * @return array<string, mixed>
     */
    private function row(Line $line, int $pageId, array $meta, array $origin): array
    {
        $lat = $meta['lat'] ?? $origin['lat'] ?? null;
        $lon = $meta['lon'] ?? $origin['lon'] ?? null;
        $location = ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180)
            ? DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $lon, $lat))
            : null;
        $now = now();

        return [
            'mediable_type' => Line::class,
            'mediable_id' => $line->id,
            'provider' => Media::PROVIDER_COMMONS,
            'external_id' => (string) $pageId,
            'type' => $meta['type'],
            'title' => mb_substr((string) $meta['title'], 0, 255),
            'caption' => $meta['caption'],
            'author' => $meta['author'] !== null ? mb_substr($meta['author'], 0, 255) : null,
            'attribution' => $meta['attribution'],
            'license' => $meta['license'] !== null ? mb_substr($meta['license'], 0, 191) : null,
            'license_url' => $meta['license_url'],
            'rights_status' => $meta['rights_status'],
            'commercial_ok' => $meta['commercial_ok'],
            'share_alike' => $meta['share_alike'],
            'mime_type' => $meta['mime'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'depicted_year' => $meta['depicted_year'],
            'captured_at' => $meta['captured_at'],
            'is_featured' => (bool) ($origin['featured'] ?? false),
            'source_url' => $meta['source_url'],
            'thumb_url' => $meta['thumb_url'],
            'display_url' => $meta['display_url'],
            'location' => $location,
            'provider_meta' => json_encode(array_filter([
                'file_title' => $meta['file_title'],
                'via' => $origin['via'],
                'category' => $origin['category'] ?? null,
                'depth' => $origin['depth'] ?? null,
                'distance_m' => $origin['distance_m'] ?? null,
                'qid' => $origin['qid'] ?? null,
                'date_raw' => $meta['date_raw'],
                'date_precision' => $meta['date_precision'],
                'year_to' => $meta['depicted_year_to'] ?? null,
                'credit' => $meta['credit'],
                'attribution_required' => $meta['attribution_required'],
                'categories' => array_slice(array_values(array_filter(
                    $meta['categories'],
                    fn ($c) => ! preg_match('/^(CC-|PD|GFDL|License|Self-published|Files with|Media (contributed|supported|needing)|Uploaded|Pages with|Images with|Photos with|Taken with|Created with)/i', $c),
                )), 0, 25),
            ], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Index Wikidata des lignes : code RFN (P1671) → éléments, catégories
     * Commons (P373 + lien commonswiki) et images (P18). Complété par la
     * colonne wikidata de `lines` si un autre import l'a renseignée.
     *
     * @param  Collection<int, Line>  $lines
     * @return array<int, array{qids: list<string>, cats: list<string>, images: list<string>}>
     */
    private function loadWikidata($lines): array
    {
        $byCode = [];
        $rows = $this->client->sparql(<<<'SPARQL'
            SELECT ?item ?code ?cat ?img ?commons WHERE {
              ?item wdt:P1671 ?code ; wdt:P17 wd:Q142 .
              FILTER(REGEX(?code, "^[0-9]{3} ?[0-9]{3}$"))
              OPTIONAL { ?item wdt:P373 ?cat }
              OPTIONAL { ?item wdt:P18 ?img }
              OPTIONAL { ?commons schema:about ?item ; schema:isPartOf <https://commons.wikimedia.org/> }
            }
            SPARQL);
        foreach ($rows as $r) {
            $code = str_pad(preg_replace('/\s+/', '', $r['code']['value']), 6, '0', STR_PAD_LEFT);
            $this->addWikidata($byCode[$code], $r);
        }

        $out = [];
        $qidColumn = collect(['wikidata_id', 'wikidata', 'wikidata_qid'])->first(fn ($c) => Schema::hasColumn('lines', $c));
        $byQid = [];
        foreach ($lines as $line) {
            if ($line->code && isset($byCode[$line->code])) {
                $out[$line->id] = $byCode[$line->code];
            }
            $qid = $qidColumn ? $line->getAttribute($qidColumn) : null;
            if (is_string($qid) && preg_match('/^Q\d+$/', $qid) && ! in_array($qid, $out[$line->id]['qids'] ?? [], true)) {
                $byQid[$qid][] = $line->id;
            }
        }

        // Éléments connus par la colonne wikidata mais absents de l'index P1671.
        foreach (array_chunk(array_keys($byQid), 50) as $chunk) {
            $data = $this->client->wikidata([
                'action' => 'wbgetentities', 'ids' => implode('|', $chunk),
                'props' => 'claims|sitelinks', 'sitefilter' => 'commonswiki',
            ]);
            foreach ($data['entities'] ?? [] as $qid => $e) {
                $entry = null;
                $cat = $e['claims']['P373'][0]['mainsnak']['datavalue']['value'] ?? null;
                $img = $e['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? null;
                $sl = $e['sitelinks']['commonswiki']['title'] ?? null;
                $this->addWikidata($entry, [
                    'item' => ['value' => "http://www.wikidata.org/entity/{$qid}"],
                    'cat' => $cat ? ['value' => $cat] : null,
                    'img' => $img ? ['value' => 'Special:FilePath/'.rawurlencode($img)] : null,
                    'commons' => $sl ? ['value' => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $sl))] : null,
                ]);
                foreach ($byQid[$qid] ?? [] as $lineId) {
                    $cur = $out[$lineId] ?? ['qids' => [], 'cats' => [], 'images' => []];
                    $out[$lineId] = [
                        'qids' => array_values(array_unique([...$cur['qids'], ...$entry['qids']])),
                        'cats' => array_values(array_unique([...$cur['cats'], ...$entry['cats']])),
                        'images' => array_values(array_unique([...$cur['images'], ...$entry['images']])),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{qids: list<string>, cats: list<string>, images: list<string>}|null  $entry
     *
     * @param-out array{qids: list<string>, cats: list<string>, images: list<string>}  $entry
     *
     * @param  array<string, mixed>  $r
     */
    private function addWikidata(?array &$entry, array $r): void
    {
        $entry ??= ['qids' => [], 'cats' => [], 'images' => []];
        $qid = basename((string) ($r['item']['value'] ?? ''));
        if ($qid && ! in_array($qid, $entry['qids'], true)) {
            $entry['qids'][] = $qid;
        }
        $cat = $r['cat']['value'] ?? null;
        if (! $cat && isset($r['commons']['value']) && str_contains($r['commons']['value'], '/wiki/Category:')) {
            $cat = str_replace('_', ' ', rawurldecode(substr($r['commons']['value'], strpos($r['commons']['value'], '/wiki/Category:') + 15)));
        }
        if ($cat) {
            $cat = $this->catTitle($cat);
            if (! in_array($cat, $entry['cats'], true)) {
                $entry['cats'][] = $cat;
            }
        }
        if (isset($r['img']['value']) && str_contains($r['img']['value'], 'Special:FilePath/')) {
            $file = 'File:'.str_replace('_', ' ', rawurldecode(substr($r['img']['value'], strpos($r['img']['value'], 'Special:FilePath/') + 17)));
            if (! in_array($file, $entry['images'], true)) {
                $entry['images'][] = $file;
            }
        }
    }

    /**
     * Lignes sans élément Wikidata catégorisé : on tente « Category:<nom> »
     * (correspondance exacte uniquement, nom de type « Ligne de X à Y »).
     *
     * @param  Collection<int, Line>  $lines
     * @return array<int, string>
     */
    private function fallbackCategories($lines): array
    {
        $want = [];
        foreach ($lines as $line) {
            if (($this->wikidata[$line->id]['cats'] ?? []) === [] && preg_match('/^Ligne d/u', $line->name)) {
                $want[$line->id] = $this->catTitle($line->name);
            }
        }
        if ($want === []) {
            return [];
        }
        $info = $this->client->categoryInfo(array_values($want));
        $out = [];
        foreach ($want as $id => $cat) {
            if (($info[$cat]['exists'] ?? false) && ($info[$cat]['files'] + $info[$cat]['subcats']) > 0) {
                $out[$id] = $cat;
            }
        }
        $this->line('  '.count($out).' catégorie(s) Commons trouvée(s) par le nom de ligne');

        return $out;
    }

    /**
     * Mots distinctifs de la ligne (noms de lieux) pour reconnaître une
     * sous-catégorie de matériel propre à la ligne.
     *
     * @param  list<string>  $cats
     * @return list<string>
     */
    private function lineTokens(Line $line, array $cats): array
    {
        $text = $line->name.' '.implode(' ', array_map(fn ($c) => substr($c, 9), $cats));
        $words = preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($words as $w) {
            if (mb_strlen($w) >= 4 && ! in_array(mb_strtolower($w), self::STOPWORDS, true)) {
                $tokens[mb_strtolower($w)] = true;
            }
        }

        return array_keys($tokens);
    }

    private function catTitle(string $name): string
    {
        $name = trim(str_replace('_', ' ', $name));
        $name = preg_replace('/^(Category|Catégorie):/i', '', $name) ?? $name;

        return 'Category:'.mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    private function summary(): void
    {
        $this->newLine();
        $this->info(sprintf(
            'Terminé : %d ligne(s) traitée(s), %d avec médias, %d média(s) écrits, %d retiré(s), %d erreur(s), %d requête(s) Wikimedia (%d reprise(s)).',
            $this->totals['lines'], $this->totals['lines_with_media'], $this->totals['upserted'],
            $this->totals['pruned'], $this->totals['errors'], $this->client->requestCount, $this->client->retryCount,
        ));
        if ($this->option('dry-run')) {
            return;
        }
        $rights = DB::table('media')->where('provider', Media::PROVIDER_COMMONS)
            ->selectRaw('rights_status, COUNT(*) AS n')->groupBy('rights_status')->orderByDesc('n')->get();
        $this->table(['Statut de droits (Commons, global)', 'Médias'], $rights->map(fn ($r) => [$r->rights_status, $r->n])->all());
    }
}
