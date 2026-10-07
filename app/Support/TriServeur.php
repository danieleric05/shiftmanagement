<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Tri côté serveur des listes paginées (paramètres d'URL `tri` et `sens`).
 *
 * Seules les clés déclarées dans la liste blanche `$colonnes` sont acceptées :
 * le nom de colonne SQL n'est jamais lu depuis la requête, ce qui exclut toute
 * injection. Convention de l'application (identique aux filtres) : une clé
 * inconnue ou un sens invalide sont simplement ignorés et l'ordre par défaut
 * s'applique (pas d'erreur 422). Un départage final sur la clé primaire rend
 * l'ordre stable d'une page à l'autre de la pagination.
 *
 * Exemple :
 *     $tri = TriServeur::depuisRequete($request, [
 *         'nom' => 'nom',
 *         'role' => fn ($q, string $sens) => $q->orderBy(Role::select('nom')->whereColumn(...), $sens),
 *     ], parDefaut: [['name', 'asc']]);
 *     $tri->appliquer($query);
 *     // Props Inertia : 'tri' => $tri->versProps()
 */
final class TriServeur
{
    public const SENS = ['asc', 'desc'];

    /**
     * @param  array<string, string|Closure(QueryBuilder, string): mixed>  $colonnes  clé publique => colonne SQL ou closure
     * @param  list<array{0: string, 1: string}>  $parDefaut  ordre appliqué quand aucun tri valide n'est demandé
     */
    private function __construct(
        public readonly ?string $cle,
        public readonly string $sens,
        private readonly array $colonnes,
        private readonly array $parDefaut,
        private readonly string $clePrimaire,
    ) {}

    /**
     * @param  array<string, string|Closure(QueryBuilder, string): mixed>  $colonnes
     * @param  list<array{0: string, 1: string}>  $parDefaut
     */
    public static function depuisRequete(Request $request, array $colonnes, array $parDefaut, string $clePrimaire = 'id'): self
    {
        $cle = $request->query('tri');
        $cle = is_string($cle) && array_key_exists($cle, $colonnes) ? $cle : null;

        $sens = $request->query('sens');
        $sens = is_string($sens) && in_array($sens, self::SENS, true) ? $sens : 'asc';

        return new self($cle, $cle === null ? 'asc' : $sens, $colonnes, $parDefaut, $clePrimaire);
    }

    /**
     * @template TBuilder of QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function appliquer(QueryBuilder $query): QueryBuilder
    {
        if ($this->cle !== null) {
            $colonne = $this->colonnes[$this->cle];

            if ($colonne instanceof Closure) {
                $colonne($query, $this->sens);
            } else {
                $query->orderBy($colonne, $this->sens);
            }
        }

        // Ordre par défaut : ordre principal sans tri demandé, puis départage
        // secondaire (ex. deux homonymes) quand un tri est demandé.
        foreach ($this->parDefaut as [$colonneDefaut, $sensDefaut]) {
            $query->orderBy($colonneDefaut, $sensDefaut);
        }

        return $query->orderBy($this->clePrimaire, 'asc');
    }

    /**
     * @return array{cle: string|null, sens: string}
     */
    public function versProps(): array
    {
        return ['cle' => $this->cle, 'sens' => $this->sens];
    }
}
