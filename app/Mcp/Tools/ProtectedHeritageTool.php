<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Heritage\HeritageIndex;
use App\Services\Heritage\MerimeeSource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('protected_heritage')]
#[Title('Patrimoine ferroviaire protégé de tout le réseau')]
#[Description(<<<'TXT'
Bâtiments voyageurs, ouvrages de gare et ouvrages d'art (viaducs, ponts, tunnels) de tout le réseau classés ou inscrits aux monuments historiques ou labellisés « Architecture contemporaine remarquable ». Mêmes données et mêmes filtres que l'API REST GET /api/v1/heritage et la page /heritage (service HeritageIndex). Les objets seulement recensés à l'Inventaire général n'y figurent pas : voir `station_heritage` pour le détail d'une gare, `line_structures` pour les ouvrages d'une ligne.
Chaque `items[]` : `id`, `object` (building bâtiment voyageurs, works ouvrage de gare, viaduct, bridge, tunnel), `name`, `title` (titre de la notice Mérimée, nul sans notice), `protection` (classified classé, registered inscrit, acr label), `protection_date` (date de l'arrêté, nulle pour un label ou quand la source l'ignore), `scope` (partial : seules certaines parties sont protégées), `architects`, `built` (`from`, `to`, `period` : siècle quand Mérimée ne donne pas d'années, `year` : année connue ou estimée au milieu de la période, à dire comme une estimation), `era`, `style`, `commune`, `department`, `region` (pour un ouvrage d'art, la commune est celle dont le centre est le plus proche), `reference` (`id`, `url` de la notice Mérimée ou de l'élément Wikidata), `image` (image libre : `url`, `source_url`, `license`, `author`, nulle sinon), `station` ou `line` (fiche VFF), `lat`, `lng`.
Filtres : `protection`, `object`, `era`, `style`, `architect` et `region` (noms exacts, tels que listés dans `facets`), `department` (code INSEE). Tri `sort` : name (défaut), protection_date (la plus récente d'abord) ou built (la plus ancienne d'abord), dates inconnues en dernier. Pagination : `limit` et `offset` ; `total` = objets qui correspondent, `facets` = valeurs de chaque filtre sur l'ensemble avec leur nombre (`facets.architects` limité aux 40 plus fréquents ; `architects_total` donne leur nombre).
Sources et licences (bloc `attribution`, mention `heritage_attribution`) : base Mérimée de la Plateforme ouverte du patrimoine (ministère de la Culture, Licence Ouverte 2.0, mention « Ministère de la Culture, base Mérimée (POP) »), Wikidata (CC0), IGN BD TOPO pour les ouvrages d'art, images de Wikimedia Commons sous la licence de chaque fichier : affichez l'auteur et la licence avec l'image.
Exemples : {} ; {"protection": "classified", "object": "viaduct"} ; {"region": "Nouvelle-Aquitaine", "sort": "built"} ; {"architect": "Victor Laloux"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ProtectedHeritageTool extends VffTool
{
    private const MAX_LIMIT = 50;

    /** Architectes listés dans `facets.architects` : les plus fréquents. */
    private const ARCHITECTS_SHOWN = 40;

    protected function rules(): array
    {
        return [
            'protection' => ['sometimes', 'string', 'in:'.implode(',', HeritageIndex::PROTECTIONS)],
            'object' => ['sometimes', 'string', 'in:'.implode(',', HeritageIndex::OBJECTS)],
            'era' => ['sometimes', 'string', 'in:'.implode(',', HeritageIndex::ERAS)],
            'style' => ['sometimes', 'string', 'max:120'],
            'architect' => ['sometimes', 'string', 'max:255'],
            'region' => ['sometimes', 'string', 'max:100'],
            'department' => ['sometimes', 'string', 'regex:/^([0-9]{2,3}|2[ABab])$/'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', HeritageIndex::SORTS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'protection' => $schema->string()->enum(HeritageIndex::PROTECTIONS)->description('Protection : classified (classé), registered (inscrit) ou acr (label Architecture contemporaine remarquable). Par défaut : toutes.'),
            'object' => $schema->string()->enum(HeritageIndex::OBJECTS)->description('Objet : building (bâtiment voyageurs), works (ouvrage de gare), viaduct, bridge ou tunnel. Par défaut : tous.'),
            'era' => $schema->string()->enum(HeritageIndex::ERAS)->description('Époque de construction (unknown : aucune date ni siècle connus). Par défaut : toutes.'),
            'style' => $schema->string()->max(120)->description('Style, nom exact tel que listé dans `facets.styles` (ex. "Art déco").'),
            'architect' => $schema->string()->max(255)->description('Architecte, nom exact tel que listé dans `facets.architects` (ex. "Victor Laloux").'),
            'region' => $schema->string()->max(100)->description('Région, nom exact tel que listé dans `facets.regions` (ex. "Occitanie").'),
            'department' => $schema->string()->description('Département : code INSEE (ex. "87", "2A", "971"), voir list_departments.'),
            'sort' => $schema->string()->enum(HeritageIndex::SORTS)->default('name')->description('Tri : name (défaut), protection_date (la plus récente d\'abord) ou built (la plus ancienne d\'abord) ; dates inconnues en dernier.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(20)->description('Nombre d\'objets renvoyés, 1 à 50 (20 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Nombre d\'objets à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $data = app(HeritageIndex::class)->get();
        $filters = array_intersect_key($input, array_flip(['protection', 'object', 'era', 'style', 'architect', 'region']));
        $sort = $input['sort'] ?? 'name';
        $items = HeritageIndex::filter($data['items'], $filters, $sort);
        if (isset($input['department'])) {
            $department = strtoupper($input['department']);
            $items = array_values(array_filter($items, fn (array $i): bool => $i['department'] === $department));
            $filters['department'] = $department;
        }
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 20);
        $page = array_slice($items, $offset, $limit);

        $facets = $data['facets'];
        $architectsTotal = count($facets['architects']);
        $architects = $facets['architects'];
        usort($architects, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $facets['architects'] = array_slice($architects, 0, self::ARCHITECTS_SHOWN);

        $providers = ['wikidata'];
        foreach ($page as $item) {
            if (str_starts_with((string) ($item['reference']['url'] ?? ''), MerimeeSource::NOTICE_URL)) {
                $providers[] = 'pop';
            }
            if (in_array($item['object'], ['viaduct', 'bridge', 'tunnel'], true)) {
                $providers[] = 'ign';
            }
            if ($item['image'] !== null) {
                $providers[] = 'commons';
            }
        }

        return $this->respond([
            'filters' => $filters + ['sort' => $sort],
            'total' => count($items),
            'total_all' => count($data['items']),
            'offset' => $offset,
            'count' => count($page),
            'has_more' => $offset + $limit < count($items),
            'items' => $page,
            'facets' => $facets,
            'architects_total' => $architectsTotal,
            'heritage_attribution' => $data['attribution'],
        ], $providers, $lang, self::url('/heritage'), ['items', 'facets.architects', 'facets.styles']);
    }
}
