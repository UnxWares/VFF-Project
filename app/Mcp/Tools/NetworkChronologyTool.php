<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\NetworkChronology;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('network_chronology')]
#[Title('Chronologie du rail en France')]
#[Description(<<<'TXT'
Ce que l'encyclopédie sait du réseau ferré français année par année depuis 1827 : ouvertures, réouvertures, fermetures, électrifications, mises en service de signalisation, séries de matériel, accidents d'au moins 5 morts et jalons nationaux. Mêmes données que l'API REST GET /api/v1/chronology et la page /chronology (service NetworkChronology, calculé par commande puis gardé en cache : `computed_at`).
Trois usages. Sans `year` ni `decade` : le panorama (une ligne par décennie : nombre de faits). Avec `decade` (multiple de 10, 1820 à la décennie en cours) : les comptes de chaque année de la décennie, sans les faits. Avec `year` : les faits détaillés de l'année, paginés (`limit`, `offset`).
Filtres : `types` (milestone, opening, reopening, closure, electrification, signalling, material, accident) et `region` (code INSEE de la région, portion située par ses gares sinon toute la ligne ; liste : outil list_departments ; jalons et matériel n'ont pas de région).
Comptes : `shown` (faits détaillés, avec au moins une source publiable), `hidden` (faits connus seulement par des sources dont les faits ne sont pas publiés : comptés, jamais détaillés ni datés) et `km` (longueur connue des faits détaillés, sans compter deux fois un tronçon). Un fait rapporté par plusieurs sources n'est compté qu'une fois.
Chaque fait : `id`, `kind`, `traffic` (fermeture ou ouverture : passenger, freight, total ou null), `date` ISO à lire avec `precision` (day : les sources qui le datent au jour s'accordent ; month ; year : année seule, ne pas inventer un jour ni un mois), `when` (date rédigée), `title`, `text`, `line` (slug, name), `sections`, `km`, `deaths`, `units`, `path`, `regions` et `sources` (provider, name, url). Reprenez les accidents avec sobriété et respect pour les victimes.
Limites : les ouvertures et fermetures sont celles des lignes connues de la base ; l'absence d'un fait ne prouve pas qu'il n'a pas eu lieu.
Exemples : {} ; {"decade": 1930, "types": ["closure"]} ; {"year": 1938, "region": "53", "limit": 20}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class NetworkChronologyTool extends VffTool
{
    private const MAX_LIMIT = 100;

    protected function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'min:'.NetworkChronology::FIRST_YEAR, 'max:'.now()->year],
            'decade' => ['sometimes', 'integer', 'multiple_of:10', 'min:1820', 'max:'.now()->year],
            'types' => ['sometimes', 'array', 'max:10'],
            'types.*' => ['string', 'in:'.implode(',', NetworkChronology::KINDS)],
            'region' => ['sometimes', 'string', 'in:'.implode(',', FrenchDepartments::regionCodes())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->min(NetworkChronology::FIRST_YEAR)->description('Année (1827 à aujourd\'hui) : les faits détaillés de l\'année. Exclusif avec `decade`.'),
            'decade' => $schema->integer()->min(1820)->description('Décennie, multiple de 10 (1930 : de 1930 à 1939) : les comptes de chaque année. Exclusif avec `year`.'),
            'types' => $schema->array()->items($schema->string()->enum(NetworkChronology::KINDS))->description('Types de faits retenus. Par défaut : tous.'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())->description('Code INSEE de la région ("53" Bretagne). Par défaut : toute la France.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(40)->description('Avec `year` : nombre de faits renvoyés (40 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Avec `year` : nombre de faits à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        if (isset($input['year'], $input['decade'])) {
            return $this->fail('invalid_period');
        }
        $types = array_values(array_intersect(NetworkChronology::KINDS, (array) ($input['types'] ?? [])));
        $region = isset($input['region']) ? (string) $input['region'] : null;
        $chronology = app(NetworkChronology::class);
        $overview = $chronology->overview($types, $region);
        $head = ['computed_at' => $overview['computed_at'], 'first_year' => $overview['first_year'], 'last_year' => $overview['last_year'],
            'filters' => $overview['filters']];

        if (isset($input['year'])) {
            return $this->year((int) $input['year'], $types, $region, $head, $input, $lang);
        }
        if (isset($input['decade'])) {
            $decade = (int) $input['decade'];
            [$first, $last] = NetworkChronology::decadeBounds($overview['last_year']);
            if ($decade < $first || $decade > $last) {
                return $this->fail('invalid_period');
            }
            $years = array_map(fn (array $y): array => ['year' => $y['year'], 'counts' => $y['counts']],
                $chronology->decade($decade, $types, $region, $lang)['years']);

            return $this->respond($head + ['decade' => $decade, 'years' => $years], [], $lang, self::url('/chronology?decade='.$decade), ['years']);
        }

        return $this->respond($head + ['decades' => $overview['decades']], [], $lang, self::url('/chronology'), ['decades']);
    }

    /**
     * @param  list<string>  $types
     * @param  array<string, mixed>  $head
     * @param  array<string, mixed>  $input
     */
    private function year(int $year, array $types, ?string $region, array $head, array $input, string $lang): Response
    {
        $decade = intdiv($year, 10) * 10;
        $found = collect(app(NetworkChronology::class)->decade($decade, $types, $region, $lang)['years'])->firstWhere('year', $year)
            ?? ['counts' => [], 'facts' => []];
        $facts = $found['facts'];
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 40);
        $page = array_slice($facts, $offset, $limit);
        $providers = array_merge([], ...array_map(fn (array $f): array => array_column($f['sources'], 'provider'), $page));

        return $this->respond(
            $head + ['year' => $year, 'counts' => $found['counts'], 'total' => count($facts), 'offset' => $offset, 'has_more' => $offset + $limit < count($facts), 'facts' => $page],
            $providers,
            $lang,
            self::url('/chronology?decade='.$decade),
            ['facts'],
        );
    }
}
