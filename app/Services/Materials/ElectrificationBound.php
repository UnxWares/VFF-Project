<?php

namespace App\Services\Materials;

use App\Enums\MaterialType;
use App\Models\Line;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

/**
 * Borne « pas avant l'électrification » d'une série électrique sur une ligne : aucune source ne date
 * l'arrivée de la BB 16000 sur Mantes - Cherbourg, mais une locomotive à caténaire n'y roule pas avant qu'une
 * partie de la ligne soit électrifiée. La borne n'est pas une date de service : c'est la plus ancienne date
 * possible, montrée avec son motif.
 *
 * Date de la ligne : la PREMIÈRE électrification connue dans VFF, de la ligne entière ou d'une seule section
 * (événements `electrification` de fiabilité `high` ou `medium`) : un matériel électrique peut circuler dès
 * la première section sous tension. Quand c'est une section (longueur résolue inférieure à MIN_SHARE de la
 * ligne), la borne la nomme (`section`). La date garde la précision de la source (jour, mois, année).
 *
 * Séries concernées : locomotives électriques et rames TGV, et les automotrices dont des systèmes
 * d'alimentation sont déclarés. Jamais un bimode, un autorail ni une série thermique, qui roulent sans
 * caténaire, ni une série retirée avant l'électrification.
 */
final class ElectrificationBound
{
    /** Part de la longueur officielle de la ligne à partir de laquelle une section vaut pour la ligne entière. */
    public const MIN_SHARE = 0.9;

    public const REASON = 'electrification';

    /**
     * @return array{date: string, precision: string, section: string|null}|null
     */
    public static function lineDate(Line $line): ?array
    {
        $length = (int) ($line->length_official_m ?? 0);
        // À date égale, l'électrification de la ligne entière (portion non résolue ou longue) passe avant une section.
        $event = DB::table('line_events')
            ->where('line_id', $line->id)
            ->where('type', 'electrification')
            ->whereIn('confidence', ['high', 'medium'])
            ->orderBy('event_date')
            ->orderByRaw('section_length_m desc nulls first')
            ->first(['event_date', 'date_precision', 'section_from', 'section_to', 'section_length_m']);
        if ($event === null) {
            return null;
        }
        $isSection = $length > 0 && $event->section_length_m !== null
            && (int) $event->section_length_m < (int) ceil($length * self::MIN_SHARE)
            && $event->section_from !== null && $event->section_to !== null;

        return [
            'date' => substr((string) $event->event_date, 0, 10),
            'precision' => (string) $event->date_precision,
            'section' => $isSection ? $event->section_from.' - '.$event->section_to : null,
        ];
    }

    /** La série a-t-elle besoin d'une caténaire pour rouler ? */
    public static function isElectric(Material $material): bool
    {
        $hasSystems = ($material->electrification ?? []) !== [];

        return match ($material->type) {
            MaterialType::LocomotiveElectrique, MaterialType::RameTgv => true,
            MaterialType::Automotrice => $hasSystems,
            default => false,
        };
    }

    /** La borne vaut-elle pour la série : électrique, et pas retirée avant la date de la borne ? */
    public static function appliesTo(Material $material, string $boundDate): bool
    {
        return self::isElectric($material)
            && ($material->in_service_to === null || (int) $material->in_service_to >= (int) substr($boundDate, 0, 4));
    }

    /**
     * Périodes dont le début précède la borne, ramené à la borne ; un début absent reste absent.
     *
     * @param  list<array<string, mixed>>  $periods
     * @param  array{date: string, precision: string}  $bound
     * @return list<array<string, mixed>>
     */
    public static function clampPeriods(array $periods, array $bound): array
    {
        foreach ($periods as $i => $period) {
            $start = $period['start_date'] ?? null;
            if (is_string($start) && $start < $bound['date']) {
                $periods[$i]['start_date'] = $bound['date'];
                $periods[$i]['start_precision'] = $bound['precision'];
                $periods[$i]['start_bounded_by'] = self::REASON;
            }
        }

        return $periods;
    }
}
