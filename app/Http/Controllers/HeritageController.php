<?php

namespace App\Http\Controllers;

use App\Services\Heritage\HeritageIndex;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /heritage, « Patrimoine ferroviaire protégé » : bâtiments voyageurs et ouvrages d'art classés ou inscrits aux
 * monuments historiques ou labellisés « Architecture contemporaine remarquable » (mêmes données que
 * `GET /api/v1/heritage`). Filtres, tri et vue par architecte côté navigateur.
 */
class HeritageController extends Controller
{
    public function index(HeritageIndex $heritage): Response
    {
        return Inertia::render('Heritage/Index', ['data' => $heritage->get()]);
    }
}
