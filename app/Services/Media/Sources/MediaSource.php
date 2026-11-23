<?php

namespace App\Services\Media\Sources;

/**
 * Une source de médias (Commons rattaché aux gares, Panoramax, Albert-Kahn, cartes postales d'archives…).
 *
 * Contrat : `provider()` est la valeur de `media.provider` (20 caractères au plus, déclarée dans
 * `database/data/source_rights.php`, table `media`) ; `records()` produit des MediaRecord complets (droits
 * compris), sans rien écrire. L'écriture, la passe à blanc et les compteurs sont à MediaWriter, commun à
 * toutes les sources : une commande d'import se réduit à `$writer->write($source->records($limit), $dryRun)`.
 *
 * Requêtes réseau : pause entre deux appels, User-Agent générique, cache local avec `.nobackup`, robots.txt et
 * conditions du service respectés ; une source hors ligne (lecture de la base) n'en fait aucune.
 */
interface MediaSource
{
    /** Valeur de `media.provider` écrite par la source. */
    public function provider(): string;

    /**
     * Médias proposés, au plus `$limit` sujets (gares, lignes…) lus quand il est donné.
     *
     * @return iterable<MediaRecord>
     */
    public function records(?int $limit = null): iterable;
}
