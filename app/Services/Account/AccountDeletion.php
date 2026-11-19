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
 *     les demandes de réinitialisation du mot de passe disparaissent avec la ligne users, comme
 *     ses notifications du site. Les sessions et les cookies « se souvenir de moi » deviennent
 *     inopérants : ils désignent un compte qui n'existe plus ;
 *   - ses signalements d'erreur (texte, adresse de contact) sont supprimés ;
 *   - l'identifiant du compte, recopié dans les propriétés des tracés bruts (source_traces,
 *     source « contrib ») et dans le journal d'audit (suspension, levée), en est retiré.
 *
 * L'opération est consignée dans le journal d'audit sans aucune donnée du compte : origine,
 * rôle, compteurs et identifiants des contributions supprimées seulement.
 */
final readonly class AccountDeletion
{
    public function __construct(private AuditLog $audit) {}

    public function delete(User $user, AccountDeletionReason $reason): AccountDeletionSummary
    {
        $avatar = $user->avatar_path;

        $summary = DB::transaction(function () use ($user, $reason): AccountDeletionSummary {
            // Une à une par le modèle (ses événements sont déclenchés) ; les approbations reçues
            // partent avec elles (clé étrangère en cascade).
            $unpublished = $user->contributions()
                ->where('status', '!=', ContributionStatus::Applied->value)
                ->orderBy('id')
                ->get()
                ->each(fn (Contribution $contribution) => $contribution->delete())
                ->map(fn (Contribution $contribution): int => $contribution->id)
                ->all();

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

            // Entrées du journal d'audit qui désignent ce compte (suspension, levée, profil partenaire
            // de l'API) : l'identifiant en est retiré, l'entrée garde son action et sa date.
            DB::update(
                "UPDATE audit_events SET context = context - 'account_id'
                 WHERE action IN (?, ?, ?, ?) AND context->>'account_id' = ?",
                [
                    AuditAction::AccountSuspended->value, AuditAction::AccountSuspensionLifted->value,
                    AuditAction::ApiPartnerGranted->value, AuditAction::ApiPartnerRevoked->value,
                    (string) $user->id,
                ],
            );

            // Signalements d'erreur envoyés par le membre (texte et adresse de contact : données
            // personnelles possibles), ouverts comme traités.
            $reports = $user->errorReports()->delete();

            $user->tokens()->delete();
            // Notifications du site (relation polymorphe : aucune clé étrangère ne les supprime).
            $user->notifications()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $summary = new AccountDeletionSummary(
                contributionsDeleted: count($unpublished),
                contributionsAnonymised: $anonymised,
                approvalsAnonymised: $approvals,
            );

            $this->audit->record(AuditAction::AccountDeleted, context: [
                'reason' => $reason->value,
                'role' => $user->role->value,
                'contributions_deleted' => $summary->contributionsDeleted,
                // Identifiants des contributions supprimées (comme `removed_contribution_id` d'un
                // retrait) : ils ne désignent plus rien et ne renseignent pas sur le compte.
                'deleted_contribution_ids' => $unpublished,
                'contributions_anonymised' => $summary->contributionsAnonymised,
                'approvals_anonymised' => $summary->approvalsAnonymised,
                'error_reports_deleted' => $reports,
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
