<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieu extends Model
{
    protected $table = 'pieux';

    protected $fillable = ['organisation_id', 'nom', 'type', 'parent_id'];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function servants(): HasMany
    {
        return $this->hasMany(Servant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Pieu::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Pieu::class, 'parent_id');
    }

    /**
     * "Pieu de Cocody (District d'Abidjan Nord, Mission d'Abidjan)" — chemin
     * complet utile partout où l'unité est affichée hors de son propre écran
     * de gestion (fiche servant, exports…).
     */
    public function cheminComplet(): string
    {
        $parties = [$this->nom];
        $courant = $this->parent;

        while ($courant) {
            $parties[] = $courant->nom;
            $courant = $courant->parent;
        }

        return implode(' — ', $parties);
    }
}
