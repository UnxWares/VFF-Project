<?php

namespace Tests\Unit\Media;

use App\Models\Media;
use App\Models\Station;
use App\Services\Media\CommonsCoverage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règles de couverture Commons : droits (CC BY, CC BY-SA, CC0, domaine public, auteur obligatoire),
 * distance à la gare, catégories propres ou communales, noms de catégories de gares.
 */
class CommonsCoverageTest extends TestCase
{
    /** @param  array<string, mixed>  $over
     * @return array<string, mixed> */
    private static function meta(array $over = []): array
    {
        return $over + [
            'file_title' => 'File:Gare de Chagny 01.jpg', 'mime' => 'image/jpeg', 'width' => 2000,
            'rights_status' => Media::RIGHTS_OPEN_LICENSE, 'commercial_ok' => true, 'license' => 'CC BY-SA 4.0',
            'author' => 'Jean Dupont', 'credit' => null, 'categories' => ['Gare de Chagny'], 'lat' => null, 'lon' => null,
        ];
    }

    /** @return array<string, array{0: array<string, mixed>, 1: ?string}> */
    public static function rightsProvider(): array
    {
        return [
            'CC BY-SA 4.0' => [[], null],
            'CC BY 2.0' => [['license' => 'CC BY 2.0'], null],
            'CC0' => [['license' => 'CC0', 'rights_status' => Media::RIGHTS_PUBLIC_DOMAIN], null],
            'domaine public avec crédit seul' => [['license' => 'Public domain', 'rights_status' => Media::RIGHTS_PUBLIC_DOMAIN, 'author' => null, 'credit' => 'Collection personnelle'], null],
            'GFDL seule' => [['license' => 'GFDL'], 'licence'],
            'licence ouverte' => [['license' => 'Licence Ouverte'], 'licence'],
            'NC' => [['license' => 'CC BY-NC 2.0', 'commercial_ok' => false], 'licence'],
            'ND' => [['license' => 'CC BY-ND 2.0'], 'licence'],
            'inconnue' => [['rights_status' => Media::RIGHTS_UNKNOWN, 'license' => null, 'commercial_ok' => null], 'licence'],
            'sans auteur ni crédit' => [['author' => null], 'auteur'],
            'auteur vide' => [['author' => '  '], 'auteur'],
            'numérisation Gallica' => [['credit' => 'Gallica - Bibliothèque nationale de France', 'license' => 'Public domain', 'rights_status' => Media::RIGHTS_PUBLIC_DOMAIN], 'gallica'],
            'auteur qui est une adresse' => [['author' => 'https://exemple.org/page'], 'auteur'],
            'vignette' => [['width' => 120], 'petite'],
            'PDF' => [['mime' => 'application/pdf'], 'mime'],
            'logo' => [['file_title' => 'File:Logo gare de Chagny.png'], 'logo'],
        ];
    }

    /** @param  array<string, mixed>  $over */
    #[DataProvider('rightsProvider')]
    public function test_licence_and_author_rules(array $over, ?string $expected): void
    {
        $this->assertSame($expected, CommonsCoverage::rejectReason(self::meta($over)));
    }

    public function test_file_far_from_the_station_is_refused_but_p18_is_tolerant(): void
    {
        $near = self::meta(['lat' => 46.9072, 'lon' => 4.7488]);
        $far = self::meta(['lat' => 46.9172, 'lon' => 4.7488]); // environ 1,1 km
        $this->assertNull(CommonsCoverage::stationReject($near, 'category', 46.9071977, 4.7487728));
        $this->assertSame('loin', CommonsCoverage::stationReject($far, 'category', 46.9071977, 4.7487728));
        $this->assertNull(CommonsCoverage::stationReject($far, 'p18', 46.9071977, 4.7487728));
        $this->assertNull(CommonsCoverage::stationReject(self::meta(), 'category', 46.9, 4.7), 'sans position : accepté');
    }

    public function test_commune_category_keeps_only_railway_files(): void
    {
        $church = self::meta(['file_title' => 'File:Eglise Saint-Pierre.jpg', 'categories' => ['Chagny']]);
        $platform = self::meta(['file_title' => 'File:Quai 2.jpg', 'categories' => ['Chagny']]);
        $this->assertSame('hors_sujet', CommonsCoverage::stationReject($church, 'weak_category', null, null));
        $this->assertNull(CommonsCoverage::stationReject($platform, 'weak_category', null, null));
        $this->assertSame('hors_sujet', CommonsCoverage::railReject($church, 'weak_category'));
        $this->assertNull(CommonsCoverage::railReject($church, 'p18'));
    }

    public function test_urban_station_of_the_same_name_is_refused(): void
    {
        $tram = self::meta(['file_title' => 'File:Camp des Loges T13 Express 2022 07.jpg', 'categories' => ["Camp des Loges (tramway d'Île-de-France)"]]);
        $metro = self::meta(['file_title' => 'File:Subway-Saint-Martin-08.jpg', 'categories' => ['Saint-Martin (Paris Metro)']]);
        $tramGare = self::meta(['file_title' => 'File:Station Gare Épinay Villetaneuse Ligne 11 Express Tramway.jpg', 'categories' => []]);
        $this->assertSame('urbain', CommonsCoverage::stationReject($tramGare, 'geosearch', null, null));
        $rer = self::meta(['file_title' => 'File:Gare RER.jpg', 'categories' => ['Gare de Saint-Martin', 'Transilien']]);
        $this->assertSame('urbain', CommonsCoverage::stationReject($tram, 'p18', null, null));
        $this->assertSame('urbain', CommonsCoverage::stationReject($metro, 'p18', null, null));
        $this->assertNull(CommonsCoverage::stationReject($rer, 'p18', null, null));
    }

    public function test_geosearch_needs_a_railway_title_that_names_the_place(): void
    {
        $this->assertTrue(CommonsCoverage::titleIsRailway('File:Gare de Vendeuvre 02.jpg'));
        $this->assertFalse(CommonsCoverage::titleIsRailway('File:Mairie de Vendeuvre.jpg'));
        $this->assertFalse(CommonsCoverage::titleIsRailway('File:Avolsheim - Rue de la Gare - boîte à livres.jpg'));
        $this->assertFalse(CommonsCoverage::titleIsRailway('File:Station Épuration Crottet 4.jpg'));
        $this->assertFalse(CommonsCoverage::titleIsRailway('File:Près de la gare de Guise.jpg'));
        $this->assertFalse(CommonsCoverage::titleIsRailway('File:Les Lumières de Laval, Quai Gambetta 1.jpg'));
        $this->assertTrue(CommonsCoverage::titleIsRailway('File:Train en gare d\'Aspach.jpg'));
        $this->assertTrue(CommonsCoverage::offTopicTitle('File:Rue Pierre Semard, panneaux Gare de Lyon-Guillotière.jpg'));
        $meta = self::meta(['file_title' => 'File:Ancienne gare d\'Étel 1.jpg', 'categories' => ['Étel']]);
        $this->assertTrue(CommonsCoverage::mentionsPlace($meta, 'Étel', 'Étel'));
        $this->assertTrue(CommonsCoverage::mentionsPlace(self::meta(['file_title' => 'File:Gare Saint-Georges-Motel.jpg']), 'St-Georges-Motel', 'Saint-Georges-Motel'));
        $this->assertFalse(CommonsCoverage::mentionsPlace(self::meta(['file_title' => 'File:Gare de Dreux.jpg', 'categories' => []]), 'Vernouillet', 'Vernouillet'));
        $this->assertFalse(CommonsCoverage::mentionsPlace(self::meta(['file_title' => 'File:Gare de Paris-Montparnasse 3.jpg', 'categories' => []]), 'Paris-Vaugirard', 'Paris'), 'la grande commune voisine ne suffit pas');
        $this->assertFalse(CommonsCoverage::mentionsPlace(self::meta(['file_title' => 'File:Gare du Nord.jpg', 'categories' => []]), 'Ancienne gare'), 'mots génériques seuls : refusé');
    }

    public function test_category_kinds(): void
    {
        $this->assertTrue(CommonsCoverage::isStationCategory('Category:Gare de Chagny'));
        $this->assertTrue(CommonsCoverage::isStationCategory('Category:Chagny railway station'));
        $this->assertFalse(CommonsCoverage::isStationCategory('Category:Chagny'));
        $this->assertTrue(CommonsCoverage::isStationSubcategory('Category:Gare de Chagny - exterior'));
        $this->assertFalse(CommonsCoverage::isStationSubcategory('Category:Mairie de Chagny'));
    }

    public function test_distance_is_in_meters(): void
    {
        $this->assertEqualsWithDelta(111_195, CommonsCoverage::distanceM(46.0, 4.0, 47.0, 4.0), 200);
        $this->assertSame(0.0, CommonsCoverage::distanceM(46.0, 4.0, 46.0, 4.0));
    }

    public function test_category_names_from_a_station_name(): void
    {
        $this->assertSame(['Category:Gare de Chagny', 'Category:Ancienne gare de Chagny'], CommonsCoverage::stationCategoryNames('Chagny', 'gare'));
        $this->assertSame(['Category:Halte d\'Estressin', 'Category:Gare d\'Estressin', 'Category:Ancienne gare d\'Estressin'], CommonsCoverage::stationCategoryNames('Estressin', 'halte'));
        $this->assertSame('Category:Gare de Saint-Victor', CommonsCoverage::stationCategoryNames('St-Victor', 'gare')[0]);
        $this->assertSame('Category:Gare de Vendeuvre', CommonsCoverage::stationCategoryNames('Vendeuvre (Aube)', 'gare')[0]);
        $this->assertSame([], CommonsCoverage::stationCategoryNames('Ancienne gare', 'gare'), 'nom générique : aucune catégorie devinée');
    }

    public function test_subject_classes(): void
    {
        $this->assertSame(Station::class, CommonsCoverage::modelClass('station'));
        $this->assertNull(CommonsCoverage::modelClass('accident'));
    }

    public function test_le_titre_doit_nommer_la_gare_entiere_et_un_mot_de_gare(): void
    {
        $t = fn (string $title, string $name) => CommonsCoverage::titleNamesStation(self::meta(['file_title' => $title]), $name);
        $this->assertTrue($t('File:Gare de Walheim.jpg', 'Walheim'));
        $this->assertTrue($t('File:Bahnhof Walheim.jpg', 'Walheim'));
        $this->assertTrue($t('File:Saint-Georges-Motel - la gare.jpg', 'St-Georges-Motel'));
        $this->assertTrue($t("File:Gare SNCF d'Étel 2019.jpg", 'Étel'));
        // Un éditeur, un viaduc ou une ligne qui portent le nom ne suffisent pas.
        $this->assertFalse($t('File:CPA Gare de Bagneaux-sur-Loing Deville 1920.jpg', 'Deville'));
        $this->assertFalse($t('File:Anchamps - Laifour - Viaduc ferroviaire sur la Meuse.jpg', 'Anchamps'));
        $this->assertFalse($t('File:Gare routière de Dreux.jpg', 'Dreux'));
        $this->assertFalse($t('File:Gare de Dreux.jpg', 'Vernouillet'));
        $this->assertFalse($t('File:Gare de Saint-Benoit - Saint-Aignan 02.jpg', 'Saint-Benoit'));
        $this->assertFalse($t('File:Bahnhof Kirchheim (Teck) 11.jpg', 'Kirchheim'));
        $this->assertFalse($t('File:Pantin Sainte-Marthe Station 1.jpg', 'Sainte-Marthe'));
        $this->assertFalse($t('File:Mont-Saint-Éloi - Gare - 1.JPG', 'St-Éloi'));
        $this->assertTrue($t('File:Périssac - Gare 1.jpg', 'Périssac'));
        $this->assertTrue($t('File:Gare de Puget-sur-Argens 3.jpg', 'Puget-sur-Argens'));
    }

    public function test_la_commune_doit_etre_nommee_dans_le_titre_ou_les_categories(): void
    {
        $meta = self::meta(['file_title' => 'File:Gare de Villeneuve.jpg', 'categories' => ['Villeneuve-sur-Yonne']]);
        $this->assertTrue(CommonsCoverage::mentionsCity($meta, 'Villeneuve-sur-Yonne', 'Villeneuve'));
        $this->assertFalse(CommonsCoverage::mentionsCity($meta, 'Cabourg', 'Villeneuve'));
        $this->assertFalse(CommonsCoverage::mentionsCity($meta, null, 'Villeneuve'));
    }

    /**
     * Règle de lieu (recherche en texte) : cas réels de gares homonymes trouvées à tort sur Commons.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: string, 4: ?string}>
     */
    public static function placeProvider(): array
    {
        return [
            // [meta, nom de la gare, commune, département, motif attendu]
            'Walheim : gare allemande (Frankenbahn)' => [['file_title' => 'File:Bahnhof Walheim.jpg', 'caption' => 'Nahverkehr auf der Frankenbahn (KBS 780)', 'categories' => ['Chimneys in Landkreis Ludwigsburg', 'Bahnhof Walheim (Württ)']], 'Walheim', 'Walheim', '68', 'pays'],
            'Kirchheim : Baden-Württemberg' => [['file_title' => 'File:Bahnhof Kirchheim (Teck) 11.jpg', 'caption' => 'Der Bahnhof Kirchheim (Teck) ist Endstation der S-Bahn', 'categories' => ['Bahnhof Kirchheim (Teck)', 'Buses in Baden-Württemberg', 'Bus stations in Landkreis Esslingen']], 'Kirchheim', 'Kirchheim', '67', 'pays'],
            'Saint-Gingolph : canton du Valais' => [['file_title' => 'File:Saint-Gingolph Station 01.jpg', 'caption' => 'La gare de Saint-Gingolph, dans le canton du Valais, Suisse.', 'categories' => ['Saint-Gingolph railway station']], 'Saint-Gingolph', 'Saint-Gingolph', '74', 'pays'],
            'Montréal : Canada' => [['file_title' => 'File:Canadian Northern Railway Station Montreal 1920.jpg', 'caption' => 'Gare du Chemin de fer Canadien du Nord, Montréal', 'categories' => ['Central Station (Montreal)', 'PD Canada']], 'Montréal', 'Montréal', '11', 'pays'],
            'Muids : gare suisse sans commune ni département nommés' => [['file_title' => 'File:Gare du Muids (2123295535).jpg', 'caption' => 'NStCM Le Muids', 'categories' => ['Le Muids railway station', 'Flickr images reviewed by FlickreviewR 2']], 'Muids', 'Muids', '27', 'lieu'],
            'Romont : gare suisse sans département nommé' => [['file_title' => 'File:Gare de Romont.JPG', 'caption' => 'Le gare de Romont', 'categories' => ['Romont railway station']], 'Romont', 'Romont', '88', 'lieu'],
            'Saint-Rémy (Calvados) : Saint-Rémy-de-Provence' => [['file_title' => 'File:Gare de Saint-Rémy-de-Provence.jpg', 'caption' => 'Gare de Saint-Rémy-de-Provence', 'categories' => ['Bouches-du-Rhône in the 20th century']], 'Saint-Rémy', 'Saint-Rémy', '14', 'departement'],
            'Saint-Benoît (La Réunion) : Saint-Benoît (Ain)' => [['file_title' => 'File:Gare de Saint-Benoît, Ain (2).jpg', 'caption' => 'Ancienne gare du tramway du Haut-Rhône, à Saint-Benoît (Ain, France)', 'categories' => ['Gare de Saint-Benoît (Ain)']], 'Saint-Benoît', 'Saint-Benoît', '974', 'departement'],
            'Floirac (Lot) : Floirac (Gironde)' => [['file_title' => 'File:Floirac la gare.jpg', 'caption' => 'Floirac sur carte-postale', 'categories' => ['Train stations in Gironde', 'Postcards of Floirac']], 'Floirac', 'Floirac', '46', 'departement'],
            'Sainte-Marthe (Marseille) : chemin de croix de Pantin' => [['file_title' => 'File:Pantin Sainte-Marthe Station 1.jpg', 'caption' => 'Église Sainte-Marthe des Quatre-Chemins. Première station.', 'categories' => ['Station 1: Jesus is condemned to death in Seine-Saint-Denis']], 'Sainte-Marthe', 'Marseille', '13', 'departement'],
            'Puget-sur-Argens : commune et département (Var)' => [['file_title' => 'File:Gare de Puget-sur-Argens 2.jpg', 'caption' => 'Vue plein ouest de la gare de Puget-sur-Argens', 'categories' => ['Postcards of train stations in Var', 'Gare de Puget-sur-Argens']], 'Puget-sur-Argens', 'Puget-sur-Argens', '83', null],
            'Estivareilles (Loire)' => [['file_title' => 'File:Estivareilles - Gare -1.jpg', 'caption' => 'Estivareilles (Loire) - Gare', 'categories' => ['Estivareilles (Loire)']], 'Estivareilles', 'Estivareilles', '42', null],
            'Estivareilles (Allier) : le fichier est celui de la Loire' => [['file_title' => 'File:Estivareilles - Gare -1.jpg', 'caption' => 'Estivareilles (Loire) - Gare', 'categories' => ['Estivareilles (Loire)']], 'Estivareilles', 'Estivareilles', '03', 'departement'],
            'Valréas : département nommé dans une catégorie' => [['file_title' => 'File:Valréas Gare 1909.jpg', 'caption' => 'train en gare de Valréas', 'categories' => ['1909 in Vaucluse', 'Train station of Valréas']], 'Valréas', 'Valréas', '84', null],
            'commune nommée mais ni département ni région' => [['file_title' => 'File:Gare de Valréas.jpg', 'caption' => '', 'categories' => ['Train station of Valréas']], 'Valréas', 'Valréas', '84', 'lieu'],
            'région nommée à la place du département' => [['file_title' => 'File:Gare de Sainte-Marthe, rame.jpg', 'caption' => 'Une rame TER', 'categories' => ['Gare de Sainte-Marthe', 'Trains in Marseille', "TER Provence-Alpes-Côte d'Azur"]], 'Sainte-Marthe', 'Marseille', '13', null],
            'Suisse normande n\'est pas la Suisse' => [['file_title' => 'File:Gare de Clécy.jpg', 'caption' => 'En Suisse normande, Calvados', 'categories' => ['Gare de Clécy']], 'Clécy', 'Clécy', '14', null],
            'commune dont le nom contient un département' => [['file_title' => 'File:Gare de Saint-Rémy-sur-Marne.jpg', 'caption' => '', 'categories' => ['Gare de Saint-Rémy-sur-Marne (Haute-Marne)']], 'Saint-Rémy-sur-Marne', 'Saint-Rémy-sur-Marne', '52', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $over
     */
    #[DataProvider('placeProvider')]
    public function test_place_rule(array $over, string $name, string $city, string $department, ?string $expected): void
    {
        $this->assertSame($expected, CommonsCoverage::placeReject(self::meta($over), $name, $city, $department, null, null));
    }

    public function test_place_rule_with_coordinates_keeps_two_kilometres(): void
    {
        $over = ['file_title' => 'File:Gare de Chagny 3.jpg', 'categories' => ['Gare de Chagny']];
        $near = self::meta($over + ['lat' => 46.9172, 'lon' => 4.7488]); // 1,1 km
        $far = self::meta($over + ['lat' => 46.9372, 'lon' => 4.7488]); // 3,3 km
        $this->assertNull(CommonsCoverage::placeReject($near, 'Chagny', 'Chagny', '71', 46.9071977, 4.7487728), 'avec position : ni département ni commune exigés');
        $this->assertSame('loin', CommonsCoverage::placeReject($far, 'Chagny', 'Chagny', '71', 46.9071977, 4.7487728));
        $german = self::meta(['categories' => ['Trains in Germany']] + $over + ['lat' => 46.9072, 'lon' => 4.7488]);
        $this->assertSame('pays', CommonsCoverage::placeReject($german, 'Chagny', 'Chagny', '71', 46.9071977, 4.7487728));
    }
}
