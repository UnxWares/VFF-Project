<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\SegmentAudit\AuditPage;
use App\Services\SegmentAudit\NetworkAudits;
use App\Services\SegmentAudit\SegmentAudits;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('segment_audits')]
#[Title('Audit des tronçons de tout le réseau')]
#[Description(<<<'TXT'
Liste des tronçons audités de toutes les lignes à une époque, les plus mal notés d'abord : où les données de VFF sont le moins sûres. Mêmes données, mêmes filtres et mêmes champs que l'API REST GET /api/v1/audits/segments et la requête GraphQL `segmentAudits` ; les preuves complètes d'un tronçon sont dans line_audit. Notes calculées par la passe automatique vff:audit-segments (méthode : docs/AUDIT-TRONCONS.md).
Chaque tronçon (`items`) : `line_slug`, `line_code`, `line_name`, `segment_id`, `status`, `traffic_type`, `trace_source`, `length_m`, `grades` (quatre notes : classification, placement, accuracy, trace ; chacune good = sûr, fair = à revoir, poor = faux ou contredit, unknown = rien pour juger), `worst_grade`, `score` (0 tout sûr à 100 tout faux), `reasons` (codes de raison stables de chaque contrôle, ex. `on_other_line_track`, `median_over_tolerance`), `audited_at`.
Filtres : `era` (2030 par défaut), `grade` (sans `check` : la plus mauvaise note du tronçon ; avec `check` : la note de ce contrôle), `check`, `reason` (code de raison, dans le contrôle `check` s'il est donné), `status`, `department` (code INSEE en majuscules), `region` (code INSEE), `line` (slug ou code à 6 chiffres), `q` (nom de ligne). Tri `sort` : worst (défaut), length, line.
Pagination : `limit` (50 par défaut, 200 au plus) et `offset` ; `total` donne le nombre de tronçons après les filtres, la page suivante commence à offset + limit tant que ce rang est inférieur à `total`. Une réponse trop volumineuse est raccourcie par la fin (`truncated.items` donne le nombre de tronçons retirés) : reprenez alors à offset + limit - truncated.items. Ère sans audit : `total` à 0.
Exemples : {"grade": "poor", "check": "placement", "department": "61", "limit": 20} ; {"reason": "on_other_line_track", "status": "abandoned"} ; {"line": "rfn-405000", "sort": "length"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SegmentAuditsTool extends VffTool
{
    protected function rules(): array
    {
        return NetworkAudits::segmentRules();
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'era' => $schema->integer()->min(1830)->max(2030)->default(2030)
                ->description("Année de l'ère (décennie de 1830 à 2030, 2030 = aujourd'hui)."),
            'grade' => $schema->string()->enum(['good', 'fair', 'poor', 'unknown'])
                ->description('Note à garder : celle du contrôle `check`, ou la plus mauvaise des quatre sans `check`.'),
            'check' => $schema->string()->enum(SegmentAudits::CHECKS)
                ->description('Contrôle dont on filtre la note : classification, placement, accuracy ou trace. Sert à `grade` et à `reason`.'),
            'reason' => $schema->string()->max(60)
                ->description('Code de raison, ex. "on_other_line_track", "median_over_tolerance", "source_contradicts" ; dans le contrôle `check` s\'il est donné, sinon dans l\'un des quatre.'),
            'status' => $schema->string()->enum(SegmentAudits::STATUSES)
                ->description('État du tronçon : in_service, single_track, service_track, construction, abandoned, dismantled ou razed.'),
            'department' => $schema->string()->enum(FrenchDepartments::codes())
                ->description('Code INSEE du département, en majuscules ("61", "2A", "974") : tronçons des lignes qui y passent ; liste : list_departments.'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())
                ->description('Code INSEE de la région ("28" Normandie ; liste avec les noms : list_departments) : tronçons des lignes de ses départements.'),
            'line' => $schema->string()->max(120)
                ->description('Une seule ligne : slug (ex. "rfn-405000", renvoyé par search) ou code SNCF Réseau à 6 chiffres.'),
            'q' => $schema->string()->max(120)
                ->description('Texte cherché dans le nom de la ligne, casse et accents ignorés.'),
            'sort' => $schema->string()->enum(NetworkAudits::SEGMENT_SORTS)->default('worst')
                ->description("Tri : worst (le plus mal noté d'abord), length (le plus long d'abord) ou line (code de ligne)."),
            'limit' => $schema->integer()->min(1)->max(AuditPage::MAX_LIMIT)->default(AuditPage::DEFAULT_LIMIT)
                ->description('Nombre de tronçons au plus (50 par défaut, 200 au plus).'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Rang du premier tronçon rendu, à partir de 0 (page suivante : offset + limit tant que ce rang est inférieur à total).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = array_diff_key($input, array_flip(['limit', 'offset', 'lang']));
        $page = app(NetworkAudits::class)->segments($filters, isset($input['limit']) ? (int) $input['limit'] : null, isset($input['offset']) ? (int) $input['offset'] : null);

        return $this->respond($page, ['vff', 'sncf', 'ign', 'osm'], $lang, self::url('/lines'), ['items']);
    }
}
