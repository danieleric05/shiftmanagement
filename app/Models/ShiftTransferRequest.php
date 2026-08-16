<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTransferRequest extends Model
{
    protected $fillable = [
        'organisation_id', 'type', 'shift_id', 'shift_destination_id', 'servant_id',
        'demandeur_id', 'motif', 'date_demande', 'discussion_servant', 'approuve_deux_shifts',
        'statut', 'resultat', 'resultat_date', 'notes', 'decideur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'date',
            'resultat_date' => 'date',
            'approuve_deux_shifts' => 'boolean',
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

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeRecentes($query, int $jours = 14)
    {
        return $query->where('date_demande', '>=', now()->subDays($jours));
    }
}
