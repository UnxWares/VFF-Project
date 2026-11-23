<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\Sncf\SardoParser;
use App\Services\Archives\Sncf\SncfArchivesImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture de la description des notices des Archives SNCF (jeu de données sous ODbL, champ lu pour juger le
 * rattachement, jamais enregistré ni servi) : règle des mentions en passant, descriptions écartées, nombre de lignes
 * décrites. Extraits réels du 23/11/2026 (tests/Fixtures/Sncf/archives-sncf-sardo-descriptions-extrait.json).
 */
class SncfArchiveDescriptionsTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../Fixtures/Sncf/archives-sncf-sardo-descriptions-extrait.json';

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true);
    }

    private function index(): LinePlaceIndex
    {
        $line = fn (int $id, string $slug, string $name, array $stations, int $first): array => ['id' => $id, 'slug' => $slug, 'name' => $name,
            'stations' => array_map(fn (string $s, int $k): array => ['name' => $s, 'city' => $s, 'pk_m' => $k * 10000], $stations, array_keys($stations)),
            'first_year' => $first, 'open_today' => true];

        return new LinePlaceIndex([
            1 => LinePlaceIndex::linePlaces($line(1, 'rfn-301000', "Ligne d'Arras à Hazebrouck", ['Arras', 'Avion', 'Béthune', 'Isbergues', 'Thiennes', 'Hazebrouck'], 1860)),
            2 => LinePlaceIndex::linePlaces($line(2, 'rfn-272000', 'Ligne de Paris-Nord à Creil', ['Paris-Nord', 'Epluches', 'Creil'], 1846)),
            3 => LinePlaceIndex::linePlaces($line(3, 'rfn-321000', "Ligne d'Amiens à Rouen", ['Amiens', 'Rouen'], 1867)),
        ]);
    }

    /** @return array<string, list<string>> titre ou début de description → lignes retenues */
    private function attached(): array
    {
        $result = (new SncfArchivesImporter)->run($this->index(), $this->rows());
        $out = [];
        foreach ($result['candidates'] as $c) {
            $out[$c['url']] ??= [];
            if ($c['accepted']) {
                $out[$c['url']][] = $c['line'];
            }
        }

        return $out;
    }

    public function test_a_generic_title_is_attached_by_the_line_its_description_describes(): void
    {
        $result = (new SncfArchivesImporter)->run($this->index(), $this->rows());
        $attached = $this->attached();

        $this->assertSame(['rfn-301000'], $attached['https://www.archives.sncf.com/les-travaux-en-gare-de-thiennes-pour-lautorite-allemande/']);
        $this->assertSame(['rfn-301000'], $attached['https://www.archives.sncf.com/les-travaux-militaires-a-la-gare-de-avion/']);
        $this->assertSame(['rfn-301000'], $attached['https://www.archives.sncf.com/les-questions-dinstallations-fixes-237/']);
        $this->assertSame(3, $result['stats']['by_description']);
        // Titre générique, jamais un titre de ligne : la raison le dit, sans point.
        $reasons = array_column($result['documents'][1][0]['score_reasons'], 'code');
        $this->assertContains('read_in_description', $reasons);
        $this->assertSame(11, $result['documents'][1][0]['score']);
    }

    public function test_a_description_is_never_kept(): void
    {
        $result = (new SncfArchivesImporter)->run($this->index(), $this->rows());

        $this->assertStringNotContainsString('Autorité allemande', serialize($result['documents']));
        $this->assertStringNotContainsString('THIENNES', serialize($result['documents']));
    }

    public function test_lines_named_in_passing_do_not_attach_the_notice(): void
    {
        $url = 'https://www.archives.sncf.com/les-dommages-de-guerre-7/';

        // « sur les lignes de Paris à Creil par Epluches et de Amiens à Rouen » : un lieu, pas le sujet du dossier.
        $this->assertSame([], $this->attached()[$url] ?? []);
        $this->assertSame(
            'DOMMAGES DE GUERRE. Installations sinistrées PAR FAITS DE GUERRE  ',
            (string) SardoParser::descriptionText($this->rows()[2]['description']),
        );
    }

    public function test_two_lines_described_leave_the_notice_unattached(): void
    {
        // « lignes d'Arras à Hazebrouck et de Lille à Béthune » : laquelle est le sujet ? aucune.
        $this->assertSame([], $this->attached()['https://www.archives.sncf.com/les-questions-dinstallations-fixes-271/'] ?? []);
    }

    /** @return iterable<string, array{string, int}> */
    public static function statements(): iterable
    {
        yield 'une ligne' => ['Ligne ARRAS à HAZEBROUCK, gare de THIENNES. - Travaux exécutés pour les besoins de l\'Autorité allemande.', 1];
        yield 'sans de' => ['Plans parcellaires. Ligne Paris à Chantilly : Commune Orry-LA-Ville', 1];
        yield 'tiret après ligne' => ['Ligne - CREIL à JEUMONT, gare de CHAUNY. - Travaux militaires (1939-1947).', 1];
        yield 'même ligne, deux noms de gare' => ['Ligne de Rodez à Albi (Rodez : H 12). Ligne de Rodez à Albi-ville, section de Tanus à Albi-ville [PL 165].', 1];
        yield 'deux lignes' => ['Ligne de Pfetterhouse à Dannemarie [Ligne 94] Ligne de Sewen à Cernay [Ligne 93] Pont sur la Doller (31, 50 m)', 2];
        yield 'parenthèse devant le nom' => ['Ligne de Colmar à (Neuf) Brisach [Ligne 87]. Ligne de Lapoutroie à Colmar [Ligne 99]', 2];
        yield 'aucune' => ['Ouvrage au km 443+405. Photos prises en février 1945.', 0];
    }

    #[DataProvider('statements')]
    public function test_the_lines_a_description_states_are_counted_once_each(string $text, int $expected): void
    {
        $this->assertSame($expected, SardoParser::statedLines($text));
    }

    public function test_descriptions_about_people_or_inventories_are_not_read(): void
    {
        $this->assertNull(SardoParser::descriptionText('Accidents ferroviaires : dossiers de contentieux concernant les personnes blessées ou tuées. Ligne de Paris à Orléans'));
        $this->assertNull(SardoParser::descriptionText('Fiches documentaires. '.str_repeat('Lignes (voir exploitation de lignes). ', 60).'Ligne de Bordeaux à Hendaye'));
        $this->assertNull(SardoParser::descriptionText(null));
        $this->assertNull(SardoParser::descriptionText('  '));
        $this->assertSame('Ligne de Nantes à La Rochelle : pont', SardoParser::descriptionText("<strong>Ligne de Nantes à La Rochelle</strong>_x000D_\n : pont"));
    }
}
