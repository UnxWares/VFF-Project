<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetSegmentStatus extends Command
{
    protected $signature = 'vff:set-status
        {code : Code RFN de la ligne (ex: 524000)}
        {status : Nouveau statut : in_service | single_track | abandoned | dismantled | razed}
        {--lat= : Latitude du centre (curation par zone)}
        {--lng= : Longitude du centre}
        {--radius= : Rayon en mètres (avec lat/lng)}
        {--era= : Année de l\'ère cible (défaut : ère courante), pour ne pas toucher toutes les ères}
        {--all : Applique à TOUS les segments de la ligne (de l\'ère)}';

    protected $description = 'Reclasse les segments d\'une ligne (ex: SNCF dit Exploitée mais en vrai = abandonnée).';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $status = (string) $this->argument('status');

        if (! in_array($status, array_map(fn ($c) => $c->value, SegmentStatus::cases()), true)) {
            $this->error("Statut invalide : {$status}");

            return self::FAILURE;
        }

        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [
                preg_replace('/\s+/', '', $code),
            ])
            ->first();

        if (! $line) {
            $this->error("Ligne {$code} introuvable");

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $lat = $this->option('lat');
        $lng = $this->option('lng');
        $radius = $this->option('radius');

        $eraId = $this->option('era')
            ? DB::table('eras')->where('year', $this->option('era'))->value('id')
            : DB::table('eras')->where('is_current', true)->value('id');
        if (! $eraId) {
            $this->error('Ère introuvable');

            return self::FAILURE;
        }

        // Un tronçon fermé, déposé ou rasé ne porte plus de trafic (invariant
        // segments.inactive_with_traffic) : son trafic redevient « unknown ».
        $traffic = SegmentStatus::from($status)->isActive() ? 'traffic_type' : "'unknown'";

        if ($all) {
            $updated = DB::update(
                "UPDATE line_segments SET status = ?, traffic_type = {$traffic} WHERE line_id = ? AND era_id = ?",
                [$status, $line->id, $eraId],
            );
            $this->info("✓ {$line->name} ({$code}) → {$updated} segments (ère) → {$status}");

            return self::SUCCESS;
        }

        if (! $lat || ! $lng || ! $radius) {
            $this->error('Fournir soit --all soit --lat --lng --radius');

            return self::FAILURE;
        }

        $updated = DB::update(
            "UPDATE line_segments
             SET status = ?, traffic_type = {$traffic}
             WHERE line_id = ? AND era_id = ?
               AND ST_DWithin(
                   geom::geography,
                   ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                   ?
               )",
            [$status, $line->id, $eraId, (float) $lng, (float) $lat, (float) $radius],
        );

        $this->info("✓ {$line->name} ({$code}) → {$updated} segments à <{$radius}m de ({$lat}, {$lng}) → {$status}");

        return self::SUCCESS;
    }
}
