<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GovernanceRequest extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'servant_id', 'type', 'motif', 'demandeur_id',
        'statut', 'decideur_id', 'decision_commentaire', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function servant(): BelongsTo
    {
        return $this->belongsTo(Servant::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }
}
