<?php

namespace App\Services\ArgentanWebFerro;

use App\Services\PlaceNames;
use Illuminate\Support\Facades\DB;

/**
 * Lectures (et seulement des lectures) des tables du moteur pour rattacher et comparer les
 * faits d'Argentan Web Ferroviaire : lignes, gares des lignes, événements datés.
 */
final class VffReader
{
    /** @var array<string, ?int> gare d'un nom sur une ligne (mémo des rattachements de photos) */
    private array $stationMemo = [];

    /**
     * Identifiants des lignes désignées par code RFN (« 430000 ») ou par slug.
     *
     * @param  list<string>  $refs
     * @return array<string, int> référence → identifiant (les références inconnues sont absentes)
     */
    public function lineIds(array $refs): array
    {
        if ($refs === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('lines')->whereIn('slug', $refs)->orWhereIn('code', $refs)->orderBy('id')->get(['id', 'slug', 'code']) as $row) {
            foreach ([$row->slug, $row->code] as $ref) {
                if ($ref !== null && in_array($ref, $refs, true) && ! isset($out[$ref])) {
                    $out[$ref] = (int) $row->id;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, list<array{name: string, type: string}>>
     */
    public function stationsByLine(array $lineIds): array
    {
        $out = [];
        if ($lineIds === []) {
            return $out;
        }
        $rows = DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->whereIn('sl.line_id', $lineIds)->orderBy('sl.line_id')->orderBy('sl.pk_m')
            ->get(['sl.line_id', 's.name', 's.type']);
        foreach ($rows as $r) {
            $out[(int) $r->line_id][] = ['name' => (string) $r->name, 'type' => (string) $r->type];
        }

        return $out;
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, list<array{type: string, date: string, precision: string, source: ?string, from: ?string, to: ?string, url: ?string}>>
     */
    public function eventsByLine(array $lineIds): array
    {
        $out = [];
        if ($lineIds === []) {
            return $out;
        }
        $rows = DB::table('line_events')->whereIn('line_id', $lineIds)->orderBy('event_date')
            ->get(['line_id', 'type', 'event_date', 'date_precision', 'source', 'section_from', 'section_to', 'source_url']);
        foreach ($rows as $r) {
            $out[(int) $r->line_id][] = [
                'type' => (string) $r->type, 'date' => substr((string) $r->event_date, 0, 10), 'precision' => (string) $r->date_precision,
                'source' => $r->source, 'from' => $r->section_from, 'to' => $r->section_to, 'url' => $r->source_url,
            ];
        }

        return $out;
    }

    /** @return array<int, array{code: ?string, name: string, slug: string}> */
    public function lines(array $lineIds): array
    {
        return DB::table('lines')->whereIn('id', $lineIds)->get(['id', 'code', 'name', 'slug'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => ['code' => $r->code, 'name' => (string) $r->name, 'slug' => (string) $r->slug]])->all();
    }

    /**
     * Noms normalisés de toutes les gares connues (stations et gares citées par les sources),
     * pour couper une section « X-Y » au bon trait d'union.
     *
     * @return array<string, true>
     */
    public function placeIndex(): array
    {
        $index = [];
        foreach (DB::table('stations')->pluck('name') as $name) {
            $index[implode(' ', PlaceNames::tokens((string) $name))] = true;
        }
        foreach (DB::table('source_stations')->distinct()->pluck('name') as $name) {
            $index[implode(' ', PlaceNames::tokens((string) $name))] = true;
        }
        unset($index['']);

        return $index;
    }

    /**
     * Gare VFF d'un nom donné sur une ligne (rattachement des photos à une gare).
     */
    public function stationOnLine(int $lineId, string $name): ?int
    {
        $key = "{$lineId}|{$name}";
        if (! array_key_exists($key, $this->stationMemo)) {
            $this->stationMemo[$key] = $this->findStationOnLine($lineId, $name);
        }

        return $this->stationMemo[$key];
    }

    private function findStationOnLine(int $lineId, string $name): ?int
    {
        $rows = DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->where('sl.line_id', $lineId)->get(['s.id', 's.name']);
        foreach ($rows as $r) {
            if (PlaceMatcher::same($name, (string) $r->name)) {
                return (int) $r->id;
            }
        }

        return null;
    }
}
