<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Accorde ou retire le profil « partenaire » de l'API publique à un compte (quota le plus haut,
 * config/api.php). Réservé aux administrateurs du serveur : le membre ne peut pas se l'attribuer.
 *
 *   php artisan vff:api-partner prenom.nom@exemple.fr
 *   php artisan vff:api-partner 42 --revoke
 *   php artisan vff:api-partner --list
 *
 * Chaque changement est consigné dans le journal d'audit (identifiant du compte seulement).
 */
class ApiPartner extends Command
{
    protected $signature = 'vff:api-partner
        {account? : Numéro ou adresse électronique du compte}
        {--revoke : Retire le profil partenaire au lieu de l\'accorder}
        {--list : Liste les comptes partenaires}';

    protected $description = 'Accorde ou retire le profil « partenaire » de l\'API publique (quota de requêtes plus élevé)';

    public function handle(AuditLog $audit): int
    {
        if ($this->option('list')) {
            return $this->list();
        }

        $needle = trim((string) $this->argument('account'));
        if ($needle === '') {
            $this->error('Indiquez le numéro ou l\'adresse électronique du compte (ou --list).');

            return self::INVALID;
        }

        $user = ctype_digit($needle)
            ? User::query()->find((int) $needle)
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($needle)])->first();
        if ($user === null) {
            $this->error('Aucun compte ne correspond.');

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        if ($revoke !== $user->isApiPartner()) {
            $this->info(sprintf('Le compte n° %d %s partenaire : rien à faire.', $user->id, $revoke ? 'n\'est pas' : 'est déjà'));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $revoke, $audit): void {
            // `api_partner_at` n'est pas affectable en masse (User::$fillable) : posé explicitement.
            $user->forceFill(['api_partner_at' => $revoke ? null : now()])->save();
            $audit->record($revoke ? AuditAction::ApiPartnerRevoked : AuditAction::ApiPartnerGranted, context: [
                'account_id' => $user->id,
            ]);
        });

        $this->info(sprintf(
            $revoke ? 'Le compte n° %d n\'est plus partenaire de l\'API.' : 'Le compte n° %d est partenaire de l\'API.',
            $user->id,
        ));

        return self::SUCCESS;
    }

    private function list(): int
    {
        $rows = User::query()->whereNotNull('api_partner_at')->orderBy('id')
            ->get(['id', 'name', 'api_partner_at'])
            ->map(fn (User $u): array => [$u->id, $u->name, $u->api_partner_at?->format('Y-m-d H:i')])
            ->all();

        if ($rows === []) {
            $this->info('Aucun compte partenaire.');

            return self::SUCCESS;
        }
        $this->table(['N°', 'Nom', 'Partenaire depuis'], $rows);

        return self::SUCCESS;
    }
}
