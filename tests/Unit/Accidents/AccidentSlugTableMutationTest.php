<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentSlugTable;
use App\Services\Accidents\AccidentSourceRef;
use PHPUnit\Framework\TestCase;

/** Table des noms de fiche retenus : clés « fournisseur:identifiant », anciens noms, ordre des sources. */
class AccidentSlugTableMutationTest extends TestCase
{
    private static function ref(string $provider, string $id): AccidentSourceRef
    {
        return new AccidentSourceRef($provider, $id, 'list', 't', 'https://example.org', 'CC');
    }

    /** @return list<array{key: string, from: string, slug: string, reason: string}> */
    private static function rows(): array
    {
        return [
            ['key' => 'wikipedia:fr:a', 'from' => '1900-01-01-ancien', 'slug' => '1900-01-01-nouveau', 'reason' => 'r1'],
            ['key' => 'bea-tt:b', 'from' => '1910-01-01-meme', 'slug' => '1910-01-01-meme', 'reason' => 'r2'],
            ['key' => 'manual:c', 'from' => '1920-01-01-x', 'slug' => '1920-01-01-y', 'reason' => 'r3'],
        ];
    }

    public function test_the_key_is_the_provider_then_a_colon_then_the_identifier(): void
    {
        $table = new AccidentSlugTable(self::rows());

        $this->assertSame('1900-01-01-nouveau', $table->retained([self::ref('wikipedia', 'fr:a')]));
        $this->assertSame('1910-01-01-meme', $table->retained([self::ref('bea-tt', 'b')]));
        $this->assertNull($table->retained([self::ref('fr', 'a')]));
        $this->assertNull($table->retained([self::ref('wikipedia', 'a')]));
        $this->assertNull($table->retained([self::ref('manual', 'C')]));
        $this->assertNull($table->retained([]));
    }

    public function test_the_first_listed_source_found_in_the_table_decides(): void
    {
        $table = new AccidentSlugTable(self::rows());

        $this->assertSame('1920-01-01-y', $table->retained([self::ref('x', 'y'), self::ref('manual', 'c'), self::ref('wikipedia', 'fr:a')]));
        $this->assertSame('1900-01-01-nouveau', $table->retained([self::ref('wikipedia', 'fr:a'), self::ref('manual', 'c')]));
    }

    public function test_only_renamed_slugs_are_retired(): void
    {
        $table = new AccidentSlugTable(self::rows());

        $this->assertTrue($table->isRetired('1900-01-01-ancien'));
        $this->assertTrue($table->isRetired('1920-01-01-x'));
        $this->assertFalse($table->isRetired('1910-01-01-meme'), 'un nom inchangé n\'est pas un ancien nom');
        $this->assertFalse($table->isRetired('1900-01-01-nouveau'));
        $this->assertFalse($table->isRetired('inconnu'));
    }

    public function test_the_rows_are_given_back_as_they_were_passed(): void
    {
        $this->assertSame(self::rows(), (new AccidentSlugTable(self::rows()))->rows());
        $this->assertSame([], (new AccidentSlugTable([]))->rows());
        $this->assertNull((new AccidentSlugTable([]))->retained([self::ref('a', 'b')]));
    }

    public function test_without_rows_the_versioned_file_is_read(): void
    {
        $file = require dirname(__DIR__, 3).'/database/data/accident_slugs.php';
        $table = new AccidentSlugTable;

        $this->assertSame($file, $table->rows());
        $first = $file[0];
        [$provider, $id] = explode(':', $first['key'], 2);
        $this->assertSame($first['slug'], $table->retained([self::ref($provider, $id)]));
        $this->assertTrue($table->isRetired($first['from']));
    }
}
