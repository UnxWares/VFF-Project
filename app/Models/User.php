<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * `role` et les compteurs `reputation_*` volontairement absents : un futur
     * `$user->update($request->all())` ne doit jamais pouvoir promouvoir un
     * compte (rôle posé explicitement, cf. AuthController::register).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'bio',
        'avatar_path',
        'locale_preference',
    ];

    /** Valeurs des colonnes à zéro par défaut : un modèle tout juste créé (non relu) a déjà ses compteurs. */
    protected $attributes = [
        'reputation_applied' => 0,
        'reputation_rejected' => 0,
        'reputation_reviews' => 0,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        // Compteurs de réputation (ReputationCalculator) : jamais posés à la main.
        'reputation_applied' => 'integer',
        'reputation_rejected' => 'integer',
        'reputation_reviews' => 'integer',
        'last_seen_at' => 'datetime',
        'inactivity_warned_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'suspended_at' => 'datetime',
        'suspended_until' => 'datetime',
        'api_partner_at' => 'datetime',
    ];

    /** Langue des courriels et notifications : celle choisie dans le profil. */
    public function preferredLocale(): string
    {
        return $this->locale_preference ?: 'fr';
    }

    /**
     * Note la visite d'un membre connecté, au plus une écriture par jour, et annule un
     * éventuel avertissement d'inactivité : revenir sur le site suffit à garder son compte.
     * Sans toucher à updated_at (ce n'est pas une modification du profil).
     */
    public function markSeen(): void
    {
        if ($this->last_seen_at?->isToday() && $this->inactivity_warned_at === null) {
            return;
        }

        $this->forceFill(['last_seen_at' => now(), 'inactivity_warned_at' => null]);
        $this->timestamps = false;
        $this->saveQuietly();
        $this->timestamps = true;
    }

    /**
     * Partenaire de l'API publique (quota le plus haut, config/api.php) : accordé par un
     * administrateur (vff:api-partner), jamais par le membre lui-même (absent de $fillable).
     */
    public function isApiPartner(): bool
    {
        return $this->api_partner_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Seul administrateur du site en mesure d'agir : son compte ne doit jamais être supprimé (ni par
     * lui-même, cf. UserPolicy::delete, ni pour inactivité) ni suspendu (UserPolicy::suspend),
     * sinon personne ne pourrait plus attribuer les rôles. Un autre administrateur suspendu ne
     * compte pas : il ne peut rien faire tant que sa suspension dure.
     */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin() && ! self::query()
            ->where('role', UserRole::Admin->value)
            ->whereKeyNot($this->getKey())
            ->notSuspended()
            ->exists();
    }

    /**
     * Compte actuellement suspendu : suspension posée et pas encore échue. Une date de fin passée
     * suffit à rétablir le compte, sans tâche planifiée.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null
            && ($this->suspended_until === null || $this->suspended_until->isFuture());
    }

    /**
     * Comptes actuellement suspendus.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function suspended(Builder $query): void
    {
        $query->whereNotNull('suspended_at')
            ->where(fn (Builder $q) => $q->whereNull('suspended_until')->orWhere('suspended_until', '>', now()));
    }

    /**
     * Comptes qui ne sont pas actuellement suspendus.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function notSuspended(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('suspended_at')
            ->orWhere(fn (Builder $q) => $q->whereNotNull('suspended_until')->where('suspended_until', '<=', now())));
    }

    /** Administrateur qui a posé la suspension en cours (null s'il a supprimé son compte depuis). */
    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by');
    }

    /**
     * Notifications du site, par le modèle de l'application (purge des notifications lues après
     * 12 mois) ; unreadNotifications() et le canal `database` passent par cette relation.
     *
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /** @return HasMany<Contribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** @return HasMany<Contribution, $this> */
    public function reviewedContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'reviewed_by');
    }

    /**
     * Signalements d'erreur envoyés par le membre connecté.
     *
     * @return HasMany<ErrorReport, $this>
     */
    public function errorReports(): HasMany
    {
        return $this->hasMany(ErrorReport::class);
    }

    /** Validateur ou administrateur : relit les contributions et examine les signalements. */
    public function isReviewer(): bool
    {
        return $this->role->isReviewer();
    }

    /**
     * Validateurs et administrateurs (UserRole::REVIEWERS).
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function reviewers(Builder $query): void
    {
        $query->whereIn('role', array_map(fn (UserRole $role): string => $role->value, UserRole::REVIEWERS));
    }
}
