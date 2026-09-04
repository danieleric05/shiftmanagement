<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'nom', 'description', 'gere_shifts'];

    protected function casts(): array
    {
        return [
            'gere_shifts' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shiftMembers(): HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }
}
