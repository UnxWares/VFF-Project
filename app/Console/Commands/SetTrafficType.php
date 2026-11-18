<?php

namespace App\Console\Commands;

use App\Enums\TrafficType;
use App\Models\Line;
use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetTrafficType extends Command
{
    protected $signature = 'vff:set-traffic
        {code : Code RFN de la ligne}
        {traffic : passenger | freight | mixed | unknown}
        {--lat= : Latitude du centre (zone)}
        {--lng= : Longitude du centre}
        {--radius= : Rayon en mètres}
        {--all : Applique à toute la ligne}';

    protected $description = 'Reclasse le type de traffic (voyageurs/fret/mixte) d\'une ligne ou d\'une zone';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $traffic = (string) $this->argument('traffic');

        if (! in_array($traffic, array_map(fn ($c) => $c->value, TrafficType::cases()), true)) {
            $this->error("Type invalide : {$traffic}");

            return self::FAILURE;
        }

        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', $code)])
            ->first();

        if (! $line) {
            $this->error("Ligne {$code} introuvable");

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $lat = $this->option('lat');
        $lng = $this->option('lng');
        $radius = $this->option('radius');

        if (! $all && (! $lat || ! $lng || ! $radius)) {
            $this->error('Fournir soit --all soit --lat --lng --radius');

            return self::FAILURE;
        }

        // Règles VFF : voyageurs ⇒ mixte, sauf sur LGV (voyageurs seuls) ; un
        // tronçon fermé, déposé ou rasé ne porte pas de trafic (reste « unknown »).
        $isLgv = (bool) DB::selectOne('SELECT '.Lgv::sql('l', null).' AS lgv FROM lines l WHERE l.id = ?', [$line->id])->lgv;
        if ($traffic === TrafficType::Passenger->value && ! $isLgv) {
            $traffic = TrafficType::Mixed->value;
            $this->warn('  Ligne classique : voyageurs enregistré en mixte (voyageurs seuls réservé aux LGV).');
        }

        $zone = $all ? '' : ' AND ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)';
        $updated = DB::update(
            "UPDATE line_segments SET traffic_type = ?
             WHERE line_id = ? AND status IN ('in_service', 'single_track'){$zone}",
            [$traffic, $line->id, ...($all ? [] : [(float) $lng, (float) $lat, (float) $radius])],
        );

        $this->info("✓ {$line->name} ({$code}) → {$updated} segments → traffic={$traffic}");

        return self::SUCCESS;
    }
}
