<?php

namespace Tests\Unit;

use App\Services\Journal\JournalFrontMatter;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JournalFrontMatterTest extends TestCase
{
    public function test_reads_scalars_lists_and_maps(): void
    {
        $data = (new JournalFrontMatter)->parse(<<<'YAML'
            ---
            # commentaire ignoré
            title: "Titre : avec deux-points"
            date: 2026-11-22
            summary: 'Résumé'
            lines:
              - 878000
              - "610000"
            image:
              src: https://upload.wikimedia.org/x.jpg
              author: Nom d’auteur
            YAML);

        $this->assertSame([
            'title' => 'Titre : avec deux-points',
            'date' => '2026-11-22',
            'summary' => 'Résumé',
            'lines' => ['878000', '610000'],
            'image' => ['src' => 'https://upload.wikimedia.org/x.jpg', 'author' => 'Nom d’auteur'],
        ], $data);
    }

    public function test_empty_key_is_an_empty_list(): void
    {
        $this->assertSame(['stations' => [], 'title' => 'T'], (new JournalFrontMatter)->parse("stations:\ntitle: T"));
    }

    /** @return array<string, array{string}> */
    public static function invalid(): array
    {
        return [
            'clé répétée' => ["title: A\ntitle: B"],
            'ligne sans clé' => ['juste du texte'],
            'majuscules dans la clé' => ['Title: A'],
            'liste puis dictionnaire' => ["lines:\n  - 1\n  src: x"],
            'élément sans clé ouverte' => ['  - 1'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_rejects_malformed_headers(string $yaml): void
    {
        $this->expectException(InvalidFrontMatterException::class);
        (new JournalFrontMatter)->parse($yaml);
    }
}
