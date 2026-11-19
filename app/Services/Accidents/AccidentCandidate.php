<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;

/**
 * Un accident en cours d'import : faits lus dans une source (Wikidata, inventaire du BEA-TT),
 * complétés par les autres sources (article Wikipédia, inventaire fusionné) puis par la
 * localisation. Les observations de bilan sont gardées par fournisseur pour le recoupement :
 * le bilan retenu et les divergences sont décidés à l'écriture (AccidentImporter).
 */
final class AccidentCandidate
{
    public ?string $wikidataId = null;

    /** Date AAAA-MM-JJ (1er du mois ou 1er janvier si la précision est moindre). */
    public ?string $date = null;

    public ?AccidentDatePrecision $precision = null;

    public ?string $dateSource = null;

    public ?string $time = null;

    public ?AccidentKind $kind = null;

    public ?string $kindSource = null;

    /** @var array<string, string> libellés par langue (fr, en, de) */
    public array $labels = [];

    public ?float $lat = null;

    public ?float $lng = null;

    public ?AccidentLocationPrecision $locationPrecision = null;

    public ?string $locationSource = null;

    public ?string $communeInsee = null;

    public ?string $communeName = null;

    public ?string $departmentCode = null;

    /** Numéro de passage à niveau cité par la source (« PN n° 25 »). */
    public ?int $levelCrossing = null;

    /** PK (en mètres) du passage à niveau retrouvé dans le jeu SNCF Réseau. */
    public ?int $levelCrossingPk = null;

    /**
     * Autres façons de nommer la commune, essayées après communeName : [nom, département|null]
     * (commune tirée du lien du rapport, correction d'un intitulé erroné).
     *
     * @var list<array{0: string, 1: ?string}>
     */
    public array $placeHints = [];

    /**
     * Les lieux de placeHints sont des lieux cités dans un texte libre, par ordre de préférence :
     * si le premier désigne plusieurs communes, aucun des suivants ne le remplace.
     */
    public bool $stopAtAmbiguousPlace = false;

    /** Gare citée par la source (« en gare de Laroche-Migennes »). */
    public ?string $stationHint = null;

    /** Point hors de tout contour de commune : distance (m) à la commune la plus proche. */
    public ?int $offshoreM = null;

    /** Pays déclaré par Wikidata (P17) : France ou État français historique. */
    public bool $declaredInFrance = false;

    /** Pays déclaré par Wikidata (P17), mais aucun n'est français (Allemagne, Suisse…). */
    public bool $declaredElsewhere = false;

    /** Tramway ou métro : réseau urbain, hors des lignes de VFF. */
    public bool $urbanTransit = false;

    /** @var array<string, int> morts par fournisseur */
    public array $deaths = [];

    /** @var array<string, int> blessés par fournisseur */
    public array $injuries = [];

    /** @var array<string, string> lien qui appuie le bilan, par fournisseur */
    public array $tollUrls = [];

    /**
     * Trains et matériel cités ; `source` : fournisseur qui les cite (un texte libre de Wikipédia
     * reste en français et n'entre que dans le résumé français).
     *
     * @var list<array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string, source: ?string}>
     */
    public array $trains = [];

    /** @var list<string> exploitants cités (P137, infobox « compagnie ») */
    public array $operators = [];

    /** @var list<string> éléments Wikidata de ligne cités (P81) */
    public array $lineItems = [];

    /** @var list<string> titres d'articles « Ligne de … » cités par Wikipédia */
    public array $lineArticles = [];

    /** @var list<string> codes de ligne SNCF cités (passage à niveau) */
    public array $lineCodes = [];

    /** @var array<string, array{title: string, url: string, revid: int|null}> */
    public array $wikipedia = [];

    /** @var list<AccidentSourceRef> */
    public array $sources = [];

    /** @var list<array{code: string, detail: string}> */
    public array $notes = [];

    /** @param string $key identité stable de la source principale (wikidata:Q…, bea-tt:…) */
    public function __construct(public readonly string $key) {}

    public function year(): ?int
    {
        return $this->date !== null ? (int) substr($this->date, 0, 4) : null;
    }

    public function note(string $code, string $detail = ''): void
    {
        foreach ($this->notes as $n) {
            if ($n['code'] === $code && $n['detail'] === $detail) {
                return;
            }
        }
        $this->notes[] = ['code' => $code, 'detail' => $detail];
    }

    public function hasNote(string $code): bool
    {
        return array_any($this->notes, fn (array $n) => $n['code'] === $code);
    }

    /** @param array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string} $train */
    public function addTrain(array $train, ?string $source): void
    {
        $train['source'] = $source;
        foreach ($this->trains as $t) {
            if (($t['label'] ?? null) === $train['label'] && ($t['number'] ?? null) === $train['number']
                && ($t['category'] ?? null) === $train['category']) {
                return;
            }
        }
        $this->trains[] = $train;
    }

    public function addSource(AccidentSourceRef $source): void
    {
        foreach ($this->sources as $s) {
            if ($s->provider === $source->provider && $s->externalId === $source->externalId) {
                return;
            }
        }
        $this->sources[] = $source;
    }
}
