<?php

namespace App\Services\Accessibility;

/**
 * Jeux SNCF Open Data lus pour l'accessibilité et les services des gares (portail data.sncf.com, licence ODbL vérifiée à chaque
 * téléchargement par SncfOpenDataCache et AccessibilityNetex). Toutes les gares sont désignées par l'identifiant du référentiel
 * « Gares de voyageurs » : les jeux qui ne portent qu'un code UIC ou un trigramme y sont rattachés par ce référentiel.
 */
final class AccessibilityDatasets
{
    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const GARES = 'gares-de-voyageurs';

    public const EQUIPMENTS = 'equipements-accessibilite-en-gare';

    public const ASSISTANCE = 'assistance-psh-pmr';

    public const ELEVATORS = 'etat-fonctionnement-elevatique-gare';

    public const SALES = 'points-vente';

    public const BIKES = 'stationnement-securise-velo-en-gare-au-30-06-24';

    /** Éditeur de chaque jeu (vérifié à chaque téléchargement). */
    public const PUBLISHERS = [
        self::GARES => 'Gares & Connexions',
        self::EQUIPMENTS => 'Gares & Connexions',
        self::ASSISTANCE => 'Gares & Connexions',
        self::ELEVATORS => 'Gares & Connexions',
        self::SALES => 'SNCF Voyageurs',
        self::BIKES => 'Gares & Connexions',
    ];

    /** Titre de chaque jeu sur le portail, tel que SNCF l'écrit. */
    public const TITLES = [
        AccessibilityNetex::DATASET => 'Accessibilité des gares',
        self::GARES => 'Gares de voyageurs',
        self::EQUIPMENTS => 'Equipements d’accessibilité en gare',
        self::ASSISTANCE => 'Données du service d’Assistance Voyageur Handicapé en gare',
        self::ELEVATORS => 'Etat de fonctionnement des équipements d\'élévatique en gare',
        self::SALES => 'Points de vente SNCF',
        self::BIKES => 'Stationnement vélo sécurisé en gare',
    ];

    /** Page du jeu sur le portail. */
    public static function page(string $dataset): string
    {
        return 'https://data.sncf.com/explore/dataset/'.$dataset.'/';
    }
}
