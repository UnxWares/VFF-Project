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
 *                      fer de X à Y », « ligne de X à Y », « la ligne X à Y », « chemins de fer à voie étroite de
 *                      X à Y ») ; pour une énumération de destinations, seulement juste après « chemin de fer »
 *                      ou « ligne » (« chemin de fer de X à Z et à Y », « ligne de X à Z, W et Y ») ;
 *   route 3          : à défaut, un trajet entre ces deux lieux (« de X à Y », « entre X et Y », « de X à Z, W
 *                      et Y » sans mot du rail juste avant, deux lieux qui se suivent dans « entre X, Y, Z et W »,
 *                      qui est souvent une étoile de destinations et pas un trajet, une étape et un bout de
 *                      « entre X et Y en passant par Z » ou de « de X à Y par Z ») ;
 *   two_places 1     : à défaut, il nomme seulement deux lieux de la ligne ;
 *   only_line_places 1 : sans trajet, tous les lieux reconnus dans le titre sont sur la ligne (« Lignes de chemin
 *                      de fer de Paris à Rouen, au Mans, à Tours » nomme Paris et Rouen : ce n'est pas la ligne
 *                      de Tours au Mans) ;
 *   ends 2           : ces deux lieux sont les extrémités de la ligne (nom actuel, ancien nom ou gares terminus) ;
 *   rail_word 2      : « chemin de fer », « voie ferrée », « tramway », « railway », « embranchement »,
 *                      « ferroviaire », ou « ligne de X à Y » ;
 *   rail_hint 1      : seulement « gare », « train », « locomotive » ou « wagon » ;
 *   date_in_life 2   : date entre la première date de la ligne et sa fermeture (ou aujourd'hui) ;
 *   date_project 1   : dans les 40 ans avant la première date (projets, enquêtes) ;
 *   date_after_closure 1 : après la fermeture (souvenirs, histoire) ;
 *   date_unknown 0   : document sans date, ou ligne sans date connue ;
 *   date_doubtful 0  : le titre imprime une année à plus de 40 ans de la date de la notice (« inauguration [...]
 *                      le 5 avril 1858 » daté de 1978) : la date ne compte pas ;
 *   doc_type 1       : type retenu ;
 *   secondary_mention 0 : la ligne n'est citée que comme raccordement d'une autre (« raccordement avec la
 *                      ligne de X à Y », « pour former avec le chemin projeté entre X et Y ») : ni trajet ni
 *                      extrémités ne comptent, le document reste sous le seuil.
 * Seuil : THRESHOLD (9 sur 11). Une voie ferrée entre deux gares intermédiaires n'est donc retenue que si la
 * date tombe dans la vie de la ligne ; un simple trajet, seulement entre ses extrémités ; deux lieux nommés sans
 * trajet, seulement s'ils sont ses extrémités, que la date tombe dans sa vie, que le titre parle de chemin de fer
 * et qu'il ne nomme aucun lieu hors de la ligne.
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
        'rail_route' => 4, 'route' => 3, 'two_places' => 1, 'only_line_places' => 1, 'ends' => 2, 'rail_word' => 2,
        'rail_hint' => 1, 'date_in_life' => 2, 'date_project' => 1, 'date_after_closure' => 1, 'date_unknown' => 0,
        'date_doubtful' => 0, 'doc_type' => 1, 'secondary_mention' => 0,
    ];

    private const STRONG_RAIL = '/\b(chemins? de fer|voies? ferrees?|railways?|tramways?|embranchements?|ferroviaires?|c de f)\b/';

    private const RAIL_HINT = '/\b(gares?|trains?|locomotives?|wagons?)\b/';

    private const OTHER_TRANSPORT = '/\b(aeriens?|aeriennes?|aviation|air france|aeroports?|autobus|autocars?|canal|canaux|navigation|paquebots?|bateaux? a vapeur|diligences?|telegraph\w*|telephoniques?|omnibus|metropolitains?|chemins? de fer americains?|routes? (nationales?|royales?|imperiales?|departementales?))\b/';

    /** Mots qui ouvrent un trajet (« de X »), en tenant compte d'un article (« de la Teste »). */
    private const ROUTE_FROM = ['de', 'd', 'du', 'des', 'depuis', 'entre'];

    private const ROUTE_TO = ['a', 'au', 'aux'];

    /** Mots qui ouvrent un trajet sans « de » : « la ligne Paris à Versailles ». */
    private const LINE_WORDS = ['ligne', 'lignes'];

    /** Mots qui lient les lieux d'une énumération (« de Paris à Rouen, au Mans et à Tours »), en plus des virgules. */
    private const LIST_LINKS = ['et', 'a', 'au', 'aux'];

    /** Lieux au plus dans une énumération après « de X à ». */
    private const MAX_LISTED = 6;

    private const ARTICLES = ['le', 'la', 'les', 'l'];

    /** Mots lus avant un trajet pour y chercher une marque de simple mention (SECONDARY_MARKERS). */
    private const SECONDARY_WINDOW = 12;

    /**
     * Mots qui, dans les douze mots avant un trajet, en font une simple mention : la ligne est le point de
     * raccordement d'une autre (« raccordement avec la ligne de Paris à Bordeaux », « partie comprise entre
     * […] et la ligne de Paris à Versailles »), pas le sujet du document.
     */
    private const SECONDARY_MARKERS = ['avec', 'raccordement', 'raccordements', 'jonction', 'rattacher', 'rattachement', 'comprise', 'compris'];

    /**
     * Mots qui, juste après « avec » (articles sautés), en font une marque de simple mention même loin du trajet :
     * « pour former avec le chemin projeté entre Marseille et Lyon », « avec celui projeté entre Le Havre et Paris ».
     */
    private const MENTION_AFTER_AVEC = ['chemin', 'chemins', 'ligne', 'lignes', 'voie', 'voies', 'celui', 'celle', 'ceux', 'celles', 'embranchement', 'embranchements'];

    /**
     * Mots du rail qui, juste avant le « de » d'une énumération de destinations, en font des voies ferrées
     * (« chemin de fer de X à Z, W et Y », « ligne de X à Z et à Y »).
     */
    private const RAIL_BEFORE_LIST = ['fer', 'ferree', 'ferrees', 'ligne', 'lignes', 'tramway', 'tramways', 'railway', 'railways', 'embranchement', 'embranchements'];

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
     *      n'est rattaché à aucune) ; à égalité, une ligne reliée par un trajet passe avant une ligne dont le
     *      titre nomme seulement deux lieux, et un document sans date va à la ligne la plus ancienne.
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
        // Seul le meilleur score reste : une ligne nettement moins bien notée ne garde pas le document.
        $best = max(array_map(fn (ArchiveMatch $m): int => $m->score, $passing));
        $winners = array_values(array_filter($passing, fn (ArchiveMatch $m): bool => $m->score === $best));
        // À égalité, un trajet entre deux lieux de la ligne passe avant deux lieux seulement nommés.
        $routed = array_values(array_filter($winners, fn (ArchiveMatch $m): bool => ! $m->has('two_places')));
        if ($routed !== [] && count($routed) < count($winners)) {
            $winners = $routed;
        }
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
                $kind = $j > $i ? $this->routeKind($tokens, $found, $a, $b) : null;
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
        } else {
            // Sans trajet, un lieu hors de la ligne dit que le titre parle d'une autre ligne (ou de plusieurs).
            if (! $route && array_diff(array_column($found, 'key'), array_keys($line->places)) === []) {
                $reasons[] = 'only_line_places';
            }
            if ($ends) {
                $reasons[] = 'ends';
            }
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
            $reasons[] = $date !== 'date_unknown' && self::titleContradicts($record) ? 'date_doubtful' : $date;
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
     * « d'X à la Y », « entre X et Y », « la ligne X à Y », « Paris - Bordeaux », ou une énumération de destinations depuis X
     * (« de X à Z, au W et à Y » relie X à chacun), deux lieux qui se suivent dans une liste ouverte par « entre »
     * (« entre Paris, Rouen, Le Havre et Dieppe » relie Paris à Rouen, Rouen au Havre, Le Havre à Dieppe), ou une
     * étape et l'un des bouts (« entre X et Y en passant par Z », « de X à Y par Z » relient Z à X et à Y).
     * « de Paris au Havre, de Bordeaux à La Teste » ne relie donc pas Paris à Bordeaux. Renvoie `secondary` quand une
     * marque de simple mention précède le trajet, `rail` quand un mot du rail le précède de cinq mots au plus
     * (« chemin de fer de X à Y », « ligne X à Y »), `route` sinon. Une liste « entre X, Y, Z » et une énumération
     * de destinations sans « chemin de fer » ou « ligne » juste avant le « de » ne donnent qu'un simple trajet :
     * deux villes voisines d'une liste ne sont pas forcément reliées par la ligne.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found  tous les lieux connus nommés dans le titre
     * @param  array{key: string, start: int, end: int}  $a
     * @param  array{key: string, start: int, end: int}  $b
     */
    private function routeKind(array $tokens, array $found, array $a, array $b): ?string
    {
        // « rapide Paris - Bordeaux » : deux lieux séparés par un tiret entouré d'espaces.
        $gap = array_slice($tokens, $a['end'], $b['start'] - $a['end']);
        if (self::spacedDash($tokens[$a['end']]['sep'] ?? '') && array_diff(array_column($gap, 'lower'), self::ARTICLES) === []) {
            return 'route';
        }
        $start = $this->routeStart($tokens, $found, $a, $b);
        if ($start === null) {
            return null;
        }
        [$from, $form] = $start;
        $lineWord = in_array($tokens[$from]['lower'], self::LINE_WORDS, true);
        $context = array_column(array_slice($tokens, max(0, $from - self::SECONDARY_WINDOW), min(self::SECONDARY_WINDOW, $from)), 'lower');
        if (self::mentionMarked($context)) {
            return 'secondary';
        }
        // Le bout d'un trajet et son étape ne sont qu'une partie du trajet décrit, et deux lieux voisins d'une liste
        // « entre X, Y, Z » sont souvent deux destinations d'une étoile : un simple trajet, qui ne passe le seuil que
        // si ce sont les extrémités de la ligne.
        if ($form === 'via' || $form === 'between_list') {
            return 'route';
        }
        // « de X à Z, W et Y » : voie ferrée vers chaque destination seulement après « chemin de fer » ou « ligne ».
        if ($form === 'enumeration') {
            return $lineWord || in_array(end($context), self::RAIL_BEFORE_LIST, true) ? 'rail' : 'route';
        }
        $before = array_slice($context, -5);

        return $lineWord || (array_intersect($before, self::RAIL_BEFORE_ROUTE) !== [] && array_intersect($before, self::NOT_RAIL) === [])
            ? 'rail' : 'route';
    }

    /**
     * Les mots qui précèdent un trajet en font-ils une simple mention (SECONDARY_MARKERS) ? « avec » ne compte que
     * dans les trois derniers mots (« raccordement avec la ligne de X à Y ») ou suivi d'un mot qui désigne une autre
     * voie (« pour former avec le chemin projeté entre X et Y ») : ailleurs, il ouvre souvent autre chose (« carte
     * [...] avec les indications des tracés entre X, Y et Z »).
     *
     * @param  list<string>  $context  mots en minuscules avant le trajet
     */
    private static function mentionMarked(array $context): bool
    {
        $near = count($context) - 3;
        foreach ($context as $k => $word) {
            if (! in_array($word, self::SECONDARY_MARKERS, true)) {
                continue;
            }
            if ($word !== 'avec' || $k >= $near) {
                return true;
            }
            $next = $k + 1;
            while (isset($context[$next]) && in_array($context[$next], self::ARTICLES, true)) {
                $next++;
            }
            if (in_array($context[$next] ?? null, self::MENTION_AFTER_AVEC, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Les mots de $start à $end (exclu), entre deux lieux, mènent-ils du premier au second ? Un seul mot de
     * liaison (« à », « au », « aux », ou « et » après « entre »), articles permis ; après « de X à », une
     * énumération de lieux séparés par des virgules, « et », « à », « au » ou « aux » (« à Rouen, au Mans, à »).
     * Un lieu d'une énumération est un lieu connu, ou un seul nom à majuscule suivi d'une liaison (« Cologne et à »).
     * Rend le nombre de lieux de l'énumération passés avant le second (0 pour un trajet direct), ou null.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     */
    private function leadsTo(array $tokens, array $found, int $start, int $end, bool $between): ?int
    {
        $i = $start;
        $skipArticles = function () use (&$i, $end, $tokens): void {
            while ($i < $end && in_array($tokens[$i]['lower'], self::ARTICLES, true)) {
                $i++;
            }
        };
        $skipArticles();
        if ($i >= $end || ! in_array($tokens[$i]['lower'], $between ? ['et'] : self::ROUTE_TO, true)) {
            return null;
        }
        $i++;
        $skipArticles();
        if ($i === $end) {
            return 0;
        }
        if ($between) {
            return null;
        }
        $starts = array_column($found, 'end', 'start');
        $listed = 0;
        while ($i < $end) {
            if (isset($starts[$i])) {
                $next = $starts[$i];
            } elseif ($tokens[$i]['upper']) {
                $next = $i + 1;
                while ($next < $end && preg_match('/^[-‐]$/u', $tokens[$next]['sep']) === 1) {
                    $next++;
                }
            } else {
                return null;
            }
            if (++$listed > self::MAX_LISTED || $next > $end) {
                return null;
            }
            // Un lieu de l'énumération est suivi d'une virgule ou d'un mot de liaison.
            $linked = str_contains($tokens[$next]['sep'] ?? '', ',');
            $i = $next;
            while ($i < $end && in_array($tokens[$i]['lower'], [...self::LIST_LINKS, ...self::ARTICLES], true)) {
                $linked = true;
                $i++;
            }
            if (! $linked) {
                return null;
            }
        }

        return $listed;
    }

    /**
     * Mot qui ouvre le trajet de $a à $b et sa forme : [position, forme], ou null. Formes : `direct` (« de X à Y »,
     * « entre X et Y »), `enumeration` (« de X à Z, W et Y »), `between_list` (« entre X, Y, Z et W »), `via`
     * (une étape et un bout, « de X à Y par Z »).
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     * @param  array{key: string, start: int, end: int}  $a
     * @param  array{key: string, start: int, end: int}  $b
     * @return array{0: int, 1: string}|null
     */
    private function routeStart(array $tokens, array $found, array $a, array $b): ?array
    {
        // Un seul article permis entre le mot qui ouvre le trajet et le lieu (« de la Teste »).
        $from = $a['start'] - 1;
        if ($from >= 0 && in_array($tokens[$from]['lower'], self::ARTICLES, true)) {
            $from--;
        }
        $opener = $from >= 0 ? $tokens[$from]['lower'] : null;
        $listed = $opener !== null && (in_array($opener, self::LINE_WORDS, true) || in_array($opener, self::ROUTE_FROM, true))
            ? $this->leadsTo($tokens, $found, $a['end'], $b['start'], $opener === 'entre') : null;
        if ($listed !== null) {
            return [$from, $listed > 0 ? 'enumeration' : 'direct'];
        }
        // « entre Paris, Rouen, Le Havre et Dieppe » : deux lieux qui se suivent dans une liste ouverte par « entre ».
        $list = $this->betweenListOpener($tokens, $found, $a['start']);
        if ($list !== null && $this->nextInList($tokens, $a['end'], $b['start'])) {
            return [$list, 'between_list'];
        }
        // « entre X et Y en passant par Z », « de X à Y par Z » : Z est une étape, reliée à X comme à Y.
        $via = $this->viaOpener($tokens, $found, $a, $b);

        return $via !== null ? [$via, 'via'] : null;
    }

    /**
     * Mot « entre » qui ouvre la liste dont fait partie le lieu commençant au mot $start (« entre Paris, Rouen,
     * Le Havre et Dieppe » pour Rouen), ou null. Les lieux de la liste sont séparés par des virgules ; chacun est
     * un lieu connu ou un nom à majuscule de trois mots au plus.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     */
    private function betweenListOpener(array $tokens, array $found, int $start): ?int
    {
        $i = $start;
        for ($items = 0; $items <= self::MAX_LISTED; $items++) {
            $p = $this->skipArticlesBack($tokens, $i - 1);
            if ($p < 0) {
                return null;
            }
            if ($tokens[$p]['lower'] === 'entre') {
                return $p;
            }
            // Le lieu précédent de la liste est séparé de celui-ci par une virgule.
            if (! str_contains($tokens[$p + 1]['sep'], ',')) {
                return null;
            }
            $previous = $this->placeStart($tokens, $found, $p + 1);
            if ($previous === null) {
                return null;
            }
            $i = $previous;
        }

        return null;
    }

    /**
     * Le lieu qui commence au mot $end suit-il directement celui qui finit au mot $start dans une liste (virgule,
     * ou « et », articles permis) ?
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     */
    private function nextInList(array $tokens, int $start, int $end): bool
    {
        $gap = array_column(array_slice($tokens, $start, $end - $start), 'lower');
        $words = array_values(array_diff($gap, self::ARTICLES));
        if ($words === []) {
            return str_contains($tokens[$start]['sep'] ?? '', ',');
        }

        return $words === ['et'];
    }

    /**
     * Trajet avec une étape (« entre X et Y en passant par Z », « de X à Y par Z », « de X à Y via Z ») : quand
     * $b est l'étape Z et $a l'un des deux bouts X ou Y, rend la position du mot qui ouvre le trajet, sinon null.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     * @param  array{key: string, start: int, end: int}  $a
     * @param  array{key: string, start: int, end: int}  $b
     */
    private function viaOpener(array $tokens, array $found, array $a, array $b): ?int
    {
        $m = $this->skipArticlesBack($tokens, $b['start'] - 1);
        if ($m < 0 || ! in_array($tokens[$m]['lower'], ['par', 'via'], true)) {
            return null;
        }
        if ($tokens[$m]['lower'] === 'par' && ($tokens[$m - 1]['lower'] ?? null) === 'passant') {
            $m -= ($tokens[$m - 2]['lower'] ?? null) === 'en' ? 2 : 1;
        }
        // Y, puis le mot de liaison, puis X, puis le mot qui ouvre le trajet.
        $toStart = $this->placeStart($tokens, $found, $m);
        $link = $toStart === null ? -1 : $this->skipArticlesBack($tokens, $toStart - 1);
        if ($link < 0 || ! in_array($tokens[$link]['lower'], ['et', ...self::ROUTE_TO], true)) {
            return null;
        }
        $fromStart = $this->placeStart($tokens, $found, $link);
        $opener = $fromStart === null ? -1 : $this->skipArticlesBack($tokens, $fromStart - 1);
        if ($opener < 0) {
            return null;
        }
        $word = $tokens[$opener]['lower'];
        $opens = $tokens[$link]['lower'] === 'et'
            ? $word === 'entre'
            : $word !== 'entre' && (in_array($word, self::ROUTE_FROM, true) || in_array($word, self::LINE_WORDS, true));
        if (! $opens || ($a['end'] !== $link && $a['end'] !== $m && ! ($a['start'] >= $toStart && $a['end'] <= $m))) {
            return null;
        }

        return $opener;
    }

    /**
     * Premier mot du lieu qui finit juste avant le mot $end (articles de tête compris) : lieu connu, ou nom à
     * majuscule de trois mots au plus (« Le Bourget »). null si aucun lieu n'y finit.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     */
    private function placeStart(array $tokens, array $found, int $end): ?int
    {
        $starts = array_column(array_filter($found, fn (array $f): bool => $f['end'] === $end), 'start');
        if ($starts !== []) {
            $start = min($starts);
        } else {
            $start = $end - 1;
            if ($start < 0 || ! $tokens[$start]['upper']) {
                return null;
            }
            while ($start > 0 && $end - $start < 3 && $tokens[$start - 1]['upper'] && ! str_contains($tokens[$start]['sep'], ',')) {
                $start--;
            }
        }
        while ($start > 0 && in_array($tokens[$start - 1]['lower'], self::ARTICLES, true) && ! str_contains($tokens[$start]['sep'], ',')) {
            $start--;
        }

        return $start;
    }

    /** Position du premier mot qui n'est pas un article, en remontant depuis $i (−1 s'il n'y en a pas). */
    private function skipArticlesBack(array $tokens, int $i): int
    {
        while ($i >= 0 && in_array($tokens[$i]['lower'], self::ARTICLES, true)) {
            $i--;
        }

        return $i;
    }

    /** Tiret entouré d'espaces (« Paris - Bordeaux ») : pas un trait d'union collé (« Paris-Lyon-Méditerranée »). */
    private static function spacedDash(string $sep): bool
    {
        return preg_match('/^\s+[-\x{2013}\x{2014}]\s+$/u', $sep) === 1;
    }

    /**
     * Le titre imprime-t-il des années toutes à plus de PROJECT_YEARS ans de la date de la notice ? (carte
     * d'invitation de 1858 datée de 1978 : la date de la notice n'est pas celle du document)
     */
    private static function titleContradicts(ArchiveRecord $record): bool
    {
        $year = $record->date->year;
        if ($year === null || preg_match_all('/(?<!\d)(1[6-9]\d\d|20[0-4]\d)(?!\d)/', $record->matchText, $m) < 1) {
            return false;
        }
        $to = $record->date->yearTo ?? $year;
        foreach ($m[1] as $printed) {
            $printed = (int) $printed;
            if ($printed >= $year - self::PROJECT_YEARS && $printed <= $to + self::PROJECT_YEARS) {
                return false;
            }
        }

        return true;
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
