<?php

namespace App\Services\Accidents\Context;

/** Bilan de vff:enrich-accidents : fichiers lus, erreurs par fichier, lignes écrites, corrections, images. */
final class AccidentContextResult
{
    public int $checked = 0;

    /** @var array<string, list<string>> slug => erreurs (fichier refusé en entier) */
    public array $invalid = [];

    /** @var list<string> slugs écrits */
    public array $written = [];

    /** @var list<string> slugs dont le contexte a été retiré (fichier disparu) */
    public array $removed = [];

    public int $references = 0;

    public int $entries = 0;

    public int $images = 0;

    /** @var list<string> images écartées, avec le motif */
    public array $imagesRejected = [];

    /** @var list<string> corrections appliquées (fiche, champ, avant, après) */
    public array $corrections = [];

    public int $imageRequests = 0;
}
