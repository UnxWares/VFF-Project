<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\Lookup;
use App\Models\Line;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('present_line')]
#[Title('Présenter une ligne avec ses sources')]
#[Description('Présentation d\'une ligne de chemin de fer française : tracé, longueur, électrification, signalisation, grandes dates et état actuel, chaque fait avec sa source, sa précision de date et les mentions de licence. Le message indique à l\'assistant les outils à appeler (search, get_line, line_events).')]
class PresentLinePrompt extends VffPrompt
{
    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument('line', 'La ligne : nom (« Paris - Brest »), code SNCF Réseau à 6 chiffres (420000) ou identifiant (rfn-420000).', required: true),
            self::langArgument(),
        ];
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'line' => ['required', 'string', 'max:150'],
            'lang' => self::langRule(),
        ]);
        $lang = (string) ($input['lang'] ?? 'fr');
        $query = trim((string) $input['line']);
        $line = Lookup::line($query) ?? self::uniqueByName($query);

        $identify = $line !== null
            ? trans('mcp.prompts.present_line.found', ['name' => $line->name, 'slug' => $line->slug, 'lang' => $lang], $lang)
            : trans('mcp.prompts.present_line.search', ['line' => $query], $lang);

        return Response::text((string) trans('mcp.prompts.present_line.message', [
            'line' => $line->name ?? $query,
            'identify' => $identify,
            'language' => self::languageName($lang),
        ], $lang));
    }

    /** Ligne dont le nom est exactement celui demandé (casse et accents ignorés), si elle est seule. */
    private static function uniqueByName(string $name): ?Line
    {
        $lines = Line::whereRaw('unaccent(lower(name)) = unaccent(lower(?))', [$name])->limit(2)->get();

        return $lines->count() === 1 ? $lines->first() : null;
    }
}
