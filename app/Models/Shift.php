<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shift extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['organisation_id', 'shift_template_id', 'nom', 'jour', 'heure_debut', 'heure_fin', 'statut'];

    /**
     * Trie par jour de la semaine réel (lundi → dimanche), pas par ordre alphabétique.
     */
    public function scopeOrderByJourCalendrier($query)
    {
        return $query->orderByRaw("CASE jour
            WHEN 'lundi' THEN 1
            WHEN 'mardi' THEN 2
            WHEN 'mercredi' THEN 3
            WHEN 'jeudi' THEN 4
            WHEN 'vendredi' THEN 5
            WHEN 'samedi' THEN 6
            WHEN 'dimanche' THEN 7
            END");
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(ShiftPosition::class)->orderBy('ordre');
    }

    public function shiftMembers(): HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }

    public function membresActifs(): HasMany
    {
        return $this->shiftMembers()->where('statut', 'actif');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_members')
            ->withPivot(['role_id', 'date_debut', 'date_fin', 'statut'])
            ->withTimestamps();
    }

    public function chefEquipe(): ?User
    {
        return $this->membresActifs()
            ->whereHas('role', fn ($q) => $q->where('gere_shifts', true))
            ->first()?->user;
    }

    /**
     * Genre du Shift déduit de son nom (ex. "Mardi Matin Sœurs") : aucun
     * champ dédié, c'est la seule source de vérité utilisée pour filtrer les
     * postes proposés et valider le genre des servants affectés.
     */
    public function estSoeurs(): bool
    {
        return Str::contains(Str::lower($this->nom), ['sœur', 'soeur']);
    }

    /**
     * Genre attendu ('homme'/'femme') des servants affectés à ce Shift.
     */
    public function genreAttendu(): string
    {
        return $this->estSoeurs() ? 'femme' : 'homme';
    }

    /**
     * Bloque l'affectation ou la permutation d'un servant vers ce Shift si
     * son genre ne correspond pas au genre attendu — règle unique utilisée à
     * la fois pour l'affectation directe (ShiftController) et la permutation
     * (ShiftTransferRequestController), plutôt que dupliquée à chaque appelant.
     * Les messages par défaut couvrent le cas "affectation" ; un appelant
     * peut les personnaliser (ex. "permutation") sans dupliquer la comparaison.
     */
    public function assurerGenreCompatible(Servant $servant, ?string $messageHomme = null, ?string $messageFemme = null): void
    {
        $genreAttendu = $this->genreAttendu();

        abort_if(
            $servant->genre !== null && $servant->genre !== $genreAttendu,
            422,
            $genreAttendu === 'femme'
                ? ($messageHomme ?? 'Un homme ne peut pas être affecté à un Shift Sœurs.')
                : ($messageFemme ?? 'Une femme ne peut pas être affectée à un Shift Frères.')
        );
    }
}
