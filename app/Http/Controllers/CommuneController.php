<?php

namespace App\Http\Controllers;

use App\Services\Communes\CommuneRail;
use App\Services\Communes\CommuneSheet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiche « Le rail à <commune> » (/communes/{insee}-{nom}) : gares et haltes, lignes qui traversent ou traversaient la
 * commune, chronologie fusionnée, carte et communes voisines (CommuneSheet). Seules les communes qui ont au moins
 * une gare ou une ligne ont une fiche. L'adresse sans le nom, ou avec un nom périmé, redirige (301) vers l'adresse
 * canonique.
 */
class CommuneController extends Controller
{
    public function show(string $slug, CommuneSheet $sheet): Response|RedirectResponse
    {
        $insee = CommuneRail::inseeOf($slug);
        $page = $insee === null ? null : $sheet->page($insee, app()->getLocale());
        abort_if($page === null, 404);

        if ($slug !== $page['commune']['slug']) {
            return redirect()->route('communes.show', $page['commune']['slug'], 301);
        }

        return Inertia::render('Communes/Show', $page);
    }
}
