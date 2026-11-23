<?php

namespace App\Console\Commands;

use App\Enums\TileFields;
use App\Models\Era;
use App\Services\GeoExporter;
use App\Services\MapDataCache;
use App\Support\MapTiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Réchauffe le cache serveur des tuiles de la carte des petits zooms, pour toutes les ères.
 *
 * Les tuiles de vue d'ensemble (zoom 0 à 6) demandent 1 à 3 s à froid (des milliers de tracés à découper) et
 * toute demande de visiteur après un recalcul des données ou l'expiration de l'entrée paie ce prix. La commande
 * les calcule une fois et les range dans le cache (Valkey) sous la clé de la route
 * `GET /api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt` (MapDataCache::warmTile) : la première visite les trouve.
 *
 * À lancer à la main ou en fin d'application du moteur (aucun cron ni timer sur le PC) : la clé contient la
 * version des données de la carte, un recalcul rend les entrées précédentes inutiles. Seules les tuiles qui
 * touchent l'emprise des tracés sont calculées. Le débit est borné : une pause (--pause-ms) entre deux tuiles
 * et un délai maximal par requête (--statement-timeout, secondes). Ne modifie aucune table.
 *
 *   php artisan vff:warm-tiles --dry-run
 *   php artisan vff:warm-tiles --era=2030 --max-zoom=4
 */
class WarmTiles extends Command
{
    protected $signature = 'vff:warm-tiles
        {--era=* : année d\'une ère (répétable ; défaut : toutes les ères)}
        {--max-zoom=6 : zoom le plus fin réchauffé (0 à '.MapDataCache::CACHED_MAX_ZOOM.')}
        {--fields=map : jeu d\'attributs des tuiles : map (carte du site), all ou both}
        {--pause-ms=50 : pause entre deux tuiles calculées (ms), pour borner le débit}
        {--statement-timeout=30 : délai maximal d\'une requête (s)}
        {--refresh : recalcule aussi les tuiles déjà en cache}
        {--dry-run : compte les tuiles à calculer et celles déjà en cache, sans rien calculer ni écrire}';

    protected $description = 'Remplit le cache des tuiles de la carte aux petits zooms pour toutes les ères (à lancer après l\'application du moteur).';

    public function handle(GeoExporter $exporter, MapDataCache $cache): int
    {
        $maxZoom = (int) $this->option('max-zoom');
        if ($maxZoom < 0 || $maxZoom > MapDataCache::CACHED_MAX_ZOOM) {
            $this->error('--max-zoom doit être compris entre 0 et '.MapDataCache::CACHED_MAX_ZOOM.' (au-delà, les tuiles ne sont pas gardées).');

            return self::INVALID;
        }
        $fieldSets = match ((string) $this->option('fields')) {
            'map' => [TileFields::Map],
            'all' => [TileFields::All],
            'both' => [TileFields::Map, TileFields::All],
            default => null,
        };
        if ($fieldSets === null) {
            $this->error('--fields doit valoir map, all ou both.');

            return self::INVALID;
        }

        $years = array_map('intval', (array) $this->option('era'));
        $eras = Era::query()->when($years !== [], fn ($q) => $q->whereIn('year', $years))->orderBy('year')->pluck('year')->all();
        if ($eras === []) {
            $this->warn('Aucune ère à réchauffer.');

            return self::SUCCESS;
        }

        $extent = $this->dataExtent();
        if ($extent === null) {
            $this->warn('Aucun tracé : rien à réchauffer.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $refresh = (bool) $this->option('refresh');
        $pauseUs = max(0, (int) $this->option('pause-ms')) * 1000;
        $timeout = max(1, (int) $this->option('statement-timeout')) * 1000;
        $tiles = $this->tilesCovering($extent, $maxZoom);
        $this->info(sprintf('%d ères, %d tuiles par ère et par jeu d\'attributs (zoom 0 à %d), version des données %s.', count($eras), count($tiles), $maxZoom, $cache->version()));

        $computed = $cached = $failed = $bytes = 0;
        $started = microtime(true);
        foreach ($eras as $year) {
            foreach ($fieldSets as $fields) {
                foreach ($tiles as [$z, $x, $y]) {
                    $key = "lines:{$year}:{$z}:{$x}:{$y}{$fields->cacheSuffix()}";
                    if ($dry) {
                        $cache->hasTile($key) && ! $refresh ? $cached++ : $computed++;

                        continue;
                    }
                    try {
                        $size = $cache->warmTile($key, function () use ($exporter, $year, $z, $x, $y, $fields, $timeout): string {
                            return DB::transaction(function () use ($exporter, $year, $z, $x, $y, $fields, $timeout): string {
                                DB::statement("SET LOCAL statement_timeout = {$timeout}");

                                return $exporter->linesAsMvt($year, $z, $x, $y, $fields);
                            });
                        }, $refresh);
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn("Tuile {$year}/{$z}/{$x}/{$y} : {$e->getMessage()}");

                        continue;
                    }
                    if ($size === null) {
                        $cached++;

                        continue;
                    }
                    $computed++;
                    $bytes += $size;
                    if ($pauseUs > 0) {
                        usleep($pauseUs);
                    }
                }
            }
            $this->line(sprintf('  ère %d : %d calculées, %d déjà en cache (%.0f s)', $year, $computed, $cached, microtime(true) - $started));
        }

        $this->info(sprintf(
            '%s : %d tuiles %s, %d déjà en cache, %d en échec, %.1f Mo dans le cache, %.0f s.',
            $dry ? 'Simulation' : 'Terminé',
            $computed,
            $dry ? 'à calculer' : 'calculées',
            $cached,
            $failed,
            $bytes / 1_048_576,
            microtime(true) - $started,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Emprise (WGS 84) de l'ensemble des tracés, en une requête bornée ; null sans tracé.
     *
     * @return array{west: float, south: float, east: float, north: float}|null
     */
    private function dataExtent(): ?array
    {
        $row = DB::transaction(function () {
            DB::statement('SET LOCAL statement_timeout = 30000');

            return DB::selectOne('SELECT ST_XMin(e) AS w, ST_YMin(e) AS s, ST_XMax(e) AS e, ST_YMax(e) AS n FROM (SELECT ST_Extent(geom) AS e FROM line_segments) t');
        });
        if ($row === null || $row->w === null) {
            return null;
        }

        return ['west' => (float) $row->w, 'south' => (float) $row->s, 'east' => (float) $row->e, 'north' => (float) $row->n];
    }

    /**
     * Tuiles (z, x, y) des zooms 0 à $maxZoom qui touchent l'emprise.
     *
     * @param  array{west: float, south: float, east: float, north: float}  $extent
     * @return list<array{int, int, int}>
     */
    private function tilesCovering(array $extent, int $maxZoom): array
    {
        $tiles = [];
        for ($z = 0; $z <= $maxZoom; $z++) {
            [$x0, $y1] = self::tileOf($extent['west'], $extent['south'], $z);
            [$x1, $y0] = self::tileOf($extent['east'], $extent['north'], $z);
            for ($x = $x0; $x <= $x1; $x++) {
                for ($y = $y0; $y <= $y1; $y++) {
                    if (MapTiles::onGrid($z, $x, $y)) {
                        $tiles[] = [$z, $x, $y];
                    }
                }
            }
        }

        return $tiles;
    }

    /** @return array{int, int} colonne et ligne de la tuile du zoom $z qui contient le point. */
    private static function tileOf(float $lon, float $lat, int $z): array
    {
        $n = 2 ** $z;
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $rad = deg2rad($lat);
        $x = (int) floor(($lon + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan($rad) + 1 / cos($rad)) / M_PI) / 2 * $n);

        return [max(0, min($n - 1, $x)), max(0, min($n - 1, $y))];
    }
}
