<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\RailProjects;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('rail_projects')]
#[Title('Projets ferroviaires et leurs étapes')]
#[Description(<<<'TXT'
Projets ferroviaires français pas encore en service : réouvertures au service voyageurs, lignes nouvelles, LGV, RER métropolitains, électrifications annoncées. Mêmes données que l'API REST GET /api/v1/projects et la page /projects (service RailProjects).
Chaque projet : `id`, `name`, `kind` (reopening réouverture, new_line ligne nouvelle, lgv, rer, electrification), `state` (dernière étape franchie : study étude, debate débat public ou concertation, dup déclaration d'utilité publique, funding financement acté, works travaux ; suspended = suspendu, reporté sans date ou abandonné), `regions`, `porteurs` (nom et rôle : maître d'ouvrage, financeur, autorité organisatrice, association), `lines` (fiches de ligne VFF : slug, name ; vide quand la ligne n'a pas de fiche), `summary` (une ou deux phrases en français rédigées par VFF), `stages` (chaque étape `kind` study, debate, dup, funding, works, service ou commissioning, `date` AAAA, AAAA-MM ou AAAA-MM-JJ à la précision de la source, `precision` year, month ou day, `done`, `note`, `source` : label, url, published), `next_stage`, `planned_commissioning` (mise en service prévue, null quand aucune source ne la donne) et `checked` (date de la dernière vérification des sources).
Règle de lecture : une étape `done` false est une date PRÉVUE, jamais certaine. Dites toujours « prévue en 2028 selon <source.label> », jamais « ouvrira en 2028 ». `service` = service voyageurs déjà ouvert sur une partie du projet, ou ouvert puis arrêté. Un projet mis en service sort de la liste : son histoire est alors dans get_line et line_events.
Filtres : `state`, `kind`, `region` (nom exact, tel que listé dans `regions` de la réponse), `line` (slug ou code à 6 chiffres d'une ligne : les projets qui la concernent ; un ancien slug répond sous le slug actuel, signalé par `redirected`). Pagination : `limit` et `offset`, `total` donne le nombre de projets qui correspondent.
Exemples : {} ; {"kind": "reopening", "state": "works"} ; {"region": "Occitanie", "limit": 5} ; {"line": "rfn-610000"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class RailProjectsTool extends VffTool
{
    private const MAX_LIMIT = 50;

    protected function rules(): array
    {
        return [
            'state' => ['sometimes', 'string', 'in:'.implode(',', RailProjects::STATES)],
            'kind' => ['sometimes', 'string', 'in:'.implode(',', RailProjects::KINDS)],
            'region' => ['sometimes', 'string', 'max:100'],
            'line' => ['sometimes', 'string', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->enum(RailProjects::STATES)->description('État du projet (dernière étape franchie, ou suspended). Par défaut : tous.'),
            'kind' => $schema->string()->enum(RailProjects::KINDS)->description('Type de projet. Par défaut : tous.'),
            'region' => $schema->string()->max(100)->description('Nom exact de la région (ex. "Occitanie"), tel que listé dans `regions` de la réponse. Par défaut : toutes.'),
            'line' => $schema->string()->min(1)->max(100)->description('Ligne concernée : slug (ex. "rfn-610000", renvoyé par search) ou code SNCF Réseau à 6 chiffres. Par défaut : tous les projets.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(20)->description('Nombre de projets renvoyés, 1 à 50 (20 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Nombre de projets à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $slug = null;
        if (isset($input['line'])) {
            $line = $this->line($input['line']);
            if ($line === null) {
                return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
            }
            $slug = $line->slug;
        }
        $data = app(RailProjects::class)->get();
        $state = $input['state'] ?? null;
        $kind = $input['kind'] ?? null;
        $region = $input['region'] ?? null;
        $matching = array_values(array_filter($data['projects'], fn (array $p): bool => ($state === null || $p['state'] === $state)
            && ($kind === null || $p['kind'] === $kind)
            && ($region === null || in_array($region, $p['regions'], true))
            && ($slug === null || in_array($slug, array_column($p['lines'], 'slug'), true))));
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 20);
        $page = array_slice($matching, $offset, $limit);

        // Fournisseurs des sources citées par les étapes renvoyées (domaine du lien : SNCF, Journal officiel, régions…).
        $providers = [];
        foreach ($page as $project) {
            foreach ($project['stages'] as $stage) {
                $providers[] = $this->attribution->providerForUrl($stage['source']['url'] ?? null);
            }
        }

        return $this->respond([
            'filters' => ['state' => $state, 'kind' => $kind, 'region' => $region, 'line' => $slug],
            'total' => count($matching),
            'offset' => $offset,
            'has_more' => $offset + $limit < count($matching),
            'planned_dates_note' => (string) trans('mcp.projects.planned_note', [], $lang),
            'projects' => $page,
            'regions' => $data['regions'],
            'kinds' => $data['kinds'],
            'states' => $data['states'],
            'checked' => $data['checked'],
        ], $providers, $lang, self::url('/projects'), ['projects']);
    }
}
