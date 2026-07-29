<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftPosition extends Model
{
    protected $fillable = ['shift_id', 'shift_template_position_id', 'nom', 'ordre'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftTemplatePosition(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplatePosition::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function titulaire(): ?Servant
    {
        return $this->assignments()->where('statut', 'actif')->first()?->servant;
    }
}
