<?php

namespace App\Services\TrackLayout;

use App\Models\Era;
use App\Models\Line;
use App\Services\LevelCrossings\LineLevelCrossings;
use App\Services\Lines\LineSchematic;
use App\Services\LineStations;
use App\Services\Profiles\LineProfiles;
use App\Services\Speed\LineSpeeds;
use Illuminate\Support\Facades\DB;

/**
 * Source `vff` du schéma des voies : ce que VFF publie déjà ailleurs, à l'ère courante, pour compléter le RINF et
 * OpenStreetMap (TrackLayout ne garde que ce qu'ils ne donnent pas) :
 *
 * - points d'exploitation : gares et haltes ouvertes de la ligne aujourd'hui (LineStations), au PK de VFF,
 *   `exact` quand il vient de SNCF Réseau ou d'une source écrite (LineSchematic::CALIBRATED), `approximate` sinon ;
 * - vitesses : tranches de vitesse maximale de l'ère courante (LineSpeeds, jeu de SNCF Réseau, ODbL) ;
 * - déclivités : calculées sur le profil en long de VFF (altitudes de l'IGN, PK calés sur les gares), par fenêtres
 *   de GRADE_WINDOW_M mètres le long des PK, `derived` ; trous et embranchements du profil écartés ;
 * - traversées : passages à niveau de SNCF Réseau au PK connu (LineLevelCrossings, ODbL).
 * Aucune voie : VFF ne connaît que le nombre de voies par portion (GET /lines/{line}/infrastructure).
 */
final class VffTrackLayoutSource implements TrackLayoutSource
{
    /** Longueur (m) des fenêtres de calcul des déclivités sur le profil en long. */
    public const GRADE_WINDOW_M = 1000;

    public function __construct(
        private readonly LineStations $stations,
        private readonly LineSpeeds $speeds,
        private readonly LineProfiles $profiles,
        private readonly LineLevelCrossings $crossings,
    ) {}

    public function key(): string
    {
        return 'vff';
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        $era = Era::query()->where('is_current', true)->first();
        if ($era === null) {
            return TrackLayoutPart::empty();
        }
        $year = (int) $era->year;

        $points = [];
        foreach ($this->stations->forLine((int) $line->id)['stations'] as $s) {
            $status = $s['eras'][$year]['status'] ?? null;
            if ($s['kind'] !== 'station' || $s['pk_m'] === null || ! in_array($status, LineSchematic::OPEN, true)) {
                continue;
            }
            $points[] = [
                'id' => 'vff:station:'.$s['slug'], 'name' => $s['name'], 'kind' => $s['type'] === 'halte' ? 'halt' : 'station',
                'pk_m' => (int) $s['pk_m'], 'uopid' => null, 'station_slug' => $s['slug'], 'rinf_type' => null, 'source' => 'vff',
                'precision' => in_array($s['link_method'], LineSchematic::CALIBRATED, true) ? 'exact' : 'approximate',
            ];
        }

        $speeds = array_map(fn (array $r): array => ['pk_from_m' => $r['pk_start_m'], 'pk_to_m' => $r['pk_end_m'], 'kmh' => $r['max_speed_kmh'], 'source' => 'vff'],
            $this->speeds->ranges((int) $line->id, (int) $era->id));

        $crossings = [];
        foreach ($this->crossings->list($line) as $c) {
            if ($c->pk_m !== null) {
                $crossings[] = ['id' => 'vff:pn:'.$c->id, 'pk_m' => (int) $c->pk_m, 'kind' => 'level_crossing', 'ref' => $c->libelle, 'source' => 'vff'];
            }
        }

        $gradients = $this->gradients($line);
        $part = new TrackLayoutPart(operationalPoints: $points, speeds: $speeds, gradients: $gradients, crossings: $crossings);

        return $part->isEmpty() ? $part : new TrackLayoutPart(
            source: ['key' => 'vff', 'label' => 'VFF', 'licence' => 'ODbL 1.0 (SNCF Réseau : vitesses, passages à niveau, PK des gares) ; Licence Ouverte 2.0 (IGN : altitudes du profil en long)',
                'dataset' => 'Gares de la ligne, vitesses maximales (ère courante), profil en long, passages à niveau', 'fetched_at' => null,
                'url' => url('/lines/'.$line->slug)],
            operationalPoints: $points, speeds: $speeds, gradients: $gradients, crossings: $crossings,
        );
    }

    /**
     * Déclivités du profil en long par fenêtres de GRADE_WINDOW_M mètres de PK, dans chaque morceau continu.
     *
     * @return list<array{pk_from_m: int, pk_to_m: int, permille: float, source: string, precision: string}>
     */
    private function gradients(Line $line): array
    {
        $profile = $this->profiles->find($line);
        if ($profile === null) {
            return [];
        }
        $rows = DB::table('line_profile_points')->where('line_profile_id', $profile->id)->whereNull('branch')
            ->whereNotNull('pk_m')->whereNotNull('altitude_m')->orderBy('seq')->get(['pk_m', 'altitude_m', 'piece']);

        return self::windows(array_map(fn (object $r): array => [(int) $r->pk_m, (float) $r->altitude_m, (int) $r->piece], $rows->all()));
    }

    /**
     * @param  list<array{0: int, 1: float, 2: int}>  $points  [PK, altitude, morceau] dans l'ordre du parcours
     * @return list<array{pk_from_m: int, pk_to_m: int, permille: float, source: string, precision: string}>
     */
    public static function windows(array $points): array
    {
        $out = [];
        $start = null;
        foreach ($points as $p) {
            if ($start === null || $p[2] !== $start[2]) {
                $start = $p;

                continue;
            }
            $dx = abs($p[0] - $start[0]);
            if ($dx >= self::GRADE_WINDOW_M) {
                $up = $p[0] > $start[0];
                $rise = $up ? $p[1] - $start[1] : $start[1] - $p[1];
                $out[] = ['pk_from_m' => min($p[0], $start[0]), 'pk_to_m' => max($p[0], $start[0]),
                    'permille' => round($rise / $dx * 1000, 1), 'source' => 'vff', 'precision' => 'derived'];
                $start = $p;
            }
        }
        usort($out, fn (array $a, array $b): int => $a['pk_from_m'] <=> $b['pk_from_m']);

        return $out;
    }
}
