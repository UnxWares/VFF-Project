<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fraîcheur des chiffres rangés sans expiration (départements, compagnies, couverture).
 *
 * Ces chiffres se calculent en plusieurs minutes : une page ne peut pas les attendre. Elles lisent donc toujours
 * la dernière valeur rangée (repli), et c'est ici que l'on sait si cette valeur est périmée : chaque calcul note
 * la version des données lue AVANT de commencer (record()), et isStale() la compare à la version actuelle. Un
 * changement de données qui survient pendant un calcul laisse donc les chiffres périmés, jamais faussement à jour.
 *
 * La version est celle des données de la carte (MapDataCache::version()) à laquelle s'ajoutent les événements
 * datés, les accidents et les ères, que les chiffres lisent aussi. Le recalcul des chiffres périmés est fait
 * par `vff:refresh-stale-figures` (tâche planifiée en production, et fin de `vff:refresh-current-era`).
 */
final class FiguresVersion
{
    private const SEEN_KEY = 'figures:data-version-seen';

    private const RECORDED_KEY = 'figures:computed-with:';

    public function __construct(private readonly MapDataCache $mapData) {}

    /** Version des données lue maintenant (mémoire de MapDataCache vidée) : à noter avant un calcul. */
    public function read(): string
    {
        Cache::forget('map-data:version');
        $row = DB::selectOne(<<<'SQL'
            SELECT concat_ws('|',
                (SELECT max(updated_at) FROM line_events),
                (SELECT count(*) FROM line_events),
                (SELECT max(updated_at) FROM accidents),
                (SELECT count(*) FROM accidents),
                (SELECT count(*) FROM eras),
                (SELECT id FROM eras WHERE is_current LIMIT 1)) AS v
        SQL);

        return substr(md5($this->mapData->version().'|'.($row->v ?? '')), 0, 16);
    }

    /** Note qu'un calcul fait avec les données de la version `$version` vient d'être rangé. */
    public function record(string $name, string $version): void
    {
        Cache::forever(self::RECORDED_KEY.$name, $version);
    }

    /** Version avec laquelle les chiffres `$name` ont été calculés, null s'ils ne l'ont pas été depuis ce suivi. */
    public function recorded(string $name): ?string
    {
        $value = Cache::get(self::RECORDED_KEY.$name);

        return is_string($value) ? $value : null;
    }

    /** Les chiffres `$name` ont-ils été calculés avec d'autres données que les actuelles ? */
    public function isStale(string $name): bool
    {
        return $this->recorded($name) !== $this->read();
    }

    /**
     * Les données sont-elles stables depuis `$minutes` minutes ? Une application en base change la version à chaque
     * écriture : mieux vaut attendre qu'elle se calme que de recalculer en boucle.
     */
    public function settled(int $minutes): bool
    {
        $version = $this->read();
        $seen = Cache::get(self::SEEN_KEY);
        if (! is_array($seen) || ($seen['version'] ?? null) !== $version) {
            Cache::forever(self::SEEN_KEY, ['version' => $version, 'since' => now()->getTimestamp()]);

            return $minutes <= 0;
        }

        return now()->getTimestamp() - (int) $seen['since'] >= $minutes * 60;
    }
}
