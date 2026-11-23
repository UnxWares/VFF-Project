<?php

namespace Tests\Unit\Heritage;

use App\Services\Heritage\WikidataStationHeritage;
use PHPUnit\Framework\TestCase;

/** Regroupement des lignes SPARQL (produit cartésien des propriétés multiples) par élément. */
class WikidataStationHeritageTest extends TestCase
{
    private static function row(array $cells): array
    {
        return array_map(fn (string $v): array => ['value' => $v], $cells);
    }

    public function test_fold_merges_the_cartesian_rows_of_one_item(): void
    {
        $item = 'http://www.wikidata.org/entity/Q658277';
        $items = WikidataStationHeritage::fold([
            self::row(['item' => $item, 'prot' => 'http://www.wikidata.org/entity/Q10387575', 'start' => '1975-01-15T00:00:00Z', 'ref' => 'PA00100349',
                'archL' => 'Roger Gonthier', 'img' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Gare%20de%20Limoges%20B%C3%A9n%C3%A9dictins.jpg']),
            self::row(['item' => $item, 'prot' => 'http://www.wikidata.org/entity/Q3214312', 'ref' => 'PA00100349', 'archL' => 'Roger Gonthier',
                'built' => '1929-01-01T00:00:00Z', 'styleL' => 'Art déco']),
            // Statut hors liste (patrimoine d'intérêt régional) et référence invalide : ignorés.
            self::row(['item' => $item, 'prot' => 'http://www.wikidata.org/entity/Q56606003', 'ref' => 'pas-une-reference']),
        ]);

        $this->assertSame(['Q658277'], array_keys($items));
        $q = $items['Q658277'];
        $this->assertSame([['kind' => 'registered', 'class' => 'Q10387575', 'date' => '1975-01-15'], ['kind' => 'acr', 'class' => 'Q3214312', 'date' => null]], $q['protections']);
        $this->assertSame(['PA00100349'], $q['refs']);
        $this->assertSame(['Roger Gonthier'], $q['architects']);
        $this->assertSame(1929, $q['built']);
        $this->assertSame('Art déco', $q['style']);
        $this->assertSame('Gare de Limoges Bénédictins.jpg', $q['image']);
    }

    public function test_qid_date_and_file_name_helpers(): void
    {
        $this->assertSame('Q123', WikidataStationHeritage::qid('http://www.wikidata.org/entity/Q123'));
        $this->assertNull(WikidataStationHeritage::qid('http://exemple.org/autre'));
        $this->assertSame('1975-01-15', WikidataStationHeritage::date('1975-01-15T00:00:00Z'));
        $this->assertNull(WikidataStationHeritage::date('+1975'));
        $this->assertSame('A B.jpg', WikidataStationHeritage::fileName('http://commons.wikimedia.org/wiki/Special:FilePath/A%20B.jpg'));
        $this->assertNull(WikidataStationHeritage::fileName('http://exemple.org/A.jpg'));
    }

    public function test_every_protection_class_is_one_of_the_documented_kinds(): void
    {
        $this->assertSame(['classified', 'registered', 'acr', 'inventory'], array_values(array_unique(array_values(WikidataStationHeritage::PROTECTIONS))));
        // Q10387684 est « classé » et Q10387575 « inscrit » (libellés de Wikidata, relus le 23/11/2026).
        $this->assertSame('classified', WikidataStationHeritage::PROTECTIONS['Q10387684']);
        $this->assertSame('registered', WikidataStationHeritage::PROTECTIONS['Q10387575']);
    }
}
