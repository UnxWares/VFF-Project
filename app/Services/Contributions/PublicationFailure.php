<?php

namespace App\Services\Contributions;

/**
 * Raison stable de l'échec de la publication d'une contribution (rien n'est écrit, l'approbation est
 * annulée), montrée au validateur sur le site et dans l'API GraphQL (`extensions.reason`). Le détail
 * technique de l'erreur ne sort jamais : il reste dans le journal du site.
 */
enum PublicationFailure: string
{
    /** La ligne n'a pas de tracé à l'ère de la contribution : la portion ne peut pas y être placée. */
    case NoGeometry = 'no_geometry';

    /** La ligne que corrige un tracé n'existe plus. */
    case LineNotFound = 'line_not_found';

    /** Erreur inattendue (base de données, programme) : le détail est dans le journal. */
    case InternalError = 'internal_error';
}
