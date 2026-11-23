<?php

namespace App\Services\Quiz;

use Illuminate\Support\Carbon;

/**
 * Libellé affiché d'une proposition. Le vivier est en cache pour toutes les langues : une proposition dont le texte
 * dépend de la langue garde un libellé neutre (`label`, qui fonde sa clé) et dit comment l'afficher :
 *
 *   - `date`  : date ISO (« 1962-05-12 »), écrite dans la langue de la page (« 12 mai 1962 ») ;
 *   - `trans` : clé de traduction (« quiz.facts.cab.etcs_l2 »).
 *
 * Sans l'un ni l'autre, le libellé est affiché tel quel (nom propre, année).
 */
final class QuizLabel
{
    /** @param  array<string, mixed>  $option */
    public static function of(array $option, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if (isset($option['date'])) {
            return self::date((string) $option['date'], $locale);
        }
        if (isset($option['trans'])) {
            return (string) trans((string) $option['trans'], [], $locale);
        }

        return (string) $option['label'];
    }

    /** Date ISO écrite en toutes lettres dans la langue (« 12 mai 1962 », « 12 May 1962 », « 12. Mai 1962 »). */
    public static function date(string $iso, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $date = Carbon::parse($iso);

        return match ($locale) {
            'en' => $date->locale('en')->isoFormat('D MMMM YYYY'),
            'de' => $date->locale('de')->isoFormat('D. MMMM YYYY'),
            default => ($date->day === 1 ? '1er' : (string) $date->day).' '.$date->locale('fr')->isoFormat('MMMM YYYY'),
        };
    }
}
