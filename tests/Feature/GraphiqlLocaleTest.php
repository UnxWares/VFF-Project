<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Console GraphiQL : la route du paquet passe par le groupe `web`, donc par SetLocale. Avant, elle
 * restait en français quelle que soit la langue demandée.
 */
class GraphiqlLocaleTest extends TestCase
{
    public function test_follows_accept_language(): void
    {
        $html = (string) $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])->get('/graphiql')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="de"', $html);
        $this->assertStringContainsString(__('docs.title_graphiql', [], 'de'), $html);
    }

    public function test_follows_the_locale_cookie(): void
    {
        $html = (string) $this->withCookie('locale', 'en')->withHeaders(['Accept-Language' => 'de'])->get('/graphiql')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en"', $html);
    }

    public function test_french_for_a_french_browser(): void
    {
        $html = (string) $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])->get('/graphiql')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="fr"', $html);
    }
}
