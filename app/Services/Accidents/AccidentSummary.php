<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Support\FrenchDepartments;
use Illuminate\Support\Carbon;

/**
 * Résumé d'un accident rédigé par VFF à partir des FAITS retenus (date et précision, nature,
 * lieu, trains, bilan), en français, anglais et allemand, avec les gabarits de
 * lang/<locale>/accidents.php (accidents.summary.*). Aucun texte de source n'est repris ; le
 * ton reste factuel et sobre, sans nom de personne.
 */
final class AccidentSummary
{
    public const LOCALES = ['fr', 'en', 'de'];

    /**
     * @param  array{
     *   date: string, precision: AccidentDatePrecision, kind: AccidentKind,
     *   commune: ?string, department: ?string,
     *   trains: list<array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string, source?: ?string}>,
     *   deaths: ?int, injuries: ?int, toll_disputed: bool
     * }  $facts
     * @return array<string, string> résumé par langue
     */
    public function write(array $facts): array
    {
        $out = [];
        foreach (self::LOCALES as $locale) {
            $sentences = [
                __('accidents.summary.kinds.'.$facts['kind']->value, [
                    'when' => $this->when($facts['date'], $facts['precision'], $locale),
                    'place' => $this->place($facts['commune'], $facts['department'], $locale),
                ], $locale),
            ];
            // Texte libre de Wikipédia (matériel décrit en français) : résumé français seulement.
            $trains = array_values(array_filter($facts['trains'], fn ($t) => $locale === 'fr'
                || ($t['source'] ?? null) !== 'wikipedia' || ($t['label'] ?? null) !== null || ($t['category'] ?? null) !== null));
            if ($trains !== []) {
                $labels = array_map(fn ($t) => self::trainLabel($t, $locale), $trains);
                $sentences[] = __('accidents.summary.trains_'.(count($labels) > 1 ? 'other' : 'one'), ['list' => implode(', ', $labels)], $locale);
            }
            $sentences[] = $this->toll($facts['deaths'], $facts['injuries'], $locale);
            if ($facts['toll_disputed']) {
                $sentences[] = __('accidents.summary.toll_disputed', [], $locale);
            }
            $out[$locale] = implode(' ', $sentences);
        }

        return $out;
    }

    /**
     * Libellé d'un train : nom de service et numéro (« TER 849 902 »), sinon catégorie traduite
     * (« train de fret »), puis le matériel entre parenthèses quand il est connu.
     *
     * @param  array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string}  $train
     */
    public static function trainLabel(array $train, string $locale): string
    {
        $name = $train['label'] ?? null;
        if ($name === null && ($train['category'] ?? null) !== null) {
            $name = __('accidents.train_categories.'.$train['category'], [], $locale);
        }
        if ($name !== null && ($train['number'] ?? null) !== null) {
            $name .= ' '.$train['number'];
        }
        $material = $train['material'] ?? null;
        if ($name === null) {
            return (string) ($material ?? __('accidents.train_categories.unknown', [], $locale));
        }

        return $material !== null ? "{$name} ({$material})" : $name;
    }

    /** « Le 12 juillet 2013 », « On 12 July 2013 », « Am 12. Juli 2013 » ; « En juillet 2013 » ; « En 1891 ». */
    public function when(string $date, AccidentDatePrecision $precision, string $locale): string
    {
        $d = Carbon::parse($date)->locale($locale);

        return match ($precision) {
            AccidentDatePrecision::Day => __('accidents.summary.when_day', ['date' => self::dayDate($date, $locale)], $locale),
            AccidentDatePrecision::Month => __('accidents.summary.when_month', ['date' => $d->translatedFormat('F Y')], $locale),
            AccidentDatePrecision::Year => __('accidents.summary.when_year', ['year' => $d->format('Y')], $locale),
        };
    }

    /** Date du jour dans la langue : « 1er juillet 2013 », « 1 July 2013 », « 1. Juli 2013 ». */
    public static function dayDate(string $date, string $locale): string
    {
        $d = Carbon::parse($date)->locale($locale);

        return match ($locale) {
            'fr' => ($d->day === 1 ? '1er' : (string) $d->day).' '.$d->translatedFormat('F Y'),
            'de' => $d->translatedFormat('j. F Y'),
            default => $d->translatedFormat('j F Y'),
        };
    }

    private function place(?string $commune, ?string $department, string $locale): string
    {
        $deptName = $department !== null ? (FrenchDepartments::find($department)[0] ?? null) : null;
        if ($commune !== null) {
            return $deptName !== null && $deptName !== $commune
                ? __('accidents.summary.at_commune', ['commune' => $commune, 'department' => $deptName], $locale)
                : __('accidents.summary.at_commune_only', ['commune' => $commune], $locale);
        }
        if ($deptName !== null) {
            return __('accidents.summary.at_department', ['department' => $deptName], $locale);
        }

        return __('accidents.summary.at_unknown', [], $locale);
    }

    private function toll(?int $deaths, ?int $injuries, string $locale): string
    {
        if ($deaths === null && $injuries === null) {
            return __('accidents.summary.toll_unknown', [], $locale);
        }
        $parts = [
            'deaths' => $deaths !== null ? $this->count('deaths', $deaths, $locale) : null,
            'injuries' => $injuries !== null ? $this->count('injuries', $injuries, $locale) : null,
        ];

        return match (true) {
            $parts['deaths'] !== null && $parts['injuries'] !== null => __('accidents.summary.toll_both', $parts, $locale),
            $parts['deaths'] !== null => __('accidents.summary.toll_deaths_only', ['deaths' => $parts['deaths']], $locale),
            default => __('accidents.summary.toll_injuries_only', ['injuries' => $parts['injuries']], $locale),
        };
    }

    /** Nombre accordé (_none pour zéro, _one, _other) et séparateur de milliers de la langue. */
    private function count(string $key, int $n, string $locale): string
    {
        $form = match (true) {
            $n === 0 => 'none',
            $n === 1 => 'one',
            default => 'other',
        };

        $thousands = match ($locale) {
            'fr' => "\u{202F}",
            'de' => '.',
            default => ',',
        };

        return __("accidents.summary.{$key}_{$form}", ['count' => number_format($n, 0, ',', $thousands)], $locale);
    }
}
