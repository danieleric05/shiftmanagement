<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'pays', 'langue', 'statut', 'license_expires_at'];

    protected function casts(): array
    {
        return [
            'license_expires_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expires_at !== null && $this->license_expires_at->isPast();
    }
}
