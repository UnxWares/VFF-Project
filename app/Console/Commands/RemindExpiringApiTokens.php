<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ApiTokensExpiring;
use App\Services\Api\ApiTokens;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Rappel par courriel 30 jours avant l'échéance des jetons d'API (un an après leur création ou leur
 * dernier renouvellement) : un seul courriel par compte, qui liste ses jetons concernés. Chaque
 * jeton n'est rappelé qu'une fois par échéance (`expiry_reminded_at`, remis à vide au renouvellement).
 * Les comptes suspendus ne reçoivent rien : leurs jetons ne servent pas pendant la suspension.
 *
 *   php artisan vff:remind-expiring-api-tokens --dry-run   # compte sans rien envoyer
 *   php artisan vff:remind-expiring-api-tokens             # tâche quotidienne de production
 *
 * Déclarée dans le planificateur pour la production seulement (routes/console.php).
 */
class RemindExpiringApiTokens extends Command
{
    protected $signature = 'vff:remind-expiring-api-tokens
        {--dry-run : Affiche le nombre de comptes et de jetons concernés sans rien envoyer}';

    protected $description = 'Prévient par courriel les membres dont un jeton d\'API expire dans 30 jours';

    public function handle(): int
    {
        $due = PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereNull('expiry_reminded_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(ApiTokens::reminderDays()))
            ->orderBy('expires_at')
            ->get()
            ->groupBy('tokenable_id');

        if ($this->option('dry-run')) {
            $this->info(sprintf('Simulation : %d compte(s) à prévenir, %d jeton(s).', $due->count(), $due->flatten()->count()));

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($due as $userId => $tokens) {
            $user = User::query()->find($userId);
            if ($user === null || $user->isSuspended()) {
                continue;
            }
            try {
                $user->notify(new ApiTokensExpiring($tokens->map(fn (PersonalAccessToken $t): array => [
                    'name' => (string) $t->name,
                    'expires_at' => $t->expires_at,
                ])->values()->all()));
                PersonalAccessToken::query()->whereKey($tokens->modelKeys())->update(['expiry_reminded_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        $this->info(sprintf('%d compte(s) prévenu(s).', $sent));
        if ($failed > 0) {
            $this->warn(sprintf('%d rappel(s) non envoyé(s) (courriel en échec) : nouvel essai au prochain passage.', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
