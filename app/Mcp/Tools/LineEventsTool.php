<?php

namespace App\Mcp\Tools;

use App\Enums\LineEventType;
use App\Mcp\Support\LineEvents;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_events')]
#[Title("Événements d'une ligne sur une période")]
#[Description(<<<'TXT'
Événements datés d'une ligne, filtrables par période (années incluses) et par type, dans l'ordre chronologique. Chaque événement : `date` ISO AAAA-MM-JJ à lire avec `precision` (day = date exacte ; month = mois connu, jour à 01 ; year = année seule), `when` (date rédigée dans la langue demandée), `type` et `label`, `section` (portion « De - À » si l'événement ne touche pas toute la ligne), `text`, `source` (wikipedia, wikidata, ohm, osm, sncf, jorf, lignes-oubliees, archeoferroviaire, manual…), `source_url` et `provider` (fournisseur dont la licence s'applique, cf. sources_and_licences).
Types : opening, opening_passenger, opening_freight, closure, closure_passenger, closure_freight, reopening, reopening_passenger, reopening_freight, electrification, modernization, dismantling (dépose de la voie), conversion (voie verte, route), concession, concession_end, declassification, heritage_start, heritage_end (train touristique, vélorail), accident, other.
Exemple : {"line": "rfn-610000", "from_year": 1900, "to_year": 1950, "types": ["closure_passenger", "closure"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineEventsTool extends VffTool
{
    /** @return list<string> */
    public static function types(): array
    {
        return array_map(fn (LineEventType $t) => $t->value, LineEventType::cases());
    }

    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'from_year' => ['sometimes', 'integer', 'min:1700', 'max:2100'],
            'to_year' => ['sometimes', 'integer', 'min:1700', 'max:2100', 'gte:from_year'],
            'types' => ['sometimes', 'array', 'min:1', 'max:25'],
            'types.*' => ['string', 'distinct', 'in:'.implode(',', self::types())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-420000") ou code SNCF Réseau à 6 chiffres.'),
            'from_year' => $schema->integer()->min(1700)->max(2100)
                ->description('Première année incluse (ex. 1900). Par défaut : pas de borne.'),
            'to_year' => $schema->integer()->min(1700)->max(2100)
                ->description('Dernière année incluse, supérieure ou égale à from_year (ex. 1950). Par défaut : pas de borne.'),
            'types' => $schema->array()->items($schema->string()->enum(self::types()))->min(1)->max(25)
                ->description('Types d\'événements retenus. Par défaut : tous.'),
            'limit' => $schema->integer()->min(1)->max(500)->default(200)
                ->description('Nombre maximal d\'événements renvoyés, 1 à 500 (200 par défaut) ; `total` donne le nombre trouvé.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $service = app(LineEvents::class);
        $query = $service->query($line->id, $input['from_year'] ?? null, $input['to_year'] ?? null, $input['types'] ?? []);
        $total = (clone $query)->count();
        $events = $query->limit((int) ($input['limit'] ?? 200))->get()->map(fn ($e) => $service->format($e, $lang))->values()->all();

        return $this->respond([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'period' => ['from_year' => $input['from_year'] ?? null, 'to_year' => $input['to_year'] ?? null],
            'total' => $total,
            'events' => $events,
        ], array_column($events, 'provider'), $lang, self::url('/lines/'.$line->slug), ['events']);
    }
}
