<?php

namespace App\Services;

use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Phases d'une ligne pour sa fiche : chaque date où LE FER (ouverture, fermeture,
 * dépose, réouverture, patrimoine) ou LE TRAFIC (mixte → fret…) change sur une
 * portion, avec l'état de TOUTE la ligne à cette date.
 *
 * Principe (cohérent avec la carte par décennie) :
 *   1. Portions = géométries distinctes des tronçons du moteur (vff:build-era-segments
 *      insère la même géométrie pour chaque ère : une portion garde son identité d'une
 *      décennie à l'autre). Leur état à chaque ère est celui de la carte (instantané
 *      au 31/12, au jour même pour l'ère courante).
 *   2. Chaque changement d'état d'une portion entre deux ères est DATÉ par les vrais
 *      événements de `line_events` de l'intervalle : d'abord ceux localisés sur la
 *      portion (section_geom), puis ceux de la portion voisine qui change de la même
 *      façon (continuité), puis ceux de « toute la ligne ». Le type d'événement doit
 *      expliquer le changement (une fermeture aux voyageurs explique mixte → fret, pas
 *      une ouverture). Sans événement : changement « entre AAAA et AAAA » (non daté).
 *   3. Une phase = une date (ou un intervalle non daté) et tous les changements qui
 *      s'y produisent. L'état de la ligne après chaque phase est complet.
 *   4. Phase « au meilleur » = la première où la ligne atteint sa plus grande longueur en service
 *      (à BEST_TOLERANCE près : un raccord de quelques centaines de mètres posé plus tard n'en est pas une).
 *
 * Une phase ne naît que d'un vrai changement (2026-10-20, contrôle qualité : « Depuis 2021,
 * disparition de la voie sur 1,3 km » sur Paris - Bordeaux en pleine exploitation) :
 *   - écart de tracé entre deux reconstructions (une portion disparaît et une autre géométrie
 *     occupe la même place à l'ère suivante, à RETRACE_TOL_M près sur au moins
 *     RETRACE_MIN_POINTS de ses 5 points témoins) : la voie n'a pas changé. Changement
 *     silencieux (`retraces` : appliqué à l'état de la ligne, jamais montré comme phase) ; si
 *     la catégorie diffère à cet endroit (mixte puis fret), seul ce changement de trafic reste ;
 *   - voie qui apparaît directement fermée, déposée ou en voie de service : c'est le premier
 *     état connu (lacune des sources), pas une fermeture. Silencieux aussi ;
 *   - à l'ère courante (« Depuis 2021 »), un changement qu'aucun événement ne date n'est pas
 *     une phase : c'est un écart entre l'état reconstitué de la dernière décennie et les sources
 *     d'aujourd'hui, rendu à part (`unexplained`) sans date inventée ;
 *   - continuité élargie : une portion séparée d'une portion datée par un petit trou du tracé
 *     (LOOSE_GAP_DEG) qui change de la même façon dans la même fenêtre prend sa date.
 *
 * Le calcul est pur (compute) ; forLine() ne fait que lire la base.
 */
final class LinePhases
{
    /** Catégories « en service » (couleur du trafic sur la carte). */
    public const ACTIVE = ['lgv', 'mixed', 'freight', 'unknown', 'heritage'];

    /** Catégories « voie disparue » (déposée, rasée, disparue sans date). */
    public const GONE = ['dismantled', 'razed', 'gone'];

    public const OPENINGS = ['opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight'];

    /** Recouvrement minimal (part de la longueur de la portion) pour qu'un événement localisé la concerne. */
    public const COVER_RATIO = 0.5;

    /** Tolérance (m) autour de la portion d'un événement (cf. BuildEraSegments::COVER_TOL_M). */
    public const COVER_TOL_M = 30;

    /** Résolutions de portion utilisables (cf. BuildEraSegments::USABLE_RESOLUTIONS). */
    public const USABLE_RESOLUTIONS = ['pk', 'places', 'trace', 'whole_line'];

    private const CONFIDENCE_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    /** Écart de tracé toléré (m) entre deux géométries d'une même voie (reconstructions, jonctions recalées). */
    public const RETRACE_TOL_M = 40;

    /** Points témoins (sur 5) d'une portion qui doivent être couverts pour qu'elle soit « retracée ». */
    public const RETRACE_MIN_POINTS = 4;

    /** Points témoins le long d'une portion (fractions de sa longueur). */
    public const SAMPLE_FRACTIONS = [0.1, 0.3, 0.5, 0.7, 0.9];

    /** Trou de tracé (degrés, ~110 à 165 m) franchi par la continuité élargie. */
    public const LOOSE_GAP_DEG = 0.0015;

    /**
     * Catégorie d'affichage d'un tronçon (mêmes règles que la carte, cf. railStyle.js).
     */
    public static function category(string $status, ?string $traffic, bool $heritage, ?string $heritageKind, bool $lgv): string
    {
        if (in_array($status, ['in_service', 'single_track'], true)) {
            if ($heritage) {
                return 'heritage';
            }
            if ($lgv || $traffic === 'passenger') {
                return 'lgv';
            }

            return in_array($traffic, ['mixed', 'freight'], true) ? $traffic : 'unknown';
        }
        if ($heritage && $heritageKind === 'velorail') {
            return 'velorail';
        }

        return match ($status) {
            'abandoned' => 'closed',
            'service_track', 'construction', 'dismantled', 'razed' => $status,
            default => 'closed',
        };
    }

    public static function isActive(string $category): bool
    {
        return in_array($category, self::ACTIVE, true);
    }

    /**
     * Types d'événements qui expliquent le passage d'une portion de $from à $to,
     * du plus décisif au moins décisif. Liste vide : aucun événement ne peut
     * l'expliquer (ex. voie déposée qui réapparaît fermée : incohérence).
     *
     * @return list<string>
     */
    public static function explainingTypes(string $from, string $to): array
    {
        $active = self::isActive(...);
        if ($to === 'construction' || $from === $to) {
            return [];
        }
        if ($active($to)) {
            if ($to === 'heritage') {
                return ['heritage_start', 'reopening', 'opening', 'closure', 'closure_freight', 'closure_passenger', 'declassification'];
            }
            if ($from === 'none') {
                return self::OPENINGS;
            }
            if (! $active($from)) {
                return ['reopening', 'reopening_passenger', 'reopening_freight', 'opening', 'opening_passenger', 'opening_freight'];
            }
            if ($from === 'heritage') {
                return ['reopening', 'reopening_passenger', 'reopening_freight', 'opening', 'heritage_end'];
            }

            return match ($to) {
                'freight' => ['closure_passenger', 'opening_freight', 'reopening_freight'],
                'mixed', 'lgv' => ['reopening_passenger', 'opening_passenger', 'reopening', 'opening'],
                default => [...self::OPENINGS, 'closure_passenger', 'closure_freight'],
            };
        }
        if (in_array($to, ['closed', 'velorail', 'service_track'], true)) {
            if (in_array($from, self::GONE, true)) {
                return []; // voie disparue qui réapparaît sans réouverture : incohérence du moteur
            }
            $types = ['closure', 'declassification', 'closure_freight', 'closure_passenger'];
            if ($to === 'velorail') {
                array_unshift($types, 'heritage_start');
            }
            if ($from === 'heritage') {
                array_unshift($types, 'heritage_end');
            }

            return $types;
        }
        if (in_array($to, self::GONE, true)) {
            // Voie fermée puis déposée : seule une dépose (ou reconversion) datée la date ; une
            // voie encore exploitée qui disparaît dans la décennie est d'abord fermée (cf. compute).
            return self::isActive($from)
                ? ['dismantling', 'conversion', 'declassification', 'closure', 'closure_freight', 'closure_passenger']
                : ['dismantling', 'conversion'];
        }

        return [];
    }

    /** Changement impossible sans réouverture (voie disparue qui réapparaît fermée) : signalé. */
    public static function isInconsistent(string $from, string $to): bool
    {
        return in_array($from, self::GONE, true) && ! self::isActive($to) && ! in_array($to, self::GONE, true) && $to !== 'none';
    }

    /**
     * Calcul pur des phases.
     *
     * @param  list<array{year: int, is_current: bool}>  $eras  toutes les ères, par année croissante
     * @param  list<array{key: string, length_m: int, states: array<int, string>}>  $portions  états (catégorie) par année d'ère
     * @param  array<int, array{id: int, type: string, date: string, precision: string, source: ?string, confidence: ?string, scope: string}>  $events
     *                                                                                                                                                  scope : localized | whole | unlocated | ignored
     * @param  array<int, list<string>>  $cover  id d'événement localisé → clés des portions couvertes
     * @param  array<string, list<string>>  $adjacency  clé de portion → clés des portions qui la touchent
     * @param  string  $today  date du jour (Y-m-d), limite de l'ère courante
     * @param  array<string, list<list<string>>>  $near  clé de portion → pour chacun de ses points témoins
     *                                                   (SAMPLE_FRACTIONS), les autres portions à moins de RETRACE_TOL_M
     * @param  array<string, list<string>>  $adjacencyLoose  continuité élargie (trous de tracé jusqu'à LOOSE_GAP_DEG)
     * @return array{phases: list<array>, best: ?int, used_events: list<int>, decades: list<array>, unexplained: list<array>}
     */
    public static function compute(array $eras, array $portions, array $events, array $cover, array $adjacency, string $today,
        array $near = [], array $adjacencyLoose = []): array
    {
        if ($portions === [] || $eras === []) {
            return ['phases' => [], 'best' => null, 'used_events' => [], 'decades' => [], 'unexplained' => []];
        }
        usort($eras, fn ($a, $b) => $a['year'] <=> $b['year']);
        $limits = array_map(fn ($e) => $e['is_current'] ? $today : sprintf('%04d-12-31', $e['year']), $eras);
        $keys = array_column($portions, 'key');
        $index = array_flip($keys);
        $lengths = array_column($portions, 'length_m', 'key');

        // 1. État effectif de chaque portion à chaque ère (absente avant d'exister : « none » ;
        //    absente après avoir existé : déposée ou disparue, cf. la règle d'affichage du moteur
        //    qui ne montre une voie déposée que pendant la décennie qui suit sa dépose).
        $seq = [];
        $present = [];
        foreach ($portions as $p) {
            $seen = false;
            $last = 'none';
            foreach ($eras as $i => $era) {
                $c = $p['states'][$era['year']] ?? null;
                $present[$p['key']][$i] = $c !== null;
                if ($c === null) {
                    $c = $seen ? (in_array($last, self::GONE, true) ? $last : 'gone') : 'none';
                } else {
                    $seen = true;
                }
                $seq[$p['key']][$i] = $last = $c;
            }
        }

        // 1 bis. Retracés : catégorie majoritaire des AUTRES portions présentes à l'ère $i qui
        //        recouvrent la portion $k (au moins RETRACE_MIN_POINTS points témoins), sinon null.
        $coverCategory = function (string $k, int $i) use ($near, $present, &$seq): ?string {
            $hit = 0;
            $cats = [];
            foreach ($near[$k] ?? [] as $others) {
                foreach ($others as $o) {
                    if ($present[$o][$i] ?? false) {
                        $hit++;
                        $cats[$seq[$o][$i]] = ($cats[$seq[$o][$i]] ?? 0) + 1;
                        break;
                    }
                }
            }
            if ($hit < self::RETRACE_MIN_POINTS) {
                return null;
            }
            arsort($cats);

            return (string) array_key_first($cats);
        };
        // Portion absente d'une ère alors qu'une autre géométrie occupe sa place : retirée (« none »),
        // pas disparue ; de même aux ères suivantes où elle reste absente.
        foreach ($keys as $k) {
            $retired = false;
            foreach ($eras as $i => $era) {
                if ($present[$k][$i] || $seq[$k][$i] === 'none') {
                    $retired = false;

                    continue;
                }
                if ($retired || $coverCategory($k, $i) !== null) {
                    $seq[$k][$i] = 'none';
                    $retired = true;
                }
            }
        }

        // 2. Changements par fenêtre (ère précédente, ère] et datation par les événements.
        $phases = [];
        $used = [];
        $silent = [];      // fenêtre → [[index de portion, état]] : appliqués à l'état, jamais montrés
        $unexplained = []; // ère courante : changements qu'aucun événement ne date
        foreach ($eras as $i => $era) {
            $lower = $i > 0 ? $limits[$i - 1] : null;
            $upper = $limits[$i];
            $inWindow = array_filter($events, fn ($e) => ($lower === null || $e['date'] > $lower) && $e['date'] <= $upper
                && in_array($e['scope'], ['localized', 'whole'], true));

            // Un événement de la fenêtre explique-t-il « none → $to » sur cette portion ? (localisé
            // dessus, ou « toute la ligne » quand $whole) : alors c'est un vrai changement daté.
            $dated = fn (string $k, string $to, bool $whole) => (bool) array_filter($inWindow, fn ($e) => in_array($e['type'], self::explainingTypes('none', $to), true)
                && (($e['scope'] === 'localized' && in_array($k, $cover[$e['id']] ?? [], true)) || ($whole && $e['scope'] === 'whole')));

            $changes = []; // clé de portion → [from, to]
            foreach ($keys as $k) {
                $from = $i > 0 ? $seq[$k][$i - 1] : 'none';
                $to = $seq[$k][$i];
                if ($from === $to) {
                    continue;
                }
                if ($to === 'none') {
                    $silent[$i][] = [$index[$k], 'none']; // géométrie retirée, la voie est ailleurs dans le tracé

                    continue;
                }
                if ($from === 'none' && $dated($k, $to, ! self::isActive($to))) {
                    $changes[$k] = [$from, $to]; // nouvelle voie (ou fermeture) datée par un événement : une vraie phase

                    continue;
                }
                if ($from === 'none' && $dated($k, $to, ! self::isActive($to))) {
                    $changes[$k] = [$from, $to]; // nouvelle voie (ou fermeture) datée par un événement : une vraie phase

                    continue;
                }
                if ($from === 'none' && $i > 0) {
                    // Nouvelle géométrie à la place d'une autre : l'état d'avant est celui de cet endroit.
                    $from = $coverCategory($k, $i - 1) ?? 'none';
                    if ($from === $to) {
                        $silent[$i][] = [$index[$k], $to];

                        continue;
                    }
                }
                if ($from === 'none' && ! self::isActive($to)) {
                    // Voie connue d'abord fermée, déposée ou en service intérieur : premier état connu.
                    $silent[$i][] = [$index[$k], $to];

                    continue;
                }
                $changes[$k] = [$from, $to];
            }
            if ($changes === []) {
                continue;
            }

            $best = function (array $candidates, array $types) {
                usort($candidates, fn ($a, $b) => [
                    array_search($a['type'], $types, true),
                    EraTimeline::SOURCE_PRIORITY[$a['source'] ?? ''] ?? 10,
                    self::CONFIDENCE_RANK[$a['confidence'] ?? 'medium'] ?? 1,
                    $a['date'], $a['id'],
                ] <=> [
                    array_search($b['type'], $types, true),
                    EraTimeline::SOURCE_PRIORITY[$b['source'] ?? ''] ?? 10,
                    self::CONFIDENCE_RANK[$b['confidence'] ?? 'medium'] ?? 1,
                    $b['date'], $b['id'],
                ]);

                return $candidates[0] ?? null;
            };
            $covers = fn (array $e, string $k) => $e['scope'] === 'whole' || in_array($k, $cover[$e['id']] ?? [], true);

            // 2a. Chemin de chaque changement : une fin de trafic suivie d'une dépose dans la même
            //     décennie donne deux étapes (fermeture, puis dépose) ; une fermeture aux voyageurs
            //     suivie d'une fermeture totale en donne une de plus (mixte → fret → fermée).
            $slots = []; // « clé#n » → [clé, from, to]
            foreach ($changes as $k => [$from, $to]) {
                $path = [[$from, $to]];
                if (self::isActive($from) && in_array($to, self::GONE, true)) {
                    $path = [[$from, 'closed'], ['closed', $to]];
                }
                if (in_array($from, ['mixed', 'lgv'], true) && ! self::isActive($to) && $to !== 'none') {
                    $cp = array_filter($inWindow, fn ($e) => $e['type'] === 'closure_passenger' && $covers($e, $k));
                    $later = fn ($d) => array_filter($inWindow, fn ($e) => in_array($e['type'], ['closure', 'declassification', 'closure_freight', 'dismantling'], true)
                        && $covers($e, $k) && $e['date'] > $d);
                    if (($first = $best($cp, ['closure_passenger'])) && $later($first['date'])) {
                        $path[0][0] = 'freight';
                        array_unshift($path, [$from, 'freight']);
                    }
                }
                foreach ($path as $n => [$f, $t]) {
                    $slots[$k.'#'.$n] = [$k, $f, $t];
                }
            }

            // 2b. Datation : événements localisés sur la portion ; puis continuité (la portion
            //     voisine qui change de la même façon donne sa date) ; puis événements « toute la
            //     ligne » ; puis continuité encore. Sans rien : étape non datée.
            $assigned = []; // slot → [event id, mode]
            foreach ($slots as $sk => [$k, $from, $to]) {
                $types = self::explainingTypes($from, $to);
                $cands = array_filter($inWindow, fn ($e) => $e['scope'] === 'localized' && in_array($e['type'], $types, true)
                    && in_array($k, $cover[$e['id']] ?? [], true));
                if ($e = $best($cands, $types)) {
                    $assigned[$sk] = [$e['id'], 'localized'];
                }
            }
            $propagate = function (?array $adj = null) use (&$assigned, $slots, $adjacency) {
                $adj ??= $adjacency;
                do {
                    $grew = false;
                    foreach ($slots as $sk => [$k, $from, $to]) {
                        if (isset($assigned[$sk])) {
                            continue;
                        }
                        $n = substr($sk, strrpos($sk, '#'));
                        foreach ($adj[$k] ?? [] as $nk) {
                            $other = $nk.$n;
                            if (isset($assigned[$other]) && $slots[$other][1] === $from && $slots[$other][2] === $to) {
                                $assigned[$sk] = [$assigned[$other][0], 'neighbor'];
                                $grew = true;
                                break;
                            }
                        }
                    }
                } while ($grew);
            };
            $propagate();
            foreach ($slots as $sk => [$k, $from, $to]) {
                if (! isset($assigned[$sk])) {
                    $types = self::explainingTypes($from, $to);
                    $cands = array_filter($inWindow, fn ($e) => $e['scope'] === 'whole' && in_array($e['type'], $types, true));
                    if ($e = $best($cands, $types)) {
                        $assigned[$sk] = [$e['id'], 'whole_line'];
                    }
                }
            }
            $propagate();
            // Continuité élargie : petits trous du tracé entre deux portions qui changent ensemble.
            if ($adjacencyLoose !== []) {
                $propagate($adjacencyLoose);
            }
            // Une étape ne peut pas précéder la précédente du même chemin (dépose avant fermeture).
            foreach ($slots as $sk => [$k]) {
                $n = (int) substr($sk, strrpos($sk, '#') + 1);
                $prev = $assigned[$k.'#'.($n - 1)] ?? null;
                if ($n > 0 && isset($assigned[$sk]) && $prev !== null && $events[$assigned[$sk][0]]['date'] < $events[$prev[0]]['date']) {
                    unset($assigned[$sk]);
                }
            }

            // Chemin dont aucune étape n'est datée : un seul changement (mixte → déposée, non daté).
            $byKey = [];
            foreach ($slots as $sk => [$k]) {
                $byKey[$k][] = $sk;
            }
            foreach ($byKey as $k => $sks) {
                if (count($sks) > 1 && ! array_filter($sks, fn ($sk) => isset($assigned[$sk]))) {
                    $first = $slots[$sks[0]];
                    $last = $slots[end($sks)];
                    foreach ($sks as $sk) {
                        unset($slots[$sk]);
                    }
                    $slots[$k.'#0'] = [$k, $first[1], $last[2]];
                }
            }

            // 3. Regroupement en phases (une date = une phase ; le non daté en fin de fenêtre).
            /** @var array<string, array{date: string, precision: string, changes: array<string, array<string, mixed>>}> $windowPhases */
            $windowPhases = [];
            foreach ($slots as $sk => [$k, $from, $to]) {
                [$eid, $mode] = $assigned[$sk] ?? [null, null];
                if ($eid === null && $era['is_current']) {
                    // « Depuis 2021 » sans événement : écart entre l'état reconstitué et les sources
                    // d'aujourd'hui, pas une phase (aucune date inventée, état des phases inchangé).
                    $ukey = $from.'>'.$to;
                    $unexplained[$ukey] ??= ['from' => $from, 'to' => $to, 'portions' => [], 'length_m' => 0];
                    $unexplained[$ukey]['portions'][] = $index[$k];
                    $unexplained[$ukey]['length_m'] += (int) ($lengths[$k] ?? 0);

                    continue;
                }
                if ($eid !== null) {
                    $ev = $events[$eid];
                    $pkey = $ev['date'].'|'.$ev['precision'];
                    $windowPhases[$pkey] ??= ['date' => $ev['date'], 'precision' => $ev['precision'], 'changes' => []];
                    $used[$eid] = true;
                } else {
                    $pkey = '~undated';
                    $windowPhases[$pkey] ??= ['date' => $upper, 'precision' => 'range', 'changes' => []];
                }
                $ckey = $from.'>'.$to.'|'.($eid ?? '-').'|'.($mode ?? '-');
                if (! isset($windowPhases[$pkey]['changes'][$ckey])) {
                    $windowPhases[$pkey]['changes'][$ckey] = ['from' => $from, 'to' => $to, 'event_id' => $eid, 'mode' => $mode,
                        'portions' => [], 'length_m' => 0, 'inconsistent' => self::isInconsistent($from, $to)];
                }
                $windowPhases[$pkey]['changes'][$ckey]['portions'][] = $index[$k];
                $windowPhases[$pkey]['changes'][$ckey]['length_m'] += (int) ($lengths[$k] ?? 0);
            }
            uksort($windowPhases, fn ($a, $b) => [$a === '~undated', $a] <=> [$b === '~undated', $b]);
            foreach ($windowPhases as $wp) {
                $changesOut = array_values($wp['changes']);
                usort($changesOut, fn ($a, $b) => $b['length_m'] <=> $a['length_m']);
                $phases[] = [
                    'date' => $wp['date'],
                    'precision' => $wp['precision'],
                    'window' => $i,
                    'from_year' => $i > 0 ? $eras[$i - 1]['year'] + 1 : null,
                    'to_year' => $era['year'],
                    'is_current' => (bool) $era['is_current'],
                    'changes' => $changesOut,
                ];
            }
        }

        $phases = self::mergeMinor($phases);

        // Changements silencieux (géométrie retracée, premier état connu) : appliqués à l'état à la
        // première phase de leur fenêtre ou d'une fenêtre suivante ; après la dernière phase, rien
        // à appliquer (l'état d'une phase plus ancienne ne doit pas les anticiper).
        foreach ($phases as &$ph) {
            $ph['retraces'] = [];
        }
        unset($ph);
        foreach ($silent as $w => $list) {
            foreach ($phases as $n => $ph) {
                if ($ph['window'] >= $w) {
                    $phases[$n]['retraces'] = array_merge($phases[$n]['retraces'], $list);
                    break;
                }
            }
        }

        // 4. État complet de la ligne après chaque phase, longueur en service, phase « au meilleur ».
        $state = array_fill(0, count($keys), 'none');
        $bestIndex = null;
        $bestLen = -1;
        foreach ($phases as $n => &$ph) {
            foreach ($ph['retraces'] as [$pi, $st]) {
                $state[$pi] = $st;
            }
            foreach ($ph['changes'] as $c) {
                foreach ($c['portions'] as $pi) {
                    $state[$pi] = $c['to'];
                }
            }
            $ph['state'] = $state;
            $byCat = [];
            foreach ($state as $pi => $cat) {
                $byCat[$cat] = ($byCat[$cat] ?? 0) + (int) $portions[$pi]['length_m'];
            }
            unset($byCat['none']);
            ksort($byCat);
            $ph['length_by_category'] = $byCat;
            $ph['active_m'] = array_sum(array_intersect_key($byCat, array_flip(self::ACTIVE)));
            $ph['index'] = $n;
            $ph['kind'] = self::kind($ph['changes']);
            $bestLen = max($bestLen, $ph['active_m']);
        }
        unset($ph);
        // Plus grand développement : la PREMIÈRE phase où la ligne atteint sa longueur maximale en service,
        // à BEST_TOLERANCE près. Avant : la phase du maximum exact, qui pouvait être un raccord de 400 m
        // posé un siècle plus tard (Paris - Brest : « 1989, mise en service de 0,4 km » au lieu de 1865).
        foreach ($phases as $n => $ph) {
            if ($ph['active_m'] >= $bestLen * (1 - self::BEST_TOLERANCE)) {
                $bestIndex = $n;
                break;
            }
        }

        // Décennie par décennie : état de la ligne à chaque ère, avec les mêmes règles (une voie
        // déposée reste comptée « déposée » ensuite, au lieu de disparaître de la synthèse).
        $decades = [];
        foreach ($eras as $i => $era) {
            $byCat = [];
            foreach ($portions as $p) {
                $cat = $seq[$p['key']][$i];
                if ($cat !== 'none') {
                    $byCat[$cat] = ($byCat[$cat] ?? 0) + (int) $p['length_m'];
                }
            }
            if ($byCat === []) {
                continue;
            }
            ksort($byCat);
            $decades[] = ['year' => $era['year'], 'is_current' => (bool) $era['is_current'], 'length_by_category' => $byCat,
                'active_m' => array_sum(array_intersect_key($byCat, array_flip(self::ACTIVE))),
                'phases' => array_keys(array_filter($phases, fn ($ph) => $ph['window'] === $i))];
        }

        $unexplained = array_values($unexplained);
        usort($unexplained, fn ($a, $b) => $b['length_m'] <=> $a['length_m']);

        return ['phases' => $phases, 'best' => $bestIndex, 'used_events' => array_map('intval', array_keys($used)), 'decades' => $decades,
            'unexplained' => $unexplained];
    }

    /** Écart relatif à la longueur maximale en service toléré pour la phase « plus grand développement ». */
    public const BEST_TOLERANCE = 0.01;

    /** Longueur (m) sous laquelle une phase datée n'est qu'un ajustement de tracé, fondue dans une phase voisine. */
    public const MINOR_M = 200;

    /** Idem pour une phase NON datée (bouts de tracé du moteur, sans événement). */
    public const MINOR_UNDATED_M = 1000;

    /**
     * Fond les phases minuscules (moins de MINOR_M de changements : raccords, bouts de
     * tracé) dans la phase suivante (la précédente pour la dernière). Leurs changements
     * sont gardés et marqués « minor » (l'état final reste exact), seule leur date
     * d'affichage change.
     *
     * @param  list<array<string, mixed>>  $phases
     * @return list<array<string, mixed>>
     */
    public static function mergeMinor(array $phases): array
    {
        $len = fn ($p) => array_sum(array_column($p['changes'], 'length_m'));
        // Phase sans aucun changement localisé par sa source (date « toute la ligne » ou héritée) :
        // même seuil que le non daté.
        $weak = fn ($p) => $p['precision'] === 'range' || ! in_array('localized', array_column($p['changes'], 'mode'), true);
        if (count($phases) < 2) {
            return $phases;
        }
        $out = [];
        $carry = [];
        foreach ($phases as $p) {
            if ($len($p) < ($weak($p) ? self::MINOR_UNDATED_M : self::MINOR_M)) {
                $carry = array_merge($carry, array_map(fn ($c) => ['minor' => true] + $c, array_values($p['changes'])));

                continue;
            }
            $p['changes'] = array_merge($carry, array_values($p['changes']));
            $carry = [];
            $out[] = $p;
        }
        if ($carry !== []) {
            if ($out === []) {
                $last = end($phases);
                $last['changes'] = array_map(fn ($c) => ['minor' => false] + $c, $carry);

                return [$last];
            }
            $out[count($out) - 1]['changes'] = array_merge($out[count($out) - 1]['changes'], $carry);
        }

        return $out;
    }

    /**
     * Nature d'une phase, d'après son plus grand changement : growth (mise en service),
     * traffic (changement de trafic), decline (fermeture), removal (dépose), heritage.
     *
     * @param  list<array{from: string, to: string, length_m: int}>  $changes
     */
    public static function kind(array $changes): string
    {
        $c = $changes[0] ?? null;
        if ($c === null) {
            return 'none';
        }
        $fromActive = self::isActive($c['from']);
        $toActive = self::isActive($c['to']);

        return match (true) {
            $c['to'] === 'heritage' || $c['to'] === 'velorail' => 'heritage',
            $toActive && ! $fromActive => 'growth',
            $toActive => 'traffic',
            in_array($c['to'], self::GONE, true) => 'removal',
            default => 'decline',
        };
    }

    /**
     * Lecture en base puis calcul. Renvoie les portions (géométrie simplifiée), les
     * phases, l'index de la phase « au meilleur » et les événements qui datent une phase.
     *
     * @return array{portions: list<array>, phases: list<array>, best: ?int, used_events: list<int>, decades: list<array>, unexplained: list<array>}
     */
    public static function forLine(object $line, ?string $today = null): array
    {
        $today ??= now()->toDateString();
        $eras = array_map(fn ($e) => ['year' => (int) $e->year, 'is_current' => (bool) $e->is_current],
            DB::select('SELECT year, is_current FROM eras ORDER BY year'));

        $rows = DB::select('SELECT md5(ST_AsEWKB(ls.geom)) AS k, e.year, ls.status, ls.traffic_type, ls.is_heritage, ls.heritage_kind,
                   ls.source, '.Lgv::sql('l', 'ls').' AS is_lgv
            FROM line_segments ls JOIN eras e ON e.id = ls.era_id JOIN lines l ON l.id = ls.line_id
            WHERE ls.line_id = ?', [$line->id]);
        if (! $rows) {
            return ['portions' => [], 'phases' => [], 'best' => null, 'used_events' => [], 'decades' => [], 'unexplained' => []];
        }
        $states = [];
        // Provenance de la géométrie de chaque portion (toutes ères confondues) : l'API ne la
        // redistribue que si toutes ces sources le permettent (LineTraceExport).
        $sources = [];
        foreach ($rows as $r) {
            $states[$r->k][(int) $r->year] = self::category($r->status, $r->traffic_type, (bool) $r->is_heritage, $r->heritage_kind, (bool) $r->is_lgv);
            if (! in_array($r->source, $sources[$r->k] ?? [], true)) {
                $sources[$r->k][] = $r->source;
            }
        }

        // Géométrie de chaque portion (une fois), ordonnée le long de la ligne : PK si connu,
        // sinon distance du début de la portion à l'extrémité ouest-nord de la ligne.
        DB::statement('DROP TABLE IF EXISTS tmp_line_portions');
        DB::statement('CREATE TEMP TABLE tmp_line_portions AS
            SELECT DISTINCT ON (md5(ST_AsEWKB(geom))) md5(ST_AsEWKB(geom)) AS k, geom,
                   COALESCE(length_meters, ROUND(ST_Length(geom::geography)))::int AS len, pk_start_m
            FROM line_segments WHERE line_id = ? ORDER BY md5(ST_AsEWKB(geom)), id', [$line->id]);
        $geoms = DB::select('WITH o AS (SELECT ST_Envelope(ST_Collect(geom)) AS env FROM tmp_line_portions)
            SELECT t.k, t.len, ST_AsGeoJSON(ST_SimplifyPreserveTopology(t.geom, 0.00003), 5) AS gj
            FROM tmp_line_portions t, o
            ORDER BY t.pk_start_m NULLS LAST,
                     ST_Distance(ST_StartPoint(t.geom), ST_SetSRID(ST_MakePoint(ST_XMin(o.env), ST_YMax(o.env)), 4326)), t.k');
        $portions = [];
        foreach ($geoms as $g) {
            $portions[] = ['key' => $g->k, 'length_m' => (int) $g->len, 'states' => $states[$g->k] ?? [],
                'geometry' => json_decode($g->gj, true), 'sources' => $sources[$g->k] ?? []];
        }

        // Portions qui se touchent (continuité) : extrémités communes, à 3 m près.
        $ends = DB::select('SELECT k, ST_X(ST_StartPoint(geom)) AS x1, ST_Y(ST_StartPoint(geom)) AS y1,
                ST_X(ST_EndPoint(geom)) AS x2, ST_Y(ST_EndPoint(geom)) AS y2 FROM tmp_line_portions');
        $points = [];
        foreach ($ends as $r) {
            $points[] = [$r->k, (float) $r->x1, (float) $r->y1];
            $points[] = [$r->k, (float) $r->x2, (float) $r->y2];
        }
        $adjacency = self::touching($points, 0.00003);
        $adjacencyLoose = self::touching($points, self::LOOSE_GAP_DEG);

        // Points témoins de chaque portion et autres portions à moins de RETRACE_TOL_M (retracés).
        $near = [];
        foreach ($portions as $p) {
            $near[$p['key']] = array_fill(0, count(self::SAMPLE_FRACTIONS), []);
        }
        DB::statement('CREATE INDEX ON tmp_line_portions USING GIST (geom)');
        foreach (DB::select('WITH pts AS MATERIALIZED (
                    SELECT t.k, f.n, ST_LineInterpolatePoint(t.geom, f.f) AS p
                    FROM tmp_line_portions t, unnest(CAST(? AS float8[])) WITH ORDINALITY AS f(f, n))
                SELECT pts.k, pts.n, o.k AS other FROM pts
                JOIN tmp_line_portions o ON o.k <> pts.k AND o.geom && ST_Expand(pts.p, 0.0008)
                 AND ST_DWithin(o.geom::geography, pts.p::geography, ?)',
            ['{'.implode(',', self::SAMPLE_FRACTIONS).'}', self::RETRACE_TOL_M]) as $r) {
            $near[$r->k][(int) $r->n - 1][] = $r->other;
        }
        // Continuité élargie : aussi les portions accolées en cours de tracé (doublons parallèles,
        // bouts raccordés au milieu d'une longue portion), dans les deux sens.
        foreach ($near as $k => $lists) {
            foreach ($lists as $others) {
                foreach ($others as $o) {
                    if (! in_array($o, $adjacencyLoose[$k] ?? [], true)) {
                        $adjacencyLoose[$k][] = $o;
                    }
                    if (! in_array($k, $adjacencyLoose[$o] ?? [], true)) {
                        $adjacencyLoose[$o][] = $k;
                    }
                }
            }
        }

        $events = self::events($line);
        $localized = array_keys(array_filter($events, fn ($e) => $e['scope'] === 'localized'));
        // Un événement localisé concerne une portion quand au moins COVER_RATIO de ses points
        // témoins (5, répartis le long de la portion) sont à moins de COVER_TOL_M de sa section.
        $cover = [];
        if ($localized) {
            foreach (DB::select('WITH ev AS MATERIALIZED (
                        SELECT id, ST_Transform(section_geom, 2154) AS g FROM line_events WHERE id = ANY(CAST(? AS bigint[]))),
                    pts AS MATERIALIZED (
                        SELECT t.k, ST_Transform(ST_LineInterpolatePoint(t.geom, f), 2154) AS p
                        FROM tmp_line_portions t, unnest(ARRAY[0.1, 0.3, 0.5, 0.7, 0.9]) AS f)
                    SELECT ev.id, pts.k FROM ev JOIN pts ON ST_DWithin(ev.g, pts.p, ?)
                    GROUP BY ev.id, pts.k HAVING COUNT(*) >= ?',
                ['{'.implode(',', $localized).'}', self::COVER_TOL_M, (int) ceil(5 * self::COVER_RATIO)]) as $r) {
                $cover[(int) $r->id][] = $r->k;
            }
        }
        DB::statement('DROP TABLE IF EXISTS tmp_line_portions');

        $result = self::compute($eras, $portions, $events, $cover, $adjacency, $today, $near, $adjacencyLoose);

        return [
            'portions' => array_map(fn ($p) => ['length_m' => $p['length_m'], 'geometry' => $p['geometry'], 'sources' => $p['sources']], $portions),
            'phases' => $result['phases'],
            'best' => $result['best'],
            'used_events' => $result['used_events'],
            'decades' => $result['decades'],
            'unexplained' => $result['unexplained'],
        ];
    }

    /**
     * Portions dont une extrémité est à moins de $eps (degrés) d'une extrémité d'une autre
     * (grille de cellules de taille $eps : seules les cellules voisines sont comparées).
     *
     * @param  list<array{0: string, 1: float, 2: float}>  $points  [clé de portion, x, y]
     * @return array<string, list<string>>
     */
    public static function touching(array $points, float $eps): array
    {
        $grid = [];
        foreach ($points as $n => [, $x, $y]) {
            $grid[(int) floor($x / $eps)][(int) floor($y / $eps)][] = $n;
        }
        $adj = [];
        foreach ($points as $n => [$k, $x, $y]) {
            $cx = (int) floor($x / $eps);
            $cy = (int) floor($y / $eps);
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    foreach ($grid[$cx + $dx][$cy + $dy] ?? [] as $m) {
                        [$k2, $x2, $y2] = $points[$m];
                        if ($k2 !== $k && abs($x2 - $x) <= $eps && abs($y2 - $y) <= $eps) {
                            $adj[$k][$k2] = true;
                        }
                    }
                }
            }
        }

        return array_map(fn ($set) => array_keys($set), $adj);
    }

    /**
     * Événements de la ligne, avec leur portée pour le moteur :
     *   localized : portion résolue (section_geom) ;
     *   whole     : toute la ligne (aucune portion citée, ou résolue « toute la ligne ») ;
     *   unlocated : portion citée mais non localisée (non utilisée pour dater) ;
     *   ignored   : antérieure à la grande vitesse sur une LGV (date d'une ligne voisine).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function events(object $line): array
    {
        $isLgv = (bool) ($line->is_lgv ?? false) || Lgv::nameIsLgv($line->name ?? null);
        $out = [];
        foreach (DB::select('SELECT id, type, event_date, date_precision, source, source_url, confidence, section_resolution,
                    section_from, section_to, section_pk_start_m, section_geom IS NOT NULL AS has_geom
                FROM line_events WHERE line_id = ? ORDER BY event_date, id', [$line->id]) as $e) {
            $date = substr((string) $e->event_date, 0, 10);
            $whole = $e->section_resolution === null && $e->section_from === null && $e->section_to === null && $e->section_pk_start_m === null;
            $scope = match (true) {
                $isLgv && EraTimeline::predatesHighSpeed($e->type, $date) => 'ignored',
                $whole || $e->section_resolution === 'whole_line' => 'whole',
                in_array($e->section_resolution, self::USABLE_RESOLUTIONS, true) && $e->has_geom => 'localized',
                default => 'unlocated',
            };
            // Tableaux PDF lignes-oubliees = décisions administratives (priorité basse, cf. EraTimeline).
            $source = $e->source === 'lignes-oubliees' && str_contains((string) $e->source_url, '.pdf') ? 'lignes-oubliees-pdf' : $e->source;
            $out[(int) $e->id] = ['id' => (int) $e->id, 'type' => $e->type, 'date' => $date,
                'precision' => in_array($e->date_precision, ['day', 'month', 'year'], true) ? $e->date_precision : 'day',
                'source' => $source, 'confidence' => $e->confidence, 'scope' => $scope];
        }

        return $out;
    }
}
