<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieu extends Model
{
    protected $table = 'pieux';

    protected $fillable = ['organisation_id', 'nom'];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class);
    }
}
