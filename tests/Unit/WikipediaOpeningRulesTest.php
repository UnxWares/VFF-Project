<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ouvertures lues à tort dans les articles Wikipédia (WikipediaLineParser, sans réseau), avec
 * les phrases réelles des événements « opening » relevés le 2026-10-19 (line_events.source_quote) :
 * une formulation d'ÉTAT (« restant ouvert administrativement »), une électrification ou une
 * mise sous tension, la mise en service d'un SERVICE (train, relation, TGV, aller-retours), d'un
 * équipement (évitements, poste, signalisation) ou d'une AUTRE ligne (LGV) n'ouvre pas la ligne.
 */
class WikipediaOpeningRulesTest extends TestCase
{
    private WikipediaLineParser $p;

    protected function setUp(): void
    {
        $this->p = new WikipediaLineParser;
    }

    /** @return list<array{0: string, 1: string}> [type, date] des ouvertures (opening*) */
    private function openings(string $wikitext, string $title): array
    {
        return array_values(array_map(fn ($e) => [$e['type'], $e['date']],
            array_filter($this->p->parseChronology($wikitext, $title), fn ($e) => str_starts_with($e['type'], 'opening'))));
    }

    /** @return array<string, array{0: string, 1: string}> wikitexte, titre de l'article */
    public static function notAnOpeningProvider(): array
    {
        return [
            '643000 état : restant ouvert administrativement' => ["== Histoire ==\n=== Fermeture ===\nLe trafic des marchandises cesse par étapes : celui de Mouchan à Eauze en 1971, celui d'Eauze à Riscle fin 2007 (date des dernières circulations, restant cependant ouvert administrativement).",
                'Ligne de Port-Sainte-Marie à Riscle'],
            '805000 état : était ouverte … jusqu\'au' => ["== Exploitation et trafic ==\nLa ligne était ouverte au trafic voyageurs entre Alès et Robiac (Relation ferroviaire Alès-Bessèges) jusqu'au 6 juillet 2012.",
                'Ligne du Teil à Alès'],
            '420000 électrification : (mise en service le …)' => ["== Histoire ==\n=== De Paris à Brest ===\nL'électrification en 25 kV - 50 Hz est réalisée par étapes : entre Le Mans et Laval (mise en service le 23 mai 1965), entre Laval et Rennes (mise en service le 1 juillet 1965).",
                'Ligne de Paris-Montparnasse à Brest'],
            '026000 titre Électrification' => ["== Infrastructure ==\n=== Électrification ===\nLa partie nord de la ligne a été électrifiée en 25 kV – 50 Hz entre Saint-Germain-sur-Meuse et Pagny-sur-Meuse (mise en service le 1 mars 1984).",
                'Ligne de Bologne à Pagny-sur-Meuse'],
            '220000 mise sous tension puis inauguration' => ["== Chronologie ==\n=== Électrification ===\n* 25 mai 1956 (mise sous tension), 9 août 1956 (inauguration)",
                'Ligne de Valleroy-Moineville à Mancieulles'],
            '830000 ouverture au service commercial de l\'électrification' => ["== Histoire ==\n=== Électrification de la ligne ===\nLaroche - Migennes – Dijon. Mise sous tension. L'ouverture au service commercial eut lieu le 22 décembre 1949 :",
                'Ligne de Paris-Lyon à Marseille-Saint-Charles'],
            '081000 électrifiée pour l\'ouverture de la LGV' => ["== Historique ==\nSeule la section de Châlons-en-Champagne à la bifurcation de Saint-Hilaire-au-Temple est électrifiée, en 25 kV – 50 Hz, pour l'ouverture de la LGV Est européenne en 2007.",
                'Ligne de Châlons-en-Champagne à Reims'],
            '890000 réélectrifiée en vue du Léman Express' => ["== Histoire ==\nLa section Bellegarde - Genève est réélectrifiée en 25 kV 50 Hz en 2014 en vue de la mise en service du Léman Express.",
                'Ligne de Lyon-Perrache à Genève'],
            '790000 un train baptisé Le Cévenol' => ["== Historique ==\n=== Le Cévenol ===\nEn 1955, un train baptisé Le Cévenol fut créé entre Vichy et Nîmes et prolongé jusqu'à Marseille pour développer le tourisme sur la ligne des Cévennes.",
                'Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac'],
            '570000 les Aqualys, mis en service' => ["== Histoire ==\n=== L'avènement du TGV ===\nParmi les liaisons, les Aqualys, mis en service en 1999 entre Paris-Austerlitz et Tours et remplacés par des Intercités en 2011, sont parmi les seules liaisons régionales françaises effectuées à 200 km/h.",
                'Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean'],
            '285000 relations omnibus prolongées' => ["== Histoire ==\nEn 1936, cinq relations omnibus relient Hénin-Liétard à Don - Sainghin dont quatre sont prolongés jusqu'à Lille.",
                'Ligne de Bauvin - Provin à Lille'],
            '354000 mise en service de trains directs' => ["== Histoire ==\nEn 1899, un raccordement est établi à Clères, permettant la mise en service de trains directs entre Le Havre et Amiens.",
                'Ligne de Motteville à Montérolier-Buchy'],
            '366000 service fret avec rotations par jour' => ["== Desserte ==\n=== Fret ferroviaire ===\nUne autoroute ferroviaire relie Cherbourg à Mouguerre. Elle est mise en service le 20 mai 2025 avec deux rotations par jour, trois fois par semaine.",
                'Ligne de Mantes-la-Jolie à Cherbourg'],
            '515000 évitements mis en service' => ["== Infrastructure ==\nDans le sens impair (de Tours vers Saint-Nazaire), elle est équipée d'évitements à Port-Boulet mis en service en 1974, à Saumur, Angers et Savenay.",
                'Ligne de Tours à Saint-Nazaire'],
            '883000 mise en service du BMU' => ["== Caractéristiques ==\n=== Signalisation ===\nAoût 1954, mise en service du BMU-DV de Mâcon à Bourg-en-Bresse.",
                'Ligne de Mâcon à Ambérieu'],
            '070000 mise en service de la LGV Est (lien)' => ["== Trafic ==\n=== Chronologie ===\n* 10 juin 2007 : mise en service de la première section de la [[LGV Est européenne]] (entre Vaires-sur-Marne et Baudrecourt), utilisée par les TGV reliant Paris à Strasbourg.",
                'Ligne de Paris-Est à Strasbourg-Ville'],
            '752000 une autre LGV que celle de l\'article' => ["== Historique ==\n=== Chronologie ===\n* 13 décembre 1992 : mise en service du tronçon nord de la LGV Rhône-Alpes (de Montanay à Saint-Quentin-Fallavier).",
                'LGV Sud-Est'],
            '677000 LGV vers Barcelone' => ["== Trafic ==\nÀ la suite de la mise en service de la ligne LGV vers Barcelone à partir du mois de décembre 2013, les Talgo reliant cette ville à Montpellier furent remplacés par plusieurs TGV.",
                'Ligne de Narbonne à Port-Bou'],
            '928000 détournement à titre exceptionnel' => ["== Exploitation ==\nCependant, à titre exceptionnel, la ligne a été ouverte, de janvier 2007 à décembre 2008, à quelques circulations voyageurs de la relation Marseille - Briançon détournées par cet itinéraire.",
                'Ligne de Rognac à Aix-en-Provence'],
            '005000 ouvrage inauguré' => ["== La ligne ==\nElles sont donc rénovées avec, notamment pour la ligne Strasbourg – Kehl, la construction d'un nouveau pont sur le Rhin au niveau de Kehl, ouvrage qui a été inauguré en 2010.",
                'LGV Est européenne'],
        ];
    }

    #[DataProvider('notAnOpeningProvider')]
    public function test_sentences_that_do_not_open_the_line(string $wikitext, string $title): void
    {
        $this->assertSame([], $this->openings($wikitext, $title));
    }

    public function test_electrification_is_still_extracted_when_the_opening_is_dropped(): void
    {
        $events = $this->p->parseChronology("== Infrastructure ==\n=== Électrification ===\nLa partie nord de la ligne a été électrifiée en 25 kV – 50 Hz entre Saint-Germain-sur-Meuse et Pagny-sur-Meuse (mise en service le 1 mars 1984).",
            'Ligne de Bologne à Pagny-sur-Meuse');

        $this->assertContains('electrification', array_column($events, 'type'));
        $this->assertNotContains('opening', array_column($events, 'type'));
    }

    /** @return array<string, array{0: string, 1: string, 2: list<array{0: string, 1: string}>}> */
    public static function realOpeningProvider(): array
    {
        return [
            'mise en service, puis électrifiée' => ["== Histoire ==\nLa ligne est mise en service en 1848 par la Compagnie des chemins de fer du Nord et électrifiée par la SNCF en 1964.",
                'Ligne de Lille à Hazebrouck', [['opening', '1848-01-01']]],
            'la ligne sujet, un train cité avant le verbe' => ["== Histoire ==\nLa ligne, essayée par un train spécial, est ouverte le 3 mars 1880.",
                'Ligne de Alpha à Bravo', [['opening', '1880-03-03']]],
            'section ouverte' => ["== Histoire ==\nLa section de Colmar à Munster est ouverte le 3 décembre 1868 par la Compagnie des chemins de fer de l'Est.",
                'Ligne de Colmar à Metzeral', [['opening', '1868-12-03']]],
            'section parcourue par des trains de marchandises, ouverte aux voyageurs' => ["== Histoire ==\nLa section de Colmar à Munster, parcourue par des trains de marchandises, est ouverte aux voyageurs le 1er mai 1885.",
                'Ligne de Colmar à Metzeral', [['opening_passenger', '1885-05-01']]],
            'LGV de l\'article' => ["== La branche Est ==\n=== Chronologie ===\n* 11 décembre 2011 : mise en service commerciale de la première phase de la branche Est de la LGV Rhin-Rhône.",
                'LGV Rhin-Rhône', [['opening', '2011-12-11']]],
        ];
    }

    #[DataProvider('realOpeningProvider')]
    public function test_real_openings_are_kept(string $wikitext, string $title, array $expected): void
    {
        $this->assertSame($expected, $this->openings($wikitext, $title));
    }
}
