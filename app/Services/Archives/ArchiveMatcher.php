<?php

namespace App\Services\Archives;

/**
 * Rattachement d'un document d'archives (notice de la BnF) à une ligne de VFF, par un score explicable.
 * Seul le titre est lu (titre propre et compléments) : jamais le contenu numérisé.
 *
 * Conditions obligatoires (sinon le document est écarté pour la ligne, motif dans `rejected`) :
 *   - le titre nomme au moins deux lieux distincts de la ligne (communes ou gares, extrémités) ;
 *   - le titre parle de chemin de fer (`no_rail_word` sinon) ; un titre qui parle d'un autre transport
 *     (avion, autocar, bateaux à vapeur, canal, navigation, diligence, route, téléphone) est écarté
 *     (`other_transport`), sauf s'il donne le trajet comme une voie ferrée (« chemin de fer de X à Y ») ;
 *   - le type est retenu : texte imprimé, carte, image fixe, manuscrit (`unsupported_type`) ;
 *   - la date n'est pas antérieure à 1820 ni à plus de 40 ans avant la première date de la ligne
 *     (`date_incompatible`).
 *
 * Points :
 *   rail_route 4     : le titre décrit une voie ferrée entre deux lieux de la ligne qui se suivent (« chemin de
 *                      fer de X à Y », « ligne de X à Y », « chemins de fer à voie étroite de X à Y ») ;
 *   route 3          : à défaut, un trajet entre ces deux lieux (« de X à Y », « entre X et Y ») ;
 *   two_places 2     : à défaut, il nomme seulement deux lieux de la ligne ;
 *   ends 2           : ces deux lieux sont les extrémités de la ligne (nom actuel, ancien nom ou gares terminus) ;
 *   rail_word 2      : « chemin de fer », « voie ferrée », « tramway », « railway », « embranchement »,
 *                      « ferroviaire », ou « ligne de X à Y » ;
 *   rail_hint 1      : seulement « gare », « train », « locomotive » ou « wagon » ;
 *   date_in_life 2   : date entre la première date de la ligne et sa fermeture (ou aujourd'hui) ;
 *   date_project 1   : dans les 40 ans avant la première date (projets, enquêtes) ;
 *   date_after_closure 1 : après la fermeture (souvenirs, histoire) ;
 *   date_unknown 0   : document sans date, ou ligne sans date connue ;
 *   doc_type 1       : type retenu ;
 *   secondary_mention 0 : la ligne n'est citée que comme raccordement d'une autre (« raccordement avec la
 *                      ligne de X à Y ») : ni trajet ni extrémités ne comptent, le document reste sous le seuil.
 * Seuil : THRESHOLD (9 sur 11). Une voie ferrée entre deux gares intermédiaires n'est donc retenue que si la
 * date tombe dans la vie de la ligne ; un simple trajet ou deux lieux nommés, seulement s'ils sont ses extrémités.
 */
final class ArchiveMatcher
{
    public const THRESHOLD = 9;

    /** Score le plus haut : trajet, extrémités, mot du rail, date dans la vie de la ligne, type. */
    public const MAX_SCORE = 11;

    /** Années avant la première date connue de la ligne où un document peut encore en parler (projets). */
    public const PROJECT_YEARS = 40;

    /** Aucun document sur une ligne française avant cette année. */
    public const EARLIEST_YEAR = 1820;

    /** Un intervalle de dates plus large ne permet pas de juger la date. */
    public const MAX_RANGE_YEARS = 10;

    /** Lignes qui peuvent se partager un même document à score égal ; au-delà, il est jugé ambigu. */
    public const MAX_TIED_LINES = 2;

    public const POINTS = [
        'rail_route' => 4, 'route' => 3, 'two_places' => 2, 'ends' => 2, 'rail_word' => 2, 'rail_hint' => 1,
        'date_in_life' => 2, 'date_project' => 1, 'date_after_closure' => 1, 'date_unknown' => 0, 'doc_type' => 1,
        'secondary_mention' => 0,
    ];

    private const STRONG_RAIL = '/\b(chemins? de fer|voies? ferrees?|railways?|tramways?|embranchements?|ferroviaires?|c de f)\b/';

    private const RAIL_HINT = '/\b(gares?|trains?|locomotives?|wagons?)\b/';

    private const OTHER_TRANSPORT = '/\b(aeriens?|aeriennes?|aviation|air france|aeroports?|autobus|autocars?|canal|canaux|navigation|paquebots?|bateaux? a vapeur|diligences?|telegraph\w*|telephoniques?|omnibus|metropolitains?|chemins? de fer americains?|routes? (nationales?|royales?|imperiales?|departementales?))\b/';

    /** Mots qui ouvrent un trajet (« de X »), en tenant compte d'un article (« de la Teste »). */
    private const ROUTE_FROM = ['de', 'd', 'du', 'des', 'depuis', 'entre'];

    private const ROUTE_TO = ['a', 'au', 'aux'];

    private const ARTICLES = ['le', 'la', 'les', 'l'];

    /** Mots lus avant un trajet pour y chercher une marque de simple mention (SECONDARY_MARKERS). */
    private const SECONDARY_WINDOW = 12;

    /**
     * Mots qui, dans les douze mots avant un trajet, en font une simple mention : la ligne est le point de
     * raccordement d'une autre (« raccordement avec la ligne de Paris à Bordeaux », « partie comprise entre
     * […] et la ligne de Paris à Versailles »), pas le sujet du document.
     */
    private const SECONDARY_MARKERS = ['avec', 'raccordement', 'raccordements', 'jonction', 'rattacher', 'rattachement', 'comprise', 'compris'];

    /** Le « chemin de fer américain » est un tramway à chevaux, pas une voie ferrée. */
    private const NOT_RAIL = ['americain', 'americains'];

    /**
     * Mots du rail qui, dans les cinq mots avant « de X à Y », font du trajet une voie ferrée (« chemin de fer de
     * X à Y », « chemins de fer à voie étroite de X à Y », « chemin de fer d'intérêt local de X à Y »).
     */
    private const RAIL_BEFORE_ROUTE = ['fer', 'ferree', 'ferrees', 'ligne', 'lignes', 'embranchement', 'embranchements', 'raccordement', 'tramway', 'tramways', 'railway', 'railways'];

    /**
     * Jugement du document pour chaque ligne dont le titre nomme au moins deux lieux.
     *
     * @return list<ArchiveMatch>
     */
    public function candidates(ArchiveRecord $record, LinePlaceIndex $index): array
    {
        $tokens = TitlePlaces::tokens($record->matchText);
        $found = TitlePlaces::find($tokens, $index->keys(), $index->maxWords(), TitlePlaces::allCaps($record->matchText));
        $keys = array_values(array_unique(array_column($found, 'key')));
        if (count($keys) < 2) {
            return [];
        }
        $lineIds = [];
        foreach ($keys as $key) {
            foreach ($index->keys()[$key] ?? [] as $id) {
                $lineIds[$id] = ($lineIds[$id] ?? 0) + 1;
            }
        }
        $text = implode(' ', array_column($tokens, 'lower'));
        $out = [];
        foreach ($lineIds as $id => $count) {
            $line = $index->line($id);
            if ($count >= 2 && $line !== null) {
                $out[] = $this->judge($record, $line, $tokens, $found, $text);
            }
        }

        return $out;
    }

    /**
     * Lignes retenues pour un document, parmi celles qui atteignent le seuil :
     *   1. la ligne la plus précise : une ligne dont les lieux reconnus sont une partie stricte de ceux d'une
     *      autre est écartée (« de Paris à Rouen par Gisors » va à la ligne qui passe par Gisors) ;
     *   2. une ligne qui existait à la date du document passe avant une ligne alors en projet (« chemin de fer
     *      de Paris à Rouen » en 1843 parle de la ligne ouverte cette année-là, pas d'une ligne de 1868) ;
     *   3. le meilleur score, partagé par MAX_TIED_LINES lignes au plus (au-delà, le document est ambigu et
     *      n'est rattaché à aucune) ; un document sans date partagé entre des lignes d'âges différents va à
     *      la plus ancienne.
     *
     * @param  list<ArchiveMatch>  $candidates
     * @return list<ArchiveMatch>
     */
    public function winners(array $candidates, int $threshold = self::THRESHOLD): array
    {
        $passing = array_values(array_filter($candidates, fn (ArchiveMatch $m): bool => $m->passes($threshold)));
        $passing = array_values(array_filter($passing, function (ArchiveMatch $m) use ($passing): bool {
            foreach ($passing as $other) {
                if (count($other->keys) > count($m->keys) && array_diff($m->keys, $other->keys) === []) {
                    return false;
                }
            }

            return true;
        }));
        $existing = array_values(array_filter($passing, fn (ArchiveMatch $m): bool => $m->has('date_in_life')));
        if ($existing !== []) {
            $passing = array_values(array_filter($passing, fn (ArchiveMatch $m): bool => ! $m->has('date_project')));
        }
        if ($passing === []) {
            return [];
        }
        $best = max(array_map(fn (ArchiveMatch $m): int => $m->score, $passing));
        $winners = array_values(array_filter($passing, fn (ArchiveMatch $m): bool => $m->score === $best));
        // Document sans date partagé entre plusieurs lignes : la plus ancienne (les documents libres de Gallica
        // sont anciens ; « Inauguration du chemin de fer de Paris à Strasbourg » ne parle pas de la LGV).
        $since = array_filter(array_map(fn (ArchiveMatch $m): ?int => $m->has('date_unknown') ? $m->lineSince : null, $winners));
        if (count($winners) > 1 && count($since) === count($winners) && count(array_unique($since)) > 1) {
            $oldest = min($since);
            $winners = array_values(array_filter($winners, fn (ArchiveMatch $m): bool => $m->lineSince === $oldest));
        }

        return count($winners) <= self::MAX_TIED_LINES ? $winners : [];
    }

    /**
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     */
    public function judge(ArchiveRecord $record, LinePlaces $line, array $tokens, array $found, string $text): ArchiveMatch
    {
        $mine = array_values(array_filter($found, fn (array $f): bool => isset($line->places[$f['key']])));
        $names = array_values(array_unique(array_map(fn (array $f): string => $line->places[$f['key']], $mine)));
        $reasons = [];
        $reject = null;

        $route = false;
        $railRoute = false;
        $secondary = false;
        $ends = false;
        foreach ($mine as $i => $a) {
            foreach ($mine as $j => $b) {
                if ($a['key'] === $b['key']) {
                    continue;
                }
                $ends = $ends || $line->areEnds($a['key'], $b['key']);
                $kind = $j > $i ? $this->routeKind($tokens, $a, $b) : null;
                $secondary = $secondary || $kind === 'secondary';
                if ($kind === 'rail' || $kind === 'route') {
                    $route = true;
                    $railRoute = $railRoute || $kind === 'rail';
                }
            }
        }
        $reasons[] = $railRoute ? 'rail_route' : ($route ? 'route' : 'two_places');
        // Ligne seulement citée comme raccordement d'une autre : ses extrémités nommées ne comptent pas.
        if (! $route && $secondary) {
            $reasons[] = 'secondary_mention';
        } elseif ($ends) {
            $reasons[] = 'ends';
        }

        $strong = $railRoute || preg_match(self::STRONG_RAIL, $text) === 1;
        if ($strong) {
            $reasons[] = 'rail_word';
        } elseif (preg_match(self::RAIL_HINT, $text) === 1) {
            $reasons[] = 'rail_hint';
        } else {
            $reject = 'no_rail_word';
        }
        // Un autre transport nommé (bateaux à vapeur, canal, autocar…) l'emporte, sauf trajet donné comme voie ferrée.
        if (! $railRoute && preg_match(self::OTHER_TRANSPORT, $text) === 1) {
            $reject ??= 'other_transport';
        }

        $date = $this->dateReason($record->date, $line);
        if ($date === null) {
            $reject ??= 'date_incompatible';
        } else {
            $reasons[] = $date;
        }

        if ($record->docType === null) {
            $reject ??= 'unsupported_type';
        } else {
            $reasons[] = 'doc_type';
        }

        $detailed = array_map(fn (string $code): array => ['code' => $code, 'points' => self::POINTS[$code]], $reasons);

        return new ArchiveMatch(
            lineId: $line->id,
            score: array_sum(array_column($detailed, 'points')),
            reasons: $detailed,
            places: $names,
            rejected: $reject,
            keys: array_values(array_unique(array_column($mine, 'key'))),
            lineSince: $line->firstYear,
        );
    }

    /**
     * Trajet entre deux lieux reconnus (a avant b), les deux lieux se suivant : « de X à Y », « du X au Y »,
     * « d'X à la Y », « entre X et Y ». « de Paris au Havre, de Bordeaux à La Teste » ne relie donc pas Paris
     * à Bordeaux. Renvoie `rail` quand un mot du rail précède le trajet de trois mots au plus (« chemin de
     * fer de X à Y », « ligne de X à Y », « voie ferrée du X au Y »), `route` sinon.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  array{key: string, start: int, end: int}  $a
     * @param  array{key: string, start: int, end: int}  $b
     */
    private function routeKind(array $tokens, array $a, array $b): ?string
    {
        $from = $a['start'] - 1;
        if ($from >= 0 && in_array($tokens[$from]['lower'], self::ARTICLES, true)) {
            $from--;
        }
        if ($from < 0 || ! in_array($tokens[$from]['lower'], self::ROUTE_FROM, true)) {
            return null;
        }
        $between = array_values(array_diff(
            array_column(array_slice($tokens, $a['end'], $b['start'] - $a['end']), 'lower'),
            self::ARTICLES,
        ));
        $to = $tokens[$from]['lower'] === 'entre' ? ['et'] : self::ROUTE_TO;
        if (count($between) !== 1 || ! in_array($between[0], $to, true)) {
            return null;
        }
        $context = array_column(array_slice($tokens, max(0, $from - self::SECONDARY_WINDOW), min(self::SECONDARY_WINDOW, $from)), 'lower');
        if (array_intersect($context, self::SECONDARY_MARKERS) !== []) {
            return 'secondary';
        }
        $before = array_slice($context, -5);

        return array_intersect($before, self::RAIL_BEFORE_ROUTE) !== [] && array_intersect($before, self::NOT_RAIL) === [] ? 'rail' : 'route';
    }

    /** Raison de date, ou null si la date rend le rattachement impossible. */
    private function dateReason(ArchiveDate $date, LinePlaces $line): ?string
    {
        $year = $date->year;
        if ($year === null || ($date->yearTo !== null && $date->yearTo - $year > self::MAX_RANGE_YEARS)) {
            if ($year !== null && ($date->yearTo ?? $year) < self::EARLIEST_YEAR) {
                return null;
            }

            return 'date_unknown';
        }
        if ($year < self::EARLIEST_YEAR) {
            return null;
        }
        $last = $line->openToday ? null : $line->lastYear;
        if ($line->firstYear === null) {
            return $last !== null && $year > $last ? 'date_after_closure' : 'date_unknown';
        }
        if ($year < $line->firstYear - self::PROJECT_YEARS) {
            return null;
        }
        if ($year < $line->firstYear) {
            return 'date_project';
        }

        return $last !== null && $year > $last ? 'date_after_closure' : 'date_in_life';
    }
}
