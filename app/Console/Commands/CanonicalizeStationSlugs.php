<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remplace les slugs de gare techniques (osm-<id>-nom) par des slugs CANONIQUES
 * lisibles (basés sur le nom, dédupliqués par département puis compteur), et
 * enregistre une redirection 301 de l'ancien slug vers le nouveau.
 *
 * Idempotent : ne retouche pas une gare déjà canonicalisée.
 */
class CanonicalizeStationSlugs extends Command
{
    protected $signature = 'vff:canonicalize-station-slugs {--dry-run}';

    protected $description = 'Slugs de gare osm-* → canoniques (nom) + redirections 301.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Ensemble des slugs déjà utilisés (gares non-osm + futurs canoniques).
        $used = DB::table('stations')->where('slug', 'not like', 'osm-%')->pluck('slug')->flip()->map(fn (): bool => true);

        $stations = DB::table('stations')->where('slug', 'like', 'osm-%')
            ->orderBy('id')->get(['id', 'name', 'slug', 'department_code']);

        $renamed = 0;
        $rows = [];
        foreach ($stations as $s) {
            $base = Str::slug($s->name) ?: 'gare-'.$s->id;
            $canon = $base;
            // Déduplication : nom → nom-<dept> → nom-<dept>-2…
            if (isset($used[$canon])) {
                $canon = $s->department_code ? "{$base}-{$s->department_code}" : $base;
                $i = 2;
                while (isset($used[$canon])) {
                    $canon = ($s->department_code ? "{$base}-{$s->department_code}" : $base).'-'.$i;
                    $i++;
                }
            }
            $used[$canon] = true;

            if ($dry) {
                if ($renamed < 15) {
                    $this->line("  {$s->slug}  →  {$canon}");
                }
                $renamed++;

                continue;
            }

            DB::table('stations')->where('id', $s->id)->update(['slug' => $canon]);
            $rows[] = ['old_slug' => $s->slug, 'station_id' => $s->id, 'created_at' => now(), 'updated_at' => now()];
            $renamed++;

            if (count($rows) >= 500) {
                DB::table('station_redirects')->insertOrIgnore($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('station_redirects')->insertOrIgnore($rows);
        }

        $this->info(($dry ? '[DRY-RUN] ' : '')."✓ {$renamed} gares canonicalisées".($dry ? '' : ' (+ redirections 301)'));

        return self::SUCCESS;
    }
}
