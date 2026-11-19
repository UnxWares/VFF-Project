<?php

namespace Tests\Unit\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\ArgentanPhotoClass;
use App\Enums\FactDatePrecision;
use App\Enums\LineEventType;
use App\Enums\StationType;
use App\Services\ArgentanWebFerro\ArgentanClient;
use App\Services\ArgentanWebFerro\ExtractedFact;
use App\Services\ArgentanWebFerro\GalleryInventory;
use App\Services\ArgentanWebFerro\LineResolver;
use App\Services\ArgentanWebFerro\PhotoClassifier;
use App\Services\ArgentanWebFerro\PlaceMatcher;
use App\Services\ArgentanWebFerro\VffComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Services de la source Argentan Web Ferroviaire sans base : noms de gares, ligne visée,
 * comparaison avec VFF, classes de droits des photos, robots.txt, JSON de la galerie.
 */
class ArgentanServicesTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function names(): iterable
    {
        yield 'abréviation St' => ["St Pierre d'Entremont", "Saint-Pierre-d'Entremont", true];
        yield 'article omis' => ['Mesnil-Mauger', 'Le Mesnil-Mauger', true];
        yield 'barre pour sur' => ['Condé/Huisne', 'Condé-sur-Huisne', true];
        yield 'nom plus long' => ["Flers de l'Orne", 'Flers', true];
        yield 'une lettre' => ['Fresnes', 'Frênes', true];
        yield 'ND' => ['ND du Hamel', 'Notre-Dame-du-Hamel', true];
        yield 'autre gare' => ['St Rémy du Plain', 'Saint-Rémy-des-Monts', false];
        yield 'nom court proche' => ['Igé', 'Isé', false];
    }

    #[DataProvider('names')]
    public function test_place_names(string $a, string $b, bool $same): void
    {
        $this->assertSame($same, PlaceMatcher::same($a, $b));
        $this->assertSame($same, PlaceMatcher::same($b, $a), 'symétrique');
    }

    public function test_near_names_share_a_significant_word(): void
    {
        $this->assertTrue(PlaceMatcher::near('Ambrières les Vallées', 'Ambrières-Centre'));
        $this->assertFalse(PlaceMatcher::near('Saint-Loup-du-Gast', 'Saint-Fraimbault'));
    }

    private static function event(?string $from = null, ?string $to = null, ?int $year = 1954, LineEventType $type = LineEventType::Closure,
        FactDatePrecision $precision = FactDatePrecision::Year, ?int $yearTo = null, ?int $month = null, ?int $day = null): ExtractedFact
    {
        return new ExtractedFact(ArgentanFactKind::Event, 'citation', type: $type, year: $year, month: $month, day: $day, yearTo: $yearTo,
            precision: $year === null ? null : $precision, from: $from, to: $to);
    }

    public function test_line_resolution(): void
    {
        $resolver = new LineResolver([1 => ['Ste-Gauburge', 'Échauffour', 'Gacé'], 2 => ['Échauffour', 'Montreuil-Cernières', 'Bernay']]);

        $this->assertSame(['line_id' => 7, 'match' => 'curated', 'note' => null], $resolver->resolve(self::event(), [7]));
        $this->assertSame(1, $resolver->resolve(self::event('Ste Gauburge', 'Gacé'), [1, 2])['line_id']);
        $this->assertSame(2, $resolver->resolve(self::event('Montreuil-Cernières', 'Bernay'), [1, 2])['line_id']);
        $this->assertSame('doubtful', $resolver->resolve(self::event(), [1, 2])['match'], 'sans section, plusieurs lignes');
        $this->assertSame('doubtful', $resolver->resolve(self::event('Echauffour'), [1, 2])['match'], 'gare commune aux deux lignes');
        $this->assertSame('none', $resolver->resolve(self::event(), [])['match']);
        $stop = new ExtractedFact(ArgentanFactKind::Stop, 'Bernay', stopName: 'Bernay');
        $this->assertSame(2, $resolver->resolve($stop, [1, 2])['line_id']);
    }

    private function comparison(): VffComparison
    {
        return new VffComparison(
            [1 => [
                ['type' => 'closure', 'date' => '1954-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'from' => 'Ste-Gauburge', 'to' => 'Gacé', 'url' => null],
                ['type' => 'opening', 'date' => '1881-10-09', 'precision' => 'day', 'source' => 'manual', 'from' => 'Gacé', 'to' => 'Ticheville', 'url' => null],
                ['type' => 'closure', 'date' => '1989-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'from' => null, 'to' => null, 'url' => null],
            ]],
            [1 => [['name' => 'Montsecret-Vassy', 'type' => 'gare'], ['name' => 'Frênes', 'type' => 'gare'], ['name' => 'Ambrières-Centre', 'type' => 'gare']]],
        );
    }

    public function test_event_comparison(): void
    {
        $c = $this->comparison();

        $this->assertSame(VffComparison::CONFIRMED, $c->compare(self::event('Ste Gauburge', 'Gacé'), [1])['status']);
        $this->assertSame(VffComparison::CONTRADICTORY, $c->compare(self::event('Ste Gauburge', 'Gacé', 1950), [1])['status']);
        $this->assertSame(VffComparison::CONFIRMED, $c->compare(self::event(null, null, 1952, precision: FactDatePrecision::Range, yearTo: 1955), [1])['status']);
        $opening = $c->compare(self::event('Gacé', 'Ticheville', 1881, LineEventType::Opening, FactDatePrecision::Day, month: 9, day: 10), [1]);
        $this->assertSame(VffComparison::CONTRADICTORY, $opening['status'], 'jour et mois inversés');
        $this->assertStringContainsString('1881-10-09', $opening['detail']);
        $this->assertSame(VffComparison::NEW, $c->compare(self::event(type: LineEventType::Dismantling), [1])['status']);
        $neighbour = $c->compare(self::event('Gacé', 'Ticheville', 1960), [1]);
        $this->assertSame(VffComparison::NEW, $neighbour['status'], 'une seule borne commune : section voisine, VFF ne date que la ligne entière (1989)');
        $this->assertStringContainsString('1989', $neighbour['detail']);
        $this->assertSame(VffComparison::CONFIRMED, $c->compare(self::event('Gacé', 'Ticheville', 1989), [1])['status'], 'même année que la ligne entière');
        $this->assertSame(VffComparison::NOT_COMPARED, $c->compare(self::event(type: LineEventType::Accident), [1])['status']);
        $this->assertSame(VffComparison::CONFIRMED, $c->compare(self::event(null, null, null), [1])['status'], 'non daté, attesté');
        $this->assertSame(VffComparison::NOT_COMPARED, $c->compare(self::event(), [])['status']);
    }

    public function test_stop_comparison(): void
    {
        $c = $this->comparison();
        $stop = fn (string $name, ?StationType $nature = null) => new ExtractedFact(ArgentanFactKind::Stop, $name, stopName: $name, stopNature: $nature);

        $this->assertSame(VffComparison::PRESENT, $c->compare($stop('Montsecret-Vassy', StationType::Gare), [1])['status']);
        $this->assertSame(VffComparison::OTHER_NATURE, $c->compare($stop('Fresnes', StationType::Halte), [1])['status']);
        $this->assertSame(VffComparison::ABSENT, $c->compare($stop("St Pierre d'Entremont", StationType::PointArret), [1])['status']);
        $near = $c->compare($stop('Ambrières les Vallées'), [1]);
        $this->assertSame(VffComparison::ABSENT, $near['status']);
        $this->assertStringContainsString('Ambrières-Centre', $near['detail']);
    }

    public function test_photo_classes(): void
    {
        $classifier = new PhotoClassifier(['own_work' => [10 => 'photo couleur relue'], 'postcard' => [11 => 'carte postale']], [12 => 'Laurent Choubrac']);
        $image = fn (int $id, string $name = 'Surdon', string $comment = '', string $file = 'surdon.jpg') => compact('id', 'name', 'comment', 'file');

        $this->assertSame(ArgentanPhotoClass::OwnWork, $classifier->classify($image(10))[0]);
        $this->assertSame(ArgentanPhotoClass::Postcard, $classifier->classify($image(11))[0]);
        $this->assertSame(ArgentanPhotoClass::Credited, $classifier->classify($image(12))[0]);
        $this->assertSame(ArgentanPhotoClass::Credited, $classifier->classify($image(13, 'Gare de Mortagne (Coll. F. Huet)'))[0]);
        $this->assertSame(ArgentanPhotoClass::Credited, $classifier->classify($image(14, comment: 'Photo: Richard LATTEN'))[0]);
        $this->assertSame(ArgentanPhotoClass::Unknown, $classifier->classify($image(15, comment: 'Photo: T. Danglos'))[0], 'l\'auteur lui-même');
        $this->assertSame(ArgentanPhotoClass::Archive, $classifier->classify($image(16, file: 'AD50 St Sever01.jpg'))[0]);
        $this->assertSame(ArgentanPhotoClass::Archive, $classifier->classify($image(17, file: 'Fold3_13-edit.jpg'))[0]);
        $this->assertSame(ArgentanPhotoClass::Unknown, $classifier->classify($image(18))[0]);
        $tagged = new PhotoClassifier(['own_work' => [20 => 'relue'], 'postcard' => [21 => 'carte']], [20 => 'Emmanuel Desnos', 21 => 'Emmanuel Desnos']);
        $this->assertSame(ArgentanPhotoClass::Credited, $tagged->classify($image(20))[0], 'le mot-clé d\'un photographe l\'emporte sur la relecture « œuvre propre »');
        $this->assertSame(ArgentanPhotoClass::Postcard, $tagged->classify($image(21))[0], 'une relecture qui exclut reste prioritaire');
        $this->assertSame(ArgentanPhotoClass::Credited, $classifier->classify($image(10, comment: 'Photo: Richard LATTEN'))[0], 'mention d\'un tiers sur une photo relue');
        $this->assertTrue(ArgentanPhotoClass::OwnWork->republishable());
        foreach ([ArgentanPhotoClass::Postcard, ArgentanPhotoClass::Archive, ArgentanPhotoClass::Credited, ArgentanPhotoClass::Unknown] as $class) {
            $this->assertFalse($class->republishable(), $class->value);
        }
    }

    public function test_robots_and_gallery_json(): void
    {
        $robots = "User-agent: Googlebot\nDisallow: /private/\n\nUser-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
        $this->assertSame(['/wp-admin/'], ArgentanClient::parseRobots($robots));

        $body = "<br />\n<b>Warning</b>:  Undefined array key 21411 in <b>/home/AWF/include/ws_functions/pwg.categories.php</b><br />\n{\"stat\":\"ok\",\"result\":{\"images\":[]}}";
        $this->assertSame(['stat' => 'ok', 'result' => ['images' => []]], ArgentanClient::decodeJson($body));
        $this->assertNull(ArgentanClient::decodeJson('pas de JSON'));
    }

    public function test_shot_year_from_caption_then_exif(): void
    {
        $this->assertSame(1989, GalleryInventory::shotYear('Montsecret - 1989', '2019-02-04 00:00:00'));
        $this->assertSame(2019, GalleryInventory::shotYear('Carrière - Vignats - 14/09/2019', null));
        $this->assertSame(2016, GalleryInventory::shotYear('Pontorson-Mont St Michel', '2016-06-01 08:29:42'));
        $this->assertNull(GalleryInventory::shotYear('Argentan', '2019-02-04 00:00:00'), 'minuit : date de mise en ligne, pas de prise de vue');
    }

    public function test_precision_mapping(): void
    {
        $this->assertSame('day', FactDatePrecision::Day->lineEventPrecision());
        $this->assertNull(FactDatePrecision::Range->lineEventPrecision(), 'une période n\'entre pas telle quelle dans line_events');
    }
}
