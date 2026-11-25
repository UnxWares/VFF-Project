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
 *   faisceau, liaison…) d'une source suivante sont gardées. À un point d'exploitation dont la source préférée n'a
 *   que des voies `approximate` (voies d'une gare du RINF centrées sur son PK), des voies de service exactes ou
 *   déduites d'une source suivante (à OP_ZONE_M près) les remplacent ; une voie remplaçante de même numéro qu'une
 *   seule voie remplacée garde son identifiant et sa référence RINF (`rinf_id`, `rinf_ref`) et le point (`op_id`).
 *   Toute voie citée par un aiguillage gardé (`from_track`, `to_track`) est gardée ;
 * - même voie décrite deux fois (absorbDuplicates) : une voie de ligne exacte du RINF (identifiant officiel, PK de
 *   ses sections) absorbe les voies d'OSM et les voies de gare approchées du RINF de même numéro (« 1bis » et
 *   « V1BIS ») qui la recouvrent sur au moins la moitié de leur longueur, et les voies principales d'OSM sans numéro
 *   posées au même endroit (décalage latéral à SAME_PLACE_M près) ; elle prend le décalage latéral d'OSM (ordre
 *   réel des voies) et les aiguillages, signaux, heurtoirs et traversées des voies absorbées la citent ;
 * - aiguillages, signaux, traversées, heurtoirs, bornes kilométriques : gardés hors des portions couvertes de leur
 *   famille par une source préférée, et sauf doublon d'un objet d'une source préférée (même famille, même
 *   référence au sens de sameRef, « PN 1 » et « 1 », ou référence absente, à DEDUPE_M près) ; dans une même
 *   source, seule la même référence au même endroit fait un doublon ;
 * - points d'exploitation : gardés sauf s'ils doublent un point déjà retenu (même gare de VFF, ou à OP_MERGE_M près) ;
 * - vitesses et déclivités : rognées aux trous de la bande déjà remplie.
 * Puis les rangées des voies (LaneAssigner), le tri par PK, la couverture (`coverage`) et les sources utilisées.
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
    private const INTERNAL = ['parent_ref', 'offset_m', 'connects'];

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
                    [$merged['tracks'], $out] = self::mergeTracks($merged['tracks'], $covered['tracks'], $items);
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

        // Une voie citée par un aiguillage gardé reste, même écartée par les règles ci-dessus.
        $keptIds = array_flip(array_map(fn (array $t): string => (string) $t['id'], $merged['tracks']));
        $cited = [];
        foreach ($merged['switches'] as $sw) {
            foreach (['from_track', 'to_track'] as $k) {
                if (($sw[$k] ?? null) !== null) {
                    $cited[(string) $sw[$k]] = true;
                }
            }
        }
        foreach ($dropped as $t) {
            if (isset($cited[(string) $t['id']]) && ! isset($keptIds[(string) $t['id']])) {
                $merged['tracks'][] = $t;
                $keptIds[(string) $t['id']] = true;
            }
        }

        // Une même voie décrite deux fois (RINF et OSM, ou voie de gare approchée du RINF sur sa voie de ligne) ne sort
        // qu'une fois ; les objets qui citaient la voie écartée citent la voie gardée.
        // Les voies écartées plus haut y prennent part : leur décalage latéral place la voie gardée, puis elles partent.
        $gone = [];
        foreach ($dropped as $t) {
            if (! isset($keptIds[(string) $t['id']])) {
                $gone[(string) $t['id']] = $t;
            }
        }
        [$all, $same] = self::absorbDuplicates([...$merged['tracks'], ...array_values($gone)]);
        $merged['tracks'] = array_values(array_filter($all, fn (array $t): bool => ! isset($gone[(string) $t['id']])));
        if ($same !== []) {
            $merged = self::remapTracks($merged, $same);
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
            // `tracks` : voies auxquelles la vitesse s'applique (références courtes), null quand la source ne le dit pas.
            'strip' => ['speeds' => array_map(fn (array $r): array => ['pk_from_m' => $r['pk_from_m'], 'pk_to_m' => $r['pk_to_m'], 'kmh' => $r['kmh'],
                'tracks' => $r['tracks'] ?? null, 'source' => $r['source']], $rangeFilter($merged['speeds'])), 'gradients' => $rangeFilter($merged['gradients'])],
            'coverage' => self::coverage($start, $end, $covered, $coveredBy),
        ];
        // Nombre d'objets de chaque famille sur la portion servie, quelles que soient les familles demandées : un aperçu
        // (`kinds=operational_points`) dit ce que la ligne a sans le transférer.
        $out['counts'] = [];
        foreach (self::COUNTED as $family) {
            $out['counts'][$family] = count($out[$family]);
        }
        foreach (['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $out[$family] = [];
            }
        }
        foreach (['speeds', 'gradients'] as $family) {
            if (! in_array($family, $kinds, true)) {
                $out['strip'][$family] = [];
            }
        }

        return $out;
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
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} voies retenues, voies écartées
     */
    private static function mergeTracks(array $kept, array $covered, array $items): array
    {
        $dropped = [];
        $new = [];
        foreach ($items as $t) {
            if (($t['kind'] ?? null) === 'running' && self::overlapsAny($covered, (int) $t['pk_from_m'], (int) $t['pk_to_m'])) {
                $dropped[] = $t;
            } else {
                $new[] = $t;
            }
        }

        // Voies approchées des sources préférées, par point d'exploitation.
        $groups = [];
        foreach ($kept as $i => $t) {
            if (($t['precision'] ?? null) === 'approximate' && ($t['kind'] ?? null) !== 'running' && ($t['op_id'] ?? null) !== null) {
                $groups[(string) $t['op_id']][] = $i;
            }
        }
        $remove = [];
        foreach ($groups as $opId => $indices) {
            $zoneFrom = min(array_map(fn (int $i): int => (int) $kept[$i]['pk_from_m'], $indices)) - self::OP_ZONE_M;
            $zoneTo = max(array_map(fn (int $i): int => (int) $kept[$i]['pk_to_m'], $indices)) + self::OP_ZONE_M;
            $replacing = array_keys(array_filter($new, fn (array $t): bool => ($t['kind'] ?? null) !== 'running'
                && ($t['precision'] ?? null) !== 'approximate' && (int) $t['pk_from_m'] < $zoneTo && $zoneFrom < (int) $t['pk_to_m']));
            if ($replacing === []) {
                continue;
            }
            foreach ($indices as $i) {
                $remove[$i] = true;
                $dropped[] = $kept[$i];
            }
            $byNumber = [];
            foreach ($indices as $i) {
                $byNumber[self::trackNumber((string) ($kept[$i]['ref'] ?? ''))][] = $i;
            }
            foreach ($replacing as $j) {
                $number = self::trackNumber((string) ($new[$j]['ref'] ?? ''));
                $same = $number === '' ? [] : ($byNumber[$number] ?? []);
                if (count($same) === 1 && ($kept[$same[0]]['source'] ?? null) === 'rinf') {
                    $new[$j]['rinf_id'] = $kept[$same[0]]['id'];
                    $new[$j]['rinf_ref'] = $kept[$same[0]]['rinf_ref'] ?? $kept[$same[0]]['ref'];
                }
                $new[$j]['op_id'] ??= $opId;
            }
        }

        return [[...array_values(array_diff_key($kept, $remove)), ...$new], $dropped];
    }

    /**
     * Une même voie décrite deux fois : voir les règles en tête de classe. Ancres : voies de ligne du RINF (`running`,
     * `exact`, numérotées). Une voie absorbée disparaît ; son décalage latéral (OSM) passe à l'ancre, en moyenne
     * pondérée par le recouvrement, pour que la voie garde sa place réelle parmi ses voisines.
     *
     * @param  list<array<string, mixed>>  $tracks
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>} voies gardées, identifiant absorbé => identifiant gardé
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
            return [$tracks, []];
        }
        $overlap = fn (array $a, array $b): int => max(0, min((int) $a['pk_to_m'], (int) $b['pk_to_m']) - max((int) $a['pk_from_m'], (int) $b['pk_from_m']));
        $length = fn (array $t): int => max(1, (int) $t['pk_to_m'] - (int) $t['pk_from_m']);
        $candidate = fn (array $t): bool => in_array($t['kind'] ?? null, ['running', 'loop'], true)
            && (($t['source'] ?? null) !== 'rinf' || ($t['precision'] ?? null) === 'approximate');

        $same = [];
        /** @var array<int, array{0: float, 1: int}> $lateral ancre => [somme pondérée, poids] */
        $lateral = [];
        $absorb = function (int $i, array $into) use (&$same, &$lateral, $tracks, $overlap, $length): bool {
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
            if (isset($tracks[$i]['offset_m']) && is_numeric($tracks[$i]['offset_m'])) {
                foreach ($into as $a) {
                    if (($o = $overlap($tracks[$i], $tracks[$a])) > 0) {
                        $lateral[$a] = [($lateral[$a][0] ?? 0.0) + (float) $tracks[$i]['offset_m'] * $o, ($lateral[$a][1] ?? 0) + $o];
                    }
                }
            }

            return true;
        };

        // Même numéro.
        foreach ($tracks as $i => $t) {
            $number = self::trackNumber((string) ($t['ref'] ?? ''));
            if ($number !== '' && ! $isAnchor($t) && $candidate($t) && isset($anchors[$number])) {
                $absorb($i, $anchors[$number]);
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

        $map = [];
        foreach ($same as $i => $a) {
            $map[(string) $tracks[$i]['id']] = (string) $tracks[$a]['id'];
        }

        return [array_values(array_diff_key($tracks, $same)), $map];
    }

    /**
     * Objets qui citent une voie absorbée : ils citent la voie gardée (aiguillages, signaux, heurtoirs, traversées,
     * voies reliées par une liaison). Un aiguillage dont les deux voies sont devenues la même ne mène plus nulle part.
     *
     * @param  array<string, list<array<string, mixed>>>  $merged
     * @param  array<string, string>  $map
     * @return array<string, list<array<string, mixed>>>
     */
    private static function remapTracks(array $merged, array $map): array
    {
        $id = fn (mixed $v): mixed => is_string($v) ? ($map[$v] ?? $v) : $v;
        foreach ($merged['switches'] as &$sw) {
            $sw['from_track'] = $id($sw['from_track'] ?? null);
            $sw['to_track'] = $id($sw['to_track'] ?? null);
            if ($sw['to_track'] !== null && $sw['to_track'] === $sw['from_track']) {
                $sw['to_track'] = null;
            }
        }
        unset($sw);
        foreach (['signals', 'buffer_stops'] as $family) {
            foreach ($merged[$family] as &$o) {
                $o['track'] = $id($o['track'] ?? null);
            }
            unset($o);
        }
        foreach ($merged['crossings'] as &$c) {
            if (isset($c['tracks']) && is_array($c['tracks'])) {
                $c['tracks'] = array_values(array_unique(array_map($id, $c['tracks'])));
            }
        }
        unset($c);
        foreach ($merged['tracks'] as &$t) {
            if (isset($t['connects']) && is_array($t['connects'])) {
                $t['connects'] = array_values(array_unique(array_map($id, $t['connects'])));
            }
        }
        unset($t);

        return $merged;
    }

    /**
     * Numéro d'une voie pour rapprocher deux sources : premier mot de la référence, sans « Voie » ni « V » devant un
     * chiffre (« 3 - COGNAC », « V3 » et « 3 » : « 3 » ; « E de Cognac » : « E » ; « VU » et « U » : « U »), « » sans
     * référence.
     */
    public static function trackNumber(string $ref): string
    {
        $ref = strtoupper(trim((string) preg_replace('/^\s*voie\s+/iu', '', $ref)));
        if (preg_match('/^V?(\d+[A-Z]?(?:BIS|TER)?)\b/', $ref, $m) === 1) {
            return $m[1];
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
