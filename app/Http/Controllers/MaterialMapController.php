<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Services\Materials\MaterialLinesMap;
use Illuminate\Http\JsonResponse;

/**
 * Carte des lignes d'une fiche de matériel (GET /materials/{slug}/map.json) : tracé allégé de chaque ligne, niveau
 * de confiance du lien, source et périodes (MaterialLinesMap). Lue par la page quand la carte s'affiche, hors du
 * groupe web (ni session ni cookie, la même réponse pour tous) ; la fiche complète de l'API
 * (GET /api/v1/materials/{slug}/map) donne les mêmes lignes en retenant les tracés dont la licence n'autorise pas la
 * redistribution, ce que cet affichage de la carte du site ne fait pas (comme les tuiles vectorielles).
 */
final class MaterialMapController
{
    public function __invoke(string $slug, MaterialLinesMap $map): JsonResponse
    {
        $material = Material::query()->where('slug', $slug)->firstOrFail();

        return response()->json($map->for($material, display: true), 200, ['X-Content-Type-Options' => 'nosniff'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'public, max-age=600');
    }
}
