<?php

namespace Tests\Support;

use Dom\HTMLDocument;
use Illuminate\Testing\TestResponse;

/**
 * Lit une réponse HTML Inertia comme un NAVIGATEUR (parseur HTML5 de PHP 8.4+,
 * Dom\HTMLDocument) : le JSON de la page doit sortir intact de la balise
 * <script data-page="app" type="application/json">, l'élément #app doit exister
 * et aucune balise active (script, img, iframe…) ne doit naître du contenu saisi.
 */
trait ParsesInertiaHtml
{
    /**
     * @return array<string, mixed> l'objet page Inertia décodé
     */
    protected function parseInertiaHtml(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $script = $doc->querySelector('script[data-page="app"][type="application/json"]');
        $this->assertNotNull($script, 'balise <script data-page="app"> absente');
        $this->assertNotNull($doc->querySelector('#app'), 'élément #app absent : la page ne peut pas démarrer');

        $page = json_decode($script->textContent, true);
        $this->assertIsArray($page, 'JSON de la page illisible après analyse HTML5 : '.substr($script->textContent, 0, 200));

        // withoutVite() : les seules balises <script> légitimes sont celle des données et, dans <head>,
        // le script fixe qui pose le thème avant le premier rendu (resources/views/app.blade.php).
        $this->assertSame(1, $doc->querySelectorAll('head > script[data-theme-init]')->length, 'script du thème absent ou dupliqué');
        $this->assertSame(1, $doc->querySelectorAll('script:not([data-theme-init])')->length, 'balise <script> injectée');
        foreach (['img', 'iframe', 'svg', 'object', 'embed', 'style'] as $tag) {
            $this->assertSame(0, $doc->querySelectorAll($tag)->length, "balise <{$tag}> injectée");
        }

        return $page;
    }

    /**
     * Charges utiles d'injection HTML/JS classiques, dont celle qui fait basculer
     * l'analyseur HTML5 en « script data double escaped » (<!--<script>).
     *
     * @return array<string, string>
     */
    protected static function htmlInjectionPayloads(): array
    {
        return [
            'fermeture de script' => '</script><script>alert("vff")</script>',
            'commentaire + script' => '<!--<script>',
            'image piégée' => '<img src=x onerror=alert(1)>',
            'svg' => '<svg/onload=alert(1)>',
            'iframe' => '<iframe src="javascript:alert(1)"></iframe>',
            'entités' => '&lt;script&gt; &amp; &quot;',
            'guillemets' => "l'Ouest \"Paris\" \u{2019}",
        ];
    }
}
