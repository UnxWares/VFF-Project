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

    /**
     * La fiche visée n'existe plus : ligne, gare ou événement d'un événement ou d'un média, ligne ou
     * série d'un lien matériel, série à corriger.
     */
    case TargetNotFound = 'target_not_found';

    /**
     * Déjà présent, ajouté entre-temps : le même fichier Commons sur la même fiche, ou une série du
     * même nom ou du même identifiant Wikidata.
     */
    case AlreadyPresent = 'already_present';

    /** Le fichier déposé n'est plus dans la réserve privée (retiré entre-temps). */
    case FileMissing = 'file_missing';

    /**
     * Le lien entre une série et une ligne contredit maintenant la règle d'écartement ou
     * d'électrification (tronçons de la ligne ou fiche de la série changés depuis l'envoi), et la
     * phrase citée ne suffit plus à le justifier.
     */
    case Incompatible = 'incompatible';

    /** Erreur inattendue (base de données, programme) : le détail est dans le journal. */
    case InternalError = 'internal_error';
}
