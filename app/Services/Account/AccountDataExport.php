<?php

namespace App\Services\Account;

use App\Models\Contribution;
use App\Models\DatabaseNotification;
use App\Models\ErrorReport;
use App\Models\User;
use App\Services\Reputation\ReputationLevels;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Données d'un compte pour le droit d'accès et de portabilité (RGPD, articles 15 et 20) :
 * un document JSON structuré, lisible par une machine, avec tout ce que VFF garde du compte.
 *
 * Clés en anglais et dates ISO 8601 : un format stable, indépendant de la langue de la page.
 * Le mot de passe (haché), le jeton « se souvenir de moi », les jetons d'API eux-mêmes et les
 * secrets de signature des webhooks ne sont jamais exportés : ce sont des secrets, pas des
 * informations sur la personne.
 */
final readonly class AccountDataExport
{
    public function __construct(private ReputationLevels $levels, private WebhookSubscriptions $webhooks) {}

    /** Version du format, à incrémenter si la structure change (3 : abonnements webhook). */
    public const FORMAT_VERSION = 3;

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return [
            'format' => 'vff-account-export',
            'version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'controller' => [
                'name' => 'UnxWares',
                'service' => 'Voies Ferrées de France (vff-project.org)',
                'privacy_contact' => 'privacy@vff-project.org',
            ],
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'role' => $user->role->value,
                'bio' => $user->bio,
                'avatar_path' => $user->avatar_path,
                'locale_preference' => $user->locale_preference,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'inactivity_warned_at' => $user->inactivity_warned_at?->toIso8601String(),
                // Acceptation des CGU à l'inscription (null pour un compte créé avant le 19/11/2026).
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'terms_version' => $user->terms_version,
                // Profil « partenaire » de l'API accordé par un administrateur (null sinon).
                'api_partner_since' => $user->api_partner_at?->toIso8601String(),
            ],
            // Réputation : les trois compteurs, le taux d'acceptation et le niveau (null tant que le compte
            // est suspendu : il garde son historique, mais n'affiche pas de niveau). Jamais publiée dans l'API.
            'reputation' => $this->reputation($user),
            // Suspension du compte par un administrateur (null si le compte n'a jamais été suspendu ou
            // si la suspension a été levée). Jamais le nom de l'administrateur qui l'a décidée.
            'suspension' => $user->suspended_at === null ? null : [
                'active' => $user->isSuspended(),
                'suspended_at' => $user->suspended_at->toIso8601String(),
                'ends_at' => $user->suspended_until?->toIso8601String(),
                'reason' => $user->suspension_reason,
            ],
            'contributions' => $user->contributions()->orderBy('id')->get()
                ->map(fn (Contribution $c): array => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'action' => $c->action->value,
                    'status' => $c->status->value,
                    'target' => $c->target_type === null ? null : [
                        'type' => class_basename($c->target_type),
                        'id' => $c->target_id,
                    ],
                    'payload' => $c->payload,
                    'created_at' => $c->created_at?->toIso8601String(),
                    'updated_at' => $c->updated_at?->toIso8601String(),
                    'reviewed_at' => $c->reviewed_at?->toIso8601String(),
                    'review_notes' => $c->review_notes,
                ])->all(),
            'approvals_given' => DB::table('contribution_approvals')
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get(['contribution_id', 'approved_at', 'notes'])
                ->map(fn (object $a): array => [
                    'contribution_id' => (int) $a->contribution_id,
                    'approved_at' => $a->approved_at === null ? null : Carbon::parse($a->approved_at)->toIso8601String(),
                    'notes' => $a->notes,
                ])->all(),
            'reviews_made' => $user->reviewedContributions()->orderBy('id')->get()
                ->map(fn (Contribution $c): array => [
                    'contribution_id' => $c->id,
                    'status' => $c->status->value,
                    'reviewed_at' => $c->reviewed_at?->toIso8601String(),
                    'review_notes' => $c->review_notes,
                ])->all(),
            // Notifications du site, telles qu'affichées (texte dans la langue du membre).
            'notifications' => $user->notifications()->reorder()->orderBy('created_at')->orderBy('id')->get()
                ->map(fn (DatabaseNotification $n): array => [
                    'kind' => $n->data['kind'] ?? null,
                    'contribution_id' => $n->data['contribution_id'] ?? null,
                    'title' => $n->data['title'] ?? null,
                    'body' => $n->data['body'] ?? null,
                    'reason' => $n->data['reason'] ?? null,
                    'created_at' => $n->created_at?->toIso8601String(),
                    'read_at' => $n->read_at?->toIso8601String(),
                ])->all(),
            // Signalements d'erreur envoyés depuis les fiches, avec la suite que l'équipe leur a donnée.
            'error_reports' => $user->errorReports()->orderBy('id')->get()
                ->map(fn (ErrorReport $r): array => [
                    'id' => $r->id,
                    'subject' => [
                        'type' => $r->subject_type->value,
                        'slug' => $r->subject_slug,
                        'label' => $r->subject_label,
                    ],
                    'description' => $r->description,
                    'source_url' => $r->source_url,
                    'contact_email' => $r->contact_email,
                    'locale' => $r->locale,
                    'status' => $r->status->value,
                    'resolution_note' => $r->resolution_note,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'handled_at' => $r->handled_at?->toIso8601String(),
                ])->all(),
            'api_tokens' => $user->tokens()->orderBy('id')->get()
                ->map(fn (PersonalAccessToken $t): array => [
                    'name' => $t->name,
                    'abilities' => $t->abilities,
                    'created_at' => $t->created_at?->toIso8601String(),
                    'last_used_at' => $t->last_used_at?->toIso8601String(),
                    'expires_at' => $t->expires_at?->toIso8601String(),
                ])->all(),
            // Abonnements webhook : adresse, événements, état et dernières livraisons ; jamais le secret.
            'webhooks' => $this->webhooks->forExport($user),
        ];
    }

    /** @return array{applied_contributions: int, rejected_contributions: int, reviews_made: int, acceptance_rate: int|null, level: string|null, level_hidden_by_suspension: bool} */
    private function reputation(User $user): array
    {
        $summary = $this->levels->forUser($user);

        return [
            'applied_contributions' => $summary->applied,
            'rejected_contributions' => $summary->rejected,
            'reviews_made' => $summary->reviews,
            'acceptance_rate' => $summary->acceptanceRate,
            'level' => $summary->level,
            'level_hidden_by_suspension' => $user->isSuspended(),
        ];
    }
}
