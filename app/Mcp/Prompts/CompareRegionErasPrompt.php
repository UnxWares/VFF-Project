<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\Lookup;
use App\Support\FrenchDepartments;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('compare_region_eras')]
#[Title('Comparer deux époques d\'une région')]
#[Description('Comparaison du réseau ferré d\'une région à deux époques : lignes ouvertes et fermées, gares par statut, événements datés entre les deux (fermetures, ouvertures, électrifications), situés par rapport à la France entière. Les années sont ramenées aux décennies de la carte (1937 donne 1930 ; l\'année en cours, le réseau d\'aujourd\'hui). Le message indique à l\'assistant les outils à appeler (list_lines, list_stations, network_events, compare_eras).')]
class CompareRegionErasPrompt extends VffPrompt
{
    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument('region', 'La région : nom (« Bretagne ») ou code INSEE (« 53 »). Liste : outil list_departments.', required: true),
            new Argument('from', 'Première époque : une année de 1830 à aujourd\'hui (ex. 1930).', required: true),
            new Argument('to', 'Seconde époque, même format (ex. 2026 pour le réseau d\'aujourd\'hui).', required: true),
            self::langArgument(),
        ];
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'region' => ['required', 'string', 'max:60'],
            'from' => ['required', 'integer', 'between:1830,2100'],
            'to' => ['required', 'integer', 'between:1830,2100', 'different:from'],
            'lang' => self::langRule(),
        ]);
        $lang = (string) ($input['lang'] ?? 'fr');
        $code = self::regionCode((string) $input['region']);
        if ($code === null) {
            throw ValidationException::withMessages(['region' => 'Unknown region: use an INSEE code or a name listed by list_departments.']);
        }
        [$first, $second] = [min((int) $input['from'], (int) $input['to']), max((int) $input['from'], (int) $input['to'])];
        $from = Lookup::era($first);
        $to = Lookup::era($second);
        if ($from === null || $to === null || $from->year === $to->year) {
            throw ValidationException::withMessages(['to' => 'Both years fall in the same map era: choose two different decades.']);
        }

        $departments = array_map(fn (string $d): string => FrenchDepartments::ALL[$d][0].' ('.$d.')', FrenchDepartments::inRegion($code));

        return Response::text((string) trans('mcp.prompts.compare_region_eras.message', [
            'region' => FrenchDepartments::REGIONS[$code],
            'code' => $code,
            'from' => $from->year,
            'to' => $to->year,
            'events_from' => $from->year + 1,
            'events_to' => $to->is_current ? (int) date('Y') : $to->year,
            'departments' => implode(', ', $departments),
            'era_note' => trans('mcp.prompts.compare_region_eras.'.($to->is_current ? 'current' : 'past'), ['to' => $to->year], $lang),
            'language' => self::languageName($lang),
        ], $lang));
    }

    /** Code INSEE d'une région donnée par son code ou son nom (casse et accents ignorés). */
    private static function regionCode(string $value): ?string
    {
        $value = trim($value);
        if (in_array($value, FrenchDepartments::regionCodes(), true)) {
            return $value;
        }
        $fold = fn (string $s): string => mb_strtolower(str_replace(['-', '’', "'"], ' ', \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s));
        $strip = fn (string $s): string => (string) preg_replace('/\p{Mn}+/u', '', $fold($s));
        foreach (FrenchDepartments::REGIONS as $code => $name) {
            if ($strip($name) === $strip($value)) {
                return (string) $code;
            }
        }

        return null;
    }
}
