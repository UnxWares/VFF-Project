<?php

namespace Tests\Unit;

use App\Services\PlaceNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PlaceNames : sorties COMPLÈTES de l'analyse des libellés de tracés
 * (archeoferroviaire, OHM…) et des noms construits, en complément de
 * PlaceNamesTest.
 *
 * Couvre les branches propres à certains libellés : réseaux de tramways
 * belges (ELRT, TELB), « Tracé entre X et Y » / « Tracé de X à Y » /
 * « Tracé de l'embranchement de X », « X : raccordement… », listes « A + B »,
 * écartements et périodes, préfixes de réseaux, libellés trop longs, ainsi que
 * les contractions françaises (« du Puy » = Le Puy, « des Arcs » = Les Arcs,
 * « au Mans » = Le Mans) dans les deux sens.
 */
class PlaceNamesBehaviourTest extends TestCase
{
    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function labelProvider(): array
    {
        $pair = fn (array $places, array $more = []) => ['places' => $places, 'pair' => true] + $more;

        return [
            'réseau de tramways ELRT' => ['ELRT 12 Lille Roubaix', $pair(['Lille', 'Roubaix'], ['clean' => 'Lille Roubaix', 'kind' => 'tramway', 'flags' => ['reseau:ELRT 12']])],
            'tracé entre X et Y' => ['Tracé entre Carhaix et Morlaix', $pair(['Carhaix', 'Morlaix'], ['clean' => 'Tracé entre Carhaix et Morlaix'])],
            'tracé de X à Saint Y' => ['Tracé de Carhaix à Saint Pol', $pair(['Carhaix', 'Saint-Pol'], ['clean' => 'Tracé de Carhaix à Saint Pol'])],
            'tracé de l\'embranchement de X' => ['Tracé de l\'embranchement de Harbouey', ['clean' => 'Embranchement de Harbouey', 'places' => ['Harbouey'], 'kind_phrase' => 'Embranchement']],
            'X : raccordement daté' => ['Dechy : raccordement probablement construit en 1916',
                ['clean' => 'Raccordement de Dechy', 'places' => ['Dechy'], 'period' => '1914-1918', 'kind_phrase' => 'Raccordement']],
            'X Y : tracé initial' => ['Nantes Pornic : tracé initial', $pair(['Nantes', 'Pornic'], ['clean' => 'Nantes Pornic', 'flags' => ['trace_initial']])],
            'liste A + B + C' => ['Hesdin + Auxi-le-Château + Frévent', ['clean' => 'Hesdin', 'places' => ['Hesdin'], 'flags' => ['liste']]],
            'téléphérique en tête' => ['Téléphérique Taye Urbès', $pair(['Taye', 'Urbès'], ['clean' => 'Téléphérique Taye Urbès', 'kind' => 'téléphérique', 'kind_phrase' => 'Téléphérique'])],
            'première nature trouvée (mines avant tramway)' => ['Tramway des mines de Ronchamp', ['clean' => 'Tramway des mines de Ronchamp', 'kind' => 'minière']],
            'projet' => ['Projet de ligne Brest Le Conquet', ['clean' => 'Projet de ligne Brest Le Conquet', 'flags' => ['projet']]],
            'seconde gare abrégée' => ['Morhange-Gare Ville', $pair(['Morhange-Gare', 'Morhange-Ville'], ['clean' => 'Morhange-Gare Ville'])],
            'déjà au format Ligne de X à Y' => ['Ligne de Lunéville à St-Dié', $pair(['Lunéville', 'St-Dié'], ['clean' => 'Ligne de Lunéville à St-Dié'])],
            'voie de 60' => ['V60 Bois-le-Prêtre Mamey', $pair(['Bois-le-Prêtre', 'Mamey'], ['clean' => 'Bois-le-Prêtre Mamey', 'gauge' => 600])],
            'ligne militaire 14-18' => ['Ligne militaire 14-18 Thiaucourt Pagny', $pair(['Thiaucourt', 'Pagny'], ['clean' => 'Thiaucourt Pagny', 'period' => '1914-1918'])],
            'voie métrique' => ['Carhaix Morlaix VM', $pair(['Carhaix', 'Morlaix'], ['clean' => 'Carhaix Morlaix', 'gauge' => 1000])],
            'voie normale' => ['VN Pontivy Loudéac', $pair(['Pontivy', 'Loudéac'], ['clean' => 'Pontivy Loudéac', 'gauge' => 1435])],
            'deux écartements : inconnu' => ['Pontivy Loudéac VN puis VM', ['clean' => 'Pontivy Loudéac puis']],
            'ouvrage Maginot 1930-1940' => ['Mil 30-40 Hackenberg', ['clean' => 'Hackenberg', 'places' => ['Hackenberg'], 'period' => '1930-1940']],
            'période 39-45' => ['Ligne 39-45 Saint-Malo Dinard', $pair(['Saint-Malo', 'Dinard'], ['clean' => 'Ligne Saint-Malo Dinard', 'period' => '1939-1945', 'kind_phrase' => 'Ligne'])],
            'deux-points entre deux lieux' => ['Buzancy:Bar', ['clean' => 'Buzancy/Bar', 'places' => ['Buzancy/Bar']]],
            'sigle de réseau en tête' => ['CB - Peyrehorade - Sauveterre', $pair(['Peyrehorade', 'Sauveterre'], ['clean' => 'Peyrehorade Sauveterre'])],
            'préfixe de réseau entre crochets' => ['EC [SJ]-Tracé de Saint-Jean à Lacanau', $pair(['Saint-Jean', 'Lacanau'], ['clean' => 'Tracé de Saint-Jean à Lacanau'])],
            'sigle et deux-points' => ['TVT : Toulon Saint-Raphaël', $pair(['Toulon', 'Saint-Raphaël'], ['clean' => 'Toulon Saint-Raphaël'])],
            'nature en queue' => ['Brest - port militaire', ['clean' => 'Brest port militaire', 'places' => ['Brest'], 'kind_phrase' => 'port militaire']],
            'libellé de réseau' => ['Bassin Minier Nord - Lignes minières', ['clean' => 'Bassin Minier Nord Lignes minières', 'network_label' => true, 'kind' => 'minière']],
            'contraction du → Le' => ['Tramway du Cap-Ferret', ['clean' => 'Tramway du Cap-Ferret', 'places' => ['Le Cap-Ferret'], 'kind' => 'tramway', 'kind_phrase' => 'Tramway']],
            'libellé trop long coupé' => ['Ligne de chemin de fer abandonnée entre deux villages très anciens du département dont personne ne se souvient plus guère',
                ['clean' => 'Ligne de chemin de fer']],
            'générique' => ['Raccordements', ['clean' => 'Raccordements', 'places' => ['Raccordements'], 'generic' => true]],
            'horodatage' => ['2023-04-01T10:00:00Z', ['junk' => true]],
            'identifiant d\'objet' => ['12345678901234-abcde', ['junk' => true]],
            'saisie parasite' => ['cdecde', ['junk' => true]],
            'vide' => ['', ['junk' => true]],
            'blancs' => ['   ', ['raw' => '', 'junk' => true]],
        ];
    }

    /** @param  array<string, mixed>  $expected  clés qui diffèrent des valeurs par défaut */
    #[DataProvider('labelProvider')]
    public function test_parse_label_full_output(string $label, array $expected): void
    {
        $defaults = [
            'raw' => $label, 'clean' => '', 'places' => [], 'pair' => false, 'junk' => false, 'generic' => false,
            'network_label' => false, 'gauge' => null, 'period' => null, 'kind' => null, 'kind_phrase' => null, 'flags' => [],
        ];

        $this->assertSame(array_replace($defaults, $expected), PlaceNames::parseLabel($label));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function buildNameProvider(): array
    {
        return [
            'embranchement militaire' => ['militaire', 'Embranchement Harbouey', 'Embranchement militaire de Harbouey'],
            'raccordements militaires (pluriel)' => ['militaire', 'Raccordements Dechy', 'Raccordements militaires de Dechy'],
            'embranchement civil' => ['principal', 'Embranchement Harbouey', 'Embranchement de Harbouey'],
            'téléphérique' => ['principal', 'Téléphérique Taye Urbès', 'Téléphérique de Taye à Urbès'],
            'tramway' => ['principal', 'Tramway Lille Roubaix', 'Tramway de Lille à Roubaix'],
            'réseau de tramways en suffixe' => ['principal', 'ELRT 12 Lille Roubaix', 'Tramway de Lille à Roubaix (ELRT 12)'],
            'période ajoutée aux lignes militaires' => ['militaire', 'Ligne militaire 14-18 Thiaucourt Pagny', 'Ligne militaire de Thiaucourt à Pagny (1914-1918)'],
            'période civile ignorée' => ['principal', 'Ligne militaire 14-18 Thiaucourt Pagny', 'Ligne de Thiaucourt à Pagny'],
            'un seul lieu militaire' => ['militaire', 'Hackenberg', 'Ligne militaire de Hackenberg'],
            'un seul lieu industriel' => ['particulier', 'Hackenberg', 'Voie industrielle de Hackenberg'],
            'un seul lieu secondaire' => ['secondaire', 'Hackenberg', 'Ligne de Hackenberg'],
            'chemin de fer forestier' => ['principal', 'Waldeisenbahn Abreschviller', 'Chemin de fer forestier d\'Abreschviller'],
            'trois lieux : par' => ['principal', 'Carhaix Morlaix Rostrenen', 'Ligne de Carhaix à Rostrenen par Morlaix'],
            '« Lignes » n\'est pas une nature' => ['principal', 'Lignes Lens Liévin', 'Ligne de Lens à Liévin'],
            'libellé descriptif gardé' => ['principal', 'Projet de ligne Brest Le Conquet', 'Projet de ligne Brest Le Conquet'],
            'ligne minière (catégorie particulier)' => ['particulier', 'Mines de Ronchamp', 'Ligne minière des mines de Ronchamp'],
            'contraction restituée' => ['principal', 'Tramway du Cap-Ferret', 'Tramway du Cap-Ferret'],
            'embranchement de X' => ['militaire', 'Tracé de l\'embranchement de Harbouey', 'Embranchement militaire de Harbouey'],
        ];
    }

    #[DataProvider('buildNameProvider')]
    public function test_build_name(string $network, string $label, string $expected): void
    {
        $this->assertSame($expected, PlaceNames::buildName($network, PlaceNames::parseLabel($label)));
    }

    /** @return array<string, array{0: string, 1: ?string, 2: string, 3: int, 4: string}> */
    public static function derivedNameProvider(): array
    {
        return [
            'militaire sans nom' => ['militaire', null, 'Metzervisse', 0, 'Ligne militaire près de Metzervisse'],
            'industrielle sans nom' => ['particulier', null, 'Hautmont', 0, 'Voie industrielle près de Hautmont'],
            'secondaire sans nom' => ['secondaire', null, 'Carhaix', 0, 'Ligne secondaire près de Carhaix'],
            'court : embranchement' => ['principal', null, 'Le Mans', 2500, 'Embranchement près du Mans'],
            'long : ligne' => ['principal', null, 'Le Mans', 5000, 'Ligne près du Mans'],
            'longueur inconnue' => ['principal', null, 'Le Mans', 0, 'Ligne près du Mans'],
            'bassin minier' => ['militaire', 'Bassin Minier Nord - Lignes minières', 'Lens', 0, 'Lignes minières du Bassin minier du Nord (secteur de Lens)'],
            'front belge' => ['militaire', 'Réseau du front belge', 'Ypres', 0, 'Réseau militaire du front belge (secteur d\'Ypres)'],
            'embranchements militaires datés' => ['militaire', 'Embranchements militaires 14-18', 'Verdun', 0, 'Embranchements militaires de Verdun (1914-1918)'],
            'plan incliné : près de' => ['principal', 'Plan incliné', 'Les Arcs', 0, 'Plan incliné près des Arcs'],
            'raccordements datés' => ['militaire', 'Mil 30-40 Raccordements', 'Hackenberg', 0, 'Raccordements de Hackenberg (1930-1940)'],
            'libellé inutilisable' => ['principal', '2023-04-01T10:00:00Z', 'Arras', 0, 'Ligne près d\'Arras'],
            'nature seule' => ['principal', 'Dépôt', 'Amiens', 0, 'Dépôt d\'Amiens'],
        ];
    }

    #[DataProvider('derivedNameProvider')]
    public function test_derived_name(string $network, ?string $label, string $place, int $lengthM, string $expected): void
    {
        $this->assertSame($expected, PlaceNames::derivedName($network, $label === null ? null : PlaceNames::parseLabel($label), $place, $lengthM));
    }

    public function test_long_descriptive_label_falls_back_to_ancien_trace(): void
    {
        $p = PlaceNames::parseLabel('Modification suite à la destruction du tunnel de Perthes');

        $this->assertSame('Ancien tracé près de Perthes', PlaceNames::derivedName('principal', $p, 'Perthes'));
    }

    /** @return array<string, array{0: string, 1: ?array}> */
    public static function lineNameProvider(): array
    {
        return [
            'contraction des → Les' => ['Ligne des Arcs à Draguignan', ['kind' => 'ligne', 'places' => ['Les Arcs', 'Draguignan']]],
            'arrivée au → Le' => ['Ligne de Caen au Mans', ['kind' => 'ligne', 'places' => ['Caen', 'Le Mans']]],
            'arrivée aux → Les, avec via' => ['Ligne de Paris aux Ifs par Rouen', ['kind' => 'ligne', 'places' => ['Paris', 'Les Ifs', 'Rouen']]],
            'nom composé avec « au »' => ['Ligne de Saint-Germain-au-Mont-d\'Or à Lyon', ['kind' => 'ligne', 'places' => ['Saint-Germain-au-Mont-d\'Or', 'Lyon']]],
            'parenthèse au milieu' => ['Ligne de Nice (Riquier) à Vintimille', ['kind' => 'ligne', 'places' => ['Nice', 'Vintimille']]],
            'un seul lieu' => ['Raccordement d\'Arras', ['kind' => 'raccordement', 'places' => ['Arras']]],
        ];
    }

    #[DataProvider('lineNameProvider')]
    public function test_parse_line_name_restores_articles(string $name, ?array $expected): void
    {
        $this->assertSame($expected, PlaceNames::parseLineName($name));
    }

    public function test_line_name_round_trips_with_contractions(): void
    {
        foreach (['Ligne des Arcs à Draguignan', 'Ligne de Caen au Mans', 'Ligne du Puy à Lyon', 'Ligne d\'Amagne-Lucquy aux Ifs'] as $name) {
            $this->assertSame($name, PlaceNames::lineName('Ligne', PlaceNames::parseLineName($name)['places']), $name);
        }
        $this->assertSame('Ligne de La Ferté à Arras par Bully-Grenay et Eu', PlaceNames::lineName('Ligne', ['La Ferté', 'Bully/Grenay', 'Eu', 'Arras']));
        $this->assertSame('Ligne de Vic-sur-Aisne à Saint-Sulpice', PlaceNames::lineName('Ligne', ['Vic-sur Aisne', 'Saint Sulpice']));
    }

    public function test_line_name_with_a_single_place_or_none(): void
    {
        $this->assertSame('Raccordement de La Chapelle', PlaceNames::lineName('Raccordement', ['La Chapelle']));
        $this->assertSame('Ligne', PlaceNames::lineName('Ligne', []));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function prepositionProvider(): array
    {
        return [
            'frontière seule' => ['Frontière', 'de la frontière', 'à la frontière'],
            'dépôt (le)' => ['Dépôt de Reinange', 'du dépôt de Reinange', 'au dépôt de Reinange'],
            'mines (les)' => ['Mines de Lens', 'des mines de Lens', 'aux mines de Lens'],
            'carrière (la)' => ['Carrière de Moulin', 'de la carrière de Moulin', 'à la carrière de Moulin'],
            'ouvrage (l\')' => ['Ouvrage du Hackenberg', 'de l\'ouvrage du Hackenberg', 'à l\'ouvrage du Hackenberg'],
            'Le' => ['Le Mans', 'du Mans', 'au Mans'],
            'Les' => ['Les Arcs', 'des Arcs', 'aux Arcs'],
            'voyelle' => ['Amiens', 'd\'Amiens', 'à Amiens'],
            'voyelle accentuée' => ['Évreux', 'd\'Évreux', 'à Évreux'],
            'La gardé' => ['La Rochelle', 'de La Rochelle', 'à La Rochelle'],
            'sigle en majuscules gardé' => ['CRB Moulins', 'de CRB Moulins', 'à CRB Moulins'],
            'type en majuscules gardé' => ['USINE Renault', 'de l\'USINE Renault', 'à l\'USINE Renault'],
        ];
    }

    #[DataProvider('prepositionProvider')]
    public function test_prepositions(string $place, string $de, string $a): void
    {
        $this->assertSame($de, PlaceNames::de($place));
        $this->assertSame($a, PlaceNames::a($place));
    }

    public function test_normalize_tokens_and_significant_words(): void
    {
        $this->assertSame('oeuvre d hoedic', PlaceNames::normalize("  Œuvre d'Hœdic  "));
        $this->assertSame('strasse', PlaceNames::normalize('Straße'));
        $this->assertSame('eglise', PlaceNames::normalize("e\u{0301}glise"), 'forme décomposée (NFD)');
        $this->assertSame('saint jean x', PlaceNames::normalize('Saint‘Jean`x'));
        $this->assertSame('', PlaceNames::normalize(null));
        $this->assertSame(['saint', 'pol', 'sainte', 'anne'], PlaceNames::tokens('St-Pol / Ste-Anne'));
        $this->assertSame(['pol', 'anne'], PlaceNames::significant('St-Pol / Ste-Anne'));
        $this->assertSame(['lens', 'a12'], PlaceNames::significant('Gare 12 de Lens A 2 X a12'), 'nombres et lettres seules écartés');
        $this->assertSame(['isle'], PlaceNames::tokens('L’Isle'));
    }
}
