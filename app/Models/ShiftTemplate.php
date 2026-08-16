<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = ['organisation_id', 'nom', 'description'];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(ShiftTemplatePosition::class)->orderBy('ordre');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
