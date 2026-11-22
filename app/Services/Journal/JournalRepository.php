<?php

namespace App\Services\Journal;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Carbon;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use UnexpectedValueException;

/**
 * Articles du journal de bord, lus dans les fichiers versionnés `resources/content/journal/` : un
 * fichier par article et par langue, `<slug>.<langue>.md` (`les-hirondelles.fr.md`), avec un en-tête
 * de métadonnées (JournalFrontMatter) puis le texte en Markdown (JournalRenderer).
 *
 * Le français est la langue de référence : un article existe dès que son fichier `.fr.md` existe, et
 * une traduction absente est remplacée par le texte français (JournalArticle::$locale le dit). Un
 * article daté du futur n'est pas publié : il apparaît le jour de sa date (fuseau de l'application).
 * Une erreur d'en-tête (champ obligatoire absent, date ou image mal formées) lève une exception
 * avec le nom du fichier : `tests/Feature/JournalContentTest.php` relit tous les articles.
 */
final class JournalRepository
{
    public const CANONICAL = 'fr';

    private const FILE = '/^([a-z0-9]+(?:-[a-z0-9]+)*)\.(fr|en|de)\.md$/';

    private const REQUIRED = ['title', 'date', 'summary'];

    private readonly string $directory;

    /** @var array<string, array<string, JournalArticle>>|null slug, puis langue */
    private ?array $files = null;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('content/journal');
    }

    /**
     * Articles publiés, du plus récent au plus ancien (même date : ordre alphabétique du slug), chacun
     * dans la langue demandée quand elle existe, sinon en français.
     *
     * @return list<JournalArticle>
     */
    public function all(string $locale): array
    {
        $today = Carbon::today()->toDateString();
        $articles = [];
        foreach (array_keys($this->files()) as $slug) {
            $article = $this->find($slug, $locale);
            if ($article !== null && $article->date <= $today) {
                $articles[] = $article;
            }
        }
        usort($articles, fn (JournalArticle $a, JournalArticle $b): int => [$b->date, $a->slug] <=> [$a->date, $b->slug]);

        return $articles;
    }

    /**
     * Articles publiés qui parlent d'une ligne (code RFN dans leur en-tête), pour le lien de sa fiche.
     *
     * @return list<array{slug: string, title: string, lang: string}>
     */
    public function aboutLine(string $code, string $locale): array
    {
        return array_values(array_map(
            fn (JournalArticle $a): array => ['slug' => $a->slug, 'title' => $a->title, 'lang' => $a->locale],
            array_filter($this->all($locale), fn (JournalArticle $a): bool => in_array($code, $a->lines, true)),
        ));
    }

    /** Article publié de ce slug, dans la langue demandée ou en français ; null s'il n'existe pas (encore). */
    public function find(string $slug, string $locale): ?JournalArticle
    {
        $versions = $this->files()[$slug] ?? null;
        if ($versions === null || ! isset($versions[self::CANONICAL])) {
            return null;
        }
        $article = $versions[$locale] ?? $versions[self::CANONICAL];

        return $article->date <= Carbon::today()->toDateString() ? $article : null;
    }

    /**
     * Toutes les versions lues, publiées ou non (pour les contrôles).
     *
     * @return array<string, array<string, JournalArticle>>
     */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }
        $raw = [];
        foreach (is_dir($this->directory) ? (scandir($this->directory) ?: []) : [] as $name) {
            if (preg_match(self::FILE, $name, $m) === 1) {
                $raw[$m[1]][$m[2]] = $this->directory.'/'.$name;
            }
        }
        ksort($raw);
        $files = [];
        foreach ($raw as $slug => $paths) {
            // Langues dans l'ordre du site (fr, en, de), le français d'abord.
            $locales = array_values(array_intersect(SetLocale::SUPPORTED, array_keys($paths)));
            foreach ($locales as $locale) {
                $files[$slug][$locale] = $this->read($paths[$locale], $slug, $locale, $locales);
            }
        }

        return $this->files = $files;
    }

    /** @param  list<string>  $locales */
    private function read(string $path, string $slug, string $locale, array $locales): JournalArticle
    {
        $name = basename($path);
        try {
            $parsed = (new FrontMatterParser(new JournalFrontMatter))->parse((string) file_get_contents($path));
        } catch (InvalidFrontMatterException $e) {
            throw new UnexpectedValueException("{$name} : {$e->getMessage()}", 0, $e);
        }
        $meta = $parsed->getFrontMatter();
        if (! is_array($meta)) {
            throw new UnexpectedValueException("{$name} : en-tête de métadonnées absent.");
        }
        foreach (self::REQUIRED as $key) {
            if (! is_string($meta[$key] ?? null) || trim($meta[$key]) === '') {
                throw new UnexpectedValueException("{$name} : champ « {$key} » absent ou vide.");
            }
        }
        foreach (['date', 'updated'] as $key) {
            if (isset($meta[$key]) && ! self::isDate($meta[$key])) {
                throw new UnexpectedValueException("{$name} : « {$key} » doit être une date AAAA-MM-JJ.");
            }
        }

        return new JournalArticle(
            slug: $slug,
            locale: $locale,
            locales: $locales,
            title: $meta['title'],
            date: $meta['date'],
            updated: isset($meta['updated']) && is_string($meta['updated']) ? $meta['updated'] : null,
            summary: $meta['summary'],
            lines: self::list($meta, 'lines', $name),
            stations: self::list($meta, 'stations', $name),
            image: self::image($meta['image'] ?? null, $name),
            disclosure: isset($meta['disclosure']) && is_string($meta['disclosure']) ? $meta['disclosure'] : null,
            body: $parsed->getContent(),
        );
    }

    private static function isDate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private static function list(array $meta, string $key, string $name): array
    {
        $value = $meta[$key] ?? [];
        if (! is_array($value) || ! array_is_list($value)) {
            throw new UnexpectedValueException("{$name} : « {$key} » doit être une liste.");
        }

        return array_map('strval', $value);
    }

    /** @return array{src: string, alt: string, author: string, licence: string, licence_url: ?string, source_url: string, width: int, height: int, caption: ?string}|null */
    private static function image(mixed $image, string $name): ?array
    {
        if ($image === null) {
            return null;
        }
        if (! is_array($image) || array_is_list($image)) {
            throw new UnexpectedValueException("{$name} : « image » doit être un dictionnaire.");
        }
        foreach (['src', 'alt', 'author', 'licence', 'source_url', 'width', 'height'] as $key) {
            if (! is_string($image[$key] ?? null) || $image[$key] === '') {
                throw new UnexpectedValueException("{$name} : « image.{$key} » absent.");
            }
        }
        // Image libre de Wikimedia Commons seulement : la politique de sécurité du site n'autorise que lui.
        if (! str_starts_with($image['src'], 'https://upload.wikimedia.org/') || ! str_starts_with($image['source_url'], 'https://commons.wikimedia.org/')) {
            throw new UnexpectedValueException("{$name} : l'image doit venir de Wikimedia Commons.");
        }
        if (! ctype_digit($image['width']) || ! ctype_digit($image['height'])) {
            throw new UnexpectedValueException("{$name} : « image.width » et « image.height » sont des nombres de pixels.");
        }

        return [
            'src' => $image['src'],
            'alt' => $image['alt'],
            'author' => $image['author'],
            'licence' => $image['licence'],
            'licence_url' => $image['licence_url'] ?? null,
            'source_url' => $image['source_url'],
            'width' => (int) $image['width'],
            'height' => (int) $image['height'],
            'caption' => $image['caption'] ?? null,
        ];
    }
}
