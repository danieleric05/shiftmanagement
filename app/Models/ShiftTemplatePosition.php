<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplatePosition extends Model
{
    protected $fillable = ['shift_template_id', 'nom', 'ordre'];

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    /**
     * Postes déjà créés sur de vrais Shifts à partir de ce poste de modèle
     * (leur "nom" est une copie figée à la création, cf. ShiftPosition).
     */
    public function shiftPositions(): HasMany
    {
        return $this->hasMany(ShiftPosition::class);
    }
}
