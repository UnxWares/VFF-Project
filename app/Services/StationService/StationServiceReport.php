<?php

namespace App\Services\StationService;

use App\Enums\StationStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Comparaison du classement avec le statut des gares à l'ère courante, et rapports CSV :
 * gares.csv (une ligne par gare classée), ecarts.csv (statut que le classement
 * proposerait, différent du statut actuel), arrets-non-rapproches.csv.
 */
final class StationServiceReport
{
    private const HEADER = ['station_id', 'nom', 'departement', 'statut_vff', 'classe', 'motif', 'statut_propose', 'arrets', 'jours',
        'fenetre', 'premier', 'dernier', 'flux', 'methodes', 'signalements', 'touristique', 'velorail', 'frequentation', 'note'];

    /**
     * Tableau croisé classe × statut de l'ère courante.
     *
     * @return array<string, array<string, int>>
     */
    public function crossTab(StationServiceResult $result): array
    {
        $statuses = $this->statuses();
        $out = [];
        foreach ($result->rows as $id => $row) {
            $status = $statuses[$id]['status'] ?? 'absente';
            $out[$row->verdict->class->value][$status] = ($out[$row->verdict->class->value][$status] ?? 0) + 1;
        }
        ksort($out);

        return $out;
    }

    /** @return list<string> fichiers écrits */
    public function write(StationServiceResult $result, string $dir): array
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : {$dir}");
        }
        $statuses = $this->statuses();
        $all = [];
        $gaps = [];
        foreach ($result->rows as $id => $row) {
            $line = $this->line($row, $statuses[$id] ?? null);
            $all[] = $line;
            if ($line['statut_propose'] !== '' && $line['statut_propose'] !== $line['statut_vff']) {
                $gaps[] = $line;
            }
        }
        $unmatchedHeader = ['feed', 'stop', 'name', 'uic', 'country', 'lat', 'lon', 'calls', 'days', 'routes'];

        return [
            $this->csv("{$dir}/gares.csv", self::HEADER, $all),
            $this->csv("{$dir}/ecarts.csv", self::HEADER, $gaps),
            $this->csv("{$dir}/arrets-non-rapproches.csv", $unmatchedHeader, $result->unmatched),
        ];
    }

    /**
     * @param  array{name: string, department: ?string, status: string}|null  $station
     * @return array<string, string|int|null>
     */
    private function line(StationServiceRow $row, ?array $station): array
    {
        $status = StationStatus::tryFrom($station['status'] ?? '') ?? StationStatus::Declassified;
        $p = $row->profile;
        $feeds = array_values(array_unique(array_column($row->evidence, 'feed')));
        $methods = array_values(array_unique(array_column($row->evidence, 'method')));
        $flags = array_values(array_unique(array_merge(...array_column($row->evidence, 'flags'))));

        return [
            'station_id' => $row->stationId,
            'nom' => $station['name'] ?? '',
            'departement' => $station['department'] ?? '',
            'statut_vff' => $station['status'] ?? 'absente',
            'classe' => $row->verdict->class->value,
            'motif' => $row->verdict->pattern->value ?? '',
            'statut_propose' => $row->verdict->class->proposedStatus($status)->value ?? '',
            'arrets' => $p?->calls,
            'jours' => $p?->servedDays,
            'fenetre' => $p === null ? '' : Ymd::iso($p->from).' / '.Ymd::iso($p->to),
            'premier' => $p === null ? '' : Ymd::iso($p->first),
            'dernier' => $p === null ? '' : Ymd::iso($p->last),
            'flux' => implode(' ', $feeds),
            'methodes' => implode(' ', $methods),
            'signalements' => implode(' ', $flags),
            'touristique' => ($row->indicators['tourist_line'] ?? false) || ($row->indicators['tourist_osm'] ?? false) ? 'oui' : '',
            'velorail' => ($row->indicators['velorail_line'] ?? false) || ($row->indicators['velorail_osm'] ?? false) ? 'oui' : '',
            'frequentation' => isset($row->indicators['frequentation']) ? $row->indicators['frequentation']['travellers'] : '',
            'note' => $row->verdict->note ?? '',
        ];
    }

    /**
     * Nom, département et statut de chaque gare à l'ère courante (absente de l'ère : fermée).
     *
     * @return array<int, array{name: string, department: ?string, status: string}>
     */
    private function statuses(): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, s.name, s.department_code, COALESCE(se.status, 'declassified') AS status
            FROM stations s
            LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = (SELECT id FROM eras WHERE is_current LIMIT 1)
        SQL) as $r) {
            $out[(int) $r->id] = ['name' => $r->name, 'department' => $r->department_code, 'status' => $r->status];
        }

        return $out;
    }

    /**
     * @param  list<string>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    private function csv(string $path, array $header, array $lines): string
    {
        $handle = fopen($path, 'wb') ?: throw new RuntimeException("Écriture impossible : {$path}");
        fputcsv($handle, $header, ',', '"', '');
        foreach ($lines as $line) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($line[$column] ?? ''), $header), ',', '"', '');
        }
        fclose($handle);

        return $path;
    }
}
