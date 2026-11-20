<?php

namespace Tests\Unit;

use App\Console\Commands\AttachStations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Extrémités nommées d'une ligne et gare retenue pour chacune (vff:attach-stations, étape 4). */
class NamedTerminiTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function names(): iterable
    {
        yield 'de … à …' => ['Ligne de Lorient à Gourin', ['lorient', 'gourin']];
        yield 'du … (article)' => ['Ligne du Mans à Saint-Rémy-de-Monts', ['le mans', 'saint remy de monts']];
        yield 'au …' => ['Ligne de Recouvrance au Conquet', ['recouvrance', 'le conquet']];
        yield 'des …' => ['Ligne des Palais à Mouthoumet', ['les palais', 'mouthoumet']];
        yield 'apostrophe typographique' => ['Ligne d’Alençon à Condé-sur-Huisne', ['alencon', 'conde sur huisne']];
        yield 'à L’…' => ['Ligne de Balbigny à L’Hôpital', ['balbigny', 'l hopital']];
        yield 'tramway, précision entre parenthèses' => ['Tramway de Lille à Roubaix (ELRT 1)', ['lille', 'roubaix']];
        yield 'frontière écartée' => ['Ligne de la frontière à Chimay', ['chimay']];
        yield 'nom sans extrémités' => ['Ligne 200000', []];
    }

    #[DataProvider('names')]
    public function test_termini_are_read_from_the_line_name(string $name, array $expected): void
    {
        $this->assertSame($expected, AttachStations::terminiFromName($name));
    }

    public function test_one_station_per_terminus_exact_name_first(): void
    {
        // Même nom : retenu, même si un nom prolongé est plus proche.
        $this->assertSame(1, AttachStations::terminusStation('caussade', [1 => 'caussade', 2 => 'caussade ville'], 2));
        // Nom prolongé, seule gare de ce nom dans la base : retenu (« Ligne de Montauban à Monclar »).
        $this->assertSame(3, AttachStations::terminusStation('montauban', [3 => 'montauban ville bourbon', 4 => 'montbeton'], 1));
        // Ville à plusieurs gares dans la base (Lyon-Part-Dieu près du tracé de « Lyon à La Balme ») : aucun.
        $this->assertNull(AttachStations::terminusStation('lyon', [5 => 'lyon part dieu'], 9));
        // Deux noms prolongés près du tracé (Nice-St-Roch et Nice-Pont-Michel pour « Nice à Levens ») : aucun.
        $this->assertNull(AttachStations::terminusStation('nice', [5 => 'nice saint roch', 6 => 'nice pont michel'], 2));
        // Préfixe qui n'est pas un mot entier : jamais (« Lyon » ne désigne pas « Lyonnais »).
        $this->assertNull(AttachStations::terminusStation('lyon', [7 => 'lyonnais'], 0));
        $this->assertNull(AttachStations::terminusStation('gourin', [], 0));
    }
}
