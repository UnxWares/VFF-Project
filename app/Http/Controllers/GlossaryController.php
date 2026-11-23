<?php

namespace App\Http\Controllers;

use App\Support\Glossary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /glossary, « Glossaire » : le vocabulaire ferroviaire de l'encyclopédie, défini en trois langues
 * (App\Support\Glossary). Présentation par thème (défaut) ou par ordre alphabétique : `?vue=alpha`.
 */
class GlossaryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Glossary', [
            'terms' => Glossary::all(app()->getLocale()),
            'themes' => Glossary::themes(),
            'view' => $request->query('vue') === 'alpha' ? 'alpha' : 'theme',
        ]);
    }
}
