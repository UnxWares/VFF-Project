<?php

namespace App\Services\Rinf;

use InvalidArgumentException;

/**
 * Requêtes SPARQL du RINF français : six lectures, chacune une ligne de résultat par objet (aucun produit
 * cartésien : chaque propriété multiple est soit regroupée, soit dédoublonnée à la lecture), bornées aux graphes
 * qui décrivent la France (GRAPHS, gestionnaires d'infrastructure français, mesure du 24/11/2026) et au pays FRA.
 * Avec une ligne (`--line=579000`), chaque requête ne lit que les sections de cette ligne nationale, leurs points
 * d'exploitation et ce qu'ils contiennent.
 */
final class RinfQueries
{
    /** Graphes nommés qui contiennent des sections de ligne en France (SNCF Réseau 0087 et six autres gestionnaires). */
    public const GRAPHS = ['0087', '3436', '3430', '60Z7', 'CY76', '3159', '0069'];

    public const PREFIXES = "PREFIX era: <http://data.europa.eu/949/>\nPREFIX wgs: <http://www.w3.org/2003/01/geo/wgs84_pos#>\n";

    private const FRA = '<http://publications.europa.eu/resource/authority/country/FRA>';

    public function __construct(private readonly ?string $lineCode = null)
    {
        if ($lineCode !== null && preg_match('/^[0-9]{3,6}$/', $lineCode) !== 1) {
            throw new InvalidArgumentException('Code de ligne attendu : 3 à 6 chiffres (ex. 579000).');
        }
    }

    /** Portée du cache : `fr` (toute la France) ou `line-<code>`. */
    public function scope(): string
    {
        return $this->lineCode === null ? 'fr' : 'line-'.$this->lineCode;
    }

    private function graphs(): string
    {
        return 'VALUES ?g { '.implode(' ', array_map(fn (string $g): string => '<http://data.europa.eu/949/graph/'.$g.'>', self::GRAPHS)).' }';
    }

    /** Filtre d'une section (?sol) sur la ligne demandée. */
    private function solScope(): string
    {
        return $this->lineCode === null ? '' : '?sol era:nationalLine/era:lineId ?scopeLine . FILTER(STRSTARTS(?scopeLine, "'.$this->lineCode.'"))';
    }

    /** Filtre d'un point d'exploitation (?op) : extrémité d'une section de la ligne demandée. */
    private function opScope(): string
    {
        return $this->lineCode === null ? '' : '{ ?scopeSol era:opStart ?op } UNION { ?scopeSol era:opEnd ?op } ?scopeSol era:nationalLine/era:lineId ?scopeLine . FILTER(STRSTARTS(?scopeLine, "'.$this->lineCode.'"))';
    }

    /** Sections de ligne, leurs extrémités et le PK (poteau kilométrique et décalage) de chacune. */
    public function sections(): string
    {
        return self::PREFIXES.'SELECT DISTINCT ?g ?sol ?uri ?lineId ?len ?nature ?opS ?opE ?skm ?soff ?slrs ?ekm ?eoff ?elrs WHERE { '.$this->graphs().' GRAPH ?g {
  ?sol a era:SectionOfLine ; era:inCountry '.self::FRA.' ; era:canonicalURI ?uri ; era:nationalLine ?lps ; era:opStart/era:uopid ?opS ; era:opEnd/era:uopid ?opE .
  ?lps era:lineId ?lineId . '.$this->solScope().'
  OPTIONAL { ?sol era:lengthOfSectionOfLine ?len } OPTIONAL { ?sol era:solNature ?nature }
  OPTIONAL { ?sol era:netReference/era:startsAt/era:hasLrsCoordinate ?cs . ?cs era:kmPost ?kps ; era:offsetFromKilometricPost ?soff . ?kps era:kilometer ?skm . OPTIONAL { ?kps era:hasLRS/era:lineId ?slrs } }
  OPTIONAL { ?sol era:netReference/era:endsAt/era:hasLrsCoordinate ?ce . ?ce era:kmPost ?kpe ; era:offsetFromKilometricPost ?eoff . ?kpe era:kilometer ?ekm . OPTIONAL { ?kpe era:hasLRS/era:lineId ?elrs } }
} } ORDER BY ?uri ?skm ?ekm';
    }

    /** Voies des sections : identifiant, sens, vitesse, rayon, système de ligne de contact. */
    public function sectionTracks(): string
    {
        return self::PREFIXES.'SELECT DISTINCT ?uri ?t ?trackId ?dir ?vmax ?radius ?cls WHERE { '.$this->graphs().' GRAPH ?g {
  ?sol a era:SectionOfLine ; era:inCountry '.self::FRA.' ; era:canonicalURI ?uri ; era:hasPart ?t . '.$this->solScope().'
  ?t a era:RunningTrack ; era:trackId ?trackId .
  OPTIONAL { ?t era:trackDirection ?dir } OPTIONAL { ?t era:maximumPermittedSpeed ?vmax } OPTIONAL { ?t era:minimumHorizontalRadius ?radius }
  OPTIONAL { ?t era:contactLineSystem/era:contactLineSystemType ?cls }
} } ORDER BY ?t ?cls';
    }

    /**
     * Paramètres déclarés des voies des sections, une ligne par voie : système d'alimentation et type de ligne de
     * contact, contrôle de vitesse hérité et autres systèmes de protection (regroupés « |»), niveau ETCS, version
     * GSM-R, gabarits (regroupés), catégorie de charge EN 15528 et classe nationale, écartement, et les paramètres
     * que le gestionnaire déclare sans objet (era:notApplicable : « aucun », distinct de « non dit »). Mesure du
     * 25/11/2026 (sept graphes français) : chacun rempli sur 97 à 100 % des 23 818 voies hors liaisons.
     */
    public function trackParameters(): string
    {
        return self::PREFIXES.'SELECT ?t (SAMPLE(?ess) AS ?energy) (SAMPLE(?clt) AS ?contact) (SAMPLE(?eo) AS ?etcs) (SAMPLE(?el) AS ?etcsLevel)
  (GROUP_CONCAT(DISTINCT STR(?tp); separator="|") AS ?protection) (GROUP_CONCAT(DISTINCT STR(?otp); separator="|") AS ?otherProtection)
  (SAMPLE(?gv) AS ?gsmr) (GROUP_CONCAT(DISTINCT STR(?gp); separator="|") AS ?gauging) (SAMPLE(?lcc) AS ?loadCategory)
  (SAMPLE(?nlc) AS ?nationalLoad) (SAMPLE(?wg) AS ?gauge) (GROUP_CONCAT(DISTINCT STR(?na); separator="|") AS ?notApplicable)
WHERE { '.$this->graphs().' GRAPH ?g {
  ?sol a era:SectionOfLine ; era:inCountry '.self::FRA.' ; era:hasPart ?t . '.$this->solScope().'
  ?t a era:RunningTrack .
  OPTIONAL { ?t era:contactLineSystem ?cl . OPTIONAL { ?cl era:energySupplySystem ?ess } OPTIONAL { ?cl era:contactLineSystemType ?clt } }
  OPTIONAL { ?t era:protectionLegacySystem ?tp } OPTIONAL { ?t era:otherTrainProtection ?otp }
  OPTIONAL { ?t era:etcs ?eo . OPTIONAL { ?eo era:etcsLevelType ?el } }
  OPTIONAL { ?t era:gsmRVersion ?gv } OPTIONAL { ?t era:gaugingProfile ?gp }
  OPTIONAL { ?t era:trackLoadCapability/era:loadCapabilityLineCategory ?lcc } OPTIONAL { ?t era:nationalLoadCapability ?nlc }
  OPTIONAL { ?t era:wheelSetGauge ?wg }
  OPTIONAL { ?t era:notApplicable ?na . FILTER(?na IN (era:protectionLegacySystem, era:gsmRVersion, era:etcs, era:gaugingProfile, era:contactLineSystem, era:trackLoadCapability, era:wheelSetGauge)) }
} } GROUP BY ?t ORDER BY ?t';
    }

    /** Profil de déclivités déclaré des voies des sections, regroupé par voie (« +04.0(+18.300)|+02.2 »). */
    public function gradients(): string
    {
        return self::PREFIXES.'SELECT ?t (GROUP_CONCAT(DISTINCT ?gp; separator="|") AS ?profile) WHERE { '.$this->graphs().' GRAPH ?g {
  ?sol a era:SectionOfLine ; era:inCountry '.self::FRA.' ; era:hasPart ?t . '.$this->solScope().'
  ?t era:gradientProfile ?gp .
} } GROUP BY ?t ORDER BY ?t';
    }

    /** Points d'exploitation : UOPID, nom, type, une position WGS 84. */
    public function operationalPoints(): string
    {
        return self::PREFIXES.'SELECT ?g ?op ?uopid ?name ?type (SAMPLE(?la) AS ?lat) (SAMPLE(?lo) AS ?lon) WHERE { '.$this->graphs().' GRAPH ?g {
  ?op a era:OperationalPoint ; era:inCountry '.self::FRA.' ; era:uopid ?uopid ; era:opName ?name . '.$this->opScope().'
  OPTIONAL { ?op era:opType ?type } OPTIONAL { ?op era:netReference ?nr . ?nr wgs:lat ?la ; wgs:long ?lo }
} } GROUP BY ?g ?op ?uopid ?name ?type ORDER BY ?op';
    }

    /** Voies et voies de service des points d'exploitation. */
    public function opTracks(): string
    {
        return self::PREFIXES.'SELECT DISTINCT ?uopid ?t ?cls ?ref ?vmax ?len ?grad ?ctype WHERE { '.$this->graphs().' GRAPH ?g {
  ?op a era:OperationalPoint ; era:inCountry '.self::FRA.' ; era:uopid ?uopid ; era:hasPart ?t . '.$this->opScope().'
  { ?t a era:RunningTrack ; era:trackId ?ref . BIND("running" AS ?cls) } UNION { ?t a era:Siding ; era:sidingId ?ref . BIND("siding" AS ?cls) }
  OPTIONAL { ?t era:maximumPermittedSpeed ?vmax } OPTIONAL { ?t era:lengthOfSiding ?len } OPTIONAL { ?t era:gradient ?grad }
  OPTIONAL { ?t era:contactLineSystem/era:contactLineSystemType ?ctype }
} } ORDER BY ?t ?ctype';
    }

    /** Bords de quai des voies des points d'exploitation, avec leur hauteur (concept platform-heights). */
    public function platforms(): string
    {
        return self::PREFIXES.'SELECT DISTINCT ?uopid ?t ?pe ?ref ?len ?h WHERE { '.$this->graphs().' GRAPH ?g {
  ?op a era:OperationalPoint ; era:inCountry '.self::FRA.' ; era:uopid ?uopid ; era:hasPart ?t . '.$this->opScope().'
  ?t era:hasPart ?pe . ?pe a era:PlatformEdge ; era:platformId ?ref .
  OPTIONAL { ?pe era:lengthOfPlatform ?len } OPTIONAL { ?pe era:platformHeight ?h }
} } ORDER BY ?pe ?h';
    }
}
