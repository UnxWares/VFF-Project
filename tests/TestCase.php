<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

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
}
