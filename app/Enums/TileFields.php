<?php

namespace App\Enums;

/**
 * Attributs des tracés dans une tuile vectorielle d'ère (`GET /api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt?fields=`).
 *
 * `all` (défaut) garde toutes les propriétés documentées. `map` ne garde que celles que lit la carte du site
 * (couleurs, traits, filtres de la légende, ordre de dessin, fiche et bulle d'un tracé cliqué), n'écrit un
 * booléen que lorsqu'il est vrai et réunit les tracés aux propriétés identiques : la tuile de vue d'ensemble
 * perd 60 % de son poids (mesures dans storage/app/reports/agents/aprem-2111/tuiles-legeres.md).
 */
enum TileFields: string
{
    /** Toutes les propriétés (réponse par défaut, inchangée). */
    case All = 'all';

    /** Seulement les propriétés lues par la carte du site. */
    case Map = 'map';

    /**
     * Propriétés de la couche `vff-lines` avec `fields=map`, dans l'ordre de la tuile.
     * Liste tenue à jour avec ce que lisent `RailMap.svelte` et `lib/railStyle.js` (vérifié par `TileFieldsTest`).
     */
    public const MAP_PROPERTIES = [
        'line_slug', 'line_name', 'line_code', 'is_lgv', 'is_threatened', 'advocacy_kind', 'network', 'status',
        'traffic_type', 'electrified', 'voltage_v', 'electrification_source', 'gauge_class', 'is_heritage',
        'heritage_kind', 'line_max_speed', 'line_electrified', 'line_voltage_v', 'draw_rank', 'covered',
    ];

    /** Suffixe de la clé de cache : vide pour la réponse par défaut, pour garder les entrées déjà calculées. */
    public function cacheSuffix(): string
    {
        return $this === self::All ? '' : ":{$this->value}";
    }
}
