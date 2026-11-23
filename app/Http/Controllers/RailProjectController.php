<?php

namespace App\Http\Controllers;

use App\Services\RailProjects;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /projects, « Projets : réouvertures et lignes nouvelles » : les projets ferroviaires pas encore en service, leur
 * état, la date de chaque étape et sa source (mêmes données que `GET /api/v1/projects`). Filtres côté navigateur.
 */
class RailProjectController extends Controller
{
    public function index(RailProjects $projects): Response
    {
        return Inertia::render('Network/Projects', ['data' => $projects->get()]);
    }
}
