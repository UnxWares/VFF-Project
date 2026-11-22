<?php

namespace App\Services;

use App\Console\Commands\ImportSncfTrackRegime;
use Illuminate\Support\Facades\DB;

/**
 * Règle de priorité unique pour `lines.nb_voies_default`, utilisée par toutes les commandes qui l'écrivent
 * (vff:import-sncf-track-regime, vff:import-lignes-oubliees, vff:enrich-line-wikipedia, vff:import-wikidata-lines).
 *
 * Le champ dit l'état actuel de la ligne. Une source datée du réseau actuel (le jeu SNCF Réseau « régime
 * d'exploitation », trace `line_sources` « sncf-regime » dont `fields_provided` contient `nb_voies_default`)
 * prime pour cet état : aucune autre source (inventaire historique, article Wikipédia, Wikidata) ne remplace une
 * valeur qu'elle a posée. Dans l'autre sens, l'import SNCF ne remplace que sa propre valeur ou un champ vide (voir
 * ImportSncfTrackRegime). La trace est tenue à jour par l'import SNCF : elle n'y figure que tant que la valeur vient
 * de lui.
 */
final class TrackCountPriority
{
    public const FIELD = 'nb_voies_default';

    /**
     * Lignes dont le nombre de voies est posé par la source datée du réseau actuel.
     *
     * @param  list<int>  $lineIds
     * @return array<int, true>
     */
    public static function lockedLineIds(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }
        $locked = [];
        $rows = DB::table('line_sources')->where('source_type', ImportSncfTrackRegime::SOURCE_TYPE)
            ->whereIn('line_id', $lineIds)->get(['line_id', 'fields_provided']);
        foreach ($rows as $row) {
            if (in_array(self::FIELD, (array) json_decode((string) $row->fields_provided, true), true)) {
                $locked[(int) $row->line_id] = true;
            }
        }

        return $locked;
    }

    public static function isLocked(int $lineId): bool
    {
        return isset(self::lockedLineIds([$lineId])[$lineId]);
    }

    /**
     * Retire le nombre de voies d'une mise à jour de `lines` quand la valeur actuelle vient de la source datée.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public static function filterUpdate(int $lineId, array $update): array
    {
        if (array_key_exists(self::FIELD, $update) && self::isLocked($lineId)) {
            unset($update[self::FIELD]);
        }

        return $update;
    }
}
