<?php

namespace App\Enums;

enum ContributionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';

    /** Encore en relecture (en attente ou approuvée une fois) : seule une contribution ouverte peut être approuvée ou refusée. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::Approved => true,
            self::Rejected, self::Applied => false,
        };
    }
}
