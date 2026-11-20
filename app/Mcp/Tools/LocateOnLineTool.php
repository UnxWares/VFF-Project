<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\LinePk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('locate_on_line')]
#[Title("Point kilométrique d'un lieu sur une ligne")]
#[Description(<<<'TXT'
Convertit des coordonnées (latitude, longitude WGS 84 en degrés décimaux) en point kilométrique (PK) sur le tracé d'une ligne, comme l'API REST GET /api/v1/lines/{line}/pk. Le point est projeté sur le tracé le plus proche de la ligne à l'ère choisie, puis la distance est mesurée le long du tracé depuis son origine.
Renvoie `pk_m` (mètres), `pk_label` (notation ferroviaire km+mmm : 249+204 = 249,204 km), `total_m` (longueur du tracé à cette ère), `geometry_withheld` et `snap` (lat, lng du point du tracé retenu, distance_from_input_m = écart avec le lieu demandé : au-delà de quelques dizaines de mètres, le lieu n'est probablement pas sur cette ligne).
Tracé dont la source n'autorise pas la redistribution (`geometry_withheld` vrai, raison dans `geometry_withheld_reason`) : `snap` est nul, ni le point projeté ni la distance au tracé ne sont donnés, et le PK, calculé au centre de la maille de 0,001 degré du lieu puis arrondi à 100 m, est juste à 150 m près environ.
Ce PK est mesuré le long du tracé VFF : il peut différer du PK officiel de SNCF Réseau (origine différente, sauts de numérotation). Pour les PK officiels des gares, voir `get_line` (gares) et `get_station`. Ligne sans tracé à l'ère demandée : erreur `no_geometry`.
Exemple : {"line": "rfn-420000", "lat": 48.10, "lng": -1.67} ; {"line": "rfn-447000", "lat": 48.65, "lng": -3.9, "year": 1930}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LocateOnLineTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-420000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "420000").'),
            'lat' => $schema->number()->min(-90)->max(90)->required()
                ->description('Latitude WGS 84 du lieu, en degrés décimaux (-90 à 90).'),
            'lng' => $schema->number()->min(-180)->max(180)->required()
                ->description('Longitude WGS 84 du lieu, en degrés décimaux (-180 à 180).'),
            'year' => $schema->integer()->min(1800)->max(2100)
                ->description("Année de 1800 à 2100, ramenée à l'ère de la carte (1937 donne l'ère 1930). Par défaut : le réseau d'aujourd'hui."),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $era = Lookup::era(isset($input['year']) ? (int) $input['year'] : null);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => $input['year'] ?? null], ['available_eras' => Lookup::eraYears()]);
        }
        $located = app(LinePk::class)->locate($line, $era->id, (float) $input['lat'], (float) $input['lng']);
        if ($located === null) {
            return $this->fail('no_geometry', ['line' => $line->name, 'year' => $era->year]);
        }
        $providers = DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $era->id)->distinct()->pluck('source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s))->all();

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], 'era_year' => $era->year] + $located,
            $providers,
            $lang,
            self::url('/lines/'.$line->slug),
        );
    }
}
