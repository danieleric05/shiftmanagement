<?php

namespace App\Support;

/**
 * Captures d'écran (données fictives) intégrées au mode d'emploi PDF.
 * Un fichier manquant n'est jamais bloquant : la figure est simplement omise.
 */
class ManuelFigures
{
    /** Ordre d'apparition dans le document = numéro de figure. */
    public const LISTE = [
        '01-connexion' => 'Page de connexion',
        '26-utilisateurs-mobile' => 'Un tableau sur téléphone : affichage en cartes (largeur 390 px)',
        '02-tableau-bord-conseil' => 'Tableau de bord du Conseil du Temple, avec le bandeau de licence',
        '23-tableau-bord-coordonnateur' => 'Tableau de bord du Coordonnateur',
        '24-servants-secretaire' => 'Liste des servant(e)s vue par la Secrétaire',
        '25-autres-tableau-bord' => 'Tableau de bord du rôle « Autres » (lecture seule)',
        '03-servants-liste' => 'Liste des servant(e)s : compteurs, recherche, filtres et tri des colonnes',
        '05-recommandes' => 'Vue « Recommandés »',
        '04-servant-situation' => 'Fiche d’un servant(e), onglet Situation : changement de statut',
        '11-releves' => 'Servant(e)s relevé(e)s et bouton Réintégrer',
        '08-modele-shift' => 'Un modèle de Shift et ses postes',
        '06-shifts-liste' => 'Liste des Shifts',
        '07-shift-fiche' => 'Fiche d’un Shift : postes et titulaires',
        '09-changement-liste' => 'Page Changement : relèves, permutations et appels',
        '10-changement-detail' => 'Détails d’une permutation avec la frise de suivi',
        '12-recrutement' => 'Besoins de recrutement par Shift',
        '13-rapports' => 'Rapports',
        '14-parametres' => 'Accueil des Paramètres',
        '15-utilisateurs' => 'Paramètres : Utilisateurs',
        '16-utilisateurs-modification' => 'Modifier un compte, avec la case « Accès au compte suspendu »',
        '17-roles' => 'Paramètres : Rôles',
        '18-pieux' => 'Paramètres : Pieux',
        '19-horaires' => 'Paramètres : Horaires',
        '20-parcours' => 'Paramètres : Étapes du parcours',
        '21-journal' => 'Paramètres : Journal d’activité',
        '22-licence' => 'Paramètres : Licence',
    ];

    /** @return array<string, array{n: int, legende: string, src: ?string}> */
    public static function charger(?string $dossier = null): array
    {
        $dossier ??= resource_path('manuel/images');
        $figures = [];
        $n = 0;

        foreach (self::LISTE as $cle => $legende) {
            $n++;
            $fichier = $dossier.'/'.$cle.'.jpg';
            $contenu = is_file($fichier) ? @file_get_contents($fichier) : false;

            $figures[$cle] = [
                'n' => $n,
                'legende' => $legende,
                'src' => $contenu === false ? null : 'data:image/jpeg;base64,'.base64_encode($contenu),
            ];
        }

        return $figures;
    }
}
