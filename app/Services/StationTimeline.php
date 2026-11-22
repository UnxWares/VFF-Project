<?php

namespace App\Services;

/**
 * Rejoue la chronologie d'une gare (station_events) à une année donnée et la
 * combine avec l'état des lignes qui la desservent à cette époque.
 *
 * Statuts produits (mêmes codes que station_eras / la carte) :
 *   active        ouverte aux voyageurs (donc aussi au fret : « s'il y a du
 *                 voyageur, il y a du fret ») ;
 *   tourist       ouverte, ligne touristique ;
 *   no_commercial fret seul : plus de voyageurs, fret sur une ligne en service ;
 *   declassified  fermée (plus aucun trafic, qu'elle soit encore debout,
 *                 démolie ou réutilisée) ;
 *   null          la gare n'existe pas encore à cette date.
 *
 * Règle d'existence : ouverture ≤ année < fermeture (années civiles). Une gare
 * dont on ne connaît que la fermeture est montrée dans la seule décennie qui
 * la précède (ouverture inconnue mais antérieure). Après sa fermeture, la
 * chronologie la dit « fermée » à toutes les années ; vff:build-station-eras ne
 * garde ensuite que la première ère fermée et retire la gare des ères
 * suivantes (règle de Baptiste du 22/11/2026, BuildStationEras::keepClosedOnlyAtClosingEra).
 */
final class StationTimeline
{
    public const ACTIVE = 'active';

    public const TOURIST = 'tourist';

    public const FREIGHT = 'no_commercial';

    public const CLOSED = 'declassified';

    /** Desserte des lignes à une ère (plafond) : voyageurs (+ fret), fret seul, aucune. */
    public const LINES_PASSENGER = 2;

    public const LINES_FREIGHT = 1;

    public const LINES_CLOSED = 0;

    private const RANK = [self::CLOSED => 0, self::FREIGHT => 1, self::ACTIVE => 2, self::TOURIST => 2];

    /**
     * Statut d'après les seuls événements de la gare, à l'année $year.
     *
     * @param  array<int, array{type: string, date: string}>  $events
     * @param  bool  $existsToday  la gare existe encore (listée SNCF, en service…)
     */
    public static function statusFromEvents(array $events, int $year, bool $existsToday): ?string
    {
        usort($events, [self::class, 'byDate']);
        $years = array_map(fn ($e) => (int) substr($e['date'], 0, 4), $events);
        $openings = array_keys(array_filter($events, fn ($e) => in_array($e['type'], ['opening', 'reopening'], true)));

        // Ouverture inconnue : on ne sait montrer la gare que juste avant sa fermeture.
        if ($openings === []) {
            $firstClose = null;
            foreach ($events as $i => $e) {
                if (in_array($e['type'], ['closure', 'closure_passenger', 'demolition'], true)) {
                    $firstClose = $years[$i];
                    break;
                }
            }
            if ($firstClose === null) {
                return null;
            }
            $assumed = intdiv($firstClose - 1, 10) * 10;
            array_unshift($events, ['type' => 'opening', 'date' => sprintf('%04d-01-01', $assumed)]);
            array_unshift($years, $assumed);
        }

        $exists = false;
        $passenger = false;
        $freight = false;
        foreach ($events as $i => $e) {
            if ($years[$i] > $year) {
                break;
            }
            $later = array_slice($events, $i + 1);
            switch ($e['type']) {
                case 'opening':
                case 'reopening':
                    [$exists, $passenger, $freight] = [true, true, true];
                    break;
                case 'closure_passenger':
                    $passenger = false;
                    // Sans suite connue (ni fermeture totale, ni fin du fret, ni réouverture) et
                    // disparue aujourd'hui : la fermeture aux voyageurs clôt la vie connue de la
                    // gare, elle est fermée (pas « fret seul » jusqu'à aujourd'hui).
                    $hasSequel = (bool) array_filter($later, fn ($x) => in_array($x['type'], ['closure', 'closure_freight', 'demolition', 'opening', 'reopening'], true));
                    if (! $existsToday && ! $hasSequel) {
                        $freight = false;
                    }
                    break;
                case 'closure_freight':
                    // Fin du fret après celle des voyageurs (Livarot : 1938 puis 30/09/1990) :
                    // plus aucun trafic, la gare est fermée, comme une fermeture totale.
                    $freight = false;
                    break;
                case 'closure':
                case 'demolition':
                    // Fermée, qu'elle soit encore debout, démolie ou réutilisée : la carte la
                    // montre fermée à la première ère qui suit, puis plus du tout
                    // (BuildStationEras::keepClosedOnlyAtClosingEra) ; une réouverture la remet.
                    [$passenger, $freight] = [false, false];
                    break;
            }
        }
        if (! $exists) {
            return null;
        }

        return $passenger ? self::ACTIVE : ($freight ? self::FREIGHT : self::CLOSED);
    }

    /**
     * Une gare ne peut pas être plus active que les lignes qui la desservent
     * à cette ère : lignes fermées (ou absentes) → gare fermée, quoi qu'en
     * disent les drapeaux voyageurs/fret ; fret seul sur la ligne → au mieux
     * fret seul.
     */
    public static function capByLines(?string $status, int $lines, bool $heritageOnly = false): ?string
    {
        if ($status === null) {
            return null;
        }
        $cap = match ($lines) {
            self::LINES_PASSENGER => $heritageOnly ? self::TOURIST : self::ACTIVE,
            self::LINES_FREIGHT => self::FREIGHT,
            default => self::CLOSED,
        };
        if (self::RANK[$status] <= self::RANK[$cap]) {
            // Gare voyageurs sur ligne uniquement touristique → touristique.
            return $status === self::ACTIVE && $cap === self::TOURIST ? self::TOURIST : $status;
        }

        return $cap;
    }

    // ── Ère courante : état du jour ─────────────────────────────────────────
    //
    // L'ère courante (« 2030 ») est l'état du réseau AU JOUR MÊME. Le statut
    // des sources (liste SNCF, OSM, Wikidata…) est un relevé daté ; il est
    // corrigé par deux règles (2026-10-19, « une gare est aussi fermée si
    // juste elle n'est plus exploitée ») :
    //   1. plus exploitée : le drapeau « voyageurs » de liste-des-gares recense
    //      aussi des haltes fermées depuis longtemps ; seul (ni jeu
    //      gares-de-voyageurs, ni arrêt en service selon OSM ou Wikidata), face
    //      à une preuve de fermeture (OSM disused / abandoned / razed, Wikidata
    //      fermée) et sans réouverture connue (ex. ligne rouverte), il ne prouve
    //      pas la desserte → fret seul (drapeau fret) ou fermée ;
    //   2. fermeture datée au plus tard aujourd'hui (source, ou
    //      stations.closed_at saisie à la main), sans réouverture postérieure :
    //      appliquée, même si la ligne reste exploitée ; sauf si le jeu
    //      gares-de-voyageurs, relevé APRÈS la fermeture, dit la gare desservie
    //      (gare rouverte sans date connue). Une fermeture datée de demain
    //      s'applique demain (vff:refresh-current-era).

    /** Statuts d'une gare desservie (voyageurs, touristique ou fret). */
    public const OPEN_STATUSES = [self::ACTIVE, self::TOURIST, self::FREIGHT];

    public const NOTE_NOT_SERVED = 'Plus desservie (absente des gares de voyageurs SNCF, sans arrêt en service selon OSM ni Wikidata)';

    public const NOTE_CLOSED_TODAY = 'Fermeture datée appliquée à l\'ère courante';

    /** État du jour imposé par une correction sourcée (database/data/gares_corrections.php, action « current »). */
    public const NOTE_SERVED_TODAY = 'Desservie aujourd\'hui (horaires publiés, correction sourcée)';

    public const NOTE_NOT_SERVED_TODAY = 'Plus desservie aujourd\'hui (correction sourcée)';

    public const NOTE_TOURIST_TODAY = 'Desservie aujourd\'hui par un train touristique seulement (correction sourcée)';

    public const NOTE_FREIGHT_TODAY = 'Desservie aujourd\'hui par le fret seulement (correction sourcée)';

    public const NOTE_HERITAGE_NO_FREIGHT = 'Ligne seulement touristique à cette époque : plus de fret';

    /**
     * Ère passée « fret seul » non confirmée (aucune source ne l'atteste) alors que les seules
     * lignes en service près de la gare sont touristiques (train touristique, vélorail, sans
     * trafic fret) : une ligne déclassée puis reprise par un train touristique n'a plus de fret,
     * la gare est fermée (Lapouyade sur Cavignac - Coutras déclassée en 1976, 2026-11-19). Un
     * fret seul confirmé par une source (Carcassonne - Rivesaltes) et l'ère courante restent.
     */
    public static function heritageLineEndsFreight(?string $status, bool $heritageOnly, bool $uncertain, bool $isCurrent): bool
    {
        return $status === self::FREIGHT && $heritageOnly && $uncertain && ! $isCurrent;
    }

    /**
     * Statut du jour imposé par une correction « current » : desservie (voyageurs), touristique seulement,
     * fret seul ou plus desservie. Le plafond par les lignes (R2) s'applique ensuite comme pour tout statut.
     *
     * @return array{0: string, 1: string} [statut, note]
     */
    public static function forcedCurrentStatus(string $status): array
    {
        return match ($status) {
            self::ACTIVE => [self::ACTIVE, self::NOTE_SERVED_TODAY],
            // Seul un train touristique s'y arrête (règle 5 de la desserte du jour) : jamais « voyageurs ».
            self::TOURIST => [self::TOURIST, self::NOTE_TOURIST_TODAY],
            // Fermée aux voyageurs mais encore desservie par le fret (embranchement, installation terminale).
            self::FREIGHT => [self::FREIGHT, self::NOTE_FREIGHT_TODAY],
            default => [self::CLOSED, self::NOTE_NOT_SERVED_TODAY],
        };
    }

    /**
     * Statut du jour (ère courante), avant plafonnement par les lignes.
     *
     * @param  string|null  $source  stations.source_status (active, tourist, no_commercial, declassified, gone)
     * @param  array<int, array<string, mixed>>  $events  chronologie de la gare (type, date, precision, inferred)
     * @param  array{status_source?: ?string, voyageurs?: bool, voyageurs_seen?: ?string, osm_active?: bool,
     *               wikidata_active?: bool, closure_proof?: bool, reopened?: bool, sncf_freight?: bool, tourist?: bool}  $evidence
     * @return array{0: ?string, 1: ?string} [statut (null : la gare n'existe plus), note]
     */
    public static function currentStatus(?string $source, array $events, string $today, array $evidence = []): array
    {
        if ($source === null || $source === 'gone') {
            return [null, null];
        }
        $status = in_array($source, self::OPEN_STATUSES, true) ? $source : self::CLOSED;
        $note = null;

        // 1. Plus exploitée.
        if ($status === self::ACTIVE && ($evidence['status_source'] ?? null) === 'sncf' && ! ($evidence['voyageurs'] ?? false)
            && ! ($evidence['osm_active'] ?? false) && ! ($evidence['wikidata_active'] ?? false)
            && ($evidence['closure_proof'] ?? false) && ! ($evidence['reopened'] ?? false)) {
            $status = ($evidence['sncf_freight'] ?? false) ? self::FREIGHT : self::CLOSED;
            $note = self::NOTE_NOT_SERVED;
        }

        // 2. Dernière fermeture datée (≤ aujourd'hui) non suivie d'une réouverture. Fermetures des
        //    sources seulement (l'état des lignes plafonne ensuite) ; une réouverture déduite d'une
        //    réouverture de la ligne compte (Montréjeau - Luchon).
        $closing = null;
        $partial = [];
        foreach (self::forTimeline(array_values(array_filter($events, fn ($e) => ! ($e['inferred'] ?? false) || $e['type'] === 'reopening'))) as $e) {
            if ((string) $e['date'] > $today) {
                break;
            }
            if (in_array($e['type'], ['opening', 'reopening'], true)) {
                [$closing, $partial] = [null, []];
            } elseif (in_array($e['type'], self::CLOSURES, true)) {
                $closing = $e;
                $partial[$e['type']] = true;
            }
        }
        // Voyageurs ET fret fermés depuis la dernière ouverture : fermeture totale, quel que soit
        // l'ordre des deux (Airvault-Gare : voyageurs en 1980, puis voyageurs et fret le même mois de
        // décembre 2015 ; l'ordre de deux fermetures du même jour ne doit pas décider du statut).
        if ($closing !== null && isset($partial['closure_passenger'], $partial['closure_freight'])) {
            $closing = ['type' => 'closure'] + $closing;
        }
        if ($closing === null || $status === self::CLOSED) {
            return [$status, $note];
        }
        $seen = ($evidence['voyageurs'] ?? false) ? ($evidence['voyageurs_seen'] ?? null) : null;
        // Une correction sourcée (database/data/gares_corrections.php, source « correction ») prime sur le
        // jeu gares-de-voyageurs, qui garde des gares remplacées (Pont-de-Claix, fermée le 29/03/2025).
        if ($seen !== null && ($closing['source'] ?? null) !== self::CORRECTION && substr((string) $seen, 0, 10) > (string) $closing['date']) {
            return [$status, $note]; // desservie selon un relevé postérieur : rouverte
        }
        $closed = match ($closing['type']) {
            'closure_passenger' => ($evidence['sncf_freight'] ?? false) && $status !== self::TOURIST ? self::FREIGHT : self::CLOSED,
            'closure_freight' => $status === self::FREIGHT ? self::CLOSED : $status,
            default => self::CLOSED,
        };
        if ($closed === $status) {
            return [$status, $note];
        }
        // Train touristique encore arrêté à la gare (ex. Thiers - Ambert) : touristique.
        if ($evidence['tourist'] ?? false) {
            return [self::TOURIST, $note];
        }

        return [$closed, self::NOTE_CLOSED_TODAY];
    }

    // ── Nature du point : gare, point technique, gare projetée ─────────────
    //
    // liste-des-gares (SNCF) recense des « gares » au sens de l'exploitation :
    // aussi des postes, voies de garage, bifurcations, sous-stations (ex. sur la
    // LGV Sud-Est : Marolles-TGV, Vaumort, Tonnerre-TGV, Pasilly, Lacour
    // d'Arcenay, Allan, Piolenc-TGV, Senas-TGV, tous sans drapeau voyageurs ni
    // fret). Règle (2026-10-20) : un point sans AUCUNE preuve d'ouverture au
    // public n'est pas une gare de la ligne (ni liste, ni compteur, ni carte) :
    //   - preuve d'ouverture au public = drapeau voyageurs ou fret de
    //     liste-des-gares, jeu gares-de-voyageurs, objet gare OSM / OHM /
    //     Wikidata (hors « projetée ») ou liste historique (lignes-oubliees,
    //     gare ou halte d'un schéma de ligne Wikipédia : wikipedia_schema),
    //     ou événement daté propre à la gare (article Wikipédia…) ;
    //   - sans preuve, c'est un point technique si toutes ses lignes sont à
    //     grande vitesse (une LGV n'a ni fret ni halte : toutes ses gares sont
    //     connues par d'autres sources) ou si son nom désigne une installation
    //     (voie de garage, bifurcation, poste, PK, sous-station, raccordement) ;
    //     sinon c'est une ancienne gare (la liste SNCF garde le code des gares
    //     fermées : Vertus, Buglose…).
    // Gare projetée jamais ouverte (Wikidata « projetée », sans ouverture
    // prouvée) : marquée « projetée », jamais « fermée ».

    public const KIND_STATION = 'station';

    public const KIND_TECHNICAL = 'technical';

    public const KIND_PROJECTED = 'projected';

    /**
     * Hors du réseau ferré décrit par VFF (correction sourcée seulement,
     * database/data/gares_corrections.php) : station de tramway ou de métro,
     * funiculaire (écartés à l'import, mais entrés par un objet mal étiqueté),
     * enregistrement erroné d'une liste (« Grasse » géocodée à Marseille). Comme
     * un point technique : ni ère, ni carte, ni fiche ligne, ni annuaire.
     */
    public const KIND_EXCLUDED = 'excluded';

    /** Noms d'installations, pas de gares (« Azincourt-Garage », « Lille-Bifurcation-de-la-Justice », « Forêt-de-la-Londe (PK) »). */
    public const TECHNICAL_NAME = '/(-garage$|\bbif(urcation)?\b|\bposte\b|\(pk\)|sous-station|\braccordement\b)/iu';

    /**
     * Nom générique d'installation, seul (sans lieu) : « Bifurcation » (50 points des listes
     * lignes-oubliees, dont un listé comme gare de Saintes - Royan), « Dépôt », « Poste 3 ».
     * Point technique, même avec une source (contrôle qualité du 2026-10-20).
     */
    public const GENERIC_NAME = '/^\s*(?:bifurcation|bif\.?|poste|raccordement|embranchement|triage|dépôt|atelier|garage|faisceau|sous-station|halte technique|point d[\'’]arrêt)(?:\s*(?:n°\s*)?\d+)?\s*$/iu';

    /**
     * Nom d'une compagnie ou d'une association, ou d'un bâtiment, pas d'une gare : « Chemin de Fer de
     * Charente-Limousine » (point OSM listé comme gare de Limoges - Angoulême), « Chemins de fer
     * départementaux de la Mayenne », « Ancien bâtiment de la gare de Choisy-le-Roi ». Hors du
     * réseau des gares (KIND_EXCLUDED : ni ère, ni carte, ni fiche ligne).
     */
    public const ORGANIZATION_NAME = '/^\s*(?:chemins?\s+de\s+fer\b|compagnie\b|association\b|société\b|amicale\b|train\s+touristique\b|vélo-?rail\b|rando-?rail\b|(?:ancien(?:ne)?s?\s+)?bâtiments?(?:\s+voyageurs)?\b)/iu';

    /**
     * @param  bool  $publicProof  une preuve d'ouverture au public (cf. ci-dessus)
     * @param  bool  $highSpeedOnly  toutes les lignes de la gare sont des LGV (au moins une ligne)
     * @param  bool  $planned  objet « projeté » (Wikidata) parmi les sources
     */
    public static function kind(string $name, bool $publicProof, bool $highSpeedOnly, bool $planned = false): string
    {
        // Un nom qui n'est pas celui d'un lieu n'est jamais une gare, même « attestée » : la ligne
        // « Bifurcation » d'une liste de lignes-oubliees, le nom d'une association posé sur un point OSM.
        if (preg_match(self::ORGANIZATION_NAME, $name) === 1) {
            return self::KIND_EXCLUDED;
        }
        if (preg_match(self::GENERIC_NAME, $name) === 1) {
            return self::KIND_TECHNICAL;
        }
        if ($publicProof) {
            return self::KIND_STATION;
        }
        if ($planned) {
            return self::KIND_PROJECTED;
        }

        return $highSpeedOnly || preg_match(self::TECHNICAL_NAME, $name) === 1 ? self::KIND_TECHNICAL : self::KIND_STATION;
    }

    // ── Date de fermeture retenue : une seule source de vérité ──────────────
    //
    // stations.closed_at (+ closed_precision, provenance.closed_at /
    // closed_after) est écrite par vff:build-station-eras APRÈS station_eras,
    // et lue telle quelle par la carte (GeoExporter), la fiche gare et la fiche
    // ligne (LineStations). La date est choisie DANS l'intervalle où les ères
    // placent la fermeture : après la dernière ère où la gare est affirmée
    // ouverte, au plus tard à l'ère suivante (fermée ou absente) ; elle ne
    // peut donc jamais contredire la frise de la carte.

    /** Précision « fermée entre closed_after + 1 et l'année de closed_at » (date inconnue). */
    public const RANGE = 'range';

    /** Ordre de préférence des types de fermeture (fin de toute desserte d'abord). */
    private const CLOSURE_TYPE_RANK = ['closure' => 0, 'demolition' => 0, 'closure_freight' => 1, 'closure_passenger' => 2,
        'declassification' => 3, 'dismantling' => 3];

    private const PRECISION_RANK = ['day' => 0, 'month' => 1, 'year' => 2];

    /**
     * Fiabilité des sources de date d'une gare : saisie manuelle, article
     * Wikipédia (infobox, phrase sourcée), qualificatifs Wikidata / OHM /
     * lignes-oubliees, puis Wikidata « date de fin » et OSM (beaucoup de dates
     * en masse : 30/12/2013, 16/12/2016, 07/12/2018…).
     */
    /** Source des événements des corrections sourcées (database/data/gares_corrections.php, App\Services\StationCorrections). */
    public const CORRECTION = 'correction';

    private const SOURCE_RANK = ['correction' => 0, 'manual' => 0, 'contribution' => 0, 'wikipedia' => 1, 'wikidata:qualifiers' => 2, 'ohm' => 2,
        'lignes-oubliees' => 2, 'osm:note' => 3, 'wikidata' => 3, 'osm' => 3];

    /**
     * Date de fermeture retenue, ou null si la gare est ouverte (ou incertaine)
     * aujourd'hui, ou n'apparaît à aucune ère.
     *
     * Candidats (dans l'intervalle seulement) : événements de la gare (source,
     * puis déduits), puis fins de desserte de ses lignes au PK de la gare
     * ($candidates[i]['line'] = true) ; à rang égal : la source la plus sûre
     * (SOURCE_RANK), puis fermeture totale > fin du fret > fermeture aux
     * voyageurs > déclassement / dépose, puis la date la plus précise, puis la
     * plus tardive ; une date plus précise de la même année et du même type
     * (autre source) précise ensuite la date retenue. Fermeture « dans les années 1980 »
     * (decade) ou « avant 1984 » (before) : intervalle (précision range). Aucun
     * candidat : précision range, bornes = l'intervalle des ères.
     *
     * @param  array<int, array{status: string, uncertain?: bool}|null>  $eras  année de l'ère → état (null ou absente : la gare n'existe pas)
     * @param  int  $currentYear  année de l'ère courante (« aujourd'hui »)
     * @param  list<array<string, mixed>>  $candidates  type, date, precision, source, inferred, line
     * @return array{date: string, precision: string, source: string, after: ?int, conflict: ?array<string, mixed>}|null
     */
    public static function resolveClosure(array $eras, int $currentYear, string $today, array $candidates): ?array
    {
        ksort($eras);
        $isOpen = fn ($s) => is_array($s) && in_array($s['status'] ?? null, self::OPEN_STATUSES, true);
        if ($isOpen($eras[$currentYear] ?? null) || array_filter($eras, fn ($s) => is_array($s)) === []) {
            return null;
        }
        $lastOpen = null;
        $lastConfirmed = null;
        foreach ($eras as $year => $s) {
            if ($year !== $currentYear && $isOpen($s)) {
                $lastOpen = $year;
                if (! ($s['uncertain'] ?? false)) {
                    $lastConfirmed = $year;
                }
            }
        }
        // Borne haute : première ère (fermée ou absente) après la dernière ère ouverte ;
        // jamais ouverte : première ère où la gare figure.
        $upper = $currentYear;
        foreach ($eras as $year => $s) {
            if ($lastOpen === null ? is_array($s) : $year > $lastOpen) {
                $upper = $year;
                break;
            }
        }
        $upperDate = $upper >= $currentYear ? $today : sprintf('%04d-12-31', $upper);
        $inRange = fn (string $date) => $date <= $upperDate && ($lastConfirmed === null || (int) substr($date, 0, 4) > $lastConfirmed);

        $best = null;
        $bestKey = null;
        $conflict = null;
        $kept = [];
        foreach ($candidates as $c) {
            $type = (string) $c['type'];
            $precision = (string) ($c['precision'] ?? 'day');
            if (! isset(self::CLOSURE_TYPE_RANK[$type]) || $precision === self::AFTER) {
                continue;
            }
            $date = substr((string) $c['date'], 0, 10);
            // « Dans les années 1980 » / « avant 1984 » : intervalle, pas une date.
            [$from, $to] = match ($precision) {
                self::DECADE => [intdiv((int) substr($date, 0, 4), 10) * 10 - 1, sprintf('%04d-12-31', intdiv((int) substr($date, 0, 4), 10) * 10 + 9)],
                self::BEFORE => [null, $date],
                default => [null, $date],
            };
            $line = (bool) ($c['line'] ?? false);
            $origin = $line ? 2 : (($c['inferred'] ?? false) ? 1 : 0);
            $vague = $precision === self::DECADE || $precision === self::BEFORE;
            // Fermeture aux voyageurs suivie d'ères « fret seul » (Strasbourg-Koenigshoffen, voyageurs fermés
            // en 1846 ; Lille-Saint-Sauveur, gare aux marchandises dès 1865) : la gare restait ouverte au
            // fret, ce n'est ni sa date de fermeture ni un conflit avec la frise.
            if ($type === 'closure_passenger' && array_filter($eras,
                fn ($s, $y) => $y >= (int) substr($date, 0, 4) && is_array($s) && $s['status'] === self::FREIGHT, ARRAY_FILTER_USE_BOTH) !== []) {
                continue;
            }
            if (! $inRange($to) && ! ($vague && $to > $upperDate && ($from === null || $from < (int) substr($upperDate, 0, 4)))) {
                if ($origin === 0 && ($conflict === null || $date > $conflict['date'])) {
                    $conflict = ['date' => $date, 'type' => $type, 'source' => $c['source'] ?? null];
                }

                continue;
            }
            $source = (string) ($c['source'] ?? '');
            $key = [$origin, $vague ? 1 : 0, $origin === 0 ? (self::SOURCE_RANK[$source] ?? 4) : 0, self::CLOSURE_TYPE_RANK[$type],
                self::PRECISION_RANK[$precision] ?? 3, -((int) str_replace('-', '', $date))];
            $kept[] = ['date' => $date, 'from' => $from, 'to' => $to, 'precision' => $precision, 'vague' => $vague, 'source' => $source,
                'origin' => $origin, 'class' => self::CLOSURE_TYPE_RANK[$type]];
            if ($bestKey === null || $key < $bestKey) {
                [$best, $bestKey] = [end($kept), $key];
            }
        }
        if ($best !== null && ! $best['vague']) {
            // Même fermeture, plus précise, d'une autre source (ex. Wikipédia « 1934 », Wikidata « 23/07/1934 »).
            foreach ($kept as $k) {
                if (! $k['vague'] && $k['origin'] <= $best['origin'] && $k['class'] === $best['class']
                    && substr($k['date'], 0, 4) === substr($best['date'], 0, 4)
                    && (self::PRECISION_RANK[$k['precision']] ?? 3) < (self::PRECISION_RANK[$best['precision']] ?? 3)) {
                    $best = $k;
                }
            }

            return ['date' => $best['date'], 'precision' => $best['precision'], 'source' => $best['source'], 'after' => null, 'conflict' => null];
        }
        if ($best !== null) {
            // Intervalle de la source, resserré par celui des ères.
            $after = max($best['from'] ?? PHP_INT_MIN, $lastConfirmed ?? PHP_INT_MIN);

            return ['date' => min($best['to'], $upperDate), 'precision' => self::RANGE, 'source' => $best['source'],
                'after' => $after === PHP_INT_MIN ? null : $after, 'conflict' => null];
        }

        return ['date' => $upperDate, 'precision' => self::RANGE, 'source' => 'vff:eras', 'after' => $lastConfirmed, 'conflict' => $conflict];
    }

    /** Rang de fiabilité d'une source de date de gare (0 = la plus sûre). */
    public static function sourceRank(?string $source): int
    {
        return self::SOURCE_RANK[(string) $source] ?? 4;
    }

    /**
     * Fermetures en double : plusieurs fermetures du même niveau (totale, ou aux
     * voyageurs) sans ouverture ni réouverture entre elles décrivent la même
     * fermeture vue par des sources différentes (ou une fiche qui fusionne deux
     * gares homonymes : Boulevard-Masséna, gare de Petite Ceinture fermée en
     * 1934 et halte du RER C fermée le 03/12/2000). On garde celle de la source
     * la plus sûre (SOURCE_RANK : Wikipédia avant les dates en masse de
     * Wikidata), puis la plus précise, puis la plus ancienne. Les événements
     * déduits ne sont pas concernés.
     *
     * @param  array<int, array<string, mixed>>  $events  triés par date
     * @return array<int, array<string, mixed>>
     */
    public static function dedupeClosures(array $events): array
    {
        $levels = ['closure' => 'total', 'demolition' => 'total', 'closure_passenger' => 'passenger'];
        $groups = [];
        $open = 0; // numéro de période (incrémenté à chaque ouverture / réouverture)
        foreach ($events as $i => $e) {
            if (in_array($e['type'], ['opening', 'reopening'], true)) {
                $open++;
            } elseif (isset($levels[$e['type']]) && ! ($e['inferred'] ?? false) && ($e['precision'] ?? 'day') !== self::AFTER) {
                $groups[$open.'|'.$levels[$e['type']]][] = $i;
            }
        }
        $drop = [];
        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }
            usort($members, fn ($a, $b) => [self::sourceRank($events[$a]['source'] ?? null), self::PRECISION_RANK[$events[$a]['precision'] ?? 'day'] ?? 3, (string) $events[$a]['date']]
                <=> [self::sourceRank($events[$b]['source'] ?? null), self::PRECISION_RANK[$events[$b]['precision'] ?? 'day'] ?? 3, (string) $events[$b]['date']]);
            foreach (array_slice($members, 1) as $i) {
                $drop[$i] = true;
            }
        }

        return array_values(array_diff_key($events, $drop));
    }

    /** Sources de dates en masse, sans portée (voyageurs, fret, totale) ni contexte. */
    private const MASS_SOURCES = ['wikidata', 'osm', 'osm:note'];

    /**
     * Sources en masse dont une fermeture peut être la fin des voyageurs quand une source mieux
     * classée date le fret plus tard : celles de MASS_SOURCES, plus OHM (fin d'un objet sans portée).
     */
    private const MASS_SOURCES_FREIGHT_LATER = ['wikidata', 'osm', 'osm:note', 'ohm'];

    /**
     * Une « fermeture » Wikidata / OSM (P3999 sans qualificatif de trafic) de la
     * même année (à un an près) qu'une fermeture aux voyageurs d'une source plus
     * sûre est la fin du service voyageurs, pas une fermeture totale, dans deux cas :
     *
     * 1. gare fret seul aujourd'hui ($freightToday) : le fret a continué (Aniche
     *    1992, Lumbres 15/07/1959, Baud 02/10/1949…). Sans cette règle, la frise
     *    montrait la gare fermée de cette date à 2020, puis « fret seul » en 2030 ;
     * 2. gare fermée ou disparue, quand une source plus sûre date aussi la fin du
     *    fret (ou la fermeture totale) une année postérieure (Livarot : voyageurs
     *    1938, marchandises 30/09/1990 selon l'infobox ; Wikidata « fermée en
     *    1938 »). OHM compte ici comme source en masse (Pouilly-sous-Charlieu,
     *    fin de l'objet le 20/05/1940, fret jusqu'en 2005), et une « démolition »
     *    Wikidata (P576) de la même année aussi (Le Lion-d'Or, P576 = 1938, fret
     *    jusqu'au 03/11/1969). Règle du 21/11/2026, qui remplace les corrections
     *    une à une de la section 9 de gares_corrections.php.
     *
     * Retypée en closure_passenger (dedupeClosures garde ensuite la source la
     * plus sûre). Une fermeture totale d'une source plus sûre de la même année
     * (Paris-Tolbiac, démolie en 2001) empêche la règle. Les fins de desserte
     * des lignes (candidats « line » de resolveClosure) ne comptent pas : seules
     * les dates de la gare elle-même.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function massClosureAsPassenger(array $events, bool $freightToday): array
    {
        $year = fn (array $e) => (int) substr((string) $e['date'], 0, 4);
        $own = fn (array $x) => ! ($x['inferred'] ?? false) && ! ($x['line'] ?? false);
        foreach ($events as $i => $e) {
            if (! $own($e)) {
                continue;
            }
            $source = $e['source'] ?? null;
            $today = $freightToday && $e['type'] === 'closure' && in_array($source, self::MASS_SOURCES, true);
            $later = in_array($source, self::MASS_SOURCES_FREIGHT_LATER, true)
                && ($e['type'] === 'closure' || ($e['type'] === 'demolition' && $source === 'wikidata'));
            if (! $today && ! $later) {
                continue;
            }
            $rank = self::sourceRank($source);
            $better = fn (array $x, array $types) => in_array($x['type'], $types, true) && $own($x)
                && self::sourceRank($x['source'] ?? null) < $rank;
            $sameYear = fn (array $x, array $types) => $better($x, $types) && abs($year($x) - $year($e)) <= 1;
            if (array_filter($events, fn ($x) => $sameYear($x, ['closure_passenger'])) === []
                || array_filter($events, fn ($x) => $sameYear($x, ['closure', 'demolition'])) !== []) {
                continue;
            }
            // Cas 2 : le fret (ou toute desserte) a continué au-delà de l'année de la fermeture en masse.
            $freightLater = $later && array_filter($events, fn ($x) => $better($x, ['closure_freight', 'closure']) && $year($x) > $year($e)) !== [];
            if ($today || $freightLater) {
                $events[$i]['type'] = 'closure_passenger';
            }
        }

        return $events;
    }

    /**
     * Fin de desserte d'une LIGNE (candidat « line » de resolveClosure) contredite par la
     * frise : la gare est encore montrée ouverte (non confirmée) à une ère postérieure, sur
     * une de ses lignes en service (hors patrimoine) à moins de 500 m ($servedUntil = la
     * dernière de ces ères). La fermeture d'une autre ligne de la gare, ou un événement
     * mal rattaché, n'est pas la fermeture de la gare : Vourey (dépose de 1959 de la
     * double voie Romans - Moirans, ligne en service), La Praz (chemin de fer du
     * Mont-Cenis fermé en 1871 sur Culoz - Modane), Griesbach (ligne de Lapoutroie fermée
     * en 1950, ligne de Metzeral en service). Retirée : la date retenue devient
     * l'intervalle des ères (« entre … et … »), sans contredire la frise (2026-11-19).
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public static function dropLineClosuresBeforeService(array $candidates, ?int $servedUntil): array
    {
        if ($servedUntil === null) {
            return $candidates;
        }

        return array_values(array_filter($candidates,
            fn ($c) => ! ($c['line'] ?? false) || (int) substr((string) $c['date'], 0, 4) > $servedUntil));
    }

    /**
     * Année ronde d'une source qui reprend une décennie d'une autre : Wikidata « 1970 »
     * (précision année) et Wikipédia « Fermeture : années 1970 » pour la même gare
     * (Réchicourt-le-Château, Marchiennes, Darnétal : 18 gares le 19/11/2026). La frise
     * garde déjà la décennie (dedupeClosures : Wikipédia avant Wikidata) ; la date retenue
     * fait de même, sinon « fermée en 1970 » contredirait l'ère 1970 « non confirmée ».
     * L'année ronde devient la décennie (jamais l'inverse).
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public static function roundYearAsDecade(array $candidates): array
    {
        $decades = [];
        foreach ($candidates as $c) {
            if (($c['precision'] ?? null) === self::DECADE) {
                $decades[substr((string) $c['date'], 0, 10)][] = (string) ($c['source'] ?? '');
            }
        }
        foreach ($candidates as $i => $c) {
            $date = substr((string) $c['date'], 0, 10);
            if (($c['precision'] ?? null) === 'year' && preg_match('/^\d{3}0-01-01$/', $date) === 1
                && array_diff($decades[$date] ?? [], [(string) ($c['source'] ?? '')]) !== []) {
                $candidates[$i]['precision'] = self::DECADE;
            }
        }

        return $candidates;
    }

    /**
     * Gare desservie aujourd'hui selon le jeu SNCF gares-de-voyageurs (relevé
     * daté $servedSeen) : une fermeture de source en masse (Wikidata, OSM)
     * antérieure au relevé et sans réouverture connue après elle n'est pas une
     * fermeture (Vincelles : P3999 du 15/12/2017 tiré du référentiel SNCF des
     * gares ; Loriol : 2014 = fermeture du guichet ; Nice-St-Augustin : ancien
     * bâtiment, gare déplacée de 400 m le 01/09/2022). Elle est retirée, pour
     * la frise des ères passées comme pour l'ère courante (qui l'ignorait déjà).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function dropContradictedClosures(array $events, ?string $servedSeen): array
    {
        if ($servedSeen === null) {
            return $events;
        }
        $seen = substr($servedSeen, 0, 10);
        $out = [];
        foreach ($events as $e) {
            $date = (string) $e['date'];
            $reopened = array_filter($events, fn ($x) => in_array($x['type'], ['opening', 'reopening'], true) && (string) $x['date'] > $date) !== [];
            if (in_array($e['type'], ['closure', 'closure_passenger'], true) && ! ($e['inferred'] ?? false)
                && in_array($e['source'] ?? null, self::MASS_SOURCES, true) && $date < $seen && ! $reopened) {
                continue;
            }
            $out[] = $e;
        }

        return $out;
    }

    /**
     * Libellé court de la date retenue, identique partout (fiche gare, fiche
     * ligne, carte) : « 12/11/1954 », « 11/1954 », « 1954 », « entre 1951 et
     * 1960 », « avant 1960 ».
     */
    public static function closureLabel(?string $date, ?string $precision, ?int $after): ?string
    {
        if ($date === null) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', substr($date, 0, 10)) + [1 => 1, 2 => 1]);

        return match ($precision) {
            self::RANGE => $after !== null ? sprintf('entre %d et %d', $after + 1, $y) : sprintf('au plus tard en %d', $y),
            'year' => (string) $y,
            'month' => sprintf('%02d/%d', $m, $y),
            default => sprintf('%02d/%02d/%d', $d, $m, $y),
        };
    }

    // ── Fermetures non datées ───────────────────────────────────────────────
    //
    // Une gare fermée aujourd'hui dont la fermeture n'est pas datée restait
    // « ouverte » jusqu'à l'ère précédente (ouverture ≤ année < fermeture, sans
    // fermeture). Règle (2026-10-19) : à une ère historique, un statut ouvert
    // n'est affirmé que s'il est COUVERT par une source ; au-delà de la
    // dernière preuve, l'ère est « incertaine » (station_eras.uncertain), avec
    // une note (« Fermeture non datée »…). Preuves (lastConfirmedEra) :
    //   - ouverture / réouverture en P (source ou déduite des lignes) : couvre
    //     la première ère ≥ P (une gare ouverte en 1855 est montrée en 1860,
    //     pas au-delà sans autre preuve) ;
    //   - fermeture datée (jour, mois, année ; source ou déduite des lignes)
    //     en D : la gare était ouverte juste avant, couvre les ères < D ;
    //     fermeture aux voyageurs : couvre aussi le niveau voyageurs ;
    //     fermeture au fret : seulement le niveau « toute desserte » ;
    //   - fermeture « dans les années 1980 » (decade) : couvre les ères < 1980 ;
    //   - fermeture « après 1950 » (after, ex. « deuxième moitié du XXe
    //     siècle ») : couvre la première ère ≥ 1950 ;
    //   - fermeture « avant 1984 » (before) : ne couvre rien.
    // Gare fret seul aujourd'hui : seul le niveau voyageurs est vérifié (le
    // fret actuel prouve la desserte). Gare ouverte aujourd'hui : rien à vérifier.

    /** Précisions de date d'une fermeture imprécise (station_events.date_precision). */
    public const DECADE = 'decade';

    public const AFTER = 'after';

    public const BEFORE = 'before';

    public const NOTE_UNDATED = 'Fermeture non datée';

    public const NOTE_UNDATED_PASSENGER = 'Fermeture aux voyageurs non datée';

    public const NOTE_UNDATED_FREIGHT = 'Fin du service marchandises non datée';

    private const CLOSURES = ['closure', 'closure_passenger', 'closure_freight', 'demolition'];

    /**
     * Événements à rejouer par statusFromEvents() : une fermeture « après Y »
     * n'est pas une date (retirée) ; « dans les années 1980 » est appliquée au
     * 31/12/1989 (la gare est montrée ouverte en 1980, l'ère est rendue
     * incertaine par unconfirmedNote()).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function forTimeline(array $events): array
    {
        $out = [];
        foreach ($events as $e) {
            $precision = $e['precision'] ?? 'day';
            if ($precision === self::AFTER && in_array($e['type'], self::CLOSURES, true)) {
                continue;
            }
            if ($precision === self::DECADE && in_array($e['type'], self::CLOSURES, true)) {
                $e['date'] = sprintf('%04d-12-31', intdiv((int) substr((string) $e['date'], 0, 4), 10) * 10 + 9);
            }
            $out[] = $e;
        }
        usort($out, [self::class, 'byDate']);

        return $out;
    }

    /**
     * Ordre chronologique ; le même jour, l'ouverture avant la fermeture (gare aux marchandises
     * « ouverte en 1865 » et « jamais ouverte aux voyageurs » en 1865 : Lille-Saint-Sauveur), et la
     * fermeture totale en dernier (Paris-Tolbiac : voyageurs et totale en juin 2001 → fermée).
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function byDate(array $a, array $b): int
    {
        // Même jour : ouverture, puis fermeture partielle (voyageurs, fret), puis fermeture totale.
        $rank = fn (array $e) => match ($e['type'] ?? null) {
            'opening', 'reopening' => 0,
            'closure', 'demolition' => 2,
            default => 1,
        };

        return [(string) $a['date'], $rank($a)] <=> [(string) $b['date'], $rank($b)];
    }

    /**
     * Dernière ère (année) où une source atteste la gare ouverte, au niveau
     * voyageurs ($passenger) ou toute desserte ; null sans aucune preuve.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @param  list<int>  $eraYears  années des ères historiques
     */
    public static function lastConfirmedEra(array $events, array $eraYears, bool $passenger): ?int
    {
        sort($eraYears);
        $firstFrom = function (int $y) use ($eraYears): ?int {
            foreach ($eraYears as $e) {
                if ($e >= $y) {
                    return $e;
                }
            }

            return null;
        };
        $lastBefore = function (int $y) use ($eraYears): ?int {
            $out = null;
            foreach ($eraYears as $e) {
                if ($e < $y) {
                    $out = $e;
                }
            }

            return $out;
        };
        $best = null;
        foreach ($events as $e) {
            $y = (int) substr((string) $e['date'], 0, 4);
            $precision = $e['precision'] ?? 'day';
            $type = $e['type'];
            if ($type === 'demolition' || ($passenger && $type === 'closure_freight')) {
                continue;
            }
            $cover = match (true) {
                in_array($type, ['opening', 'reopening'], true) => $firstFrom($y),
                ! in_array($type, self::CLOSURES, true) => null,
                $precision === self::AFTER => $firstFrom($y),
                $precision === self::BEFORE => null,
                $precision === self::DECADE => $lastBefore(intdiv($y, 10) * 10),
                default => $lastBefore($y),
            };
            if ($cover !== null && ($best === null || $cover > $best)) {
                $best = $cover;
            }
        }

        return $best;
    }

    /**
     * Note d'une ère historique dont le statut ouvert n'est couvert par aucune
     * source (l'ère est alors « incertaine »), ou null si le statut est affirmé.
     *
     * @param  array<int, array<string, mixed>>  $events  chronologie de la gare (avec date_precision)
     * @param  list<int>  $eraYears  années des ères historiques
     * @param  string|null  $status  statut de la gare à cette ère (après plafonnement par les lignes)
     * @param  string|null  $today  état actuel selon les sources : CLOSED (fermée ou disparue), FREIGHT, ou autre
     */
    public static function unconfirmedNote(array $events, array $eraYears, int $year, ?string $status, ?string $today): ?string
    {
        if ($status === null || $status === self::CLOSED || ! in_array($today, [self::CLOSED, self::FREIGHT], true)) {
            return null;
        }
        $passengerStatus = $status !== self::FREIGHT;
        if ($today === self::FREIGHT && ! $passengerStatus) {
            return null; // le fret actuel prouve la desserte
        }
        $cover = self::lastConfirmedEra($events, $eraYears, $passengerStatus);
        if ($cover !== null && $year <= $cover) {
            return null;
        }
        if ($today === self::FREIGHT) {
            return self::NOTE_UNDATED_PASSENGER;
        }

        return self::undatedClosureNote($events, $passengerStatus);
    }

    /**
     * « Fermeture dans les années 1980 », « Fermeture non datée (après 1950) »…
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    public static function undatedClosureNote(array $events, bool $passengerStatus = true): string
    {
        $vague = [];
        $passengerClosed = false;
        foreach ($events as $e) {
            if (! in_array($e['type'], self::CLOSURES, true) || $e['type'] === 'closure_freight') {
                continue;
            }
            $precision = $e['precision'] ?? 'day';
            $y = (int) substr((string) $e['date'], 0, 4);
            if (in_array($precision, [self::DECADE, self::BEFORE, self::AFTER], true)) {
                $vague[$precision] ??= $y;
            } elseif ($e['type'] === 'closure_passenger') {
                $passengerClosed = true;
            }
        }

        return match (true) {
            isset($vague[self::DECADE]) => 'Fermeture dans les années '.(intdiv($vague[self::DECADE], 10) * 10),
            isset($vague[self::BEFORE]) => self::NOTE_UNDATED.' (avant '.$vague[self::BEFORE].')',
            isset($vague[self::AFTER]) => self::NOTE_UNDATED.' (après '.$vague[self::AFTER].')',
            ! $passengerStatus && $passengerClosed => self::NOTE_UNDATED_FREIGHT,
            default => self::NOTE_UNDATED,
        };
    }

    // ── Règles G3 et G6 de l'audit des gares (désactivées par défaut) ────────
    //
    // Décisions recommandées par l'audit des gares (QUESTIONS.md, matinée du
    // 21/11/2026 ; rapport aprem-2111/audit-gares-suite.md, « Décisions à
    // prendre » n° 5). vff:build-station-eras ne les applique qu'avec
    // --rules=g3,g6 ou la clé stations.era_rules (config/stations.php).

    /** Règle G3 : ouverture déduite bornée par la carte. */
    public const RULE_OPENING_BY_MAP = 'g3';

    /** Règle G6 : fermeture tirée de la fin des voyageurs de la ligne, rattachée à la section par PK. */
    public const RULE_CLOSURE_BY_LINE = 'g6';

    public const ERA_RULES = [self::RULE_OPENING_BY_MAP, self::RULE_CLOSURE_BY_LINE];

    /**
     * G3 : nombre d'ères au plus entre l'ouverture déduite et la première ère desservie. Au-delà, c'est la carte
     * qui a un trou (Cubzac-les-Ponts : ligne de 1874, gare montrée en 2020 seulement) et non la date : la règle
     * ne s'applique pas. Les retards relevés par l'audit sur des dates sourcées vont d'une à trois ères.
     */
    public const OPENING_BY_MAP_MAX_ERAS = 3;

    /**
     * G3. Ouverture DÉDUITE des lignes (provenance inferred:…, jamais une date sourcée) antérieure d'au moins
     * une ère à la première ère où la carte montre la gare desservie : la ligne n'est pas tracée en service
     * près de la gare aux ères intermédiaires. La carte se trompe parfois (trou de tracé), la date de la
     * ligne aussi (date de la première section d'une ligne ouverte par sections). L'intervalle retenu
     * contient la vraie date dans les deux cas : « entre l'année de la ligne et la première ère desservie »
     * (Messei : 1866 à 1880). Même convention que les fermetures en intervalle (resolveClosure) : la date est
     * la borne haute (31/12 de l'ère, ou aujourd'hui pour l'ère courante), `after` l'année qui précède la
     * borne basse. La carte n'est pas crue quand elle a un trou de plus de OPENING_BY_MAP_MAX_ERAS ères, ni
     * quand la gare a une fin de service (voyageurs, totale, démolition) datée au plus tard l'année de la
     * première ère desservie : la gare était alors déjà ouverte (Breuilpont : ligne de 1872, voyageurs fermés
     * en 1969, gare montrée fret seul en 2000 seulement).
     *
     * @param  string  $openedAt  ouverture déduite (AAAA-MM-JJ)
     * @param  array<int, ?string>  $eras  année de l'ère → statut de la gare (absente : null ou clé manquante)
     * @param  list<int>  $eraYears  années de toutes les ères historiques (hors ère courante)
     * @param  ?string  $firstEnd  première fin de service connue de la gare (AAAA-MM-JJ, source ou déduite)
     * @return array{date: string, precision: string, after: int, first_open: int}|null null : la carte ne contredit pas la date
     */
    public static function openingBoundedByMap(string $openedAt, array $eras, array $eraYears, int $currentYear, string $today,
        ?string $firstEnd = null): ?array
    {
        $open = array_keys(array_filter($eras, fn (?string $s): bool => in_array($s, self::OPEN_STATUSES, true)));
        if ($open === []) {
            return null;
        }
        $first = min($open);
        $year = (int) substr($openedAt, 0, 4);
        // Une ère (état au 31/12 de son année) postérieure à l'ouverture où la gare n'est pas desservie.
        $gap = array_filter($eraYears, fn (int $y): bool => $y >= $year && $y < $first && $y !== $currentYear);
        if ($gap === [] || count($gap) > self::OPENING_BY_MAP_MAX_ERAS
            || ($firstEnd !== null && (int) substr($firstEnd, 0, 4) <= $first)) {
            return null;
        }

        return ['date' => $first >= $currentYear ? $today : sprintf('%04d-12-31', $first), 'precision' => self::RANGE,
            'after' => $year - 1, 'first_open' => $first];
    }

    /**
     * G6. Fermeture d'une gare fermée sans date (intervalle des ères jusqu'à aujourd'hui) tirée de la fin des
     * voyageurs de ses lignes, seulement quand CHAQUE ligne rattachée (hors terminus nommé ou relu) a une fin
     * des voyageurs dont la section est donnée par PK et couvre la gare, sans réouverture de la ligne après.
     * Un événement « toute la ligne » ou rattaché par la seule géométrie ne date rien (Brison-Saint-Innocent
     * recevait 1871, date d'un événement mal rattaché à toute la 900000, ouverte). Une ligne qui ferme ensuite au
     * fret ou à tout trafic à la gare (événement daté après la fin des voyageurs, quel que soit son rattachement)
     * ne date rien non plus : le fret a continué, la fin des voyageurs n'est pas la fermeture de la gare (Talmay,
     * voyageurs 1938, fret jusqu'en 1971 ; Chemin-Peseux, 1969 et 1993). La date retenue est la plus
     * tardive des lignes ; elle doit tomber après la dernière ère où la gare est montrée desservie de façon sûre
     * (une ère est l'état au 31/12 de son année : Pont-de-Roide, fin de 1938 mais sûre en 2020, est écartée), au
     * plus tard à la borne haute des ères ($upperDate) et aujourd'hui, et après toute réouverture de la gare.
     *
     * @param  array<int, array{status: string, uncertain?: bool}|null>  $eras  année → état de la gare
     * @param  list<array{date: ?string, precision: ?string, freight_after?: bool}>  $lineEnds  une entrée par ligne exigée : sa fin des voyageurs
     *                                                                                          par PK (null : aucune), suivie ou non d'une fermeture au fret ou à tout trafic
     * @param  ?string  $reopenedAt  dernière réouverture connue de la gare (AAAA-MM-JJ)
     * @return array{date: string, precision: string}|null
     */
    public static function closureFromLinePassengerEnds(array $eras, int $currentYear, string $upperDate, string $today,
        array $lineEnds, ?string $reopenedAt = null): ?array
    {
        if ($lineEnds === []) {
            return null;
        }
        $best = null;
        foreach ($lineEnds as $end) {
            if (($end['date'] ?? null) === null || ($end['freight_after'] ?? false)) {
                return null;
            }
            $date = substr((string) $end['date'], 0, 10);
            if ($best === null || $date > $best['date']) {
                $best = ['date' => $date, 'precision' => in_array($end['precision'] ?? null, ['day', 'month', 'year'], true) ? (string) $end['precision'] : 'day'];
            }
        }
        $lastSure = null;
        foreach ($eras as $year => $s) {
            if ($year !== $currentYear && is_array($s) && in_array($s['status'], self::OPEN_STATUSES, true) && ! ($s['uncertain'] ?? false)) {
                $lastSure = max($lastSure ?? $year, $year);
            }
        }
        $year = (int) substr($best['date'], 0, 4);
        if (($lastSure !== null && $year <= $lastSure) || $best['date'] > $upperDate || $best['date'] > $today
            || ($reopenedAt !== null && $reopenedAt >= $best['date'])) {
            return null;
        }

        return $best;
    }
}
