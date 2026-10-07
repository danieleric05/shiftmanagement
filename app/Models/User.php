<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'nom', 'prenom', 'email', 'password', 'organisation_id', 'role_id', 'telephone', 'photo', 'statut', 'is_platform_owner', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'preferences'])]
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
            'must_change_password' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /**
     * Clés (liste blanche) et ordre par défaut des colonnes de la liste des
     * servants (Servants/Index et vue « Nouveaux »).
     */
    public const COLONNES_SERVANTS = ['nom', 'prenom', 'statut', 'voir', 'pieu'];

    /**
     * Ordre des colonnes de la liste des servants choisi par l'utilisateur,
     * ou l'ordre par défaut si aucun ordre valide n'est enregistré (une
     * valeur stockée qui ne serait plus une permutation exacte de la liste
     * blanche — colonne ajoutée/retirée depuis — est ignorée).
     *
     * @return list<string>
     */
    public function ordreColonnesServants(): array
    {
        $ordre = $this->preferences['colonnes_servants'] ?? null;

        if (! is_array($ordre)) {
            return self::COLONNES_SERVANTS;
        }

        $trie = $ordre;
        $reference = self::COLONNES_SERVANTS;
        sort($trie);
        sort($reference);

        return array_is_list($ordre) && $trie === $reference ? $ordre : self::COLONNES_SERVANTS;
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function shiftMemberships(): HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }

    public function servant(): HasOne
    {
        return $this->hasOne(Servant::class);
    }

    public function shifts(): BelongsToMany
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
     * IDs des shifts que cet utilisateur gère (rôle de coordination via ShiftMember,
     * cf. gereDesShifts()), indépendamment de son rôle global. Sert de base au
     * contrôle d'accès par shift.
     *
     * @return Collection<int, int>
     */
    public function shiftsGeres(): Collection
    {
        // Le rôle « Autres » (lecture seule) ne gère jamais de shift, même s'il
        // était inscrit comme membre d'un shift avec un rôle de coordination.
        if ($this->estEnLectureSeule()) {
            return collect();
        }

        return $this->shiftMemberships()
            ->where('statut', 'actif')
            ->whereHas('role', fn ($q) => $q->where('gere_shifts', true))
            ->pluck('shift_id');
    }

    /**
     * Un rôle peut être marqué « gère des shifts » depuis Paramètres → Rôles
     * (coché par défaut sur Coordonnateur d'équipe, mais pas limité à lui) :
     * c'est ce booléen, pas le slug du rôle, qui donne accès au dashboard
     * coordinateur et au rôle "chef d'équipe" au sein d'un Shift.
     */
    public function gereDesShifts(): bool
    {
        return ! $this->estEnLectureSeule() && (bool) $this->role?->gere_shifts;
    }

    public function estAdministrateur(): bool
    {
        return in_array($this->role?->slug, ['administrateur', 'super_admin'], true);
    }

    /**
     * Administrateur ou Secrétaire : création/modification des servants et
     * gestion des permutations (transferts de shift) à l'échelle de
     * l'organisation, sans accès aux réglages réservés à l'administrateur.
     */
    public function gereServantsEtPermutations(): bool
    {
        return $this->estAdministrateur() || $this->role?->slug === 'secretaire';
    }

    /**
     * Rôle « Autres » : consultation en lecture seule de toute l'organisation
     * (hors configuration), sans aucune action d'écriture.
     */
    public function estEnLectureSeule(): bool
    {
        return $this->role?->slug === 'autres';
    }

    /**
     * Consultation de toutes les données de l'organisation (servants,
     * demandes de changement…) : administrateur, secrétaire, ou rôle
     * « Autres » en lecture seule. À n'utiliser que pour des droits de
     * LECTURE — les écritures restent régies par gereServantsEtPermutations().
     */
    public function consulteToutesLesDonnees(): bool
    {
        return $this->gereServantsEtPermutations() || $this->estEnLectureSeule();
    }
}
