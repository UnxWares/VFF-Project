<?php

namespace App\Console\Commands;

use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlagHeritageArea extends Command
{
    protected $signature = 'vff:flag-area
        {code : Code RFN de la ligne (ex: 719000)}
        {lat : Latitude du centre de la zone}
        {lng : Longitude du centre de la zone}
        {radius : Rayon en mètres}
        {--unflag : Retire le flag heritage au lieu de le poser}';

    protected $description = 'Flag heritage UNIQUEMENT les segments d\'une ligne dans une zone circulaire (curation fine).';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $lat = (float) $this->argument('lat');
        $lng = (float) $this->argument('lng');
        $radius = (float) $this->argument('radius');
        $unflag = (bool) $this->option('unflag');
        $value = $unflag ? 'false' : 'true';

        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [
                preg_replace('/\s+/', '', $code),
            ])
            ->first();

        if (! $line) {
            $this->error("Ligne {$code} introuvable");

            return self::FAILURE;
        }

        $updated = DB::update(
            "UPDATE line_segments
             SET is_heritage = {$value}
             WHERE line_id = ?
               AND ST_DWithin(
                   geom::geography,
                   ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                   ?
               )",
            [$line->id, $lng, $lat, $radius],
        );

        $verb = $unflag ? 'Déflaggé' : 'Flaggé';
        $this->info("✓ {$verb} : {$line->name} ({$line->code}) → {$updated} segments dans rayon {$radius}m autour de ({$lat}, {$lng})");

        return self::SUCCESS;
    }
}
