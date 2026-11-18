<?php

namespace App\Services\Traction;

/**
 * Électrification d'une PORTION de ligne à la date d'une ère.
 *
 * Entrées :
 *   - événements datés, chacun avec sa PORTÉE (scope), du plus au moins sûr :
 *       curated   : chronologie experte (TractionCuration), remplace tout le reste ;
 *       located   : événement localisé sur la portion (section_geom, PK, gares) ;
 *       neighbour : date d'une portion voisine de la même ligne (portion électrifiée
 *                   aujourd'hui sans événement propre) ;
 *       line      : événement « toute la ligne » ;
 *       unlocated : événement de la ligne dont la portion n'a pas pu être localisée.
 *     Seule la meilleure portée présente est retenue pour les mises sous tension ;
 *     les désélectrifications localisées (ou toute la ligne) s'appliquent toujours.
 *   - l'état ACTUEL observé (SNCF Open Data > OSM > BD TOPO) : électrifiée ou non, tension ;
 *   - LGV (toujours 25 kV 50 Hz) et statut de la portion à l'ère.
 *
 * Sortie de stateAt() : electrified, voltage_v, source (cf. migration
 * 2026_10_19_180000), year (année de l'électrification en vigueur). La source
 * « unknown » signale une portion électrifiée AUJOURD'HUI dont on ignore la date
 * d'électrification : à cette ère, on ne sait pas (electrified = false).
 */
final class ElectrificationTimeline
{
    public const SCOPES = ['curated', 'located', 'neighbour', 'line', 'unlocated'];

    private const SOURCE_BY_SCOPE = [
        'curated' => 'curated', 'located' => 'event', 'neighbour' => 'event_neighbour',
        'line' => 'event_line', 'unlocated' => 'event_unlocated',
    ];

    /** Première électrification ferroviaire en France (Paris-Austerlitz - Orsay, 1900). */
    public const FIRST_YEAR = 1900;

    /** Premières lignes en 50 Hz (Aix-les-Bains - La Roche-sur-Foron, 1951). */
    public const FIRST_AC50_YEAR = 1951;

    /** Décision ministérielle du 20 août 1920 : 1 500 V continu pour les grandes compagnies. */
    public const DC1500_DECISION_YEAR = 1920;

    /**
     * Lignes pionnières du Midi en 12 kV 16 2/3 Hz (1912-1920) : converties en 1 500 V
     * continu après la décision de 1920 (Lannemezan - Arreau : « conversion en 1923 en
     * 1500 V »). Seule exception connue, Perpignan - Villefranche, est en curation.
     */
    public const MIDI_12KV_CONVERSION_YEAR = 1923;

    /** @var array<int, array{date: string, kind: string, voltage_v: ?int, scope: string}> */
    private array $elec = [];

    /** @var array<int, array{date: string, kind: string, voltage_v: ?int, scope: string}> */
    private array $deelec = [];

    private ?string $bestScope = null;

    /**
     * @param  array<int, array{date: string, kind: string, voltage_v?: ?int, scope: string}>  $events
     * @param  array{state: ?bool, voltage_v: ?int, source: ?string}  $current  état observé aujourd'hui (state null = inconnu)
     */
    public function __construct(
        array $events,
        private readonly array $current = ['state' => null, 'voltage_v' => null, 'source' => null],
        private readonly bool $isLgv = false,
        private readonly bool $historicElectrified = false,
    ) {
        $events = array_values(array_filter($events, fn ($e) => in_array($e['scope'], self::SCOPES, true)));
        $curated = array_values(array_filter($events, fn ($e) => $e['scope'] === 'curated'));
        if ($curated) {
            $events = $curated;
        }
        foreach ($events as $e) {
            $e['voltage_v'] ??= null;
            // Avant 1951, la tension d'un événement voisin de la ligne vaut tension citée (cf. voltageFor).
            if ($e['voltage_v'] === null && ($e['voltage_hint'] ?? 20000) < 20000 && (int) substr($e['date'], 0, 4) < self::FIRST_AC50_YEAR) {
                $e['voltage_v'] = $e['voltage_hint'];
            }
            if ($e['kind'] === ElectrificationText::KIND_DEELECTRIFICATION) {
                if (in_array($e['scope'], ['curated', 'located', 'line'], true)) {
                    $this->deelec[] = $e;
                }

                continue;
            }
            if ($e['kind'] !== ElectrificationText::KIND_ELECTRIFICATION) {
                continue;
            }
            // Pas de traction électrique en France avant 1900 : date d'ouverture prise pour une électrification.
            if ($e['scope'] !== 'curated' && (int) substr($e['date'], 0, 4) < self::FIRST_YEAR) {
                continue;
            }
            // Une mise sous tension en 50 Hz avant 1951 est une erreur de lecture.
            if ($e['scope'] !== 'curated' && ($e['voltage_v'] ?? 0) >= 20000 && (int) substr($e['date'], 0, 4) < self::FIRST_AC50_YEAR) {
                continue;
            }
            // Événement peu localisé, postérieur à 1951, dont la tension contredit la tension
            // actuelle de la portion : il parle d'une autre section (ex. « ré-électrification de
            // Bellegarde - Genève en 25 kV » sur la ligne de Lyon à Genève, en 1 500 V ailleurs).
            // Les tensions pionnières (12 kV Midi, 650 V troisième rail) sont gardées.
            if (in_array($e['scope'], ['line', 'unlocated', 'neighbour'], true) && $e['voltage_v'] !== null
                && (int) substr($e['date'], 0, 4) >= self::FIRST_AC50_YEAR
                && ($this->current['state'] ?? null) === true && $this->current['voltage_v'] !== null
                && $e['voltage_v'] !== $this->current['voltage_v']) {
                continue;
            }
            $this->elec[] = $e;
        }
        // Portion NON électrifiée aujourd'hui : une date peu localisée (toute la ligne, portion
        // non résolue, voisine) parle d'une autre partie de la ligne, sauf si une désélectrification
        // postérieure explique que la caténaire a disparu depuis.
        if (($this->current['state'] ?? null) === false) {
            $this->elec = array_values(array_filter($this->elec, function ($e) {
                if (in_array($e['scope'], ['curated', 'located'], true)) {
                    return true;
                }
                foreach ($this->deelec as $d) {
                    if ($d['date'] > $e['date']) {
                        return true;
                    }
                }

                return false;
            }));
        }
        foreach (self::SCOPES as $scope) {
            if (array_filter($this->elec, fn ($e) => $e['scope'] === $scope)) {
                $this->bestScope = $scope;
                break;
            }
        }
        $all = $this->elec;
        $this->elec = array_values(array_filter($this->elec, fn ($e) => $e['scope'] === $this->bestScope));
        // Portion électrifiée aujourd'hui : une électrification « toute la ligne » POSTÉRIEURE
        // aux événements localisés (ré-électrification, passage du troisième rail à la caténaire,
        // électrification après une dépose) la concerne aussi.
        if (in_array($this->bestScope, ['located', 'neighbour'], true) && ($this->current['state'] ?? null) === true) {
            $last = max(array_column($this->elec, 'date'));
            foreach ($all as $e) {
                if ($e['scope'] === 'line' && $e['date'] > $last) {
                    $this->elec[] = $e;
                }
            }
        }
        usort($this->elec, fn ($a, $b) => strcmp($a['date'], $b['date']));
        usort($this->deelec, fn ($a, $b) => strcmp($a['date'], $b['date']));
    }

    /** Vrai si au moins une date de mise sous tension est connue pour la portion. */
    public function isDated(): bool
    {
        return $this->elec !== [];
    }

    /** Année de la première électrification connue (ou null). */
    public function firstYear(): ?int
    {
        return $this->elec ? (int) substr($this->elec[0]['date'], 0, 4) : null;
    }

    /**
     * État à la fin de l'année $year (ou aujourd'hui pour l'ère courante).
     *
     * @return array{electrified: bool, voltage_v: ?int, source: ?string, year: ?int}
     */
    public function stateAt(int $year, bool $isCurrent = false, ?string $status = 'in_service'): array
    {
        $no = fn (?string $source) => ['electrified' => false, 'voltage_v' => null, 'source' => $source, 'year' => null];

        // Voie déposée ou disparue : plus de caténaire. Ligne en construction : pas encore.
        if (in_array($status, ['dismantled', 'razed', 'construction'], true)) {
            return $no('status');
        }
        $cutoff = $isCurrent ? date('Y-m-d') : sprintf('%04d-12-31', $year);
        $current = $this->current;

        // Ère courante (et décennie 2020, qui la précède immédiatement) : l'observation actuelle
        // fait foi, y compris pour une caténaire déposée ou une électrification récente non datée.
        // (Sauf électrification datée APRÈS l'ère : Gretz - Nogent-sur-Seine en 2022 n'est pas en 2020.)
        if (($isCurrent || ($year >= 2020 && ! $this->firstElectrificationAfter($cutoff))) && $current['state'] !== null) {
            return $current['state']
                ? ['electrified' => true, 'voltage_v' => $current['voltage_v'] ?? ($this->isLgv ? 25000 : null), 'source' => $current['source'], 'year' => $this->lastElectrificationYear($cutoff)]
                : $no($current['source']);
        }
        // LGV : électrifiées en 25 kV 50 Hz dès leur mise en service.
        if ($this->isLgv && $year >= 1981) {
            return ['electrified' => true, 'voltage_v' => 25000, 'source' => 'lgv', 'year' => null];
        }

        // Rejeu des mises sous tension / hors tension jusqu'à la date de l'ère.
        $transitions = [];
        foreach ($this->elec as $e) {
            $transitions[] = $e + ['on' => true];
        }
        foreach ($this->deelec as $e) {
            $transitions[] = $e + ['on' => false];
        }
        usort($transitions, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: ($a['on'] <=> $b['on']));
        $state = null;
        foreach ($transitions as $t) {
            if ($t['date'] > $cutoff) {
                break;
            }
            // Nouvelle mise sous tension d'une portion déjà électrifiée, sans changement de tension
            // (prolongement, achèvement, passage du troisième rail à la caténaire) : l'électrification
            // en vigueur, sa date et sa source sont conservées.
            if ($t['on'] && ($state['on'] ?? false) && ($t['voltage_v'] === null || $state['voltage_v'] === null || $t['voltage_v'] === $state['voltage_v'])) {
                $state['voltage_v'] ??= $t['voltage_v'];

                continue;
            }
            $state = $t;
        }

        if ($state !== null) {
            if (! $state['on']) {
                return $no('event');
            }
            $source = self::SOURCE_BY_SCOPE[$state['scope']];
            $voltage = $this->voltageFor($state, $year);
            if ($voltage === 12000 && $state['scope'] !== 'curated' && $year >= self::MIDI_12KV_CONVERSION_YEAR
                && (int) substr($state['date'], 0, 4) < self::MIDI_12KV_CONVERSION_YEAR) {
                $voltage = 1500;
            }

            return ['electrified' => true, 'voltage_v' => $voltage, 'source' => $source, 'year' => (int) substr($state['date'], 0, 4)];
        }

        // Aucune transition avant l'ère.
        if ($this->elec) {
            // Électrifiée PLUS TARD : pas encore à cette ère.
            return $no(self::SOURCE_BY_SCOPE[$this->bestScope]);
        }
        $knownToday = $current['state'] === true || $this->historicElectrified;
        if ($this->deelec && ! $this->elec) {
            // Désélectrifiée plus tard sans date d'électrification : électrifiée avant, depuis une date inconnue.
            return $year < self::FIRST_YEAR ? $no('rule') : $no('unknown');
        }
        if ($knownToday) {
            $v = (int) ($current['voltage_v'] ?? 0);
            // Bornes d'époque : rien avant 1900 ; 1 500 V / 3 kV continu après la décision du
            // 20 août 1920 (les électrifications antérieures, pionnières, sont datées) ;
            // 15 et 25 kV alternatif (frontières, 50 Hz) après 1951.
            if ($year < self::FIRST_YEAR
                || ($v >= 15000 && $year < self::FIRST_AC50_YEAR)
                || (in_array($v, [1500, 3000], true) && $year <= self::DC1500_DECISION_YEAR)) {
                return $no('rule');
            }
            // Décennie la plus récente : l'état actuel vaut (les rares électrifications
            // postérieures à 2020 sans date connue sont l'exception).
            if ($year >= 2020 && $current['state'] === true) {
                return ['electrified' => true, 'voltage_v' => $current['voltage_v'], 'source' => $current['source'], 'year' => null];
            }

            return $no('unknown');
        }

        // Aucune source : non électrifiée par défaut (la très grande majorité des lignes disparues).
        return $no($current['state'] === false ? $current['source'] : 'none');
    }

    /** Tension d'une électrification : citée, sinon actuelle si compatible, sinon règle d'époque. */
    private function voltageFor(array $event, int $year): ?int
    {
        $current = $this->current;
        $eventYear = (int) substr($event['date'], 0, 4);
        // Dernière décennie : la tension observée aujourd'hui prime (changements de tension non datés).
        if ($year >= 2020 && ($current['state'] ?? null) === true && $current['voltage_v'] && ! $this->hasLaterEvent($event)) {
            return $current['voltage_v'];
        }
        if ($event['voltage_v'] !== null) {
            return $event['voltage_v'];
        }
        // Tension citée par un autre événement de la ligne à ± 5 ans (cf. vff:enrich-era-segments) :
        // préférée à la tension actuelle avant 1951 (troisième rail 650-750 V de la banlieue, depuis
        // passé au 25 kV), après elle en cas de désaccord (autre section, autre tension).
        $hint = $event['voltage_hint'] ?? null;
        $currentOk = ($current['state'] ?? null) === true && $current['voltage_v']
            && ($current['voltage_v'] < 20000 || $eventYear >= self::FIRST_AC50_YEAR);
        if ($hint !== null && ($hint < 20000 || $eventYear >= self::FIRST_AC50_YEAR) && ($eventYear < self::FIRST_AC50_YEAR || ! $currentOk)) {
            return $hint;
        }
        if ($currentOk) {
            return $current['voltage_v'];
        }
        if ($eventYear > self::DC1500_DECISION_YEAR && $eventYear < self::FIRST_AC50_YEAR) {
            return 1500;
        }

        return null;
    }

    private function hasLaterEvent(array $event): bool
    {
        foreach (array_merge($this->elec, $this->deelec) as $e) {
            if ($e['date'] > $event['date']) {
                return true;
            }
        }

        return false;
    }

    /** Vrai si la première mise sous tension connue est postérieure à $cutoff. */
    private function firstElectrificationAfter(string $cutoff): bool
    {
        return $this->elec !== [] && $this->elec[0]['date'] > $cutoff;
    }

    private function lastElectrificationYear(string $cutoff): ?int
    {
        $year = null;
        foreach ($this->elec as $e) {
            if ($e['date'] <= $cutoff) {
                $year = (int) substr($e['date'], 0, 4);
            }
        }

        return $year;
    }
}
