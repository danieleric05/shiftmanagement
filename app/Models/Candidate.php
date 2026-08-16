<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Candidate extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'nom', 'prenom', 'telephone', 'shift_souhaite_id',
        'date_appel', 'statut', 'servant_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_appel' => 'date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shiftSouhaite(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_souhaite_id');
    }

    public function servant(): BelongsTo
    {
        return $this->belongsTo(Servant::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
