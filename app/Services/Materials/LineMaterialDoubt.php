<?php

namespace App\Services\Materials;

use App\Models\LineMaterial;

/**
 * Lien ligne × matériel dont toutes les preuves portent un doute sur la nature de la circulation
 * (`evidence[].doubt`, voir WikipediaMaterialSections::doubt()) : commande, essais, présentation ou
 * circulation exceptionnelle, acheminement à vide vers un atelier… Un tel matériel est cité par les
 * sources, mais rien ne dit qu'il a assuré le service : la fiche de ligne le range à part.
 *
 * Un lien n'est douteux que si aucune source sûre ne l'appuie : fiabilité `low` seulement, et chaque
 * preuve nomme un doute. Un lien `low` parce que l'article décrit plusieurs lignes (`shared_article`)
 * n'est pas douteux, et un lien qui a une preuve sans doute (Wikidata, section « Matériel ») ne l'est pas.
 */
final class LineMaterialDoubt
{
    /**
     * Raisons connues, de la plus parlante à la moins : quand plusieurs preuves disent des choses
     * différentes, la première de cette liste est donnée.
     */
    public const REASONS = ['trial', 'one_off', 'order', 'positioning', 'preserved', 'possible', 'diverted', 'restricted', 'component', 'section'];

    /** Raison de repli pour une famille de doute que cette liste ne connaît pas encore. */
    public const OTHER = 'other';

    /**
     * Raison du doute pour la série sur la ligne, ou null si elle est tenue pour avoir circulé.
     *
     * @param  iterable<LineMaterial>  $links  liens de la ligne vers la série
     */
    public static function reason(iterable $links): ?string
    {
        $found = [];
        $any = false;
        foreach ($links as $link) {
            $any = true;
            $evidence = $link->evidence ?? [];
            if ($link->confidence !== 'low' || $evidence === []) {
                return null;
            }
            foreach ($evidence as $item) {
                if (! is_string($item['doubt'] ?? null)) {
                    return null;
                }
                $found[$item['doubt']] = true;
            }
        }
        if (! $any) {
            return null;
        }
        foreach (self::REASONS as $reason) {
            if (isset($found[$reason])) {
                return $reason;
            }
        }

        return self::OTHER;
    }
}
