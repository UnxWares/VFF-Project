<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Services\WikipediaLineParser;
use RuntimeException;

/**
 * Faits relus d'Argentan Web Ferroviaire (table de source argentanwebferro_facts) traduits pour le
 * moteur (vff:apply-argentanwebferro) : événements de lignes (line_events, source
 * « argentanwebferro », rangée après Wikipédia et avant OpenHistoricalMap dans
 * EraTimeline::SOURCE_PRIORITY) et arrêts des listes par ligne (source_stations, posés sur le
 * tracé par vff:import-station-sources --source=pk).
 *
 * Règles (décision de l'utilisateur du 2026-11-20 sur la question 19) :
 *   - seuls les faits relus ou gardés (review <> rejected), rattachés à une ligne, entrent ;
 *   - une date entre comme la source la donne (jour, mois ou année) ; une période (« entre 1966 et
 *     1971 ») ou un fait sans date n'entre pas : le moteur ne date jamais à la place de la source ;
 *   - accidents et événements divers restent dans la table de source (les accidents ont leur
 *     propre fiche, avec leurs sources) ;
 *   - les faits contredits par des sources plus sûres sont écartés un par un, avec leur raison
 *     (database/data/argentanwebferro_moteur.php, clé « skip ») ;
 *   - arrêts : ceux que VFF connaît déjà sur la ligne (ils calent les PK de l'article sur le
 *     tracé) et ceux qu'une autre source confirme (clé « stops ») ; jamais une bifurcation, jamais
 *     un arrêt absent de VFF sans confirmation.
 *
 * Droits (source_rights.php, « argentanwebferro ») : un fait, un lien vers l'article, une phrase
 * citée au plus. Aucun texte d'article n'est repris au-delà de cette phrase.
 */
final readonly class EngineFacts
{
    public const SOURCE = 'argentanwebferro';

    public const FINGERPRINT_PREFIX = 'argentanwebferro:';

    /** Types d'événements versés au moteur (cartes et chronologie). */
    public const EVENT_TYPES = [
        'opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight',
        'closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling',
        'concession', 'electrification', 'modernization',
    ];

    /** Statuts de la comparaison (VffComparison) d'un arrêt déjà connu de VFF sur sa ligne. */
    private const KNOWN_STOP = [VffComparison::PRESENT, VffComparison::OTHER_NATURE];

    /** Longueur maximale de la phrase citée (une phrase, cf. obligations du registre des droits). */
    private const QUOTE_MAX = 240;

    /**
     * @param  array<string, string>  $skip  empreinte du fait → raison de l'écart
     * @param  array<string, array<string, mixed>>  $stops  empreinte de l'arrêt → confirmation (name, line, source_url, quote, reason)
     */
    public function __construct(public array $skip = [], public array $stops = []) {}

    public static function path(): string
    {
        return database_path('data/argentanwebferro_moteur.php');
    }

    public static function load(?string $path = null): self
    {
        $path ??= self::path();
        if (! is_file($path)) {
            throw new RuntimeException("Décisions introuvables : {$path}");
        }
        $data = require $path;

        return new self($data['skip'] ?? [], $data['stops'] ?? []);
    }

    /**
     * Erreurs de forme du fichier de décisions (vide si valide).
     *
     * @return list<string>
     */
    public function validate(): array
    {
        $errors = [];
        foreach ($this->skip as $fp => $reason) {
            if (! preg_match('/^[0-9a-f]{40}$/', (string) $fp) || trim((string) $reason) === '') {
                $errors[] = "skip {$fp} : empreinte de 40 caractères et raison";
            }
        }
        foreach ($this->stops as $fp => $c) {
            if (! preg_match('/^[0-9a-f]{40}$/', (string) $fp)) {
                $errors[] = "stops {$fp} : empreinte de 40 caractères";
            }
            foreach (['name', 'source_url', 'reason'] as $k) {
                if (trim((string) ($c[$k] ?? '')) === '') {
                    $errors[] = "stops {$fp} : {$k} manquant";
                }
            }
            if (! str_starts_with((string) ($c['source_url'] ?? ''), 'http')) {
                $errors[] = "stops {$fp} : source_url doit être un lien";
            }
        }

        return $errors;
    }

    /**
     * Ligne de line_events pour un fait, ou la raison pour laquelle il n'entre pas.
     *
     * @param  array<string, mixed>  $fact  ligne de argentanwebferro_facts
     * @return array{row: ?array<string, mixed>, reason: ?string}
     */
    public function event(array $fact): array
    {
        $fp = (string) $fact['fingerprint'];
        $precision = FactDatePrecision::tryFrom((string) ($fact['date_precision'] ?? ''));
        $from = self::place($fact['section_from'] ?? null);
        $to = self::place($fact['section_to'] ?? null);
        $reason = match (true) {
            ($fact['kind'] ?? null) !== ArgentanFactKind::Event->value => 'pas un événement',
            ($fact['review'] ?? null) === 'rejected' => 'rejeté à la relecture',
            isset($this->skip[$fp]) => 'écarté : '.$this->skip[$fp],
            empty($fact['line_id']) => 'aucune ligne VFF (fait de plusieurs lignes)',
            ! in_array($fact['event_type'] ?? null, self::EVENT_TYPES, true) => 'type gardé en source ('.($fact['event_type'] ?? '?').')',
            empty($fact['event_date']) || $precision === null => 'sans date',
            $precision->lineEventPrecision() === null => 'période (pas une date)',
            // « Jusqu'à Bernay » sans départ : ni la portion ni toute la ligne, le fait reste en source.
            ($from === null) !== ($to === null) => 'portion incomplète (une seule borne)',
            default => null,
        };
        if ($reason !== null) {
            return ['row' => null, 'reason' => $reason];
        }

        return ['row' => [
            'line_id' => (int) $fact['line_id'],
            'type' => (string) $fact['event_type'],
            'event_date' => (string) $fact['event_date'],
            'date_precision' => $precision->lineEventPrecision(),
            'description' => self::describe((string) $fact['event_type'], $from, $to),
            'source' => self::SOURCE,
            'source_url' => (string) $fact['source_url'],
            'source_quote' => self::quote($fact['source_quote'] ?? null),
            // Rangée sous Wikipédia : confiance moyenne, comme les autres sources secondaires.
            'confidence' => 'medium',
            'section_from' => $from,
            'section_to' => $to,
            // Portion à résoudre sur le tracé (vff:resolve-event-sections) ; sans portion : toute la ligne.
            'section_resolution' => $from === null ? null : 'pending',
            'fingerprint' => self::FINGERPRINT_PREFIX.$fp,
        ], 'reason' => null];
    }

    /**
     * Ligne de source_stations pour un arrêt, ou null (arrêt absent sans confirmation, bifurcation).
     *
     * @param  array<string, mixed>  $fact
     * @param  array<string, int>  $lineIdsByRef  code SNCF ou slug → identifiant (ligne d'une confirmation)
     * @return array<string, mixed>|null
     */
    public function stop(array $fact, array $lineIdsByRef = []): ?array
    {
        if (($fact['kind'] ?? null) !== ArgentanFactKind::Stop->value || ($fact['review'] ?? null) === 'rejected'
            || ($fact['stop_nature'] ?? null) === 'embranchement') {
            return null;
        }
        $fp = (string) $fact['fingerprint'];
        $confirmed = $this->stops[$fp] ?? null;
        $known = in_array($fact['vff_status'] ?? null, self::KNOWN_STOP, true);
        if ($confirmed === null && ! $known) {
            return null;
        }
        $lineId = isset($confirmed['line']) ? ($lineIdsByRef[(string) $confirmed['line']] ?? null) : null;
        $lineId ??= empty($fact['line_id']) ? null : (int) $fact['line_id'];
        if ($lineId === null) {
            return null;
        }
        $properties = array_filter([
            'page' => (string) $fact['source_url'],
            'source_url' => (string) $fact['source_url'],
            'nature' => $fact['stop_nature'] ?? null,
            'name_in_source' => (string) $fact['stop_name'],
            'confirmation' => $confirmed === null ? null : array_intersect_key($confirmed, array_flip(['source_url', 'quote', 'reason'])),
        ], fn ($v) => $v !== null);

        return [
            'source' => self::SOURCE,
            'source_ref' => $fp,
            'line_id' => $lineId,
            // Arrêt connu : le nom de la gare de VFF (« Périers-en-Cotentin » pour « Périers »), pour que la
            // pose par PK la retrouve (gare connue homonyme) au lieu d'en créer une seconde.
            'name' => (string) ($confirmed['name'] ?? self::knownName($fact['vff_detail'] ?? null) ?? self::place((string) $fact['stop_name'])),
            // « pk_m » => null dans la confirmation : PK de l'article dans un autre repère (cumulé sur plusieurs
            // lignes), la gare est alors placée par sa commune.
            'pk_m' => match (true) {
                $confirmed !== null && array_key_exists('pk_m', $confirmed) => $confirmed['pk_m'] === null ? null : (int) $confirmed['pk_m'],
                isset($fact['pk_m']) => (int) $fact['pk_m'],
                default => null,
            },
            'properties' => $properties,
        ];
    }

    /** Description rédigée par VFF (gabarit traduit par EventText::structured). */
    public static function describe(string $type, ?string $from, ?string $to): string
    {
        $label = WikipediaLineParser::TYPE_LABELS[$type] ?? 'Événement';
        if ($from !== null && $to !== null) {
            return $label.(preg_match('/^[AEIOUYÉÈÊÂÎÔ]/u', $from) ? ' d\'' : ' de ').$from.' à '.$to;
        }

        return $label.' de la ligne';
    }

    /**
     * Nom de lieu écrit comme dans VFF : « St », « Ste » développés (« Ste Gauburge » →
     * « Sainte-Gauburge »), barre oblique de l'auteur lue « sur » (« Neuville/Touques »).
     */
    public static function place(?string $name): ?string
    {
        $n = trim((string) $name);
        if ($n === '') {
            return null;
        }
        $n = (string) preg_replace('~\s*/\s*~u', '-sur-', $n);
        $n = (string) preg_replace('/\bSte[ -]/u', 'Sainte-', $n);
        $n = (string) preg_replace('/\bSt[ -]/u', 'Saint-', $n);

        return $n;
    }

    /** Nom de la gare de VFF relevé par la comparaison (« VFF : Coutances » ou « VFF : Messei (gare), article : halte »). */
    public static function knownName(?string $detail): ?string
    {
        return preg_match('/^VFF : (.+?)(?: \((?:gare|halte|point_arret|passage_niveau|embranchement)\), article : .*)?$/u', (string) $detail, $m) === 1 ? $m[1] : null;
    }

    /** Une phrase citée au plus, coupée proprement (registre des droits). */
    private static function quote(?string $quote): ?string
    {
        $q = trim(str_replace(["\u{2014}", '--'], '-', (string) $quote));

        return $q === '' ? null : mb_strimwidth($q, 0, self::QUOTE_MAX, '…');
    }
}
