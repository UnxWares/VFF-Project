<?php

namespace App\Services\TrackLayout;

use App\Models\Line;
use App\Services\LineStations;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schéma des voies d'une ligne (`GET /api/v1/lines/{line}/track-layout`, GraphQL `Line.track_layout`, outil MCP
 * `line_track_layout` ; contrat agents-commun/contrat-schema-voies.md), à la manière d'un « routebook » : voies
 * numérotées, voies d'évitement et de service, aiguillages, traversées, signaux, points d'exploitation, bande des
 * vitesses et des déclivités, chaque objet avec sa provenance. Réseau d'aujourd'hui (ère courante) seulement.
 *
 * SOURCES, par ordre de préférence (TrackLayoutSource, clé = `source` des objets) :
 * 1. `rinf` : RINF de l'ERA (RinfTrackLayoutSource, tables rinf_*, vff:import-rinf) ;
 * 2. `osm` : OpenStreetMap (géométrie des voies et aiguillages là où le RINF manque), branché par l'agent
 *    « osm-voies » : une classe TrackLayoutSource de clé `osm`, étiquetée TAG dans le conteneur
 *    (`$this->app->tag([OsmTrackLayoutSource::class], TrackLayout::TAG)` dans AppServiceProvider) ;
 * 3. `vff` : données déjà dans VFF (PK sourcés des gares, vitesses de SNCF Réseau, déclivités du profil en long,
 *    passages à niveau de SNCF Réseau), VffTrackLayoutSource.
 *
 * RÈGLES DE FUSION (une source plus loin dans l'ordre ne fait que compléter) :
 * - voies (décision du 24/11/2026, avec osm-voies) : sur une portion `tracks` couverte par une source préférée, ses
 *   voies `running` priment et celles d'une source suivante sont écartées ; les autres voies (évitement, service,
 *   faisceau, liaison…) d'une source suivante sont gardées. Une voie `running` d'une source suivante n'est jamais
 *   écartée quand un de ses objets la cite (aiguillage, signal, heurtoir, traversée) ou quand elle est une voie d'un
 *   point d'exploitation de la source préférée (même numéro au sens de sameNumber, dans sa zone) : elle longe alors
 *   un quai ou porte la voie d'une gare (Paris-Nord, 25/11/2026 : les parties à quai des voies 3 à 21 et 30 à 36
 *   disparaissaient, 89 signaux et 14 heurtoirs citaient une voie absente). À un point d'exploitation dont la source
 *   préférée n'a que des voies `approximate` (voies d'une gare du RINF centrées sur son PK), des voies de service
 *   exactes ou déduites d'une source suivante, et les voies de même numéro (à OP_ZONE_M près), les remplacent ; une
 *   voie remplaçante de même numéro qu'une seule voie remplacée garde son identifiant et sa référence RINF
 *   (`rinf_id`, `rinf_ref`), le point (`op_id`) et ses quais (`platform`) ;
 * - même voie décrite deux fois (absorbDuplicates) : une voie de ligne exacte du RINF (identifiant officiel, PK de
 *   ses sections) absorbe les voies d'OSM et les voies de gare approchées du RINF de même numéro (« 1bis » et
 *   « V1BIS », « DO » et « VDO », ou le nom que lui donne un quai : « 3 » de « Quai V1/V1BIS » à Brétigny) qui la
 *   recouvrent sur au moins la moitié de leur longueur, et les voies principales d'OSM sans numéro
 *   posées au même endroit (décalage latéral à SAME_PLACE_M près) ; elle prend le décalage latéral d'OSM (ordre
 *   réel des voies), les quais des voies absorbées, et les aiguillages, signaux, heurtoirs et traversées des voies
 *   absorbées la citent ;
 * - aiguillages, signaux, traversées, heurtoirs, bornes kilométriques : gardés hors des portions couvertes de leur
 *   famille par une source préférée, et sauf doublon d'un objet d'une source préférée (même famille, même
 *   référence au sens de sameRef, « PN 1 » et « 1 », ou référence absente, à DEDUPE_M près) ; dans une même
 *   source, seule la même référence au même endroit fait un doublon ;
 * - points d'exploitation : gardés sauf s'ils doublent un point déjà retenu (même gare de VFF, ou à OP_MERGE_M près) ;
 * - vitesses et déclivités : rognées aux trous de la bande déjà remplie.
 * Puis les rangées des voies (LaneAssigner : les voies d'un même quai voisines, de part et d'autre de lui), le tri
 * par PK, la couverture (`coverage`) et les sources utilisées.
 *
 * Clés de travail des voies (retirées de la réponse) : `offset_m` et `connects` (OSM, rangées), `platform_keys`
 * (quais que la voie borde, « point|quai », RINF) et `aliases` (autres numéros de la voie, tirés du nom d'un quai).
 *
 * Une source dont une table manque (migration pas encore passée) est ignorée : 200 et ce que disent les autres.
 *
 * Paramètres : portion [from, to] en mètres (objets qui la touchent ; bornes nulles : toute la ligne), familles
 * à servir (`kinds`). Une ligne sans donnée rend 200, des listes vides et la couverture (jamais une erreur).
 */
final class TrackLayout
{
    /** Étiquette du conteneur des sources supplémentaires (OSM). */
    public const TAG = 'vff.track-layout.sources';

    /** Ordre de préférence des sources, par clé ; une clé inconnue passe après. */
    public const PRIORITY = ['rinf', 'osm', 'vff'];

    /** Familles que l'on peut demander (`kinds`). */
    public const KINDS = ['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'speeds', 'gradients'];

    /** Familles comptées dans `counts`. */
    public const COUNTED = ['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones'];

    /** Familles dont les trous sont listés dans `coverage.missing`. */
    public const COVERAGE_FAMILIES = ['tracks', 'switches', 'signals', 'speeds', 'gradients'];

    /** Deux objets ponctuels d'une même famille à moins de cette distance (m) et de même référence : doublon. */
    public const DEDUPE_M = 30;

    /** Marge (m) autour des voies approchées d'un point d'exploitation où une voie de service d'une autre source les remplace. */
    public const OP_ZONE_M = 300;

    /** Part de la plus courte de deux voies qu'elles doivent partager en PK pour être la même voie. */
    public const SAME_TRACK_SHARE = 0.5;

    /** Écart latéral (m) sous lequel deux voies sans numéro au même endroit sont la même voie (entraxe : 4 m au moins). */
    public const SAME_PLACE_M = 1.5;

    /** Taille (m) des cases où sont rangées les zones des points d'exploitation pendant la fusion des voies. */
    private const ZONE_CELL_M = 1000;

    /** Deux points d'exploitation à moins de cette distance (m) : le second double le premier. */
    public const OP_MERGE_M = 150;

    /** Champs de chaque famille, dans l'ordre de la réponse (contrat, puis ajouts) ; un champ absent vaut null. */
    public const FIELDS = [
        'sources' => ['key', 'label', 'licence', 'dataset', 'fetched_at', 'url', 'attribution', 'licence_url'],
        'operational_points' => ['id', 'name', 'kind', 'pk_m', 'uopid', 'station_slug', 'rinf_type', 'source', 'precision'],
        'tracks' => ['id', 'ref', 'rinf_ref', 'rinf_id', 'kind', 'pk_from_m', 'pk_to_m', 'lane', 'op_id', 'station_slug', 'length_m',
            'electrified', 'platform', 'direction', 'max_speed_kmh', 'source', 'precision'],
        'switches' => ['id', 'ref', 'pk_m', 'kind', 'from_track', 'to_track', 'facing', 'source', 'precision'],
        'signals' => ['id', 'ref', 'pk_m', 'kind', 'form', 'track', 'direction', 'source', 'precision'],
        'crossings' => ['id', 'pk_m', 'kind', 'ref', 'use', 'tracks', 'source', 'precision'],
        'buffer_stops' => ['id', 'pk_m', 'track', 'source', 'precision'],
        'milestones' => ['id', 'pk_m', 'label', 'source', 'precision'],
    ];

    /** Clés de travail des sources, retirées de la réponse. */
    private const INTERNAL = ['parent_ref', 'offset_m', 'connects', 'platform_keys', 'aliases'];

    /** Codes SQLSTATE d'une table ou d'une colonne absente (PostgreSQL : undefined_table, undefined_column). */
    private const MISSING_RELATION = ['42P01', '42703'];

    /** Version de la forme de la réponse (à changer avec elle). */
    public const FORMAT = 1;

    /** @var list<TrackLayoutSource> */
    private array $sources;

    /** @param  iterable<TrackLayoutSource>  $sources */
    public function __construct(iterable $sources)
    {
        $list = [];
        foreach ($sources as $source) {
            $list[$source->key()] = $source;
        }
        uksort($list, fn (string $a, string $b): int => self::rank($a) <=> self::rank($b) ?: strcmp($a, $b));
        $this->sources = array_values($list);
    }

    /** @return list<string> clés des sources branchées, dans l'ordre de préférence */
    public function sourceKeys(): array
    {
        return array_map(fn (TrackLayoutSource $s): string => $s->key(), $this->sources);
    }

    /**
     * Schéma de la ligne, au format du contrat.
     *
     * @param  list<string>|null  $kinds  familles à servir (null : toutes)
     * @return array<string, mixed>
     */
    public function forLine(Line $line, ?int $fromM = null, ?int $toM = null, ?array $kinds = null): array
    {
        if ($fromM !== null && $toM !== null && $fromM > $toM) {
            [$fromM, $toM] = [$toM, $fromM];
        }
        $kinds = $kinds === null ? self::KINDS : array_values(array_intersect(self::KINDS, $kinds));

        $parts = [];
        foreach ($this->sources as $source) {
            $part = $this->read($source, $line, $fromM, $toM);
            if (! $part->isEmpty()) {
                $parts[$source->key()] = $part;
            }
        }

        $merged = array_fill_keys(TrackLayoutPart::FAMILIES, []);
        $covered = array_fill_keys(TrackLayoutPart::FAMILIES, []);
        $coveredBy = [];
        $dropped = [];
        foreach ($parts as $key => $part) {
            foreach (TrackLayoutPart::FAMILIES as $family) {
                $items = $part->family($family);
                if ($family === 'tracks') {
                    [$merged['tracks'], $out] = self::mergeTracks($merged['tracks'], $covered['tracks'], $items, self::citedTracks($part));
                    $dropped = [...$dropped, ...$out];
                } else {
                    $merged[$family] = [...$merged[$family], ...match ($family) {
                        'operational_points' => self::newPoints($merged[$family], $items),
                        'switches', 'signals', 'crossings', 'buffer_stops', 'milestones' => self::newPunctual($merged[$family], $covered[$family], $items),
                        default => self::clipRanges($items, $covered[$family]),
                    }];
                }
                $own = $part->covered[$family] ?? [];
                if (in_array($family, ['speeds', 'gradients'], true)) {
                    $own = [...$own, ...array_map(fn (array $r): array => [(int) $r['pk_from_m'], (int) $r['pk_to_m']], $items)];
                }
                $covered[$family] = self::union([...$covered[$family], ...$own]);
                $coveredBy[$key][$family] = self::union($own);
            }
        }

        // Une même voie décrite deux fois (RINF et OSM, ou voie de gare approchée du RINF sur sa voie de ligne) ne sort
        // qu'une fois ; les objets qui citaient la voie écartée citent la voie gardée.
        // Les voies écartées plus haut y prennent part : leur décalage latéral place la voie gardée, puis elles partent.
        $keptIds = array_flip(array_map(fn (array $t): string => (string) $t['id'], $merged['tracks']));
        $gone = [];
        foreach ($dropped as $t) {
            if (! isset($keptIds[(string) $t['id']])) {
                $gone[(string) $t['id']] = $t;
            }
        }
        [$all, $same, $at] = self::absorbDuplicates([...$merged['tracks'], ...array_values($gone)]);
        $merged['tracks'] = array_values(array_filter($all, fn (array $t): bool => ! isset($gone[(string) $t['id']])));
        if ($same !== []) {
            $merged = self::remapTracks($merged, $same, $at);
        }

        $extent = self::extent($merged, $covered);
        $start = $fromM !== null ? max($fromM, $extent[0] ?? $fromM) : $extent[0];
        $end = $toM !== null ? min($toM, $extent[1] ?? $toM) : $extent[1];
        if ($start !== null && $end !== null && $start > $end) {
            $start = $end = null;
        }

        $in = fn (int $a, int $b): bool => $start === null || $end === null || ($a <= $end && $b >= $start);
        $points = array_values(array_filter($merged['operational_points'], fn (array $o): bool => $o['pk_m'] !== null && $in((int) $o['pk_m'], (int) $o['pk_m'])));
        $tracks = array_values(array_filter($merged['tracks'], fn (array $t): bool => $in((int) $t['pk_from_m'], (int) $t['pk_to_m'])));
        // Les rangées ne servent qu'aux voies : une réponse sans voies (aperçu d'une longue ligne) s'en passe.
        $tracks = in_array('tracks', $kinds, true) ? LaneAssigner::assign($tracks) : array_map(fn (array $t): array => ['lane' => 0] + $t, $tracks);
        $pointFilter = fn (array $list): array => self::sortByPk(array_values(array_filter($list, fn (array $o): bool => $in((int) $o['pk_m'], (int) $o['pk_m']))));
        $rangeFilter = fn (array $list): array => self::sortRanges(self::clipTo(array_values(array_filter($list, fn (array $r): bool => $in((int) $r['pk_from_m'], (int) $r['pk_to_m']))), $start, $end));

        $used = array_values(array_filter(array_map(fn (TrackLayoutPart $p): ?array => $p->source, $parts)));

        $out = [
            'line' => ['code' => $line->code, 'slug' => $line->slug, 'name' => $line->name],
            'pk' => ['start_m' => $start, 'end_m' => $end,
                'start_label' => LineStations::pkLabel($start), 'end_label' => LineStations::pkLabel($end)],
            'sources' => self::shape('sources', $used),
            'operational_points' => self::shape('operational_points', self::sortByPk($points)),
            'tracks' => self::shape('tracks', self::sortTracks($tracks)),
            'switches' => self::shape('switches', $pointFilter($merged['switches'])),
            'signals' => self::shape('signals', $pointFilter($merged['signals'])),
            'crossings' => self::shape('crossings', $pointFilter($merged['crossings'])),
            'buffer_stops' => self::shape('buffer_stops', $pointFilter($merged['buffer_stops'])),
            'milestones' => self::shape('milestones', $pointFilter($merged['milestones'])),
            // `tracks` : voies auxquelles la vitesse ou la déclivité s'applique (références courtes), null quand la source
            // ne le dit pas.
            'strip' => ['speeds' => array_map(fn (array $r): array => ['pk_from_m' => $r['pk_from_m'], 'pk_to_m' => $r['pk_to_m'], 'kmh' => $r['kmh'],
                'tracks' => $r['tracks'] ?? null, 'source' => $r['source']], $rangeFilter($merged['speeds'])),
                'gradients' => array_map(fn (array $r): array => ['pk_from_m' => $r['pk_from_m'], 'pk_to_m' => $r['pk_to_m'], 'permille' => $r['permille'],
                    'tracks' => $r['tracks'] ?? null, 'source' => $r['source'], 'precision' => $r['precision'] ?? null], $rangeFilter($merged['gradients']))],
            'coverage' => self::coverage($start, $end, $covered, $coveredBy),
        ];
        // Nombre d'objets de chaque famille sur la portion servie, quelles que soient les familles demandées : un aperçu
        // (`kinds=operational_points`) dit ce que la ligne a sans le transférer.
        $out['counts'] = [];
        foreach (self::COUNTED as $family) {
            $out['counts'][$family] = count($out[$family]);
        }

        return self::onlyKinds($out, $kinds);
    }

    /**
     * Réponse réduite aux familles demandées : les autres listes sont vidées, `counts` et `coverage` restent (un aperçu
     * dit ce que la portion a sans le transférer).
     *
     * @param  array<string, mixed>  $layout  réponse de forLine
     * @param  list<string>|null  $kinds  familles à servir (null : toutes)
     * @return array<string, mixed>
     */
    public static function onlyKinds(array $layout, ?array $kinds): array
    {
        if ($kinds === null) {
            return $layout;
        }
        foreach (['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $layout[$family] = [];
            }
        }
        foreach (['speeds', 'gradients'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $layout['strip'][$family] = [];
            }
        }

        return $layout;
    }

    /**
     * Objets d'une famille dans la forme de la réponse : champs de FIELDS dans l'ordre (null si absent), puis les
     * ajouts d'une source, sans les clés de travail.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function shape(string $family, array $items): array
    {
        $fields = array_fill_keys(self::FIELDS[$family], null);
        $internal = array_flip(self::INTERNAL);

        return array_map(fn (array $o): array => array_diff_key(array_replace($fields, $o), $internal), $items);
    }

    /** Réponse vide conforme au contrat (ligne inconnue des sources). */
    public static function emptyFor(Line $line): array
    {
        return (new self([]))->forLine($line);
    }

    private static function rank(string $key): int
    {
        $i = array_search($key, self::PRIORITY, true);

        return $i === false ? count(self::PRIORITY) : (int) $i;
    }

    /**
     * Part d'une source, ou une part vide si une de ses tables manque (ordre de déploiement : migration pas encore
     * passée) : jamais d'erreur 500 pour autant. Lue dans une transaction (point de sauvegarde dans une transaction
     * en cours) pour qu'une requête en échec n'empêche pas les suivantes.
     */
    private function read(TrackLayoutSource $source, Line $line, ?int $fromM, ?int $toM): TrackLayoutPart
    {
        try {
            return DB::transaction(fn (): TrackLayoutPart => $source->part($line, $fromM, $toM));
        } catch (QueryException $e) {
            if (! in_array((string) $e->getCode(), self::MISSING_RELATION, true)) {
                throw $e;
            }
            Log::warning("Schéma des voies : source {$source->key()} ignorée, table ou colonne absente.", ['error' => $e->getMessage()]);

            return TrackLayoutPart::empty();
        }
    }

    /**
     * Voies d'une source moins préférée ajoutées à celles déjà retenues (règles en tête de classe).
     *
     * @param  list<array<string, mixed>>  $kept  voies des sources préférées
     * @param  list<array{0: int, 1: int}>  $covered  portions `tracks` couvertes par les sources préférées
     * @param  list<array<string, mixed>>  $items  voies de la source
     * @param  array<string, true>  $cited  identifiants des voies de la source que ses propres objets citent
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} voies retenues, voies écartées
     */
    private static function mergeTracks(array $kept, array $covered, array $items, array $cited = []): array
    {
        // Voies approchées des sources préférées, par point d'exploitation : leur zone (à OP_ZONE_M près) et leurs numéros.
        $groups = [];
        foreach ($kept as $i => $t) {
            if (($t['precision'] ?? null) === 'approximate' && ($t['kind'] ?? null) !== 'running' && ($t['op_id'] ?? null) !== null) {
                $groups[(string) $t['op_id']][] = $i;
            }
        }
        $zones = [];
        $byNumber = [];
        foreach ($groups as $opId => $indices) {
            $zones[$opId] = [min(array_map(fn (int $i): int => (int) $kept[$i]['pk_from_m'], $indices)) - self::OP_ZONE_M,
                max(array_map(fn (int $i): int => (int) $kept[$i]['pk_to_m'], $indices)) + self::OP_ZONE_M];
            foreach ($indices as $i) {
                foreach (self::numbersOf($kept[$i]) as $number) {
                    $byNumber[$opId][$number][] = $i;
                }
            }
        }
        $inZone = fn (array $t, string $opId): bool => (int) $t['pk_from_m'] < $zones[$opId][1] && $zones[$opId][0] < (int) $t['pk_to_m'];
        // Voies du point de même numéro qu'une voie de la source (« 3 » d'OSM et « Voie 3 de Paris-Nord » du RINF).
        $sameNumber = function (array $numbers, string $opId) use ($byNumber): array {
            foreach ($numbers as $number) {
                if (($found = self::byForms($byNumber[$opId] ?? [], $number)) !== []) {
                    return array_values(array_unique($found));
                }
            }

            return [];
        };

        // Zones rangées par cases de ZONE_CELL_M mètres : une voie ne se compare qu'aux zones des cases qu'elle touche
        // (plusieurs centaines de points d'exploitation et quelques milliers de voies sur Paris - Marseille).
        $cells = [];
        foreach ($zones as $opId => [$a, $b]) {
            for ($c = intdiv($a, self::ZONE_CELL_M) - 1; $c <= intdiv($b, self::ZONE_CELL_M) + 1; $c++) {
                $cells[$c][] = $opId;
            }
        }
        $near = function (array $t) use ($cells, $inZone): array {
            $ops = [];
            for ($c = intdiv((int) $t['pk_from_m'], self::ZONE_CELL_M); $c <= intdiv((int) $t['pk_to_m'], self::ZONE_CELL_M); $c++) {
                foreach ($cells[$c] ?? [] as $opId) {
                    $ops[$opId] = true;
                }
            }

            return array_values(array_filter(array_map('strval', array_keys($ops)), fn (string $opId): bool => $inZone($t, $opId)));
        };

        $dropped = [];
        $new = [];
        $numbers = [];
        $nearOps = [];
        foreach ($items as $t) {
            $ops = $zones === [] ? [] : $near($t);
            $own = $ops === [] ? [] : self::numbersOf($t);
            if (($t['kind'] ?? null) === 'running' && self::overlapsAny($covered, (int) $t['pk_from_m'], (int) $t['pk_to_m'])
                && ! isset($cited[(string) $t['id']])
                && array_filter($ops, fn (string $op): bool => $sameNumber($own, $op) !== []) === []) {
                $dropped[] = $t;
            } else {
                $new[] = $t;
                $numbers[] = $own;
                foreach ($ops as $op) {
                    $nearOps[$op][] = count($new) - 1;
                }
            }
        }

        $remove = [];
        foreach ($groups as $opId => $indices) {
            $replacing = array_values(array_filter($nearOps[$opId] ?? [], fn (int $j): bool => (($new[$j]['kind'] ?? null) !== 'running'
                && ($new[$j]['precision'] ?? null) !== 'approximate') || $sameNumber($numbers[$j], $opId) !== []));
            if ($replacing === []) {
                continue;
            }
            foreach ($indices as $i) {
                $remove[$i] = true;
                $dropped[] = $kept[$i];
            }
            foreach ($replacing as $j) {
                $same = $sameNumber($numbers[$j], $opId);
                if (count($same) === 1 && ($kept[$same[0]]['source'] ?? null) === 'rinf') {
                    $replaced = $kept[$same[0]];
                    $new[$j]['rinf_id'] = $replaced['id'];
                    $new[$j]['rinf_ref'] = $replaced['rinf_ref'] ?? $replaced['ref'];
                    // Le point dont elle est la voie (Paris-Nord-Surface et Paris-Nord ont le même PK).
                    $new[$j]['op_id'] = $opId;
                    if (($replaced['platform_keys'] ?? []) !== []) {
                        $new[$j]['platform_keys'] = array_values(array_unique([...$new[$j]['platform_keys'] ?? [], ...$replaced['platform_keys']]));
                        $new[$j]['platform'] = true;
                    }
                }
                $new[$j]['op_id'] ??= $opId;
            }
        }

        return [[...array_values(array_diff_key($kept, $remove)), ...$new], $dropped];
    }

    /**
     * Identifiants des voies d'une source que ses propres objets citent : aiguillages (`from_track`, `to_track`),
     * signaux et heurtoirs (`track`), traversées (`tracks`).
     *
     * @return array<string, true>
     */
    private static function citedTracks(TrackLayoutPart $part): array
    {
        $cited = [];
        foreach ($part->switches as $sw) {
            foreach ([$sw['from_track'] ?? null, $sw['to_track'] ?? null] as $id) {
                if ($id !== null) {
                    $cited[(string) $id] = true;
                }
            }
        }
        foreach ([...$part->signals, ...$part->bufferStops] as $o) {
            if (($o['track'] ?? null) !== null) {
                $cited[(string) $o['track']] = true;
            }
        }
        foreach ($part->crossings as $c) {
            foreach ((array) ($c['tracks'] ?? []) as $id) {
                $cited[(string) $id] = true;
            }
        }

        return $cited;
    }

    /**
     * Numéros d'une voie : celui de sa référence (trackNumber) puis ceux que lui donne le nom d'un quai (`aliases`).
     *
     * @param  array<string, mixed>  $track
     * @return list<string>
     */
    public static function numbersOf(array $track): array
    {
        $numbers = [self::trackNumber((string) ($track['ref'] ?? '')), ...array_map('strval', (array) ($track['aliases'] ?? []))];

        return array_values(array_unique(array_filter($numbers, fn (string $n): bool => $n !== '')));
    }

    /**
     * Formes sous lesquelles une autre source écrit un numéro de voie : lui-même, puis, pour un code en lettres, avec
     * ou sans le « V » (« Voie ») que le RINF met devant (mesure du 25/11/2026 sur les voies de ligne du RINF sans
     * voie d'OSM de même numéro : « VDO », « VRO », « VDM », « VRV », « VLC1 », « VM » du RINF sont « DO », « RO »,
     * « DM », « RV », « LC1 », « M » d'OSM à Paris-Nord ; « RD » du RINF y est « VRD » d'OSM ; « Quai VD » de
     * Cognac borde la voie « D »). Un numéro qui commence par un chiffre n'a qu'une forme (trackNumber ôte déjà le
     * « V » de « V3 »).
     *
     * @return list<string>
     */
    public static function numberForms(string $number): array
    {
        if ($number === '') {
            return [];
        }
        $forms = [$number];
        if (preg_match('/^[A-Z]/', $number) === 1) {
            $forms[] = 'V'.$number;
            if (preg_match('/^V[A-Z]/', $number) === 1) {
                $forms[] = substr($number, 1);
            }
        }

        return $forms;
    }

    /** Deux numéros de voie (trackNumber) qui désignent la même voie (numberForms). */
    public static function sameNumber(string $a, string $b): bool
    {
        return $a !== '' && $b !== '' && in_array($b, self::numberForms($a), true);
    }

    /**
     * Entrées d'un index par numéro pour un numéro, sous sa forme exacte d'abord, puis ses autres formes.
     *
     * @template T
     *
     * @param  array<string, list<T>>  $index
     * @return list<T>
     */
    private static function byForms(array $index, string $number): array
    {
        foreach (self::numberForms($number) as $form) {
            if (isset($index[$form])) {
                return $index[$form];
            }
        }

        return [];
    }

    /**
     * Une même voie décrite deux fois : voir les règles en tête de classe. Ancres : voies de ligne du RINF (`running`,
     * `exact`, numérotées). Une voie absorbée disparaît ; son décalage latéral (OSM) passe à l'ancre, en moyenne
     * pondérée par le recouvrement, pour que la voie garde sa place réelle parmi ses voisines.
     * Rend les voies gardées, identifiant absorbé => identifiant gardé, et identifiant absorbé => pièces de la voie gardée
     * qu'il recouvre [identifiant, début, fin] (une voie du RINF est coupée à chaque section).
     *
     * @param  list<array<string, mixed>>  $tracks
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>, 2: array<string, list<array{0: string, 1: int, 2: int}>>}
     */
    public static function absorbDuplicates(array $tracks): array
    {
        $isAnchor = fn (array $t): bool => ($t['source'] ?? null) === 'rinf' && ($t['precision'] ?? null) === 'exact'
            && ($t['kind'] ?? null) === 'running' && self::trackNumber((string) ($t['ref'] ?? '')) !== '';
        $anchors = [];
        foreach ($tracks as $i => $t) {
            if ($isAnchor($t)) {
                $anchors[self::trackNumber((string) $t['ref'])][] = $i;
            }
        }
        if ($anchors === []) {
            return [$tracks, [], []];
        }
        $overlap = fn (array $a, array $b): int => max(0, min((int) $a['pk_to_m'], (int) $b['pk_to_m']) - max((int) $a['pk_from_m'], (int) $b['pk_from_m']));
        $length = fn (array $t): int => max(1, (int) $t['pk_to_m'] - (int) $t['pk_from_m']);
        $candidate = fn (array $t): bool => in_array($t['kind'] ?? null, ['running', 'loop'], true)
            && (($t['source'] ?? null) !== 'rinf' || ($t['precision'] ?? null) === 'approximate');

        $same = [];
        /** @var array<int, array{0: float, 1: int}> $lateral ancre => [somme pondérée, poids] */
        $lateral = [];
        /** @var array<int, list<string>> $keys ancre => quais des voies qu'elle absorbe */
        $keys = [];
        /** @var array<int, list<int>> $along voie absorbée => pièces de l'ancre qu'elle recouvre */
        $along = [];
        $absorb = function (int $i, array $into) use (&$same, &$lateral, &$keys, &$along, $tracks, $overlap, $length): bool {
            $best = null;
            $shared = 0;
            foreach ($into as $a) {
                $o = $overlap($tracks[$i], $tracks[$a]);
                $shared += $o;
                if ($o > 0 && ($best === null || $o > $overlap($tracks[$i], $tracks[$best]))) {
                    $best = $a;
                }
            }
            if ($best === null || $shared < self::SAME_TRACK_SHARE * $length($tracks[$i])) {
                return false;
            }
            $same[$i] = $best;
            $along[$i] = array_values(array_filter($into, fn (int $a): bool => $overlap($tracks[$i], $tracks[$a]) > 0));
            if (($tracks[$i]['platform_keys'] ?? []) !== []) {
                $keys[$best] = [...$keys[$best] ?? [], ...$tracks[$i]['platform_keys']];
            }
            if (isset($tracks[$i]['offset_m']) && is_numeric($tracks[$i]['offset_m'])) {
                foreach ($into as $a) {
                    if (($o = $overlap($tracks[$i], $tracks[$a])) > 0) {
                        $lateral[$a] = [($lateral[$a][0] ?? 0.0) + (float) $tracks[$i]['offset_m'] * $o, ($lateral[$a][1] ?? 0) + $o];
                    }
                }
            }

            return true;
        };

        // Même numéro (ou une de ses formes, ou le nom que lui donne un quai).
        foreach ($tracks as $i => $t) {
            if ($isAnchor($t) || ! $candidate($t)) {
                continue;
            }
            foreach (self::numbersOf($t) as $number) {
                if (($into = self::byForms($anchors, $number)) !== [] && $absorb($i, $into)) {
                    break;
                }
            }
        }
        // Décalage latéral des ancres : celui de leurs doublons, sinon la moyenne de leur numéro.
        foreach ($anchors as $indices) {
            $sum = 0.0;
            $weight = 0;
            foreach ($indices as $a) {
                $sum += $lateral[$a][0] ?? 0.0;
                $weight += $lateral[$a][1] ?? 0;
            }
            foreach ($indices as $a) {
                if (! isset($tracks[$a]['offset_m']) && ($own = $lateral[$a] ?? null) !== null) {
                    $tracks[$a]['offset_m'] = round($own[0] / $own[1], 2);
                } elseif (! isset($tracks[$a]['offset_m']) && $weight > 0) {
                    $tracks[$a]['offset_m'] = round($sum / $weight, 2);
                }
            }
        }
        // Même place : voie principale d'OSM sans numéro sur une ancre placée.
        $placed = array_values(array_filter(array_merge(...array_values($anchors)), fn (int $a): bool => isset($tracks[$a]['offset_m'])));
        foreach ($tracks as $i => $t) {
            if (isset($same[$i]) || ($t['kind'] ?? null) !== 'running' || ($t['source'] ?? null) === 'rinf'
                || self::trackNumber((string) ($t['ref'] ?? '')) !== '' || ! isset($t['offset_m']) || ! is_numeric($t['offset_m'])) {
                continue;
            }
            $near = array_values(array_filter($placed, fn (int $a): bool => abs((float) $tracks[$a]['offset_m'] - (float) $t['offset_m']) <= self::SAME_PLACE_M));
            if ($near !== []) {
                $absorb($i, $near);
            }
        }

        // Les quais des voies absorbées passent à la voie gardée (rangées : ses voisines de quai).
        foreach ($keys as $a => $list) {
            $tracks[$a]['platform_keys'] = array_values(array_unique([...$tracks[$a]['platform_keys'] ?? [], ...$list]));
        }

        $map = [];
        $at = [];
        foreach ($same as $i => $a) {
            $map[(string) $tracks[$i]['id']] = (string) $tracks[$a]['id'];
            $at[(string) $tracks[$i]['id']] = array_map(fn (int $p): array => [(string) $tracks[$p]['id'], (int) $tracks[$p]['pk_from_m'], (int) $tracks[$p]['pk_to_m']], $along[$i]);
        }

        return [array_values(array_diff_key($tracks, $same)), $map, $at];
    }

    /**
     * Objets qui citent une voie absorbée : ils citent la voie gardée (aiguillages, signaux, heurtoirs, traversées,
     * voies reliées par une liaison), et, quand elle est en plusieurs pièces, la pièce qui porte leur PK (Paris-Nord :
     * un aiguillage au PK 0+830 de la voie LC1 d'OSM, absorbée par la voie VLC1 du RINF coupée au PK 1+115, citait la
     * pièce 1+115 à 1+495, hors de la fenêtre de la gare). Un aiguillage dont les deux voies sont devenues la même ne
     * mène plus nulle part.
     *
     * @param  array<string, list<array<string, mixed>>>  $merged
     * @param  array<string, string>  $map
     * @param  array<string, list<array{0: string, 1: int, 2: int}>>  $at
     * @return array<string, list<array<string, mixed>>>
     */
    private static function remapTracks(array $merged, array $map, array $at = []): array
    {
        $id = function (mixed $v, mixed $pk = null) use ($map, $at): mixed {
            if (! is_string($v) || ! isset($map[$v])) {
                return $v;
            }
            foreach (is_numeric($pk) ? $at[$v] ?? [] : [] as [$piece, $from, $to]) {
                if ((int) $pk >= $from && (int) $pk <= $to) {
                    return $piece;
                }
            }

            return $map[$v];
        };
        foreach ($merged['switches'] as &$sw) {
            $sw['from_track'] = $id($sw['from_track'] ?? null, $sw['pk_m'] ?? null);
            $sw['to_track'] = $id($sw['to_track'] ?? null, $sw['pk_m'] ?? null);
            if ($sw['to_track'] !== null && $sw['to_track'] === $sw['from_track']) {
                $sw['to_track'] = null;
            }
        }
        unset($sw);
        foreach (['signals', 'buffer_stops'] as $family) {
            foreach ($merged[$family] as &$o) {
                $o['track'] = $id($o['track'] ?? null, $o['pk_m'] ?? null);
            }
            unset($o);
        }
        foreach ($merged['crossings'] as &$c) {
            if (isset($c['tracks']) && is_array($c['tracks'])) {
                $c['tracks'] = array_values(array_unique(array_map(fn (mixed $v): mixed => $id($v, $c['pk_m'] ?? null), $c['tracks'])));
            }
        }
        unset($c);
        foreach ($merged['tracks'] as &$t) {
            if (isset($t['connects']) && is_array($t['connects'])) {
                $t['connects'] = array_values(array_unique(array_map(fn (mixed $v): mixed => $id($v), $t['connects'])));
            }
        }
        unset($t);

        return $merged;
    }

    /**
     * Numéro d'une voie pour rapprocher deux sources : premier mot de la référence, sans « Voie » ni « V » devant un
     * chiffre (« 3 - COGNAC », « V3 » et « 3 » : « 3 » ; « E de Cognac » : « E » ; « VU » et « U » : « U » ; « 2 bis » et
     * « V2BIS » : « 2BIS », jamais « 2 », la voie 2 elle-même), « » sans référence.
     */
    public static function trackNumber(string $ref): string
    {
        $ref = strtoupper(trim((string) preg_replace('/^\s*voie\s+/iu', '', $ref)));
        if (preg_match('/^V?(\d+[A-Z]?)(?:\s?(BIS|TER|QUATER))?\b/', $ref, $m) === 1) {
            return $m[1].($m[2] ?? '');
        }
        $word = preg_match('/^([A-Z0-9]+)\b/', $ref, $m) === 1 ? $m[1] : '';

        // Voie unique : « VU » du RINF, « U » d'OpenStreetMap, « UNIQUE ».
        return in_array($word, ['VU', 'U', 'UNIQUE'], true) ? 'U' : $word;
    }

    /**
     * Points d'exploitation d'une source moins préférée qui ne doublent pas un point retenu.
     *
     * @param  list<array<string, mixed>>  $kept
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function newPoints(array $kept, array $items): array
    {
        $out = [];
        foreach ($items as $o) {
            $dup = false;
            $slug = $o['station_slug'] ?? null;
            foreach ($kept as $k) {
                $sameStation = $slug !== null && $slug === ($k['station_slug'] ?? null);
                $near = $o['pk_m'] !== null && $k['pk_m'] !== null && abs((int) $o['pk_m'] - (int) $k['pk_m']) <= self::OP_MERGE_M;
                if ($sameStation || $near) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                $out[] = $o;
            }
        }

        return $out;
    }

    /**
     * Objets ponctuels (aiguillages, signaux, traversées, heurtoirs, bornes) d'une source moins préférée : hors des portions déjà
     * couvertes pour leur famille et sans doublon.
     *
     * @param  list<array<string, mixed>>  $kept
     * @param  list<array{0: int, 1: int}>  $covered
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function newPunctual(array $kept, array $covered, array $items): array
    {
        // Objets rangés par cases de DEDUPE_M mètres : un doublon ne peut être que dans la case du PK ou ses deux voisines
        // (4 430 aiguillages sur Paris - Marseille : la comparaison de tous avec tous coûtait près d'une seconde).
        $keptCells = self::cells($kept);
        $outCells = [];
        $out = [];
        foreach ($items as $o) {
            $pk = (int) $o['pk_m'];
            if (self::overlapsAny($covered, $pk, $pk)) {
                continue;
            }
            foreach (self::neighbours($keptCells, $pk) as $k) {
                if (abs((int) $k['pk_m'] - $pk) <= self::DEDUPE_M && (($k['ref'] ?? null) === null || ($o['ref'] ?? null) === null || self::sameRef($k['ref'], $o['ref']))) {
                    continue 2;
                }
            }
            // Within one source, two objects are distinct (the switches of a ladder, the signals of a gantry lie a
            // few metres apart): only the same reference at the same place is a duplicate.
            foreach (self::neighbours($outCells, $pk) as $k) {
                if (abs((int) $k['pk_m'] - $pk) <= self::DEDUPE_M && ($o['ref'] ?? null) !== null && ($k['ref'] ?? null) !== null && self::sameRef($k['ref'], $o['ref'])) {
                    continue 2;
                }
            }
            $out[] = $o;
            $outCells[(int) floor($pk / self::DEDUPE_M)][] = $o;
        }

        return $out;
    }

    /**
     * Deux références d'objet qui désignent le même objet : mêmes lettres et chiffres, sans le préfixe « PN » d'un
     * passage à niveau (« PN 1 » de SNCF Réseau et « 1 » d'OpenStreetMap), sans tenir compte de la casse.
     */
    public static function sameRef(mixed $a, mixed $b): bool
    {
        $norm = fn (mixed $r): string => (string) preg_replace('/^PN(?=\d)/', '', strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $r)));

        return $norm($a) === $norm($b);
    }

    /**
     * Objets ponctuels rangés par case de DEDUPE_M mètres de PK.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<int, list<array<string, mixed>>>
     */
    private static function cells(array $items): array
    {
        $cells = [];
        foreach ($items as $o) {
            $cells[(int) floor((int) $o['pk_m'] / self::DEDUPE_M)][] = $o;
        }

        return $cells;
    }

    /**
     * Objets de la case d'un PK et des deux cases voisines.
     *
     * @param  array<int, list<array<string, mixed>>>  $cells
     * @return list<array<string, mixed>>
     */
    private static function neighbours(array $cells, int $pk): array
    {
        $c = (int) floor($pk / self::DEDUPE_M);

        return [...$cells[$c - 1] ?? [], ...$cells[$c] ?? [], ...$cells[$c + 1] ?? []];
    }

    /**
     * Portions de bande (vitesses, déclivités) rognées aux trous de la bande déjà remplie.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{0: int, 1: int}>  $covered
     * @return list<array<string, mixed>>
     */
    private static function clipRanges(array $items, array $covered): array
    {
        $out = [];
        foreach ($items as $r) {
            foreach (self::subtract([(int) $r['pk_from_m'], (int) $r['pk_to_m']], $covered) as [$a, $b]) {
                if ($b > $a) {
                    $out[] = ['pk_from_m' => $a, 'pk_to_m' => $b] + $r;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    private static function overlapsAny(array $ranges, int $from, int $to): bool
    {
        foreach ($ranges as [$a, $b]) {
            if ($from < $b && $a < $to || ($from === $to && $from >= $a && $from <= $b)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Réunion de portions [début, fin].
     *
     * @param  list<array{0: int, 1: int}>  $ranges
     * @return list<array{0: int, 1: int}>
     */
    public static function union(array $ranges): array
    {
        $ranges = array_map(fn (array $r): array => [min($r[0], $r[1]), max($r[0], $r[1])], $ranges);
        usort($ranges, fn (array $a, array $b): int => $a <=> $b);
        $out = [];
        foreach ($ranges as $r) {
            $last = count($out) - 1;
            if ($last >= 0 && $r[0] <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $r[1]);
            } else {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * Ce qui reste d'une portion hors des portions données.
     *
     * @param  array{0: int, 1: int}  $range
     * @param  list<array{0: int, 1: int}>  $minus
     * @return list<array{0: int, 1: int}>
     */
    public static function subtract(array $range, array $minus): array
    {
        $parts = [[min($range), max($range)]];
        foreach (self::union($minus) as [$a, $b]) {
            $next = [];
            foreach ($parts as [$x, $y]) {
                if ($b <= $x || $a >= $y) {
                    $next[] = [$x, $y];

                    continue;
                }
                if ($a > $x) {
                    $next[] = [$x, $a];
                }
                if ($b < $y) {
                    $next[] = [$b, $y];
                }
            }
            $parts = $next;
        }

        return $parts;
    }

    /**
     * PK extrêmes de tout ce que les sources décrivent.
     *
     * @param  array<string, list<array<string, mixed>>>  $merged
     * @param  array<string, list<array{0: int, 1: int}>>  $covered
     * @return array{0: ?int, 1: ?int}
     */
    private static function extent(array $merged, array $covered): array
    {
        $pks = [];
        foreach ($merged as $items) {
            foreach ($items as $o) {
                foreach (['pk_m', 'pk_from_m', 'pk_to_m'] as $k) {
                    if (isset($o[$k])) {
                        $pks[] = (int) $o[$k];
                    }
                }
            }
        }
        foreach ($covered as $ranges) {
            foreach ($ranges as [$a, $b]) {
                $pks[] = $a;
                $pks[] = $b;
            }
        }

        return $pks === [] ? [null, null] : [min($pks), max($pks)];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function clipTo(array $items, ?int $start, ?int $end): array
    {
        if ($start === null || $end === null) {
            return $items;
        }

        return array_map(fn (array $r): array => ['pk_from_m' => max($start, (int) $r['pk_from_m']), 'pk_to_m' => min($end, (int) $r['pk_to_m'])] + $r, $items);
    }

    /**
     * Couverture de la portion servie : longueur décrite par le RINF et par OSM (voies), et trous par famille.
     *
     * @param  array<string, list<array{0: int, 1: int}>>  $covered
     * @param  array<string, array<string, list<array{0: int, 1: int}>>>  $coveredBy
     * @return array{total_m: int, rinf_m: int, osm_m: int, vff_m: int, missing: list<array{pk_from_m: int, pk_to_m: int, what: string}>}
     */
    private static function coverage(?int $start, ?int $end, array $covered, array $coveredBy): array
    {
        if ($start === null || $end === null) {
            return ['total_m' => 0, 'rinf_m' => 0, 'osm_m' => 0, 'vff_m' => 0, 'missing' => []];
        }
        $length = function (array $ranges) use ($start, $end): int {
            $sum = 0;
            foreach (self::union($ranges) as [$a, $b]) {
                $sum += max(0, min($b, $end) - max($a, $start));
            }

            return $sum;
        };
        $missing = [];
        foreach (self::COVERAGE_FAMILIES as $family) {
            foreach (self::subtract([$start, $end], $covered[$family]) as [$a, $b]) {
                if ($b > $a) {
                    $missing[] = ['pk_from_m' => $a, 'pk_to_m' => $b, 'what' => $family];
                }
            }
        }

        return [
            'total_m' => $end - $start,
            'rinf_m' => $length($coveredBy['rinf']['tracks'] ?? []),
            'osm_m' => $length($coveredBy['osm']['tracks'] ?? []),
            'vff_m' => $length($coveredBy['vff']['tracks'] ?? []),
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortByPk(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_m'], (string) $a['id']] <=> [(int) $b['pk_m'], (string) $b['id']]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortRanges(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_from_m'], (int) $a['pk_to_m']] <=> [(int) $b['pk_from_m'], (int) $b['pk_to_m']]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortTracks(array $items): array
    {
        usort($items, fn (array $a, array $b): int => [(int) $a['pk_from_m'], (int) $a['lane'], (string) $a['id']] <=> [(int) $b['pk_from_m'], (int) $b['lane'], (string) $b['id']]);

        return $items;
    }
}
