<?php

namespace App\Services\Schematic;

/**
 * Réglages du plan schématique (« vue ferroviaire » de /maps), lus par SchematicLayout et
 * OctilinearRouter. Les longueurs sont en mètres Web Mercator (EPSG:3857, la projection de la carte),
 * les coûts en pas de grille (un pas droit vaut 1, un pas en diagonale √2).
 *
 * Les valeurs par défaut ont été réglées sur les ères 1930, 1970 et 2030 en regardant les plans
 * produits (rapport storage/app/reports/agents/matin/vue-ferroviaire.md) ; les tests construisent de
 * petits graphes avec une grille plus fine.
 */
final readonly class SchematicOptions
{
    public function __construct(
        // Pas de la grille du plan : chaque nœud du plan (jonction, gare de bifurcation, terminus) est
        // posé sur un point de cette grille, et chaque axe la suit à 0, 45 ou 90 degrés.
        public float $cellM = 6000.0,
        // Antenne (axe sans issue) sans gare plus courte que cela : retirée (bout de voie, faisceau).
        public float $spurM = 3000.0,
        // Deux nœuds reliés par un axe plus court que cela sont fondus en un seul, en pas de grille.
        public float $mergeCells = 0.45,
        // Deux nœuds qui tombent sur le même point et sont reliés par un axe de moins de mergeSameCells
        // pas : fondus ; plus long (boucle), le second est déplacé sur un point libre voisin.
        public float $mergeSameCells = 1.6,
        // Distance au plus (en pas) à laquelle un nœud est déplacé pour trouver un point libre.
        public int $placeRadius = 3,
        // Tracé réel simplifié à cette tolérance (en pas) avant de guider le routage.
        public float $guideTolerance = 0.35,
        // Coût d'un changement de direction de 45°, 90° et 135° (demi-tour interdit).
        public float $bend45 = 2.0,
        public float $bend90 = 4.0,
        public float $bend135 = 9.0,
        // Écart au tracé réel : coût par pas au-delà d'une marge, de geoFree pas au moins et de
        // geoFreeRatio fois la distance entre les deux nœuds de l'axe (un long axe peut s'écarter
        // davantage, pour rester droit plutôt que de suivre le tracé en escalier).
        public float $geoWeight = 0.45,
        public float $geoFree = 0.6,
        public float $geoFreeRatio = 0.12,
        // Départ ou arrivée dans une autre direction que le tracé réel : coût par tranche de 45°.
        public float $portWeight = 0.9,
        // Pas déjà pris par un autre axe (axes superposés).
        public float $overlapCost = 30.0,
        // Croisement d'un autre axe (diagonales qui se coupent, passage par un point déjà traversé).
        public float $crossCost = 6.0,
        // Passage par un nœud du plan qui n'est pas une extrémité de l'axe (fausse correspondance).
        public float $placeCost = 80.0,
        // Recherche d'un axe : états explorés au plus avant de se rabattre sur un tracé à un coude.
        public int $maxExpansions = 60000,
        // Gares intermédiaires : part de l'espacement régulier (1) face à la position réelle (0).
        public float $evenSpacing = 0.5,
        // Loupes : grandes agglomérations agrandies, comme sur les plans de réseau, pour que leurs gares
        // et leurs lignes tiennent sur la grille. Chaque loupe : [longitude, latitude, rayon en mètres
        // Web Mercator, grossissement au centre (3 au plus)]. Le grossissement décroît en gaussienne
        // avec la distance (facteur 1 + (g - 1) × exp(-(d / rayon)²)), sans bord ni pli.
        public array $lenses = self::LENSES,
    ) {}

    /** @var list<array{0: float, 1: float, 2: float, 3: float}> Paris, Lyon, Lille, Marseille */
    public const LENSES = [
        [2.347, 48.859, 45000.0, 3.0],
        [4.859, 45.760, 28000.0, 2.2],
        [3.070, 50.637, 28000.0, 2.2],
        [5.380, 43.303, 24000.0, 2.0],
    ];
}
