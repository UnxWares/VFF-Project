<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Journal\JournalArticle;
use App\Services\Journal\JournalPresenter;
use App\Services\Journal\JournalRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('journal')]
#[Title('Journal de bord (articles longs)')]
#[Description(<<<'TXT'
Articles longs du journal de bord de VFF, écrits par l'équipe sur des lignes emblématiques et sur le projet, avec leurs sources en notes. Même contenu que l'API REST GET /api/v1/journal (sans `slug` : liste, du plus récent au plus ancien, sans le texte) et GET /api/v1/journal/{slug} (avec `slug` : l'article complet).
Champs : `title`, `date` et `updated` (AAAA-MM-JJ), `summary`, `lang` (langue du texte renvoyé : un article sans traduction est renvoyé en français) et `locales`, `lines` (liste : codes RFN ; article : fiches citées avec `code`, `slug`, `name`), `stations` (liste : slugs ; article : fiches citées), `image` (photo de Wikimedia Commons avec auteur et licence), et pour l'article `author` (toujours l'équipe), `disclosure` (engagement déclaré, à mentionner quand vous reprenez l'article), `html` (texte, sources en notes de fin), `toc`, `reading_minutes`.
Droits : texte sous CC BY-SA 4.0 (citez « Voies Ferrées de France » avec `vff_url`), image sous sa propre licence. Les faits datés de la ligne sont dans get_line et line_events.
Exemples : {} ; {"line": "878000"} ; {"slug": "les-hirondelles", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class JournalTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'slug' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:160'],
            'line' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->max(160)
                ->description("Slug d'un article (renvoyé par la liste, ex. \"les-hirondelles\"). Sans lui : la liste des articles."),
            'line' => $schema->string()->min(6)->max(6)
                ->description('Code RFN à 6 chiffres : seulement les articles qui parlent de cette ligne (liste).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $journal = app(JournalRepository::class);
        $presenter = app(JournalPresenter::class);
        $previous = app()->getLocale();
        app()->setLocale($lang);
        try {
            if (! empty($input['slug'])) {
                $article = $journal->find($input['slug'], $lang);
                if ($article === null) {
                    return $this->fail('journal_not_found', ['query' => $input['slug']]);
                }
                $full = $presenter->full($article);

                return $this->respond($full, $this->providers($article), $lang, self::url($full['path']));
            }

            $articles = $journal->all($lang);
            if (! empty($input['line'])) {
                $articles = array_values(array_filter($articles, fn (JournalArticle $a): bool => in_array($input['line'], $a->lines, true)));
            }
            $items = array_map(fn (JournalArticle $a): array => $presenter->summary($a) + ['vff_url' => self::url('/journal/'.$a->slug)], $articles);

            return $this->respond(
                ['total' => count($items), 'articles' => $items],
                array_merge(['vff'], ...array_map(fn (JournalArticle $a): array => $this->providers($a), $articles)),
                $lang,
                self::url('/journal'),
                ['articles'],
            );
        } finally {
            app()->setLocale($previous);
        }
    }

    /** @return list<string> */
    private function providers(JournalArticle $article): array
    {
        return $article->image === null ? ['vff'] : ['vff', 'commons'];
    }
}
