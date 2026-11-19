<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\MarkOfflineReadable;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\TestCase;

/**
 * Mode hors ligne : le service worker ne garde une page dans le navigateur que si le serveur pose
 * `X-VFF-Offline: allow` (MarkOfflineReadable). Ces tests vérifient qu'il ne le pose que sur une page
 * publique complète, vue par un visiteur non connecté, et jamais sur une page de compte, de contribution,
 * de validation, une réponse partielle, une erreur ou une page qui porte un message de session.
 */
class OfflineReadableHeaderTest extends TestCase
{
    use ActsAsRoles;
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function publicPages(): array
    {
        return [
            'accueil' => ['/'],
            'carte' => ['/maps'],
            'lignes' => ['/lines'],
            'gares' => ['/stations'],
            'matériel' => ['/materials'],
            'confidentialité' => ['/privacy'],
            'crédits des médias' => ['/credits/media'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_are_offline_readable_for_a_guest(string $uri): void
    {
        $this->get($uri)->assertOk()->assertHeader(MarkOfflineReadable::HEADER, MarkOfflineReadable::ALLOW);
    }

    public function test_line_sheet_is_offline_readable_in_html_and_inertia(): void
    {
        $line = Line::factory()->create();
        $uri = "/lines/{$line->slug}";

        $html = $this->get($uri)->assertOk()->assertHeader(MarkOfflineReadable::HEADER, MarkOfflineReadable::ALLOW);
        $version = (string) $html->viewData('page')['version'];

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])
            ->get($uri)
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader(MarkOfflineReadable::HEADER, MarkOfflineReadable::ALLOW);
    }

    /** @return array<string, array{0: string}> */
    public static function privateGuestPages(): array
    {
        return [
            'connexion' => ['/login'],
            'inscription' => ['/register'],
            'mot de passe oublié' => ['/forgot-password'],
            'contribuer' => ['/contribute'],
            'réputation' => ['/contribute/reputation'],
            'documentation API' => ['/api'],
        ];
    }

    #[DataProvider('privateGuestPages')]
    public function test_account_and_contribution_pages_are_never_offline_readable(string $uri): void
    {
        $this->get($uri)->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
    }

    public function test_pages_seen_by_a_signed_in_member_are_never_offline_readable(): void
    {
        $member = $this->member('validator');

        foreach (['/', '/lines', '/privacy', '/profile', '/validate', '/contribute/mine', '/notifications'] as $uri) {
            $this->actingAs($member)->get($uri)->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        }
    }

    public function test_partial_reloads_and_prefetches_are_never_offline_readable(): void
    {
        $version = (string) $this->get('/lines')->viewData('page')['version'];
        $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'];

        $this->withHeaders([...$inertia, 'X-Inertia-Partial-Component' => 'Lines/Index', 'X-Inertia-Partial-Data' => 'lines'])
            ->get('/lines')->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        $this->withHeaders([...$inertia, 'Purpose' => 'prefetch'])
            ->get('/lines')->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
    }

    public function test_errors_redirects_and_writes_are_never_offline_readable(): void
    {
        $this->get('/lines/ligne-qui-n-existe-pas')->assertNotFound()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        $this->get('/une-adresse-inconnue')->assertNotFound()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        $this->get('/profile')->assertRedirect()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        $this->post('/lines')->assertStatus(405)->assertHeaderMissing(MarkOfflineReadable::HEADER);
    }

    public function test_page_carrying_a_session_message_is_never_offline_readable(): void
    {
        $this->withSession(['success' => 'Votre signalement a été envoyé.'])
            ->get('/lines')->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
        $this->withSession(['error' => 'Envoi impossible.'])
            ->get('/lines')->assertOk()->assertHeaderMissing(MarkOfflineReadable::HEADER);
    }

    public function test_every_listed_route_exists_and_is_a_public_get_route(): void
    {
        foreach (MarkOfflineReadable::ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route inconnue : {$name}");
            $this->assertContains('GET', $route->methods(), $name);
            $this->assertNotContains('auth', $route->gatherMiddleware(), $name);
            $this->assertStringStartsNotWith('contribute', $name);
            $this->assertStringStartsNotWith('validate', $name);
            $this->assertStringStartsNotWith('profile', $name);
        }
    }
}
