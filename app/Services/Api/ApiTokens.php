<?php

namespace App\Services\Api;

use App\Enums\ApiAccessProfile;
use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Jetons d'API personnels (Sanctum) d'un compte : création, révocation, liste du profil.
 *
 * Un jeton donne la lecture de l'API publique (capacité `read`) et relève la limite de débit de
 * son compte (ApiAccessProfile). Coché à la création, il reçoit aussi la capacité `contribute`
 * (CONTRIBUTE) : les mutations GraphQL de contribution et de validation, avec les droits du compte.
 * Son secret n'est montré qu'une fois, à la création : la base n'en garde que l'empreinte SHA-256.
 * La création et la révocation sont consignées dans le journal d'audit (identifiant du jeton et
 * capacité de contribution, jamais son nom ni son secret).
 */
final readonly class ApiTokens
{
    /** Capacité facultative des mutations GraphQL (submitContribution, validateContribution). */
    public const CONTRIBUTE = 'contribute';

    public function __construct(private AuditLog $audit) {}

    /**
     * Capacités d'un nouveau jeton : `read` toujours, plus les capacités facultatives demandées
     * qui figurent dans config/api.php (les autres sont ignorées).
     *
     * @param  list<string>  $optional
     * @return list<string>
     */
    public static function abilities(array $optional = []): array
    {
        $allowed = (array) config('api.tokens.optional_abilities', [self::CONTRIBUTE]);

        return array_values(array_unique([
            ...(array) config('api.tokens.abilities', ['read']),
            ...array_values(array_intersect($optional, $allowed)),
        ]));
    }

    public static function maxPerAccount(): int
    {
        return max(1, (int) config('api.tokens.max_per_account', 10));
    }

    /** Le compte peut-il créer un jeton maintenant ? */
    public function canCreate(User $user): bool
    {
        return ! $user->isSuspended() && $user->tokens()->count() < self::maxPerAccount();
    }

    /**
     * Crée un jeton : lecture, et contribution si `$contribute`. Les refus (compte suspendu,
     * nombre maximal atteint) sont vérifiés par l'appelant (StoreApiTokenRequest, middleware not.suspended) ; ce service les
     * vérifie de nouveau, sous verrou du compte, pour qu'un double envoi ne dépasse pas le nombre maximal.
     *
     * @throws ApiTokenRefused
     */
    public function create(User $user, string $name, bool $contribute = false): NewAccessToken
    {
        return DB::transaction(function () use ($user, $name, $contribute): NewAccessToken {
            // Suspension relue sur la ligne verrouillée : une suspension décidée pendant la requête compte.
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuspended()) {
                throw ApiTokenRefused::suspended();
            }
            if ($user->tokens()->count() >= self::maxPerAccount()) {
                throw ApiTokenRefused::tooMany(self::maxPerAccount());
            }

            $token = $user->createToken(trim($name), self::abilities($contribute ? [self::CONTRIBUTE] : []));
            // Capacité de contribution notée seulement quand elle est accordée.
            $this->audit->record(AuditAction::ApiTokenCreated, $user, context: [
                'token_id' => $token->accessToken->id,
                ...($contribute ? ['contribute' => true] : []),
            ]);

            return $token;
        });
    }

    /** Révoque (supprime) un jeton du compte ; faux si le jeton n'existe pas ou appartient à un autre compte. */
    public function revoke(User $user, int $tokenId): bool
    {
        return DB::transaction(function () use ($user, $tokenId): bool {
            $deleted = $user->tokens()->whereKey($tokenId)->delete() > 0;
            if ($deleted) {
                $this->audit->record(AuditAction::ApiTokenRevoked, $user, context: ['token_id' => $tokenId]);
            }

            return $deleted;
        });
    }

    /**
     * Ce que montre la section « Jetons d'accès à l'API » du profil : jetons (sans secret), quota du
     * compte, nombre maximal, et le jeton tout juste créé (secret en clair, une seule fois).
     *
     * @return array{tokens: list<array{id: int, name: string, contribute: bool, created_at: ?string, last_used_at: ?string}>, profile: string, per_minute: int, anonymous_per_minute: int, max_tokens: int, can_create: bool, suspended: bool, new_token: ?array{id: int, plain: string}}
     */
    public function forProfile(User $user, mixed $justCreated = null): array
    {
        $tokens = $user->tokens()->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (PersonalAccessToken $t): array => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'contribute' => $t->can(self::CONTRIBUTE),
                'created_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()->all();

        $profile = ApiAccessProfile::for($user);
        $new = is_array($justCreated) && is_int($justCreated['id'] ?? null) && is_string($justCreated['plain'] ?? null)
            && in_array($justCreated['id'], array_column($tokens, 'id'), true)
            ? ['id' => $justCreated['id'], 'plain' => $justCreated['plain']]
            : null;

        return [
            'tokens' => $tokens,
            'profile' => $profile->value,
            'per_minute' => $profile->perMinute(),
            'anonymous_per_minute' => ApiAccessProfile::Anonymous->perMinute(),
            'max_tokens' => self::maxPerAccount(),
            'can_create' => ! $user->isSuspended() && count($tokens) < self::maxPerAccount(),
            'suspended' => $user->isSuspended(),
            'new_token' => $new,
        ];
    }
}
