<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel réseau réel (Wikipédia, Wikidata, Overpass, IGN, SNCF,
        // Commons…) : toute requête HTTP non simulée par Http::fake() lève une
        // exception. Les tests fournissent leurs réponses (tests/Fixtures).
        Http::preventStrayRequests();

        // Pas de dépendance au serveur Vite (public/hot) ni au build (manifest) :
        // les tests tournent pareil en local et en CI.
        $this->withoutVite();

        // Les pages Inertia du projet sont dans resources/js/Pages (P majuscule) ;
        // la config par défaut d'inertia-laravel cherche resources/js/pages.
        // assertInertia()->component() vérifie ainsi que le .svelte existe.
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
    }

    /**
     * Visite d'une page Inertia telle que le navigateur la voit une fois ses props différées reçues (Inertia::defer,
     * fiches de ligne et de gare) : la première réponse, puis la requête partielle de chaque groupe annoncé, et
     * les props reçues versées dans la page de la première réponse (assertInertia, viewData). Sans props
     * différées, la réponse telle quelle.
     *
     * @param  array<string, string>  $headers
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function getWithDeferredProps(string $uri, array $headers = []): TestResponse
    {
        $response = $this->get($uri, $headers);
        $view = $response->baseResponse instanceof Response ? $response->baseResponse->getOriginalContent() : null;
        if (! $view instanceof View || ! is_array($page = $view->getData()['page'] ?? null) || empty($page['deferredProps'])) {
            return $response;
        }
        foreach ($page['deferredProps'] as $keys) {
            $props = $this->get($uri, [...$headers,
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $page['version'],
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia-Partial-Component' => (string) $page['component'],
                'X-Inertia-Partial-Data' => implode(',', $keys),
            ])->assertOk()->json('props');
            $page['props'] = [...$page['props'], ...array_intersect_key((array) $props, array_flip($keys))];
        }
        unset($page['deferredProps']);
        $view->with('page', $page);

        return $response;
    }
}
