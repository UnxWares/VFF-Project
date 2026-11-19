<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\AccidentPlaceTable;
use App\Services\Accidents\WikipediaListReader;
use PHPUnit\Framework\TestCase;

/**
 * Table de correspondance de lieux des puces sans lien lisible : elle ne vaut que pour la puce du bon jour dont
 * le texte nomme encore le lieu, et ne choisit jamais entre deux communes.
 */
class AccidentPlaceTableTest extends TestCase
{
    public function test_a_row_matches_only_its_day_and_its_extract(): void
    {
        $table = new AccidentPlaceTable([
            ['date' => '1869-07-03', 'needle' => 'en gare de Creil', 'commune' => 'Creil', 'department' => '60', 'basis' => 'en gare de Creil'],
        ]);

        $this->assertSame(['name' => 'Creil', 'department' => '60', 'basis' => 'en gare de Creil'], $table->find('1869-07-03', 'En gare de Creil, une locomotive part seule.'));
        $this->assertNull($table->find('1869-07-04', 'En gare de Creil, une locomotive part seule.'), 'un autre jour');
        $this->assertNull($table->find('1869-07-03', 'Près de Compiègne, une locomotive part seule.'), 'le texte ne nomme plus le lieu');
    }

    public function test_two_rows_of_different_communes_choose_nothing(): void
    {
        $table = new AccidentPlaceTable([
            ['date' => '1900-01-01', 'needle' => 'a creil', 'commune' => 'Creil', 'department' => '60', 'basis' => 'a'],
            ['date' => '1900-01-01', 'needle' => 'a angers', 'commune' => 'Angers', 'department' => '49', 'basis' => 'b'],
        ]);

        $this->assertNull($table->find('1900-01-01', 'À Creil puis à Angers, un accident.'));
    }

    public function test_the_shipped_table_is_well_formed_and_has_no_duplicate_extract(): void
    {
        $rows = (new AccidentPlaceTable)->rows();

        $this->assertGreaterThan(30, count($rows));
        $keys = [];
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
            $this->assertNotSame('', $row['needle']);
            $this->assertMatchesRegularExpression('/^(?:\d{2}|2[AB])$/', $row['department']);
            $this->assertNotSame('', $row['basis']);
            $this->assertArrayNotHasKey($row['date'].'|'.$row['needle'], $keys);
            $keys[$row['date'].'|'.$row['needle']] = true;
        }
    }

    public function test_the_reader_uses_the_table_before_its_own_guess(): void
    {
        $reader = new WikipediaListReader(new AccidentKindClassifier, new AccidentPlaceTable([
            ['date' => '1869-07-03', 'needle' => 'en gare de creil', 'commune' => 'Creil', 'department' => '60', 'basis' => 'en gare de Creil'],
        ]));

        $entries = $reader->read("* {{date|3|juillet|1869}} - En gare de Creil, une locomotive tamponne des wagons.<ref>x</ref>\n* {{date|4|juillet|1869}} - En gare de Creil, un autre accident.\n");

        $this->assertSame([['Creil', '60']], $entries[0]['places']);
        $this->assertSame('en gare de Creil', $entries[0]['place_basis']);
        $this->assertSame([], $entries[1]['places']);
        $this->assertNull($entries[1]['place_basis']);
    }
}
