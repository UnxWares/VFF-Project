<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Retire les clés étrangères du graphe (dérivé, reconstruit ère par ère) vers
 * line_segments et lines.
 *
 * Avec elles, chaque tronçon supprimé par le moteur décennal (ou ligne
 * fusionnée par le dédoublonnage) mettait à jour ou supprimait des lignes du
 * graphe dans la même transaction, et chaque reconstruction du graphe posait
 * des verrous sur des milliers de tronçons. Le graphe garde les identifiants
 * à titre de référence ; il est simplement reconstruit après le moteur.
 *
 * Supprimer une clé étrangère verrouille AUSSI la table référencée en accès
 * exclusif (retrait de ses déclencheurs). Pour ne jamais bloquer les autres
 * traitements derrière une longue lecture, chaque tentative est limitée à
 * 1 s de verrou, avec quelques reprises ; en cas d'échec la migration reste
 * en attente et pourra être rejouée plus tard (le graphe fonctionne avec ou
 * sans ces clés : les colonnes sont indexées).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const CONSTRAINTS = [
        ['network_edges', 'network_edges_segment_id_foreign'],
        ['network_edges', 'network_edges_line_id_foreign'],
        // station_edges a hérité du nom « network_edges_line_id_foreign » (table renommée).
        ['station_edges', 'network_edges_line_id_foreign'],
        ['station_edges', 'station_edges_line_id_foreign'],
    ];

    public function up(): void
    {
        foreach (self::CONSTRAINTS as [$table, $constraint]) {
            for ($attempt = 1; ; $attempt++) {
                try {
                    DB::transaction(function () use ($table, $constraint) {
                        DB::statement("SET LOCAL lock_timeout = '1s'");
                        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
                    });
                    break;
                } catch (QueryException $e) {
                    if ($attempt >= 20 || ! str_contains($e->getMessage(), 'lock timeout')) {
                        throw $e;
                    }
                    sleep(3);
                }
            }
        }
    }

    public function down(): void
    {
        // Pas de recréation : le graphe peut référencer des tronçons régénérés depuis.
    }
};
