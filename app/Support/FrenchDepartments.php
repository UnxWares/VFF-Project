<?php

namespace App\Support;

/**
 * Départements français et leur région (Code officiel géographique de l'INSEE, régions depuis le
 * 1er janvier 2016). Sert aux faits « régions / départements traversés » de la fiche ligne, calculés
 * sur le tracé de la ligne entière (commune_contours.departement), pas sur l'élément Wikidata
 * rattaché, qui peut ne décrire qu'une partie de la ligne (LGV Sud-Est : la seule LN1).
 */
final class FrenchDepartments
{
    private const ARA = 'Auvergne-Rhône-Alpes';

    private const BFC = 'Bourgogne-Franche-Comté';

    private const BRE = 'Bretagne';

    private const CVL = 'Centre-Val de Loire';

    private const GES = 'Grand Est';

    private const HDF = 'Hauts-de-France';

    private const IDF = 'Île-de-France';

    private const NOR = 'Normandie';

    private const NAQ = 'Nouvelle-Aquitaine';

    private const OCC = 'Occitanie';

    private const PDL = 'Pays de la Loire';

    private const PAC = 'Provence-Alpes-Côte d\'Azur';

    /** Code → [nom, région]. */
    public const ALL = [
        '01' => ['Ain', self::ARA], '02' => ['Aisne', self::HDF], '03' => ['Allier', self::ARA],
        '04' => ['Alpes-de-Haute-Provence', self::PAC], '05' => ['Hautes-Alpes', self::PAC], '06' => ['Alpes-Maritimes', self::PAC],
        '07' => ['Ardèche', self::ARA], '08' => ['Ardennes', self::GES], '09' => ['Ariège', self::OCC],
        '10' => ['Aube', self::GES], '11' => ['Aude', self::OCC], '12' => ['Aveyron', self::OCC],
        '13' => ['Bouches-du-Rhône', self::PAC], '14' => ['Calvados', self::NOR], '15' => ['Cantal', self::ARA],
        '16' => ['Charente', self::NAQ], '17' => ['Charente-Maritime', self::NAQ], '18' => ['Cher', self::CVL],
        '19' => ['Corrèze', self::NAQ], '2A' => ['Corse-du-Sud', 'Corse'], '2B' => ['Haute-Corse', 'Corse'],
        '21' => ['Côte-d\'Or', self::BFC], '22' => ['Côtes-d\'Armor', self::BRE], '23' => ['Creuse', self::NAQ],
        '24' => ['Dordogne', self::NAQ], '25' => ['Doubs', self::BFC], '26' => ['Drôme', self::ARA],
        '27' => ['Eure', self::NOR], '28' => ['Eure-et-Loir', self::CVL], '29' => ['Finistère', self::BRE],
        '30' => ['Gard', self::OCC], '31' => ['Haute-Garonne', self::OCC], '32' => ['Gers', self::OCC],
        '33' => ['Gironde', self::NAQ], '34' => ['Hérault', self::OCC], '35' => ['Ille-et-Vilaine', self::BRE],
        '36' => ['Indre', self::CVL], '37' => ['Indre-et-Loire', self::CVL], '38' => ['Isère', self::ARA],
        '39' => ['Jura', self::BFC], '40' => ['Landes', self::NAQ], '41' => ['Loir-et-Cher', self::CVL],
        '42' => ['Loire', self::ARA], '43' => ['Haute-Loire', self::ARA], '44' => ['Loire-Atlantique', self::PDL],
        '45' => ['Loiret', self::CVL], '46' => ['Lot', self::OCC], '47' => ['Lot-et-Garonne', self::NAQ],
        '48' => ['Lozère', self::OCC], '49' => ['Maine-et-Loire', self::PDL], '50' => ['Manche', self::NOR],
        '51' => ['Marne', self::GES], '52' => ['Haute-Marne', self::GES], '53' => ['Mayenne', self::PDL],
        '54' => ['Meurthe-et-Moselle', self::GES], '55' => ['Meuse', self::GES], '56' => ['Morbihan', self::BRE],
        '57' => ['Moselle', self::GES], '58' => ['Nièvre', self::BFC], '59' => ['Nord', self::HDF],
        '60' => ['Oise', self::HDF], '61' => ['Orne', self::NOR], '62' => ['Pas-de-Calais', self::HDF],
        '63' => ['Puy-de-Dôme', self::ARA], '64' => ['Pyrénées-Atlantiques', self::NAQ], '65' => ['Hautes-Pyrénées', self::OCC],
        '66' => ['Pyrénées-Orientales', self::OCC], '67' => ['Bas-Rhin', self::GES], '68' => ['Haut-Rhin', self::GES],
        '69' => ['Rhône', self::ARA], '70' => ['Haute-Saône', self::BFC], '71' => ['Saône-et-Loire', self::BFC],
        '72' => ['Sarthe', self::PDL], '73' => ['Savoie', self::ARA], '74' => ['Haute-Savoie', self::ARA],
        '75' => ['Paris', self::IDF], '76' => ['Seine-Maritime', self::NOR], '77' => ['Seine-et-Marne', self::IDF],
        '78' => ['Yvelines', self::IDF], '79' => ['Deux-Sèvres', self::NAQ], '80' => ['Somme', self::HDF],
        '81' => ['Tarn', self::OCC], '82' => ['Tarn-et-Garonne', self::OCC], '83' => ['Var', self::PAC],
        '84' => ['Vaucluse', self::PAC], '85' => ['Vendée', self::PDL], '86' => ['Vienne', self::NAQ],
        '87' => ['Haute-Vienne', self::NAQ], '88' => ['Vosges', self::GES], '89' => ['Yonne', self::BFC],
        '90' => ['Territoire de Belfort', self::BFC], '91' => ['Essonne', self::IDF], '92' => ['Hauts-de-Seine', self::IDF],
        '93' => ['Seine-Saint-Denis', self::IDF], '94' => ['Val-de-Marne', self::IDF], '95' => ['Val-d\'Oise', self::IDF],
        '971' => ['Guadeloupe', 'Guadeloupe'], '972' => ['Martinique', 'Martinique'], '973' => ['Guyane', 'Guyane'],
        '974' => ['La Réunion', 'La Réunion'], '976' => ['Mayotte', 'Mayotte'],
    ];

    /** @return array{0: string, 1: string}|null [nom, région] */
    public static function find(string $code): ?array
    {
        $code = strtoupper(trim($code));

        return self::ALL[$code] ?? self::ALL[str_pad($code, 2, '0', STR_PAD_LEFT)] ?? null;
    }
}
