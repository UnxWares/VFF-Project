<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\SegmentAudit\AuditPage;
use App\Services\SegmentAudit\SegmentAudits;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_audit')]
#[Title("Audit des tronçons d'une ligne")]
#[Description(<<<'TXT'
Audit des tronçons d'une ligne à une époque, même contenu que l'API REST GET /api/v1/lines/{line}/audit : quatre notes par tronçon, avec leurs preuves, calculées par la passe automatique de vérification de VFF (vff:audit-segments).
Notes : good (sûr), fair (à revoir), poor (faux ou contredit), unknown (rien pour juger). `classification` = état du tronçon (status, traffic_type) comparé aux sources datées (Wikipédia, lignes-oubliées, OHM, Wikidata ; pour 2030 aussi SNCF Réseau, BD TOPO, OSM) ; `placement` = écart à la voie réelle d'une autre source (médiane et 95e centile en mètres, cible 1 m, tolérance 5 m) ; `accuracy` = allers-retours, coudes, écarts aux jonctions, trous, gares loin de la ligne ; `trace` = source du dessin et sa précision connue.
`grades`, `worst_grade`, `score` (0 tout sûr à 100 tout faux, pondéré par la longueur), `shares` (part de la longueur par note), `line_checks` (longueur comparée à la longueur officielle, ordre des gares), `segments` (le plus mal noté d'abord, une page de 50 par défaut et 200 au plus : `limit`, `offset`, et `segments_total` après le filtre `grade` ; chacun avec `evidence` et ses codes `reasons`).
Codes de raisons récents : `on_other_line_track` (tronçon posé sur la voie d'une autre ligne alors que sa propre voie passe à 8 à 60 m), `shared_track` (tronc commun ou embranchement sur la voie de départ, sans voie propre : sûr), `confirmed_by_precise_source` (tracé confirmé par une voie BD TOPO ou OSM indépendante), `observation_outvoted` (une seule observation actuelle contre deux d'accord) et `continuous_service` (ère passée : ligne sans fin datée, tronçon sûr aujourd'hui).
Utile pour savoir quelle confiance accorder à l'état ou au tracé d'une ligne à une époque. Ligne pas encore auditée à cette époque : erreur `audit_not_computed`.
Exemple : {"line": "rfn-405000", "era": 2030, "grade": "poor"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineAuditTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'era' => ['sometimes', 'integer', 'min:1830', 'max:2030'],
            'grade' => ['sometimes', 'string', 'in:good,fair,poor,unknown'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.AuditPage::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-405000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "405000").'),
            'era' => $schema->integer()->min(1830)->max(2030)->default(2030)
                ->description("Année de l'ère (décennie de 1830 à 2030, 2030 = aujourd'hui)."),
            'grade' => $schema->string()->enum(['good', 'fair', 'poor', 'unknown'])
                ->description('Ne garder que les tronçons dont la plus mauvaise note vaut celle-ci.'),
            'limit' => $schema->integer()->min(1)->max(AuditPage::MAX_LIMIT)->default(AuditPage::DEFAULT_LIMIT)
                ->description('Nombre de tronçons au plus (50 par défaut, 200 au plus), les plus mal notés d’abord.'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Rang du premier tronçon rendu, à partir de 0 (page suivante : offset + limit tant que ce rang est inférieur à segments_total).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $year = (int) ($input['era'] ?? 2030);
        $audit = app(SegmentAudits::class)->forLine($line, $year);
        if ($audit === null) {
            return $this->fail('audit_not_computed', ['line' => $line->name, 'year' => $year]);
        }
        $audit = AuditPage::apply($audit, $input['grade'] ?? null, isset($input['limit']) ? (int) $input['limit'] : null, isset($input['offset']) ? (int) $input['offset'] : null);

        return $this->respond($audit, ['vff', 'sncf', 'ign', 'osm'], $lang, self::url('/lines/'.$line->slug), ['segments']);
    }
}
