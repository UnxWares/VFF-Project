<?php

namespace App\Services\ArgentanWebFerro;

use RuntimeException;

/**
 * Curation relue de la source (database/data/argentanwebferro.php) : rattachement des
 * articles et des albums aux lignes VFF, corrections et ajouts de faits, classes de droits
 * des photos.
 */
final readonly class Curation
{
    /**
     * @param  array<string, array{lines: list<string>, note?: string}>  $articles
     * @param  array<string, array<string, mixed>>  $facts
     * @param  array<string, list<array<string, mixed>>>  $added
     * @param  array<string, list<string>>  $photoAlbums
     * @param  array<string, array<int, string>>  $photos  classe → [identifiant Piwigo => raison] (cf. photoClasses())
     */
    public function __construct(
        public array $articles = [],
        public array $facts = [],
        public array $added = [],
        public array $photoAlbums = [],
        public array $photos = [],
    ) {}

    public static function path(): string
    {
        return database_path('data/argentanwebferro.php');
    }

    public static function load(?string $path = null): self
    {
        $path ??= self::path();
        if (! is_file($path)) {
            throw new RuntimeException("Curation introuvable : {$path}");
        }
        $data = require $path;

        return new self(
            articles: $data['articles'] ?? [],
            facts: $data['facts'] ?? [],
            added: $data['added'] ?? [],
            photoAlbums: $data['photo_albums'] ?? [],
            photos: self::photoClasses($data['photos'] ?? []),
        );
    }

    /**
     * Classes de photos écrites « classe → raison → identifiants » dans le fichier, rendues
     * « classe → [identifiant => raison] » pour PhotoClassifier.
     *
     * @param  array<string, array<string, list<int>>>  $byReason
     * @return array<string, array<int, string>>
     */
    public static function photoClasses(array $byReason): array
    {
        $out = [];
        foreach ($byReason as $class => $groups) {
            foreach ($groups as $reason => $ids) {
                foreach ($ids as $id) {
                    $out[$class][(int) $id] = (string) $reason;
                }
            }
        }

        return $out;
    }

    /** @return list<string> références de lignes citées (articles et albums) */
    public function lineRefs(): array
    {
        $refs = [];
        foreach ($this->articles as $article) {
            $refs = [...$refs, ...$article['lines']];
        }
        foreach ($this->photoAlbums as $lines) {
            $refs = [...$refs, ...$lines];
        }
        foreach ([$this->facts, ...array_values($this->added)] as $facts) {
            foreach ($facts as $fact) {
                if (isset($fact['line'])) {
                    $refs[] = (string) $fact['line'];
                }
            }
        }

        return array_values(array_unique($refs));
    }
}
