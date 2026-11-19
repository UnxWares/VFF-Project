<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    /**
     * Retrait d'une contribution (contenu illicite, spam, données personnelles d'un tiers) :
     * décision d'UnxWares selon les CGU, donc réservée aux administrateurs.
     */
    public function remove(User $user, Contribution $contribution): bool
    {
        return $user->isAdmin();
    }

    /**
     * Fichier déposé d'une contribution « média » en attente : jamais public avant sa publication,
     * il n'est montré qu'à son auteur et aux validateurs (et administrateurs) qui le relisent.
     */
    public function viewPendingFile(User $user, Contribution $contribution): bool
    {
        return (int) $contribution->user_id === (int) $user->id || $user->isReviewer();
    }
}
