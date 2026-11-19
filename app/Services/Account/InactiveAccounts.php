<?php

namespace App\Services\Account;

use App\Enums\AccountDeletionReason;
use App\Enums\AuditAction;
use App\Models\User;
use App\Notifications\InactiveAccountWarning;
use App\Services\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Comptes inutilisés : « Un compte inutilisé pendant 3 ans est supprimé, après un avertissement
 * envoyé par courriel un mois avant » (politique de confidentialité, section « Si vous créez un
 * compte »). Les deux durées ci-dessous doivent rester celles du texte publié.
 *
 * Dernière utilisation = dernière visite connectée (users.last_seen_at), à défaut la création du
 * compte. Un membre averti qui revient (User::markSeen) voit son avertissement annulé.
 */
final readonly class InactiveAccounts
{
    public const INACTIVITY_YEARS = 3;

    public const NOTICE_MONTHS = 1;

    public function __construct(
        private AccountDeletion $deletion,
        private AuditLog $audit,
    ) {}

    /**
     * Comptes à avertir : inutilisés depuis 3 ans moins un mois, pas encore avertis.
     *
     * @return Builder<User>
     */
    public function dueForWarning(CarbonImmutable $now): Builder
    {
        $threshold = $now->subYears(self::INACTIVITY_YEARS)->addMonths(self::NOTICE_MONTHS);

        return User::query()
            ->whereNull('inactivity_warned_at')
            ->whereRaw('COALESCE(last_seen_at, created_at) <= ?', [$threshold]);
    }

    /**
     * Comptes à supprimer : avertis depuis un mois au moins et pas revenus depuis.
     *
     * @return Builder<User>
     */
    public function dueForDeletion(CarbonImmutable $now): Builder
    {
        return User::query()
            ->whereNotNull('inactivity_warned_at')
            ->where('inactivity_warned_at', '<=', $now->subMonths(self::NOTICE_MONTHS))
            ->whereRaw('COALESCE(last_seen_at, created_at) < inactivity_warned_at');
    }

    /**
     * Envoie les avertissements. Un compte n'est marqué averti que si le courriel est parti :
     * sans courriel effectivement envoyé, le délai d'un mois ne commence pas.
     *
     * @return array{warned: int, failed: int}
     */
    public function warn(CarbonImmutable $now): array
    {
        $warned = 0;
        $failed = 0;
        $deletionDate = $now->addMonths(self::NOTICE_MONTHS);

        // lazyById : la requête est relue par paquets d'identifiants croissants, sans décalage
        // faussé par les comptes marqués ou supprimés en cours de route.
        $this->dueForWarning($now)->lazyById(100)->each(function (User $user) use ($now, $deletionDate, &$warned, &$failed): void {
            try {
                $user->notifyNow(new InactiveAccountWarning($deletionDate));
            } catch (Throwable $e) {
                report($e);
                $failed++;

                return;
            }

            // Sans toucher à updated_at ni passer par markSeen (ce n'est pas une visite).
            User::query()->whereKey($user->id)->toBase()->update(['inactivity_warned_at' => $now]);
            $warned++;
        });

        if ($warned > 0 || $failed > 0) {
            $this->audit->record(AuditAction::InactivityWarningsSent, context: ['warned' => $warned, 'failed' => $failed]);
        }

        return ['warned' => $warned, 'failed' => $failed];
    }

    /**
     * Supprime les comptes avertis restés inutilisés ; renvoie leur nombre. Le dernier
     * administrateur est gardé (même règle que UserPolicy::delete) et signalé dans le journal de
     * l'application à chaque passage, pour qu'un autre administrateur soit nommé.
     */
    public function deleteExpired(CarbonImmutable $now): int
    {
        $deleted = 0;
        $this->dueForDeletion($now)->lazyById(100)->each(function (User $user) use (&$deleted): void {
            if ($user->isLastAdmin()) {
                Log::warning('Comptes inactifs : le dernier administrateur n\'est pas supprimé malgré son inactivité.', ['user_id' => $user->id]);

                return;
            }

            $this->deletion->delete($user, AccountDeletionReason::Inactivity);
            $deleted++;
        });

        return $deleted;
    }
}
