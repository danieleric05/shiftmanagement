<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Interview extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'candidate_id', 'shift_souhaite_id', 'planifie_par',
        'date_entretien', 'heure_entretien', 'engagement_vu', 'statut', 'resultat',
        'shift_affecte_id', 'decideur_id', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'date_entretien' => 'date',
            'engagement_vu' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function shiftSouhaite(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_souhaite_id');
    }

    public function shiftAffecte(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_affecte_id');
    }

    public function planifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planifie_par');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }
}
