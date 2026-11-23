<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Media\Aerial\AerialImages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_aerial')]
#[Title("Vues aériennes historiques d'une gare")]
#[Description(<<<'TXT'
Vues aériennes historiques des abords d'une gare (500 m sur 300 m centrés sur la gare), une par période des photographies de l'IGN : 1950-1965, 1965-1980, 1980-1995 (BD ORTHO historique). Mêmes données que l'API REST GET /api/v1/stations/{station}/aerial et que la fiche de la gare ; utile pour voir les voies, les quais et la halle d'une gare aujourd'hui disparue.
Chaque vue (`views`) : `period`, `from` et `to` (années), `url` (image JPEG servie par VFF ; 404 quand la période ne couvre pas la gare, sans autre requête), `layer` (couche du WMS de l'IGN), `attribution` (crédit à afficher avec l'image), `license` et `license_url` (Licence Ouverte 2.0 : réutilisation libre, y compris commerciale, avec la mention de l'IGN), `source_url` (lien « Remonter le temps » de l'IGN), `commercial_ok`, `derivatives_ok`. Liste vide quand les vues aériennes sont désactivées sur l'instance.
Prend le slug renvoyé par search (ex. "limoges-benedictins").
Exemple : {"station": "brest"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationAerialTool extends VffTool
{
    protected function rules(): array
    {
        return ['station' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(255)->required()
                ->description('Gare : slug renvoyé par search (ex. "brest").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = Lookup::station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $pos = DB::selectOne('SELECT ST_Y(geom) AS lat, ST_X(geom) AS lng FROM stations WHERE id = ?', [$station->id]);
        $views = $pos === null || $pos->lat === null ? [] : AerialImages::views($station->slug, (float) $pos->lat, (float) $pos->lng);
        // Adresse du relais rendue absolue sur le site public (elle est relative dans l'API REST).
        $views = array_map(fn (array $v): array => ['url' => self::url($v['url'])] + $v, $views);

        return $this->respond([
            'station' => ['slug' => $station->slug, 'name' => $station->name],
            'count' => count($views),
            'views' => $views,
        ], $views === [] ? [] : ['ign'], $lang, self::url('/stations/'.$station->slug));
    }
}
