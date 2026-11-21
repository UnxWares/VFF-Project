<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\NotableUnits;
use App\Support\SourceRights;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rames remarquables (NotableUnits) sur des données d'essai : langue de repli, licence de chaque source,
 * recherche par surnom (accents, casse, début de surnom, surnom dans une phrase). Le fichier réel est
 * contrôlé par tests/Feature/Materials/NotableUnitsTest.
 */
class NotableUnitsTest extends TestCase
{
    private static function units(): NotableUnits
    {
        $rights = new SourceRights(['providers' => [
            'libre' => ['nom' => 'Libre', 'statut' => SourceRights::SAFE, 'licence' => 'CC0'],
            'tiers' => ['nom' => 'Tiers', 'statut' => SourceRights::UNCERTAIN, 'licence' => 'Pas de licence'],
            'ferme' => ['nom' => 'Fermé', 'statut' => SourceRights::FORBIDDEN],
            'bizarre' => ['nom' => 'Sans statut'],
        ]]);
        $fact = fn (string $kind, string $source, array $text, array $extra = []): array => $extra + [
            'kind' => $kind, 'date' => '1981-01-01', 'precision' => 'year', 'source' => $source, 'text' => $text,
        ];

        return new NotableUnits($rights, [
            'sources' => [
                's-libre' => ['provider' => 'libre', 'publisher' => 'Éditeur', 'url' => 'https://example.org/a', 'date' => '2020-01-01'],
                's-tiers' => ['provider' => 'tiers', 'publisher' => 'Presse', 'url' => 'https://example.org/b', 'reported_by' => 'Radio'],
                's-ferme' => ['provider' => 'ferme', 'publisher' => 'Fermé', 'url' => 'https://example.org/c'],
                's-bizarre' => ['provider' => 'bizarre', 'publisher' => 'X', 'url' => 'https://example.org/d'],
                's-absent' => ['provider' => 'inconnu', 'publisher' => 'Y', 'url' => 'https://example.org/e'],
            ],
            'units' => [
                'tgv-sud-est' => [
                    ['number' => 1, 'nickname' => 'Patrick', 'unknown' => ['fr' => 'Inconnu', 'en' => 'Unknown'], 'facts' => [
                        $fact('nickname', 's-tiers', ['fr' => 'Surnom', 'en' => 'Nickname', 'de' => 'Spitzname'], ['quote' => 'court']),
                        $fact('record', 's-libre', ['fr' => 'Record'], ['date_to' => '1981-12-31']),
                    ]],
                    ['number' => '02', 'nickname' => 'Pat', 'facts' => []],
                ],
                'bb-15000' => [
                    ['number' => '15001', 'nickname' => null, 'facts' => [
                        $fact('a', 's-ferme', ['fr' => 'A']),
                        $fact('b', 's-bizarre', ['fr' => 'B']),
                        $fact('c', 's-absent', ['fr' => 'C']),
                    ]],
                ],
                'z-2n2' => [
                    ['number' => '7', 'nickname' => 'Hélène', 'facts' => []],
                    ['number' => '8', 'nickname' => 'Le  Mistral', 'facts' => []],
                ],
            ],
        ]);
    }

    public function test_texts_in_the_requested_language_with_french_fallback(): void
    {
        $en = self::units()->forMaterial('tgv-sud-est', 'en');
        $this->assertSame('1', $en[0]['number']);
        $this->assertSame('Unknown', $en[0]['unknown']);
        $this->assertSame(['Nickname', 'Record'], array_column($en[0]['facts'], 'text'));
        $de = self::units()->forMaterial('tgv-sud-est', 'de');
        $this->assertSame('Inconnu', $de[0]['unknown']);
        $this->assertSame(['Spitzname', 'Record'], array_column($de[0]['facts'], 'text'));
        // Langue non prise en charge : le français.
        $this->assertSame('Surnom', self::units()->forMaterial('tgv-sud-est', 'it')[0]['facts'][0]['text']);
        $this->assertNull(self::units()->forMaterial('tgv-sud-est')[1]['unknown']);
        $this->assertSame([], self::units()->forMaterial('inconnue'));
    }

    public function test_every_fact_carries_its_source_and_licence_status(): void
    {
        [$nick, $record] = self::units()->forMaterial('tgv-sud-est')[0]['facts'];
        $this->assertSame([
            'id' => 's-tiers', 'publisher' => 'Presse', 'reported_by' => 'Radio', 'date' => null, 'url' => 'https://example.org/b',
            'provider' => 'tiers', 'license' => 'Pas de licence', 'license_status' => 'uncertain',
        ], $nick['source']);
        $this->assertSame('court', $nick['quote']);
        $this->assertNull($nick['date_to']);
        $this->assertSame(['verified', null, '2020-01-01', '1981-12-31', null], [
            $record['source']['license_status'], $record['source']['reported_by'], $record['source']['date'], $record['date_to'], $record['quote'],
        ]);
        $facts = self::units()->forMaterial('bb-15000')[0]['facts'];
        $this->assertSame(['forbidden', null, null], array_map(fn (array $f): ?string => $f['source']['license_status'], $facts));
        $this->assertSame([null, null, null], array_map(fn (array $f): ?string => $f['source']['license'], $facts));
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function searches(): iterable
    {
        yield 'surnom exact' => ['Patrick', ['tgv-sud-est' => '1']];
        yield 'casse et espaces' => ['  PATRICK ', ['tgv-sud-est' => '1']];
        yield 'début de surnom, trois lettres' => ['pat', ['tgv-sud-est' => '1']];
        yield 'deux lettres : trop court' => ['pa', []];
        yield 'surnom court exact' => ['Pat', ['tgv-sud-est' => '1']];
        yield 'surnom dans une phrase' => ['TGV Patrick rame 01', ['tgv-sud-est' => '1']];
        yield 'surnom collé à un autre mot' => ['tgvpatrick', []];
        yield 'accents ignorés' => ['helene', ['z-2n2' => '7']];
        yield 'accents dans la recherche' => ['HÉLÈNE', ['z-2n2' => '7']];
        yield 'espaces réduits dans le surnom' => ['le mistral', ['z-2n2' => '8']];
        yield 'recherche vide' => ['   ', []];
        yield 'rien' => ['autorail', []];
    }

    /** @param array<string, string> $expected série => numéro */
    #[DataProvider('searches')]
    public function test_search_by_nickname(string $query, array $expected): void
    {
        $this->assertSame($expected, array_map(fn (array $u): string => $u['number'], self::units()->matching($query)));
    }

    public function test_the_first_matching_unit_of_a_series_wins(): void
    {
        // « Pat » et « Patrick » répondent à « pat » : la première rame de la série est gardée.
        $this->assertSame(['tgv-sud-est' => ['number' => '1', 'nickname' => 'Patrick']], self::units()->matching('pat'));
        $this->assertSame(['tgv-sud-est', 'bb-15000', 'z-2n2'], array_keys(self::units()->all()));
        $this->assertArrayHasKey('s-absent', self::units()->sources());
    }
}
