<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;
use App\Services\PlaceNames;

/**
 * Nature d'un accident d'après la classe Wikidata (P31) ou d'après un texte français (titre du
 * BEA-TT, type de l'infobox Wikipédia, libellé).
 *
 * Texte : la PREMIÈRE circonstance nommée l'emporte (« Dérive suivie du déraillement » est une
 * dérive, « Déraillement et début d'incendie » un déraillement), ce qui suit l'usage des titres
 * d'enquête. « Sans collision », « quasi- » et « nez à nez » désignent un incident sans accident.
 */
final class AccidentKindClassifier
{
    /** Classes Wikidata (sous-classes d'« accident ferroviaire », Q1078765) → nature. */
    public const WIKIDATA_CLASSES = [
        'Q1331380' => AccidentKind::Derailment,   // déraillement
        'Q18460175' => AccidentKind::Derailment,  // renversement (overturning)
        'Q11396408' => AccidentKind::Collision,   // collision ferroviaire
        'Q19403959' => AccidentKind::Collision,   // collision par un train
        'Q7696812' => AccidentKind::Collision,    // télescopage
        'Q19710423' => AccidentKind::LevelCrossing, // collision de passage à niveau
        'Q11396401' => AccidentKind::Fire,        // incendie de train
        'Q28934204' => AccidentKind::Attack,      // attentat ferroviaire
        'Q2223653' => AccidentKind::Attack,       // attentat terroriste
        'Q121484355' => AccidentKind::PersonStruck, // piéton heurté par un train
        'Q17005145' => AccidentKind::Runaway,     // train à la dérive
        'Q179057' => AccidentKind::Explosion,     // explosion
    ];

    /** Classes Wikidata des réseaux urbains (hors des lignes de VFF). */
    public const URBAN_CLASSES = ['Q7833114']; // accident de tramway

    /**
     * Motifs sur le texte normalisé (minuscules, sans accents ni ponctuation), par nature. La
     * position de la première correspondance départage.
     */
    private const PATTERNS = [
        AccidentKind::Incident->value => '/\b(?:quasi|nez a nez|depassement de la vitesse|immobilisation|mise en danger|enrayage|talonnage|defaillance de freinage|detresse)\b/',
        AccidentKind::PersonStruck->value => '/\b(?:heurt (?:d un |de |des )?(?:\d+ |deux |trois |quatre |cinq )?(?:pietons?|personnes?|agents?|voyageu\w*|enfants?)|happement|accident de personnes?|accident mortel d une? voyageu\w*|entrainement d une personne)/',
        AccidentKind::LevelCrossing->value => '/\b(?:passages? a niveau|pn)\b/',
        AccidentKind::Derailment->value => '/\b(?:deraill\w*|deguidage|renversement|deviation d un train)/',
        AccidentKind::Runaway->value => '/\bderive\b/',
        AccidentKind::Collision->value => '/\b(?:collisions?|tamponnement|telescopage|rattrapage|contact entre deux|choc d un train|heurt d un (?:engin|train)|heurtoir)\b/',
        AccidentKind::Fire->value => '/\bincendies?\b/',
        AccidentKind::Explosion->value => '/\bexplosions?\b/',
        AccidentKind::StructureFailure->value => '/\b(?:effondrement|ecroulement|eboulement|ruptures?)\b/',
        AccidentKind::Attack->value => '/\b(?:attentats?|sabotage)\b/',
        AccidentKind::Other->value => '/\b(?:electrocution|haute tension|chute)\b/',
    ];

    /**
     * Verbes du récit d'une liste (« percute », « tamponne », « se renversent », « s'effondre ») : la
     * même règle de la première circonstance, mais sur des formes conjuguées que les titres d'enquête
     * et les types d'infobox n'emploient pas. N'est appliqué qu'à défaut de nature lue par
     * fromFrenchText() ; un train qui heurte une personne est traité avant par le lecteur de liste.
     */
    private const NARRATIVE = [
        AccidentKind::Derailment->value => '/\b(?:se renvers\w*|se couch\w*|culbut\w*|rails s ecart\w*|(?:quitt|sort)\w* (?:les rails|des rails|la voie|de la voie))/',
        AccidentKind::Collision->value => '/\b(?:percut\w*|tamponn\w*|telescop\w*|en echarpe|heurt\w*|embouti\w*|rattrap\w*)/',
        AccidentKind::Runaway->value => '/\b(?:s emball\w*|a la derive)\b/',
        AccidentKind::Fire->value => '/\b(?:prend(?:nt)? feu|prit feu|en feu)\b/',
        AccidentKind::Explosion->value => '/\bexplos\w*/',
        AccidentKind::StructureFailure->value => '/\b(?:s effondr\w*|s ecroul\w*|ecroul\w*|emporte par (?:la crue|les eaux|une crue))/',
        AccidentKind::PersonStruck->value => '/\bhapp\w*/',
    ];

    /** @param list<string> $classes */
    public function fromWikidataClasses(array $classes): ?AccidentKind
    {
        foreach ($classes as $qid) {
            if (isset(self::WIKIDATA_CLASSES[$qid])) {
                return self::WIKIDATA_CLASSES[$qid];
            }
        }

        return null;
    }

    public function fromFrenchText(?string $text): ?AccidentKind
    {
        return $this->firstMatch($text, self::PATTERNS);
    }

    /** Nature d'après le récit d'une puce de liste (verbes conjugués) ; null si le texte ne la dit pas. */
    public function fromNarrative(?string $text): ?AccidentKind
    {
        return $this->firstMatch($text, self::NARRATIVE);
    }

    /**
     * Nature de la première circonstance nommée.
     *
     * @param  array<string, string>  $patterns  nature → motif sur le texte normalisé
     */
    private function firstMatch(?string $text, array $patterns): ?AccidentKind
    {
        $norm = PlaceNames::normalize($text);
        if ($norm === '') {
            return null;
        }
        // « sans collision » ne décrit pas une collision.
        $norm = str_replace('sans collision', 'sans', $norm);
        $best = null;
        $bestPos = PHP_INT_MAX;
        foreach ($patterns as $kind => $pattern) {
            if (preg_match($pattern, $norm, $m, PREG_OFFSET_CAPTURE) && $m[0][1] < $bestPos) {
                $bestPos = $m[0][1];
                $best = AccidentKind::from($kind);
            }
        }

        return $best;
    }
}
