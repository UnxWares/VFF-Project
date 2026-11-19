<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\NetworkStats;
use App\Services\SiteStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('network_at_era')]
#[Title('État du réseau à une époque')]
#[Description(<<<'TXT'
Statistiques du réseau ferré français à une époque, d'après la carte VFF par décennie (instantané au 31/12 de l'année de l'ère ; l'ère courante décrit le réseau d'aujourd'hui). Une année quelconque est ramenée à l'ère qui la contient ou la précède (1937 donne l'ère 1930) ; à partir de l'année en cours : réseau actuel.
Renvoie, en km arrondis : longueur en service (open_km), fermée voie en place (closed_km), déposée ou effacée (gone_km), électrifiée en service (electrified_km), longueur en service par trafic (mixed, passenger, freight, heritage = associatif, unknown) ; le nombre de lignes portant du trafic (active_lines) et le nombre de gares par statut à cette ère (active, tourist, no_commercial, declassified, uncertain…), plus la liste des ères disponibles, les chiffres d'ensemble du site (`site_totals` : lignes, gares, matériels, ères cartographiées, km exploités aujourd'hui) et la courbe du réseau à chaque ère (`network_profile` : km en service et lignes actives par ère, voies parallèles comprises).
Les longueurs sont celles des tronçons de la carte (somme des tracés, voies parallèles d'une même ligne comprises), à prendre comme ordre de grandeur et non comme kilométrage officiel.
Exemple : {"year": 1930} ; {"year": 2026, "lang": "de"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class NetworkAtEraTool extends VffTool
{
    protected function rules(): array
    {
        return ['year' => ['required', 'integer', 'min:1800', 'max:2100']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->min(1800)->max(2100)->required()
                ->description('Année quelconque de 1800 à 2100 (ex. 1930, 1937, 2026), ramenée à l\'ère de la carte.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $era = Lookup::era((int) $input['year']);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => $input['year']], ['available_eras' => Lookup::eraYears()]);
        }
        $profile = app(NetworkStats::class)->eraProfile($era->id);
        $providers = DB::table('line_segments')->where('era_id', $era->id)->distinct()->pluck('source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s))->all();

        $traffic = [];
        foreach ($profile['open_km_by_traffic'] as $code => $km) {
            $label = $code === 'passenger' ? 'mcp.traffic.passenger' : 'lines.categories.'.$code;
            $traffic[] = ['code' => $code, 'label' => (string) trans($label, [], $lang), 'km' => $km];
        }
        $stations = [];
        foreach ($profile['stations_by_status'] as $code => $n) {
            $stations[] = ['code' => $code, 'label' => (string) trans('stations.statuses.'.$code, [], $lang), 'count' => $n];
        }

        return $this->respond([
            'requested_year' => (int) $input['year'],
            'era_year' => $era->year,
            'is_current' => $era->is_current,
            'snapshot' => $era->is_current ? (string) trans('mcp.today', [], $lang) : sprintf('%d-12-31', $era->year),
            'open_km' => $profile['open_km'],
            'closed_km' => $profile['closed_km'],
            'gone_km' => $profile['gone_km'],
            'electrified_km' => $profile['electrified_km'],
            'active_lines' => $profile['active_lines'],
            'open_km_by_traffic' => $traffic,
            'stations_by_status' => $stations,
            'available_eras' => Lookup::eraYears(),
            // Chiffres d'ensemble du site et courbe du réseau à chaque ère (mêmes valeurs que GET /api/v1/stats).
            'site_totals' => app(SiteStats::class)->counts(),
            'network_profile' => app(SiteStats::class)->profile(),
        ], $providers, $lang, self::url('/maps?era='.$era->year));
    }
}
