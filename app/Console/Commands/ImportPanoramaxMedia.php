<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Media\Panoramax\PanoramaxClient;
use App\Services\Media\Panoramax\PanoramaxSceneBuilder;
use App\Services\Media\Panoramax\PanoramaxSelection;
use App\Services\Media\Panoramax\PanoramaxStationSource;
use App\Services\Media\Panoramax\PanoramaxVision;
use App\Services\Media\Sources\MediaWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Photos de rue Panoramax des gares (instances IGN et OpenStreetMap France) : au plus 3 par gare, qui voient un
 * bâtiment ou un quai de la gare (règle des abords de PanoramaxSelection : bâtiments BD TOPO, emprise de la gare,
 * quais SNCF, obstacles sur la ligne de visée). Une requête par gare au catalogue fédéré (2 s d'écart) et, pour les
 * gares qui ont des photos, une au WFS de l'IGN (1 s d'écart), réponses gardées en cache
 * (`storage/app/data/panoramax/`) : toutes les gares prennent environ 6 h la première fois.
 *
 *   vff:import-panoramax-media --dry-run --sample=300 --seed=2211   mesure sur 300 gares tirées au hasard
 *   vff:import-panoramax-media --dry-run --cache-only               relit le cache sans réseau
 *   vff:import-panoramax-media --dry-run --rule=legacy              règle ancienne (60 m, 15°), pour comparer
 *   vff:import-panoramax-media --instance=ign                       photos de l'instance IGN seulement
 *   vff:import-panoramax-media --dry-run --vision                   tri par vision : télécharge les vignettes à noter
 *   scripts/panoramax-vision/run.sh                                      note les vignettes (CLIP, processeur)
 *   vff:import-panoramax-media --vision --cache-only                importe les photos dont la gare est visible
 *   vff:import-panoramax-media                                      toutes les gares
 */
class ImportPanoramaxMedia extends Command
{
    protected $signature = 'vff:import-panoramax-media
        {--dry-run : N\'écrit rien en base : compte les gares et les photos}
        {--station= : Une seule gare (slug)}
        {--sample=0 : Nombre de gares tirées au hasard (mesure)}
        {--seed=1 : Graine du tirage}
        {--limit= : Nombre maximal de gares lues}
        {--cache-only : Aucune requête réseau : seules les gares déjà en cache sont lues}
        {--csv= : Fichier CSV où écrire les photos retenues}
        {--rule=scene : Règle de tri : scene (abords de la gare, par défaut) ou legacy (60 m, 15°)}
        {--instance=* : Instances gardées (ign, osm-fr ; les deux par défaut)}
        {--vision : Tri par vision (note « la gare est visible » de scripts/panoramax-vision, en cache), instance IGN seule}';

    protected $description = 'Photos Panoramax (IGN, OpenStreetMap France) des gares : au plus 3 par gare, qui voient un bâtiment ou un quai de la gare';

    public function handle(MediaWriter $writer): int
    {
        $ids = null;
        if ($this->option('station') !== null) {
            $ids = DB::table('stations')->where('slug', (string) $this->option('station'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                $this->error('Gare inconnue.');

                return self::FAILURE;
            }
        } elseif ((int) $this->option('sample') > 0) {
            // Tirage reproductible : ordre pseudo-aléatoire fixé par la graine, sans dépendre de random() de PostgreSQL.
            $all = DB::table('stations')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
            mt_srand((int) $this->option('seed'));
            shuffle($all);
            $ids = array_slice($all, 0, (int) $this->option('sample'));
        }

        $rule = (string) $this->option('rule');
        $instances = array_values(array_map('strval', (array) $this->option('instance')));
        if (! in_array($rule, ['scene', 'legacy'], true) || array_diff($instances, array_keys(PanoramaxSelection::INSTANCES)) !== []) {
            $this->error('Règle (scene, legacy) ou instance ('.implode(', ', array_keys(PanoramaxSelection::INSTANCES)).') inconnue.');

            return self::FAILURE;
        }

        $vision = $this->option('vision') ? app(PanoramaxVision::class) : null;
        if ($vision !== null) {
            // Seules les vignettes de l'IGN se lisent (robots.txt d'OpenStreetMap France) : les autres instances sont ignorées.
            $instances = PanoramaxVision::INSTANCES;
        }
        $source = new PanoramaxStationSource(
            app(PanoramaxClient::class), $ids, (bool) $this->option('cache-only'),
            $rule === 'scene' ? app(PanoramaxSceneBuilder::class) : null,
            $instances !== [] ? $instances : null,
            $vision,
        );
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $report = $writer->write($source->records($limit), (bool) $this->option('dry-run'));

        $this->info(($this->option('dry-run') ? '[à blanc] ' : '').sprintf(
            '%d gares lues (%d hors cache, %d en erreur, %d sans bâtiment de gare connu) ; %d photos (%d nouvelles) sur %d gares, dont %d sans aucun média avant',
            $source->stationsRead, $source->stationsSkipped, $source->stationsFailed, $source->stationsWithoutScene,
            $report->inserted + $report->updated, $report->inserted,
            $report->touchedCount(Station::class), $report->gainedCount(Station::class),
        ));
        if ($vision !== null) {
            $this->info(sprintf('Vision (%s) : %d vignettes téléchargées, %d photos candidates sans note (liste : storage/app/data/panoramax/vision/a-noter.txt, à noter avec scripts/panoramax-vision/run.sh)',
                trim($vision->model()), $vision->downloaded, count(array_unique($vision->unscored))));
        }
        $licenses = array_count_values(array_map(fn (array $a): string => (string) $a['license'], $source->accepted));
        $this->table(['Licence', 'Photos'], collect($licenses)->map(fn (int $n, string $l): array => [$l, $n])->values()->all());
        if ($source->rejected !== []) {
            arsort($source->rejected);
            $this->table(['Photo écartée', 'Nombre'], collect($source->rejected)->map(fn (int $n, string $r): array => [$r, $n])->values()->all());
        }
        if ($this->option('csv') !== null) {
            $h = fopen((string) $this->option('csv'), 'wb');
            if ($h !== false) {
                fputcsv($h, ['slug', 'station', 'id', 'distance_m', 'angle', 'license', 'date', 'thumb', 'page', 'instance', 'station_seen_deg', 'platform_seen_deg', 'station_building_m', 'vision_score'], escape: '\\');
                foreach ($source->accepted as $a) {
                    fputcsv($h, [$a['slug'], $a['station'], $a['id'], $a['distance_m'], $a['angle'], $a['license'], $a['date'], $a['thumb'], $a['page'],
                        $a['instance'], $a['station_seen_deg'], $a['platform_seen_deg'], $a['station_building_m'], $a['vision_score']], escape: '\\');
                }
                fclose($h);
            }
        }

        return $source->stationsFailed > 0 && $source->stationsRead === 0 ? self::FAILURE : self::SUCCESS;
    }
}
