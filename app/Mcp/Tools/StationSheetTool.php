<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\StationSheet;
use App\Support\EventText;
use App\Support\GeneratedText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_station')]
#[Title("Fiche d'une gare")]
#[Description(<<<'TXT'
Fiche d'une gare, halte ou point d'arrêt français, actuel ou disparu : identité (nom, autres noms, nature, ville, département, code UIC, élément Wikidata, coordonnées WGS 84 en degrés décimaux), statut actuel (active = ouverte aux voyageurs, tourist = train touristique, no_commercial = fret seul, declassified = fermée, gone = disparue, planned = projetée, technical = point technique, excluded = hors réseau ferré), dates d'ouverture et de fermeture avec leur précision, statut à chaque décennie de la carte (instantané au 31/12, ère courante = aujourd'hui ; uncertain = non confirmé), chronologie (chaque date avec sa source, son lien, et `inferred` = date déduite des lignes plutôt que d'une source sur la gare), lignes desservies (période de desserte, point kilométrique en km, desserte actuelle) et sources rattachées (SNCF, OSM, Wikidata, OHM…, avec lien, écart en mètres à la position retenue, méthode de rapprochement, date de lecture par VFF et attributs bruts reçus de la source). `attribute_sources` dit quelle source a fourni chaque attribut de la gare ; `position.flagged` signale une position à vérifier.
Prend le slug renvoyé par search (ex. "limoges-benedictins").
Exemple : {"station": "brest", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationSheetTool extends VffTool
{
    protected function rules(): array
    {
        return ['station' => ['required', 'string', 'min:1', 'max:150']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(150)->required()
                ->description('Slug de la gare, tel que renvoyé par search (ex. "brest", "limoges-benedictins").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = Lookup::station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $sheet = app(StationSheet::class);
        $sid = (int) $station->id;
        $coords = DB::selectOne('SELECT ST_X(geom) AS lng, ST_Y(geom) AS lat FROM stations WHERE id = ?', [$sid]);
        $status = fn (?string $s) => $s === null ? null : ['code' => $s, 'label' => (string) trans('stations.statuses.'.$s, [], $lang)];
        $date = fn ($d, ?string $p) => $d === null ? null : ['date' => substr((string) $d, 0, 10), 'precision' => $p ?? 'day',
            'when' => EventText::isoDate(substr((string) $d, 0, 10), $p ?? 'day', $lang)];

        $providers = [];
        $events = $sheet->events($sid)->map(function ($e) use ($lang, $date, &$providers) {
            $providers[] = $this->attribution->providerFor('station_events', $e->source, $e->source_url);
            $note = GeneratedText::stationNote($e->note, $lang);
            $label = 'stations.events.'.$e->type;

            return ['type' => $e->type, 'label' => trans()->has($label, $lang) ? (string) trans($label, [], $lang) : $e->type]
                + (array) $date($e->event_date, $e->date_precision)
                + ['source' => $e->source, 'source_url' => $e->source_url, 'inferred' => (bool) $e->is_inferred, 'note' => $note['text']];
        })->values()->all();

        $rawBySource = $sheet->rawBySource($sid);
        $sources = array_map(function ($s) use (&$providers, $rawBySource) {
            $providers[] = $this->attribution->providerFor('station_sources', $s->source, $s->source_url);
            $raw = StationSheet::rawFor($rawBySource, $s);

            return ['source' => $s->source, 'ref' => $s->source_ref, 'name' => $s->name, 'url' => StationSheet::sourceUrl($s),
                'distance_m' => (int) $s->distance_m, 'match_method' => $s->match_method] + $raw;
        }, $sheet->sources($sid));

        return $this->respond([
            'station' => [
                'slug' => $station->slug,
                'name' => $station->name,
                'alt_names' => $station->alt_names ?? [],
                'type' => $station->type->value,
                'status' => $status(StationSheet::displayStatus($station)),
                'city' => $station->city,
                'department_code' => $station->department_code,
                'uic_code' => $station->uic_code,
                'wikidata_id' => $station->wikidata_id,
                'attribute_sources' => $station->provenance ?? [],
                'position' => [
                    'source' => $station->position_source,
                    'spread_m' => $station->position_spread_m,
                    'flagged' => (bool) $station->position_flagged,
                ],
                'lat' => round((float) $coords->lat, 6),
                'lng' => round((float) $coords->lng, 6),
                'opened' => $date($station->opened_at?->format('Y-m-d'), $station->opened_precision),
                'closed' => $date($station->closed_at?->format('Y-m-d'), $station->closed_precision),
            ],
            'eras' => array_values(array_map(fn ($e) => ['year' => (int) $e->year, 'status' => $e->status],
                array_filter($sheet->eras($sid), fn ($e) => $e->status !== null))),
            'events' => $events,
            'lines' => array_map(fn ($l) => [
                'slug' => $l->slug, 'code' => $l->code, 'name' => $l->name,
                'first_year' => $l->first_year !== null ? (int) $l->first_year : null,
                'last_year' => $l->last_year !== null ? (int) $l->last_year : null,
                'pk_km' => $l->pk_m !== null ? round($l->pk_m / 1000, 3) : null,
                'served_today' => (bool) $l->active_current,
            ], $sheet->lines($sid)),
            'sources' => $sources,
        ], $providers, $lang, self::url('/stations/'.$station->slug), ['events', 'sources', 'lines', 'eras']);
    }
}
