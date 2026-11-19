<?php

namespace App\Services\Account;

use App\Enums\AccountDeletionReason;
use App\Enums\AuditAction;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Suppression d'un compte, telle que la décrit la politique de confidentialité :
 *
 *   - les contributions appliquées (publiées dans l'encyclopédie) restent, détachées du compte
 *     (user_id NULL : « Contributeur anonyme » à l'affichage) ;
 *   - les contributions non publiées (en attente, partiellement approuvées, refusées) sont
 *     supprimées, avec leurs approbations ;
 *   - les approbations données comme validateur restent comptées mais ne désignent plus
 *     personne ; les décisions de relecture passent à reviewed_by NULL (clé étrangère) ;
 *   - le profil (nom, courriel, mot de passe, présentation, avatar, langue), les jetons d'API et
 *     les demandes de réinitialisation du mot de passe disparaissent avec la ligne users. Les
 *     sessions et les cookies « se souvenir de moi » deviennent inopérants : ils désignent un
 *     compte qui n'existe plus ;
 *   - l'identifiant du compte, recopié dans les propriétés des tracés bruts (source_traces,
 *     source « contrib »), en est retiré.
 *
 * L'opération est consignée dans le journal d'audit sans aucune donnée du compte : origine,
 * rôle et compteurs seulement.
 */
final readonly class AccountDeletion
{
    public function __construct(private AuditLog $audit) {}

    public function delete(User $user, AccountDeletionReason $reason): AccountDeletionSummary
    {
        $avatar = $user->avatar_path;

        $summary = DB::transaction(function () use ($user, $reason): AccountDeletionSummary {
            $unpublished = $user->contributions()
                ->where('status', '!=', ContributionStatus::Applied->value)
                ->delete();

            $anonymised = Contribution::query()
                ->where('user_id', $user->id)
                ->update(['user_id' => null]);

            $approvals = DB::table('contribution_approvals')
                ->where('user_id', $user->id)
                ->update(['user_id' => null]);

            DB::update(
                "UPDATE source_traces SET properties = properties - 'user_id'
                 WHERE source = 'contrib' AND properties->>'user_id' = ?",
                [(string) $user->id],
            );

            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $summary = new AccountDeletionSummary(
                contributionsDeleted: $unpublished,
                contributionsAnonymised: $anonymised,
                approvalsAnonymised: $approvals,
            );

            $this->audit->record(AuditAction::AccountDeleted, context: [
                'reason' => $reason->value,
                'role' => $user->role->value,
                'contributions_deleted' => $summary->contributionsDeleted,
                'contributions_anonymised' => $summary->contributionsAnonymised,
                'approvals_anonymised' => $summary->approvalsAnonymised,
            ]);

            $user->delete();

            return $summary;
        });

        // Fichier d'avatar téléversé sur le disque public (une URL externe n'est pas à nous).
        // Après la transaction : un échec de suppression du fichier ne doit pas rétablir le compte.
        if ($avatar !== null && $avatar !== '' && ! Str::startsWith($avatar, ['http://', 'https://', '//'])) {
            Storage::disk('public')->delete(Str::after($avatar, '/storage/'));
        }

        return $summary;
    }
}
