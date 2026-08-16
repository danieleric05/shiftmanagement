<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'organisation_id', 'role_id', 'telephone', 'photo', 'statut', 'is_platform_owner'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_owner' => 'boolean',
        ];
    }

    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function shiftMemberships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }

    public function servant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Servant::class);
    }

    public function shifts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_members')
            ->withPivot(['role_id', 'date_debut', 'date_fin', 'statut'])
            ->withTimestamps();
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    /**
     * IDs des shifts que cet utilisateur gère (rôle de coordination via ShiftMember),
     * indépendamment de son rôle global. Sert de base au contrôle d'accès par shift.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function shiftsGeres(): \Illuminate\Support\Collection
    {
        return $this->shiftMemberships()
            ->where('statut', 'actif')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                'chef_equipe', 'chef_adjoint', 'coordinateur', 'coordinateur_adjoint',
            ]))
            ->pluck('shift_id');
    }

    public function estAdministrateur(): bool
    {
        return in_array($this->role?->slug, ['administrateur', 'super_admin'], true);
    }
}
