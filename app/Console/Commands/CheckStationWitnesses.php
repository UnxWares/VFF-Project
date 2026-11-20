<?php

namespace App\Console\Commands;

use App\Services\GeoExporter;
use App\Services\LineStations;
use App\Services\StationTimeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gares témoins sur la base réelle (lecture seule) : pour chaque gare de
 * database/data/gares_temoins.json (dates de fermeture connues et sourcées),
 * compare les TROIS affichages :
 *   - la carte : GeoExporter::stationsAsGeoJson, à chaque ère (frise) ;
 *   - la fiche gare : stations.closed_at + station_eras (comme StationController) ;
 *   - la fiche ligne : App\Services\LineStations (vue « décennie par décennie ») ;
 * et la date retenue à la date sourcée attendue.
 *
 *   php artisan vff:check-station-witnesses
 *
 * Sortie 1 si un affichage diverge ou si une date diffère de l'attendue
 * (écart de données à corriger : événement de ligne manquant, source fausse…).
 */
class CheckStationWitnesses extends Command
{
    protected $signature = 'vff:check-station-witnesses {--file= : Jeu de gares témoins (défaut : database/data/gares_temoins.json)}';

    protected $description = 'Gares témoins : même date de fermeture et même état par ère sur la carte, la fiche gare et la fiche ligne (lecture seule)';

    public function handle(GeoExporter $geo, LineStations $lineStations): int
    {
        $file = $this->option('file') ?: database_path('data/gares_temoins.json');
        $witnesses = json_decode((string) @file_get_contents($file), true)['stations'] ?? null;
        if (! is_array($witnesses)) {
            $this->error("Jeu de témoins illisible : {$file}");

            return self::FAILURE;
        }
        $years = DB::table('eras')->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
        $map = [];
        foreach ($years as $y) {
            foreach ($geo->stationsAsGeoJson($y)['features'] as $f) {
                $map[(int) $f['id']][$y] = $f['properties'];
            }
        }

        $rows = [];
        $failures = 0;
        foreach ($witnesses as $w) {
            $codes = array_column($w['lines'] ?? [], 'code');
            $station = DB::table('stations as s')->where('s.name', $w['name'])
                ->orderByRaw('NOT EXISTS (SELECT 1 FROM station_lines sl JOIN lines l ON l.id = sl.line_id WHERE sl.station_id = s.id AND l.code IN ('
                    .implode(', ', array_fill(0, max(1, count($codes)), '?')).'))', $codes ?: [''])
                ->first(['s.id', 's.closed_at', 's.closed_precision', DB::raw("s.provenance->>'closed_after' AS closed_after")]);
            if ($station === null) {
                $rows[] = [$w['name'], $w['category'], $this->expected($w), '-', 'gare introuvable'];
                $failures++;

                continue;
            }
            $label = StationTimeline::closureLabel($station->closed_at, $station->closed_precision, is_numeric($station->closed_after) ? (int) $station->closed_after : null) ?? 'ouverte';
            $problems = [];
            if ($station->closed_at !== ($w['expected']['closed_at'] ?? null)
                || ($station->closed_at !== null && $station->closed_precision !== ($w['expected']['closed_precision'] ?? null))) {
                $problems[] = 'date ≠ attendue';
            }
            // Fiche gare : statut affiché par ère.
            $sheet = [];
            foreach (DB::table('eras as e')->leftJoin('station_eras as se', fn ($j) => $j->on('se.era_id', '=', 'e.id')->where('se.station_id', $station->id))
                ->get(['e.year', 'se.status', 'se.uncertain']) as $r) {
                $sheet[(int) $r->year] = LineStations::displayStatus($r->status, (bool) $r->uncertain);
            }
            // Frise (carte et fiches) compatible avec la date : jamais ouverte après la fermeture,
            // jamais fermée aujourd'hui sans date.
            $closedYear = $station->closed_at !== null ? (int) substr($station->closed_at, 0, 4) : null;
            $current = (int) DB::table('eras')->where('is_current', true)->value('year');
            foreach ($years as $y) {
                $open = in_array($sheet[$y], StationTimeline::OPEN_STATUSES, true);
                if ($closedYear !== null && $open && ($y >= $closedYear || $y === $current)) {
                    $problems[] = "frise {$y} ouverte après la date";
                }
            }
            // … ni déjà fermée (ou absente) à l'ère qui précède la date, après avoir été ouverte.
            $before = array_values(array_filter($years, fn ($y) => $closedYear !== null && $y < $closedYear));
            if ($before !== [] && $station->closed_precision !== StationTimeline::RANGE) {
                $prev = end($before);
                $wasOpen = array_filter($before, fn ($y) => in_array($sheet[$y], StationTimeline::OPEN_STATUSES, true));
                if ($wasOpen !== [] && ! in_array($sheet[$prev], [...StationTimeline::OPEN_STATUSES, LineStations::UNCERTAIN], true)) {
                    $problems[] = "frise {$prev} fermée avant la date";
                }
            }
            if ($closedYear === null && ! in_array($sheet[$current] ?? null, [...StationTimeline::OPEN_STATUSES, LineStations::UNCERTAIN], true)) {
                $problems[] = "fermée aujourd'hui sans date";
            }
            // Fiche ligne : chaque ligne de la gare.
            foreach (DB::table('station_lines')->where('station_id', $station->id)->pluck('line_id') as $lineId) {
                $row = collect($lineStations->forLine((int) $lineId)['stations'])->firstWhere('id', (int) $station->id);
                foreach ($years as $y) {
                    if (($row['eras'][$y]['status'] ?? null) !== $sheet[$y]) {
                        $problems[] = "fiche ligne {$y}";
                    }
                }
                if (($row['closed_at'] ?? null) !== $station->closed_at) {
                    $problems[] = 'date fiche ligne';
                }
            }
            // Carte.
            foreach ($years as $y) {
                $feature = $map[(int) $station->id][$y] ?? null;
                if ($feature === null ? ! in_array($sheet[$y], [null, StationTimeline::CLOSED], true) : $feature['status'] !== $sheet[$y]) {
                    $problems[] = "carte {$y}";
                }
                if ($feature !== null && $feature['closed_at'] !== $station->closed_at) {
                    $problems[] = "date carte {$y}";
                }
            }
            $failures += $problems === [] ? 0 : 1;
            $rows[] = [$w['name'], $w['category'], $this->expected($w), $label, $problems === [] ? 'OK' : implode(', ', array_unique($problems))];
        }
        $this->table(['gare', 'catégorie', 'attendue', 'date retenue', 'verdict'], $rows);
        $this->line(sprintf('%d témoins, %d en écart.', count($witnesses), $failures));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<string, mixed>  $w */
    private function expected(array $w): string
    {
        return StationTimeline::closureLabel($w['expected']['closed_at'] ?? null, $w['expected']['closed_precision'] ?? null, null) ?? 'ouverte';
    }
}
