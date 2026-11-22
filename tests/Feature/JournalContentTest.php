<?php

namespace Tests\Feature;

use App\Services\Journal\JournalRenderer;
use App\Services\Journal\JournalRepository;
use Tests\TestCase;

/**
 * Relecture automatique des articles publiés (resources/content/journal) : en-têtes complets, version
 * française présente, sources en notes toutes définies et appelées, liens vers les fiches bien formés,
 * typographie du site (pas de tiret long), aucun titre de niveau 1 dans le texte, photo de Commons
 * avec auteur et licence, lien d'intérêts déclaré sur l'article d'AngouLim.
 */
class JournalContentTest extends TestCase
{
    private function journal(): JournalRepository
    {
        return new JournalRepository(resource_path('content/journal'));
    }

    public function test_every_article_has_a_french_version_and_a_complete_header(): void
    {
        $files = $this->journal()->files();
        $this->assertNotEmpty($files);
        foreach ($files as $slug => $versions) {
            $this->assertArrayHasKey('fr', $versions, "{$slug} : version française absente");
            foreach ($versions as $article) {
                $this->assertNotSame('', trim($article->title));
                $this->assertLessThanOrEqual(300, mb_strlen($article->summary), "{$slug} : résumé trop long pour les aperçus");
                $this->assertNotEmpty($article->lines, "{$slug} : aucune ligne liée");
                $this->assertNotNull($article->image, "{$slug} : pas de photo");
            }
        }
    }

    public function test_texts_follow_the_site_typography_and_cite_their_sources(): void
    {
        foreach (glob(resource_path('content/journal').'/*.md') ?: [] as $path) {
            $name = basename($path);
            $text = (string) file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]|(?<!-)--(?!-)/u', $text, "{$name} : tiret long ou double tiret");
            $this->assertDoesNotMatchRegularExpression('/^# /m', $text, "{$name} : titre de niveau 1 dans le texte");
            $this->assertDoesNotMatchRegularExpression('/archeoferroviaire/i', $text, "{$name} : source à ne pas citer");

            preg_match_all('/\[\^([a-z0-9-]+)\](?!:)/', $text, $calls);
            preg_match_all('/^\[\^([a-z0-9-]+)\]:/m', $text, $notes);
            $this->assertNotEmpty($notes[1], "{$name} : aucune source en note");
            $this->assertEqualsCanonicalizing(array_values(array_unique($notes[1])), array_values(array_unique($calls[1])), "{$name} : note sans appel ou appel sans note");

            preg_match_all('/\]\(((?:ligne|gare):[^)]*)\)/', $text, $links);
            foreach ($links[1] as $link) {
                $this->assertMatchesRegularExpression('/^(ligne:\d{6}|gare:[a-z0-9-]+)(#[a-z0-9_-]+)?$/', $link, "{$name} : lien mal formé « {$link} »");
            }
        }
    }

    public function test_articles_render_without_error(): void
    {
        $renderer = app(JournalRenderer::class);
        foreach ($this->journal()->files() as $versions) {
            foreach ($versions as $article) {
                $rendered = $renderer->render($article, 'nouvel onglet', 'retour');
                $this->assertGreaterThan(800, $rendered['words'], "{$article->slug} : article trop court pour le journal");
                $this->assertNotEmpty($rendered['toc']);
                $this->assertStringContainsString('role="doc-endnotes"', $rendered['html']);
            }
        }
    }

    public function test_angoulim_article_declares_the_interest(): void
    {
        $article = $this->journal()->files()['angouleme-limoges-angoulim']['fr'];
        $this->assertNotNull($article->disclosure);
        $this->assertStringContainsString('adhérent d’AngouLim', $article->disclosure);
        $this->assertSame(['610000'], $article->lines);
    }
}
