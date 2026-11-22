<?php

namespace App\Services\Journal;

use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Texte Markdown d'un article du journal rendu en HTML pour la page, l'API et les flux.
 *
 * - Titres `##` : identifiant stable tiré du texte (ancre partageable) et entrée du sommaire.
 *   Le `#` est refusé : le titre de l'article est le seul h1 de la page.
 * - Liens vers l'encyclopédie : `[texte](ligne:878000)` (code RFN) et `[texte](gare:morez)` (slug de
 *   la gare, ancien slug accepté), avec une ancre facultative (`ligne:878000#chronicle`). Ils deviennent
 *   des liens vers la fiche quand elle existe ; sinon le texte reste, sans lien (jamais de lien mort).
 * - Liens externes : ouverts dans un nouvel onglet, signalé par l'icône du site et une phrase lue.
 * - Notes `[^1]` : sources de l'article, rendues en fin de texte (rôles doc-noteref et doc-endnotes),
 *   avec un retour vers l'appel nommé pour les lecteurs d'écran.
 * - HTML écrit dans le Markdown : échappé (le texte reste lisible, rien n'est exécuté) ; liens
 *   `javascript:` et autres protocoles dangereux retirés par CommonMark.
 */
final class JournalRenderer
{
    /** Vitesse de lecture retenue pour la durée affichée (mots par minute). */
    public const WORDS_PER_MINUTE = 220;

    /** Identifiants déjà pris par le gabarit du site. */
    private const RESERVED_IDS = ['contenu', 'main', 'app'];

    /**
     * @return array{
     *   html: string,
     *   toc: list<array{id: string, label: string}>,
     *   lines: list<array{code: string, slug: string, name: string}>,
     *   stations: list<array{slug: string, name: string, city: ?string}>,
     *   words: int,
     *   reading_minutes: int,
     * }
     */
    public function render(JournalArticle $article, string $newTabLabel, string $backLabel): array
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'footnote' => [
                'backref_symbol' => '↑',
                'container_add_hr' => false,
                'ref_id_prefix' => 'appel-',
                'footnote_id_prefix' => 'note-',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new FootnoteExtension);
        $environment->addExtension(new TableExtension);
        $environment->addRenderer(ExternalMark::class, new ExternalMark);

        $document = (new MarkdownParser($environment))->parse($article->body);

        [$headings, $links] = $this->collect($document);
        $toc = $this->anchor($headings);
        [$lineCodes, $stationSlugs] = $this->targets($links, $article);
        $lines = $this->lines($lineCodes);
        $stations = $this->stations($stationSlugs);
        $this->rewrite($links, $lines, $stations, $newTabLabel);

        $html = (string) (new HtmlRenderer($environment))->renderDocument($document);
        // Retour d'une note vers son appel : la flèche seule ne dit rien aux lecteurs d'écran.
        $html = str_replace('role="doc-backlink"', 'role="doc-backlink" aria-label="'.e($backLabel).'"', $html);
        $words = str_word_count(strip_tags($html), 0, 'ÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸàâäçéèêëîïôöùûüÿŒœÆæß0123456789\'’-');

        return [
            'html' => trim($html),
            'toc' => $toc,
            'lines' => self::unique(array_map(fn (string $c): ?array => $lines[$c] ?? null, $lineCodes)),
            // Un ancien slug et le slug actuel d'une même gare ne donnent qu'une fiche.
            'stations' => self::unique(array_map(fn (string $sl): ?array => $stations[$sl] ?? null, $stationSlugs)),
            'words' => $words,
            'reading_minutes' => max(1, (int) ceil($words / self::WORDS_PER_MINUTE)),
        ];
    }

    /** @return array{0: list<Heading>, 1: list<Link>} */
    private function collect(Document $document): array
    {
        $headings = [];
        $links = [];
        $walker = $document->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();
            if (! $event->isEntering()) {
                continue;
            }
            if ($node instanceof Heading) {
                if ($node->getLevel() === 1) {
                    throw new \UnexpectedValueException('Titre de niveau 1 dans le texte : le titre de l\'article est dans l\'en-tête, les sections commencent à « ## ».');
                }
                $headings[] = $node;
            } elseif ($node instanceof Link) {
                $links[] = $node;
            }
        }

        return [$headings, $links];
    }

    /**
     * Identifiant de chaque titre (texte en minuscules sans accents, suffixe -2, -3 en cas de doublon) ;
     * sommaire des titres de niveau 2.
     *
     * @param  list<Heading>  $headings
     * @return list<array{id: string, label: string}>
     */
    private function anchor(array $headings): array
    {
        $taken = array_fill_keys(self::RESERVED_IDS, true);
        $toc = [];
        foreach ($headings as $heading) {
            $label = trim(StringContainerHelper::getChildText($heading));
            $base = Str::slug($label) ?: 'section';
            $id = $base;
            for ($n = 2; isset($taken[$id]); $n++) {
                $id = "{$base}-{$n}";
            }
            $taken[$id] = true;
            $heading->data->set('attributes/id', $id);
            if ($heading->getLevel() === 2) {
                $toc[] = ['id' => $id, 'label' => $label];
            }
        }

        return $toc;
    }

    /**
     * Lignes et gares citées : celles de l'en-tête d'abord, dans son ordre, puis celles des liens du texte.
     *
     * @param  list<Link>  $links
     * @return array{0: list<string>, 1: list<string>}
     */
    private function targets(array $links, JournalArticle $article): array
    {
        $lines = $article->lines;
        $stations = $article->stations;
        foreach ($links as $link) {
            $target = self::target($link->getUrl());
            if ($target === null) {
                continue;
            }
            if ($target[0] === 'ligne') {
                $lines[] = $target[1];
            } else {
                $stations[] = $target[1];
            }
        }

        return [array_values(array_unique($lines)), array_values(array_unique($stations))];
    }

    /** @return array{0: 'ligne'|'gare', 1: string, 2: string}|null type, identifiant, ancre (avec #) */
    private static function target(string $url): ?array
    {
        if (preg_match('/^(ligne|gare):([a-z0-9-]{1,160})(#[a-z0-9_-]{1,80})?$/', $url, $m) !== 1) {
            return null;
        }

        /** @var 'ligne'|'gare' $kind */
        $kind = $m[1];

        return [$kind, $m[2], $m[3] ?? ''];
    }

    /**
     * Fiches des lignes citées, par code RFN. Plusieurs lignes peuvent partager un code (ancienne ligne
     * et ligne du réseau actuel) : la fiche du réseau ferré national (`rfn-<code>`) est préférée.
     *
     * @param  list<string>  $codes
     * @return array<string, array{code: string, slug: string, name: string}>
     */
    public function lines(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $found = [];
        Line::query()->whereIn('code', $codes)->orderBy('id')->get(['id', 'code', 'slug', 'name'])
            ->each(function (Line $line) use (&$found): void {
                $code = (string) $line->code;
                if (! isset($found[$code]) || $line->slug === 'rfn-'.$code) {
                    $found[$code] = ['code' => $code, 'slug' => (string) $line->slug, 'name' => (string) $line->name];
                }
            });

        return $found;
    }

    /**
     * Fiches des gares citées, par slug ; un ancien slug (table station_redirects) mène à la gare actuelle.
     *
     * @param  list<string>  $slugs
     * @return array<string, array{slug: string, name: string, city: ?string}>
     */
    private function stations(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        $redirects = DB::table('station_redirects')->whereIn('old_slug', $slugs)->pluck('station_id', 'old_slug')->all();
        $rows = Station::query()
            ->where(fn ($q) => $q->whereIn('slug', $slugs)->orWhereIn('id', array_values($redirects)))
            ->get(['id', 'slug', 'name', 'city']);
        $found = [];
        foreach ($slugs as $slug) {
            $station = $rows->firstWhere('slug', $slug) ?? (isset($redirects[$slug]) ? $rows->firstWhere('id', $redirects[$slug]) : null);
            if ($station instanceof Station) {
                $found[$slug] = ['slug' => (string) $station->slug, 'name' => (string) $station->name, 'city' => $station->city];
            }
        }

        return $found;
    }

    /**
     * @param  list<Link>  $links
     * @param  array<string, array{code: string, slug: string, name: string}>  $lines
     * @param  array<string, array{slug: string, name: string, city: ?string}>  $stations
     */
    private function rewrite(array $links, array $lines, array $stations, string $newTabLabel): void
    {
        foreach ($links as $link) {
            $url = $link->getUrl();
            $target = self::target($url);
            if ($target !== null) {
                $sheet = $target[0] === 'ligne'
                    ? (isset($lines[$target[1]]) ? '/lines/'.$lines[$target[1]]['slug'] : null)
                    : (isset($stations[$target[1]]) ? '/stations/'.$stations[$target[1]]['slug'] : null);
                if ($sheet === null) {
                    self::unwrap($link);
                } else {
                    $link->setUrl($sheet.$target[2]);
                }

                continue;
            }
            if (preg_match('#^https?://#i', $url) === 1) {
                $link->data->set('attributes/target', '_blank');
                $link->data->set('attributes/rel', 'noopener noreferrer');
                $link->appendChild(new ExternalMark($newTabLabel));
            }
        }
    }

    /**
     * Fiches trouvées, sans doublon (même slug), dans l'ordre de première citation.
     *
     * @template T of array{slug: string}
     *
     * @param  list<T|null>  $sheets
     * @return list<T>
     */
    private static function unique(array $sheets): array
    {
        $out = [];
        foreach ($sheets as $sheet) {
            if ($sheet !== null) {
                $out[$sheet['slug']] ??= $sheet;
            }
        }

        return array_values($out);
    }

    /** Remplace un lien par son texte. */
    private static function unwrap(Link $link): void
    {
        /** @var list<Node> $children */
        $children = iterator_to_array($link->children(), false);
        foreach ($children as $child) {
            $link->insertBefore($child);
        }
        $link->detach();
    }
}
