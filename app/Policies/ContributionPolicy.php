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
}
