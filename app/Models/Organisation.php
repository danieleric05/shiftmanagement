<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $nom
 * @property Carbon|null $license_expires_at
 */
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

    /**
     * Niveau d'alerte de la licence : null (sans date d'expiration), info
     * (> 60 jours), attention (≤ 60 jours), urgent (≤ 7 jours) ou expire.
     * Mêmes seuils que le bandeau de compte à rebours.
     */
    public function niveauLicence(): ?string
    {
        if ($this->license_expires_at === null) {
            return null;
        }

        $secondesRestantes = now()->diffInSeconds($this->license_expires_at, false);

        return match (true) {
            $secondesRestantes <= 0 => 'expire',
            $secondesRestantes <= 7 * 86400 => 'urgent',
            $secondesRestantes <= 60 * 86400 => 'attention',
            default => 'info',
        };
    }

    /**
     * État lisible de la licence : valide, expire_bientot (≤ 60 jours),
     * expiree ou sans_date.
     */
    public function etatLicence(): string
    {
        return match ($this->niveauLicence()) {
            null => 'sans_date',
            'expire' => 'expiree',
            'urgent', 'attention' => 'expire_bientot',
            default => 'valide',
        };
    }
}
