<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\NonCommercialSources;
use App\Mcp\Support\ServedStationDates;
use App\Mcp\Support\VffTool;
use App\Services\Communes\CommuneRail;
use App\Services\Communes\CommuneSheet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_commune')]
#[Title("Le rail d'une commune")]
#[Description(<<<'TXT'
Le rail d'une commune actuelle : ses gares et haltes, les lignes qui la traversent ou la traversaient, sa chronologie et ses voisines. Mêmes données que l'API REST GET /api/v1/communes/{insee} et la page « Le rail à… » (/communes/{insee}-{nom}, service CommuneSheet). Seules les communes qui ont au moins une gare ou une ligne ont une fiche.
`commune` : code INSEE (ex. "16015" pour Angoulême, "2A004" en Corse), adresse de la page ("16015-angouleme") ou nom ("Angoulême") ; un nom porté par plusieurs communes donne l'erreur `commune_ambiguous` avec les `candidates` (code INSEE, département) : précisez `department` ou passez le code.
Réponse : `commune` (`insee`, `slug`, `name`, `department_code`, `department_name`, `population`, `former_names` : anciennes communes fusionnées), `current_era`, `counts`, `stations` (gares dont le point est dans la commune : `slug` pour get_station, `type`, `status`, `opened_at`/`closed_at` avec leur précision, `opened_after`/`closed_after` pour une date seulement bornée, `lat`, `lng`), `lines` (lignes dont 50 m de voie au moins sont dans la commune à l'une des ères : `state` open ou closed, `track` de la voie d'une ligne fermée (in_place, dismantled, none), `km` dans la commune et `km_year` de la carte qui les mesure, `first_year`, `last_year`, `opened`, `closed`, `electrified` quand un événement daté de la ligne passe par la commune), `chronology` (ouvertures, fermetures, électrifications et accidents de la commune, du plus ancien au plus récent ; `kind`, `date`, `precision` day, month, year, decade, after, before ou range, à lire avec elle ; paginée par `limit` et `offset`, `chronology_total`) et `neighbors` (communes voisines qui ont du rail).
Précautions : positions d'après les contours simplifiés à 100 m de l'IGN et d'Etalab, une gare à moins de 100 m d'une limite peut être rattachée à la commune voisine ; longueurs en km de voie, voies parallèles comprises. Aucun tracé n'est servi. Les faits d'une source à usage non commercial ne sont jamais servis.
Sources et licences (bloc `attribution`) : contours des communes (Etalab, ODbL ; IGN ADMIN EXPRESS, Licence Ouverte 2.0), tracés et gares de VFF (ODbL, dont OpenStreetMap et SNCF Open Data), événements datés avec leur source dans line_events et station_events, accidents (Wikidata CC0, Wikipédia).
Exemples : {"commune": "16015"} ; {"commune": "Saint-Pierre-des-Corps"} ; {"commune": "Saint-Sulpice", "department": "81"} ; {"commune": "87085", "limit": 50, "offset": 50}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetCommuneTool extends VffTool
{
    private const MAX_LIMIT = CommuneSheet::CHRONOLOGY_LIMIT;

    /** Événements de gare de la chronologie lus dans station_events (les autres viennent de la fiche de la gare). */
    private const STATION_EVENT_KINDS = ['station_reopening', 'station_closure_passenger', 'station_closure_freight', 'station_demolition'];

    protected function rules(): array
    {
        return [
            'commune' => ['required', 'string', 'min:1', 'max:120'],
            'department' => ['sometimes', 'string', 'regex:/^([0-9]{2,3}|2[ABab])$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'commune' => $schema->string()->min(1)->max(120)->required()
                ->description('Commune actuelle : code INSEE (ex. "16015"), adresse de la page ("16015-angouleme") ou nom ("Angoulême").'),
            'department' => $schema->string()
                ->description('Département (code INSEE, ex. "81") pour départager deux communes du même nom.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(100)
                ->description('Nombre d\'entrées de la chronologie renvoyées, 1 à 400 (100 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Nombre d\'entrées de la chronologie à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $department = isset($input['department']) ? strtoupper($input['department']) : null;
        $insee = CommuneRail::inseeOf(strtoupper($input['commune'])) ?? CommuneRail::inseeOf($input['commune']);
        if ($insee === null) {
            $found = app(CommuneRail::class)->search($input['commune'], 10);
            if ($department !== null) {
                $found = array_values(array_filter($found, fn (array $c): bool => $c['department_code'] === $department));
            }
            // Le même nom écrit autrement (casse, accents, tirets) : la commune de ce nom, sinon le seul résultat.
            $exact = array_values(array_filter($found, fn (array $c): bool => Str::slug($c['name']) === Str::slug($input['commune'])));
            $chosen = count($exact) === 1 ? $exact[0] : (count($found) === 1 ? $found[0] : null);
            if ($chosen === null) {
                $candidates = array_map(fn (array $c): array => array_intersect_key($c, array_flip(['insee', 'slug', 'name', 'department_code', 'department_name'])), $exact !== [] ? $exact : $found);

                return $candidates === []
                    ? $this->fail('commune_not_found', ['query' => $input['commune']])
                    : $this->fail('commune_ambiguous', ['query' => $input['commune']], ['candidates' => $candidates]);
            }
            $insee = $chosen['insee'];
        }

        $page = app(CommuneSheet::class)->page($insee, $lang);
        if ($page === null || ($department !== null && $page['commune']['department_code'] !== $department)) {
            return $this->fail('commune_not_found', ['query' => $input['commune']]);
        }
        unset($page['map']);
        $dates = app(ServedStationDates::class)->load(array_merge(array_column($page['stations'], 'slug'),
            array_map(fn (array $e): ?string => $e['station']['slug'] ?? null, $page['chronology'])));
        $page['stations'] = array_map(fn (array $s): array => $dates->station($s), $page['stations']);
        $chronology = $this->servedChronology($page['chronology'], $dates);
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 100);
        $page['chronology'] = array_slice($chronology, $offset, $limit);
        $page['chronology_total'] = count($chronology);
        $page['chronology_offset'] = $offset;
        $page['chronology_has_more'] = $offset + $limit < count($chronology);

        $providers = ['communes', 'ign'];
        if (in_array('accident', array_column($chronology, 'kind'), true)) {
            $providers[] = 'wikidata';
        }

        return $this->respond($page, $providers, $lang, self::url('/communes/'.$page['commune']['slug']), ['chronology', 'lines', 'stations', 'neighbors']);
    }

    /**
     * Chronologie servie au MCP : un événement de gare (réouverture, fin d'un trafic, démolition) n'est gardé que si un
     * événement d'une source servie le donne (même gare, même type, même année) ; l'ouverture et la fermeture d'une
     * gare (dates de sa fiche) seulement si elles sont servies (ServedStationDates) ; les événements de ligne sont déjà
     * lus sans les sources non commerciales (CommuneSheet).
     *
     * @param  list<array<string, mixed>>  $chronology
     * @return list<array<string, mixed>>
     */
    private function servedChronology(array $chronology, ServedStationDates $dates): array
    {
        $chronology = array_values(array_filter($chronology, fn (array $e): bool => match ($e['kind']) {
            'station_opening' => $dates->served($e['station']['slug'] ?? null, 'opened', $e['date']),
            'station_closure' => $dates->served($e['station']['slug'] ?? null, 'closed', $e['date']),
            default => true,
        }));
        $slugs = [];
        foreach ($chronology as $e) {
            if (in_array($e['kind'], self::STATION_EVENT_KINDS, true)) {
                $slugs[] = $e['station']['slug'];
            }
        }
        if ($slugs === []) {
            return $chronology;
        }
        $served = [];
        $rows = DB::table('station_events')->join('stations', 'stations.id', '=', 'station_events.station_id')
            ->whereIn('stations.slug', array_values(array_unique($slugs)))->whereNotNull('station_events.event_date')
            ->whereRaw(app(NonCommercialSources::class)->sqlKeptStationEvent('station_events'))
            ->orderBy('station_events.event_date')->orderBy('station_events.id')
            ->get(['stations.slug', 'station_events.type', 'station_events.event_date', 'station_events.date_precision']);
        foreach ($rows as $r) {
            $served[$r->slug.'|station_'.$r->type.'|'.substr((string) $r->event_date, 0, 4)][] = ['date' => substr((string) $r->event_date, 0, 10), 'precision' => (string) ($r->date_precision ?? 'day')];
        }

        $out = [];
        foreach ($chronology as $e) {
            if (in_array($e['kind'], self::STATION_EVENT_KINDS, true)) {
                $same = $served[$e['station']['slug'].'|'.$e['kind'].'|'.substr((string) $e['date'], 0, 4)] ?? [];
                if ($same === []) {
                    continue;
                }
                if (! in_array(substr((string) $e['date'], 0, 10), array_column($same, 'date'), true)) {
                    $e = array_replace($e, $same[0]);
                }
            }
            $out[] = $e;
        }

        return $out;
    }
}
