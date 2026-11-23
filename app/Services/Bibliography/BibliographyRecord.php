<?php

namespace App\Services\Bibliography;

use App\Services\Archives\ArchiveDate;
use App\Services\Archives\ArchiveRecord;
use Illuminate\Support\Str;

/**
 * Une référence bibliographique lue dans une source (notice de la BnF, référence d'un article de Wikipédia,
 * élément de Wikidata) : seulement ce qu'une notice dit, jamais le contenu de l'ouvrage.
 *
 * `title` : titre et complément du titre (« L'épopée de la ligne des Causses : histoire du chemin de fer… ») ;
 * `authors` : « Prénom Nom », dans l'ordre de la notice, quatre au plus ; `isbn` : ISBN-13 valide ou null ;
 * `url` : notice de la BnF, ou à défaut référence d'origine (permalien de l'article, item Wikidata) ;
 * `journal` : revue d'un article.
 */
final readonly class BibliographyRecord
{
    public const BOOK = 'book';

    public const ARTICLE = 'article';

    /**
     * @param  list<string>  $authors
     * @param  list<string>  $isbns  tous les ISBN-13 valides de la notice (la BnF en liste parfois plusieurs : broché, relié)
     */
    public function __construct(
        public string $kind,
        public string $title,
        public array $authors,
        public ?string $publisher,
        public ?string $place,
        public ?int $year,
        public ?string $isbn,
        public ?string $journal,
        public string $provider,
        public ?string $url,
        public ?string $bnfArk = null,
        public array $isbns = [],
    ) {}

    /** La même référence avec les données d'une autre (notice de la BnF trouvée par ISBN), sous l'ISBN d'origine. */
    public function enrichedBy(self $notice): self
    {
        return new self($notice->kind, $notice->title, $notice->authors, $notice->publisher, $notice->place, $notice->year, $this->isbn,
            $this->journal, $notice->provider, $notice->url, $notice->bnfArk, $notice->isbns);
    }

    /** Clé de dédoublonnage : ISBN, sinon ark de la notice, sinon empreinte du titre, du premier auteur et de l'année. */
    public function dedupKey(): string
    {
        if ($this->isbn !== null) {
            return 'isbn:'.$this->isbn;
        }
        if ($this->bnfArk !== null) {
            return 'ark:'.$this->bnfArk;
        }

        return 'sha1:'.sha1(implode('|', [self::norm($this->title), self::norm($this->authors[0] ?? ''), (string) $this->year, $this->kind]));
    }

    /** Texte sur lequel le rattachement est jugé : le titre (avec son complément) sans la ponctuation. */
    public function matchText(): string
    {
        return $this->title;
    }

    /** Le même enregistrement sous la forme attendue par ArchiveMatcher (rattachement d'un titre à une ligne). */
    public function asArchiveRecord(): ArchiveRecord
    {
        return new ArchiveRecord(
            gallicaArk: $this->dedupKey(),
            catalogueArk: $this->bnfArk,
            title: $this->title,
            matchText: $this->title,
            docType: 'monograph',
            authors: $this->authors,
            date: $this->year === null ? ArchiveDate::unknown() : new ArchiveDate((string) $this->year, $this->year, null, null, 'year'),
            shelfmark: null,
            recordUpdatedOn: null,
        );
    }

    /** Comparaison de titres et de noms : minuscules sans accents, lettres et chiffres séparés par un blanc. */
    public static function norm(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text))));
    }
}
