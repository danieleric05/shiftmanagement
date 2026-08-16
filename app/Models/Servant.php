<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Servant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'user_id', 'nom', 'prenom', 'genre', 'photo',
        'telephone', 'telephone_appel', 'pieu_id', 'date_naissance', 'adresse', 'statut', 'titre_leadership',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pieu(): BelongsTo
    {
        return $this->belongsTo(Pieu::class);
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(ServantWorkflowStep::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function assignationsActives(): HasMany
    {
        return $this->assignments()->where('statut', 'actif');
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
