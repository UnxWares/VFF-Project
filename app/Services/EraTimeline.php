<?php

namespace App\Services;

use App\Support\SourceRights;

/**
 * Chronologie d'UNE portion de ligne : rejoue ses événements datés et donne
 * son état à une date donnée (snapshot d'une ère décennale).
 *
 * Règle de snapshot : l'ère Y montre l'état au 31 décembre de l'année Y. Un
 * événement daté de l'année Y (même connu à l'année seulement) s'applique
 * donc à l'ère Y. L'ère COURANTE (2030) montre l'état au jour même (consigne
 * utilisateur : le 19/10/2026, « 2030 » veut dire le 19/10/2026 ; demain, le
 * 20/10/2026) : un événement daté après aujourd'hui n'y figure pas ($asOf). Ex. : ouverte en entier en 1970, fermée en partie en 1980
 * → dans l'ère 1980, la partie fermée n'est plus active, le reste l'est.
 *
 * Trafic : on suit deux services indépendants, voyageurs (P) et fret (F) :
 *   opening / reopening            → P + F (tout trafic)
 *   opening_passenger / reopening_passenger → + P ; idem _freight → + F
 *   closure                        → plus aucun trafic
 *   closure_passenger / _freight   → − P / − F (ex. fermée aux voyageurs → fret seul)
 *   dismantling                    → voie déposée
 *   conversion                     → voie déposée ET emprise reconvertie (reuse :
 *                                    greenway = voie verte, road…), cf. line_events.reuse
 *   declassification               → plus aucun trafic (déclassée)
 *   heritage_start / heritage_end  → exploitation par une ASSOCIATION (heritage_kind :
 *                                    tourist_train, velorail, museum) quand il n'y a plus
 *                                    de trafic commercial ; la voie est alors en place
 *
 * Plusieurs sources décrivent souvent le MÊME événement à quelques années près
 * (ex. ouverture 1853 selon une source, 1859 selon une autre) : `dedupe()`
 * ne garde que la source la plus fiable par type d'événement et par fenêtre de
 * DEDUPE_WINDOW_YEARS. Exception : la fin d'un service (voyageurs, fret) dont la meilleure
 * source ne dit rien vient d'une autre source (cf. serviceClosuresNotCovered : une fermeture
 * aux voyageurs « toute la ligne » n'est plus masquée par une fermeture totale ou au fret
 * localisée, la portion est « fret » entre les deux et non plus « mixte »).
 */
class EraTimeline
{
    /** Fiabilité des sources de dates (plus petit = plus fiable). */
    public const SOURCE_PRIORITY = [
        'manual' => 0,
        'contrib' => 1,
        'lignes-oubliees' => 2,
        'sncf' => 3,
        'wikipedia' => 4,
        // Événement contribué qui cite une page web secondaire hors des sources ci-dessus (blog,
        // site d'association…) : rangé comme Wikipédia (cf. rankedSource).
        'contrib-web' => 4,
        // Articles Wikipédia des réseaux secondaires (une fiche par réseau, lignes en tableau).
        'wikipedia-reseaux' => 5,
        // Argentan Web Ferroviaire (faits relus des articles des lignes de l'Orne, vff:apply-argentanwebferro) :
        // après Wikipédia (dates surtout à l'année, sans source primaire citée fait par fait), avant
        // OpenHistoricalMap (sections réelles, 88 % d'accord sur les dates comparables ; décision du 2026-11-20).
        'argentanwebferro' => 5,
        'ohm' => 6,
        // Tableaux PDF de lignes-oubliees : DÉCISIONS administratives de fermeture ou de
        // déclassement, souvent bien après la fin réelle du trafic (ex. 436000 : décision
        // 2018 pour un trafic arrêté en 1987). Retenues seulement à défaut d'autre source.
        'lignes-oubliees-pdf' => 7,
        'wikidata' => 8,
        // Dates ESTIMÉES d'après la compagnie (vff:import-secondary-network-dates) : jamais une date sûre.
        'estimation-reseau' => 9,
    ];

    /**
     * Fournisseur du lien cité par un événement contribué (registre des droits) → source du moteur
     * dont il prend le rang : un contributeur qui cite Wikipédia ne pèse pas plus que Wikipédia.
     */
    public const CITED_SOURCES = [
        'lignes-oubliees' => 'lignes-oubliees',
        'sncf' => 'sncf',
        'wikipedia' => 'wikipedia',
        'ohm' => 'ohm',
        'osm' => 'osm',
        'wikidata' => 'wikidata',
        'archeoferroviaire' => 'archeoferroviaire',
    ];

    public const DEDUPE_WINDOW_YEARS = 3;

    /**
     * Premier service voyageurs en France : le 1er mars 1832, sur la ligne de Saint-Étienne à
     * Andrézieux, première voie ferrée de France (« Le 1er mars 1832, la ligne est ouverte aux
     * voyageurs », https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-%C3%89tienne_%C3%A0_Andr%C3%A9zieux&oldid=239572220 ;
     * Saint-Étienne - Lyon : « quelques passagers étant acceptés » le 3 avril 1832). Règle générale
     * « ouverture sans voyageurs » : avant cette date, une voie ouverte (même par un « opening » sans
     * précision du trafic) ne portait que des marchandises ; l'ère 1830 (état au 31/12/1830) est donc
     * tout entière fret (contrôle qualité du 2026-10-20 : trois tronçons « mixtes » en 1830).
     */
    public const FIRST_PASSENGER_SERVICE = '1832-03-01';

    /** Première mise en service d'une ligne à grande vitesse en France (Paris - Lyon, 27/09/1981). */
    public const HIGH_SPEED_FIRST_YEAR = 1981;

    /** Ancienneté max (ans) d'une fermeture « récente », qui prime sur un statut SNCF d'exploitation (cf. isRecentSourcedClosure). */
    public const RECENT_CLOSURE_YEARS = 10;

    /** Écart (ans) au-delà duquel deux ouvertures par portion se contredisent (cf. earlierConflictingOpening). */
    public const OPENING_CONFLICT_YEARS = 20;

    /** Écart max entre la première ouverture « toute la ligne » et son achèvement (cf. completionOpening). */
    public const COMPLETION_WINDOW_YEARS = 40;

    /** Ordre d'application des événements d'une même date. */
    private const TYPE_ORDER = [
        'opening' => 0, 'opening_passenger' => 0, 'opening_freight' => 0,
        'reopening' => 1, 'reopening_passenger' => 1, 'reopening_freight' => 1,
        'closure_passenger' => 2, 'closure_freight' => 2, 'closure' => 3,
        'declassification' => 4, 'dismantling' => 5, 'conversion' => 6,
        'heritage_end' => 7, 'heritage_start' => 8,
    ];

    /** @var array<int, array{type: string, date: string, source: ?string, reuse?: ?string, whole?: bool, heritage_kind?: ?string}> */
    private array $events;

    /**
     * @param  array<int, array{type: string, date: string, source?: ?string, reuse?: ?string}>  $events  date au format Y-m-d
     * @param  string  $defaultServices  services ouverts par un `opening` générique : 'PF' (défaut), 'F' (réseau
     *                                   industriel), '' (trafic inconnu, ex. lignes militaires : en service, type non qualifié)
     * @param  bool  $passengerImpliesFreight  règle métier « si y'a du passager, y'a du fret » : une portion
     *                                         ouverte aux voyageurs est mixte. Désactivée pour les LGV (voyageurs seuls).
     */
    public function __construct(array $events, private string $defaultServices = 'PF', private bool $passengerImpliesFreight = true)
    {
        $relevant = array_values(array_filter(
            $events,
            fn ($e) => isset(self::TYPE_ORDER[$e['type']]),
        ));
        $this->events = self::dedupe($relevant);
        usort($this->events, fn ($a, $b) => [$a['date'], self::TYPE_ORDER[$a['type']]] <=> [$b['date'], self::TYPE_ORDER[$b['type']]]);
    }

    /** États actuels observés qui prouvent que la voie a DISPARU (cf. table present_states). */
    public const GONE_STATES = ['dismantled', 'greenway', 'road', 'built_over'];

    private ?string $presentState = null;

    private ?int $presentSince = null;

    private bool $goneByDefault = false;

    private bool $heritageEndsCommercialTraffic = false;

    /**
     * Une exploitation associative (heritage_start) sans fermeture connue avant elle met fin
     * au trafic commercial à sa date : la portion devient patrimoine (ex. Abreschviller -
     * Grand-Soldat, ligne particulière de 1884, train touristique depuis 1968). Sinon la
     * portion resterait « en service fret » sous le train touristique. À activer pour les
     * lignes particulières et secondaires, et pour le réseau principal là où aucune donnée
     * SNCF actuelle ne montre de trafic commercial (cf. vff:build-era-segments).
     */
    public function heritageEndsCommercialTraffic(bool $ends = true): self
    {
        $this->heritageEndsCommercialTraffic = $ends;

        return $this;
    }

    /**
     * Ce qu'on sait de la portion AUJOURD'HUI (preuve de terrain) :
     *   - $state : track_in_place | dismantled | greenway | road | built_over | null ;
     *   - $sinceYear : année du changement d'état si connue (ex. ouverture de la voie verte) ;
     *   - $goneByDefault : faute d'observation, la portion est-elle réputée disparue
     *     (tracé de ligne disparue archeoferroviaire) ?
     */
    public function withPresentState(?string $state, ?int $sinceYear = null, bool $goneByDefault = false): self
    {
        $this->presentState = $state;
        $this->presentSince = $sinceYear;
        $this->goneByDefault = $goneByDefault;

        return $this;
    }

    /**
     * État de la portion au 31/12 de l'année donnée, ou null si elle ne doit
     * pas apparaître sur la carte de cette ère.
     *
     * Règle d'affichage des voies disparues (consigne utilisateur) : une voie
     * n'apparaît « déposée » que sur la carte de la DÉCENNIE qui suit sa dépose,
     * puis plus du tout. Avant : en service ou fermée (voie en place).
     *   - dépose datée D : « déposée » si Y-10 < D <= Y, absente si D <= Y-10 ;
     *   - dépose prouvée aujourd'hui mais non datée : on la place juste après la
     *     fermeture C (fermée si Y-10 < C <= Y, déposée si Y-20 < C <= Y-10,
     *     absente avant) ; si l'observation donne l'année (voie verte ouverte en
     *     2021…), c'est elle qui sert de date de dépose ;
     *   - voie en place (track_in_place, ou aucune preuve de disparition) :
     *     reste « fermée » tant qu'elle existe.
     * « La décennie qui suit » = l'ère dont l'instantané précédent ($previousYear,
     * Y-10 par défaut) est antérieur à la dépose. Pour l'ère courante (état au jour
     * J, ex. 2026), l'ère précédente est 2020 : une voie déposée en 2017 apparaît
     * « déposée » en 2020, puis plus du tout (et non une seconde fois en 2030).
     *
     * @return array{status: string, traffic: string, reuse?: string}|null `reuse`
     *                                                                     seulement pour une emprise reconvertie
     */
    public function stateAt(int $year, bool $isCurrent = false, ?int $previousYear = null, ?string $asOf = null): ?array
    {
        $prev = $previousYear ?? $year - 10;
        $opened = false;
        $p = false;
        $f = false;
        $unqualified = false; // en service, mais on ne sait pas quel trafic
        $dismantled = false;
        $goneYear = null;
        $closedYear = null;
        $reuse = null;
        $heritage = null;
        // Date de l'instantané : le 31/12 de l'ère, ou le jour même pour l'ère courante ($asOf).
        $limit = $asOf ?? sprintf('%04d-12-31', $year);
        // Dépose DATÉE après cette ère : la voie était encore là, rien à déduire.
        $laterDatedRemoval = false;
        // (Une voie verte posée À CÔTÉ de rails observés en place n'est pas une dépose.)
        $railsSeen = $this->presentState === 'track_in_place';
        // Remise en service PROPRE à la portion après cette ère (réouverture, ouverture localisée,
        // exploitation associative) : la disparition observée aujourd'hui suit la dernière
        // fermeture, pas celle-ci (Pontorson - Moidrey fermée en 1886, plateforme reprise par le
        // tramway en 1901) : pas de dépose déduite avant elle.
        $laterReopening = false;
        foreach ($this->events as $e) {
            if ($e['date'] > $limit && ($e['type'] === 'dismantling' || ($e['type'] === 'conversion' && ! $railsSeen))) {
                $laterDatedRemoval = true;
            }
            if ($e['date'] > $limit && ! ($e['whole'] ?? false)
                && (str_starts_with($e['type'], 'reopening') || str_starts_with($e['type'], 'opening') || $e['type'] === 'heritage_start')) {
                $laterReopening = true;
            }
        }

        foreach ($this->events as $e) {
            if ($e['date'] > $limit) {
                break;
            }
            // Seule une RÉouverture (ou une ouverture propre à la portion) peut remettre
            // en service une portion déjà ouverte puis fermée.
            if ($opened && ($e['whole'] ?? false) && str_starts_with($e['type'], 'opening')) {
                continue;
            }
            $wasActive = $opened && ($p || $f || $unqualified);
            switch ($e['type']) {
                case 'opening':
                case 'reopening':
                    $opened = true;
                    $dismantled = false;
                    $p = str_contains($this->defaultServices, 'P');
                    $f = str_contains($this->defaultServices, 'F');
                    $unqualified = $this->defaultServices === '';
                    break;
                case 'opening_passenger':
                case 'reopening_passenger':
                    $opened = true;
                    $dismantled = false;
                    $p = true;
                    break;
                case 'opening_freight':
                case 'reopening_freight':
                    $opened = true;
                    $dismantled = false;
                    $f = true;
                    break;
                case 'closure_passenger':
                    $p = false;
                    break;
                case 'closure_freight':
                    $f = false;
                    break;
                case 'closure':
                case 'declassification':
                    $p = $f = $unqualified = false;
                    break;
                case 'heritage_start':
                    $heritage = $e['heritage_kind'] ?? 'tourist_train';
                    $opened = true;
                    $dismantled = false;
                    if ($this->heritageEndsCommercialTraffic) {
                        // Reprise par une association sans fermeture connue : c'est la fin du
                        // trafic commercial (cf. heritageEndsCommercialTraffic()).
                        $p = $f = $unqualified = false;
                    }
                    break;
                case 'heritage_end':
                    $heritage = null;
                    break;
                case 'dismantling':
                case 'conversion':
                    $p = $f = $unqualified = false;
                    $heritage = null;
                    $reuse = $e['type'] === 'conversion' ? ($e['reuse'] ?? 'other') : $reuse;
                    if ($e['type'] === 'conversion' && $railsSeen) {
                        // Voie verte construite à côté de la voie restée en place : fermée,
                        // avec l'indication de réutilisation, mais pas déposée.
                        break;
                    }
                    $dismantled = true;
                    $goneYear = (int) substr($e['date'], 0, 4);
                    break;
            }
            $active = $opened && ($p || $f || $unqualified);
            if ($active) {
                $reuse = null; // une réouverture efface une reconversion antérieure
                $goneYear = null;
                $closedYear = null;
            } elseif ($wasActive) {
                $closedYear = (int) substr($e['date'], 0, 4);
            }
        }

        if (! $opened) {
            return null;
        }
        if ($isCurrent && ($p || $f || $unqualified) && ! $dismantled && $this->isGoneToday()) {
            // Disparue aujourd'hui alors qu'aucune fermeture n'est connue : la
            // chronologie est incomplète, mais elle n'est en tout cas plus en service.
            return null;
        }
        if ($unqualified && ! $p && ! $f) {
            return ['status' => 'in_service', 'traffic' => 'unknown'];
        }
        if ($p && $limit < self::FIRST_PASSENGER_SERVICE) {
            // Aucun voyageur avant le premier service voyageurs de France : fret seul.
            $p = false;
            $f = true;
        }
        if ($p) {
            return ['status' => 'in_service', 'traffic' => $f || $this->passengerImpliesFreight ? 'mixed' : 'passenger'];
        }
        if ($f) {
            return ['status' => 'in_service', 'traffic' => 'freight'];
        }
        if ($heritage !== null) {
            // Plus de trafic commercial, mais une association exploite la voie (en place).
            return ['status' => $heritage === 'velorail' ? 'abandoned' : 'in_service', 'traffic' => 'unknown', 'heritage' => $heritage];
        }

        // Plus aucun trafic : fermée (voie en place), déposée, ou plus du tout sur la carte.
        $observedReuse = in_array($this->presentState, ['greenway', 'road'], true) ? $this->presentState : null;
        if (! $dismantled && ! $laterDatedRemoval && ! $laterReopening && $this->isGoneToday()) {
            if ($this->presentSince !== null && $this->presentSince <= $year) {
                $goneYear = $this->presentSince;
            } elseif ($this->presentSince === null && $closedYear !== null) {
                // Dépose non datée : juste après la fermeture. Fermée dans l'ère de la
                // fermeture, « déposée » dans l'ère suivante, absente ensuite.
                if ($closedYear <= $prev - 10) {
                    return null;
                }
                // (Ère courante : fermée récemment et déjà disparue → « déposée » aujourd'hui.)
                $goneYear = $closedYear <= $prev || $isCurrent ? $year : null;
            } elseif ($closedYear === null && $isCurrent) {
                return null; // disparue aujourd'hui, fermeture inconnue
            }
            if ($goneYear !== null) {
                $dismantled = true;
                $reuse ??= $observedReuse;
            }
        }

        if ($dismantled) {
            if ($goneYear !== null && $goneYear <= $prev) {
                return null;
            }
            $state = ['status' => 'dismantled', 'traffic' => 'unknown'];

            return $reuse ? $state + ['reuse' => $reuse] : $state;
        }

        return ['status' => 'abandoned', 'traffic' => 'unknown'] + ($reuse ? ['reuse' => $reuse] : []);
    }

    private function isGoneToday(): bool
    {
        if ($this->presentState !== null) {
            return in_array($this->presentState, self::GONE_STATES, true);
        }

        return $this->goneByDefault;
    }

    /** Vrai si au moins un événement d'ouverture est connu (sinon la portion n'est pas datable). */
    public function isDated(): bool
    {
        foreach ($this->events as $e) {
            // Une exploitation associative datée suffit à placer la portion dans le temps.
            if (str_starts_with($e['type'], 'opening') || str_starts_with($e['type'], 'reopening') || $e['type'] === 'heritage_start') {
                return true;
            }
        }

        return false;
    }

    /**
     * Arbitrage entre sources :
     *   1. pour chaque FAMILLE d'événements (ouvertures, réouvertures,
     *      fermetures, dépose, déclassement), seule la meilleure source qui en
     *      parle pour cette portion est retenue. « Meilleure » = d'abord la plus
     *      PRÉCISE (un événement daté sur cette portion bat un événement global
     *      « toute la ligne », ex. l'année d'achèvement d'une infobox ne doit pas
     *      écraser les ouvertures section par section), puis la plus FIABLE. Ex. : lignes-oubliees dit
     *      « fermée aux voyageurs en 1938, au fret en 1967 » → la « fermeture »
     *      imprécise de Wikidata en 1938 ne doit pas fermer tout le trafic (sauf
     *      fermeture totale plus tardive, cf. lateTotalClosure) ;
     *   2. puis, par type, une seule occurrence par fenêtre de quelques années
     *      (doublons d'une même source, ex. fiche + tableau PDF).
     *
     * Exception pour l'exploitation associative : une date « toute la ligne » d'une source plus
     * fiable passe avant la portion observée d'une source moins fiable (datedWholeHeritage).
     *
     * Exceptions à l'arbitrage des fermetures par famille : fermeture totale plus tardive
     * d'une autre source (lateTotalClosure) et fin d'un SERVICE dont la meilleure source ne
     * dit rien (serviceClosuresNotCovered, ex. fermeture aux voyageurs de 1940 « toute la
     * ligne » face à une fermeture au fret localisée de 2007).
     *
     * @param  array<int, array{type: string, date: string, source?: ?string}>  $events
     * @return array<int, array{type: string, date: string, source: ?string}>
     */
    public static function dedupe(array $events): array
    {
        $rank = fn ($e) => (($e['whole'] ?? false) ? 100 : 0) + self::priority($e['source'] ?? null);
        $best = [];
        foreach ($events as $e) {
            $family = self::family($e['type']);
            $best[$family] = min($best[$family] ?? PHP_INT_MAX, $rank($e));
        }
        foreach (self::HERITAGE_FAMILIES as $family) {
            if (($whole = self::datedWholeHeritage($events, $family)) !== null) {
                $best[$family] = $whole;
            }
        }
        // Ouvertures connues seulement « pour toute la ligne » (infobox : première mise en
        // service, puis achèvement…), toutes sources confondues : une seule est retenue,
        // cf. wholeLineOpening().
        $wholeOpening = ($best['opening'] ?? 0) >= 100 ? self::wholeLineOpening($events) : null;
        $lateClosure = self::lateTotalClosure($events, $rank, $best['closure'] ?? null);
        $serviceClosures = self::serviceClosuresNotCovered($events, $rank, $best['closure'] ?? null);
        $earlierOpening = self::earlierConflictingOpening($events, $rank, $best['opening'] ?? null);
        $events = array_filter($events, fn ($e) => match (true) {
            $e === $lateClosure, in_array($e, $serviceClosures, true) => true,
            self::family($e['type']) === 'opening' && $earlierOpening !== null => $e === $earlierOpening,
            self::family($e['type']) === 'opening' && $wholeOpening !== null => $e === $wholeOpening,
            default => $rank($e) === $best[self::family($e['type'])],
        });

        usort($events, fn ($a, $b) => [self::priority($a['source'] ?? null), $a['date']] <=> [self::priority($b['source'] ?? null), $b['date']]);

        $kept = [];
        foreach ($events as $e) {
            $year = (int) substr($e['date'], 0, 4);
            foreach ($kept as $k) {
                if ($k['type'] === $e['type'] && abs((int) substr($k['date'], 0, 4) - $year) <= self::DEDUPE_WINDOW_YEARS) {
                    continue 2;
                }
            }
            $kept[] = ['type' => $e['type'], 'date' => $e['date'], 'source' => $e['source'] ?? null, 'reuse' => $e['reuse'] ?? null,
                'whole' => $e['whole'] ?? false, 'heritage_kind' => $e['heritage_kind'] ?? null];
        }

        return $kept;
    }

    /** Familles d'événements d'exploitation associative (cf. datedWholeHeritage). */
    private const HERITAGE_FAMILIES = ['heritage_start', 'heritage_end'];

    /**
     * Exploitation associative : exception à « la portion datée passe avant la ligne entière ».
     * Pour heritage_start / heritage_end, la PORTION d'un événement localisé vient en général
     * d'une observation (IGN, OSM : vff:import-heritage-lines) et sa DATE n'est souvent que celle
     * de la source (« attestée » en 2026, confiance basse). Face à un événement « toute la
     * ligne » d'une source PLUS fiable (ex. 'manual' des lignes hors RFN, date et citation
     * sourcées : « Chemin de fer de la baie de Somme, exploitation depuis le 4 juillet 1971 »),
     * c'est la date de ce dernier qui fait foi : sinon la ligne n'apparaissait exploitée par
     * l'association qu'à la date de l'observation. Un événement localisé d'une source aussi ou
     * plus fiable garde la priorité (ex. 4489 : manual « toute la ligne » 1981, manual
     * Le Passage - Les Tilliers 1991 → la portion n'est exploitée qu'à partir de 1991).
     *
     * Retourne le rang (cf. dedupe) des événements « toute la ligne » à retenir pour la
     * famille, ou null si la règle générale s'applique.
     *
     * @param  array<int, array{type: string, source?: ?string, whole?: bool}>  $events
     */
    private static function datedWholeHeritage(array $events, string $family): ?int
    {
        $whole = null;
        $localized = null;
        foreach ($events as $e) {
            if (self::family($e['type']) !== $family) {
                continue;
            }
            $p = self::priority($e['source'] ?? null);
            if ($e['whole'] ?? false) {
                $whole = min($whole ?? PHP_INT_MAX, $p);
            } else {
                $localized = min($localized ?? PHP_INT_MAX, $p);
            }
        }

        return $whole !== null && $localized !== null && $whole < $localized ? 100 + $whole : null;
    }

    /**
     * Ouvertures contradictoires d'une même portion : quand une autre source, datée par
     * portion, la donne ouverte plus de OPENING_CONFLICT_YEARS AVANT toutes les ouvertures
     * de la meilleure source, la plus ancienne l'emporte. La date tardive est presque
     * toujours un autre événement mal typé par l'extraction du texte (électrification,
     * caténaires, double voie, nouveau service, ex. Paris - Brest : « Rambouillet - Le
     * Mans 1937 », « Laval - Rennes 1965 ») ou la reconstruction d'une ligne pionnière
     * (Saint-Étienne - Lyon 1830-1833, « tracé définitif » 1857) : la portion existait déjà.
     *
     * @param  callable(array): int  $rank
     */
    private static function earlierConflictingOpening(array $events, callable $rank, ?int $bestRank): ?array
    {
        if ($bestRank === null || $bestRank >= 100) {
            return null;
        }
        $openings = array_filter($events, fn ($e) => str_starts_with($e['type'], 'opening') && ! ($e['whole'] ?? false));
        $firstBest = min(array_map(fn ($e) => (int) substr($e['date'], 0, 4), array_filter($openings, fn ($e) => $rank($e) === $bestRank)));
        $chosen = null;
        foreach ($openings as $e) {
            if ($rank($e) > $bestRank && (int) substr($e['date'], 0, 4) < $firstBest - self::OPENING_CONFLICT_YEARS
                && ($chosen === null || $e['date'] < $chosen['date'])) {
                $chosen = $e;
            }
        }

        return $chosen;
    }

    /**
     * Exception à l'arbitrage des fermetures par famille : une fermeture TOTALE d'une
     * source moins bien classée est gardée quand
     *   - la meilleure source de la famille n'a pas de fermeture totale et ne ferme qu'UN
     *     des deux services (voyageurs seuls : sans cela la portion resterait « fret seul »
     *     indéfiniment ; fret seul : elle resterait « voyageurs », donc « mixte », ex. 3606
     *     Beaurieux - Pontavert, fret fermé en 1962, fin du réseau en 1966) ;
     *   - elle est datée APRÈS la dernière fermeture de la meilleure source, au-delà de
     *     DEDUPE_WINDOW_YEARS (une fermeture « totale » de 1937 face à une fermeture aux
     *     voyageurs de 1938 est la même, en moins précis) ;
     *   - elle n'est pas « toute la ligne » alors que la meilleure source est par portion
     *     (cf. Culoz - Modane : une date globale ne contredit pas une portion datée).
     * La plus ancienne qui remplit ces conditions est retenue (null sinon).
     *
     * @param  callable(array): int  $rank
     */
    private static function lateTotalClosure(array $events, callable $rank, ?int $bestRank): ?array
    {
        if ($bestRank === null) {
            return null;
        }
        $top = array_filter($events, fn ($e) => self::family($e['type']) === 'closure' && $rank($e) === $bestRank);
        $types = array_unique(array_column($top, 'type'));
        if (in_array('closure', $types, true) || count($types) !== 1) {
            return null;
        }
        $lastTop = max(array_map(fn ($e) => (int) substr($e['date'], 0, 4), $top));
        $chosen = null;
        foreach ($events as $e) {
            if ($e['type'] === 'closure' && $rank($e) > $bestRank
                && (int) substr($e['date'], 0, 4) > $lastTop + self::DEDUPE_WINDOW_YEARS
                && ! (($e['whole'] ?? false) && $bestRank < 100)
                && ($chosen === null || $e['date'] < $chosen['date'])) {
                $chosen = $e;
            }
        }

        return $chosen;
    }

    /**
     * Seconde exception à l'arbitrage des fermetures par famille : la fin d'un SERVICE dont
     * la meilleure source des fermetures de la portion ne dit rien vient des autres sources.
     * Fermer aux voyageurs, au fret ou à tout trafic sont des faits distincts : une source
     * qui ne date que la fermeture totale ou au fret d'une section ne contredit pas la
     * fermeture aux voyageurs, bien antérieure, qu'une autre source donne pour toute la
     * ligne. Sans cela, la portion restait « mixte » jusqu'à sa fermeture totale (643000 :
     * fermée aux voyageurs en 1940, « mixte » jusqu'en 2020 sous une fermeture au fret
     * Eauze - Riscle de 2007 ; 413000 Caen - Vire : fermée aux voyageurs en 1938, « mixte »
     * jusqu'aux fermetures par section de 1954 à 1995).
     *   - voyageurs : la meilleure source n'a aucune closure_passenger ;
     *   - fret : la meilleure source n'a ni closure_freight ni fermeture totale (le fret part
     *     en dernier : sa fermeture totale dit quand il a cessé ; les voyageurs, eux, partent
     *     souvent des décennies avant la fermeture totale) ;
     * on garde alors les fermetures de ce service de la meilleure source qui en parle
     * (portion avant « toute la ligne », puis fiabilité), datées AVANT la première fermeture
     * totale de la meilleure source (après, la portion est déjà fermée : ce serait un autre
     * cycle, ou une autre section). Une date connue à l'année seule (confiance basse) reste
     * une information valable : elle n'est pas écartée pour sa précision.
     *
     * @param  callable(array): int  $rank
     * @return list<array>
     */
    private static function serviceClosuresNotCovered(array $events, callable $rank, ?int $bestRank): array
    {
        if ($bestRank === null) {
            return [];
        }
        $top = array_filter($events, fn ($e) => self::family($e['type']) === 'closure' && $rank($e) === $bestRank);
        $totals = array_column(array_filter($top, fn ($e) => $e['type'] === 'closure'), 'date');
        $firstTotal = $totals ? min($totals) : null;
        $out = [];
        foreach (['closure_passenger', 'closure_freight'] as $type) {
            $said = array_filter($top, fn ($e) => $e['type'] === $type || ($type === 'closure_freight' && $e['type'] === 'closure'));
            if ($said) {
                continue;
            }
            $candidates = array_filter($events, fn ($e) => $e['type'] === $type && ($firstTotal === null || $e['date'] < $firstTotal));
            if (! $candidates) {
                continue;
            }
            $min = min(array_map($rank, $candidates));
            foreach ($candidates as $e) {
                if ($rank($e) === $min) {
                    $out[] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * Ouverture retenue pour une portion qui n'est datée que par des ouvertures « toute la
     * ligne » (infobox, Wikidata P1619…) : la PLUS ANCIENNE (règle établie : la ligne
     * existait au moins depuis). Les ouvertures par étapes se localisent par leurs sections
     * (vff:resolve-event-sections), ou d'après les portions datées voisines
     * (vff:build-era-segments), pas en repoussant toute la ligne à son achèvement.
     *
     * @param  array<int, array{type: string, date: string}>  $events
     */
    public static function wholeLineOpening(array $events): ?array
    {
        $openings = array_values(array_filter($events, fn ($e) => self::family($e['type']) === 'opening'));
        usort($openings, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $openings[0] ?? null;
    }

    /**
     * Achèvement d'une ligne ouverte par étapes, d'après ses ouvertures « toute la ligne » :
     * la plus tardive à moins de COMPLETION_WINDOW_YEARS de la première (au-delà : prolongement
     * ou réouverture) et avant le premier événement de fin de la portion (un ancien tracé
     * fermé en 1866 n'a pas attendu l'achèvement de 1871, cf. Culoz - Modane). Sert de borne
     * haute à l'ouverture déduite d'une portion voisine (null s'il n'y a pas d'ouverture).
     * Une ouverture de la même année que la première n'est pas un achèvement : c'est la même,
     * à une autre précision. Sinon la borne tombait sur la première (752000 : Wikidata « 1981 »
     * et Wikipédia 27/09/1981), et toutes les portions de la LGV Sud-Est datées par leurs
     * voisines (Rhône-Alpes 1992-1994, Méditerranée 2001) étaient ramenées à 1981.
     *
     * @param  array<int, array{type: string, date: string}>  $events
     */
    public static function completionOpening(array $events): ?array
    {
        $openings = array_values(array_filter($events, fn ($e) => self::family($e['type']) === 'opening'));
        if (! $openings) {
            return null;
        }
        usort($openings, fn ($a, $b) => $a['date'] <=> $b['date']);
        $end = null;
        foreach ($events as $e) {
            if (in_array(self::family($e['type']), ['closure', 'declassification', 'dismantling', 'conversion'], true)) {
                $end = min($end ?? $e['date'], $e['date']);
            }
        }
        $window = sprintf('%04d', (int) substr($openings[0]['date'], 0, 4) + self::COMPLETION_WINDOW_YEARS).substr($openings[0]['date'], 4);
        $chosen = $openings[0];
        $firstYear = (int) substr($openings[0]['date'], 0, 4);
        foreach ($openings as $o) {
            // Même année que la première : la même ouverture, connue avec une autre précision
            // (Wikidata « 1981 » = 01/01/1981, Wikipédia 27/09/1981), pas un achèvement.
            if ((int) substr($o['date'], 0, 4) > $firstYear && $o['date'] <= $window && ($end === null || $o['date'] <= $end)) {
                $chosen = $o;
            }
        }

        return $chosen;
    }

    /**
     * Ouvertures « toute la ligne » qui sont en réalité des RÉouvertures, pour une portion
     * encore exploitée (« la ligne rouvre aux voyageurs en 1948 », « tram-train mis en
     * service en 2014 ») : datées plus d'un an après la première ouverture ET après une
     * fermeture. Sans fermeture entre les deux, c'est un achèvement (ligne ouverte par
     * étapes, cf. completionOpening), pas une réouverture.
     *
     * @param  array<int, array{type: string, date: string}>  $events
     */
    public static function wholeOpeningsAsReopenings(array $events): array
    {
        $first = null;
        foreach ($events as $e) {
            if (str_starts_with($e['type'], 'opening')) {
                $first = min($first ?? $e['date'], $e['date']);
            }
        }
        if ($first === null) {
            return $events;
        }
        $limit = (string) ((int) substr($first, 0, 4) + 1).substr($first, 4);
        $ends = [];
        foreach ($events as $e) {
            if (in_array(self::family($e['type']), ['closure', 'declassification', 'dismantling', 'conversion'], true)) {
                $ends[] = $e['date'];
            }
        }

        return array_map(function ($e) use ($limit, $first, $ends) {
            if (($e['whole'] ?? false) && str_starts_with($e['type'], 'opening') && $e['date'] > $limit
                && array_filter($ends, fn ($d) => $d > $first && $d < $e['date'])) {
                $e['type'] = 're'.$e['type'];
            }

            return $e;
        }, $events);
    }

    /**
     * Portion EXPLOITÉE aujourd'hui que ses fermetures PARTIELLES (voyageurs, puis fret)
     * laissent sans aucun trafic à $year : la plus récente, qu'aucune réouverture ne suit,
     * est contredite et retirée (sinon la portion « clignote » : fermée en 2020, en
     * service en 2030).
     *
     * @param  array<int, array{type: string, date: string, source?: ?string}>  $events
     */
    public static function withoutContradictedPartialClosures(array $events, string $defaultServices, bool $passengerImpliesFreight, int $year): array
    {
        while (($s = (new self($events, $defaultServices, $passengerImpliesFreight))->stateAt($year)) !== null && $s['status'] !== 'in_service') {
            $last = null;
            foreach (self::dedupe($events) as $e) {
                if (in_array($e['type'], ['closure_passenger', 'closure_freight'], true) && ($last === null || $e['date'] >= $last['date'])) {
                    $last = $e;
                }
            }
            if ($last === null || self::isRecentSourcedClosure($last, $year)) {
                break; // (une fermeture récente et sourcée prime sur le statut SNCF)
            }
            $events = array_values(array_filter($events, fn ($e) => ! ($e['type'] === $last['type'] && $e['date'] === $last['date']
                && ($e['source'] ?? null) === $last['source'])));
        }

        return $events;
    }

    /**
     * Fermeture RÉCENTE (moins de RECENT_CLOSURE_YEARS avant $year), localisée sur la portion
     * et sourcée (source au moins aussi fiable que wikipedia-reseaux) : elle prime sur un
     * statut SNCF « exploitée » ou une voie IGN « en service », qui peuvent être périmés
     * (ex. 761000 : fermeture de mars 2020, ligne encore « exploitée » dans les formes SNCF).
     *
     * @param  array{type: string, date: string, source?: ?string, whole?: bool}  $e
     */
    public static function isRecentSourcedClosure(array $e, int $year): bool
    {
        return in_array(self::family($e['type']), ['closure', 'declassification', 'dismantling', 'conversion'], true)
            && ! ($e['whole'] ?? false)
            && (int) substr($e['date'], 0, 4) > $year - self::RECENT_CLOSURE_YEARS
            && self::priority($e['source'] ?? null) <= self::SOURCE_PRIORITY['wikipedia-reseaux'];
    }

    /**
     * Événement de chronologie antérieur à la grande vitesse : sans objet pour une LGV ou un
     * raccordement LGV (il vient d'une ligne classique voisine ou d'un tracé partagé).
     */
    public static function predatesHighSpeed(string $type, string $date): bool
    {
        return isset(self::TYPE_ORDER[$type]) && (int) substr($date, 0, 4) < self::HIGH_SPEED_FIRST_YEAR;
    }

    private static function family(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'reopening') => 'reopening',
            str_starts_with($type, 'opening') => 'opening',
            str_starts_with($type, 'closure') => 'closure',
            default => $type,
        };
    }

    /**
     * Source sous laquelle le moteur range un événement (clé de SOURCE_PRIORITY) :
     *   - tableau PDF de lignes-oubliees : `lignes-oubliees-pdf` (décision administrative) ;
     *   - événement contribué (`contrib`) : la confiance suit la source citée, comme pour les
     *     corrections `manual` dans le registre des droits. Un lien vers une source que le moteur
     *     connaît (CITED_SOURCES) prend son rang ; une autre page web citée avec une confiance
     *     moyenne ou basse devient `contrib-web` ; un ouvrage, une archive, un document numérisé ou
     *     un site officiel (confiance haute) gardent le rang de `contrib`.
     */
    public static function rankedSource(?string $source, ?string $url, ?string $confidence = null): ?string
    {
        if ($source === 'contrib') {
            $cited = self::CITED_SOURCES[app(SourceRights::class)->providerForUrl($url) ?? ''] ?? null;
            if ($cited !== null) {
                return self::rankedSource($cited, $url);
            }

            return in_array($confidence, [null, 'high'], true) ? 'contrib' : 'contrib-web';
        }

        return $source === 'lignes-oubliees' && str_contains((string) $url, '.pdf') ? 'lignes-oubliees-pdf' : $source;
    }

    /** Confiance d'un événement contribué d'après la source citée (cf. rankedSource). */
    public static function citedConfidence(string $kind, ?string $url): string
    {
        if ($kind !== 'web') {
            return 'high';
        }
        $rights = app(SourceRights::class);
        $provider = $rights->providerForUrl($url);

        return match (true) {
            $provider === 'wikidata' => 'low',
            isset(self::CITED_SOURCES[$provider ?? '']) => 'medium',
            $provider !== null && $rights->status($provider) === SourceRights::SAFE => 'high',
            default => 'medium',
        };
    }

    private static function priority(?string $source): int
    {
        return self::SOURCE_PRIORITY[$source ?? ''] ?? 10;
    }
}
