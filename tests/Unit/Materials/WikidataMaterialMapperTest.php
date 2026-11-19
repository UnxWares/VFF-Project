<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\WikidataMaterialMapper;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use Tests\TestCase;

/**
 * Traduction d'un élément Wikidata en fiche VFF : aucun champ estimé, provenance par champ,
 * prudence sur les dates, les nombres et les unités.
 */
class WikidataMaterialMapperTest extends TestCase
{
    private WikidataMaterialMapper $mapper;

    /** Libellés des éléments cités. */
    private const LABELS = [
        'Q309084' => ['fr' => 'Alstom', 'en' => 'Alstom'],
        'Q3300038' => ['fr' => 'Matériel de Traction Électrique', 'en' => null],
        'Q13646' => ['fr' => 'SNCF', 'en' => 'SNCF'],
        'Q106530439' => ['fr' => 'électrification ferroviaire 25 kV 50 Hz', 'en' => null],
        'Q1999572' => ['fr' => 'écartement standard', 'en' => 'standard gauge'],
        'Q24686956' => ['fr' => "B'B'", 'en' => "Bo'Bo'"],
    ];

    private const COMMONS = [
        'BB 15025.JPG' => [
            'file_title' => 'File:BB 15025.JPG', 'thumb_url' => 'https://upload.wikimedia.org/thumb/BB_15025.JPG/500px-BB_15025.JPG',
            'display_url' => 'https://upload.wikimedia.org/BB_15025.JPG', 'source_url' => 'https://commons.wikimedia.org/wiki/File:BB_15025.JPG',
            'width' => 4928, 'height' => 3264, 'author' => 'Florian Fèvre', 'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'rights_status' => 'open_license',
            'commercial_ok' => true, 'share_alike' => true, 'attribution_required' => true,
            'attribution' => 'Florian Fèvre, CC BY-SA 4.0, via Wikimedia Commons',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new WikidataMaterialMapper(WikidataMaterialRules::default());
    }

    /** Élément BB 15000, tel que réduit par WikidataMaterialSource::trimEntity. */
    private function bb15000(array $claims = []): array
    {
        $v = fn (mixed $value, array $q = [], string $rank = 'normal'): array => ['v' => $value, 'q' => $q, 'rank' => $rank];

        return [
            'labels' => ['fr' => 'SNCF BB 15000', 'en' => 'SNCF Class BB 15000'],
            'descriptions' => ['fr' => 'série de 65 locomotives électriques de la SNCF'],
            'aliases' => ['fr' => ['BB 15000', 'Nez cassé']],
            'sitelinks' => ['frwiki' => 'BB 15000', 'enwiki' => 'SNCF Class BB 15000'],
            'claims' => $claims + [
                'P176' => [$v('Q309084'), $v('Q3300038')],
                'P137' => [$v('Q13646', ['P580' => [['time' => '+1971-05-01T00:00:00Z', 'precision' => 11]]])],
                'P729' => [$v(['time' => '+1971-00-00T00:00:00Z', 'precision' => 9])],
                'P1092' => [$v(['amount' => 65.0, 'unit' => null])],
                'P2052' => [$v(['amount' => 160.0, 'unit' => 'Q180154'])],
                'P930' => [$v('Q106530439')],
                'P1064' => [$v('Q1999572')],
                'P2978' => [$v('Q24686956')],
                'P2802' => [$v("15001 \u{2013} 15065")],
                'P18' => [$v('Autre.jpg'), $v('BB 15025.JPG', [], 'preferred')],
                'P373' => [$v('SNCF Class BB 15000')],
            ],
        ];
    }

    private function map(array $entity, array $ancestors = ['Q625151'], array $categories = []): array|string
    {
        return $this->mapper->map('Q746572', $entity, $ancestors, self::LABELS, ['Q1999572' => 1435], self::COMMONS, $categories);
    }

    public function test_an_entity_becomes_a_complete_sheet_with_provenance_per_field(): void
    {
        $sheet = $this->map($this->bb15000());

        $this->assertIsArray($sheet);
        $this->assertSame('BB 15000', $sheet['name'], 'titre Wikipédia, sans « SNCF »');
        $this->assertSame('locomotive_electrique', $sheet['type']);
        $this->assertSame(['SNCF BB 15000', 'SNCF Class BB 15000', 'Nez cassé'], $sheet['designations'], 'sans le nom ni doublon');
        $this->assertSame('Alstom, Matériel de Traction Électrique', $sheet['builder']);
        $this->assertSame([['name' => 'SNCF', 'wikidata_id' => 'Q13646', 'from_year' => 1971, 'to_year' => null, 'units' => null]], $sheet['operators']);
        $this->assertSame([1971, null, 65, 160], [$sheet['in_service_from'], $sheet['in_service_to'], $sheet['units_built'], $sheet['max_speed_kmh']]);
        $this->assertNull($sheet['power_kw'], 'non renseignée : jamais estimée');
        $this->assertSame(1435, $sheet['gauge_mm']);
        $this->assertSame("B'B'", $sheet['wheel_arrangement']);
        $this->assertSame('15001 - 15065', $sheet['fleet_numbers'], 'tiret moyen remplacé');
        $this->assertSame('File:BB 15025.JPG', $sheet['image']['file'], 'image de rang préféré');
        $this->assertSame('Florian Fèvre, CC BY-SA 4.0, via Wikimedia Commons', $sheet['image']['attribution']);

        $this->assertSame(['source' => 'wikidata', 'ref' => 'Q746572#P729'], $sheet['provenance']['in_service_from']);
        $this->assertSame(['source' => 'wikidata', 'ref' => 'Q746572#P279:Q625151'], $sheet['provenance']['type']);
        $this->assertSame('commons', $sheet['provenance']['image']['source']);
        $this->assertArrayNotHasKey('power_kw', $sheet['provenance'], 'pas de provenance pour un champ vide');
    }

    public function test_a_decade_is_not_a_year_and_an_impossible_retirement_is_dropped(): void
    {
        $decade = ['v' => ['time' => '+1970-00-00T00:00:00Z', 'precision' => 8], 'q' => [], 'rank' => 'normal'];
        $sheet = $this->map($this->bb15000(['P729' => [$decade]]));
        $this->assertNull($sheet['in_service_from']);

        $before = ['v' => ['time' => '+1960-00-00T00:00:00Z', 'precision' => 9], 'q' => [], 'rank' => 'normal'];
        $sheet = $this->map($this->bb15000(['P730' => [$before]]));
        $this->assertSame(1971, $sheet['in_service_from']);
        $this->assertNull($sheet['in_service_to']);
    }

    public function test_an_ambiguous_number_built_is_left_empty(): void
    {
        $count = fn (float $n, array $q = []): array => ['v' => ['amount' => $n, 'unit' => null], 'q' => $q, 'rank' => 'normal'];

        $sheet = $this->map($this->bb15000(['P1092' => [$count(10, ['P580' => [[]]]), $count(20, ['P580' => [[]]])]]));
        $this->assertNull($sheet['units_built']);

        $sheet = $this->map($this->bb15000(['P1092' => [$count(10, ['P580' => [[]]]), $count(30)]]));
        $this->assertSame(30, $sheet['units_built'], 'la seule valeur sans qualificatif');
    }

    public function test_a_converted_power_keeps_its_original_value(): void
    {
        $sheet = $this->map($this->bb15000(['P2109' => [
            ['v' => ['amount' => 2000.0, 'unit' => 'Q160857'], 'q' => [], 'rank' => 'normal'],
            ['v' => ['amount' => 1000.0, 'unit' => 'Q3320608'], 'q' => [], 'rank' => 'normal'],
        ]]));

        $this->assertSame(1471, $sheet['power_kw'], 'la plus grande');
        $this->assertSame(['amount' => 2000.0, 'unit' => 'Q160857'], $sheet['provenance']['power_kw']['original']);
    }

    public function test_series_outside_the_scope_are_skipped_with_a_reason(): void
    {
        $this->assertSame(WikidataMaterialMapper::SKIP_NO_ENTITY, $this->mapper->map('Q1', null, [], [], [], []));
        $this->assertSame(WikidataMaterialMapper::SKIP_METRO, $this->map($this->bb15000(), ['Q625151', 'Q1144784']));
        $this->assertSame(WikidataMaterialMapper::SKIP_NO_TYPE, $this->map($this->bb15000(), []));
        $foreign = $this->bb15000(['P137' => [['v' => 'Q3359730', 'q' => [], 'rank' => 'normal']]]);
        $this->assertSame(WikidataMaterialMapper::SKIP_FOREIGN, $this->map($foreign));

        $nameless = ['labels' => [], 'sitelinks' => []] + $this->bb15000();
        $this->assertSame(WikidataMaterialMapper::SKIP_NO_NAME, $this->map($nameless));
    }

    public function test_wikipedia_categories_rank_a_series_that_wikidata_does_not_classify(): void
    {
        $sheet = $this->map($this->bb15000(), [], ['Locomotive électrique de la SNCF']);
        $this->assertSame('locomotive_electrique', $sheet['type']);
        $this->assertSame(['source' => 'wikipedia', 'ref' => 'Catégorie:Locomotive électrique de la SNCF'], $sheet['provenance']['type']);

        $this->assertSame(WikidataMaterialMapper::SKIP_METRO, $this->map($this->bb15000(), [], ['Matériel roulant du métro de Lyon']));
    }

    public function test_the_label_is_used_when_there_is_no_french_article(): void
    {
        $entity = ['sitelinks' => []] + $this->bb15000();
        $sheet = $this->map($entity);

        $this->assertSame('SNCF BB 15000', $sheet['name']);
        $this->assertSame('Q746572#label:fr', $sheet['provenance']['name']['ref']);
    }

    /** Infobox lue dans l'article Wikipédia de la série. */
    private function infobox(array $parameters, bool $shared = false): array
    {
        return ['title' => 'BB 15000', 'revid' => 237800094, 'parameters' => $parameters, 'read_on' => '2026-11-19'] + ($shared ? ['shared' => true] : []);
    }

    public function test_a_railcar_becomes_bimode_only_when_wikidata_or_its_article_says_so(): void
    {
        // Wikidata : classe « engin ferroviaire bi-modes ».
        $sheet = $this->map($this->bb15000(), ['Q1567915', 'Q483373', 'Q28455463']);
        $this->assertSame('bimode', $sheet['type']);
        $this->assertSame('Q746572#P279:Q28455463', $sheet['provenance']['type']['ref']);

        // Un autorail sans classe bimode reste autorail ; son article l'annonce bimode : sa catégorie change, avec la révision citée.
        $this->assertSame('autorail', $this->map($this->bb15000(), ['Q1567915'])['type']);
        $sheet = $this->mapper->map('Q746572', $this->bb15000(), ['Q1567915'], self::LABELS, [], [], [], $this->infobox(['type' => 'automotrice bimode']));
        $this->assertSame('bimode', $sheet['type']);
        $this->assertSame(['source' => 'wikipedia', 'ref' => 'BB 15000#infobox:Type', 'revid' => 237800094], $sheet['provenance']['type']);

        // Une locomotive n'est pas reclassée par l'infobox (la famille Traxx écrit « bimode » dans sa motorisation).
        $sheet = $this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['type' => 'automotrice bimode']));
        $this->assertSame('locomotive_electrique', $sheet['type']);
    }

    public function test_the_service_state_comes_only_from_a_source_with_its_reading_date(): void
    {
        $withoutSource = $this->map($this->bb15000());
        $this->assertNull($withoutSource['service_status'], 'une date de retrait absente ne prouve pas que la série circule');
        $this->assertNull($withoutSource['service_status_checked_at']);
        $this->assertArrayNotHasKey('service_status', $withoutSource['provenance']);

        // Effectif de l'infobox, daté d'une année récente.
        $sheet = $this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '1 au 13 07 2026']));
        $this->assertSame(['in_service', '2026-11-19'], [$sheet['service_status'], $sheet['service_status_checked_at']]);
        $this->assertSame(['source' => 'wikipedia', 'ref' => 'BB 15000#infobox:Effectif', 'revid' => 237800094, 'as_of' => '2026-07-13', 'units' => 1], $sheet['provenance']['service_status']);

        // Effectif nul : retirée. Effectif d'une année trop ancienne, ou sans date : rien.
        $this->assertSame('withdrawn', $this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '0 en 2025']))['service_status']);
        $this->assertNull($this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '12 rames (en 2012)']))['service_status']);
        $this->assertNull($this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '12 rames']))['service_status']);
    }

    public function test_the_fleet_of_an_article_shared_by_several_series_is_not_given_to_one_of_them(): void
    {
        $sheet = $this->mapper->map('Q746572', $this->bb15000(), ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '513 au 18 05 2026'], shared: true));

        $this->assertSame('in_service', $sheet['service_status']);
        $this->assertArrayNotHasKey('units', $sheet['provenance']['service_status']);
    }

    public function test_wikidata_state_of_use_comes_first_and_keeps_its_value_and_date(): void
    {
        $entity = ['read_on' => '2026-11-18'] + $this->bb15000(['P5817' => [['v' => 'Q55654238', 'q' => ['P585' => [['time' => '+2025-03-00T00:00:00Z', 'precision' => 10]]], 'rank' => 'normal']]]);
        $sheet = $this->mapper->map('Q746572', $entity, ['Q625151'], self::LABELS, [], [], [], $this->infobox(['effectif' => '0 en 2025']));

        $this->assertSame(['in_service', '2026-11-18'], [$sheet['service_status'], $sheet['service_status_checked_at']]);
        $this->assertSame(['source' => 'wikidata', 'ref' => 'Q746572#P5817', 'value' => 'Q55654238', 'as_of' => '2025-03'], $sheet['provenance']['service_status']);

        // Valeur inconnue (« abandonné », par exemple) : aucun état.
        $unknown = ['read_on' => '2026-11-18'] + $this->bb15000(['P5817' => [['v' => 'Q1', 'q' => [], 'rank' => 'normal']]]);
        $this->assertNull($this->map($unknown)['service_status']);
    }

    public function test_the_discontinued_date_is_used_when_there_is_no_retirement_date(): void
    {
        $end = ['v' => ['time' => '+1999-00-00T00:00:00Z', 'precision' => 9], 'q' => [], 'rank' => 'normal'];
        $sheet = $this->map($this->bb15000(['P2669' => [$end]]));

        $this->assertSame(1999, $sheet['in_service_to']);
        $this->assertSame('Q746572#P2669', $sheet['provenance']['in_service_to']['ref']);
    }

    public function test_a_series_attested_on_a_french_line_keeps_its_attestation_and_skips_the_operator_rule(): void
    {
        $foreign = $this->bb15000(['P137' => [['v' => 'Q3359730', 'q' => [], 'rank' => 'normal']]]);
        $attestation = ['source' => 'wikipedia', 'ref' => 'LGV Est européenne'];

        $sheet = $this->mapper->map('Q746572', $foreign, ['Q625151'], self::LABELS, [], [], [], null, $attestation);

        $this->assertIsArray($sheet);
        $this->assertSame('attested', $sheet['scope']);
        $this->assertSame($attestation, $sheet['provenance']['scope']);
        // Une série rattachée à la France par Wikidata est du périmètre « france » avec sa propre provenance.
        $french = $this->map($this->bb15000());
        $this->assertSame(['france', ['source' => 'wikidata', 'ref' => 'Q746572#P17|P137']], [$french['scope'], $french['provenance']['scope']]);
        // Le métro reste écarté même attesté.
        $this->assertSame(WikidataMaterialMapper::SKIP_METRO, $this->mapper->map('Q746572', $foreign, ['Q1144784'], self::LABELS, [], [], [], null, $attestation));
    }

    public function test_entities_are_trimmed_to_useful_claims_without_deprecated_ones(): void
    {
        $snak = fn (string $id): array => ['snaktype' => 'value', 'datavalue' => ['type' => 'wikibase-entityid', 'value' => ['id' => $id]]];
        $trimmed = WikidataMaterialSource::trimEntity([
            'labels' => ['fr' => ['value' => 'X 73500'], 'de' => ['value' => 'ignoré']],
            'claims' => [
                'P176' => [
                    ['mainsnak' => $snak('Q309084'), 'rank' => 'normal', 'qualifiers' => ['P1092' => [[
                        'snaktype' => 'value', 'datavalue' => ['type' => 'quantity', 'value' => ['amount' => '+331', 'unit' => '1']],
                    ]]]],
                    ['mainsnak' => $snak('Q1'), 'rank' => 'deprecated'],
                ],
                'P9999' => [['mainsnak' => $snak('Q2'), 'rank' => 'normal']],
                'P730' => [['mainsnak' => ['snaktype' => 'novalue'], 'rank' => 'normal']],
            ],
        ]);

        $this->assertSame(['fr' => 'X 73500'], $trimmed['labels']);
        $this->assertSame([['v' => 'Q309084', 'q' => ['P1092' => [['amount' => 331.0, 'unit' => null]]], 'rank' => 'normal']], $trimmed['claims']['P176']);
        $this->assertArrayNotHasKey('P9999', $trimmed['claims']);
        $this->assertArrayNotHasKey('P730', $trimmed['claims'], 'pas de valeur');
    }
}
