<?php

namespace App\Services\Account;

/** Bilan d'une suppression de compte (compteurs, sans donnée personnelle). */
final readonly class AccountDeletionSummary
{
    public function __construct(
        public int $contributionsDeleted,
        public int $contributionsAnonymised,
        public int $approvalsAnonymised,
    ) {}
}
