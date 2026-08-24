<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShiftTransferRequest extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'type', 'shift_id', 'shift_destination_id', 'servant_id',
        'demandeur_id', 'motif', 'date_demande', 'discussion_servant', 'approuve_deux_shifts',
        'validation_chef_origine', 'validation_chef_origine_par_id', 'validation_chef_origine_le',
        'validation_chef_destination', 'validation_chef_destination_par_id', 'validation_chef_destination_le',
        'entretien_date', 'entretien_heure',
        'statut', 'resultat', 'resultat_date', 'favorable', 'shift_position_destination_id', 'notes', 'decideur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'date',
            'resultat_date' => 'date',
            'approuve_deux_shifts' => 'boolean',
            'validation_chef_origine' => 'boolean',
            'validation_chef_origine_le' => 'datetime',
            'validation_chef_destination' => 'boolean',
            'validation_chef_destination_le' => 'datetime',
            'entretien_date' => 'date',
            'favorable' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftDestination(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_destination_id');
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

    public function validateurOrigine(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_chef_origine_par_id');
    }

    public function validateurDestination(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_chef_destination_par_id');
    }

    public function shiftPositionDestination(): BelongsTo
    {
        return $this->belongsTo(ShiftPosition::class, 'shift_position_destination_id');
    }

    /**
     * Les deux chefs (origine + destination) ont validé la permutation : condition
     * requise avant que l'administrateur puisse planifier l'entretien puis trancher.
     */
    public function validationsChefsCompletes(): bool
    {
        return $this->validation_chef_origine === true && $this->validation_chef_destination === true;
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeRecentes($query, int $jours = 14)
    {
        return $query->where('date_demande', '>=', now()->subDays($jours));
    }
}
