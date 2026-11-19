<?php

namespace Tests\Unit\Media;

use App\Services\Media\CommonsFileResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Adresse d'un fichier Wikimedia Commons proposée par un contributeur → titre « File:… », et
 * verdict sur ses droits (licence libre permettant l'usage commercial, format d'image ou de
 * document).
 */
class CommonsFileResolverTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string}> */
    public static function addresses(): array
    {
        return [
            'page du fichier' => ['https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'page encodée' => ['https://commons.wikimedia.org/wiki/File:Gare_de_Ch%C3%A2teauroux.jpg', 'File:Gare de Châteauroux.jpg'],
            'version mobile' => ['https://commons.m.wikimedia.org/wiki/File:Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'index.php' => ['https://commons.wikimedia.org/w/index.php?title=File:Gare_de_Nontron.jpg&oldid=1', 'File:Gare de Nontron.jpg'],
            'espace traduit' => ['https://commons.wikimedia.org/wiki/Fichier:Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'fichier original' => ['https://upload.wikimedia.org/wikipedia/commons/a/ab/Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'miniature' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare_de_Nontron.jpg/500px-Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'titre seul' => ['File:gare de Nontron.jpg', 'File:Gare de Nontron.jpg'],
            'titre en allemand' => ['Datei:Bahnhof.jpg', 'File:Bahnhof.jpg'],
            'espaces autour' => ['  https://commons.wikimedia.org/wiki/File:A.png  ', 'File:A.png'],
            'autre site' => ['https://fr.wikipedia.org/wiki/Fichier:Gare.jpg', null],
            'page de catégorie' => ['https://commons.wikimedia.org/wiki/Category:Gare_de_Nontron', null],
            'adresse vide de titre' => ['https://commons.wikimedia.org/wiki/File:', null],
            'caractère interdit' => ['File:A|B.jpg', null],
            'texte libre' => ['une belle photo', null],
            'fichier d’un autre wiki' => ['https://upload.wikimedia.org/wikipedia/fr/a/ab/Gare.jpg', null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_file_title_is_read_from_the_address(string $input, ?string $expected): void
    {
        $this->assertSame($expected, CommonsFileResolver::fileTitle($input));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: ?string}> */
    public static function rights(): array
    {
        $ok = ['mime' => 'image/jpeg', 'rights_status' => 'open_license', 'commercial_ok' => true];

        return [
            'CC BY-SA' => [$ok, null],
            'domaine public' => [['rights_status' => 'public_domain', 'commercial_ok' => true] + $ok, null],
            'document PDF' => [['mime' => 'application/pdf'] + $ok, null],
            'licence inconnue' => [['rights_status' => 'unknown', 'commercial_ok' => null] + $ok, CommonsFileResolver::REFUSED_LICENSE],
            'non commerciale' => [['commercial_ok' => false] + $ok, CommonsFileResolver::REFUSED_LICENSE],
            'vidéo' => [['mime' => 'video/webm'] + $ok, CommonsFileResolver::REFUSED_FORMAT],
            'son' => [['mime' => 'audio/ogg'] + $ok, CommonsFileResolver::REFUSED_FORMAT],
        ];
    }

    /** @param  array<string, mixed>  $meta */
    #[DataProvider('rights')]
    public function test_only_free_commercial_images_and_documents_are_accepted(array $meta, ?string $refusal): void
    {
        $this->assertSame($refusal, CommonsFileResolver::refusal($meta));
    }
}
