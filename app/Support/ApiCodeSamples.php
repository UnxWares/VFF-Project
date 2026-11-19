<?php

namespace App\Support;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

/**
 * Exemples d'appel en JavaScript (`fetch`) et en Python (`requests`) des principaux points d'accès
 * REST, joints à la spécification OpenAPI par l'extension `x-codeSamples` de chaque opération :
 * Scalar (/docs/api) les affiche à côté de l'exemple curl qu'il génère lui-même.
 *
 * Chaque exemple est décrit une fois (SAMPLES : chemin réel, paramètres, lecture de la réponse) et
 * les deux programmes en sont tirés, pour qu'ils appellent la même adresse. Les noms de champs lus
 * sont ceux de la réponse réelle : un test (ApiCodeSamplesTest) les vérifie contre l'API.
 * L'adresse est celle du site public, même depuis un serveur local.
 */
final class ApiCodeSamples implements DocumentTransformer
{
    public const BASE_URL = 'https://vff-project.org/api/v1';

    /**
     * Exemples par identifiant d'opération Scramble.
     *   path  : chemin sous /api/v1, avec des valeurs réelles à la place des paramètres de chemin ;
     *   query : paramètres de requête (chaîne ou entier) ;
     *   js    : lecture de la réponse, `body` étant le JSON décodé ;
     *   py    : même lecture en Python, `body` étant le JSON décodé.
     *
     * @var array<string, array{path: string, query: array<string, string|int>, js: string, py: string}>
     */
    public const SAMPLES = [
        'lines.index' => [
            'path' => '/lines',
            'query' => ['q' => 'brest', 'status' => 'open', 'per_page' => 3],
            'js' => 'for (const line of body.data) console.log(line.slug, line.name, line.state);',
            'py' => "for line in body[\"data\"]:\n    print(line[\"slug\"], line[\"name\"], line[\"state\"])",
        ],
        'lines.show' => [
            'path' => '/lines/rfn-420000',
            'query' => [],
            'js' => 'const line = body.data;'."\n".'console.log(line.name, `${line.key_figures.length_km} km`, line.today.state);',
            'py' => "line = body[\"data\"]\nprint(line[\"name\"], f\"{line['key_figures']['length_km']} km\", line[\"today\"][\"state\"])",
        ],
        'stations.index' => [
            'path' => '/stations',
            'query' => ['q' => 'brest', 'per_page' => 3],
            'js' => 'for (const station of body.data) console.log(station.slug, station.name, station.status);',
            'py' => "for station in body[\"data\"]:\n    print(station[\"slug\"], station[\"name\"], station[\"status\"])",
        ],
        'events.index' => [
            'path' => '/events',
            'query' => ['department' => '29', 'type' => 'closure_passenger', 'from' => '1930', 'to' => '1939', 'per_page' => 5],
            'js' => 'for (const event of body.data) console.log(event.date, event.line.name, event.provenance.name);',
            'py' => "for event in body[\"data\"]:\n    print(event[\"date\"], event[\"line\"][\"name\"], event[\"provenance\"][\"name\"])",
        ],
        'media.index' => [
            'path' => '/media',
            'query' => ['line' => 'rfn-420000', 'per_page' => 3],
            'js' => 'for (const media of body.data) console.log(media.title, "-", media.attribution);',
            'py' => "for media in body[\"data\"]:\n    print(media[\"title\"], \"-\", media[\"attribution\"])",
        ],
        'materials.index' => [
            'path' => '/materials',
            'query' => ['type' => 'locomotive_electrique', 'sort' => '-max_speed_kmh', 'per_page' => 3],
            'js' => 'for (const m of body.data) console.log(m.name, m.max_speed_kmh, "km/h");',
            'py' => "for material in body[\"data\"]:\n    print(material[\"name\"], material[\"max_speed_kmh\"], \"km/h\")",
        ],
        'accidents.index' => [
            'path' => '/accidents',
            'query' => ['kind' => 'derailment', 'min_deaths' => 10, 'sort' => '-deaths', 'per_page' => 3],
            'js' => 'for (const a of body.data) console.log(a.date, a.name.fr, a.deaths, a.review_status);',
            'py' => "for accident in body[\"data\"]:\n    print(accident[\"date\"], accident[\"name\"][\"fr\"], accident[\"deaths\"], accident[\"review_status\"])",
        ],
        'search' => [
            'path' => '/search',
            'query' => ['q' => 'rennes'],
            'js' => 'for (const line of body.lines.slice(0, 5)) console.log("ligne", line.slug, line.name);'."\n".'for (const station of body.stations.slice(0, 5)) console.log("gare", station.slug, station.name);',
            'py' => "for line in body[\"lines\"][:5]:\n    print(\"ligne\", line[\"slug\"], line[\"name\"])\nfor station in body[\"stations\"][:5]:\n    print(\"gare\", station[\"slug\"], station[\"name\"])",
        ],
        'route' => [
            'path' => '/route',
            'query' => ['from' => 'rennes', 'to' => 'brest', 'era' => 1930],
            'js' => 'console.log(`${body.distance_km} km, ${body.duration_h} h (indicatif)`, body.lines.map((l) => l.line_name));',
            'py' => "print(f\"{body['distance_km']} km, {body['duration_h']} h (indicatif)\", [l[\"line_name\"] for l in body[\"lines\"]])",
        ],
        'route.compare' => [
            'path' => '/route/compare',
            'query' => ['from' => 'angouleme', 'to' => 'limoges-benedictins', 'era' => 1930, 'compare' => 2026],
            'js' => 'const { earlier, later, differences } = body;'."\n".'console.log(differences.outcome, earlier.route?.distance_km, later.route?.distance_km, differences.lines_only_earlier.map((l) => [l.line_name, l.other_era]));',
            'py' => "earlier, later, diff = body[\"earlier\"], body[\"later\"], body[\"differences\"]\nprint(diff[\"outcome\"], (earlier[\"route\"] or {}).get(\"distance_km\"), (later[\"route\"] or {}).get(\"distance_km\"))\nfor line in diff[\"lines_only_earlier\"]:\n    print(line[\"line_name\"], line[\"other_era\"])",
        ],
        'eras.compare' => [
            'path' => '/eras/1930/compare/1980',
            'query' => [],
            'js' => 'console.log(body.data.changes, body.data.open_km);',
            'py' => 'print(body["data"]["changes"], body["data"]["open_km"])',
        ],
    ];

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $id = $operation->operationId;
                if ($id !== null && isset(self::SAMPLES[$id])) {
                    $operation->setExtensionProperty('codeSamples', self::forOperation($id));
                }
            }
        }
    }

    /**
     * Entrées `x-codeSamples` d'une opération : `lang` (langage), `label` (libellé du client), `source`.
     *
     * @return list<array{lang: string, label: string, source: string}>
     */
    public static function forOperation(string $operationId, string $baseUrl = self::BASE_URL): array
    {
        return [
            ['lang' => 'JavaScript', 'label' => 'JavaScript (fetch)', 'source' => self::javascript($operationId, $baseUrl)],
            ['lang' => 'Python', 'label' => 'Python (requests)', 'source' => self::python($operationId, $baseUrl)],
        ];
    }

    /** Programme JavaScript (module ou Node.js 18 et plus : `fetch` est natif, `await` au niveau du module). */
    public static function javascript(string $operationId, string $baseUrl = self::BASE_URL): string
    {
        $sample = self::SAMPLES[$operationId];
        $url = $baseUrl.$sample['path'];
        $fetch = "const response = await fetch(url);\nif (!response.ok) throw new Error(`HTTP \${response.status}`);\nconst body = await response.json();\n\n".$sample['js']."\n";
        if ($sample['query'] === []) {
            return "const url = \"{$url}\";\n".$fetch;
        }
        $params = implode(",\n", array_map(
            fn (string $key, string|int $value): string => "    {$key}: ".json_encode((string) $value, JSON_UNESCAPED_SLASHES),
            array_keys($sample['query']), $sample['query'],
        ));

        return "const url = new URL(\"{$url}\");\nurl.search = new URLSearchParams({\n{$params},\n});\n\n".$fetch;
    }

    /** Programme Python 3 (bibliothèque `requests`). */
    public static function python(string $operationId, string $baseUrl = self::BASE_URL): string
    {
        $sample = self::SAMPLES[$operationId];
        $url = $baseUrl.$sample['path'];
        $params = $sample['query'] === [] ? '' : "    params={\n".implode('', array_map(
            fn (string $key, string|int $value): string => "        \"{$key}\": ".(is_int($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES)).",\n",
            array_keys($sample['query']), $sample['query'],
        ))."    },\n";

        return "import requests\n\nresponse = requests.get(\n    \"{$url}\",\n{$params}    timeout=30,\n)\nresponse.raise_for_status()\nbody = response.json()\n\n".$sample['py']."\n";
    }
}
