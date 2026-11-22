<?php

namespace Tests\Unit;

use App\Services\Journal\JournalArticle;
use App\Services\Journal\JournalRenderer;
use Tests\TestCase;

/** Identifiants du texte rendu d'un article du journal : uniques, même quand un titre ressemble à une note. */
class JournalRendererIdsTest extends TestCase
{
    public function test_a_heading_never_takes_the_id_of_a_footnote(): void
    {
        // « ## Note 1 » donnait l'identifiant note-1, celui de la première note : deux éléments de même id,
        // et le lien de l'appel menait au titre au lieu de la note.
        $article = new JournalArticle('essai', 'fr', ['fr'], 'Essai', '2026-01-01', null, 'Résumé', [], [], null, null,
            "## Note 1\n\nTexte[^1].\n\n## Appel 1\n\nSuite.\n\n[^1]: Source.\n");

        $rendered = (new JournalRenderer)->render($article, 'nouvel onglet', 'retour à l\'appel');

        preg_match_all('/\bid="([^"]+)"/', $rendered['html'], $m);
        $this->assertSame($m[1], array_values(array_unique($m[1])), 'identifiants en double : '.implode(', ', $m[1]));
        $this->assertContains('note-1', $m[1]);
        $this->assertContains('appel-1', $m[1]);
        $this->assertSame(['Note 1', 'Appel 1'], array_column($rendered['toc'], 'label'));
    }
}
