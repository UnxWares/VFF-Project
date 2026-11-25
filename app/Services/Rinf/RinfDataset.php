<?php

namespace App\Services\Rinf;

/**
 * RINF (Register of Infrastructure) de l'Agence de l'Union européenne pour les chemins de fer (ERA), tel que le
 * publie le graphe de connaissances de l'ERA : point SPARQL public sans compte ni clé, un graphe nommé par
 * gestionnaire d'infrastructure (SNCF Réseau : ENDPOINT, graphe GRAPH_SNCF_RESEAU), vocabulaire
 * http://data.europa.eu/949/ (era:OperationalPoint, era:SectionOfLine, era:RunningTrack, era:Siding,
 * era:PlatformEdge, positions linéaires era:LinearPositioningSystemCoordinate en km + décalage en mètres).
 *
 * LICENCE (vérifiée le 24/11/2026) : le dump officiel du graphe (Zenodo, ERA collecteur des données, version
 * v11.0 du 31/10/2026, fichier rinf-kg-20261031.nq) est publié sous European Union Public Licence 1.2 ; les
 * dumps de 2025 l'étaient sous CC BY 4.0. Mention de copyright du site de l'ERA : « Reproduction is authorised,
 * provided the source is acknowledged, save where otherwise stated. » Aucune formule d'attribution imposée :
 * ATTRIBUTION reprend celle de l'usage (« RINF data © European Union Agency for Railways »).
 */
final class RinfDataset
{
    public const ENDPOINT = 'https://graph.data.era.europa.eu/repositories/rinf-plus';

    public const GRAPH_SNCF_RESEAU = 'http://data.europa.eu/949/graph/0087';

    public const LICENCE = 'EUPL 1.2 (ERA Knowledge Graph dump v11.0, 31/10/2026); ERA copyright notice: reproduction authorised, source acknowledged';

    public const ATTRIBUTION = 'RINF data © European Union Agency for Railways (ERA Knowledge Graph), modified by VFF';

    public const URL = 'https://graph.data.era.europa.eu/';

    public const LICENCE_URL = 'https://zenodo.org/records/16413403/latest';

    /** Fiche de provenance servie en exemple par la documentation de l'API. */
    public const SOURCE_EXAMPLE = ['key' => 'rinf', 'label' => 'RINF (ERA)', 'licence' => self::LICENCE,
        'dataset' => self::GRAPH_SNCF_RESEAU.' ('.self::ENDPOINT.')', 'fetched_at' => '2026-11-24', 'url' => self::URL,
        'attribution' => self::ATTRIBUTION, 'licence_url' => self::LICENCE_URL];

    /** Types de point d'exploitation du RINF (concept op-types) vers les sortes du contrat. */
    public const OP_KINDS = [
        10 => 'station', 20 => 'station', 30 => 'station', 70 => 'halt',
        80 => 'junction', 120 => 'junction', 90 => 'border', 140 => 'border', 130 => 'siding',
    ];

    /**
     * Fiche de provenance du schéma des voies.
     *
     * @return array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: string, attribution: string, licence_url: string}
     */
    public static function sourceMeta(?string $dataset, ?string $fetchedAt): array
    {
        return ['key' => 'rinf', 'label' => 'RINF (ERA)', 'licence' => self::LICENCE, 'dataset' => $dataset !== '' ? $dataset : null,
            'fetched_at' => $fetchedAt, 'url' => self::URL, 'attribution' => self::ATTRIBUTION, 'licence_url' => self::LICENCE_URL];
    }

    /** Sorte d'un point d'exploitation (contrat) d'après son type RINF et son nom. */
    public static function opKind(?int $opType, string $name): string
    {
        if ($opType !== null && isset(self::OP_KINDS[$opType])) {
            return self::OP_KINDS[$opType];
        }
        if (preg_match('/\b(évitement|evitement|voie d\'évitement)\b/iu', $name) === 1) {
            return 'passing_loop';
        }

        return 'other';
    }

    /**
     * Référence courte d'une voie : « Voie V1 », « Voie 1 », « Voie V1 de Paris-12 à Marseille-01 » et « 1 »
     * deviennent « V1 » (de même pour la voie 2), « Voie UNIQUE de … », « Voie unique » et « UNIQUE » deviennent
     * « VU », « Voie V1BIS » « V1BIS », « Voie E de Sireuil » « E », « 5 - COGNAC » « 5 » ; un autre identifiant
     * perd seulement le mot « Voie » de tête. L'identifiant complet reste dans `rinf_ref`.
     */
    public static function shortTrackRef(string $ref): string
    {
        $ref = trim($ref);
        if (preg_match('/^(voie\s+)?unique\b/iu', $ref) === 1) {
            return 'VU';
        }
        if (preg_match('/^(?:voie\s+)?(V?\d{1,4}[A-Z]?(?:\s?BIS|\s?TER)?|[A-Z]{1,2}\d{0,3})(?:\s*-\s*.*|\s+(?:de|du|des|d\'|à)\b.*)?$/iu', $ref, $m) === 1) {
            $id = strtoupper((string) preg_replace('/\s+/', '', $m[1]));

            return in_array($id, ['1', '2'], true) ? 'V'.$id : $id;
        }

        return trim((string) preg_replace('/^voie\s+/iu', '', $ref));
    }

    /**
     * Sens normal d'une voie de section dans le sens des PK : `up` (PK croissants), `down`, `both`, null.
     * Le RINF le donne par rapport à la section (N : de son début à sa fin, O : l'inverse, B : les deux).
     */
    public static function direction(?string $rinf, bool $sectionIncreasing): ?string
    {
        return match ($rinf) {
            'N' => $sectionIncreasing ? 'up' : 'down',
            'O' => $sectionIncreasing ? 'down' : 'up',
            'B' => 'both',
            default => null,
        };
    }

    /**
     * Sorte (contrat) d'une voie de point d'exploitation : liaison (« Liaison BS83/84 ») = crossover, voie
     * principale qui traverse le point (voie 1, voie 2, voie unique) = running, voie de
     * faisceau ou de triage = yard, voie d'installation terminale embranchée = spur, voie de service = siding,
     * voie de gare parcourue par les trains (era:RunningTrack d'un point) = loop.
     */
    public static function opTrackKind(string $rinfClass, string $ref, ?int $opType): string
    {
        return match (true) {
            preg_match('/^liaison\b/iu', trim($ref)) === 1 => 'crossover',
            $rinfClass === 'running' && in_array(self::shortTrackRef($ref), ['V1', 'V2', 'VU'], true) => 'running',
            $rinfClass === 'running' => 'loop',
            $opType === 130 || preg_match('/\bITE\b/u', $ref) === 1 => 'spur',
            $opType === 100 || preg_match('/\b(faisceau|triage)\b/iu', $ref) === 1 => 'yard',
            default => 'siding',
        };
    }

    /**
     * PK en mètres d'une position RINF « km + décalage » (kilomètre du poteau, décalage en mètres, négatif
     * possible : « 0+-31 » = -31 m).
     */
    public static function pkMeters(float $km, float $offset): int
    {
        return (int) round($km * 1000 + $offset);
    }
}
