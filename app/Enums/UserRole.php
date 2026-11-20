<?php

namespace App\Enums;

enum UserRole: string
{
    case Visitor = 'visitor';
    case Contributor = 'contributor';
    case Validator = 'validator';
    case Admin = 'admin';

    /** Rôles qui relisent les contributions et examinent les signalements d'erreur. */
    public const REVIEWERS = [self::Validator, self::Admin];

    public function isReviewer(): bool
    {
        return in_array($this, self::REVIEWERS, true);
    }
}
