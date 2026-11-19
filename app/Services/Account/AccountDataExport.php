<?php

namespace App\Services\Account;

use App\Models\Contribution;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Données d'un compte pour le droit d'accès et de portabilité (RGPD, articles 15 et 20) :
 * un document JSON structuré, lisible par une machine, avec tout ce que VFF garde du compte.
 *
 * Clés en anglais et dates ISO 8601 : un format stable, indépendant de la langue de la page.
 * Le mot de passe (haché), le jeton « se souvenir de moi » et les jetons d'API eux-mêmes ne
 * sont jamais exportés : ce sont des secrets, pas des informations sur la personne.
 */
final class AccountDataExport
{
    /** Version du format, à incrémenter si la structure change. */
    public const FORMAT_VERSION = 1;

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
                'reputation' => $user->reputation,
                'bio' => $user->bio,
                'avatar_path' => $user->avatar_path,
                'locale_preference' => $user->locale_preference,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'inactivity_warned_at' => $user->inactivity_warned_at?->toIso8601String(),
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
            'api_tokens' => $user->tokens()->orderBy('id')->get()
                ->map(fn (PersonalAccessToken $t): array => [
                    'name' => $t->name,
                    'abilities' => $t->abilities,
                    'created_at' => $t->created_at?->toIso8601String(),
                    'last_used_at' => $t->last_used_at?->toIso8601String(),
                    'expires_at' => $t->expires_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
