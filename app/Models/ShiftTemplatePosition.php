<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ShiftTemplatePosition extends Model
{
    public const GENRE_FRERES = 'freres';

    public const GENRE_SOEURS = 'soeurs';

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

    /**
     * Genre d'un poste déduit de son nom : "soeurs" pour les variantes
     * féminines (Coordonnatrice…, Servante), "freres" pour les variantes
     * masculines et pour le Scelleur (ordonnance exclusivement masculine),
     * null pour un poste mixte ou personnalisé.
     */
    public static function genreDuNom(string $nom): ?string
    {
        return match (true) {
            str_contains($nom, 'Coordonnatrice') || $nom === 'Servante' => self::GENRE_SOEURS,
            str_contains($nom, 'Coordonnateur') || $nom === 'Servant' => self::GENRE_FRERES,
            $nom === 'Scelleur' => self::GENRE_FRERES,
            default => null,
        };
    }

    /**
     * Nom du poste masculin correspondant à un poste féminin
     * ("Coordonnatrice Adjointe de la formation" → "Coordonnateur Adjoint de
     * la formation", "Servante" → "Servant"), null pour tout autre poste.
     */
    public static function nomMasculinCorrespondant(string $nom): ?string
    {
        if (self::genreDuNom($nom) !== self::GENRE_SOEURS) {
            return null;
        }

        if ($nom === 'Servante') {
            return 'Servant';
        }

        return str_replace(['Coordonnatrice', 'Adjointe'], ['Coordonnateur', 'Adjoint'], $nom);
    }

    /**
     * Regroupe les postes d'un modèle en blocs indissociables, dans l'ordre
     * d'affichage : un poste masculin et son pendant féminin forment un bloc
     * [homme, femme] (la femme toujours juste en dessous), tout autre poste
     * (Scelleur, poste personnalisé, variante sans pendant) forme un bloc
     * seul. Les blocs suivent le rang `ordre` (puis l'id) de leur premier
     * poste.
     *
     * @param  Collection<int, ShiftTemplatePosition>  $positions
     * @return Collection<int, Collection<int, ShiftTemplatePosition>>
     */
    public static function enBlocs(Collection $positions): Collection
    {
        $triees = $positions->sortBy([['ordre', 'asc'], ['id', 'asc']])->values();

        // Associe chaque poste féminin au premier poste masculin homonyme
        // encore libre (un homme n'a qu'une seule femme associée).
        $femmeParHomme = [];
        $femmesAssociees = [];
        foreach ($triees as $femme) {
            $nomMasculin = self::nomMasculinCorrespondant($femme->nom);
            if ($nomMasculin === null) {
                continue;
            }

            $homme = $triees->first(fn (self $p) => $p->nom === $nomMasculin && ! isset($femmeParHomme[$p->id]));
            if ($homme !== null) {
                $femmeParHomme[$homme->id] = $femme;
                $femmesAssociees[$femme->id] = true;
            }
        }

        return $triees
            ->reject(fn (self $p) => isset($femmesAssociees[$p->id]))
            ->map(fn (self $p) => isset($femmeParHomme[$p->id]) ? collect([$p, $femmeParHomme[$p->id]]) : collect([$p]))
            ->values();
    }
}
