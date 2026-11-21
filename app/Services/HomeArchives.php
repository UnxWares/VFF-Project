<?php

namespace App\Services;

use App\Models\Line;
use App\Models\Media;
use App\Services\Media\HomeImageSelector;
use App\Support\WebUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Images de la section « Le rail en images » de l'accueil.
 *
 * Rotation : une fenêtre fixe de `config('home.archives.rotation_minutes')` minutes (30 par défaut). La graine est
 * le début de la fenêtre, donc le tirage est le même pour tous les visiteurs pendant la fenêtre (cache jusqu'à
 * sa fin) et change d'une fenêtre à l'autre. Les images de la fenêtre précédente sont écartées tant que la
 * réserve de la période le permet : celles qui ont été montrées, lues dans le cache (gardé une fenêtre de plus
 * pour cela), ou à défaut (cache vide) le tirage de la fenêtre précédente recalculé sans sa propre exclusion.
 *
 * Choix : une image par période d'abord (avant 1915, 1915-1945, 1946-1970, 1971-1990, 1991-2010, après 2010),
 * puis complétées par un deuxième tour dans le même ordre de périodes, en évitant de répéter une année. Rangées
 * de la plus ancienne à la plus récente. Réserve : images `is_featured` et images retenues par
 * {@see HomeImageSelector} (`provider_meta.home_pick`).
 */
class HomeArchives
{
    private const PERIOD_ENDS = [1915, 1946, 1971, 1991, 2011];

    public function __construct(private readonly ?int $rotationSeconds = null, private readonly ?int $count = null) {}

    public function windowSeconds(): int
    {
        return max(60, ($this->rotationSeconds ?? (int) config('home.archives.rotation_minutes', 30) * 60));
    }

    public function windowStart(?int $now = null): int
    {
        $w = $this->windowSeconds();
        $now ??= now()->getTimestamp();

        return intdiv($now, $w) * $w;
    }

    /** @return list<array<string, mixed>> */
    public function current(): array
    {
        $now = now()->getTimestamp();
        $start = $this->windowStart($now);
        // Gardé jusqu'à la fin de la fenêtre suivante : celle-ci y lit les images vraiment montrées.
        $ttl = max(1, $start + 2 * $this->windowSeconds() - $now);

        return Cache::remember($this->cacheKey($start), $ttl, function () use ($start): array {
            $shown = Cache::get($this->cacheKey($start - $this->windowSeconds()));

            return $this->forWindow($start, is_array($shown) ? array_values(array_filter(array_column($shown, 'id'), 'is_int')) : null);
        });
    }

    private function cacheKey(int $start): string
    {
        return 'home_archives:8:'.$this->windowSeconds().':'.$start;
    }

    /**
     * @param  list<int>|null  $previous  images montrées dans la fenêtre précédente ; null si inconnues
     *                                    (tirage précédent recalculé sans sa propre exclusion)
     * @return list<array<string, mixed>>
     */
    public function forWindow(int $start, ?array $previous = null): array
    {
        $pool = $this->pool();
        $previous ??= array_column($this->pick($pool, $start - $this->windowSeconds(), []), 'id');

        return $this->pick($pool, $start, $previous);
    }

    /**
     * Tirage pur : `$pool` est la réserve (lignes de `pool()`), `$avoid` les identifiants à éviter si possible.
     *
     * @param  list<array<string, mixed>>  $pool
     * @param  list<int>  $avoid
     * @return list<array<string, mixed>>
     */
    public function pick(array $pool, int $seed, array $avoid): array
    {
        $count = $this->count ?? (int) config('home.archives.count', 7);
        $byPeriod = [];
        foreach ($pool as $m) {
            $byPeriod[$this->period((int) $m['year'])][] = $m;
        }
        ksort($byPeriod);
        foreach ($byPeriod as $p => $items) {
            // Ordre pseudo-aléatoire propre à la fenêtre ; les images de la fenêtre précédente passent en dernier.
            usort($items, fn ($a, $b) => [in_array($a['id'], $avoid, true), md5($a['id'].'|'.$seed)]
                <=> [in_array($b['id'], $avoid, true), md5($b['id'].'|'.$seed)]);
            $byPeriod[$p] = $items;
        }

        $chosen = [];
        $years = [];
        for ($round = 0; $round < $count && count($chosen) < $count; $round++) {
            $added = false;
            foreach ($byPeriod as $p => &$items) {
                if (count($chosen) >= $count) {
                    break;
                }
                // Première image dont l'année n'est pas déjà prise ; à défaut, la première restante.
                $idx = null;
                foreach ($items as $i => $m) {
                    if (! in_array($m['year'], $years, true)) {
                        $idx = $i;
                        break;
                    }
                }
                $idx ??= array_key_first($items);
                if ($idx === null) {
                    continue;
                }
                $chosen[] = $items[$idx];
                $years[] = $items[$idx]['year'];
                unset($items[$idx]);
                $added = true;
            }
            unset($items);
            if (! $added) {
                break;
            }
        }

        usort($chosen, fn ($a, $b) => [$a['year'], $a['id']] <=> [$b['year'], $b['id']]);

        return $chosen;
    }

    public function period(int $year): int
    {
        foreach (self::PERIOD_ENDS as $i => $end) {
            if ($year < $end) {
                return $i + 1;
            }
        }

        return count(self::PERIOD_ENDS) + 1;
    }

    /**
     * Toute la réserve éligible, mise en forme pour la page.
     *
     * @return list<array<string, mixed>>
     */
    public function pool(): array
    {
        $rows = DB::select(
            "SELECT m.id, m.depicted_year, m.caption, m.author, m.license, m.license_url, m.source_url,
                    m.thumb_url, m.display_url, m.width, m.height,
                    m.provider_meta->>'date_precision' AS date_precision,
                    l.slug AS line_slug, l.name AS line_name
             FROM media m
             JOIN lines l ON l.id = m.mediable_id
             WHERE m.mediable_type = ? AND (m.is_featured OR jsonb_exists(m.provider_meta, ?))
               AND m.provider = ? AND m.depicted_year IS NOT NULL
               AND m.thumb_url IS NOT NULL AND m.source_url IS NOT NULL
               AND m.caption IS NOT NULL AND m.license IS NOT NULL
               AND m.width >= 1200 AND m.width > m.height
             ORDER BY m.id",
            [Line::class, HomeImageSelector::FLAG, Media::PROVIDER_COMMONS],
        );

        return collect($rows)->map(fn ($m) => [
            'id' => (int) $m->id,
            'year' => (int) $m->depicted_year,
            'decade' => $m->date_precision === 'decade',
            'caption' => $m->caption,
            'author' => $m->author,
            'license' => $m->license,
            // Adresses venues de Commons : http(s) seulement (WebUrl).
            'license_url' => WebUrl::safe($m->license_url),
            'source_url' => WebUrl::safe($m->source_url),
            'thumb_url' => WebUrl::safe($m->thumb_url),
            'display_url' => WebUrl::safe($m->display_url),
            'width' => (int) $m->width,
            'height' => (int) $m->height,
            'line' => ['slug' => $m->line_slug, 'name' => $m->line_name],
        ])->all();
    }
}
