<?php

namespace App\Mcp\Tools;

use App\Enums\ServiceEnd;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Api\MediaGallery;
use App\Services\StationServiceDates;
use App\Services\StationSheet;
use App\Services\StationTimeline;
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
Fiche d'une gare, halte ou point d'arrêt français, actuel ou disparu : identité (nom, autres noms, nature, ville, département, code UIC, élément Wikidata, coordonnées WGS 84 en degrés décimaux), statut actuel (active = ouverte aux voyageurs, tourist = train touristique, no_commercial = fret seul, declassified = fermée, gone = disparue, planned = projetée, technical = point technique, excluded = hors réseau ferré), dates d'ouverture et de fermeture avec leur précision (`when` = date rédigée à cette précision ; une fermeture de précision range n'est connue que par un intervalle : `date` nulle, `from_year` et `to_year` = bornes en années, `to_year` nul quand la date est inconnue ; chronologie : day, month, year, decade, before = avant, after = après), statut à chaque décennie de la carte (instantané au 31/12, ère courante = aujourd'hui ; uncertain = non confirmé), chronologie (chaque date avec sa source, son lien, et `inferred` = date déduite des lignes plutôt que d'une source sur la gare), lignes desservies (période de desserte : `first_year`, `last_year` et `last_year_kind` = year, upper_bound pour « au plus tard cette année » ou undated pour « fin non datée », jamais une date sûre dans ces deux cas ; `years_source` = line_events, traces, ou station quand les années viennent des dates de la gare faute d'événement daté de la ligne ; `service_start` et `service_end` = date sûre du début et de la fin de desserte, avec `precision` (day, month, year), `when` rédigé à cette précision et `event_id`, l'événement de ligne qui la donne (son `id` dans les événements de get_line et line_events, REST /api/v1/events/{id}), nuls quand seule l'année est sûre ; point kilométrique en km, desserte actuelle) et sources rattachées (SNCF, OSM, Wikidata, OHM…, avec lien, écart en mètres à la position retenue, méthode de rapprochement, date de lecture par VFF et attributs bruts reçus de la source). `attribute_sources` dit quelle source a fourni chaque attribut de la gare ; `position.flagged` signale une position à vérifier. `media_count` : nombre d'images de la gare publiées (photos, cartes postales, plans) ; lisez-les avec leurs droits par l'outil media et {"station": "<slug>"}.
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
        // Fermeture encadrée (range) : jamais écrite comme un jour. Bornes en années ; borne haute nulle
        // quand elle n'est que l'année du calcul (fermeture non datée, comme ServiceEnd::Undated).
        $currentYear = (int) now()->year;
        $closedAt = $station->closed_at?->format('Y-m-d');
        // Borne basse de l'intervalle, lue comme la fiche (StationSheet) : fermée après cette année.
        $closedAfter = is_numeric($station->provenance['closed_after'] ?? null) ? (int) $station->provenance['closed_after'] : null;
        $closed = $closedAt !== null && $station->closed_precision === StationTimeline::RANGE
            ? ['date' => null, 'precision' => StationTimeline::RANGE,
                'from_year' => $closedAfter !== null ? $closedAfter + 1 : null,
                'to_year' => (int) substr($closedAt, 0, 4) < $currentYear ? (int) substr($closedAt, 0, 4) : null,
                'when' => EventText::isoDate($closedAt, StationTimeline::RANGE, $lang, $closedAfter, $currentYear)]
            : $date($closedAt, $station->closed_precision);

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
                'closed' => $closed,
            ],
            'eras' => array_values(array_map(fn ($e) => ['year' => (int) $e->year, 'status' => $e->status],
                array_filter($sheet->eras($sid), fn ($e) => $e->status !== null))),
            'events' => $events,
            'lines' => array_map(fn ($l) => [
                'slug' => $l->slug, 'code' => $l->code, 'name' => $l->name,
                'first_year' => $l->first_year !== null ? (int) $l->first_year : null,
                'last_year' => $l->last_year !== null ? (int) $l->last_year : null,
                'last_year_kind' => ServiceEnd::of($l->last_year !== null ? (int) $l->last_year : null,
                    $closedAt, $station->closed_precision, $currentYear)?->value,
                'years_source' => $l->years_source,
                // Date sûre du début et de la fin de desserte, et l'événement de ligne qui la donne.
                'service_start' => StationServiceDates::written(($bounds = StationServiceDates::bounds($l, $currentYear))['start'], $lang),
                'service_end' => StationServiceDates::written($bounds['end'], $lang),
                'pk_km' => $l->pk_m !== null ? round($l->pk_m / 1000, 3) : null,
                'served_today' => (bool) $l->active_current,
            ], $sheet->lines($sid)),
            'sources' => $sources,
            'media_count' => app(MediaGallery::class)->count($station),
        ], $providers, $lang, self::url('/stations/'.$station->slug), ['events', 'sources', 'lines', 'eras']);
    }
}
