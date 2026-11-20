<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\Lookup;
use App\Models\Station;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('station_story')]
#[Title('Raconter l\'histoire d\'une gare')]
#[Description('Histoire d\'une gare dans l\'ordre chronologique : ouverture, lignes qui l\'ont desservie, changements de statut, accidents survenus à proximité, images d\'archives et situation actuelle, avec la source et la précision de chaque date. Le message indique à l\'assistant les outils à appeler (search, get_station, get_line, accidents, media).')]
class StationStoryPrompt extends VffPrompt
{
    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument('station', 'La gare : nom (« Brest ») ou identifiant (brest).', required: true),
            self::langArgument(),
        ];
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'station' => ['required', 'string', 'max:150'],
            'lang' => self::langRule(),
        ]);
        $lang = (string) ($input['lang'] ?? 'fr');
        $query = trim((string) $input['station']);
        $station = Lookup::station($query) ?? self::uniqueByName($query);

        $identify = $station !== null
            ? trans('mcp.prompts.station_story.found', ['name' => $station->name, 'city' => $station->city ?? '?', 'slug' => $station->slug], $lang)
            : trans('mcp.prompts.station_story.search', ['station' => $query], $lang);

        return Response::text((string) trans('mcp.prompts.station_story.message', [
            'station' => $station->name ?? $query,
            'identify' => $identify,
            'language' => self::languageName($lang),
        ], $lang));
    }

    /** Gare dont le nom est exactement celui demandé (casse et accents ignorés), si elle est seule. */
    private static function uniqueByName(string $name): ?Station
    {
        $stations = Station::whereRaw('unaccent(lower(name)) = unaccent(lower(?))', [$name])->limit(2)->get();

        return $stations->count() === 1 ? $stations->first() : null;
    }
}
