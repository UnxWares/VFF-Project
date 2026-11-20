<?php

namespace Tests\Unit;

use App\Services\PlaceNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Toponymes et libellés de tracés (PlaceNames) : comparaison de noms entre
 * sources (archeoferroviaire, SNCF, OSM, OHM), analyse des libellés bruts et
 * construction des noms affichés « Ligne de X à Y » (élisions, contractions).
 * Exemples tirés des libellés réels de source_traces.
 */
class PlaceNamesTest extends TestCase
{
    // ─── Comparaison de noms ─────────────────────────────────────────────

    public function test_normalize_folds_case_accents_punctuation_and_saint(): void
    {
        $this->assertSame('saint etienne chateaucreux', PlaceNames::normalize('Saint-Étienne Châteaucreux'));
        $this->assertSame('saint die', PlaceNames::normalize('St-Dié'));
        $this->assertSame('sainte foy l argentiere', PlaceNames::normalize('Ste-Foy l’Argentière'));
        $this->assertSame('oeuilly', PlaceNames::normalize('Œuilly'));
        $this->assertSame('', PlaceNames::normalize(null));
        $this->assertSame('', PlaceNames::normalize(' - '));
        // « st » au milieu d'un mot n'est pas « saint ».
        $this->assertSame('stiring wendel', PlaceNames::normalize('Stiring-Wendel'));
    }

    public function test_normalize_handles_decomposed_unicode_like_composed_text(): void
    {
        $nfd = "Lune\u{0301}ville"; // « é » en NFD (e + accent combinant)

        $this->assertSame(PlaceNames::normalize('Lunéville'), PlaceNames::normalize($nfd));
        $this->assertSame('é', PlaceNames::nfc("e\u{0301}"));
    }

    public function test_sncf_and_vff_spellings_of_a_line_have_the_same_tokens(): void
    {
        $this->assertSame(PlaceNames::tokens('Ligne de Lunéville à St-Dié'), PlaceNames::tokens('ligne de luneville a saint die'));
        $this->assertSame(['ligne', 'luneville', 'saint', 'die'], PlaceNames::tokens('Ligne de Lunéville à St-Dié'));
    }

    public function test_significant_words_drop_kinds_generic_words_and_numbers(): void
    {
        $this->assertSame(['pierre', 'corps'], PlaceNames::significant('Gare de Saint-Pierre-des-Corps 2'));
        $this->assertSame(['carhaix', 'morlaix'], PlaceNames::significant('Ligne de Carhaix à Morlaix'));
        $this->assertSame([], PlaceNames::significant('Raccordement de la gare'));
    }

    // ─── Noms de lignes VFF / SNCF ───────────────────────────────────────

    /** @return array<string, array{0: string, 1: ?array}> */
    public static function lineNameProvider(): array
    {
        return [
            'élision' => ['Ligne d’Amagne-Lucquy à Revigny', ['kind' => 'ligne', 'places' => ['Amagne-Lucquy', 'Revigny']]],
            'via' => ['Ligne de Paris-St-Lazare à Mantes-Station par Conflans-Ste-Honorine',
                ['kind' => 'ligne', 'places' => ['Paris-St-Lazare', 'Mantes-Station', 'Conflans-Ste-Honorine']]],
            'plusieurs via' => ['Ligne de A à B par C, D et E', ['kind' => 'ligne', 'places' => ['A', 'B', 'C', 'D', 'E']]],
            'raccordement à un seul lieu' => ['Raccordement de La Chapelle-St-Denis', ['kind' => 'raccordement', 'places' => ['La Chapelle-St-Denis']]],
            'contraction « du » → Le' => ['Ligne du Puy à Saint-Georges-d’Aurac', ['kind' => 'ligne', 'places' => ['Le Puy', 'Saint-Georges-d’Aurac']]],
            'voie-mère' => ['Voie-mère de Pont-à-Vendin à Violaines', ['kind' => 'voie-mère', 'places' => ['Pont-à-Vendin', 'Violaines']]],
            'parenthèse ignorée' => ['Ligne de Paris à Strasbourg (LGV)', ['kind' => 'ligne', 'places' => ['Paris', 'Strasbourg']]],
            'nom dérivé « près de »' => ['Ligne près de Hautmont', null],
            'pas un nom de ligne' => ['Paris', null],
        ];
    }

    #[DataProvider('lineNameProvider')]
    public function test_parse_line_name(string $name, ?array $expected): void
    {
        $this->assertSame($expected, PlaceNames::parseLineName($name));
    }

    // ─── Libellés bruts de tracés ────────────────────────────────────────

    public function test_archeo_label_with_two_places_is_a_pair(): void
    {
        $p = PlaceNames::parseLabel('Carhaix Morlaix');

        $this->assertSame(['Carhaix', 'Morlaix'], $p['places']);
        $this->assertTrue($p['pair']);
        $this->assertFalse($p['junk']);
        $this->assertFalse($p['generic']);
        $this->assertSame('Ligne de Carhaix à Morlaix', PlaceNames::buildName('principal', $p));
    }

    public function test_article_after_a_slash_stays_with_its_place(): void
    {
        $this->assertSame(['Saint-Malo', 'Cancale/La Houle'], PlaceNames::parseLabel('Saint-Malo Cancale/La Houle')['places']);
        $this->assertSame(['Doyet-La Presle', 'Commentry'], PlaceNames::chunks('Doyet-La Presle Commentry'));
    }

    /** @return array<string, array{0: string, 1: ?int}> */
    public static function gaugeProvider(): array
    {
        return [
            'voie de 60' => ['Soultz Schoenenbourg (voie 0m60)', 600],
            'voie de 70' => ['Cartignac Le Huga Hourtin-Plage La Gracieuse (voie de 0m70)', 700],
            'voie métrique' => ['Tramway de X (voie métrique)', 1000],
            // « à l'origine en voie de 0m70 » : écartement changé ensuite, jamais inventé.
            'écartement initial seulement' => ['Naujac Saint-Isidore (à l’origine en voie de 0m70)', null],
            'sans mention' => ['Carhaix Morlaix', null],
        ];
    }

    #[DataProvider('gaugeProvider')]
    public function test_gauge_is_read_only_when_explicit_and_unambiguous(string $label, ?int $gauge): void
    {
        $this->assertSame($gauge, PlaceNames::parseLabel($label)['gauge']);
    }

    public function test_military_period_and_annotations_are_stripped_from_places(): void
    {
        $p = PlaceNames::parseLabel('Caterpillar-Junction Contalmaison 14-18 ligne anglaise (voie de 0m60)');

        $this->assertSame(['Caterpillar-Junction', 'Contalmaison'], $p['places']);
        $this->assertSame('1914-1918', $p['period']);
        $this->assertSame(600, $p['gauge']);
        $this->assertSame('Ligne militaire de Caterpillar-Junction à Contalmaison (1914-1918)', PlaceNames::buildName('militaire', $p));
        // Hors réseau militaire, la période n'est pas ajoutée au nom.
        $this->assertSame('Ligne de Caterpillar-Junction à Contalmaison', PlaceNames::buildName('principal', $p));
    }

    public function test_descriptions_after_semicolon_and_dates_are_not_place_names(): void
    {
        $p = PlaceNames::parseLabel('Tramway du Cap-Ferret ; ligne à voie de 0m60 ouverte en 1879 et fermée vers 1935 ; à traction hippomobile jusqu’en 1925');

        $this->assertSame('Tramway du Cap-Ferret', $p['clean']);
        $this->assertSame('tramway', $p['kind']);
        $this->assertSame('Tramway du Cap-Ferret', PlaceNames::buildName('secondaire', $p));
        $this->assertSame(['Condé', 'Guignicourt'], PlaceNames::parseLabel('Condé Guignicourt 1905/1914')['places']);
        $this->assertSame(['Champ de tir de Kergroix'], PlaceNames::parseLabel('Champ de tir de Kergroix 6')['places']);
    }

    public function test_status_flags_of_a_trace(): void
    {
        $this->assertSame(['trace_initial'], PlaceNames::parseLabel('Nantes Pornic : tracé initial')['flags']);
        $this->assertSame(['Nantes', 'Pornic'], PlaceNames::parseLabel('Nantes Pornic : tracé initial')['places']);
        $this->assertContains('projet', PlaceNames::parseLabel('Projet de tracé jamais réalisé')['flags']);
        $this->assertContains('liste', PlaceNames::parseLabel('Amiens + Doullens + Albert')['flags']);
    }

    public function test_network_prefixes_and_separators_are_removed(): void
    {
        $this->assertSame(['Peyrehorade', 'Sauveterre'], PlaceNames::parseLabel('CB - Peyrehorade - Sauveterre')['places']);
        // « Buzancy:Bar » = « Buzancy/Bar » (gare desservant deux lieux).
        $p = PlaceNames::parseLabel('Buzancy:Bar');
        $this->assertSame(['Buzancy/Bar'], $p['places']);
        $this->assertSame('Ligne de Buzancy-Bar', PlaceNames::buildName('principal', $p));
    }

    public function test_kind_word_at_head_or_tail_names_a_branch(): void
    {
        $head = PlaceNames::parseLabel('Embranchement Harbouey');
        $tail = PlaceNames::parseLabel('Arras raccordement');
        $colon = PlaceNames::parseLabel('Couvron : embranchement aérodrome militaire');

        $this->assertSame(['Harbouey'], $head['places']);
        $this->assertSame('Embranchement', $head['kind_phrase']);
        $this->assertSame('Embranchement de Harbouey', PlaceNames::buildName('principal', $head));
        $this->assertSame('Embranchement militaire de Harbouey', PlaceNames::buildName('militaire', $head));
        $this->assertSame('Raccordement d\'Arras', PlaceNames::buildName('principal', $tail));
        $this->assertSame('Embranchement aérodrome militaire de Couvron', $colon['clean']);
        $this->assertSame('Port militaire de Brest', PlaceNames::buildName('principal', PlaceNames::parseLabel('Brest - port militaire')));
    }

    public function test_second_station_of_the_same_town(): void
    {
        $this->assertSame(['Morhange-Gare', 'Morhange-Ville'], PlaceNames::parseLabel('Morhange-Gare Ville')['places']);
    }

    public function test_border_is_a_place(): void
    {
        $this->assertSame(['Anor', 'Frontière'], PlaceNames::parseLabel('Anor frontière')['places']);
        $this->assertSame('de la frontière', PlaceNames::de('frontière'));
        $this->assertSame('à la frontière', PlaceNames::a('Frontière'));
    }

    /** @return array<string, array{0: ?string}> */
    public static function junkProvider(): array
    {
        return [
            'vide' => [''],
            'null' => [null],
            'horodatage' => ['2021-03-04T10:22:00Z'],
            'identifiant d\'objet' => ['20210304102200-ab12c'],
            'saisie parasite' => ['cdecde'],
        ];
    }

    #[DataProvider('junkProvider')]
    public function test_technical_labels_are_junk(?string $label): void
    {
        $p = PlaceNames::parseLabel($label);

        $this->assertTrue($p['junk']);
        $this->assertSame([], $p['places']);
    }

    public function test_generic_labels_do_not_name_a_line(): void
    {
        foreach (['raccordement', 'Tracé initial', 'Voie de service', 'Funiculaire'] as $label) {
            $this->assertTrue(PlaceNames::parseLabel($label)['generic'], $label);
        }
        $this->assertFalse(PlaceNames::parseLabel('Carhaix Morlaix')['generic']);
        // « Ligne militaire » seul : l'annotation est retirée, il ne reste rien.
        $this->assertTrue(PlaceNames::parseLabel('Ligne militaire')['junk']);
    }

    public function test_label_already_in_line_name_format(): void
    {
        $p = PlaceNames::parseLabel('Ligne de Paris à Lyon');

        $this->assertSame(['Paris', 'Lyon'], $p['places']);
        $this->assertSame('Ligne de Paris à Lyon', PlaceNames::buildName('principal', $p));
    }

    public function test_long_descriptive_label_is_cut_at_a_word(): void
    {
        $p = PlaceNames::parseLabel(str_repeat('Abcdefgh ', 15));

        $this->assertLessThanOrEqual(90, mb_strlen($p['clean']));
        $this->assertStringEndsNotWith(' ', $p['clean']);
    }

    // ─── Chunks ──────────────────────────────────────────────────────────

    public function test_chunks_keep_descriptive_place_names_together(): void
    {
        $this->assertSame(['Ouvrage du Hackenberg', 'Veckring'], PlaceNames::chunks('Ouvrage du Hackenberg Veckring'));
        $this->assertSame(['Carrière Lamarque', 'Dax'], PlaceNames::chunks('Carrière Lamarque Dax'));
        $this->assertSame(['Le Huga', 'Les Manchets'], PlaceNames::chunks('Le Huga Les Manchets'));
        $this->assertSame(['Dax', 'Mont'], PlaceNames::chunks('Ligne Dax Mont'));
    }

    public function test_lowercase_word_marks_a_description_not_places(): void
    {
        $this->assertSame([], PlaceNames::chunks('vers Dax'));
        $this->assertSame([], PlaceNames::chunks('Dax 1905'));
    }

    // ─── Élisions et contractions ────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string, 2: string}> lieu, « de », « à » */
    public static function prepositionProvider(): array
    {
        return [
            'consonne' => ['Revigny', 'de Revigny', 'à Revigny'],
            'voyelle' => ['Amagne', 'd\'Amagne', 'à Amagne'],
            'Y initial' => ['Yvetot', 'd\'Yvetot', 'à Yvetot'],
            'É initial' => ['Étretat', 'd\'Étretat', 'à Étretat'],
            'Le' => ['Le Mans', 'du Mans', 'au Mans'],
            'Les' => ['Les Arcs', 'des Arcs', 'aux Arcs'],
            'La (pas de contraction)' => ['La Ferté', 'de La Ferté', 'à La Ferté'],
            'type masculin' => ['Dépôt de Reinange', 'du dépôt de Reinange', 'au dépôt de Reinange'],
            'type féminin' => ['Sucrerie de Nangis', 'de la sucrerie de Nangis', 'à la sucrerie de Nangis'],
            'type pluriel' => ['Carrières de Lafarge', 'des carrières de Lafarge', 'aux carrières de Lafarge'],
            'type élidé' => ['Ouvrage du Hackenberg', 'de l\'ouvrage du Hackenberg', 'à l\'ouvrage du Hackenberg'],
            'type élidé (usine)' => ['Usine Solvay', 'de l\'usine Solvay', 'à l\'usine Solvay'],
            'sigle conservé' => ['ZI Nord', 'de la ZI Nord', 'à la ZI Nord'],
        ];
    }

    #[DataProvider('prepositionProvider')]
    public function test_french_prepositions_before_a_place(string $place, string $de, string $a): void
    {
        $this->assertSame($de, PlaceNames::de($place));
        $this->assertSame($a, PlaceNames::a($place));
    }

    public function test_line_name_with_via_places(): void
    {
        $this->assertSame('Ligne d\'Amagne au Mans', PlaceNames::lineName('Ligne', ['Amagne', 'Le Mans']));
        $this->assertSame('Ligne de Paris à Achères par Mantes, Conflans et Poissy',
            PlaceNames::lineName('Ligne', ['Paris', 'Mantes', 'Conflans', 'Poissy', 'Achères']));
        $this->assertSame('Ligne de Cartignac à La Gracieuse par Le Huga et Hourtin-Plage',
            PlaceNames::buildName('principal', PlaceNames::parseLabel('Cartignac Le Huga Hourtin-Plage La Gracieuse (voie de 0m70)')));
        $this->assertSame('Ligne de l\'ouvrage du Hackenberg à Veckring',
            PlaceNames::lineName('Ligne', ['Ouvrage du Hackenberg', 'Veckring']));
    }

    public function test_place_name_official_forms(): void
    {
        $this->assertSame('Bully-Grenay', PlaceNames::placeName('Bully / Grenay'));
        $this->assertSame('Vic-sur-Aisne', PlaceNames::placeName('Vic-sur Aisne'));
        $this->assertSame('Saint-Sulpice Laurière', PlaceNames::placeName('Saint Sulpice Laurière'));
        $this->assertSame('Ligne militaire de Vic-sur-Aisne à Berry-au-Bac',
            PlaceNames::buildName('militaire', PlaceNames::parseLabel('Vic-sur Aisne Berry-au Bac')));
    }

    // ─── Noms dérivés (tracés sans nom) ──────────────────────────────────

    public function test_derived_name_by_network_for_unnamed_traces(): void
    {
        $this->assertSame('Ligne militaire près de Metzervisse', PlaceNames::derivedName('militaire', null, 'Metzervisse'));
        $this->assertSame('Voie industrielle près de Hautmont', PlaceNames::derivedName('particulier', null, 'Hautmont'));
        $this->assertSame('Ligne secondaire près du Mans', PlaceNames::derivedName('secondaire', null, 'Le Mans'));
        // Court tronçon du réseau principal : embranchement.
        $this->assertSame('Embranchement près d\'Amiens', PlaceNames::derivedName('principal', null, 'Amiens', 2000));
        $this->assertSame('Ligne près d\'Amiens', PlaceNames::derivedName('principal', null, 'Amiens', 5000));
        $this->assertSame('Ligne près d\'Amiens', PlaceNames::derivedName('principal', null, 'Amiens'));
    }

    public function test_derived_name_keeps_the_generic_label_and_the_period(): void
    {
        $this->assertSame('Raccordement d\'Arras', PlaceNames::derivedName('principal', PlaceNames::parseLabel('raccordement'), 'Arras'));
        $this->assertSame('Ligne militaire près de Verdun (1914-1918)', PlaceNames::derivedName('militaire', PlaceNames::parseLabel('Ligne militaire 14-18'), 'Verdun'));
        // Libellé descriptif trop long : remplacé par « Ancien tracé ».
        $this->assertSame('Ancien tracé près de Perthes',
            PlaceNames::derivedName('principal', PlaceNames::parseLabel('Modification suite à la destruction du tunnel de Perthes'), 'Perthes'));
    }

    public function test_derived_name_of_a_network_label_names_the_sector(): void
    {
        $this->assertSame('Lignes minières du Bassin minier du Nord (secteur de Lens)',
            PlaceNames::derivedName('principal', PlaceNames::parseLabel('Bassin Minier Nord - Lignes minières'), 'Lens'));
    }

    public function test_display_names_never_contain_long_dashes(): void
    {
        $labels = ['Brest — Quimper', 'Carhaix — Morlaix (voie métrique)', 'Tinson Oulches 14—18 (voie de 0m60)'];
        foreach ($labels as $label) {
            $p = PlaceNames::parseLabel($label);
            foreach (['principal', 'militaire', 'particulier', 'secondaire'] as $network) {
                $this->assertStringNotContainsString('—', PlaceNames::buildName($network, $p), $label);
                $this->assertStringNotContainsString('—', PlaceNames::derivedName($network, $p, 'Brest'), $label);
            }
        }
    }

    public function test_ucfirst_is_multibyte_safe(): void
    {
        $this->assertSame('Étoile', PlaceNames::ucfirst('étoile'));
        $this->assertSame('', PlaceNames::ucfirst(''));
    }
}
