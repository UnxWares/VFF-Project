<?php

namespace App\Console\Commands;

use App\Services\LineMerger;
use App\Services\PlaceNames;
use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lignes en double et événements rattachés à la mauvaise ligne : quatre
 * passes rejouables (la chaîne vff:rebuild-history les reproduit), chacune
 * avec --dry-run et un rapport CSV de relecture
 * (storage/app/reports/merge-split-lines[-dry-run].csv).
 *
 * 1. SECTIONS (sections) : archeoferroviaire nomme ses tracés par SECTION
 *    (« Boissy-Saint-Léger Marles-en-Brie ») ; une ligne créée depuis archeo
 *    (« Ligne de X à Y », sans code) est fusionnée dans une ligne existante
 *    « Ligne de A à Y » si TOUT est vrai :
 *      - elles partagent le terminus d'arrivée Y (ou l'origine : « Mézidon à Dives »,
 *        première section de « Mézidon à Trouville-Deauville », 2026-10-19) ;
 *      - le tracé de la ligne existante est incomplet (< 85 % de sa longueur officielle) ;
 *      - la ligne créée touche une EXTRÉMITÉ de ce tracé (< 300 m) ;
 *      - les deux longueurs additionnées retrouvent la longueur officielle (± 15 %) ;
 *      - l'autre extrémité de la section (X, ou Y si l'origine est partagée) est un
 *        lieu de la ligne existante (nom, gares).
 *
 * 2. MÊME ARTICLE (article) : une ligne créée par vff:link-traces (sans code)
 *    porte le même article Wikipédia qu'une ligne à code, de même catégorie,
 *    et c'est bien la même ligne : l'une recouvre l'autre (≥ 90 % du plus
 *    court à ≤ 30 m), ou la ligne à code n'a aucun tracé et les longueurs
 *    concordent (± 25 % de la longueur officielle), ou la ligne créée touche
 *    la ligne à code (≤ 300 m) et tous ses toponymes sont des lieux de celle-ci
 *    (« Ligne d'Anor à la frontière » → La Plaine à Hirson et Anor (frontière)).
 *    Une catégorie différente (réseau secondaire homonyme) n'est JAMAIS fusionnée.
 *
 * 3. COUVERTURE (couverture) : une ligne créée (sans code) qui contient le
 *    tracé connu d'une ligne à code incomplète est cette ligne (ou une de ses
 *    sections) : ≥ 90 % du tracé connu de la ligne à code à ≤ 30 m, même
 *    catégorie, longueur ≤ 125 % de la longueur officielle, et ses DEUX
 *    extrémités sont des lieux de la ligne à code (nom, article, gares), dont
 *    au moins une citée, l'autre pouvant être à ≤ 6 km d'une extrémité
 *    (« Monsempron-Libos Pradines » = ligne 632000 Monsempron-Libos à Cahors).
 *
 * 4. ORPHELINES (orphelines) : ligne à code SANS AUCUN tracé (fermée avant le
 *    RFN actuel, souvent créée par lignes-oubliees). Preuves cherchées :
 *      a. tracé OHM / OSM / BD TOPO nommé exactement comme la ligne (nom ou
 *         article, « Ancienne ligne de Caen à Vire » compris), situé à ≤ 3 km
 *         d'un point connu de la ligne (gares Wikidata, gares VFF, extrémités) :
 *         non rattaché, il est rattaché à la ligne (match_method name_orphan,
 *         reposé après vff:match-current-network qui remet OSM / BD TOPO à zéro) ;
 *      b. ligne créée (sans code) qui EST cette ligne sous un autre nom
 *         (« Sarrebourg à Abreschwiller », « Rethondes à Troësnes ») : elle
 *         porte un tracé OHM nommé comme la ligne (a) ou passe à ≤ 400 m de
 *         ≥ 80 % de ses points connus (≥ 2), partage une extrémité citée, et
 *         ne dépasse pas 135 % de la longueur officielle (sinon tracé couvrant
 *         deux lignes, laissé à relire), ni moins de 60 % (section ou autre
 *         ligne partageant une section) ; ses deux bouts sont à ≤ 6 km de
 *         points connus de la ligne : fusionnée dans la ligne à code.
 *
 * 5. HOMONYMES (homonymes) : un événement Wikipédia tiré d'un article qui
 *    appartient à une AUTRE ligne (ligne à code portant ce titre, ou code de
 *    l'infobox via Wikidata) est déplacé sur cette ligne, ou supprimé s'il y
 *    figure déjà ; le titre est retiré de la ligne homonyme. Sans ligne à
 *    code propriétaire, un article partagé reste à la ligne dont le nom cite
 *    les toponymes du titre (« Quimper » ≠ « Quimperlé »). Wikidata : un
 *    événement dont l'item est rattaché à une autre ligne y est déplacé.
 *
 * Fusion (App\Services\LineMerger) : tracés, rattachements multiples,
 * événements (sans doublon), items Wikidata, médias passent sur la ligne
 * cible ; nom et slug de la ligne fusionnée gardés dans alt_names
 * (sections / merged) ; la ligne fusionnée est supprimée.
 *
 *   php artisan vff:merge-split-lines --dry-run
 *   php artisan vff:merge-split-lines --only=homonymes
 *   php artisan vff:merge-split-lines      # puis vff:build-line-references
 */
class MergeSplitLines extends Command
{
    protected $signature = 'vff:merge-split-lines
        {--dry-run : calcule et affiche sans rien écrire}
        {--only= : passes séparées par des virgules (sections, article, couverture, orphelines, homonymes)}';

    protected $description = 'Fusionne les lignes en double (sections, même article, couverture) et recolle les événements Wikipédia/Wikidata homonymes.';

    private const PHASES = ['sections', 'article', 'couverture', 'orphelines', 'homonymes'];

    /** Sources de tracés qui définissent une ligne (BD TOPO / OSM : réseau actuel, rattachement à part). */
    private const TRACE_SOURCES = "('archeoferroviaire', 'ohm', 'sncf')";

    private LineMerger $merger;

    private bool $dry;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<int, true> lignes déjà fusionnées (supprimées) pendant cette exécution */
    private array $gone = [];

    /** @var array<int, array<string, list<string>>> lieux connus de chaque ligne (cache) */
    private array $known = [];

    /** @var array<int, array<string, list<string>>> lieux des gares, par ligne */
    private array $stationWords = [];

    /** @var array<string, int>|null noms normalisés des communes */
    private ?array $communeNames = null;

    /** @var array<int, array<string, true>> noms complets des lieux connus (traits d'union gardés), par ligne (cache) */
    private array $knownFull = [];

    /** @var array<int, array<string, true>> noms complets des gares, par ligne */
    private array $stationFull = [];

    public function handle(LineMerger $merger): int
    {
        $this->merger = $merger;
        $this->dry = (bool) $this->option('dry-run');
        // État d'exécution remis à zéro : vff:rebuild-history appelle la commande plusieurs fois dans le même
        // processus (sinon le rapport du 2e passage reprend les décisions du 1er, et les caches de lieux sont périmés).
        $this->rows = [];
        $this->gone = [];
        $this->known = [];
        $this->stationWords = [];
        $this->knownFull = [];
        $this->stationFull = [];
        $this->communeNames = null;
        $only = $this->option('only') ? array_map('trim', explode(',', $this->option('only'))) : self::PHASES;
        if ($bad = array_diff($only, self::PHASES)) {
            $this->error('Passe(s) inconnue(s) : '.implode(', ', $bad));

            return self::INVALID;
        }
        DB::connection()->disableQueryLog();

        if (in_array('sections', $only, true)) {
            $this->sections();
        }
        if (array_intersect(['article', 'couverture', 'orphelines'], $only)) {
            $this->prepareGeometries();
            if (in_array('article', $only, true)) {
                $this->sameArticle();
            }
            if (in_array('couverture', $only, true)) {
                $this->coverage();
            }
            if (in_array('orphelines', $only, true)) {
                $this->orphans();
            }
        }
        if (in_array('homonymes', $only, true)) {
            $this->homonyms();
        }

        $this->writeCsv();
        if ($this->dry) {
            $this->warn('Simulation (--dry-run) : rien n\'a été écrit.');
        } elseif ($this->gone) {
            $this->info('Relancer vff:build-line-references puis vff:build-era-segments sur les lignes cibles.');
        }

        return self::SUCCESS;
    }

    // ───────────────────────────── 1. sections ─────────────────────────────

    private function sections(): void
    {
        $pairs = DB::select(<<<'SQL'
            WITH created AS (
                SELECT l.id, l.name, r.geom, r.length_m,
                       lower(unaccent(split_part(l.name, ' à ', 2))) AS dest,
                       lower(unaccent(regexp_replace(split_part(l.name, ' à ', 1), '^Ligne (de la |de l''|du |des |de |d'')', ''))) AS orig
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.slug LIKE 'archeo-%' AND l.code IS NULL AND l.name LIKE '% à %'
            ),
            existing AS (
                SELECT l.id, l.name, l.length_official_m AS off, r.geom, r.length_m,
                       lower(unaccent(l.name)) AS uname
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.slug NOT LIKE 'archeo-%' AND l.length_official_m > 0
                  AND r.length_m < 0.85 * l.length_official_m
            )
            SELECT DISTINCT ON (c.id) c.id AS created_id, c.name AS created_name, ROUND(c.length_m / 1000.0, 1) AS created_km,
                   e.id AS target_id, e.name AS target_name, ROUND(e.length_m / 1000.0, 1) AS target_km, ROUND(e.off / 1000.0, 1) AS official_km,
                   (c.dest <> '' AND e.uname LIKE '% a ' || c.dest || '%') AS by_dest
            FROM created c JOIN existing e
              ON abs((e.length_m + c.length_m) - e.off) < 0.15 * e.off
             AND ((c.dest <> '' AND e.uname LIKE '% a ' || c.dest || '%')
                  -- même ORIGINE (« Mézidon à Dives », première section de « Mézidon à Trouville-Deauville »)
                  OR (c.orig <> '' AND (e.uname LIKE 'ligne de ' || c.orig || ' a %' OR e.uname LIKE 'ligne d''' || c.orig || ' a %'
                      OR e.uname LIKE 'ligne du ' || c.orig || ' a %' OR e.uname LIKE 'ligne des ' || c.orig || ' a %'
                      OR e.uname LIKE 'ligne de la ' || c.orig || ' a %')))
             AND ST_DWithin(ST_Boundary(e.geom)::geography, c.geom::geography, 300)
            ORDER BY c.id, (c.dest <> '' AND e.uname LIKE '% a ' || c.dest || '%') DESC, abs((e.length_m + c.length_m) - e.off)
            SQL);

        $this->info('→ 1. Sections créées à part : '.count($pairs));
        foreach ($pairs as $p) {
            // L'autre extrémité de la section doit être un lieu de la ligne (nom, gares) : sinon c'est une autre
            // ligne qui aboutit au même terminus (« Armentières à Halluin » n'est pas une section de Somain à Halluin)
            // ou qui part de la même gare (« Chalon-sur-Saône à Mâcon » n'est pas une section de Chalon à Bourg).
            $places = PlaceNames::parseLineName($p->created_name)['places'] ?? [];
            $other = ($p->by_dest ? ($places[0] ?? '') : ($places[1] ?? ''));
            if ($this->placeOn($other, (int) $p->target_id) !== true) {
                $this->row('sections', 'rejeté', (object) ['cid' => $p->created_id, 'cname' => $p->created_name, 'lid' => $p->target_id,
                    'lname' => $p->target_name, 'code' => ''], ($p->by_dest ? 'origine' : 'destination')." « {$other} » étrangère à la ligne (même ".($p->by_dest ? 'terminus' : 'origine').' seulement)');

                continue;
            }
            $this->merge('sections', (int) $p->created_id, (int) $p->target_id,
                "section {$p->created_km} km + tracé {$p->target_km} km ≈ {$p->official_km} km officiels", 'merged_section');
            if (! $this->dry) {
                DB::update(
                    "UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb)
                        || jsonb_build_object('sections', COALESCE(alt_names->'sections', '[]'::jsonb) || to_jsonb(?::text)), updated_at = NOW()
                     WHERE id = ?",
                    [$p->created_name, $p->target_id],
                );
            }
        }
    }

    // ─────────────────────── géométries de travail ───────────────────────

    /**
     * lm_c : lignes créées par vff:link-traces sans code, géométrie = tracés de la
     * source qui les a créées (les tracés OHM rattachés par recouvrement la
     * doubleraient) ; lm_l : lignes à code, union des tracés archeo, OHM et
     * SNCF. En Lambert-93.
     */
    private function prepareGeometries(): void
    {
        DB::statement('DROP TABLE IF EXISTS lm_c, lm_l');
        $src = self::TRACE_SOURCES;
        DB::statement("CREATE TEMP TABLE lm_c AS
            SELECT l.id, l.name, l.network, l.wikipedia_title, g.g, ST_Length(g.g) AS len
            FROM lines l
            JOIN LATERAL (SELECT ST_Union(ST_Transform(ST_Simplify(t.geom, 0.00001), 2154)) AS g
                          FROM source_traces t WHERE t.line_id = l.id AND t.source = l.alt_names->>'source') g ON g.g IS NOT NULL
            WHERE l.code IS NULL AND l.alt_names->>'created_by' = 'vff:link-traces'");
        DB::statement("CREATE TEMP TABLE lm_l AS
            SELECT l.id, l.name, l.code, l.network, l.wikipedia_title, l.length_official_m AS off, g.g, COALESCE(ST_Length(g.g), 0) AS len
            FROM lines l
            LEFT JOIN LATERAL (SELECT ST_Union(ST_Transform(ST_Simplify(t.geom, 0.00001), 2154)) AS g
                               FROM source_traces t WHERE t.line_id = l.id AND t.source IN {$src}) g ON TRUE
            WHERE l.code IS NOT NULL");
        DB::statement('CREATE INDEX ON lm_c USING gist (g)');
        DB::statement('CREATE INDEX ON lm_l USING gist (g)');
        DB::statement('ANALYZE lm_c');
        DB::statement('ANALYZE lm_l');
    }

    // ───────────────────────────── 2. même article ─────────────────────────────

    private function sameArticle(): void
    {
        $pairs = DB::select(<<<'SQL'
            SELECT c.id AS cid, c.name AS cname, c.network AS cnet, c.len AS clen,
                   l.id AS lid, l.name AS lname, l.code, l.network AS lnet, l.off, l.len AS llen, l.wikipedia_title,
                   CASE WHEN l.g IS NULL THEN NULL ELSE ST_Length(ST_Intersection(c.g, ST_Buffer(l.g, 30))) / NULLIF(c.len, 0) END AS c_in_l,
                   CASE WHEN l.g IS NULL THEN NULL ELSE ST_Length(ST_Intersection(l.g, ST_Buffer(c.g, 30))) / NULLIF(l.len, 0) END AS l_in_c,
                   CASE WHEN l.g IS NULL THEN NULL ELSE ST_Distance(c.g, l.g) END AS dist
            FROM lm_c c JOIN lm_l l ON l.wikipedia_title = c.wikipedia_title
            ORDER BY c.id
            SQL);
        $this->info('→ 2. Lignes créées portant l\'article d\'une ligne à code : '.count($pairs));
        $byC = [];
        foreach ($pairs as $p) {
            $byC[$p->cid][] = $p;
        }
        foreach ($byC as $cid => $list) {
            if (count($list) > 1) {
                $this->row('article', 'à relire', $list[0], 'article partagé par plusieurs lignes à code');

                continue;
            }
            $p = $list[0];
            if ($p->cnet !== $p->lnet) {
                $this->row('article', 'distinct', $p, "catégorie {$p->cnet} ≠ {$p->lnet} : réseau homonyme (événements traités en passe 4)");

                continue;
            }
            $why = null;
            if ($p->c_in_l !== null && max((float) $p->c_in_l, (float) $p->l_in_c) >= 0.9) {
                $why = sprintf('recouvrement %d %% / %d %%', round($p->c_in_l * 100), round($p->l_in_c * 100));
            } elseif ($p->llen < 1 && $p->off > 0 && abs($p->clen - $p->off) <= 0.25 * $p->off) {
                $why = sprintf('ligne à code sans tracé, %.1f km pour %.1f km officiels', $p->clen / 1000, $p->off / 1000);
            } elseif ($p->dist !== null && $p->dist <= 300 && $this->namePlacesOn($p->cname, (int) $p->lid, allowEmpty: true)) {
                $why = sprintf('section contiguë (%d m), toponymes de la ligne', round($p->dist));
            }
            if ($why) {
                $this->merge('article', (int) $cid, (int) $p->lid, "même article « {$p->wikipedia_title} », {$why}", 'merged_duplicate', $p);
            } else {
                $this->row('article', 'à relire', $p, sprintf('même article, mais ni recouvrement (%s), ni section prouvée',
                    $p->c_in_l === null ? 'ligne à code sans tracé' : round($p->c_in_l * 100).' % / '.round($p->l_in_c * 100).' %'));
            }
        }
    }

    // ───────────────────────────── 3. couverture ─────────────────────────────

    private function coverage(): void
    {
        $pairs = DB::select(<<<'SQL'
            SELECT c.id AS cid, c.name AS cname, c.len AS clen, l.id AS lid, l.name AS lname, l.code, l.off, l.len AS llen,
                   ST_Length(ST_Intersection(l.g, ST_Buffer(c.g, 30))) / l.len AS l_in_c
            FROM lm_c c JOIN lm_l l ON l.g IS NOT NULL AND l.len >= 500 AND c.network = l.network
                 AND c.g && ST_Expand(l.g, 30) AND ST_DWithin(c.g, l.g, 30)
            WHERE (l.off IS NULL OR l.len < 0.85 * l.off)
            SQL);
        $cands = [];
        foreach ($pairs as $p) {
            if ($p->l_in_c >= 0.9 && ! isset($this->gone[$p->cid]) && ! isset($this->gone[$p->lid])) {
                $cands[$p->cid][] = $p;
            }
        }
        $this->info('→ 3. Lignes créées contenant le tracé connu d\'une ligne à code incomplète : '.count($cands));
        foreach ($cands as $cid => $list) {
            $ok = [];
            foreach ($list as $p) {
                $reason = $this->coverageProof($p);
                if (is_string($reason)) {
                    $ok[] = [$p, $reason];
                } else {
                    $this->row('couverture', 'rejeté', $p, $reason[0]);
                }
            }
            if (count($ok) === 1) {
                [$p, $reason] = $ok[0];
                $this->merge('couverture', (int) $cid, (int) $p->lid, $reason, 'merged_duplicate', $p);
            } elseif (count($ok) > 1) {
                foreach ($ok as [$p, $reason]) {
                    $this->row('couverture', 'à relire', $p, 'plusieurs lignes à code candidates : '.$reason);
                }
            }
        }
        $this->citedSections();
    }

    /**
     * Section créée à part d'une ligne à code incomplète, reconnue par ses
     * DEUX extrémités : toutes deux lieux de la ligne (nom, article, gares du
     * schéma Wikipédia ou de lignes-oubliees), bouts du tracé à ≤ 6 km de points
     * connus de la ligne, tracé complémentaire (≤ 30 % sur le tracé connu) et
     * longueurs additionnées ≤ 120 % de la longueur officielle
     * (« Saint-Germain-du-Plain à Bourg-en-Bresse » = ligne 882000 Chalon-sur-Saône
     * à Bourg-en-Bresse, dont seuls 4,5 km étaient tracés).
     */
    private function citedSections(): void
    {
        $lines = DB::select('SELECT id, name, code, network, off, len FROM lm_l WHERE off > 0 AND len < 0.85 * off');
        $index = [];
        foreach ($lines as $l) {
            if (! isset($this->gone[$l->id])) {
                foreach (array_keys($this->knownPlaces((int) $l->id)) as $k) {
                    $index[$k][] = $l;
                }
            }
        }
        $cands = [];
        foreach (DB::select('SELECT id, name, network, len FROM lm_c') as $c) {
            if (isset($this->gone[$c->id])) {
                continue;
            }
            $ends = array_slice(PlaceNames::parseLineName($c->name)['places'] ?? [], 0, 2);
            if (count($ends) < 2) {
                continue;
            }
            $hit = [];
            foreach (self::subPlaces($ends[0]) as $w) {
                foreach ($index[implode(' ', $w)] ?? [] as $l) {
                    $hit[$l->id] = $l;
                }
            }
            foreach ($hit as $lid => $l) {
                if ($l->network === $c->network && $this->placeOn($ends[0], $lid) === true && $this->placeOn($ends[1], $lid) === true
                    && $c->len + $l->len <= 1.2 * $l->off) {
                    $cands[$c->id][] = [$c, $l];
                }
            }
        }
        if (! $cands) {
            return;
        }
        $this->buildAnchors(array_values(array_unique(array_merge(...array_map(fn ($list) => array_map(fn ($x) => (int) $x[1]->id, $list), $cands)))));
        $n = 0;
        $added = [];   // km déjà ajoutés à chaque ligne par une autre section dans cette passe
        foreach ($cands as $cid => $list) {
            $ok = [];
            foreach ($list as [$c, $l]) {
                $p = (object) ['cid' => $c->id, 'cname' => $c->name, 'lid' => $l->id, 'lname' => $l->name, 'code' => $l->code];
                if ($c->len + $l->len + ($added[$l->id] ?? 0) > 1.2 * $l->off) {
                    $this->row('couverture', 'à relire', $p, 'section citée, mais les sections additionnées dépassent 120 % de la longueur officielle');

                    continue;
                }
                $over = $l->len > 0 ? (float) DB::selectOne('SELECT ST_Length(ST_Intersection(c.g, ST_Buffer(l.g, 30))) / NULLIF(c.len, 0) AS s
                    FROM lm_c c, lm_l l WHERE c.id = ? AND l.id = ?', [$c->id, $l->id])->s : 0.0;
                if ($over > 0.3) {
                    $this->row('couverture', 'rejeté', $p, sprintf('section citée, mais %d %% sur le tracé déjà connu', round($over * 100)));

                    continue;
                }
                $ends = $this->endpointsDistance((int) $c->id, (int) $l->id);
                if ($ends === null || max($ends) > 6000) {
                    $this->row('couverture', 'à relire', $p, 'section citée, mais un bout du tracé est '
                        .($ends === null ? 'introuvable' : sprintf('à %.1f km de tout point connu de la ligne', max($ends) / 1000)));

                    continue;
                }
                $ok[] = [$p, sprintf('section citée (extrémités = lieux de la ligne), %.1f km + %.1f km tracés pour %.1f km officiels', $c->len / 1000, $l->len / 1000, $l->off / 1000)];
            }
            if (count($ok) === 1) {
                [$p, $why] = $ok[0];
                $this->merge('couverture', (int) $cid, (int) $p->lid, $why, 'merged_section');
                $added[$p->lid] = ($added[$p->lid] ?? 0) + (float) DB::table('lm_c')->where('id', $cid)->value('len');
                $n++;
            } elseif (count($ok) > 1) {
                foreach ($ok as [$p, $why]) {
                    $this->row('couverture', 'à relire', $p, 'plusieurs lignes à code candidates : '.$why);
                }
            }
        }
        $this->info("  Sections citées par leurs deux extrémités : {$n}");
    }

    /**
     * Preuve qu'une ligne créée EST la ligne à code (ou une de ses sections).
     *
     * @return string|array{0: string} raison (acceptée) ou [raison du rejet]
     */
    private function coverageProof(object $p): string|array
    {
        if ($p->off && $p->clen > 1.25 * $p->off) {
            return [sprintf('trop longue : %.1f km pour %.1f km officiels (autre ligne prolongée)', $p->clen / 1000, $p->off / 1000)];
        }
        $parsed = PlaceNames::parseLineName($p->cname);
        if (! $parsed || count($parsed['places']) < 2) {
            return ['nom sans deux extrémités'];
        }
        $cited = 0;
        $near = [];
        foreach (array_slice($parsed['places'], 0, 2) as $place) {
            $on = $this->placeOn($place, (int) $p->lid);
            if ($on === true) {
                $cited++;

                continue;
            }
            $d = $on === false ? $this->distanceToLineEnds($place, (int) $p->lid, (int) $p->cid) : null;
            if ($d === null || $d > 6000) {
                return ["extrémité « {$place} » étrangère à la ligne ".($d === null ? '(non localisée)' : sprintf('(%.1f km)', $d / 1000))];
            }
            $near[] = sprintf('%s à %.1f km d\'une extrémité', $place, $d / 1000);
        }
        if ($cited < 1) {
            return ['aucune extrémité citée par la ligne à code'];
        }

        return sprintf('contient %d %% du tracé connu (%.1f km), %.1f km%s, extrémités : %s', round($p->l_in_c * 100), $p->llen / 1000,
            $p->clen / 1000, $p->off ? sprintf(' pour %.1f km officiels', $p->off / 1000) : '', $near ? implode(', ', $near) : 'citées');
    }

    // ───────────────────────────── 4. orphelines ─────────────────────────────

    private function orphans(): void
    {
        $orph = [];
        foreach (DB::select('SELECT l.id, l.name, l.code, l.network, l.wikipedia_title, l.length_official_m AS off FROM lines l
            WHERE l.code IS NOT NULL AND NOT EXISTS (SELECT 1 FROM source_traces t WHERE t.line_id = l.id)') as $o) {
            if (! isset($this->gone[$o->id])) {
                $orph[(int) $o->id] = $o;
            }
        }
        $this->info('→ 4. Lignes à code sans aucun tracé : '.count($orph));
        if (! $orph) {
            return;
        }
        $this->buildAnchors(array_keys($orph));

        // a. Tracés nommés exactement comme la ligne.
        $keys = [];
        foreach ($orph as $o) {
            foreach (array_filter([$o->name, $o->wikipedia_title]) as $n) {
                $keys[$this->nameKey($n)][] = (int) $o->id;
            }
        }
        $named = [];   // orphan id => [trace rows]
        $names = DB::table('source_traces')->whereIn('source', ['ohm', 'osm', 'bdtopo'])->whereNotNull('name')->distinct()->pluck('name');
        $hits = [];
        foreach ($names as $n) {
            if (isset($keys[$this->nameKey($n)])) {
                $hits[$n] = $keys[$this->nameKey($n)];
            }
        }
        foreach (array_chunk(array_keys($hits), 200, true) as $chunk) {
            foreach (DB::table('source_traces')->whereIn('source', ['ohm', 'osm', 'bdtopo'])->whereIn('name', $chunk)
                ->get(['id', 'source', 'name', 'line_id', 'match_method', 'length_m']) as $t) {
                foreach (array_unique($hits[$t->name]) as $oid) {
                    $named[$oid][] = $t;
                }
            }
        }
        $attached = 0;
        $byCreated = [];   // created line => [orphan id => preuve]
        foreach ($named as $oid => $traces) {
            $near = DB::select('SELECT t.id FROM source_traces t, lm_ancg a WHERE a.line_id = ? AND t.id IN ('.implode(',', array_column($traces, 'id')).')
                AND ST_DWithin(ST_Transform(t.geom, 2154), a.g, 3000)', [$oid]);
            $near = array_flip(array_map(fn ($r) => (int) $r->id, $near));
            $free = [];
            foreach ($traces as $t) {
                if (! isset($near[$t->id])) {
                    continue;
                }
                if ($t->line_id === null) {
                    $free[] = (int) $t->id;
                } elseif (DB::table('lines')->where('id', $t->line_id)->whereNull('code')->whereRaw("alt_names->>'created_by' = 'vff:link-traces'")->exists()) {
                    $byCreated[(int) $t->line_id][$oid] = "tracé {$t->source} « {$t->name} »";
                }
            }
            // Un tracé libre qui double une ligne créée (≥ 50 % à ≤ 30 m) n'est pas rattaché (doublon d'affichage) :
            // il prouve seulement que cette ligne créée est la ligne orpheline (étape b).
            if ($free) {
                foreach (DB::select('SELECT t.id, t.name, t.source, c.id AS cid FROM source_traces t
                    JOIN lm_c c ON c.g && ST_Expand(ST_Transform(t.geom, 2154), 30) AND ST_DWithin(c.g, ST_Transform(t.geom, 2154), 30)
                    WHERE t.id IN ('.implode(',', $free).')
                      AND ST_Length(ST_Intersection(ST_Transform(t.geom, 2154), ST_Buffer(c.g, 30))) >= 0.5 * ST_Length(ST_Transform(t.geom, 2154))') as $d) {
                    $byCreated[(int) $d->cid][$oid] ??= "tracé {$d->source} « {$d->name} »";
                    $free = array_values(array_diff($free, [(int) $d->id]));
                }
            }
            if ($free) {
                $km = DB::table('source_traces')->whereIn('id', $free)->sum('length_m') / 1000;
                if (! $this->dry) {
                    DB::table('source_traces')->whereIn('id', $free)->update(['line_id' => $oid, 'match_method' => 'name_orphan', 'match_score' => 1, 'updated_at' => now()]);
                }
                $attached += count($free);
                $o = $orph[$oid];
                $this->rows[] = ['phase' => 'orphelines', 'decision' => $this->dry ? 'à rattacher' : 'rattaché', 'line_id' => null,
                    'line_name' => count($free).' tracé(s) nommé(s)', 'target_id' => $oid, 'target_name' => $o->name, 'target_code' => $o->code,
                    'detail' => sprintf('%.1f km de tracés OSM / OHM / BD TOPO non rattachés, nommés comme la ligne, à ≤ 3 km de ses points connus', $km)];
            }
        }

        // b. Lignes créées qui passent par les points connus de la ligne orpheline.
        foreach (DB::select('SELECT a.line_id AS oid, c.id AS cid, a.n,
                (SELECT COUNT(DISTINCT ST_SnapToGrid(d.geom, 300)) FROM ST_Dump(a.g) d WHERE ST_DWithin(d.geom, c.g, 400)) AS hits
            FROM lm_ancg a JOIN lm_c c ON c.g && ST_Expand(a.g, 400) AND ST_DWithin(c.g, a.g, 400)
            WHERE a.n >= 2') as $r) {
            if ($r->hits >= 2 && $r->hits >= 0.8 * $r->n) {
                $byCreated[(int) $r->cid][(int) $r->oid] ??= "passe à ≤ 400 m de {$r->hits}/{$r->n} points connus";
            }
        }
        $cLines = $byCreated ? DB::table('lm_c')->whereIn('id', array_keys($byCreated))->get(['id', 'name', 'len'])->keyBy('id') : collect();
        foreach ($byCreated as $cid => $cands) {
            $c = $cLines[$cid] ?? null;
            if (! $c || isset($this->gone[$cid])) {
                continue;
            }
            $ok = [];
            foreach ($cands as $oid => $proof) {
                $o = $orph[$oid];
                $p = (object) ['cid' => $cid, 'cname' => $c->name, 'lid' => $oid, 'lname' => $o->name, 'code' => $o->code];
                if (! $this->sharesEnd($c->name, $oid)) {
                    $this->row('orphelines', 'à relire', $p, "{$proof}, mais aucune extrémité commune");

                    continue;
                }
                // Les DEUX bouts du tracé doivent être près de points connus de la ligne : sinon c'est une autre
                // ligne qui partage une section avec elle (« Villers-Cotterêts à Port-aux-Perches »).
                $ends = $this->endpointsDistance($cid, $oid);
                if ($ends === null || max($ends) > 6000) {
                    $this->row('orphelines', 'à relire', $p, $proof.', mais un bout du tracé est '
                        .($ends === null ? 'introuvable' : sprintf('à %.1f km de tout point connu de la ligne', max($ends) / 1000)));

                    continue;
                }
                if (! $o->off && ! str_starts_with($proof, 'tracé')) {
                    $this->row('orphelines', 'à relire', $p, "{$proof}, mais longueur officielle inconnue");

                    continue;
                }
                if ($o->off && $c->len > 1.35 * $o->off) {
                    $this->row('orphelines', 'à relire', $p, sprintf('%s, mais %.1f km pour %.1f km officiels : tracé couvrant aussi une autre ligne', $proof, $c->len / 1000, $o->off / 1000));

                    continue;
                }
                // Trop court : autre ligne qui partage une section (« Villers-Cotterêts Port-aux-Perches »,
                // voie hippomobile 1839-1884 reprise en partie par la ligne), ou simple section : à relire.
                if ($o->off && $c->len < 0.6 * $o->off) {
                    $this->row('orphelines', 'à relire', $p, sprintf('%s, mais %.1f km pour %.1f km officiels : section ou autre ligne', $proof, $c->len / 1000, $o->off / 1000));

                    continue;
                }
                $ok[$oid] = sprintf('%s, extrémité commune, %.1f km%s', $proof, $c->len / 1000, $o->off ? sprintf(' pour %.1f km officiels', $o->off / 1000) : '');
            }
            if (count($ok) === 1) {
                $this->merge('orphelines', $cid, array_key_first($ok), 'ligne orpheline : '.reset($ok), 'merged_duplicate');
            } elseif (count($ok) > 1) {
                foreach ($ok as $oid => $why) {
                    $this->row('orphelines', 'à relire', (object) ['cid' => $cid, 'cname' => $c->name, 'lid' => $oid, 'lname' => $orph[$oid]->name, 'code' => $orph[$oid]->code],
                        'plusieurs lignes orphelines candidates : '.$why);
                }
            }
        }
        $left = DB::selectOne('SELECT COUNT(*) AS n FROM lines l WHERE l.code IS NOT NULL AND NOT EXISTS (SELECT 1 FROM source_traces t WHERE t.line_id = l.id)')->n;
        $this->line("  {$attached} tracés nommés rattachés ; lignes à code encore sans tracé : {$left}".($this->dry ? ' (avant écriture)' : ''));
    }

    /**
     * lm_anc / lm_ancg : points connus de lignes (gares de l'item Wikidata, gares
     * VFF, extrémités nommées localisées : l'instance de commune la plus proche
     * des autres points, ou toutes s'il n'y en a pas), en Lambert-93.
     *
     * @param  list<int>  $lineIds
     */
    private function buildAnchors(array $lineIds): void
    {
        DB::statement('DROP TABLE IF EXISTS lm_anc, lm_ancg');
        DB::statement('CREATE TEMP TABLE lm_anc (line_id bigint, g geometry)');
        if (! $lineIds) {
            DB::statement('CREATE TEMP TABLE lm_ancg (line_id bigint, g geometry, n int)');

            return;
        }
        $ids = implode(',', $lineIds);
        DB::statement("INSERT INTO lm_anc
            SELECT w.line_id, ST_Transform(d.geom, 2154) FROM wikidata_lines w, LATERAL ST_Dump(w.points) d
            WHERE w.line_id IN ({$ids}) AND w.points IS NOT NULL
            UNION ALL
            SELECT sl.line_id, ST_Transform(s.geom, 2154) FROM station_lines sl JOIN stations s ON s.id = sl.station_id
            WHERE sl.line_id IN ({$ids}) AND s.geom IS NOT NULL");
        $this->ensureGazetteer();
        foreach (DB::table('lines')->whereIn('id', $lineIds)->get(['id', 'name']) as $l) {
            foreach (array_slice(PlaceNames::parseLineName($l->name)['places'] ?? [], 0, 2) as $end) {
                DB::statement('INSERT INTO lm_anc SELECT ?, z.g FROM lm_gaz z
                    WHERE z.norm = ? AND (NOT EXISTS (SELECT 1 FROM lm_anc a WHERE a.line_id = ?)
                        OR ST_DWithin(z.g, (SELECT ST_Collect(a.g) FROM lm_anc a WHERE a.line_id = ?), 30000))',
                    [$l->id, PlaceNames::normalize($end), $l->id, $l->id]);
            }
        }
        DB::statement('CREATE INDEX ON lm_anc USING gist (g)');
        DB::statement('CREATE TEMP TABLE lm_ancg AS SELECT line_id, ST_Collect(g) AS g, COUNT(DISTINCT ST_SnapToGrid(g, 300)) AS n FROM lm_anc GROUP BY line_id');
    }

    /** Clé de comparaison d'un nom de ligne (« Ancienne ligne de Caen à Vire » = « Ligne de Caen à Vire »). */
    private function nameKey(string $name): string
    {
        $k = PlaceNames::normalize(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));

        return preg_replace('/^(?:ancienne|ancien|ex)\s+/', '', $k);
    }

    /**
     * Distances (m) des deux bouts les plus éloignés du tracé d'une ligne créée
     * aux points connus de la ligne orpheline (lm_anc).
     *
     * @return array{0: float, 1: float}|null
     */
    private function endpointsDistance(int $createdId, int $lineId): ?array
    {
        $r = DB::selectOne('WITH s AS (SELECT ST_LineMerge(g) AS g FROM lm_c WHERE id = ?),
                b AS (SELECT (ST_Dump(ST_Boundary(s.g))).geom AS p FROM s),
                pair AS (SELECT b1.p AS p1, b2.p AS p2 FROM b b1, b b2 ORDER BY ST_Distance(b1.p, b2.p) DESC LIMIT 1),
                a AS (SELECT ST_Collect(g) AS g FROM lm_anc WHERE line_id = ?)
            SELECT ST_Distance(pair.p1, a.g) AS d1, ST_Distance(pair.p2, a.g) AS d2 FROM pair, a', [$createdId, $lineId]);

        return $r && $r->d1 !== null && $r->d2 !== null ? [(float) $r->d1, (float) $r->d2] : null;
    }

    /** Une extrémité de la ligne créée est un lieu connu de la ligne à code. */
    private function sharesEnd(string $createdName, int $lineId): bool
    {
        foreach (array_slice(PlaceNames::parseLineName($createdName)['places'] ?? [], 0, 2) as $place) {
            if ($this->placeOn($place, $lineId) === true) {
                return true;
            }
        }

        return false;
    }

    // ───────────────────────────── 5. homonymes ─────────────────────────────

    private function homonyms(): void
    {
        $lines = [];
        foreach (DB::select('SELECT id, name, code, network, wikipedia_title FROM lines') as $l) {
            $lines[(int) $l->id] = $l;
        }
        // Propriétaires d'un article : lignes à code qui le portent, ou dont le code est dans l'infobox (Wikidata).
        $owners = [];
        foreach ($lines as $l) {
            if ($l->code && $l->wikipedia_title) {
                $owners[$this->tkey($l->wikipedia_title)][(int) $l->id] = true;
            }
        }
        $byCode = [];
        foreach ($lines as $l) {
            if ($l->code) {
                $byCode[$l->code][] = (int) $l->id;
            }
        }
        foreach (DB::select('SELECT frwiki_title, codes, line_id FROM wikidata_lines WHERE frwiki_title IS NOT NULL') as $w) {
            foreach (json_decode($w->codes ?? '[]', true) ?: [] as $c) {
                foreach ($byCode[$c['code'] ?? ''] ?? [] as $lid) {
                    $owners[$this->tkey($w->frwiki_title)][$lid] = true;
                }
            }
        }
        // Lignes qui portent chaque titre (avec ou sans code).
        $holders = [];
        foreach ($lines as $l) {
            if ($l->wikipedia_title) {
                $holders[$this->tkey($l->wikipedia_title)][] = (int) $l->id;
            }
        }

        $events = DB::select("SELECT id, line_id, source_url, section_from, section_to FROM line_events WHERE source = 'wikipedia' AND source_url IS NOT NULL");
        $plan = [];   // [line_id][title] => ['target' => ?int, 'ids' => [], 'why' => string]
        foreach ($events as $e) {
            $title = LineMerger::wikipediaTitle($e->source_url);
            $lid = (int) $e->line_id;
            if (! $title || isset($this->gone[$lid]) || ! isset($lines[$lid])) {
                continue;
            }
            $k = $this->tkey($title);
            $target = null;
            $why = null;
            $own = array_keys($owners[$k] ?? []);
            if ($own && ! in_array($lid, $own, true)) {
                $target = count($own) === 1 ? $own[0] : $this->ownerOfSection($own, $e);
                $why = 'article de '.implode(', ', array_map(fn ($o) => $lines[$o]->name.' ('.$lines[$o]->code.')', $own))
                    .(count($own) > 1 ? ($target ? ' ; portion citée sur '.$lines[$target]->name : ' ; portion non attribuable') : '');
                // Portion non attribuable, mais la ligne n'est même pas de la catégorie des lignes de l'article
                // (voie particulière nommée comme la ligne) : l'événement est faux ici, on le retire.
                if (! $target && ! in_array($lines[$lid]->network, array_map(fn ($o) => $lines[$o]->network, $own), true)) {
                    $target = 0;
                    $why .= ', autre catégorie : supprimé';
                }
            } elseif (! $own && count($holders[$k] ?? []) > 1 && ! $this->titleCites($title, $lines[$lid]->name)) {
                $compatible = array_values(array_filter($holders[$k], fn ($h) => $h !== $lid && $this->titleCites($title, $lines[$h]->name)));
                if (! $compatible) {
                    continue;
                }
                $target = count($compatible) === 1 ? $compatible[0] : null;
                $why = 'le nom ne cite pas les toponymes du titre, contrairement à '.implode(', ', array_map(fn ($o) => $lines[$o]->name, $compatible));
            } else {
                continue;
            }
            $plan[$lid][$title."\t".$target] ??= ['target' => $target, 'ids' => [], 'why' => $why];
            $plan[$lid][$title."\t".$target]['ids'][] = (int) $e->id;
        }

        $n = 0;
        foreach ($plan as $lid => $byTitle) {
            foreach ($byTitle as $key => $p) {
                $title = explode("\t", $key)[0];
                $n += count($p['ids']);
                $from = $lines[$lid];
                $to = $p['target'] ? $lines[$p['target']] : null;
                $drop = $p['target'] === 0;
                $r = ['events_moved' => 0, 'events_dropped' => 0];
                if (! $this->dry && ($to || $drop)) {
                    DB::transaction(function () use ($p, $from, $title, $drop, &$r) {
                        $r = $drop
                            ? ['events_moved' => 0, 'events_dropped' => DB::table('line_events')->whereIn('id', $p['ids'])->delete()]
                            : $this->merger->moveEvents($p['ids'], $p['target']);
                        if ($this->tkey((string) $from->wikipedia_title) === $this->tkey($title)) {
                            DB::table('lines')->where('id', $from->id)->update(['wikipedia_title' => null, 'updated_at' => now()]);
                        }
                    }, 5);
                }
                $this->rows[] = [
                    'phase' => 'homonymes', 'decision' => $drop ? ($this->dry ? 'à supprimer' : 'supprimé') : ($to ? ($this->dry ? 'à déplacer' : 'déplacé') : 'à relire'),
                    'line_id' => $from->id, 'line_name' => $from->name, 'target_id' => $to?->id, 'target_name' => $to?->name,
                    'target_code' => $to?->code,
                    'detail' => sprintf('%d événement(s) de « %s » : %s%s', count($p['ids']), $title, $p['why'],
                        $this->dry || ! ($to || $drop) ? '' : sprintf(' ; %d déplacé(s), %d déjà présent(s) supprimé(s)', $r['events_moved'], $r['events_dropped'])),
                ];
            }
        }
        $this->info('→ 5. Événements Wikipédia d\'un article homonyme : '.$n.' sur '.count($plan).' ligne(s)');

        // Wikidata : l'item de l'événement est rattaché à une autre ligne.
        $wd = DB::select("SELECT e.id, e.line_id, w.line_id AS target FROM line_events e
            JOIN wikidata_lines w ON w.qid = substring(e.source_url from 'wiki/(Q[0-9]+)')
            WHERE e.source = 'wikidata' AND w.line_id IS NOT NULL AND w.line_id <> e.line_id");
        if ($wd && ! $this->dry) {
            foreach ($wd as $e) {
                $this->merger->moveEvents([(int) $e->id], (int) $e->target);
            }
        }
        $this->info('  Événements Wikidata d\'un item rattaché à une autre ligne : '.count($wd));
        foreach ($wd as $e) {
            $this->rows[] = ['phase' => 'homonymes', 'decision' => $this->dry ? 'à déplacer' : 'déplacé', 'line_id' => $e->line_id,
                'line_name' => $lines[$e->line_id]->name ?? '', 'target_id' => $e->target, 'target_name' => $lines[$e->target]->name ?? '',
                'target_code' => $lines[$e->target]->code ?? '', 'detail' => "événement Wikidata {$e->id}"];
        }
    }

    /**
     * Article commun à plusieurs lignes à code (« Ligne de Nantes à
     * Saint-Gilles-Croix-de-Vie » = 534000 + 535000) : la ligne dont les lieux
     * connus contiennent les lieux cités par l'événement, si elle est unique.
     *
     * @param  list<int>  $owners
     */
    private function ownerOfSection(array $owners, object $e): ?int
    {
        $places = array_filter([$e->section_from, $e->section_to]);
        if (! $places) {
            return null;
        }
        $hits = array_values(array_filter($owners, function ($o) use ($places) {
            foreach ($places as $pl) {
                if ($this->placeOn($pl, $o) !== true) {
                    return false;
                }
            }

            return true;
        }));

        return count($hits) === 1 ? $hits[0] : null;
    }

    // ───────────────────────────── outils ─────────────────────────────

    private function merge(string $phase, int $from, int $into, string $why, ?string $traceMethod, ?object $p = null): void
    {
        if (isset($this->gone[$from]) || isset($this->gone[$into])) {
            return;
        }
        $names = DB::table('lines')->whereIn('id', [$from, $into])->get(['id', 'name', 'code'])->keyBy('id');
        $r = null;
        if (! $this->dry) {
            // 5 essais : d'autres traitements (gares, tronçons) écrivent en parallèle dans les tables
            // dérivées que la suppression de la ligne vide en cascade (interblocage possible).
            $r = DB::transaction(fn () => $this->merger->merge($from, $into, $traceMethod, "vff:merge-split-lines ({$phase}) : {$why}"), 5);
        }
        $this->gone[$from] = true;
        $this->rows[] = [
            'phase' => $phase, 'decision' => $this->dry ? 'à fusionner' : 'fusionné',
            'line_id' => $from, 'line_name' => $names[$from]->name ?? '', 'target_id' => $into,
            'target_name' => $names[$into]->name ?? '', 'target_code' => $names[$into]->code ?? '',
            'detail' => $why.($r ? sprintf(' ; %d tracé(s), %d événement(s) déplacé(s), %d en double supprimé(s)',
                $r['traces'], $r['events_moved'], $r['events_dropped']) : ''),
        ];
        $this->line(sprintf('  %s %s → %s : %s', $this->dry ? '[dry-run]' : '✓', $names[$from]->name ?? $from, $names[$into]->name ?? $into, $why));
    }

    private function row(string $phase, string $decision, object $p, string $detail): void
    {
        $this->rows[] = ['phase' => $phase, 'decision' => $decision, 'line_id' => $p->cid, 'line_name' => $p->cname,
            'target_id' => $p->lid, 'target_name' => $p->lname, 'target_code' => $p->code ?? '', 'detail' => $detail];
        if ($decision !== 'rejeté' && $this->output->isVerbose()) {
            $this->line("  ({$decision}) {$p->cname} / {$p->lname} : {$detail}");
        }
    }

    /**
     * Lieux connus d'une ligne (nom, article, alias, gares SNCF, schéma
     * Wikipédia, gares des sources), chacun = ensemble trié de mots
     * significatifs ; les noms composés « Hirson et Anor », « Saint-Maime -
     * Dauphin », « Fraisses/Unieux » sont séparés.
     *
     * @return array<string, list<string>>
     */
    private function knownPlaces(int $lineId): array
    {
        if (isset($this->known[$lineId])) {
            return $this->known[$lineId];
        }
        if (! $this->stationWords) {
            $this->stationWords = [0 => []];
            $this->stationFull = [];
            $add = function ($id, $name) {
                foreach (self::subPlaces((string) $name) as $w) {
                    $this->stationWords[(int) $id][implode(' ', $w)] = $w;
                }
                foreach (self::fullNames((string) $name) as $f) {
                    $this->stationFull[(int) $id][$f] = true;
                }
            };
            foreach (DB::select('SELECT l.id, g.libelle FROM sncf_gares g JOIN lines l ON l.code = g.code_ligne') as $r) {
                $add($r->id, $r->libelle);
            }
            foreach (DB::select('SELECT line_id, name FROM wikipedia_line_stations') as $r) {
                $add($r->line_id, $r->name);
            }
            foreach (DB::select('SELECT line_id, name FROM source_stations WHERE line_id IS NOT NULL') as $r) {
                $add($r->line_id, $r->name);
            }
        }
        $l = DB::table('lines')->where('id', $lineId)->first(['name', 'wikipedia_title', 'alt_names']);
        $places = $this->stationWords[$lineId] ?? [];
        $full = $this->stationFull[$lineId] ?? [];
        $alt = json_decode($l->alt_names ?? '{}', true) ?: [];
        foreach (array_filter([$l->name, $l->wikipedia_title, $alt['wikipedia']['name'] ?? null, $alt['lignes_oubliees']['name'] ?? null], 'is_string') as $n) {
            foreach (PlaceNames::parseLineName($n)['places'] ?? [] as $place) {
                foreach (self::subPlaces($place) as $w) {
                    $places[implode(' ', $w)] = $w;
                }
                foreach (self::fullNames($place) as $f) {
                    $full[$f] = true;
                }
            }
        }
        $this->knownFull[$lineId] = $full;

        return $this->known[$lineId] = $places;
    }

    /** @return list<list<string>> sous-lieux (mots significatifs triés) d'un toponyme composé */
    private static function subPlaces(string $place): array
    {
        $out = [];
        foreach (preg_split('/\s+(?:et|-|–)\s+|\s*[\/,]\s*/u', $place) as $part) {
            $w = array_values(array_unique(PlaceNames::significant($part)));
            if ($w) {
                sort($w);
                $out[] = $w;
            }
        }

        return $out;
    }

    /**
     * Noms complets normalisés des sous-lieux d'un toponyme composé, traits
     * d'union gardés : « Les Loges-Vaucottes-sur-Mer » → « les loges-vaucottes-sur-mer ».
     *
     * @return list<string>
     */
    private static function fullNames(string $place): array
    {
        $out = [];
        foreach (preg_split('/\s+(?:et|-|–)\s+|\s*[\/,]\s*/u', $place) as $part) {
            $f = implode('-', array_filter(array_map(PlaceNames::normalize(...), explode('-', $part)), fn (string $w) => $w !== ''));
            if ($f !== '') {
                $out[] = $f;
            }
        }

        return $out;
    }

    /**
     * Le toponyme est-il un lieu de la ligne ? Mêmes mots significatifs ; un nom
     * plus court contenu dans un lieu connu (« Les Mazes » ⊂ « Les Mazes-le-Crès »)
     * seulement s'il n'est pas lui-même une commune (« Tours » ≠ « Joué-lès-Tours »).
     * Une commune l'est pourtant si un lieu connu COMMENCE par son nom suivi d'un
     * trait d'union et n'est pas lui-même une commune : « Les Loges » est sur la
     * ligne de la gare « Les Loges-Vaucottes-sur-Mer », mais « Saint-Denis » n'est
     * pas sur celle de la commune « Saint-Denis-lès-Martel ».
     *
     * @return bool|null null : aucun mot significatif (« la frontière »)
     */
    private function placeOn(string $place, int $lineId): ?bool
    {
        $subs = self::subPlaces($place);
        if (! $subs) {
            return null;
        }
        $known = $this->knownPlaces($lineId);
        foreach ($subs as $w) {
            if (isset($known[implode(' ', $w)])) {
                return true;
            }
        }
        if ($this->communeNames === null) {
            $this->communeNames = array_flip(DB::table('communes')->pluck('nom_norm')->all());
        }
        if (! isset($this->communeNames[PlaceNames::normalize($place)])) {
            foreach ($subs as $w) {
                foreach ($known as $k) {
                    if (count($k) > count($w) && ! array_diff($w, $k)) {
                        return true;
                    }
                }
            }

            return false;
        }
        foreach (self::fullNames($place) as $p) {
            foreach (array_keys($this->knownFull[$lineId] ?? []) as $k) {
                if (str_starts_with($k, $p.'-') && ! isset($this->communeNames[str_replace('-', ' ', $k)])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Tous les toponymes du nom (extrémités) sont des lieux connus de la ligne. */
    private function namePlacesOn(string $name, int $lineId, bool $allowEmpty = false): bool
    {
        $parsed = PlaceNames::parseLineName($name);
        if (! $parsed) {
            return false;
        }
        foreach ($parsed['places'] as $place) {
            $on = $this->placeOn($place, $lineId);
            if ($on === false || ($on === null && ! $allowEmpty)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Distance (m) entre un toponyme (localisé à ≤ 10 km de la ligne créée) et
     * la plus proche extrémité nommée de la ligne à code (communes, gares).
     */
    private function distanceToLineEnds(string $place, int $lineId, int $createdId): ?float
    {
        $this->ensureGazetteer();
        $l = DB::table('lines')->where('id', $lineId)->first(['name', 'wikipedia_title']);
        $ends = [];
        foreach (array_filter([$l->name, $l->wikipedia_title]) as $n) {
            foreach (array_slice(PlaceNames::parseLineName($n)['places'] ?? [], 0, 2) as $e) {
                $ends[] = PlaceNames::normalize($e);
                foreach (preg_split('/\s+(?:et|-|–)\s+|\s*[\/,]\s*/u', $e) as $part) {
                    $ends[] = PlaceNames::normalize($part);
                }
            }
        }
        $ends = array_values(array_unique(array_filter($ends)));
        if (! $ends) {
            return null;
        }
        $d = DB::selectOne(
            'WITH c AS (SELECT g FROM lm_c WHERE id = ?),
                  a AS (SELECT z.g FROM lm_gaz z, c WHERE z.norm = ? AND ST_DWithin(z.g, c.g, 10000)),
                  b AS (SELECT g FROM lm_gaz WHERE norm IN ('.implode(',', array_fill(0, count($ends), '?')).'))
             SELECT MIN(ST_Distance(a.g, b.g)) AS d FROM a, b',
            array_merge([$createdId, PlaceNames::normalize($place)], $ends),
        );

        return $d?->d !== null ? (float) $d->d : null;
    }

    /** lm_gaz : communes (actuelles et anciennes) et gares SNCF, noms normalisés, Lambert-93. */
    private function ensureGazetteer(): void
    {
        if (DB::selectOne("SELECT to_regclass('pg_temp.lm_gaz') AS t")->t) {
            return;
        }
        DB::statement('CREATE TEMP TABLE lm_gaz AS SELECT nom_norm AS norm, ST_Transform(geom, 2154) AS g FROM communes');
        foreach (array_chunk(DB::select('SELECT DISTINCT libelle, lat, lon FROM sncf_gares WHERE lat IS NOT NULL AND libelle IS NOT NULL'), 500) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as $g) {
                $values[] = '(?, ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 2154))';
                array_push($bind, PlaceNames::normalize($g->libelle), $g->lon, $g->lat);
            }
            DB::statement('INSERT INTO lm_gaz VALUES '.implode(',', $values), $bind);
        }
        DB::statement('CREATE INDEX ON lm_gaz (norm)');
    }

    /** Le titre cite-t-il tous les toponymes (mots significatifs) du nom de la ligne ? */
    private function titleCites(string $title, string $name): bool
    {
        $t = PlaceNames::significant(preg_replace('/\s*\([^)]*\)/u', '', $title));
        $parsed = PlaceNames::parseLineName($name);
        $places = $parsed ? array_slice($parsed['places'], 0, 2) : [$name];
        foreach ($places as $p) {
            $w = PlaceNames::significant($p);
            if ($w && ! array_intersect($w, $t)) {
                return false;
            }
        }

        return true;
    }

    private function tkey(string $title): string
    {
        return PlaceNames::normalize(str_replace('_', ' ', $title));
    }

    private function writeCsv(): void
    {
        $dir = storage_path('app/reports');
        @mkdir($dir, 0775, true);
        $path = $dir.'/merge-split-lines'.($this->dry ? '-dry-run' : '').'.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, ['phase', 'decision', 'line_id', 'line_name', 'target_id', 'target_name', 'target_code', 'detail']);
        foreach ($this->rows as $r) {
            fputcsv($fh, CsvSafe::row([$r['phase'], $r['decision'], $r['line_id'], $r['line_name'], $r['target_id'], $r['target_name'], $r['target_code'], $r['detail']]));
        }
        fclose($fh);
        $counts = [];
        foreach ($this->rows as $r) {
            $counts[$r['phase'].' : '.$r['decision']] = ($counts[$r['phase'].' : '.$r['decision']] ?? 0) + 1;
        }
        ksort($counts);
        $this->table(['Passe : décision', 'Lignes'], array_map(fn ($k, $v) => [$k, $v], array_keys($counts), $counts));
        $this->line('  détail : '.$path);
    }
}
