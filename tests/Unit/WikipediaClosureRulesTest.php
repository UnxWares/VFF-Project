<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fermetures aux voyageurs dans les articles Wikipédia (WikipediaLineParser, sans réseau),
 * avec les phrases réelles des articles qui produisaient de fausses fermetures ou en
 * perdaient de vraies (audit du 2026-10-19 : fermetures contredites par une desserte
 * voyageurs actuelle).
 *
 * Règle métier : une desserte voyageurs réduite (omnibus supprimés, trains directs ou
 * saisonniers maintenus, trains de fin de semaine) reste du voyageur, la portion n'est
 * pas « fret seul » : pas d'événement de fermeture, un événement « Réduction de la
 * desserte voyageurs » à la place.
 */
class WikipediaClosureRulesTest extends TestCase
{
    private WikipediaLineParser $p;

    protected function setUp(): void
    {
        $this->p = new WikipediaLineParser;
    }

    /** Événements réduits à [type, date, de, à], sans les concessions de l'infobox. */
    private function events(string $wikitext, string $title): array
    {
        return array_values(array_map(fn ($e) => [$e['type'], $e['date'], $e['section_from'], $e['section_to']],
            array_filter($this->p->parseChronology($wikitext, $title), fn ($e) => ! str_starts_with($e['type'], 'concession'))));
    }

    /** Mêmes événements, dans l'ordre (date, type) : l'ordre de sortie du parseur n'est pas garanti. */
    private function sorted(string $wikitext, string $title): array
    {
        $events = $this->events($wikitext, $title);
        usort($events, fn ($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        return $events;
    }

    private function types(string $wikitext, string $title): array
    {
        return array_column($this->events($wikitext, $title), 0);
    }

    // ─── Ligne 379000 Mézidon - Trouville-Deauville (cas signalé) ─────────

    private const MEZIDON = <<<'WIKI'
== Histoire ==
=== Fermeture de la section Mézidon - Dives ===
Après la guerre, le trafic commence à décliner du fait de la concurrence accrue de l'automobile. Quand l'administration des chemins de fer de l'État est incorporée à la SNCF, une partie de son réseau est fermée au trafic voyageur. C'est le cas de la section Mézidon - Dives-Cabourg à partir du 1er mars 1938 ; à cette même date, la correspondance vers Caen à Dozulé - Putot n'est plus assurée.

En 1944, la ligne est endommagée lors de la bataille de Normandie ; les trains reprennent leurs circulation à partir du 1er août 1945. Le trafic voyageurs cesse le 5 octobre 1947. La section Troarn - Dozulé, jusqu'alors ouverte au fret, est quant à elle définitivement fermée. La SNCF renonce une fois pour toutes au trafic de marchandises entre Mézidon et Dives-Cabourg le 3 novembre 1969.

=== Passage à vide et renaissance de la section Dives - Trouville ===
En 1960, les haltes de Tourgéville et de Gonneville - Saint-Vaast, à caractère rural, présentant un trafic faible, sont fermées. En 1970, la desserte omnibus entre Trouville - Deauville et Dives-Cabourg est supprimée et remplacée par des cars. Il ne reste plus que les trains directs Paris-St-Lazare - Dives-Cabourg qui circulent en saison ainsi qu'un Turbotrain RTG direct à l'année, fonctionnant les fins de semaine, ce jusqu'en 1995.
WIKI;

    public function test_379000_omnibus_service_replaced_by_coaches_while_direct_trains_remain_is_not_a_closure(): void
    {
        $events = $this->p->parseChronology(self::MEZIDON, 'Ligne de Mézidon à Trouville - Deauville');
        $in1970 = array_values(array_filter($events, fn ($e) => $e['date'] === '1970-01-01'));

        $this->assertCount(1, $in1970);
        $this->assertSame('other', $in1970[0]['type']);
        $this->assertStringStartsWith(WikipediaLineParser::SERVICE_REDUCTION_LABEL, $in1970[0]['description']);
        $this->assertSame(['Trouville - Deauville', 'Dives-Cabourg'], [$in1970[0]['section_from'], $in1970[0]['section_to']]);
    }

    public function test_379000_closures_of_the_mezidon_dives_section_are_extracted(): void
    {
        $events = $this->events(self::MEZIDON, 'Ligne de Mézidon à Trouville - Deauville');

        // « C'est le cas de la section … à partir du 1 mars 1938 » reprend la fermeture de la phrase précédente.
        $this->assertContains(['closure_passenger', '1938-03-01', 'Mézidon', 'Dives-Cabourg'], $events);
        // « Le trafic voyageurs cesse le 5 octobre 1947 » : la portion est celle du titre « Fermeture de la section Mézidon - Dives ».
        $this->assertContains(['closure_passenger', '1947-10-05', 'Mézidon', 'Dives'], $events);
        // « La SNCF renonce … au trafic de marchandises … le 3 novembre 1969 ».
        $this->assertContains(['closure_freight', '1969-11-03', 'Mézidon', 'Dives-Cabourg'], $events);
        // Les haltes fermées en 1960 ne ferment pas la ligne.
        $this->assertNotContains('1960-01-01', array_column($events, 1));
    }

    // ─── Desserte omnibus seule, trains de voyageurs maintenus ───────────

    public function test_527000_omnibus_moved_to_road_while_an_express_railcar_remains_is_a_reduction(): void
    {
        $wiki = "== Histoire ==\n=== Évolution de la desserte ===\nLe 27 septembre 1970, le service omnibus sur la ligne est reporté sur la route. La seule circulation d'un train de voyageurs porte ensuite sur l'autorail express Nantes - Poitiers - Limoges, assuré par un X 2400.";

        $this->assertSame(['other'], $this->types($wiki, 'Ligne de Clisson à Cholet'));
    }

    public function test_473000_closure_to_omnibus_passenger_service_is_a_reduction(): void
    {
        // Les trains d'été (Corail directs depuis Paris, puis « Tire-Bouchon » en 1985) ont continué.
        $wiki = "== Histoire ==\n=== Chronologie ===\n* 24 juillet 1882 : mise en service de la ligne d'Auray à Quiberon\n* 6 mars 1972 : fermeture de la ligne au service des voyageurs omnibus\n* 1 juillet 1985 : mise en service du « Tire-Bouchon »";

        $closures = array_filter($this->events($wiki, "Ligne d'Auray à Quiberon"), fn ($e) => preg_match('/^(closure|other)/', $e[0]));

        $this->assertSame([['other', '1972-03-06']], array_values(array_map(fn ($e) => [$e[0], $e[1]], $closures)));
    }

    public function test_one_train_kept_after_the_closure_is_a_reduction(): void
    {
        $wiki = "== Histoire ==\nLa ligne est fermée au service des voyageurs :\n* le 28 septembre 1969 entre Château-Salins et Bénestroff (seul un train Nancy - Bénestroff est maintenu jusqu'au 1 mai 1979),\n* le 31 mai 1970 entre Bénestroff et Sarralbe.";

        $this->assertSame([['other', '1969-09-28'], ['closure_passenger', '1970-05-31']],
            array_map(fn ($e) => [$e[0], $e[1]], $this->events($wiki, 'Ligne de Champigneulles à Sarralbe')));
    }

    public function test_freight_or_special_trains_left_after_a_passenger_closure_do_not_make_a_reduction(): void
    {
        $fret = "== Histoire ==\nLa section de Pons à La Tremblade est fermée au trafic voyageurs le 15 mai 1939. Seul subsiste alors un trafic de fret sur cette ligne.";
        $special = "== Histoire ==\nL'extension de la fermeture au trafic voyageurs à la section de Vias à Paulhan est effectuée le 28 juin 1957, à l'exception de trains spéciaux et du train annuel de pèlerinage (organisé par le diocèse).";

        $this->assertSame(['closure_passenger'], $this->types($fret, 'Ligne de Saintes à Royan'));
        $this->assertSame(['closure_passenger'], $this->types($special, 'Ligne de Vias à Lodève'));
    }

    // ─── Phrases qui ne sont pas des fermetures de la ligne ──────────────

    public function test_272000_no_more_end_to_end_train_is_not_a_closure(): void
    {
        $wiki = "== Histoire ==\nÀ partir de 1999, plus aucun train de voyageurs ne parcourt la ligne de bout en bout en service commercial régulier, situation qui perdure jusqu'à la création d'un TER Paris – Creil – Longueau – Lille en 2021.";

        $this->assertSame([], $this->types($wiki, 'Ligne de Paris-Nord à Lille'));
    }

    public function test_892000_last_steam_train_and_a_station_closed_to_passengers_are_not_line_closures(): void
    {
        $steam = "== Histoire ==\n* En septembre 1972, dernier train assuré avec une locomotive à vapeur (141R du dépôt d'Annemasse) entre Évian-les-Bains et Bellegarde.";
        $station = "== Tracé ==\nAprès quelques courbes difficiles, la ligne arrive à Bossey - Veyrier. Comme Viry, elle est fermée au trafic voyageurs depuis 1996, mais on trouve le quai de chargement des ordures ménagères à destination d'Arlod près de Bellegarde-sur-Valserine dans l'Ain.";

        $this->assertSame([], $this->types($steam, 'Ligne de Longeray-Léaz au Bouveret'));
        $this->assertSame([], $this->types($station, 'Ligne de Longeray-Léaz au Bouveret'));
    }

    public function test_887000_a_council_debating_the_closure_is_not_a_closure(): void
    {
        $wiki = "== Histoire ==\nLe 4 juillet 1937, le conseil municipal de Massieux délibère sur la suppression du service voyageurs sur la ligne. Toujours à Massieux, le 28 mai 1938, la municipalité apprend la décision du Conseil Général de l'Ain sur la suppression du service Voyageurs sur la ligne.\n\nLe service voyageurs cesse le 15 décembre 1938 entre Sathonay et Trévoux.";

        $this->assertSame([['closure_passenger', '1938-12-15', 'Sathonay', 'Trévoux']], $this->events($wiki, 'Ligne de Lyon-Croix-Rousse à Trévoux'));
    }

    // ─── Portion fermée ──────────────────────────────────────────────────

    public function test_705000_section_still_used_is_excluded_from_the_closure(): void
    {
        $wiki = "== Histoire ==\nEn 1969, le trafic voyageurs entre Montluçon et Moulins est reporté sur la route, la section entre Montluçon et Commentry étant toujours utilisée pour les liaisons Intercités de Lyon à Bordeaux (jusqu'en 2012) et TER Montluçon – Clermont-Ferrand via Gannat.";

        $this->assertSame([['closure_passenger', '1969-01-01', 'Commentry', 'Moulins']], $this->events($wiki, 'Ligne de Montluçon à Moulins'));
    }

    public function test_232000_line_named_among_many_closed_lines_takes_the_section_of_the_heading(): void
    {
        $wiki = "== Historique ==\n=== Section d'Ormoy-Villers à Estrées-Saint-Denis ===\nLe 15 mai 1939 est le jour de la suppression du service voyageurs sur la plupart des lignes SNCF de moindre importance dans le département de l'Oise dont celle d'Ormoy-Villers à Boves, mais aussi dans d'autres régions de la France, dans le cadre de la politique de la coordination.";

        $this->assertSame([['closure_passenger', '1939-05-15', 'Ormoy-Villers', 'Estrées-Saint-Denis']], $this->events($wiki, "Ligne d'Ormoy-Villers à Boves"));
    }

    public function test_317000_general_measure_is_skipped_and_the_sections_closed_at_that_moment_are_dated(): void
    {
        $wiki = "== Histoire ==\n=== Section de Rochy-Condé à Compiègne ===\nLe 15 mai 1939 est le jour de la suppression du service voyageurs sur la plupart des lignes SNCF de moindre importance dans le département de l'Oise, mais aussi dans d'autres régions de la France, dans le cadre de la politique de coordination. Cette dernière poursuit officiellement l'objectif de mettre fin à la compétition entre les lignes d'autocars privées et le chemin de fer. Le service voyageurs entre Beauvais et Estrées-Saint-Denis, entre La Rue-Saint-Pierre et Saint-Just-en-Chaussée et entre Verberie et Estrées cesse à ce moment.";

        $this->assertSame([['closure_passenger', '1939-05-15', 'Beauvais', 'Estrées-Saint-Denis']], $this->events($wiki, 'Ligne de Rochy-Condé à Soissons'));
    }

    public function test_another_subject_between_the_passenger_service_and_the_closure_keeps_a_total_closure(): void
    {
        $wiki = "== Histoire ==\nLe service voyageurs est interrompu le 15 mai 1939, la ligne est fermée le 17 décembre 1951 et déferrée en 1955.";

        $this->assertContains('closure', $this->types($wiki, 'Ligne de Carhaix à Morlaix'));
        $this->assertNotContains('closure_passenger', $this->types($wiki, 'Ligne de Carhaix à Morlaix'));
    }

    // ─── Listes de fermetures ────────────────────────────────────────────

    public function test_027000_bullets_announced_by_a_freight_closure_sentence_are_freight_closures(): void
    {
        // Sous le titre « Après la mise en service », les puces étaient prises pour des ouvertures.
        $wiki = "== Historique ==\n=== Après la mise en service ===\nLa totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.\n\nLe trafic des marchandises a été supprimé en cinq étapes :\n* de Grand - Avranville à Sionne - Midrevaux, le 3 mars 1969 ;\n* de Gondrecourt-le-Château à Dainville, le 16 décembre 1973 ;\n* de Nançois-Tronville à Velaines en 2020.";

        $this->assertSame([
            ['closure_freight', '1969-03-03', 'Grand - Avranville', 'Sionne - Midrevaux'],
            ['closure_passenger', '1969-03-03', null, null],
            ['closure_freight', '1973-12-16', 'Gondrecourt-le-Château', 'Dainville'],
            ['closure_freight', '2020-01-01', 'Nançois-Tronville', 'Velaines'],
        ], $this->sorted($wiki, 'Ligne de Nançois - Tronville à Neufchâteau'));
    }

    public function test_the_traffic_of_the_list_is_the_one_of_the_last_clause(): void
    {
        $wiki = "== Histoire ==\nLa ligne a été fermée au trafic des voyageurs en 1939 et au trafic des marchandises en plusieurs étapes :\n* 1952 : section de L'Hospitalet-du-Larzac à Avèze - Molières.\n* 1971 : section d'Avèze - Molières au Vigan.";

        $this->assertSame([
            ['closure_passenger', '1939-01-01', null, null],
            ['closure_freight', '1952-01-01', "L'Hospitalet-du-Larzac", 'Avèze - Molières'],
            ['closure_freight', '1971-01-01', 'Avèze - Molières', 'Le Vigan'],
        ], $this->sorted($wiki, 'Ligne de Tournemire - Roquefort au Vigan'));
    }

    public function test_600000_but_total_closure_later_is_a_second_closure(): void
    {
        $wiki = "== Histoire ==\n=== Fermeture des tronçons extrêmes ===\n* Luçay-le-Mâle - Buzançais : fermeture au trafic des voyageurs le 27 septembre 1980, mais fermeture totale en 1988.";

        $this->assertSame([
            ['closure_passenger', '1980-09-27', 'Luçay-le-Mâle', 'Buzançais'],
            ['closure', '1988-01-01', 'Luçay-le-Mâle', 'Buzançais'],
        ], $this->sorted($wiki, 'Chemin de fer du Blanc-Argent'));
    }

    // ─── Plusieurs événements datés dans la même phrase (audit trafic des ères passées) ──
    //
    // Chaque mot-clé prend SA date : une date placée entre le mot-clé précédent et celui-ci
    // appartient au précédent (sinon « fermée aux voyageurs » prenait la date de l'ouverture,
    // et la ligne devenait « fret » dès sa mise en service). Le trafic d'un autre événement
    // de la phrase ne déteint pas sur lui.

    /** @return array<string, array{0: string, 1: string, 2: list<array{0: string, 1: string, 2: ?string, 3: ?string}>}> */
    public static function datedPairProvider(): array
    {
        return [
            '333000 ouverte le … et fermée au service des voyageurs le …' => ['Ligne de Goincourt à Gournay - Ferrières',
                'La ligne a été ouverte le 4 août 1870 et fermée au service des voyageurs le 6 mars 1939.',
                [['opening', '1870-08-04', null, null], ['closure_passenger', '1939-03-06', null, null]]],
            '578000 mise en service le … et fermée au trafic voyageurs le …' => ['Ligne d\'Aiffres à Ruffec',
                'La ligne a été mise en service le 23 février 1885 et fermée au trafic voyageurs le 2 octobre 1938.',
                [['opening', '1885-02-23', null, null], ['closure_passenger', '1938-10-02', null, null]]],
            'tramway inauguré et fermé, dates sans mois' => ['Chemin de fer de Roisel à Hargicourt',
                'La ligne est inaugurée le 25 08 1907 et fermée le 01 06 1932.',
                [['opening', '1907-01-01', null, null], ['closure', '1932-01-01', null, null]]],
            'ouverte en …, fermée aux voyageurs en … (qualificatif de la fermeture seule)' => ['Ligne de Alpha à Bravo',
                'La ligne, ouverte en 1880, est fermée au service des voyageurs en 1938.',
                [['opening', '1880-01-01', null, null], ['closure_passenger', '1938-01-01', null, null]]],
            '409000 réservée au fret plus tard : ouverture à tout trafic' => ['Ligne de Chartres à Dreux',
                'La ligne de Chartres à Dreux est une ligne de chemin de fer, ouverte entre Chartres et Dreux en 1873, réservée au fret depuis 1971.',
                [['opening', '1873-01-01', null, null]]],
            '288000 affectée au fret sans autre date : ouverture au fret' => ['Ligne de Bully - Grenay à La Bassée - Violaines',
                'Ouverte en 1865 par la Compagnie des mines de Béthune, la ligne est exclusivement affectée au trafic des marchandises.',
                [['opening_freight', '1865-01-01', null, null]]],
            '436000 voyageurs en totalité, puis fret section par section' => ['Ligne de La Chapelle-Anthenaise à Flers',
                'La ligne est fermée en 1970 en totalité aux voyageurs le 31 mai 1970, au trafic marchandises de Céaucé à Ambrières le 31 mai 1970, d\'Ambrières à Mayenne en mars 1987, de Domfront à Céaucé le 31 mai 1987, de Flers à Domfront le 1 octobre 1990.',
                [['closure_passenger', '1970-01-01', null, null], ['closure_freight', '1970-05-31', 'Céaucé', 'Ambrières'],
                    ['closure_freight', '1987-03-01', 'Ambrières', 'Mayenne'], ['closure_freight', '1987-05-31', 'Domfront', 'Céaucé'],
                    ['closure_freight', '1990-10-01', 'Flers', 'Domfront']]],
            '432000 fermée aux voyageurs, au fret par section' => ['Ligne d\'Alençon à Domfront',
                'La ligne est fermée au trafic voyageurs le 15 mai 1938, au trafic marchandises de Couterne à Juvigny le 7 mai 1971, de Pré-en-Pail à Couterne le 15 juillet 1989.',
                [['closure_passenger', '1938-05-15', null, null], ['closure_freight', '1971-05-07', 'Couterne', 'Juvigny'],
                    ['closure_freight', '1989-07-15', 'Pré-en-Pail', 'Couterne']]],
            '647000 fermé au service voyageurs, au trafic marchandises' => ['Ligne de Bon-Encontre à Vic-en-Bigorre',
                'Le tronçon de Vic-en-Bigorre à Auch est fermé au service voyageurs le 15 mai 1939, au trafic marchandises le 28 juin 1959.',
                [['closure_passenger', '1939-05-15', 'Vic-en-Bigorre', 'Auch'], ['closure_freight', '1959-06-28', 'Vic-en-Bigorre', 'Auch']]],
            '423000 fermée en …, retranchée le … : chaque mot-clé sa date' => ['Ligne d\'Alençon à Condé-sur-Huisne',
                'Fermée définitivement en 1989, la ligne est retranchée du réseau ferré national le 17 novembre 2001.',
                [['closure', '1989-01-01', null, null], ['declassification', '2001-11-17', null, null]]],
        ];
    }

    #[DataProvider('datedPairProvider')]
    public function test_each_keyword_of_a_sentence_takes_its_own_date_and_traffic(string $title, string $sentence, array $expected): void
    {
        $this->assertSame($expected, $this->sorted("== Histoire ==\n{$sentence}", $title));
    }

    public function test_a_temporary_closure_next_to_an_opening_is_still_ignored(): void
    {
        // Fermeture temporaire (déraillement, « pendant plusieurs jours ») : pas une fermeture,
        // même quand la phrase date aussi l'ouverture.
        $wiki = "== Histoire ==\nOuverte le 4 août 1870, la ligne est fermée pendant plusieurs jours en 1910 à la suite d'un déraillement.";

        $this->assertSame([['opening', '1870-08-04', null, null]], $this->sorted($wiki, 'Ligne de Alpha à Bravo'));
    }

    public function test_a_single_date_still_serves_every_keyword_of_the_sentence(): void
    {
        // Sans date propre après lui, un mot-clé garde la date la plus proche (règle inchangée).
        $wiki = "== Histoire ==\nLe 2 octobre 1938, la ligne est fermée aux voyageurs et déclassée.";

        $this->assertSame([['closure_passenger', '1938-10-02', null, null], ['declassification', '1938-10-02', null, null]],
            $this->sorted($wiki, 'Ligne de Alpha à Bravo'));
    }

    /** @return array<string, array{0: string, 1: list<array{0: string, 1: string, 2: ?string, 3: ?string}>}> */
    public static function twoTrafficsOneDateProvider(): array
    {
        return [
            'aux voyageurs et aux marchandises en …' => ['La ligne est fermée aux voyageurs et aux marchandises en 1950.', [['closure', '1950-01-01', null, null]]],
            '063000 au service des voyageurs et des marchandises le …' => ['La ligne a été fermée au service des voyageurs et des marchandises le 21 mars 1988.',
                [['closure', '1988-03-21', null, null]]],
            'au service des voyageurs et au service des marchandises le …' => ['La ligne est fermée au service des voyageurs et au service des marchandises le 1er mars 1950.',
                [['closure', '1950-03-01', null, null]]],
            'chaque trafic sa date : inchangé' => ['La ligne est fermée aux voyageurs en 1938 et aux marchandises en 1950.',
                [['closure_passenger', '1938-01-01', null, null], ['closure_freight', '1950-01-01', null, null]]],
        ];
    }

    #[DataProvider('twoTrafficsOneDateProvider')]
    public function test_two_traffics_closed_on_one_date_are_a_total_closure(string $sentence, array $expected): void
    {
        // Avant : la phrase était coupée à « et aux marchandises », la première moitié restait
        // sans date, la seconde sans sujet : aucune fermeture (la ligne restait « mixte »).
        $this->assertSame($expected, $this->sorted("== Histoire ==\n{$sentence}", 'Ligne de Alpha à Bravo'));
    }

    /** Portion de l'événement : [type, date, de, à, portion inconnue ?] */
    private function withPortion(string $sentence, string $title = 'Ligne de Alpha à Bravo'): array
    {
        $events = array_values(array_filter($this->p->parseChronology("== Histoire ==\n{$sentence}", $title), fn ($e) => ! str_starts_with($e['type'], 'concession')));
        usort($events, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        return array_map(fn ($e) => [$e['type'], $e['date'], $e['section_from'], $e['section_to'], $e['portion_unknown']], $events);
    }

    public function test_whole_line_passenger_closure_next_to_freight_closures_by_section_stays_whole(): void
    {
        // 303000 : la section citée est celle de la fermeture au fret, extraite avec elle ; la
        // fermeture aux voyageurs vaut pour toute la ligne (avant : « portion inconnue », ignorée).
        $this->assertSame([
            ['closure_passenger', '1939-01-09', null, null, false],
            ['closure_freight', '1940-01-01', 'Bravo', 'Charlie', false],
            ['closure_freight', '1958-11-01', 'Charlie', 'Delta', false],
        ], $this->withPortion('La ligne est fermée au trafic voyageurs le 9 janvier 1939, au trafic marchandises sur la section de Bravo à Charlie (10,2 km) en 1940, de Charlie à Delta (4,5 km) le 1er novembre 1958.', 'Ligne de Bravo à Delta'));
    }

    public function test_704000_freight_closures_by_section_after_a_whole_line_passenger_closure(): void
    {
        $this->assertSame([
            ['closure_passenger', '1940-06-27', null, null, false],
            ['closure_freight', '1950-05-14', 'Alpha', 'Lafat', false],
            ['closure_freight', '1952-05-18', 'Lafat', 'Bravo', false],
        ], $this->withPortion('Déficitaire, la ligne a été fermée au service des voyageurs le 27 juin 1940 et au service des marchandises le 14 mai 1950 pour le tronçon de Alpha à Lafat et le 18 mai 1952 pour le tronçon de Lafat à Bravo.'));
    }

    public function test_a_portion_cited_without_an_event_keeps_the_doubt(): void
    {
        // 876000 : « Du côté suisse » est une portion (côté d'une frontière) : le démontage n'est
        // pas étendu à toute la ligne. (La proposition suivante porte sa propre fin du fret, cf.
        // WikipediaPetitsCorrectifsTest.)
        $events = $this->withPortion('Du côté suisse la ligne fut démontée entre 1942 et 1943, alors que le trafic marchandise perdura entre Alpha et la gare de Charlie jusqu\'au 3 novembre 1969.', 'Ligne de Alpha à Bravo');

        $this->assertSame(['dismantling', '1942-01-01', null, null, true], $events[0]);
    }
}
