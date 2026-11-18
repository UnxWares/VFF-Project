<?php

namespace Tests\Feature\I18n;

use App\Console\Commands\OhmEvents;
use App\Models\Line;
use App\Services\SecondaryNetworkParser;
use App\Services\StationLineCoverage;
use App\Services\StationTimeline;
use App\Services\WikipediaLineParser;
use App\Support\Advocacy;
use App\Support\EventText;
use App\Support\GeneratedText;
use Database\Seeders\AdvocacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Textes générés par VFF dans la langue de la page (point 7 du chantier fiche-contenus) :
 * plaidoyer (lines.advocacy), notes des fiches gare (GeneratedText), descriptions et citations
 * des événements de la chronique (EventText). Règles : le français stocké reste la référence,
 * chaque gabarit généré est reconnu et traduit en EN et DE, les noms propres ne changent pas,
 * ce qui reste une citation française est signalé (lang = fr), jamais de tiret long.
 */
class GeneratedTextsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const LOCALES = ['en', 'de'];

    // ─── Plaidoyer ─────────────────────────────────────────────────────

    public function test_advocacy_seeder_stores_faithful_english_and_german_texts(): void
    {
        Line::factory()->create(['code' => '878000', 'name' => 'Ligne d\'Andelot-en-Montagne à La Cluse']);
        Line::factory()->create(['code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême']);
        $this->seed(AdvocacySeeder::class);

        $a = json_decode((string) DB::table('lines')->where('code', '610000')->value('advocacy'), true);
        $this->assertSame('Rouvrons la ligne Angoulême - Limoges', $a['title'], 'le français reste la référence');
        $en = Advocacy::localized($a, 'en');
        $de = Advocacy::localized($a, 'de');
        // Mêmes faits que le français : date, durée annoncée, lien d'intérêts.
        $this->assertStringContainsString('13 March 2018', $en['message']);
        $this->assertStringContainsString('three weeks', $en['message']);
        $this->assertStringContainsString('We are members of AngouLim because we support this initiative.', $en['message']);
        $this->assertStringContainsString('13. März 2018', $de['message']);
        $this->assertStringContainsString('drei Wochen', $de['message']);
        $this->assertStringContainsString('Wir sind Mitglied von AngouLim, weil wir diese Initiative unterstützen.', $de['message']);
        $this->assertSame(['title' => 'en', 'message' => 'en', 'cta_label' => 'en'], $en['lang']);
        $this->assertSame('Support AngouLim', $en['cta_label']);
        $this->assertSame('AngouLim unterstützen', $de['cta_label']);
        $this->assertSame($a['cta_url'], $en['cta_url']);

        $h = json_decode((string) DB::table('lines')->where('code', '878000')->value('advocacy'), true);
        foreach (['fr', 'en', 'de'] as $locale) {
            $l = Advocacy::localized($h, $locale);
            $this->assertStringContainsString('Hirondelles', $l['title']);
            $this->assertSame($locale, $l['lang']['message']);
            $this->assertDoesNotMatchRegularExpression('/—|--/u', implode(' ', [$l['title'], $l['message'], $l['cta_label']]));
        }
    }

    public function test_flag_threatened_takes_translations_and_falls_back_honestly(): void
    {
        Line::factory()->create(['code' => '100000', 'name' => 'Ligne test']);
        $this->artisan('vff:flag-threatened', ['code' => '100000', '--title' => 'Sauvons la ligne', '--title-en' => 'Save the line',
            '--message' => 'Écrivez au préfet.', '--message-de' => 'Schreiben Sie dem Präfekten.'])->assertSuccessful();
        $a = json_decode((string) DB::table('lines')->where('code', '100000')->value('advocacy'), true);
        $this->assertSame('Sauvons la ligne', $a['title']);
        $this->assertSame(['en' => ['title' => 'Save the line'], 'de' => ['message' => 'Schreiben Sie dem Präfekten.']], $a['translations']);

        $en = Advocacy::localized($a, 'en');
        $this->assertSame('Save the line', $en['title']);
        // Message propre non traduit en anglais : français, signalé comme tel.
        $this->assertSame('Écrivez au préfet.', $en['message']);
        $this->assertSame('fr', $en['lang']['message']);
        // Bouton par défaut : traduit (lang/*/home.php).
        $this->assertSame('Take action for the line', $en['cta_label']);
        $this->assertSame('Für die Strecke aktiv werden', Advocacy::localized($a, 'de')['cta_label']);
        $this->assertSame('Agir pour la ligne', Advocacy::localized($a, 'fr')['cta_label']);

        // Textes par défaut : identiques à ceux de la commande, traduits partout.
        $this->artisan('vff:flag-threatened', ['code' => '100000', '--kind' => 'reopening'])->assertSuccessful();
        $b = json_decode((string) DB::table('lines')->where('code', '100000')->value('advocacy'), true);
        $this->assertSame('Pour la réouverture de la ligne', $b['title']);
        $this->assertSame('We support the reopening of this line.', Advocacy::localized($b, 'en')['message']);
        $this->assertSame('de', Advocacy::localized($b, 'de')['lang']['title']);
        $this->assertNull(Advocacy::localized(null, 'en'));
    }

    public function test_home_page_shows_the_advocacy_in_the_page_language(): void
    {
        Line::factory()->create(['code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême']);
        $this->seed(AdvocacySeeder::class);

        foreach (['fr' => 'Rouvrons la ligne Angoulême - Limoges', 'en' => 'Let\'s reopen the Angoulême - Limoges line',
            'de' => 'Öffnen wir die Strecke Angoulême - Limoges wieder'] as $locale => $title) {
            $this->withHeader('Accept-Language', $locale)->get('/')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('threatened.0.advocacy.title', $title)
                    ->where('threatened.0.advocacy.lang.title', $locale));
        }
    }

    // ─── Notes des fiches gare ─────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function storedStationNotes(): array
    {
        $notes = [
            StationLineCoverage::NOTE_UNMODELED, StationLineCoverage::NOTE_UNDATED, StationLineCoverage::NOTE_NO_TRACK,
            StationLineCoverage::NOTE_TRACK_GONE, StationLineCoverage::NOTE_UNMODELED_TOURIST,
            StationTimeline::NOTE_NOT_SERVED, StationTimeline::NOTE_CLOSED_TODAY, StationTimeline::NOTE_UNDATED,
            StationTimeline::NOTE_UNDATED_PASSENGER, StationTimeline::NOTE_UNDATED_FREIGHT,
            // Littéraux de BuildStationEras.
            'Dates déduites des lignes', 'Plus aucune ligne en service à cette époque', 'Lignes ouvertes au seul fret à cette époque',
            'Ouverture du tracé desservant la gare', 'Fermeture du tracé desservant la gare', 'Fermeture aux voyageurs des lignes desservant la gare',
            // Nom de ligne : forme actuelle, ancienne forme (doublon « la ligne Ligne de »), autre nom.
            'Ouverture de la ligne de Briouze à La Ferté-Macé', 'Ouverture de la ligne Ligne de Briouze à La Ferté-Macé',
            'Fin de desserte de la ligne « Tramway de Pau »', 'Fermeture aux voyageurs de la ligne d\'Argentan à Granville',
            'Réouverture aux voyageurs de la ligne de Nice à Breil-sur-Roya',
        ];
        // Notes datées vagues (StationTimeline::undatedClosureNote).
        foreach ([['decade', 1980], ['before', 1984], ['after', 1950]] as [$precision, $year]) {
            $notes[] = StationTimeline::undatedClosureNote([['type' => 'closure', 'date' => $year.'-01-01', 'precision' => $precision]]);
        }

        return array_combine($notes, array_map(fn ($n) => [$n], $notes));
    }

    #[DataProvider('storedStationNotes')]
    public function test_every_generated_station_note_is_recognised_and_translated(string $note): void
    {
        $hit = GeneratedText::recognize($note, GeneratedText::stationKeys());
        $this->assertNotNull($hit, "gabarit inconnu : {$note}");
        foreach (self::LOCALES as $locale) {
            $r = GeneratedText::stationNote($note, $locale);
            $this->assertNull($r['lang'], $note);
            $this->assertNotSame($note, $r['text'], "{$locale} : {$note}");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w\/]):[a-z_]+|—|--/u', (string) $r['text'], 'paramètre non remplacé ou tiret long');
            $this->assertDoesNotMatchRegularExpression('/\b(?:ligne|desservant|datée|aucune)\b/u', (string) $r['text'], "français restant ({$locale})");
        }
    }

    public function test_station_closure_parser_wikidata_notes_are_all_translated(): void
    {
        // Toute note « Wikidata : … » écrite par StationClosureParser (lue dans le source), entourée de « » par ImportStationClosures.
        $source = (string) file_get_contents(app_path('Services/StationClosureParser.php'));
        // Chaînes entre apostrophes (échappements \\' compris), sans interpolation.
        preg_match_all('/\'(Wikidata : (?:[^\'\\\\]|\\\\.)*)\'/u', $source, $m);
        preg_match('/\[(\'P3999\' => \'[^\]]+)\]/u', $source, $props);
        $notes = array_map(fn ($s) => stripslashes($s), $m[1]);
        preg_match_all('/\'(P\d+)\' => \'([^\']+)\'/u', $props[1] ?? '', $pl, PREG_SET_ORDER);
        foreach ($pl as [, $p, $label]) {
            $notes[] = "Wikidata : {$label} ({$p})";
        }
        $notes[] = 'Wikidata : événement clé « fermeture » (P793, qualificatif P585)';
        $notes[] = 'Wikidata : événement clé « démolition » (P793, qualificatif P585)';
        $this->assertGreaterThanOrEqual(8, count(array_unique($notes)));
        foreach (array_unique($notes) as $note) {
            if (str_ends_with(trim($note), '«') || str_contains($note, '{')) {
                continue; // gabarit à concaténation ou interpolé (événement clé, libellés P3999 / P576) : couvert ci-dessus
            }
            $quoted = '« '.$note.' »';
            $this->assertNotNull(GeneratedText::recognize($quoted, GeneratedText::stationKeys()), $quoted);
            $this->assertStringStartsWith('“Wikidata: ', GeneratedText::stationNote($quoted, 'en')['text']);
            $this->assertStringStartsWith('„Wikidata: ', GeneratedText::stationNote($quoted, 'de')['text']);
        }
    }

    public function test_line_names_keep_their_proper_form_and_the_french_duplicate_is_fixed(): void
    {
        $old = 'Ouverture de la ligne Ligne de Briouze à La Ferté-Macé';
        $this->assertSame('Ouverture de la ligne de Briouze à La Ferté-Macé', GeneratedText::stationNote($old, 'fr')['text']);
        $this->assertSame('Opening of the line Ligne de Briouze à La Ferté-Macé', GeneratedText::stationNote($old, 'en')['text']);
        $this->assertSame('Eröffnung der Strecke Ligne de Briouze à La Ferté-Macé', GeneratedText::stationNote($old, 'de')['text']);
        $this->assertSame('Fin de desserte de la ligne « Tramway de Pau »', GeneratedText::stationNote('Fin de desserte de la ligne Tramway de Pau', 'fr')['text']);

        // La même règle en SQL (génération en base par BuildStationEras).
        foreach (['Ligne de Briouze à La Ferté-Macé', 'Ligne 4', 'Tramway de Pau', 'Chemin de fer du Blanc-Argent'] as $name) {
            $sql = GeneratedText::lineInSentenceSql('n.name');
            $this->assertSame(GeneratedText::lineInSentence($name), DB::selectOne("SELECT {$sql} AS s FROM (SELECT ?::text AS name) n", [$name])->s);
        }
    }

    public function test_composite_notes_translate_each_part_and_flag_citations(): void
    {
        $note = StationLineCoverage::NOTE_NO_TRACK.' · Dates déduites des lignes · Fermeture dans les années 1990';
        $this->assertSame('Uncertain status: no reconstructed track within 300 m at that time · Dates inferred from the lines · Closure in the 1990s',
            GeneratedText::stationNote($note, 'en')['text']);
        $this->assertStringEndsWith('Schließung in den 1990er-Jahren', (string) GeneratedText::stationNote($note, 'de')['text']);
        $this->assertSame($note, GeneratedText::stationNote($note, 'fr')['text']);

        $quote = '« Elle est fermée au trafic voyageurs en 1993. »';
        $this->assertSame(['text' => $quote, 'lang' => 'fr'], GeneratedText::stationNote($quote, 'en'));
        $this->assertSame(['text' => $quote, 'lang' => null], GeneratedText::stationNote($quote, 'fr'));
        $this->assertSame(['text' => null, 'lang' => null], GeneratedText::stationNote(null, 'de'));
    }

    public function test_station_page_shows_notes_in_the_page_language(): void
    {
        $this->buildRailNetwork();
        DB::table('station_eras')->insert(['station_id' => $this->origin->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'uncertain' => true,
            'note' => 'Dates déduites des lignes · Fermeture non datée', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_events')->insert(['station_id' => $this->origin->id, 'type' => 'opening', 'event_date' => '1869-12-06', 'date_precision' => 'day',
            'source' => 'inferred:line_event', 'is_inferred' => true, 'note' => 'Ouverture de la ligne Ligne de Briouze à La Ferté-Macé',
            'created_at' => now(), 'updated_at' => now()]);

        $this->withHeader('Accept-Language', 'de')->get('/stations/'.$this->origin->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eras.0.note', 'Daten aus den Strecken abgeleitet · Undatierte Schließung')
                // Détection « non datée » faite sur le texte stocké, indépendante de la langue.
                ->where('eras.0.undated', true)
                ->where('events.0.note', 'Eröffnung der Strecke Ligne de Briouze à La Ferté-Macé')
                ->where('events.0.note_lang', null));
    }

    // ─── Chronique des lignes ──────────────────────────────────────────

    public function test_every_importer_label_has_a_translation(): void
    {
        $known = EventText::labels();
        $ohm = (new \ReflectionClass(OhmEvents::class))->getConstant('LABELS');
        foreach ([WikipediaLineParser::TYPE_LABELS, SecondaryNetworkParser::TYPE_LABELS, $ohm, [WikipediaLineParser::SERVICE_REDUCTION_LABEL]] as $labels) {
            foreach ($labels as $fr) {
                $this->assertArrayHasKey($fr, $known, "libellé sans traduction : {$fr}");
            }
        }
    }

    /** @return array<string, array{0: ?string, 1: string, 2: ?string, 3: ?string, 4: string, 5: string}> */
    public static function eventDescriptions(): array
    {
        return [
            'wikipedia texte' => ['wikipedia', 'Fermeture aux voyageurs de Saillat - Chassenon à Angoulême (Wikipédia, texte, « Histoire > Déclin »)', 'Saillat - Chassenon', 'Angoulême',
                'Closure to passengers from Saillat - Chassenon to Angoulême (Wikipedia, text, section “Histoire > Déclin”)',
                'Einstellung des Personenverkehrs von Saillat - Chassenon nach Angoulême (Wikipedia, Text, Abschnitt „Histoire > Déclin“)'],
            'wikipedia PK + voyelle' => ['wikipedia', 'Déclassement d\'Arlanc à Sembadel (PK 467,580 à 493,960) (Wikipédia, liste, « Chronologie »)', 'Arlanc', 'Sembadel',
                'Decommissioning from Arlanc to Sembadel (PK 467.580 to 493.960) (Wikipedia, list, section “Chronologie”)',
                'Entwidmung von Arlanc nach Sembadel (PK 467,580 bis 493,960) (Wikipedia, Liste, Abschnitt „Chronologie“)'],
            'wikipedia toute la ligne' => ['wikipedia', 'Ouverture de toute la ligne (Wikipédia, texte, introduction)', null, null,
                'Opening of the whole line (Wikipedia, text, introduction)', 'Eröffnung der gesamten Strecke (Wikipedia, Text, Einleitung)'],
            'infobox concession' => ['wikipedia', 'Concession à SNCF Réseau (infobox Wikipédia)', null, null,
                'Concession granted to SNCF Réseau (Wikipedia infobox)', 'Konzession an SNCF Réseau (Wikipedia-Infobox)'],
            'wikidata section' => ['wikidata', 'Électrification de la section Firminy - Saint-Étienne-Châteaucreux en 1500 V continu', null, null,
                'Electrification of the section Firminy - Saint-Étienne-Châteaucreux with 1500 V DC', 'Elektrifizierung des Abschnitts Firminy - Saint-Étienne-Châteaucreux mit 1500 V Gleichstrom'],
            'wikidata ligne' => ['wikidata', 'Fermeture de la ligne (trafic non précisé par Wikidata)', null, null,
                'Closure of the line (traffic not specified by Wikidata)', 'Schließung der Strecke (Verkehr von Wikidata nicht angegeben)'],
            'ohm portion' => ['ohm', 'Ouverture de la portion Montbrison - Andrézieux (19,0 km), d\'après OpenHistoricalMap.', null, null,
                'Opening of the section Montbrison - Andrézieux (19.0 km), according to OpenHistoricalMap.', 'Eröffnung des Abschnitts Montbrison - Andrézieux (19,0 km), laut OpenHistoricalMap.'],
            'ohm près de' => ['ohm', 'Fermeture (tout trafic) d\'une portion de 16,2 km près de Bliesbrück, d\'après OpenHistoricalMap.', null, null,
                'Closure (all traffic) of a 16.2 km section near Bliesbrück, according to OpenHistoricalMap.', 'Schließung (gesamter Verkehr) eines 16,2 km langen Abschnitts bei Bliesbrück, laut OpenHistoricalMap.'],
            'archeoferroviaire' => ['archeoferroviaire', 'Fermeture (1940) d\'après le nom du tracé archeoferroviaire', null, null,
                'Closure (1940) according to the name of the archeoferroviaire route', 'Schließung (1940) laut dem Namen des archeoferroviaire-Streckenverlaufs'],
            'réseau secondaire' => ['wikipedia-reseaux', 'Fermeture (tout trafic) au plus tard à la fin de l\'exploitation du réseau, sur toute la ligne (article Wikipédia « Chemins de fer départementaux de la Somme »)', null, null,
                'Closure (all traffic) at the latest when the network ceased operating, on the whole line (Wikipedia article “Chemins de fer départementaux de la Somme”)',
                'Schließung (gesamter Verkehr) spätestens mit dem Betriebsende des Netzes, auf der gesamten Strecke (Wikipedia-Artikel „Chemins de fer départementaux de la Somme“)'],
            'voie verte' => ['osm', 'Reconversion en voie verte (Trans\'Oise), ouverte en 2020 d\'après OpenStreetMap', null, null,
                'Conversion into a greenway (Trans\'Oise), opened in 2020 according to OpenStreetMap', 'Umbau zum Radweg (Trans\'Oise), eröffnet 2020 laut OpenStreetMap'],
            'patrimoine' => ['heritage', 'Exploitation associative : Chemin de fer du Haut Forez, train touristique entre Sembadel et Chaise-Dieu, depuis 1986 (d\'après article Wikipédia de l\'exploitation) ; aussi nommé « Chemin de Fer du Haut Forez (CFHF) ». Sources : Wikipédia (liste), IGN BD TOPO.', null, null,
                'Run by an association: Chemin de fer du Haut Forez, tourist train between Sembadel and Chaise-Dieu, since 1986 (according to the Wikipedia article on the operator); also known as « Chemin de Fer du Haut Forez (CFHF) ». Sources: Wikipedia (list), IGN BD TOPO.',
                'Betrieb durch einen Verein: Chemin de fer du Haut Forez, Museumsbahn zwischen Sembadel und Chaise-Dieu, seit 1986 (laut dem Wikipedia-Artikel über den Betreiber); auch genannt « Chemin de Fer du Haut Forez (CFHF) ». Quellen: Wikipedia (Liste), IGN BD TOPO.'],
        ];
    }

    #[DataProvider('eventDescriptions')]
    public function test_generated_event_descriptions_are_translated(?string $source, string $fr, ?string $from, ?string $to, string $en, string $de): void
    {
        $this->assertSame(['text' => $fr, 'lang' => null], EventText::describe($source, $fr, 'fr', $from, $to));
        $this->assertSame(['text' => $en, 'lang' => null], EventText::describe($source, $fr, 'en', $from, $to));
        $this->assertSame(['text' => $de, 'lang' => null], EventText::describe($source, $fr, 'de', $from, $to));
    }

    public function test_citations_and_hand_written_reasons_stay_french_and_are_flagged(): void
    {
        $lo = 'Déclassement (Totalité de la ligne) : 10/11/1989 - PK déduits de la liste des gares de la fiche';
        $this->assertSame(['text' => 'lignes-oubliees record: “Déclassement (Totalité de la ligne) : 10/11/1989” - kilometre points derived from the record\'s list of stations', 'lang' => 'fr'],
            EventText::describe('lignes-oubliees', $lo, 'en'));
        $manual = 'Ouverture de Souillac à Cazoulès (correction sourcée : liste « Mises en service » de l\'article)';
        $this->assertSame(['text' => 'Opening from Souillac to Cazoulès (sourced correction: liste « Mises en service » de l\'article)', 'lang' => 'fr'],
            EventText::describe('manual', $manual, 'en', 'Souillac', 'Cazoulès'));
        $free = 'Texte libre sans gabarit';
        $this->assertSame(['text' => $free, 'lang' => 'fr'], EventText::describe('manual', $free, 'de'));

        // Citations générées : traduites ; vraie citation : française, signalée ; nom de tracé : nom propre.
        $this->assertSame('Infobox: concessionnaire5 = RFF; dateconc5 = 1997', EventText::quote('wikipedia', 'Infobox : concessionnaire5 = RFF ; dateconc5 = 1997', 'en')['text']);
        $wd = EventText::quote('wikidata', 'Wikidata Q3239643 · P1619 (date d\'ouverture officielle) = 1875-00-00 - date retenue 1875-01-01 (précision : année)', 'de');
        $this->assertSame(['text' => 'Wikidata Q3239643 · P1619 (offizielles Eröffnungsdatum) = 1875-00-00 - verwendetes Datum 1875-01-01 (Genauigkeit: Jahr)', 'lang' => null], $wd);
        $this->assertSame(['text' => 'way w42: railway=abandoned (+2 other OHM way(s))', 'lang' => null], EventText::quote('ohm', 'way w42 : railway=abandoned (+2 autre(s) way(s) OHM)', 'en'));
        $this->assertSame('fr', EventText::quote('wikipedia', 'Depuis le 13 mars 2018, la portion est fermée.', 'en')['lang']);
        $this->assertNull(EventText::quote('archeoferroviaire', 'Ligne de Raon-l\'Étape à Raon-sur-Plaine (1914-1918)', 'en')['lang']);
    }

    public function test_french_dates_and_numbers_follow_the_page_language(): void
    {
        $this->assertSame('9 May 1986', EventText::date('9 mai 1986', 'en'));
        $this->assertSame('1. April 1990', EventText::date('1er avril 1990', 'de'));
        $this->assertSame('June 1985', EventText::date('juin 1985', 'en'));
        $this->assertSame('1985', EventText::date('1985', 'de'));
        $this->assertSame('12.3', EventText::num('12,3', 'en'));
        $this->assertSame('1234,5', EventText::num('1 234,5', 'de'));
    }
}
