<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\SegmentAudit\AuditPage;
use App\Services\SegmentAudit\NetworkAudits;
use App\Services\StationAudit\DateGrade;
use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\StationAuditQuery;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_audits')]
#[Title('Audit des gares de tout le réseau')]
#[Description(<<<'TXT'
Liste des gares auditées, les plus éloignées de leurs sources d'abord : où la position ou les dates d'une gare sont le moins sûres. Mêmes données, mêmes filtres et mêmes champs que l'API REST GET /api/v1/audits/stations et la requête GraphQL `stationAudits` ; le détail d'une gare (positions, dates candidates de Wikidata) est dans get_station, champ `audit`. Passe automatique vff:audit-stations (méthode : docs/AUDIT-GARES.md), une par gare, sans ère.
Chaque gare (`items`) : `station_id`, `slug`, `name`, `department`, `region` (nom), `status`, `position_source`, `placement_grade` (agree : deux sources indépendantes à moins de 20 m ; close : la plus proche entre 20 et 100 m ; isolated : une seule famille de sources ; disagree : une source précise à plus de 100 m ; none : aucune source), `families`, écarts en mètres `nearest_agreeing_m`, `farthest_precise_m`, `track_offset_m`, `opening_grade` et `closure_grade` (sourced, estimated, unknown, not_applicable), `passenger` et `freight` (état : open, closed, unknown), `checks` (codes des contrôles croisés en échec), `sources` (familles de sources). Aucune coordonnée.
Filtres : `grade` (note de placement), `check` (contrôle en échec), `opening`, `closure`, `department` (code INSEE en majuscules), `region` (code INSEE), `line` (gares desservies par cette ligne : slug ou code à 6 chiffres), `q` (nom de gare). Tri `sort` : spread (défaut, écart à la source précise la plus éloignée), track, name.
Pagination : `limit` (50 par défaut, 200 au plus) et `offset` ; `total` donne le nombre de gares après les filtres, la page suivante commence à offset + limit tant que ce rang est inférieur à `total`. Une réponse trop volumineuse est raccourcie par la fin (`truncated.items` donne le nombre de gares retirées) : reprenez alors à offset + limit - truncated.items.
Exemples : {"grade": "disagree", "region": "28", "limit": 20} ; {"check": "wikidata_differs", "department": "61"} ; {"line": "rfn-405000", "sort": "name"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationAuditsTool extends VffTool
{
    protected function rules(): array
    {
        return NetworkAudits::stationRules();
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'grade' => $schema->string()->enum(PlacementGrade::values())
                ->description('Note de placement : agree, close, isolated, disagree ou none. Par défaut : toutes.'),
            'check' => $schema->string()->enum(StationAuditQuery::CHECKS)
                ->description('Contrôle croisé en échec, ex. far_from_track, wikidata_differs, closure_before_opening, outlier_source.'),
            'opening' => $schema->string()->enum(DateGrade::values())
                ->description("Note de la date d'ouverture : sourced, estimated, unknown ou not_applicable."),
            'closure' => $schema->string()->enum(DateGrade::values())
                ->description('Note de la date de fermeture : sourced, estimated, unknown ou not_applicable (gare ouverte).'),
            'department' => $schema->string()->enum(FrenchDepartments::codes())
                ->description('Code INSEE du département de la gare, en majuscules ("61", "2A", "974") ; liste : list_departments.'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())
                ->description('Code INSEE de la région de la gare ("28" Normandie ; liste avec les noms : list_departments).'),
            'line' => $schema->string()->max(120)
                ->description('Gares desservies par cette ligne : slug (ex. "rfn-405000", renvoyé par search) ou code SNCF Réseau à 6 chiffres.'),
            'q' => $schema->string()->max(120)
                ->description('Texte cherché dans le nom de la gare, casse et accents ignorés.'),
            'sort' => $schema->string()->enum(StationAuditQuery::SORTS)->default('spread')
                ->description("Tri : spread (écart à la source précise la plus éloignée d'abord), track (écart à la voie) ou name."),
            'limit' => $schema->integer()->min(1)->max(AuditPage::MAX_LIMIT)->default(AuditPage::DEFAULT_LIMIT)
                ->description('Nombre de gares au plus (50 par défaut, 200 au plus).'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Rang de la première gare rendue, à partir de 0 (page suivante : offset + limit tant que ce rang est inférieur à total).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = array_diff_key($input, array_flip(['limit', 'offset', 'lang']));
        $page = app(NetworkAudits::class)->stations($filters, isset($input['limit']) ? (int) $input['limit'] : null, isset($input['offset']) ? (int) $input['offset'] : null);

        return $this->respond($page, ['vff', 'sncf', 'osm', 'ign', 'wikidata'], $lang, self::url('/stations'), ['items']);
    }
}
