<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\Sncf\SardoParser;
use App\Services\Archives\Sncf\SncfArchivesImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Notices du jeu de données « Archives SNCF » : lecture (dates, types, thèmes exclus), titre préparé pour le
 * rattachement (noms de compagnies retirés, listes de lignes) et jugement par le comparateur de Gallica, sur des
 * lignes construites sans base. Titres réels du jeu de données (22/11/2026).
 */
class SncfArchivesTest extends TestCase
{
    /** @param array<string, mixed> $row */
    private function row(array $row): array
    {
        return $row + [
            'titre' => 'Gare de Flers située sur la ligne d\'Argentan à Granville', 'type_de_document' => 'Image',
            'url' => 'https://www.archives.sncf.com/gare-de-flers/', 'ndeg_dossier' => '12001', 'theme' => 'Aménagement du territoire : réseau et gares',
            'debut' => null, 'fin' => null, 'date_de_prise_de_vue' => '1983-05-01 00:00:00',
        ];
    }

    public function test_a_record_keeps_only_metadata_and_cleans_spaces(): void
    {
        $record = (new SardoParser)->parse($this->row([
            'titre' => "Hall de la gare d'Aix-les-Bains-Le Revard située sur les lignes de Culoz à\u{00A0} Modane (frontière)",
            'description' => 'Un agent nommé dans la description',
        ]));

        $this->assertNotNull($record);
        $this->assertSame("Hall de la gare d'Aix-les-Bains-Le Revard située sur les lignes de Culoz à Modane (frontière)", $record->title);
        $this->assertSame('image', $record->docType);
        $this->assertSame('12001', $record->reference);
        $this->assertSame('month', $record->date->precision);
        $this->assertSame('1983-05-01', $record->date->exact);
        // La description n'est que lue pour juger le rattachement (SncfArchiveDescriptionsTest : jamais enregistrée).
        $this->assertSame('Un agent nommé dans la description', $record->descriptionText);
    }

    /** @return iterable<string, array{array<string, mixed>, string, ?int, ?int, ?string, string}> */
    public static function dates(): iterable
    {
        yield 'jour de prise de vue' => [['date_de_prise_de_vue' => '1958-09-27 00:00:00'], '1958-09-27', 1958, null, '1958-09-27', 'day'];
        yield '1er janvier : année' => [['date_de_prise_de_vue' => '1960-01-01 00:00:00'], '1960', 1960, null, null, 'year'];
        yield 'jj/mm/aaaa' => [['date_de_prise_de_vue' => '15/06/1861'], '1861-06-15', 1861, null, '1861-06-15', 'day'];
        yield 'mm/aaaa' => [['date_de_prise_de_vue' => '06/1944'], '1944-06', 1944, null, '1944-06-01', 'month'];
        yield 'intervalle de prise de vue' => [['date_de_prise_de_vue' => '1950-1955'], '1950-1955', 1950, 1955, null, 'range'];
        yield 'dossier sur une année' => [['debut' => '1942', 'fin' => '1942', 'date_de_prise_de_vue' => null], '1942', 1942, null, null, 'year'];
        yield 'dossier sur plusieurs années' => [['debut' => '1941', 'fin' => '1950'], '1941-1950', 1941, 1950, null, 'range'];
        yield 'illisible' => [['date_de_prise_de_vue' => 'janv.-45'], '', null, null, null, 'unknown'];
    }

    /** @param array<string, mixed> $row */
    #[DataProvider('dates')]
    public function test_dates_are_read_at_their_precision(array $row, string $label, ?int $year, ?int $to, ?string $exact, string $precision): void
    {
        $date = SardoParser::date($this->row($row));

        $this->assertSame($label, $date->label ?? '');
        $this->assertSame($year, $date->year);
        $this->assertSame($to, $date->yearTo);
        $this->assertSame($exact, $date->exact);
        $this->assertSame($precision, $date->precision);
    }

    public function test_records_about_people_or_without_link_are_left_out(): void
    {
        $parser = new SardoParser;

        $this->assertNull($parser->parse($this->row(['theme' => 'FICHIERS DES CHEMINOTS BLESSES ET TUES PENDANT LA SECONDE GUERRE MONDIALE'])));
        $this->assertNull($parser->parse($this->row(['theme' => 'DOSSIERS DE CONTENTIEUX'])));
        $this->assertNull($parser->parse($this->row(['theme' => 'Les trains, QUESTIONS DE PERSONNEL'])));
        $this->assertNull($parser->parse($this->row(['url' => 'https://example.org/notice'])));
        $this->assertNull($parser->parse($this->row(['type_de_document' => null])));
        $this->assertNull($parser->parse($this->row(['titre' => '  '])));
        $this->assertSame('video', $parser->parse($this->row(['type_de_document' => 'Vidéo']))?->docType);
    }

    public function test_company_names_do_not_count_as_lines(): void
    {
        $this->assertSame(
            "Etat du recrutement des femmes au sein des équipes d'entretien de la compagnie",
            SardoParser::withoutCompanies("Etat du recrutement des femmes au sein des équipes d'entretien de la Compagnie du Chemin de fer de Paris à Orléans"),
        );
        $this->assertSame(
            'Lettre de la compagnie concernant le recrutement',
            SardoParser::withoutCompanies('Lettre de la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée concernant le recrutement'),
        );
        // Une ponctuation termine le nom : la ligne citée ensuite reste.
        $this->assertSame('compagnie, ligne de Tours à Nantes', SardoParser::withoutCompanies("Compagnie d'Orléans, ligne de Tours à Nantes"));
    }

    public function test_each_route_after_lignes_is_read_as_a_line(): void
    {
        $this->assertSame(
            "Gare d'Argentan située sur les lignes du Mans à Mézidon et ligne d'Argentan à Granville",
            SardoParser::listedLines("Gare d'Argentan située sur les lignes du Mans à Mézidon et d'Argentan à Granville"),
        );
        $this->assertSame(
            'Gare de Brive située sur les lignes des Aubrais à Montauban, ligne de Coutras à Tulle et ligne de Brive à Toulouse',
            SardoParser::listedLines('Gare de Brive située sur les lignes des Aubrais à Montauban, de Coutras à Tulle et de Brive à Toulouse'),
        );
        // Une seule ligne : rien ne change ; la liste s'arrête à la fin de la proposition.
        $this->assertSame('Gare de Flers sur la ligne d\'Argentan à Granville et de Paris', SardoParser::listedLines('Gare de Flers sur la ligne d\'Argentan à Granville et de Paris'));
        $this->assertSame('Viaduc (lignes de X à Y). Puis et de Z', SardoParser::listedLines('Viaduc (lignes de X à Y). Puis et de Z'));
    }

    private function index(): LinePlaceIndex
    {
        return new LinePlaceIndex([
            1 => LinePlaceIndex::linePlaces([
                'id' => 1, 'slug' => 'rfn-430000', 'name' => 'Ligne du Mans à Mézidon',
                'stations' => [['name' => 'Le Mans', 'city' => 'Le Mans', 'pk_m' => 0], ['name' => 'Alençon', 'city' => 'Alençon', 'pk_m' => 50000],
                    ['name' => 'Argentan', 'city' => 'Argentan', 'pk_m' => 100000], ['name' => 'Mézidon', 'city' => 'Mézidon Vallée d\'Auge', 'pk_m' => 140000]],
                'first_year' => 1856, 'open_today' => true,
            ]),
            2 => LinePlaceIndex::linePlaces([
                'id' => 2, 'slug' => 'rfn-425000', 'name' => "Ligne d'Argentan à Granville",
                'stations' => [['name' => 'Argentan', 'city' => 'Argentan', 'pk_m' => 0], ['name' => 'Flers', 'city' => 'Flers', 'pk_m' => 40000],
                    ['name' => 'Granville', 'city' => 'Granville', 'pk_m' => 130000]],
                'first_year' => 1861, 'open_today' => true,
            ]),
            3 => LinePlaceIndex::linePlaces([
                'id' => 3, 'slug' => 'rfn-570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean',
                'stations' => [['name' => 'Paris-Austerlitz', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Orléans', 'city' => 'Orléans', 'pk_m' => 120000],
                    ['name' => 'Bordeaux-St-Jean', 'city' => 'Bordeaux', 'pk_m' => 580000]],
                'first_year' => 1840, 'open_today' => true,
            ]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<string>> titre → lignes retenues
     */
    private function winners(array $rows): array
    {
        $result = (new SncfArchivesImporter)->run($this->index(), $rows);
        $out = [];
        foreach ($result['candidates'] as $c) {
            $out[$c['title']] ??= [];
            if ($c['accepted']) {
                $out[$c['title']][] = $c['line'];
            }
        }

        return $out;
    }

    public function test_the_matcher_attaches_photos_to_the_named_lines_only(): void
    {
        $titles = [
            "Esplanade de la gare d'Alençon située sur la ligne du Mans à Mézidon",
            "Gare d'Argentan située sur les lignes du Mans à Mézidon et d'Argentan à Granville",
            'Compagnie du Chemin de fer de Paris à Orléans : voitures à voyageurs',
            "Etat du recrutement des femmes au sein des équipes d'entretien de la Compagnie du Chemin de fer de Paris à Orléans",
        ];
        $rows = array_map(fn (string $t): array => $this->row(['titre' => $t, 'url' => 'https://www.archives.sncf.com/'.md5($t).'/']), $titles);

        $winners = $this->winners($rows);

        $this->assertSame(['rfn-430000'], $winners[$titles[0]]);
        $this->assertEqualsCanonicalizing(['rfn-430000', 'rfn-425000'], $winners[$titles[1]]);
        // Le nom de la compagnie retiré, le titre ne nomme plus deux lieux : aucune ligne n'est jugée.
        $this->assertArrayNotHasKey($titles[2], $winners);
        $this->assertArrayNotHasKey($titles[3], $winners);
    }

    public function test_a_notice_listed_twice_is_judged_once(): void
    {
        $row = $this->row(['titre' => "Esplanade de la gare d'Alençon située sur la ligne du Mans à Mézidon"]);
        $result = (new SncfArchivesImporter)->run($this->index(), [$row, $row]);

        $this->assertSame(1, $result['stats']['kept']);
        $this->assertSame(1, $result['stats']['accepted']);
        $this->assertSame(11, $result['documents'][1][0]['score']);
        $this->assertSame('month', $result['documents'][1][0]['date_precision']);
    }
}
