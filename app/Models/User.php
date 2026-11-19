<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * `role` et `reputation` volontairement absents : un futur
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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'reputation' => 'integer',
        'last_seen_at' => 'datetime',
        'inactivity_warned_at' => 'datetime',
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

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Seul administrateur du site : son compte ne doit jamais être supprimé (ni par lui-même,
     * cf. UserPolicy::delete, ni pour inactivité), sinon personne ne pourrait plus attribuer les rôles.
     */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin() && ! self::query()
            ->where('role', UserRole::Admin->value)
            ->whereKeyNot($this->getKey())
            ->exists();
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
}
