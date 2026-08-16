<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRecruitmentNeed extends Model
{
    protected $fillable = [
        'organisation_id', 'shift_id', 'nombre_a_recruter', 'echeance', 'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'echeance' => 'date',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
