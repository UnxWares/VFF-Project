<?php

namespace App\Mcp\Tools;

use App\Enums\ServiceEnd;
use App\Mcp\Support\VffTool;
use App\Services\Api\MediaGallery;
use App\Services\Sncf\StationPlatforms;
use App\Services\StationAudit\StationAuditQuery;
use App\Services\StationServiceDates;
use App\Services\StationSheet;
use App\Services\StationTimeline;
use App\Support\EventText;
use App\Support\GeneratedText;
use App\Support\RedistributableDates;
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
Fiche d'une gare, halte ou point d'arrêt français, actuel ou disparu : identité (nom, autres noms, nature, ville, département, code UIC, élément Wikidata, coordonnées WGS 84 en degrés décimaux), statut actuel (active = ouverte aux voyageurs, tourist = train touristique, no_commercial = fret seul, declassified = fermée, gone = disparue, planned = projetée, technical = point technique, excluded = hors réseau ferré), dates d'ouverture et de fermeture avec leur précision (`when` = date rédigée à cette précision ; une ouverture ou une fermeture de précision range n'est connue que par un intervalle : `date` nulle, `from_year` et `to_year` = bornes en années (`to_year` nul pour une fermeture quand la date est inconnue ; pour une ouverture `to_year` est toujours donné, et `station.opened_after` = ouverte après cette année) ; chronologie : day, month, year, decade, before = avant, after = après), statut à chaque décennie de la carte (instantané au 31/12, ère courante = aujourd'hui ; uncertain = non confirmé), chronologie (chaque date avec sa source, son lien, et `inferred` = date déduite des lignes plutôt que d'une source sur la gare), lignes desservies (période de desserte : `first_year`, `last_year` et `last_year_kind` = year, upper_bound pour « au plus tard cette année » ou undated pour « fin non datée », jamais une date sûre dans ces deux cas ; `years_source` = line_events, traces, ou station quand les années viennent des dates de la gare faute d'événement daté de la ligne ; `service_start` et `service_end` = date sûre du début et de la fin de desserte, avec `precision` (day, month, year), `when` rédigé à cette précision et `event_id`, l'événement de ligne qui la donne (son `id` dans les événements de get_line et line_events, REST /api/v1/events/{id}), nuls quand seule l'année est sûre ; point kilométrique en km, desserte actuelle) et sources rattachées (SNCF, OSM, Wikidata, OHM…, avec lien, écart en mètres à la position retenue, méthode de rapprochement, date de lecture par VFF et attributs bruts reçus de la source). `attribute_sources` dit quelle source a fourni chaque attribut de la gare ; `position.flagged` signale une position à vérifier. `media_count` : nombre d'images de la gare publiées (photos, cartes postales, plans) ; lisez-les avec leurs droits par l'outil media et {"station": "<slug>"}.
`platforms` : quais de la gare d'après le jeu « Liste des quais » de SNCF Réseau (ODbL, état actuel) : `count` (quais distincts, un quai central entre deux voies compte une fois), `longest_m`, `total_length_m`, `items` (label SNCF, code_ligne de la voie bordée, length_m, pk_start_m, pk_end_m en mètres), `dataset_modified`, `attribution` (mention « SNCF Réseau » à reproduire) et `provenance` ; null quand le jeu ne donne aucun quai pour la gare, ce qui ne prouve pas qu'elle n'en a pas.
`audit` : audit de la gare (null tant qu'elle n'a pas été auditée) : note de placement (`placement.grade` : agree = une source indépendante à moins de 20 m, close = entre 20 et 100 m, isolated = aucune source indépendante proche, disagree = une source précise à plus de 100 m ou une grossière à plus de 1 km, none = aucune source ; positions de chaque source avec l'écart en mètres à la position retenue et à la voie, coordonnées seulement si la source est redistribuable), notes des dates (sourced, estimated = déduite par VFF, unknown, not_applicable = encore ouverte), services voyageurs et fret, contrôles croisés en échec (`checks[].code`, dont `outlier_source` : une source précise isolée s'écarte des autres, et `closure_sncf_referential` : fermeture tirée du référentiel SNCF), et dates de Wikidata (P1619, P3999) comparées à celles de VFF sans les remplacer (`comparison` : same, more_precise, less_precise, close = à moins d'un an d'un intervalle des ères, differs, new).
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
        $station = $this->station($input['station']);
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
        // Ouverture ou fermeture que seuls des faits sans source redistribuable donnent : celle d'une source servie de
        // même année, ou nulle (RedistributableDates, ServedStationDates).
        $dates = app(RedistributableDates::class)->station([
            'slug' => $station->slug,
            'opened_at' => $station->opened_at?->format('Y-m-d'), 'opened_precision' => $station->opened_precision,
            'opened_after' => is_numeric($station->provenance['opened_after'] ?? null) ? (int) $station->provenance['opened_after'] : null,
            'closed_at' => $station->closed_at?->format('Y-m-d'), 'closed_precision' => $station->closed_precision,
            // Borne basse de l'intervalle, lue comme la fiche (StationSheet) : fermée après cette année.
            'closed_after' => is_numeric($station->provenance['closed_after'] ?? null) ? (int) $station->provenance['closed_after'] : null,
        ]);
        $closedAt = $dates['closed_at'];
        $closedAfter = $dates['closed_after'];
        $closed = $closedAt !== null && $dates['closed_precision'] === StationTimeline::RANGE
            ? ['date' => null, 'precision' => StationTimeline::RANGE,
                'from_year' => $closedAfter !== null ? $closedAfter + 1 : null,
                'to_year' => (int) substr($closedAt, 0, 4) < $currentYear ? (int) substr($closedAt, 0, 4) : null,
                'when' => EventText::isoDate($closedAt, StationTimeline::RANGE, $lang, $closedAfter, $currentYear)]
            : $date($closedAt, $dates['closed_precision']);

        // Ouverture déduite et bornée par la carte (règle G3, range) : jamais un jour sûr. Bornes en années ;
        // la borne haute reste l'année de opened_at, même l'année en cours (la gare est desservie à l'ère courante).
        $openedAt = $dates['opened_at'];
        $openedAfter = $dates['opened_after'];
        $opened = $openedAt !== null && $dates['opened_precision'] === StationTimeline::RANGE
            ? ['date' => null, 'precision' => StationTimeline::RANGE,
                'from_year' => $openedAfter !== null ? $openedAfter + 1 : null,
                'to_year' => (int) substr($openedAt, 0, 4),
                'when' => EventText::isoDate($openedAt, StationTimeline::RANGE, $lang, $openedAfter)]
            : $date($openedAt, $dates['opened_precision']);

        $lineRows = $sheet->lines($sid);
        $requests = [];
        foreach ($lineRows as $i => $l) {
            $bounds = StationServiceDates::bounds($l, $currentYear);
            $requests["{$i}:start"] = ['bound' => $bounds['start'], 'role' => 'start', 'station_id' => $sid];
            $requests["{$i}:end"] = ['bound' => $bounds['end'], 'role' => 'end', 'station_id' => $sid];
        }
        $servedBounds = app(RedistributableDates::class)->serviceBounds($requests);

        $providers = [];
        // Chronologie sans les faits sans source redistribuable, ni les dates déduites de tels faits.
        $events = $sheet->events($sid, app(RedistributableDates::class)->stationEventKept('station_events'))->map(function ($e) use ($lang, $date, &$providers) {
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

        // Quais (SNCF Réseau, ODbL) : null quand le jeu « Liste des quais » n'en donne aucun.
        $platforms = app(StationPlatforms::class)->forStation($sid);
        if ($platforms !== null) {
            $providers[] = StationPlatforms::SOURCE;
        }

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
                'opened' => $opened,
                // Ouverte après cette année (précision range de `opened`), nul sinon.
                'opened_after' => ($opened['precision'] ?? null) === StationTimeline::RANGE ? $openedAfter : null,
                'closed' => $closed,
            ],
            'eras' => array_values(array_map(fn ($e) => ['year' => (int) $e->year, 'status' => $e->status],
                array_filter($sheet->eras($sid), fn ($e) => $e->status !== null))),
            'events' => $events,
            'lines' => array_map(fn ($l, $i) => [
                'slug' => $l->slug, 'code' => $l->code, 'name' => $l->name,
                'first_year' => $l->first_year !== null ? (int) $l->first_year : null,
                'last_year' => $l->last_year !== null ? (int) $l->last_year : null,
                'last_year_kind' => ServiceEnd::of($l->last_year !== null ? (int) $l->last_year : null,
                    $closedAt, $station->closed_precision, $currentYear)?->value,
                'years_source' => $l->years_source,
                // Date sûre du début et de la fin de desserte, et l'événement de ligne qui la donne.
                // Borne dont l'événement vient d'une source non commerciale : remplacée ou nulle (ServedEventDates).
                'service_start' => StationServiceDates::written($servedBounds["{$i}:start"], $lang),
                'service_end' => StationServiceDates::written($servedBounds["{$i}:end"], $lang),
                'pk_km' => $l->pk_m !== null ? round($l->pk_m / 1000, 3) : null,
                'served_today' => (bool) $l->active_current,
            ], $lineRows, array_keys($lineRows)),
            'sources' => $sources,
            'platforms' => $platforms,
            'media_count' => app(MediaGallery::class)->count($station),
            'audit' => app(StationAuditQuery::class)->forStation($sid),
        ], $providers, $lang, self::url('/stations/'.$station->slug), ['events', 'sources', 'lines', 'eras']);
    }
}
