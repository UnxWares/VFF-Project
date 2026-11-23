<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\CatalogSearch;
use App\Services\GlossarySearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search')]
#[Title('Rechercher une ligne, une gare, un matériel, un accident ou un terme du glossaire')]
#[Description(<<<'TXT'
Recherche par nom dans l'encyclopédie VFF (lignes de chemin de fer françaises actuelles et disparues, gares, matériels roulants, accidents).
Point de départ obligé : renvoie les identifiants (`slug`) attendus par les autres outils (get_line, line_events, get_station, historical_route).
Lignes : par nom (« Paris à Brest ») ou code SNCF Réseau à 6 chiffres (« 420000 », espaces tolérés) ; les lignes en service passent avant les lignes fermées.
Gares : par nom, avec ville, code de département et coordonnées WGS 84 (lat, lng en degrés décimaux) pour distinguer les homonymes.
Accidents : par nom (français, anglais ou allemand), commune ou date (« 1891 », « 07/2013 », « 12/07/2013 », « 2013-07-12 »), ou lieu et année (« Lagny 1933 ») ; seuls les accidents publiés sont renvoyés (identifiant pour `get_accident`).
Glossaire (`glossary`) : termes du vocabulaire ferroviaire par nom, sigle ou développé, en français, anglais ou allemand (« TVM 430 », « KVB », « block automatique lumineux », « overhead line »), avec l'adresse de leur définition.
Mots génériques facultatifs (« gare », « station », « Bahnhof », « ligne », « line », « Strecke », « accident », « UIC ») ; noms allemands et anglais des villes (« Straßburg », « Mülhausen ») ; ancien slug d'une gare fusionnée ou d'une ligne renommée : la fiche qui l'a remplacé, en premier.
Casse, accents, tirets, apostrophes et abréviations (St, Ste, N.-D., s/, lès) indifférents, petits mots facultatifs, mots dans n'importe quel ordre (« st hilaire chaleons » trouve « St-Hilaire-de-Chaléons »). Résultats triés par pertinence : nom exact, début du nom, début d'un mot, tous les mots, puis le reste ; quand moins de 3 fiches correspondent, noms approchés (fautes de frappe) marqués `approximate`.
Exemple : {"query": "brest"} ; {"query": "420000", "kinds": ["lines"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchTool extends VffTool
{
    public const KINDS = ['lines', 'stations', 'materials', 'accidents', 'glossary'];

    protected function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'kinds' => ['sometimes', 'array', 'min:1', 'max:5'],
            'kinds.*' => ['string', 'distinct', 'in:'.implode(',', self::KINDS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(100)->required()
                ->description('Texte recherché, 2 à 100 caractères : nom de ligne, code de ligne à 6 chiffres, nom de gare, de matériel ou d\'accident, commune ou date d\'accident. Exemple : "Limoges".'),
            'kinds' => $schema->array()->items($schema->string()->enum(self::KINDS))->min(1)->max(5)
                ->description('Catégories cherchées parmi lines, stations, materials, accidents, glossary. Par défaut : les cinq.'),
            'limit' => $schema->integer()->min(1)->max(20)->default(8)
                ->description('Nombre maximal de résultats par catégorie, 1 à 20 (8 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $kinds = $input['kinds'] ?? self::KINDS;
        $limit = (int) ($input['limit'] ?? 8);
        $found = app(CatalogSearch::class)->search(
            $input['query'],
            in_array('lines', $kinds, true) ? $limit : 0,
            in_array('stations', $kinds, true) ? $limit : 0,
            in_array('materials', $kinds, true) ? $limit : 0,
            in_array('accidents', $kinds, true) ? $limit : 0,
        );
        $link = fn (string $prefix) => fn (array $r) => array_diff_key($r, ['type' => true]) + ['vff_url' => self::url("/{$prefix}/{$r['slug']}")];

        return $this->respond([
            'query' => $input['query'],
            'lines' => array_map($link('lines'), $found['lines']),
            'stations' => array_map($link('stations'), $found['stations']),
            'materials' => array_map($link('materials'), $found['materials']),
            // Nom dans la langue demandée (`names` porte les trois, `name` reste le français).
            'accidents' => array_map(
                fn (array $r) => ['name' => $r['names'][$lang] ?? $r['name']] + $link('accidents')($r),
                $found['accidents'],
            ),
            'glossary' => in_array('glossary', $kinds, true) ? array_map(
                fn (array $r) => ['slug' => $r['slug'], 'name' => $r['names'][$lang] ?? $r['name'], 'abbr' => $r['abbr'], 'vff_url' => self::url('/glossary#'.$r['slug'])],
                app(GlossarySearch::class)->search($input['query'], $limit),
            ) : [],
        ], [], $lang);
    }
}
