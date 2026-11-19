<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Données SNCF Réseau factices pour les tests des passages à niveau et de la signalisation :
 * passages à niveau (`sncf_level_crossings`) et forme officielle d'une ligne (`sncf_line_geometries`).
 */
trait SeedsLevelCrossings
{
    /** Passage à niveau, position en degrés ; `mnemo` au format SNCF (« CLASSE 17 »). */
    protected function seedLevelCrossing(string $idIf, string $code, ?int $pk, float $lat, float $lon, ?string $mnemo = 'CLASSE 17', array $extra = []): void
    {
        DB::table('sncf_level_crossings')->insert($extra + [
            'id_if' => $idIf, 'libelle' => 'PN '.substr($idIf, -2), 'mnemo' => $mnemo, 'obstacle' => 'VC',
            'code_ligne' => $code, 'rg_troncon' => 1, 'pk_m' => $pk, 'commune' => 'Courthiézy',
            'lat' => $lat, 'lon' => $lon, 'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Tronçon de forme SNCF Réseau en ligne droite d'ouest en est, entre deux PK. */
    protected function seedSncfShape(string $code, int $pkFrom, int $pkTo, float $lat, float $lonFrom, float $lonTo): void
    {
        DB::table('sncf_line_geometries')->insert([
            'code_ligne' => $code, 'libelle' => "Ligne {$code}", 'pk_debut_m' => $pkFrom, 'pk_fin_m' => $pkTo,
            'rg_troncon' => 1, 'idgaia' => "gaia-{$code}-{$pkFrom}",
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lonFrom, $lat, $lonTo, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
