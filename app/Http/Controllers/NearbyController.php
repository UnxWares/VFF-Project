<?php

namespace App\Http\Controllers;

use App\Services\Communes\CommuneRail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Autour de moi » : /nearby. La position de l'internaute ne passe jamais par ce contrôleur : le navigateur
 * interroge `GET /api/v1/what-was-here?summary=1` avec un point arrondi à 0,001 degré. Ici ne se lisent que le nom d'une
 * commune tapé (`?q=`, résultats de la recherche de communes du site, formulaire sans JavaScript compris) et la
 * commune choisie (`?commune=16015-angouleme`, son centre est une donnée publique).
 */
class NearbyController extends Controller
{
    public function index(Request $request, CommuneRail $communes): Response
    {
        $q = trim((string) $request->query('q', ''));
        $matches = mb_strlen($q) >= 2 ? $communes->search($q) : [];

        $place = null;
        $insee = CommuneRail::inseeOf((string) $request->query('commune', ''));
        if ($insee !== null && in_array($insee, $communes->codes(), true)) {
            $row = DB::table('communes')->where('type', 'commune-actuelle')->where('code_insee', $insee)
                ->selectRaw('code_insee, nom, ST_Y(geom) AS lat, ST_X(geom) AS lng')->first();
            if ($row !== null) {
                $place = [
                    'name' => (string) $row->nom,
                    'slug' => CommuneRail::slug((string) $row->code_insee, (string) $row->nom),
                    'lat' => round((float) $row->lat, 4),
                    'lng' => round((float) $row->lng, 4),
                ];
            }
        }

        return Inertia::render('Network/Nearby', [
            'place' => $place,
            'query' => $q,
            'matches' => array_map(fn (array $m): array => ['slug' => $m['slug'], 'name' => $m['name'],
                'department_code' => $m['department_code'], 'department_name' => $m['department_name']], $matches),
        ]);
    }
}
