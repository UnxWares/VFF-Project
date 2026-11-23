<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des lignes des fonds de l'agent medias-3 (extraits réels de tests/Fixtures/OpenData, sans image) : accents remis,
 * commune tirée du titre, licence par fonds, dépôts refusés, dates vagues.
 */
class OpenDataFondsNormalizerTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function dataset(string $key): array
    {
        return (require __DIR__.'/../../../../database/data/open_data_media.php')[$key];
    }

    /** @return array<string, mixed> */
    private static function row(string $file, string $column, string $value): array
    {
        foreach (file(__DIR__.'/../../../Fixtures/OpenData/'.$file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (($row[$column] ?? null) === $value) {
                return $row;
            }
        }
        self::fail("{$file} : ligne {$value} absente de l'extrait");
    }

    public function test_saone_et_loire_accents_are_repaired_and_the_commune_comes_from_the_title(): void
    {
        $n = OpenDataNormalizer::normalize(self::dataset('sl71-cp'), self::row('sl71-cp-extrait.jsonl', 'Reference', '6 FI 4307'));

        $this->assertTrue($n['ok']);
        $c = $n['candidate'];
        $this->assertSame('Montchanin-les-Mines. Intérieur de la gare.', $c['title']);
        // « Chalon-sur-Saône (arrondissement) / Montchanin / Montchanin (canton) » : la commune est « Montchanin », pas l'arrondissement.
        $this->assertSame('Montchanin', $c['commune']);
        $this->assertSame('after', $c['date_precision']);
        $this->assertSame(1903, $c['year']);
        $this->assertSame('Licence Ouverte (Etalab)', $c['rights']['license']);
        $this->assertSame('open_license', $c['rights']['rights_status']);
        $this->assertMatchesRegularExpression('#^https://www\.archives71\.fr/ark:/60535/#', (string) $c['source']);
        $this->assertSame('https://saone-et-loire71.fr/opendata/archives/CartesPostales/6FI_4307.jpg', $c['image']);
    }

    public function test_saone_et_loire_cards_after_1950_and_non_railway_cards_are_refused(): void
    {
        $d = self::dataset('sl71-cp');

        // « Après 1950 » : droits d'éditeur en cours, hors de la période reprise.
        $this->assertSame('hors_periode', OpenDataNormalizer::normalize($d, self::row('sl71-cp-extrait.jsonl', 'Reference', '6 FI 2537'))['reason']);
        // Une rue « avenue de la Gare » n'est pas une vue ferroviaire.
        $this->assertSame('non_ferroviaire', OpenDataNormalizer::normalize($d, self::row('sl71-cp-extrait.jsonl', 'Reference', '6 FI 57'))['reason']);
    }

    public function test_the_commune_of_a_list_is_the_one_the_title_names(): void
    {
        $d = self::dataset('sl71-cp');
        $c = fn (string $ref): ?string => OpenDataNormalizer::normalize($d, self::row('sl71-cp-extrait.jsonl', 'Reference', $ref))['candidate']['commune'] ?? null;

        $this->assertSame('Le Creusot', $c('6 FI 4304'));
        $this->assertSame('Tournus', $c('6 FI 4699'));
        $this->assertSame('Chagny', $c('6 FI 8300'));
        // Hameau nommé par le titre : seul lieu non marqué de la liste.
        $this->assertSame('La Chapelle-de-Guinchay', $c('6 FI 7762'));
    }

    public function test_getty_rights_country_and_dates_are_read_from_the_row(): void
    {
        $d = self::dataset('getty');
        $n = OpenDataNormalizer::normalize($d, self::row('getty-extrait.jsonl', 'accession', '84.XO.401.3'));

        $this->assertTrue($n['ok']);
        $c = $n['candidate'];
        $this->assertSame('CC0 1.0', $c['rights']['license']);
        $this->assertSame('public_domain', $c['rights']['rights_status']);
        $this->assertSame('Lyon', $c['commune']);
        $this->assertSame('Édouard Baldus', $c['author']);
        $this->assertSame(['circa', 1861], [$c['date_precision'], $c['year']]);
        $this->assertStringStartsWith('https://media.getty.edu/iiif/image/', $c['image']);
        $this->assertStringContainsString('/full/400,/', $c['thumb']);

        // Planche d'un album français sans lieu représenté : France ; objet hors des albums et sans lieu : refusé.
        $this->assertTrue(OpenDataNormalizer::normalize($d, self::row('getty-extrait.jsonl', 'accession', '84.XM.407.38'))['ok']);
        $this->assertSame('hors_fonds', OpenDataNormalizer::normalize($d, self::row('getty-extrait.jsonl', 'accession', '84.XC.979.2874'))['reason']);
    }

    public function test_the_ecole_des_ponts_keeps_only_its_own_documents_under_the_open_licence(): void
    {
        $d = self::dataset('enpc-ph');
        $own = OpenDataNormalizer::normalize($d, self::row('enpc-ph-extrait.jsonl', 'ark', 'btv1b10483104f'));

        $this->assertTrue($own['ok']);
        $this->assertSame('Gare de Nice', $own['candidate']['title']);
        $this->assertSame('Ministère des travaux publics. Photographies. Alpes-Maritimes', $own['candidate']['caption']);
        $this->assertSame('PH 125(1) A.12', $own['candidate']['inventory']);
        $this->assertSame(1873, $own['candidate']['year']);
        $this->assertSame('Licence Ouverte (Etalab)', $own['candidate']['rights']['license']);
        $this->assertStringEndsWith('/f1/full/400,/0/native.jpg', $own['candidate']['thumb']);

        // Carte du dépôt de la BnF (régime de réutilisation de la BnF) : refusée, comme tout document qui n'est pas de l'École.
        $this->assertSame('hors_fonds', OpenDataNormalizer::normalize($d, self::row('enpc-ph-extrait.jsonl', 'ark', 'btv1b53085108c'))['reason']);
        // Un manuscrit n'est pas une image.
        $this->assertSame('hors_fonds', OpenDataNormalizer::normalize($d, self::row('enpc-ph-extrait.jsonl', 'ark', 'btv1b10482737z'))['reason']);
    }

    public function test_the_ecole_des_ponts_drops_foreign_railway_views_by_their_original_title(): void
    {
        $d = self::dataset('enpc-ph');
        $base = [
            'ark' => 'btv1b10000001', 'repository' => 'Ecole nationale des ponts et chaussées', 'shelfmark' => 'Ecole nationale des ponts et chaussées, PH 1', 'type' => 'image',
            'image' => 'https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b10000001/f1/full/1600,/0/native.jpg', 'thumb' => 'https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b10000001/f1/full/400,/0/native.jpg', 'date' => null, 'author' => null,
        ];
        // Titres réels : le titre d'origine italien ou allemand est suivi de sa traduction entre crochets.
        foreach (['Viadotto fonte Carbone al km 81.000 [Viaduc fonte Carbone au km 81.000]', 'Bahnhof Lienz Aufnahmsgebaüde [Bâtiment d\'accueil de la gare de Lienz]', 'Small Garnskirk locomotive'] as $title) {
            $this->assertSame('hors_france', OpenDataNormalizer::normalize($d, ['title' => $title] + $base)['reason'], $title);
        }
        $this->assertTrue(OpenDataNormalizer::normalize($d, ['title' => 'Viaduc de Morlaix. Vue des travaux fin juin 1863'] + $base)['ok']);
    }

    public function test_a_title_in_brackets_without_title_is_cleaned_before_the_railway_filter(): void
    {
        $n = OpenDataNormalizer::normalize(self::dataset('enpc-ph'), self::row('enpc-ph-extrait.jsonl', 'ark', 'btv1b104834464'));

        $this->assertTrue($n['ok']);
        $this->assertSame('Gare de Chateaudun', $n['candidate']['title']);
    }

    public function test_numistral_keeps_the_bnu_and_refuses_the_bnf(): void
    {
        $d = self::dataset('numistral');
        $n = OpenDataNormalizer::normalize($d, self::row('numistral-extrait.jsonl', 'ark', 'btv1b102164183'));

        $this->assertTrue($n['ok']);
        $this->assertSame('Station du chemin de fer à Schléstadt', $n['candidate']['title']);
        $this->assertSame(1842, $n['candidate']['year']);
        $this->assertSame('NIM28871', $n['candidate']['inventory']);
        $this->assertSame('Licence Ouverte (Etalab)', $n['candidate']['rights']['license']);

        $this->assertSame('hors_fonds', OpenDataNormalizer::normalize($d, self::row('numistral-extrait.jsonl', 'ark', 'btv1b525107115'))['reason']);
    }
}
