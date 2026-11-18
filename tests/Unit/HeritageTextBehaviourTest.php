<?php

namespace Tests\Unit;

use App\Services\HeritageText as H;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Textes des exploitations associatives (HeritageText) : sorties exactes,
 * en complément de HeritageTextTest et HeritageTextHelpersTest.
 *
 * Non-régression des correctifs :
 *   - « SNCF » concessionnaire n'est pas un concessionnaire associatif (le motif
 *     « cf… » ne vaut qu'en début de mot : CFT, CFTVA…) ;
 *   - passé simple « reprit », « reprirent » : reprise après interruption ;
 *   - présent / passé simple « ouvre », « ouvrit » suivis d'une date : début.
 */
class HeritageTextBehaviourTest extends TestCase
{
    private const NOW = 2026;

    /** @return array<string, array{0: ?string, 1: ?array}> */
    public static function dateProvider(): array
    {
        $d = fn (string $date, string $precision) => ['date' => $date, 'precision' => $precision, 'year' => (int) substr($date, 0, 4)];

        return [
            'jj/mm/aaaa' => ['03/06/1982', $d('1982-06-03', 'day')],
            'jour impossible : mm/aaaa' => ['31/02/1982', $d('1982-02-01', 'month')],
            'mm/aaaa' => ['06/1982', $d('1982-06-01', 'month')],
            'janvier' => ['01/1982', $d('1982-01-01', 'month')],
            'décembre' => ['12/1982', $d('1982-12-01', 'month')],
            'mois 13 : année' => ['13/1982', $d('1982-01-01', 'year')],
            'mois 0 : année' => ['00/1982', $d('1982-01-01', 'year')],
            'mois en lettres' => ['juin 1969', $d('1969-06-01', 'month')],
            'mois accentué en majuscule' => ['Août 2005', $d('2005-08-01', 'month')],
            '1er' => ['1er juillet 2017', $d('2017-07-01', 'day')],
            '1er avec espaces' => ['  1er  août 1999 ', $d('1999-08-01', 'day')],
            'jour en chiffres' => ['3 juin 1982', $d('1982-06-03', 'day')],
            '31 juin : mois' => ['31 juin 1982', $d('1982-06-01', 'month')],
            'modèle date' => ['{{date|3|juin|1982}}', $d('1982-06-03', 'day')],
            'lien wiki' => ['[[1982 dans les chemins de fer|1982]]', $d('1982-01-01', 'year')],
            'année seule' => ['1982', $d('1982-01-01', 'year')],
            'avant 1900 : hors champ' => ['1er mars 1850', null],
            'sans date' => ['aucune date', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('dateProvider')]
    public function test_parse_date(?string $text, ?array $expected): void
    {
        $this->assertSame($expected, H::parseDate($text));
    }

    /** @return array<string, array{0: string, 1: array<string, array<string, mixed>>}> */
    public static function sentenceProvider(): array
    {
        $y = fn (int $year) => ['date' => "{$year}-01-01", 'precision' => 'year', 'year' => $year];

        return [
            'depuis le jj mois aaaa' => ['Le train touristique circule depuis le 3 juin 1982.', ['start' => ['date' => '1982-06-03', 'precision' => 'day', 'year' => 1982]]],
            'entre A et B' => ['Le vélorail a circulé entre 1983 et 2003.', ['start' => $y(1983), 'end' => $y(2003)]],
            'intervalle entre parenthèses' => ['Exploitation (1990-2005) par l’association.', ['start' => $y(1990), 'end' => $y(2005)]],
            'ouvert puis cessé' => ['Ouvert en juin 2018, il a cessé son activité en 2021.',
                ['start' => ['date' => '2018-06-01', 'precision' => 'month', 'year' => 2018], 'end' => $y(2021)]],
            'jusqu’en (apostrophe typographique)' => ['Le vélorail a fonctionné jusqu’en 2008.', ['end' => $y(2008)]],
            'intervalle inversé ignoré' => ['De 2010 à 2005 rien.', []],
            'avant 1950 ignoré' => ['Le train circule depuis 1949.', []],
            'année future ignorée' => ['Le train circule depuis 2040.', []],
            'dernière saison' => ['Dernière saison en 2015 après une première saison en 2001.', ['end' => $y(2015), 'start' => $y(2001)]],
            'en aaaa, un X est créé' => ['En 2009, un vélo-rail est créé sur la ligne.', ['start' => $y(2009)]],
            'fin de l’exploitation' => ['Fin de l’exploitation en 2012.', ['end' => $y(2012)]],
            'premiers trains' => ['Premiers trains en 1975.', ['start' => $y(1975)]],
            'à partir de' => ['À partir de 1990 un train circule.', ['start' => $y(1990)]],
            'le jj mois aaaa … démarre' => ['Le 14 juin 1969 une exploitation touristique démarre.', ['start' => ['date' => '1969-06-14', 'precision' => 'day', 'year' => 1969]]],
            'fermé fin aaaa' => ['Fermé fin 2019 après 30 ans.', ['end' => $y(2019)]],
            'inauguré officiellement' => ['Inauguré officiellement le 2 juillet 1995.', ['start' => ['date' => '1995-07-02', 'precision' => 'day', 'year' => 1995]]],
            'premier début et première fin seulement' => ['Depuis l’été 1993 le train circule, il s’arrête en 2000 puis de 2001 à 2004 encore.', ['start' => $y(1993), 'end' => $y(2000)]],
            'présent « ouvre en »' => ['Le vélorail des Monts ouvre en 1992.', ['start' => $y(1992)]],
            'passé simple « ouvrit en »' => ['Le vélorail ouvrit en 1992.', ['start' => $y(1992)]],
        ];
    }

    /** @param  array<string, array<string, mixed>>  $expected */
    #[DataProvider('sentenceProvider')]
    public function test_dates_in_sentence(string $sentence, array $expected): void
    {
        $this->assertSame($expected, H::datesInSentence($sentence, self::NOW));
    }

    public function test_date_matches_carry_type_and_character_position(): void
    {
        $this->assertSame([
            ['date' => '1983-01-01', 'precision' => 'year', 'year' => 1983, 'type' => 'start', 'pos' => 28],
            ['date' => '2003-01-01', 'precision' => 'year', 'year' => 2003, 'type' => 'end', 'pos' => 36],
        ], H::dateMatches('Le vélorail a circulé entre 1983 et 2003.', self::NOW));

        // Positions en caractères (« ’ » compte pour un), triées.
        $this->assertSame([24, 41], array_column(H::dateMatches('Le train circule depuis 1990 et jusqu’en 2010.', self::NOW), 'pos'));
        $this->assertSame(['start', 'end'], array_column(H::dateMatches('Créé en 1980, interrompu en 1990.', self::NOW), 'type'));
    }

    public function test_interruptions_with_restart(): void
    {
        $this->assertSame([[
            'end' => ['date' => '2008-01-01', 'precision' => 'year', 'year' => 2008],
            'restart' => ['date' => '2013-07-02', 'precision' => 'day', 'year' => 2013],
            'quote' => 'Après avoir été interrompues à l\'été 2008, les circulations ont repris le 2 juillet 2013',
        ]], H::interruptionsInText('Après avoir été interrompues à l’été 2008, les circulations ont repris le 2 juillet 2013. Autre phrase.', self::NOW));

        $two = H::interruptionsInText('Les circulations furent interrompues en 1990 et reprirent le 3 mai 1996. Le vélorail fut arrêté en 2001, puis redémarré en 2003.', self::NOW);
        $this->assertSame([[1990, 1996], [2001, 2003]], array_map(fn ($i) => [$i['end']['year'], $i['restart']['year']], $two));
        $this->assertSame('1996-05-03', $two[0]['restart']['date']);
        $this->assertSame(1996, H::interruptionsInText('Le train fut suspendu en 1990 et reprit en 1996.', self::NOW)[0]['restart']['year']);
    }

    public function test_interruptions_must_be_ordered_and_within_bounds(): void
    {
        $this->assertSame([], H::interruptionsInText('Interrompu en 2010, il a repris en 2005.', self::NOW));
        $this->assertSame([], H::interruptionsInText('Suspendu en 2019 puis relancé en 2030.', self::NOW), 'reprise future');
        $this->assertSame([], H::interruptionsInText('Le train fut suspendu en 1940 et reprit en 1946.', self::NOW), 'avant 1950');
    }

    public function test_sentences(): void
    {
        $this->assertSame(['Le train roule', 'Il s\'arrête!', 'Puce une', 'Puce deux', 'Nouveau paragraphe?', 'Oui. ab', '1990 fut'],
            H::sentences("== Histoire ==\nLe train roule. Il s'arrête!\n* Puce une\n# Puce deux\n\nNouveau paragraphe? Oui. ab. 1990 fut."));
        $this->assertSame(['Courte', 'Une phrase assez longue', '12 trains', '«Citation» suit'],
            H::sentences('A. B. Courte. Une phrase assez longue. 12 trains. «Citation» suit.'));
    }

    public function test_dates_in_text_keep_the_earliest_start_and_the_latest_end(): void
    {
        $text = 'La ligne est fermée en 1950. Le Train de la Vallée circule depuis 1985 sur la section. Le vélorail des Monts '
            .'ouvre en 1992. La voie verte est créée en 2001. Le Train de la Vallée a cessé en 2015. Projet relancé en 2020 pour le Train de la Vallée.';

        $this->assertSame([
            'start' => ['date' => '1985-01-01', 'precision' => 'year', 'year' => 1985],
            'quote_start' => 'Le Train de la Vallée circule depuis 1985 sur la section',
            'end' => ['date' => '2015-01-01', 'precision' => 'year', 'year' => 2015],
            'quote_end' => 'Le Train de la Vallée a cessé en 2015',
        ], H::datesInText($text, ['Train de la Vallée'], self::NOW));
        $this->assertSame(1992, H::datesInText($text, ['vélorail des Monts'], self::NOW)['start']['year']);
        $this->assertSame([], H::datesInText($text, ['inexistant'], self::NOW));

        $earliest = H::datesInText('Le Train de la Vallée circule depuis 1990. Le Train de la Vallée fut créé en 1985.', ['Train de la Vallée'], self::NOW);
        $this->assertSame(['start' => ['date' => '1985-01-01', 'precision' => 'year', 'year' => 1985], 'quote_start' => 'Le Train de la Vallée fut créé en 1985'], $earliest);

        // Fin antérieure au début : fin écartée.
        $this->assertSame(['start', 'quote_start'], array_keys(H::datesInText(
            'Le Train de la Vallée a cessé en 1980. Le Train de la Vallée est ouvert en 1990.', ['Train de la Vallée'], self::NOW)));
    }

    public function test_dates_in_text_skip_line_history_and_far_dates(): void
    {
        // Début précédé de « fermeture » / « dépose » : histoire de la ligne ; la fin, elle, compte.
        $this->assertSame(['end' => ['date' => '2010-01-01', 'precision' => 'year', 'year' => 2010],
            'quote_end' => 'Le train touristique, après la fermeture de 1960 et la dépose, ouvert en 1975, a cessé en 2010'],
            H::datesInText('Le train touristique, après la fermeture de 1960 et la dépose, ouvert en 1975, a cessé en 2010.', ['train touristique'], self::NOW));
        // Sujet étranger (voie verte) avant la date.
        $this->assertSame([], H::datesInText('La voie verte longe le train touristique ouvert en 1995.', ['train touristique'], self::NOW));
        // Date à plus de 120 caractères de la mention : ignorée, sauf fenêtre élargie.
        $far = 'Le vélorail '.str_repeat('roule ', 25).'depuis 1990.';
        $this->assertSame([], H::datesInText($far, ['vélorail'], self::NOW));
        $this->assertSame(1990, H::datesInText($far, ['vélorail'], self::NOW, 200)['start']['year']);
        // Mots-clés vides ignorés, plusieurs mentions possibles.
        $this->assertSame(1985, H::datesInText('Le Train de la Vallée circule depuis 1985.', ['  ', 'Train de la Vallée', 'Vallée'], self::NOW)['start']['year']);
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: ?string}> */
    public static function kindProvider(): array
    {
        return [
            'train et vélorail' => ['Train Touristique, Vélo-rail', ['tourist_train', 'velorail'], 'tourist_train'],
            'vélorail entre parenthèses' => ['inexploitée (vélorail)', ['velorail'], 'velorail'],
            'musée roulant' => ['Musée des tramways à vapeur', ['tourist_train', 'museum'], 'tourist_train'],
            'cyclo-draisine' => ['Cyclo-draisine', ['velorail'], 'velorail'],
            'rail bikes' => ['Rail bikes', ['velorail'], 'velorail'],
            'ligne touristique' => ['Ligne touristique', ['tourist_train'], 'tourist_train'],
            'voie verte' => ['Voie verte', [], null],
            'autorail' => ['Autorail Picasso', ['tourist_train'], 'tourist_train'],
            'sigle CFT…' => ['CFTVA', ['tourist_train'], 'tourist_train'],
            'écomusée' => ['Écomusée', ['museum'], 'museum'],
        ];
    }

    /** @param  list<string>  $kinds */
    #[DataProvider('kindProvider')]
    public function test_kinds_and_main_kind(string $text, array $kinds, ?string $main): void
    {
        $this->assertSame($kinds, H::kindsOf($text));
        $this->assertSame($main, H::mainKind($kinds));
    }

    public function test_name_tokens_and_similarity(): void
    {
        $this->assertSame(['cevenne'], H::nameTokens('Train à vapeur des Cévennes'));
        $this->assertSame(1.0, H::nameSimilarity('Train à vapeur des Cévennes', 'Chemin de fer des Cévennes'));
        $this->assertSame(1.0, H::nameSimilarity('Vélorails du Larzac', 'Vélorail du Larzac'));
        $this->assertSame(0.5, H::nameSimilarity('Livradois-Forez', 'Haut-Forez'));
        $this->assertSame(1.0, H::nameSimilarity('Les Trains de la Mûre', 'Petit train de La Mure'));
        $this->assertSame([], H::nameTokens('CFT 2000'), 'sigle générique et nombre');
        $this->assertSame(0.0, H::nameSimilarity('', 'x'));
        $this->assertSame(['ab'], H::nameTokens('ab a'), 'mots d\'une lettre écartés');
        $this->assertSame(['gorge', 'doux'], H::nameTokens('Gorges du Doux, gorges du Doux'), 'sans doublon');
    }

    public function test_norm_and_fold(): void
    {
        $this->assertSame('l etang oeuf aaou c y', H::norm('  L’Étang-Œuf ÂÄÖÛ ç ÿ '));
        $this->assertSame('a l ete ca ', H::fold('À l’Été ça!'));
        $this->assertSame(' and ', H::fold('Ñandú'), 'même longueur que l\'original');
    }

    /** @return array<string, array{0: array<string, string>, 1: array<string, mixed>}> */
    public static function infoboxProvider(): array
    {
        return [
            'dates et exploitant' => [
                ['date création' => '3 juin 1982', 'date fin' => '2010', 'exploitant' => '[[Association X]]'],
                ['start' => ['date' => '1982-06-03', 'precision' => 'day', 'year' => 1982], 'end' => ['date' => '2010-01-01', 'precision' => 'year', 'year' => 2010], 'operator' => '[[Association X]]'],
            ],
            'dates hors bornes, association « oui »' => [['Date de création' => '1949', 'Date fin' => '2050', 'association' => 'oui'], []],
            'concessionnaire associatif' => [
                ['concessionnaireact' => 'Association du train', 'dateconcact' => '1er mai 1995'],
                ['start' => ['date' => '1995-05-01', 'precision' => 'day', 'year' => 1995], 'operator' => 'Association du train'],
            ],
            'concessionnaire SNCF : pas un début associatif' => [['concessionnaireact' => 'SNCF', 'dateconcact' => '1er mai 1995'], ['operator' => 'SNCF']],
            'concessionnaire CFT…' => [['concessionnaireact' => 'CFTVA', 'dateconcact' => '1995'], ['start' => ['date' => '1995-01-01', 'precision' => 'year', 'year' => 1995], 'operator' => 'CFTVA']],
            'exploitant dans le nom, fin antérieure écartée' => [
                ['nom' => 'Train à Vapeur des Cévennes (Sàrl CITEV)', 'ouverture' => 'juin 1982', 'fin' => '1980'],
                ['start' => ['date' => '1982-06-01', 'precision' => 'month', 'year' => 1982], 'operator' => 'Sàrl CITEV'],
            ],
            'exploitant trop long' => [['gestionnaire' => str_repeat('x', 170)], []],
            'exploitant nettoyé' => [['exploitant' => '  <b>Amicale  du rail</b> '], ['operator' => 'Amicale du rail']],
        ];
    }

    /**
     * @param  array<string, string>  $infobox
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('infoboxProvider')]
    public function test_infobox_facts(array $infobox, array $expected): void
    {
        $this->assertSame($expected, H::infoboxFacts($infobox, self::NOW));
    }

    public function test_wiki_cells(): void
    {
        $this->assertSame([['Gare de Lugarde', 'Lugarde'], ['Riom-ès-Montagnes', 'Riom-ès-Montagnes'], ['Page', 'Ancre']],
            H::wikiLinks('[[ Gare de Lugarde | Lugarde ]] et [[Riom-ès-Montagnes]] [[Page#Section|Ancre]]'));
        $this->assertSame('Gras et italique suite fin 12 km lien B',
            H::plain("'''Gras''' et ''italique''<br />suite<br>fin {{nobr|12|km}} {{refnec}} [https://x.org lien] [https://y.org] <ref>note</ref><ref name=\"a\"/> <!-- c --> [[A|B]]  "));
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function sectionProvider(): array
    {
        $r = fn (?string $from, ?string $to, ?int $pkFrom = null, ?int $pkTo = null) => ['from' => $from, 'to' => $to, 'pk_from' => $pkFrom, 'pk_to' => $pkTo];

        return [
            'de la gare de X à Y.' => ['de la gare de Lugarde à Riom-ès-Montagnes.', $r('gare de Lugarde', 'Riom-ès-Montagnes')],
            'PK entre parenthèses ignorés' => ['d\'Anduze - Saint-Jean-du-Gard (697,710 - 711,357)', $r('Anduze', 'Saint-Jean-du-Gard')],
            'points kilométriques' => ['km 527,5 - km 540', $r(null, null, 527500, 540000)],
            'PK à PK' => ['PK 12 à PK 20,1x', $r(null, null, 12000, 20100)],
            'un seul lieu' => ['Seule', $r(null, null)],
            'vide' => ['', $r(null, null)],
            'lien protégé' => ['[[Gare de Lugarde - Marchastel]] - Riom', $r('Gare de Lugarde - Marchastel', 'Riom')],
        ];
    }

    /** @param  array<string, mixed>  $expected */
    #[DataProvider('sectionProvider')]
    public function test_parse_section(string $cell, array $expected): void
    {
        $this->assertSame($expected, H::parseSection($cell));
    }
}
