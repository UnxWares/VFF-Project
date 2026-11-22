<?php

namespace Tests\Feature\Web;

use App\Enums\ErrorReportStatus;
use App\Enums\ErrorReportSubject;
use App\Models\Contribution;
use App\Models\ErrorReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Contrôle d'accès de CHAQUE route de /validate (modération, comptes, signalements, graphe, audits),
 * déduite de la table des routes : une route ajoutée plus tard est contrôlée sans test à écrire.
 *
 *   - un visiteur et un contributeur reçoivent 403 sur toutes ;
 *   - un validateur reçoit 403 sur toutes les routes réservées aux administrateurs (comptes, retrait
 *     d'une contribution, graphe, audits) ;
 *   - un administrateur n'est refusé nulle part.
 *
 * Les modèles liés à l'adresse existent vraiment : un 404 de liaison arriverait avant l'autorisation
 * et masquerait une route sans contrôle.
 */
class ValidationRoutesAccessTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    /** Routes de /validate ouvertes aux validateurs (les autres sont réservées aux administrateurs). */
    private const REVIEWER_ROUTES = [
        'GET validate', 'POST validate/{id}/approve', 'POST validate/{id}/reject',
        'GET validate/reports', 'GET validate/reports/{report}', 'PATCH validate/reports/{report}',
    ];

    /** @return list<RouteDefinition> */
    private function validationRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $r) => $r->uri() === 'validate' || str_starts_with($r->uri(), 'validate/'),
        ));
    }

    private function signature(RouteDefinition $route): string
    {
        $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];

        return $method.' '.$route->uri();
    }

    private function uri(RouteDefinition $route, Contribution $contribution, ErrorReport $report, int $userId): string
    {
        $uri = (string) preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($route, $contribution, $report, $userId): string {
            return match ($m[1]) {
                'id', 'contribution' => (string) $contribution->id,
                'report' => (string) $report->id,
                'user' => (string) $userId,
                'check' => explode('|', ltrim((string) ($route->wheres['check'] ?? 'x'), '(?:'))[0],
                'layer' => explode('|', trim((string) ($route->wheres['layer'] ?? 'x'), '()?:'))[0],
                'kind' => explode('|', trim((string) ($route->wheres['kind'] ?? 'x'), '()?:'))[0],
                'year' => '2030', 'z' => '5', 'x' => '1', 'y' => '1',
                default => '1',
            };
        }, $route->uri());

        return '/'.$uri;
    }

    private function statusFor(string $method, string $uri, User $user): int
    {
        return $this->actingAs($user)->call($method, $uri)->getStatusCode();
    }

    public function test_every_validation_route_enforces_its_role(): void
    {
        $routes = $this->validationRoutes();
        $this->assertGreaterThan(15, count($routes), 'les routes de /validate doivent être trouvées');

        $contribution = Contribution::factory()->create();
        $report = ErrorReport::create([
            'subject_type' => ErrorReportSubject::Line,
            'subject_id' => 1,
            'subject_slug' => 'ligne',
            'subject_label' => 'Ligne',
            'description' => 'Une description assez longue pour passer les règles.',
            'locale' => 'fr',
            'status' => ErrorReportStatus::Open,
        ]);
        $target = $this->member('contributor');
        $visitor = $this->member('visitor');
        $contributor = $this->member('contributor');
        $validator = $this->member('validator');
        $admin = $this->member('admin');

        $failures = [];
        foreach ($routes as $route) {
            $signature = $this->signature($route);
            $method = explode(' ', $signature)[0];
            $uri = $this->uri($route, $contribution, $report, $target->id);

            foreach ([['visiteur', $visitor], ['contributeur', $contributor]] as [$label, $user]) {
                $status = $this->statusFor($method, $uri, $user);
                if ($status !== 403) {
                    $failures[] = "{$signature} : {$label} reçoit {$status} au lieu de 403";
                }
            }

            $adminOnly = ! in_array($signature, self::REVIEWER_ROUTES, true);
            $status = $this->statusFor($method, $uri, $validator);
            if ($adminOnly && $status !== 403) {
                $failures[] = "{$signature} : un validateur reçoit {$status} sur une route d'administration";
            }
            if (! $adminOnly && $status === 403) {
                $failures[] = "{$signature} : un validateur est refusé alors que la route lui est ouverte";
            }
            if ($this->statusFor($method, $uri, $admin) === 403) {
                $failures[] = "{$signature} : un administrateur est refusé";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
