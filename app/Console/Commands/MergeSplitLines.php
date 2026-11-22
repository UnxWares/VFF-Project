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
 *        première section de « Mézidon à Trouville-Deauville », 2026-10-19), ou la
 *        section est nommée dans l'autre sens : son origine est l'arrivée de la ligne
 *        (« Abbeville à Saint-Pol », section de « Fives à Abbeville ») ou son arrivée en
 *        est l'origine (« Bressuire à Parthenay », section de « Neuville-de-Poitou à
 *        Bressuire », 2026-11-22) ;
 *      - le tracé de la ligne existante est incomplet (< 85 % de sa longueur officielle) ;
 *      - la ligne créée touche une EXTRÉMITÉ de ce tracé (< 300 m) ;
 *      - les deux longueurs additionnées retrouvent la longueur officielle (± 15 %) ;
 *      - l'autre extrémité de la section (celle que les deux lignes ne partagent pas)
 *        est un lieu de la ligne existante (nom, gares).
 *    « au » et « aux » valent « à le » et « à les » dans les deux noms (« Ligne de
 *    Port-de-Piles au Blanc », section de « Port-de-Piles à Argenton-sur-Creuse »).
 *
 *    Section SANS extrémité partagée (section du milieu, ou nom de gare en origine :
 *    « Montauban » pour « Montauban-Ville-Bourbon », 2026-11-22), fusionnée dans une
 *    ligne à code seulement si TOUT est vrai :
 *      - un bout de la section est à ≤ 1 km du tracé connu de la ligne, et au plus
 *        10 % de la section le recouvre (à 30 m) ;
 *      - le tracé est incomplet : < 85 % de la longueur officielle, les deux
 *        longueurs additionnées en font 70 à 115 %, et la section comble au moins
 *        la moitié du manque ;
 *      - une preuve que la section est sur la ligne :
 *          a. ses deux toponymes sont des lieux de la ligne (nom, gares, portions
 *             des événements Wikipédia, lignes-oubliées ou corrections ; préfixe de
 *             nom de gare compris) ;
 *          b. ou un seul l'est, mais il est au bout de la section qui ne touche pas
 *             le tracé connu (≤ 3 km) et loin de ce tracé (> 1 km), l'autre bout
 *             touchant ce tracé : la section prolonge la ligne jusqu'à un de ses lieux ;
 *          c. ou un toponyme l'est et au moins trois gares rattachées à la ligne par
 *             une source (SNCF, Wikidata, schéma) sont sur la section, à plus de 1 km
 *             du tracé connu ;
 *      - quand les deux toponymes ont un PK sur la ligne, leur écart vaut 80 à 125 %
 *        de la longueur de la section (ordre des PK) ;
 *      - une seule ligne à code remplit tout (sinon « à relire »).
 *    Sans longueur officielle, seule la preuve a, avec l'écart des PK vérifié, suffit.
 *    Ce qu'elle refuse : une autre ligne qui part d'une gare de la ligne ou qui
 *    aboutit à son tracé (« Mortagne à La Loupe » contre « La Loupe à Prey »,
 *    « Thionville à Fontoy » contre « Metz à Zoufftgen ») : le lieu commun y est le
 *    bout qui touche le tracé, l'autre n'est pas un lieu de la ligne.
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

    /** Distance (m) à laquelle un bout de section « touche » le tracé connu d'une ligne (sections sans extrémité partagée). */
    private const SECTION_TOUCH_M = 1000;

    /** Distance (m) entre le bout libre d'une section et le lieu de la ligne qui la nomme (prolongement). */
    private const SECTION_PLACE_M = 3000;

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

    /** @var array<int, array{0: array<string, list<string>>, 1: array<string, true>}> lieux des portions d'événements, par ligne (cache) */
    private array $eventPlaces = [];

    /** @var array<int, list<object>> gares à PK de chaque ligne (cache) */
    private array $stationPks = [];

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
        $this->eventPlaces = [];
        $this->stationPks = [];
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
        // « au » et « aux » contractent « à le » et « à les » : ramenés à « à » pour le découpage, dans les deux noms
        // (« Ligne de Port-de-Piles au Blanc », « Ligne de Laon au Cateau »), seulement quand le nom n'a pas de « à ».
        $named = "CASE WHEN l.name LIKE '% à %' THEN l.name
                       WHEN l.name LIKE '% aux %' THEN regexp_replace(l.name, '\\s+aux\\s+', ' à les ')
                       WHEN l.name LIKE '% au %' THEN regexp_replace(l.name, '\\s+au\\s+', ' à le ')
                       ELSE l.name END";
        // La ligne existante part de $place (« Ligne de/d'/du/des/de la $place à … ») ou y arrive.
        $from = fn (string $place) => "(e.uname LIKE 'ligne de ' || {$place} || ' a %' OR e.uname LIKE 'ligne d''' || {$place} || ' a %'
                OR e.uname LIKE 'ligne du ' || {$place} || ' a %' OR e.uname LIKE 'ligne des ' || {$place} || ' a %'
                OR e.uname LIKE 'ligne de la ' || {$place} || ' a %')";
        $to = fn (string $place) => "e.uname LIKE '% a ' || {$place} || '%'";
        $pairs = DB::select(<<<SQL
            WITH created AS (
                SELECT l.id, l.name, r.geom, r.length_m,
                       lower(unaccent(split_part({$named}, ' à ', 2))) AS dest,
                       lower(unaccent(regexp_replace(split_part({$named}, ' à ', 1), '^Ligne (de la |de l''|du |des |de |d'')', ''))) AS orig
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.slug LIKE 'archeo-%' AND l.code IS NULL AND {$named} LIKE '% à %'
            ),
            existing AS (
                SELECT l.id, l.name, l.length_official_m AS off, r.geom, r.length_m,
                       lower(unaccent({$named})) AS uname
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.slug NOT LIKE 'archeo-%' AND l.length_official_m > 0
                  AND r.length_m < 0.85 * l.length_official_m
            )
            SELECT DISTINCT ON (c.id) c.id AS created_id, c.name AS created_name, ROUND(c.length_m / 1000.0, 1) AS created_km,
                   e.id AS target_id, e.name AS target_name, ROUND(e.length_m / 1000.0, 1) AS target_km, ROUND(e.off / 1000.0, 1) AS official_km,
                   -- extrémité partagée : même terminus, même origine, puis section nommée dans l'autre sens
                   CASE WHEN c.dest <> '' AND {$to('c.dest')} THEN 'dest'
                        WHEN c.orig <> '' AND {$from('c.orig')} THEN 'orig'
                        WHEN c.orig <> '' AND {$to('c.orig')} THEN 'orig_dest'
                        ELSE 'dest_orig' END AS link
            FROM created c JOIN existing e
              ON abs((e.length_m + c.length_m) - e.off) < 0.15 * e.off
             AND ((c.dest <> '' AND {$to('c.dest')})
                  -- même ORIGINE (« Mézidon à Dives », première section de « Mézidon à Trouville-Deauville »)
                  OR (c.orig <> '' AND {$from('c.orig')})
                  -- autre sens : l'origine de la section est l'arrivée de la ligne (« Abbeville à Saint-Pol »,
                  -- section de « Fives à Abbeville »), ou son arrivée en est l'origine
                  OR (c.orig <> '' AND {$to('c.orig')})
                  OR (c.dest <> '' AND {$from('c.dest')}))
             AND ST_DWithin(ST_Boundary(e.geom)::geography, c.geom::geography, 300)
            ORDER BY c.id, CASE WHEN c.dest <> '' AND {$to('c.dest')} THEN 0 WHEN c.orig <> '' AND {$from('c.orig')} THEN 1 ELSE 2 END,
                     abs((e.length_m + c.length_m) - e.off)
            SQL);

        $this->info('→ 1. Sections créées à part : '.count($pairs));
        $rejected = [];
        foreach ($pairs as $p) {
            // L'autre extrémité de la section doit être un lieu de la ligne (nom, gares) : sinon c'est une autre
            // ligne qui aboutit au même terminus (« Armentières à Halluin » n'est pas une section de Somain à Halluin),
            // qui part de la même gare (« Chalon-sur-Saône à Mâcon » n'est pas une section de Chalon à Bourg) ou qui
            // part de son terminus (« Argenton-sur-Creuse à La Châtre » n'est pas une section de Port-de-Piles à Argenton).
            $places = PlaceNames::parseLineName($p->created_name)['places'] ?? [];
            $otherIsOrigin = in_array($p->link, ['dest', 'dest_orig'], true);
            $other = $otherIsOrigin ? ($places[0] ?? '') : ($places[1] ?? '');
            if ($this->placeOn($other, (int) $p->target_id) !== true) {
                $shared = match ($p->link) {
                    'dest' => 'même terminus seulement',
                    'orig' => 'même origine seulement',
                    'orig_dest' => 'part du terminus seulement',
                    default => 'arrive à l\'origine seulement',
                };
                $this->row('sections', 'rejeté', (object) ['cid' => $p->created_id, 'cname' => $p->created_name, 'lid' => $p->target_id,
                    'lname' => $p->target_name, 'code' => ''], ($otherIsOrigin ? 'origine' : 'destination')." « {$other} » étrangère à la ligne ({$shared})");
                $rejected[(int) $p->created_id] = array_key_last($this->rows);

                continue;
            }
            $this->mergeSection((int) $p->created_id, (int) $p->target_id, $p->created_name,
                "section {$p->created_km} km + tracé {$p->target_km} km ≈ {$p->official_km} km officiels");
        }
        $this->sectionsWithoutSharedEnd($rejected);
    }

    /** Fusion d'une section dans sa ligne à code, son nom gardé dans alt_names.sections de la ligne. */
    private function mergeSection(int $createdId, int $targetId, string $createdName, string $why): void
    {
        $this->merge('sections', $createdId, $targetId, $why, 'merged_section');
        if (! $this->dry) {
            DB::update(
                "UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb)
                    || jsonb_build_object('sections', COALESCE(alt_names->'sections', '[]'::jsonb) || to_jsonb(?::text)), updated_at = NOW()
                 WHERE id = ?",
                [$createdName, $targetId],
            );
        }
    }

    /**
     * Sections sans extrémité partagée avec la ligne à code (règles dans le docblock de la classe) : section du
     * milieu (« Breteuil à Senonches » dans « La Loupe à Prey »), nom de gare en origine (« Montauban à
     * Saint-Sulpice » dans « Montauban-Ville-Bourbon à La Crémade »), prolongement jusqu'à un lieu de la ligne
     * (« Souillac à Saint-Denis-près-Martel » dans « Souillac à Viescamp-sous-Jallès »).
     *
     * @param  array<int, int>  $rejected  lignes refusées par la règle des extrémités partagées → indice de leur ligne du rapport
     */
    private function sectionsWithoutSharedEnd(array $rejected): void
    {
        $touch = self::SECTION_TOUCH_M;
        $candidates = DB::select(<<<SQL
            WITH created AS (
                SELECT l.id, l.name, r.geom, r.length_m
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.slug LIKE 'archeo-%' AND l.code IS NULL AND r.length_m > 0
            ),
            pts AS (SELECT c.id, (ST_Dump(ST_Boundary(c.geom))).geom AS g FROM created c),
            -- bouts de la section : les deux extrémités de ses morceaux les plus éloignées l'une de l'autre
            ends AS (
                SELECT DISTINCT ON (a.id) a.id, a.g AS ga, b.g AS gb
                FROM pts a JOIN pts b ON b.id = a.id
                ORDER BY a.id, ST_Distance(a.g::geography, b.g::geography) DESC
            ),
            existing AS (
                SELECT l.id, l.name, l.length_official_m AS off, r.geom, r.length_m
                FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
                WHERE l.code IS NOT NULL AND l.slug NOT LIKE 'archeo-%'
                  AND (l.length_official_m IS NULL OR r.length_m < 0.85 * l.length_official_m)
            )
            SELECT c.id AS created_id, c.name AS created_name, c.length_m AS created_m,
                   e.id AS target_id, e.name AS target_name, e.length_m AS target_m, e.off,
                   ST_AsText(n.ga) AS ga, ST_AsText(n.gb) AS gb,
                   ST_Distance(n.ga::geography, e.geom::geography) AS da, ST_Distance(n.gb::geography, e.geom::geography) AS db
            FROM created c JOIN ends n ON n.id = c.id
            JOIN existing e ON (e.geom && ST_Expand(n.ga, 0.02) AND ST_DWithin(n.ga::geography, e.geom::geography, {$touch}))
                            OR (e.geom && ST_Expand(n.gb, 0.02) AND ST_DWithin(n.gb::geography, e.geom::geography, {$touch}))
            WHERE e.off IS NULL OR c.length_m + e.length_m BETWEEN 0.70 * e.off AND 1.15 * e.off
            ORDER BY c.id, e.id
            SQL);

        $proven = [];
        foreach ($candidates as $p) {
            $cid = (int) $p->created_id;
            if (isset($this->gone[$cid])) {
                continue;
            }
            $verdict = $this->sectionProof($p);
            if (is_array($verdict)) {
                $proven[$cid][] = [$p, $verdict['detail']];
            } elseif ($verdict !== null && ! isset($rejected[$cid])) {
                $this->row('sections', 'rejeté', (object) ['cid' => $cid, 'cname' => $p->created_name, 'lid' => $p->target_id,
                    'lname' => $p->target_name, 'code' => ''], $verdict);
            }
        }
        $this->info('→ 1 bis. Sections sans extrémité partagée prouvées : '.count($proven));
        $replaced = [];
        foreach ($proven as $cid => $hits) {
            if (count($hits) > 1) {
                foreach ($hits as [$p, $detail]) {
                    $this->row('sections', 'à relire', (object) ['cid' => $cid, 'cname' => $p->created_name, 'lid' => $p->target_id,
                        'lname' => $p->target_name, 'code' => ''], 'plusieurs lignes à code possibles ; '.$detail);
                }

                continue;
            }
            [$p, $detail] = $hits[0];
            if (isset($rejected[$cid])) {
                // Refusée par la règle des extrémités partagées, prouvée ici : la ligne du rapport est remplacée.
                $replaced[$rejected[$cid]] = true;
            }
            $this->mergeSection($cid, (int) $p->target_id, $p->created_name, $detail);
        }
        $this->rows = array_values(array_diff_key($this->rows, $replaced));
    }

    /**
     * Preuve qu'une section sans extrémité partagée est sur la ligne à code.
     *
     * @return array{detail: string}|string|null preuve, motif de refus (au moins un toponyme sur la ligne), ou null
     *                                           (aucun toponyme de la ligne : autre ligne, sans ligne au rapport)
     */
    private function sectionProof(object $p): array|string|null
    {
        $lineId = (int) $p->target_id;
        $places = array_slice(PlaceNames::parseLineName($p->created_name)['places'] ?? [], 0, 2);
        if (count($places) < 2) {
            return null;
        }
        $named = array_map(fn (string $place) => $this->placeOnLine($place, $lineId), $places);
        if (! in_array(true, $named, true)) {
            return null;
        }
        $km = fn (float $m) => number_format($m / 1000, 1, '.', '');
        $lengths = "section {$km((float) $p->created_m)} km + tracé {$km((float) $p->target_m)} km"
            .($p->off ? " ≈ {$km((float) $p->off)} km officiels" : ' (sans longueur officielle)');

        // La section comble au moins la moitié du manque : un court morceau près d'une gare de la ligne (« Thionville
        // à Basse-Yutz », « Rennes à Cesson-Sévigné ») est plutôt un embranchement ou une autre ligne.
        if ($p->off && (float) $p->created_m < 0.5 * ((float) $p->off - (float) $p->target_m)) {
            return 'section plus courte que la moitié du tracé manquant';
        }

        // Ordre des PK : l'écart des PK des deux toponymes doit retrouver la longueur de la section.
        $pk = $this->pkSpan($places, $lineId, (float) $p->created_m);
        if ($pk === false) {
            return 'écart des PK des deux toponymes incompatible avec la longueur de la section';
        }

        $touchA = (float) $p->da <= self::SECTION_TOUCH_M;
        $touchB = (float) $p->db <= self::SECTION_TOUCH_M;
        $proof = null;
        if ($named[0] === true && $named[1] === true) {
            $proof = 'deux toponymes sur la ligne';
        } elseif (! $p->off) {
            // Sans longueur officielle, seule la preuve par les deux toponymes vaut : le plus souvent une autre ligne
            // qui part d'une gare de la ligne (raccordements), pas de ligne au rapport.
            return null;
        } elseif ($touchA !== $touchB) {
            // Prolongement : le toponyme de la ligne est au bout qui ne touche pas le tracé connu.
            $place = $named[0] === true ? $places[0] : $places[1];
            $far = $touchA ? $p->gb : $p->ga;
            if ($this->placeNear($place, $far, self::SECTION_PLACE_M) && ! $this->placeNearLine($place, $lineId, self::SECTION_TOUCH_M)) {
                $proof = "prolonge le tracé connu jusqu'à « {$place} »";
            }
        }
        if ($proof === null) {
            $stations = $this->stationsOnSection((int) $p->created_id, $lineId);
            if (count($stations) >= 3) {
                $proof = count($stations).' gares de la ligne sur la section, loin du tracé connu ('.implode(', ', array_slice($stations, 0, 6)).')';
            }
        }
        if ($proof === null) {
            $other = $named[0] === true ? $places[1] : $places[0];
            $kept = $named[0] === true ? $places[0] : $places[1];

            return "« {$other} » étrangère à la ligne, « {$kept} » au bout qui touche son tracé ou déjà sur ce tracé";
        }

        $overlap = (float) DB::selectOne(
            'SELECT ST_Length(ST_Intersection(c.geom, ST_Buffer(e.geom::geography, 30)::geometry)::geography) AS m
             FROM line_reference_geometries c, line_reference_geometries e WHERE c.line_id = ? AND e.line_id = ?',
            [$p->created_id, $lineId],
        )->m;
        if ($overlap > 0.10 * (float) $p->created_m) {
            return sprintf('recouvre le tracé connu sur %d %%', round(100 * $overlap / (float) $p->created_m));
        }
        if ($pk === null && ! $p->off) {
            return 'sans longueur officielle ni PK des deux toponymes';
        }

        return ['detail' => "section sans extrémité partagée : {$lengths} ; {$proof}"
            .($pk !== null ? " ; PK {$pk}" : '')];
    }

    /** Toponyme sur la ligne : lieux connus (placeOn) ou portions des événements tirés d'un texte. */
    private function placeOnLine(string $place, int $lineId): bool
    {
        if ($this->placeOn($place, $lineId) === true) {
            return true;
        }
        if (! isset($this->eventPlaces[$lineId])) {
            $known = [];
            $full = [];
            $rows = DB::select("SELECT DISTINCT section_from AS a, section_to AS b FROM line_events
                WHERE line_id = ? AND source IN ('wikipedia', 'lignes-oubliees', 'manual')
                  AND (section_from IS NOT NULL OR section_to IS NOT NULL)", [$lineId]);
            foreach ($rows as $r) {
                foreach (array_filter([$r->a, $r->b], 'is_string') as $n) {
                    foreach (self::subPlaces($n) as $w) {
                        $known[implode(' ', $w)] = $w;
                    }
                    foreach (self::fullNames($n) as $f) {
                        $full[$f] = true;
                    }
                }
            }
            $this->eventPlaces[$lineId] = [$known, $full];
        }

        return $this->placeIn($place, ...$this->eventPlaces[$lineId]) === true;
    }

    /**
     * Écart des PK des deux toponymes sur la ligne (gares SNCF, schéma Wikipédia, gares des sources), comparé à la
     * longueur de la section : texte « a → b km », false si aucun couple de PK ne donne 80 à 125 % de la longueur,
     * null si un toponyme n'a pas de PK.
     *
     * @param  list<string>  $places
     */
    private function pkSpan(array $places, int $lineId, float $sectionM): string|false|null
    {
        if (! isset($this->stationPks[$lineId])) {
            $this->stationPks[$lineId] = DB::select(
                'SELECT g.libelle AS name, g.pk_m FROM sncf_gares g JOIN lines l ON l.code = g.code_ligne WHERE l.id = ? AND g.pk_m IS NOT NULL
                 UNION SELECT name, pk_m FROM wikipedia_line_stations WHERE line_id = ? AND pk_m IS NOT NULL
                 UNION SELECT name, pk_m FROM source_stations WHERE line_id = ? AND pk_m IS NOT NULL',
                [$lineId, $lineId, $lineId],
            );
        }
        $pks = [];
        foreach ($places as $i => $place) {
            foreach ($this->stationPks[$lineId] as $s) {
                $known = [];
                foreach (self::subPlaces((string) $s->name) as $w) {
                    $known[implode(' ', $w)] = $w;
                }
                if ($this->placeIn($place, $known, array_fill_keys(self::fullNames((string) $s->name), true)) === true) {
                    $pks[$i][] = (int) $s->pk_m;
                }
            }
        }
        if (! isset($pks[0], $pks[1])) {
            return null;
        }
        $best = null;
        foreach ($pks[0] as $a) {
            foreach ($pks[1] as $b) {
                $ratio = abs($a - $b) / $sectionM;
                if ($ratio >= 0.80 && $ratio <= 1.25 && ($best === null || abs($ratio - 1) < abs($best[2] - 1))) {
                    $best = [$a, $b, $ratio];
                }
            }
        }

        return $best ? sprintf('%.1f → %.1f km', $best[0] / 1000, $best[1] / 1000) : false;
    }

    /** Un lieu de ce nom (commune, gare SNCF) est-il à moins de $meters du point (WKT, WGS 84) ? */
    private function placeNear(string $place, string $pointWkt, int $meters): bool
    {
        $this->ensureGazetteer();

        return (bool) DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM lm_gaz WHERE norm = ? AND ST_DWithin(g, ST_Transform(ST_GeomFromText(?, 4326), 2154), ?)) AS ok',
            [PlaceNames::normalize($place), $pointWkt, $meters],
        )->ok;
    }

    /** Un lieu de ce nom (commune, gare SNCF) est-il déjà à moins de $meters du tracé connu de la ligne ? */
    private function placeNearLine(string $place, int $lineId, int $meters): bool
    {
        $this->ensureGazetteer();

        return (bool) DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM lm_gaz z, line_reference_geometries r WHERE r.line_id = ? AND z.norm = ?
                AND ST_DWithin(z.g, ST_Transform(r.geom, 2154), ?)) AS ok',
            [$lineId, PlaceNames::normalize($place), $meters],
        )->ok;
    }

    /**
     * Gares rattachées à la ligne par une source (PK SNCF, Wikidata, schéma), à ≤ 200 m de la section et à plus de
     * 1 km du tracé connu de la ligne.
     *
     * @return list<string>
     */
    private function stationsOnSection(int $createdId, int $lineId): array
    {
        return array_column(DB::select(
            "SELECT DISTINCT s.name FROM station_lines sl
             JOIN stations s ON s.id = sl.station_id
             JOIN line_reference_geometries c ON c.line_id = ?
             JOIN line_reference_geometries e ON e.line_id = sl.line_id
             WHERE sl.line_id = ? AND sl.method IN ('sncf_pk', 'wikidata_p81', 'source_pk')
               AND ST_DWithin(s.geom::geography, c.geom::geography, 200)
               AND NOT ST_DWithin(s.geom::geography, e.geom::geography, 1000)
             ORDER BY s.name",
            [$createdId, $lineId],
        ), 'name');
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
        $known = $this->knownPlaces($lineId);

        return $this->placeIn($place, $known, $this->knownFull[$lineId] ?? []);
    }

    /**
     * Le toponyme est-il un des lieux donnés (mots significatifs, noms complets) ?
     * Règles de placeOn().
     *
     * @param  array<string, list<string>>  $known
     * @param  array<string, true>  $knownFull
     */
    private function placeIn(string $place, array $known, array $knownFull): ?bool
    {
        $subs = self::subPlaces($place);
        if (! $subs) {
            return null;
        }
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
            foreach (array_keys($knownFull) as $k) {
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
