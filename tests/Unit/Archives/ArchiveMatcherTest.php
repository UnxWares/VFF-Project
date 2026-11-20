<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\ArchiveDate;
use App\Services\Archives\ArchiveMatch;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\ArchiveRecord;
use App\Services\Archives\CatalogueSruParser;
use App\Services\Archives\LinePlaceIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement d'un document de Gallica à une ligne : score explicable, conditions obligatoires, seuil
 * et titres ambigus. Lignes construites sans base (LinePlaceIndex::linePlaces), notices réelles
 * (tests/Fixtures/Bnf) ou écrites pour le cas.
 */
class ArchiveMatcherTest extends TestCase
{
    private LinePlaceIndex $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = new LinePlaceIndex([
            1 => LinePlaceIndex::linePlaces([
                'id' => 1, 'slug' => 'rfn-340000', 'name' => 'Ligne de Paris-St-Lazare au Havre',
                'stations' => [
                    ['name' => 'Paris-St-Lazare', 'city' => 'Paris', 'pk_m' => 0],
                    ['name' => 'Poissy', 'city' => 'Poissy', 'pk_m' => 26000],
                    ['name' => 'Rouen-Rive-Droite', 'city' => 'Rouen', 'pk_m' => 140000],
                    ['name' => 'Le Havre', 'city' => 'Le Havre', 'pk_m' => 228000],
                ],
                'first_year' => 1840, 'open_today' => true,
            ]),
            2 => LinePlaceIndex::linePlaces([
                'id' => 2, 'slug' => 'rfn-610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême',
                'stations' => [
                    ['name' => 'Limoges-Bénédictins', 'city' => 'Limoges', 'pk_m' => 400000],
                    ['name' => 'Saillat-Chassenon', 'city' => 'Saillat-sur-Vienne', 'pk_m' => 446000],
                    ['name' => 'Angoulême', 'city' => 'Angoulême', 'pk_m' => 518000],
                ],
                'first_year' => 1875, 'open_today' => false, 'last_year' => 2018,
            ]),
            // Une ligne plus récente qui passe aussi par Paris et Rouen.
            3 => LinePlaceIndex::linePlaces([
                'id' => 3, 'slug' => 'rfn-335000', 'name' => 'Ligne de Paris à Rouen par Gisors',
                'stations' => [['name' => 'Paris', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Gisors', 'city' => 'Gisors', 'pk_m' => 70000],
                    ['name' => 'Rouen', 'city' => 'Rouen', 'pk_m' => 130000]],
                'first_year' => 1868, 'open_today' => true,
            ]),
        ]);
    }

    private function record(string $title, ?int $year, ?string $type = 'monograph'): ArchiveRecord
    {
        return new ArchiveRecord('ark:/12148/btv1b'.substr(md5($title), 0, 9), null, $title, $title, $type, [],
            $year === null ? ArchiveDate::unknown() : new ArchiveDate((string) $year, $year, null, null, 'year'), null, null);
    }

    /** @return array<string, ArchiveMatch> par slug de ligne */
    private function judge(ArchiveRecord $record): array
    {
        $out = [];
        foreach ((new ArchiveMatcher)->candidates($record, $this->index) as $m) {
            $out[$this->index->line($m->lineId)->slug ?? '?'] = $m;
        }

        return $out;
    }

    /** @return list<string> */
    private static function codes(ArchiveMatch $m): array
    {
        return array_column($m->reasons, 'code');
    }

    public function test_the_angouleme_limoges_brochure_is_a_sure_match_with_its_reasons(): void
    {
        $record = (new CatalogueSruParser)->parse((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Bnf/sru-angouleme-limoges.xml'))['records'][0];
        $m = $this->judge($record)['rfn-610000'];

        $this->assertNull($m->rejected);
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_project', 'doc_type'], self::codes($m));
        $this->assertSame(10, $m->score);
        $this->assertSame(['Limoges', 'Angoulême'], $m->places);
        $this->assertTrue($m->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_a_route_between_two_stations_goes_to_the_line_that_existed_at_that_date(): void
    {
        // « de Paris à Rouen » en 1843 : la ligne du Havre (concédée en 1840) l'emporte sur celle de Gisors (1868).
        $judged = $this->judge($this->record('Chemin de fer de Paris à Rouen', 1843, 'map'));

        $this->assertSame(9, $judged['rfn-340000']->score);
        $this->assertContains('date_in_life', self::codes($judged['rfn-340000']));
        // Pour Gisors, 1843 est un projet (25 ans avant) : meilleur score grâce aux extrémités, mais la ligne
        // qui existait à cette date passe avant.
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_project', 'doc_type'], self::codes($judged['rfn-335000']));
        $this->assertSame(10, $judged['rfn-335000']->score);

        $winners = (new ArchiveMatcher)->winners(array_values($judged));
        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $winners));
    }

    public function test_the_line_whose_places_are_all_named_wins_over_a_line_with_fewer(): void
    {
        $judged = $this->judge($this->record('Projet de chemin de fer de Paris à Rouen par Gisors', 1862));
        $winners = (new ArchiveMatcher)->winners(array_values($judged));

        $this->assertSame(['rfn-335000'], array_map(fn (ArchiveMatch $m) => $this->index->line($m->lineId)?->slug, $winners));
    }

    /** @return iterable<string, array{string, ?int, string}> */
    public static function rejections(): iterable
    {
        yield 'pas de mot du rail' => ['De Paris au Havre : guide itinéraire', 1855, 'no_rail_word'];
        yield 'autre transport' => ['Guide des bateaux à vapeur de Paris au Havre, avec les gares', 1841, 'other_transport'];
        yield 'avant tout projet' => ['Chemin de fer de Paris au Havre', 1790, 'date_incompatible'];
        yield 'type non retenu' => ['Chemin de fer de Paris au Havre', 1850, 'unsupported_type'];
    }

    #[DataProvider('rejections')]
    public function test_mandatory_conditions_reject_the_document_with_their_reason(string $title, ?int $year, string $reason): void
    {
        $m = $this->judge($this->record($title, $year, $reason === 'unsupported_type' ? null : 'monograph'))['rfn-340000'];

        $this->assertSame($reason, $m->rejected);
        $this->assertFalse($m->passes(1));
    }

    public function test_a_rail_route_overrides_another_transport_named_in_the_title(): void
    {
        $m = $this->judge($this->record('Chemin de fer de Paris au Havre et bateaux à vapeur', 1847))['rfn-340000'];

        $this->assertNull($m->rejected);
        $this->assertSame(11, $m->score);
    }

    public function test_two_places_without_a_route_pass_only_when_they_are_the_ends_and_the_date_fits(): void
    {
        $ends = $this->judge($this->record('Tours, catastrophe de chemin de fer, rapide de Paris, près du Havre', 1926, 'image'))['rfn-340000'];
        $this->assertSame(['two_places', 'only_line_places', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($ends));
        $this->assertSame(9, $ends->score);

        $middle = $this->judge($this->record('Gares de Poissy et de Rouen', 1926, 'image'))['rfn-340000'];
        $this->assertSame(['two_places', 'only_line_places', 'rail_hint', 'date_in_life', 'doc_type'], self::codes($middle));
        $this->assertFalse($middle->passes(ArchiveMatcher::THRESHOLD));

        // Un lieu d'une autre ligne (Gisors) : il manque le point qui permettait d'atteindre le seuil.
        $other = $this->judge($this->record('Chemin de fer : Gisors, Paris, Le Havre', 1926, 'image'))['rfn-340000'];
        $this->assertSame(['two_places', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($other));
        $this->assertSame(8, $other->score);
        $this->assertFalse($other->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_a_place_list_is_not_a_route(): void
    {
        // « de Paris au Havre, de Bordeaux… » : Paris et Rouen ne se suivent pas, pas de trajet Paris - Rouen.
        $m = $this->judge($this->record('Chemins de fer de Paris à Orléans, de Rouen à Dieppe', 1840))['rfn-340000'];

        $this->assertContains('two_places', self::codes($m));
    }

    public function test_a_line_named_only_as_a_connection_is_a_mention_not_the_subject(): void
    {
        $m = $this->judge($this->record('Chemin de fer de Gisors : raccordement avec la ligne de Paris au Havre', 1870))['rfn-340000'];

        $this->assertSame(['two_places', 'secondary_mention', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));
        $this->assertSame(6, $m->score);
        $this->assertFalse($m->passes(ArchiveMatcher::THRESHOLD));
    }

    /**
     * Lignes des faux rattachements relevés par la revue du commit bb27386 (point 3), nommées comme dans `vff`.
     *
     * @return array<string, ArchiveMatch> par slug
     */
    private function judgeOnReviewLines(string $title, int $year, string $type = 'monograph'): array
    {
        $line = fn (int $id, string $slug, string $name, array $stations, int $first) => LinePlaceIndex::linePlaces([
            'id' => $id, 'slug' => $slug, 'name' => $name, 'first_year' => $first, 'open_today' => true,
            'stations' => array_map(fn (array $s, int $k) => ['name' => $s[0], 'city' => $s[1], 'pk_m' => $k * 10000], $stations, array_keys($stations)),
        ]);
        $index = new LinePlaceIndex([
            1 => $line(1, 'rfn-340000', 'Ligne de Paris-St-Lazare au Havre', [['Paris-St-Lazare', 'Paris'], ['Rouen-Rive-Droite', 'Rouen'], ['Le Havre', 'Le Havre']], 1840),
            2 => $line(2, 'rfn-561000', 'Ligne de Tours au Mans', [['Tours', 'Tours'], ['Château-du-Loir', 'Château-du-Loir'], ['Le Mans', 'Le Mans']], 1855),
            3 => $line(3, 'rfn-70000', 'Ligne de Noisy-le-Sec à Strasbourg-Ville', [['Paris-Est', 'Paris'], ['Noisy-le-Sec', 'Noisy-le-Sec'], ['Strasbourg-Ville', 'Strasbourg']], 1845),
            4 => $line(4, 'rfn-969000', 'Ligne de Paris-Bastille à Marles-en-Brie', [['Paris-Bastille', 'Paris'], ['Vincennes', 'Vincennes'], ['Saint-Maur', 'Saint-Maur-des-Fossés'], ['La Varenne-Saint-Hilaire', 'Saint-Maur-des-Fossés']], 1855),
            5 => $line(5, 'rfn-973000', 'Ligne de Paris-St-Lazare à Versailles-RD', [['Paris-St-Lazare', 'Paris'], ['Saint-Cloud', 'Saint-Cloud'], ['Versailles-Rive-Droite', 'Versailles']], 1839),
            6 => $line(6, 'rfn-976000', "Ligne de L'Étang-la-Ville à Saint-Cloud", [["L'Étang-la-Ville", "L'Étang-la-Ville"], ['Saint-Cloud', 'Saint-Cloud']], 1882),
        ]);
        $record = new ArchiveRecord('ark:/12148/btv1b'.substr(md5($title), 0, 9), null, $title, $title, $type, [],
            new ArchiveDate((string) $year, $year, null, null, 'year'), null, null);
        $out = [];
        foreach ((new ArchiveMatcher)->candidates($record, $index) as $m) {
            $out[$index->line($m->lineId)->slug ?? '?'] = $m;
        }

        return $out;
    }

    /** @return iterable<string, array{string, int, string, string}> titre, année, type, ligne qui ne doit pas le recevoir */
    public static function reviewFalseMatches(): iterable
    {
        yield 'id 163 : trois lignes au départ de Paris' => ['[Lignes de chemin de fer de Paris à Rouen, au Mans, à Tours, avec l\'indication des lignes projetées sur ce réseau]', 1875, 'map', 'rfn-561000'];
        yield 'id 32 : embarcadère de la ligne de Vincennes' => ["Notes et observations de M. Caffin d'Orsigny, sur une pétition touchant une modification à l'emplacement fixé de l'embarcadère du chemin de fer de Paris à la Varenne-Saint-Hilaire, par Vincennes et Saint-Maur : à Messieurs les président et membres du conseil d'administration du chemin de fer de Strasbourg", 1855, 'monograph', 'rfn-70000'];
        yield 'id 30 : invitation de 1858 datée de 1978' => ['Ville de Paris. Inauguration du boulevard de Sébastopol le Lundi 5 Avril 1858, à 2 heures Gare du Chemin de Fer de Strasbourg. Tribune du Portique [...] Bon pour une personne [etc.]', 1978, 'monograph', 'rfn-70000'];
        yield 'id 143 : simple mention de la ligne Paris à Versailles' => ["Ministère des Travaux publics. Département de Seine-et-Oise. Chemins de fer de L'Etang-la-ville à Saint-Cloud... Partie comprise entre le chemin de fer de grande ceinture et la ligne Paris à Versailles (rive droite)...", 1875, 'map', 'rfn-973000'];
    }

    #[DataProvider('reviewFalseMatches')]
    public function test_two_named_places_alone_no_longer_reach_the_threshold(string $title, int $year, string $type, string $wrongLine): void
    {
        $judged = $this->judgeOnReviewLines($title, $year, $type);
        $winners = array_map(fn (ArchiveMatch $m) => $m->lineId, (new ArchiveMatcher)->winners(array_values($judged)));

        $this->assertArrayHasKey($wrongLine, $judged);
        $this->assertFalse($judged[$wrongLine]->passes(ArchiveMatcher::THRESHOLD), (string) $judged[$wrongLine]->score);
        $this->assertNotContains($judged[$wrongLine]->lineId, $winners);
    }

    public function test_the_review_examples_go_to_the_line_they_describe(): void
    {
        // « de Paris à Rouen, au Mans » : la ligne de Paris au Havre par Rouen reçoit la carte de 1875.
        $map = $this->judgeOnReviewLines('[Lignes de chemin de fer de Paris à Rouen, au Mans, à Tours, avec l\'indication des lignes projetées sur ce réseau]', 1875, 'map');
        $this->assertSame('rail_route', self::codes($map['rfn-340000'])[0]);
        $this->assertTrue($map['rfn-340000']->passes(ArchiveMatcher::THRESHOLD));

        // « la ligne Paris à Versailles » après « comprise entre » : une mention, pas le sujet.
        $mention = $this->judgeOnReviewLines("Chemins de fer de L'Etang-la-ville à Saint-Cloud... Partie comprise entre le chemin de fer de grande ceinture et la ligne Paris à Versailles (rive droite)...", 1875, 'map');
        $this->assertContains('secondary_mention', self::codes($mention['rfn-973000']));
        $this->assertSame('rail_route', self::codes($mention['rfn-976000'])[0]);

        // Sans marque de mention, « la ligne Paris à Versailles » est bien un trajet.
        $route = $this->judgeOnReviewLines('Plan de la ligne Paris à Versailles (rive droite)', 1875, 'map');
        $this->assertSame('rail_route', self::codes($route['rfn-973000'])[0]);
    }

    public function test_an_enumeration_of_destinations_is_a_route_to_each_of_them(): void
    {
        $m = $this->judge($this->record('Chemin de fer de Paris à Cologne et au Havre', 1850))['rfn-340000'];
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));

        $list = $this->judge($this->record('Itinéraires de Paris à Saint-Germain, Poissy, Auteuil, Le Havre', 1850))['rfn-340000'];
        $this->assertSame('route', self::codes($list)[0]);
    }

    /**
     * Juge un titre sur une seule ligne donnée (nom, gares [nom, commune], première année).
     *
     * @param  list<array{0: string, 1: string}>  $stations
     */
    private function judgeOn(string $title, int $year, string $name, array $stations, int $first, string $type = 'monograph'): ArchiveMatch
    {
        $index = new LinePlaceIndex([1 => LinePlaceIndex::linePlaces([
            'id' => 1, 'slug' => 'ligne', 'name' => $name, 'first_year' => $first, 'open_today' => false, 'last_year' => 1950,
            'stations' => array_map(fn (array $s, int $k) => ['name' => $s[0], 'city' => $s[1], 'pk_m' => $k * 10000], $stations, array_keys($stations)),
        ])]);
        $record = new ArchiveRecord('ark:/12148/btv1b'.substr(md5($title), 0, 9), null, $title, $title, $type, [],
            new ArchiveDate((string) $year, $year, null, null, 'year'), null, null);
        $judged = (new ArchiveMatcher)->candidates($record, $index);
        $this->assertCount(1, $judged);

        return $judged[0];
    }

    public function test_a_waypoint_is_a_route_to_each_end_of_the_described_route(): void
    {
        // Document perdu de la ligne de Senlis à Verberie (gallica-corrections) : Senlis est une étape.
        $senlis = $this->judgeOn("Projet d'une ligne de chemin de fer entre Le Bourget et Verberie en passant par Senlis", 1900,
            'Ligne de Senlis à Verberie', [['Senlis', 'Senlis'], ['Verberie', 'Verberie']], 1884, 'map');
        $this->assertSame(['route', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($senlis));
        $this->assertTrue($senlis->passes(ArchiveMatcher::THRESHOLD));

        // « de X à Y par Z » relie aussi Z au départ X.
        $colmar = $this->judgeOn('Projet de chemin de fer de Colmar au Rhin par Neuf-Brisach', 1869,
            'Ligne de Colmar à Neuf-Brisach', [['Colmar', 'Colmar'], ['Neuf-Brisach', 'Neuf-Brisach']], 1878);
        $this->assertSame('route', self::codes($colmar)[0]);
        $this->assertContains('ends', self::codes($colmar));

        // L'étape et un bout ne sont qu'une partie du trajet : sans les extrémités de la ligne, sous le seuil.
        $brest = $this->judgeOn('Plan et profil du chemin de fer de Paris à Bordeaux passant par Chartres, Tours', 1838,
            'Ligne de Paris-Montparnasse à Brest', [['Paris-Montparnasse', 'Paris'], ['Chartres', 'Chartres'], ['Brest', 'Brest']], 1840);
        $this->assertSame('route', self::codes($brest)[0]);
        $this->assertFalse($brest->passes(ArchiveMatcher::THRESHOLD), (string) $brest->score);
    }

    public function test_places_that_follow_each_other_in_a_between_list_are_a_route(): void
    {
        // Document perdu de Paris - Le Havre : « avec » loin du trajet n'en fait plus une simple mention.
        // Deux lieux voisins d'une liste « entre » : un simple trajet (relecture du 20/11), qui passe le seuil entre
        // les extrémités de la ligne.
        $map = $this->judge($this->record("Carte d'une partie du bassin de la Seine entre Paris et la mer avec les indications de plusieurs tracés de chemins de fer entre Paris, Rouen, Le Hâvre et Dieppe [...]", 1837, 'map'))['rfn-340000'];
        $this->assertSame(['route', 'ends', 'rail_word', 'date_project', 'doc_type'], self::codes($map));
        $this->assertTrue($map->passes(ArchiveMatcher::THRESHOLD));

        // Deux lieux de la liste qui ne se suivent pas ne forment pas un trajet.
        $apart = $this->judge($this->record('Tracés de chemins de fer entre Paris, Orléans et Rouen', 1850))['rfn-340000'];
        $this->assertSame('two_places', self::codes($apart)[0]);

        // « avec » juste avant le trajet reste une marque de simple mention.
        $mention = $this->judge($this->record('Chemin de fer de Gisors, avec la ligne de Paris au Havre', 1870))['rfn-340000'];
        $this->assertContains('secondary_mention', self::codes($mention));
    }

    /**
     * Juge un titre réel sur des lignes nommées comme dans `vff` : slug => [nom, gares [nom, commune], première année].
     *
     * @param  array<string, array{0: string, 1: list<array{0: string, 1: string}>, 2: int}>  $lines
     * @return array<string, ArchiveMatch> par slug
     */
    private function judgeOnLines(string $title, int $year, string $type, array $lines): array
    {
        $places = [];
        $id = 0;
        foreach ($lines as $slug => [$name, $stations, $first]) {
            $id++;
            $places[$id] = LinePlaceIndex::linePlaces([
                'id' => $id, 'slug' => $slug, 'name' => $name, 'first_year' => $first, 'open_today' => true,
                'stations' => array_map(fn (array $st, int $k) => ['name' => $st[0], 'city' => $st[1], 'pk_m' => $k * 10000], $stations, array_keys($stations)),
            ]);
        }
        $index = new LinePlaceIndex($places);
        $record = new ArchiveRecord('ark:/12148/btv1b'.substr(md5($title), 0, 9), null, $title, $title, $type, [],
            new ArchiveDate((string) $year, $year, null, null, 'year'), null, null);
        $out = [];
        foreach ((new ArchiveMatcher)->candidates($record, $index) as $m) {
            $out[$index->line($m->lineId)->slug ?? '?'] = $m;
        }

        return $out;
    }

    public function test_neighbours_in_a_between_list_are_not_a_railway_of_the_line(): void
    {
        // Relecture du 20/11, point A : une étoile de destinations depuis Paris. Valenciennes et Lille, voisines dans
        // la liste, ne décrivent pas la ligne de Fives à Hirson : simple trajet entre deux gares intermédiaires, sous
        // le seuil même avec une date dans la vie de la ligne (9 avec `rail_route`).
        $title = 'Étude des chemins de fer ; 2e ligne Chemins entre Paris, Valenciennes, Lille, Dunkerque, Calais & Boulogne : Carte générale et profil en long';
        $judged = $this->judgeOnLines($title, 1835, 'map', [
            'rfn-267000' => ['Ligne de Fives à Hirson', [['Fives', 'Lille'], ['Valenciennes', 'Valenciennes'], ['Hirson', 'Hirson']], 1830],
            'rfn-304000' => ['Ligne de Coudekerque-Branche aux Fontinettes', [['Coudekerque-Branche', 'Coudekerque-Branche'], ['Dunkerque', 'Dunkerque'], ['Calais-Ville', 'Calais'], ['Les Fontinettes', 'Calais']], 1830],
        ]);
        foreach (['rfn-267000', 'rfn-304000'] as $slug) {
            $this->assertSame(['route', 'rail_word', 'date_in_life', 'doc_type'], self::codes($judged[$slug]), $slug);
            $this->assertFalse($judged[$slug]->passes(ArchiveMatcher::THRESHOLD), $slug);
        }

        // « entre Paris, Versailles, St Germain et Poissy » : plus de voie ferrée de Saint-Germain à Poissy.
        $poissy = $this->judgeOnLines('[Projet de tracé de chemins de fer entre Paris, Versailles, St Germain et Poissy]', 1836, 'map', [
            'archeo-saint-germain-gency' => ['Ligne de Saint-Germain à Gency par Poissy', [['Saint-Germain', 'Saint-Germain'], ['Poissy', 'Poissy'], ['Gency', 'Gency']], 1830],
        ])['archeo-saint-germain-gency'];
        $this->assertSame('route', self::codes($poissy)[0]);
        $this->assertFalse($poissy->passes(ArchiveMatcher::THRESHOLD), (string) $poissy->score);
    }

    public function test_an_enumeration_is_a_railway_only_right_after_railway_or_line(): void
    {
        $line = ['rfn-340000' => ['Ligne de Paris-St-Lazare au Havre', [['Paris-St-Lazare', 'Paris'], ['Rouen-Rive-Droite', 'Rouen'], ['Yvetot', 'Yvetot'], ['Le Havre', 'Le Havre']], 1840]];

        // « chemin de fer de X à Z, W et Y » : une voie ferrée vers chaque destination.
        $rail = $this->judgeOnLines('Chemin de fer de Paris à Dieppe, Rouen et Yvetot', 1850, 'monograph', $line)['rfn-340000'];
        $this->assertSame('rail_route', self::codes($rail)[0]);

        // Le mot du rail plus loin, pas juste avant le « de » : un simple trajet, sous le seuil entre deux gares qui
        // ne sont pas les extrémités.
        $plain = $this->judgeOnLines('Chemins de fer. Service des voitures de Paris à Dieppe, Rouen et Yvetot', 1850, 'monograph', $line)['rfn-340000'];
        $this->assertSame(['route', 'rail_word', 'date_in_life', 'doc_type'], self::codes($plain));
        $this->assertFalse($plain->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_with_far_from_the_route_still_marks_another_planned_line(): void
    {
        // Relecture du 20/11, point B : « pour former avec le chemin projeté entre Marseille et Lyon » cite la ligne de
        // Lyon à Marseille comme le prolongement d'un projet de Paris à Lyon de 1833 : une simple mention.
        $title = "Projet de formation d'une société pour l'établissement d'un chemin de fer entre Paris et Lyon par Troyes et Dijon... proposé par M. Arnollet,... pour former avec le chemin projeté entre Marseille et Lyon, et avec celui projeté entre le Havre et Paris la grande communication de l'Océan à la Méditerranée";
        $judged = $this->judgeOnLines($title, 1833, 'monograph', [
            'rfn-830000' => ['Ligne de Paris-Lyon à Marseille-St-Charles', [['Paris-Gare-de-Lyon', 'Paris'], ['Dijon-Ville', 'Dijon'], ['Lyon-Perrache', 'Lyon'], ['Marseille-St-Charles', 'Marseille']], 1847],
            'rfn-905000' => ['Ligne de Lyon-Perrache P1 à Marseille-St-Charles (via Grenoble)', [['Lyon-Perrache', 'Lyon'], ['Grenoble', 'Grenoble'], ['Marseille-St-Charles', 'Marseille']], 1858],
        ]);

        $this->assertContains('secondary_mention', self::codes($judged['rfn-905000']));
        $this->assertFalse($judged['rfn-905000']->passes(ArchiveMatcher::THRESHOLD), (string) $judged['rfn-905000']->score);
        // Le trajet décrit, de Paris à Lyon par Dijon, reste à la ligne de Paris à Marseille.
        $this->assertSame('rail_route', self::codes($judged['rfn-830000'])[0]);
        $winners = (new ArchiveMatcher)->winners(array_values($judged));
        $this->assertSame([$judged['rfn-830000']->lineId], array_map(fn (ArchiveMatch $m) => $m->lineId, $winners));

        // Même sans la ligne principale, le document ne part pas sur la ligne de Grenoble.
        $alone = $this->judgeOnLines($title, 1833, 'monograph', ['rfn-905000' => [
            'Ligne de Lyon-Perrache P1 à Marseille-St-Charles (via Grenoble)', [['Lyon-Perrache', 'Lyon'], ['Grenoble', 'Grenoble'], ['Marseille-St-Charles', 'Marseille']], 1858,
        ]]);
        $this->assertSame([], (new ArchiveMatcher)->winners(array_values($alone)));

        // « avec » loin du trajet et suivi d'autre chose qu'une voie (« avec les indications ») ne marque rien.
        $map = $this->judge($this->record("Carte d'une partie du bassin de la Seine entre Paris et la mer avec les indications de plusieurs tracés de chemins de fer entre Paris, Rouen, Le Hâvre et Dieppe [...]", 1837, 'map'))['rfn-340000'];
        $this->assertNotContains('secondary_mention', self::codes($map));
    }

    public function test_two_places_separated_by_a_spaced_dash_are_a_route(): void
    {
        $m = $this->judge($this->record('Catastrophe de chemin de fer [déraillement du rapide Paris - Le Havre, près de Tours]', 1926, 'image'))['rfn-340000'];

        $this->assertSame(['route', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));
        $this->assertSame(10, $m->score);
    }

    public function test_a_printed_year_far_from_the_record_date_makes_the_date_doubtful(): void
    {
        $m = $this->judge($this->record('Inauguration du chemin de fer de Paris au Havre, le 5 avril 1847', 1978))['rfn-340000'];
        $this->assertContains('date_doubtful', self::codes($m));
        $this->assertSame(9, $m->score);

        $near = $this->judge($this->record('Inauguration du chemin de fer de Paris au Havre, le 5 avril 1847', 1848))['rfn-340000'];
        $this->assertContains('date_in_life', self::codes($near));
    }

    public function test_a_document_on_two_lines_keeps_only_the_better_one(): void
    {
        $matcher = new ArchiveMatcher;
        $reason = fn (string $code) => [['code' => $code, 'points' => ArchiveMatcher::POINTS[$code]]];
        // Scores différents : seule la meilleure ligne garde le document.
        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $matcher->winners([
            new ArchiveMatch(1, 11, $reason('rail_route'), [], null, ['a', 'b']),
            new ArchiveMatch(2, 9, $reason('rail_route'), [], null, ['c', 'd']),
        ])));
        // Même score : la ligne reliée par un trajet passe avant celle dont le titre nomme seulement deux lieux
        // (« Chemin de fer d'Auch à Lannemezan, d'Arreau à Saint-Lary », revue du commit bb27386).
        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $matcher->winners([
            new ArchiveMatch(2, 9, $reason('two_places'), [], null, ['lannemezan', 'arreau']),
            new ArchiveMatch(1, 9, $reason('rail_route'), [], null, ['auch', 'lannemezan']),
        ])));
    }

    public function test_a_horse_tramway_is_not_the_railway(): void
    {
        $m = $this->judge($this->record('Chemin de fer américain de Paris à Rouen', 1861))['rfn-340000'];

        $this->assertSame('other_transport', $m->rejected);
    }

    public function test_an_undated_document_shared_by_two_lines_goes_to_the_oldest(): void
    {
        $matcher = new ArchiveMatcher;
        $undated = [['code' => 'date_unknown', 'points' => 0]];
        $old = new ArchiveMatch(1, 9, $undated, [], null, ['paris', 'rouen'], 1840);
        $recent = new ArchiveMatch(3, 9, $undated, [], null, ['paris', 'rouen'], 1868);

        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $matcher->winners([$recent, $old])));
    }

    public function test_a_plain_route_between_two_stations_needs_the_ends(): void
    {
        // « chaîne des Vosges de Saverne à Sélestat » : un trajet, pas une voie ferrée ; Poissy et Rouen ne
        // sont pas les extrémités de la ligne du Havre, le document reste sous le seuil.
        $m = $this->judge($this->record('Chemin de fer : panorama des coteaux de la Seine, vue de Poissy à Rouen', 1850, 'image'))['rfn-340000'];

        $this->assertSame(['route', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));
        $this->assertSame(8, $m->score);
        $this->assertFalse($m->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_the_parts_of_a_title_are_not_read_as_one_compound_name(): void
    {
        $record = new ArchiveRecord('ark:/12148/btv1b1', null, 'Chemin de fer de Paris à Rouen : Partie', 'Chemin de fer de Paris à Rouen : Partie', 'map', [],
            new ArchiveDate('1843', 1843, null, null, 'year'), null, null);

        $this->assertSame(['Paris', 'Rouen'], $this->judge($record)['rfn-340000']->places);
    }

    public function test_the_metro_is_not_a_railway_line(): void
    {
        $m = $this->judge($this->record('Construction du chemin de fer métropolitain municipal de Paris. Viaduc de Poissy, ligne vers Rouen', 1903, 'image'))['rfn-340000'];

        $this->assertSame('other_transport', $m->rejected);
    }

    public function test_a_tie_between_more_than_two_lines_is_ambiguous(): void
    {
        $matcher = new ArchiveMatcher;
        $tie = fn (int $id) => new ArchiveMatch($id, 10, [], [], null, ['a', 'b']);

        $this->assertCount(2, $matcher->winners([$tie(1), $tie(2), new ArchiveMatch(3, 9, [], [], null, ['a', 'c'])]));
        $this->assertSame([], $matcher->winners([$tie(1), $tie(2), $tie(3)]));
        $this->assertSame([], $matcher->winners([new ArchiveMatch(1, 8, [], [])]));
        $this->assertSame([], $matcher->winners([new ArchiveMatch(1, 11, [], [], 'no_rail_word')]));
    }

    public function test_dates_after_closure_and_unknown_dates(): void
    {
        $after = $this->judge($this->record("Souvenirs du chemin de fer d'Angoulême à Limoges", 2020))['rfn-610000'];
        $this->assertContains('date_after_closure', self::codes($after));

        $undated = $this->judge($this->record("Chemin de fer d'Angoulême à Limoges", null))['rfn-610000'];
        $this->assertContains('date_unknown', self::codes($undated));
        $this->assertSame(9, $undated->score);
    }

    /** @return iterable<string, array{string, ?array{0: string, 1: string}}> */
    public static function names(): iterable
    {
        yield 'de X à Y' => ['Ligne de Limoges-Bénédictins à Angoulême', ['Limoges-Bénédictins', 'Angoulême']];
        yield 'au' => ['Ligne de Paris-St-Lazare au Havre', ['Paris-St-Lazare', 'Havre']];
        yield 'élision et par' => ["Ligne d'Épernay à Reims par Rilly", ['Épernay', 'Reims']];
        yield 'du' => ['Ligne du Pouzin à Privas', ['Pouzin', 'Privas']];
        yield 'chemin de fer' => ['Chemin de fer de Paris à Lyon', ['Paris', 'Lyon']];
        yield 'parenthèse' => ['Ligne militaire de Maricourt à Péronne (1914-1918)', ['Maricourt', 'Péronne']];
        yield 'tiret' => ["Ligne L'Aigle - Conches", ["L'Aigle", 'Conches']];
        yield 'un seul lieu' => ['Voie-mère de Calais', null];
    }

    /** @param ?array{0: string, 1: string} $expected */
    #[DataProvider('names')]
    public function test_route_ends_are_read_from_line_names(string $name, ?array $expected): void
    {
        $this->assertSame($expected, LinePlaceIndex::routeEnds($name));
    }

    public function test_line_places_use_terminus_cities_and_skip_connections(): void
    {
        $line = $this->index->line(2);
        $this->assertNotNull($line);
        $this->assertTrue($line->areEnds('limoges', 'angouleme'));
        $this->assertSame([['Limoges', 'Angoulême']], $line->queryPairs);
        $this->assertArrayHasKey('saillat sur vienne', $line->places);
        $this->assertArrayHasKey('saillat chassenon', $line->places);

        // Ligne de ceinture : ses deux gares terminus sont dans Paris, Paris n'est donc pas une extrémité.
        $belt = LinePlaceIndex::linePlaces(['id' => 8, 'slug' => 'rfn-980000', 'name' => 'Ligne de ceinture', 'stations' => [
            ['name' => 'Passy', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Paris-Bestiaux', 'city' => 'Paris', 'pk_m' => 9000],
        ]]);
        $this->assertSame([[['passy'], ['paris bestiaux']]], $belt->endPairs);
        $this->assertFalse($belt->areEnds('passy', 'paris'));

        $connection = LinePlaceIndex::linePlaces(['id' => 9, 'slug' => 'rfn-9', 'name' => 'Raccordement de Bourges à Pont-Vert']);
        $this->assertSame([], $connection->queryPairs);
    }
}
