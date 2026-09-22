<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mode d'emploi — Temple Shift Management</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin-top: 28px; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 2px solid #4f46e5; color: #4338ca; page-break-before: always; }
        h2.no-break { page-break-before: auto; }
        h3 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; color: #111827; }
        p { margin: 4px 0; }
        p.meta { color: #6b7280; margin-top: 0; }
        ul, ol { margin: 4px 0 8px 0; padding-left: 20px; }
        li { margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #d1d5db; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f3f4f6; }
        .cover { text-align: center; margin-top: 120px; }
        .cover h1 { font-size: 30px; }
        .cover p { color: #6b7280; font-size: 14px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #eef2ff; color: #4338ca; font-size: 11px; }
        .note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 10px; margin: 8px 0; }
        .toc ol { padding-left: 18px; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>Temple Shift Management</h1>
        <p>Mode d'emploi de l'application</p>
        <p class="meta">Généré le {{ $genereLe }}</p>
    </div>

    <h2 class="no-break">Sommaire</h2>
    <div class="toc">
        <ol>
            <li>Connexion</li>
            <li>Tableau de bord</li>
            <li>Serviteurs</li>
            <li>Modèles de Shift</li>
            <li>Shifts</li>
            <li>Changement (relèves, permutations, appels)</li>
            <li>Recrutement</li>
            <li>Rapports</li>
            <li>Paramètres</li>
        </ol>
    </div>

    <h2>1. Connexion</h2>
    <p>L'accès à l'application se fait via la page de connexion, avec l'email et le mot de passe fournis par l'administrateur.</p>
    <p>Selon le rôle du compte, les menus disponibles diffèrent :</p>
    <ul>
        <li><span class="badge">Administrateur</span> / <span class="badge">Super Administrateur</span> : accès à tous les modules (Shifts, Serviteurs, Modèles de Shift, Recrutement, Changement, Rapports, Paramètres).</li>
        <li><span class="badge">Coordonnateur d'équipe</span> : accès au tableau de bord, au Recrutement et au Changement, limités aux Shifts qu'il gère ; consultation en lecture seule des autres Shifts via « Mon Shift ».</li>
        <li><span class="badge">Serviteur</span> (compte de connexion facultatif) : accès à son propre espace — ses affectations et sa fiche personnelle uniquement.</li>
    </ul>

    <h2>2. Tableau de bord</h2>
    <p>Le contenu du tableau de bord dépend du rôle du compte connecté.</p>

    <h3>2.1 Administrateur</h3>
    <ul>
        <li><strong>Shifts</strong> : liens directs vers chaque fiche de Shift, regroupés Frères / Sœurs, avec le nombre de postes vacants.</li>
        <li><strong>Demandes de relève, de permutation et d'appel</strong> : les 5 dernières de chaque type, avec un lien « Afficher tout » vers le module Changement et un bouton <strong>Valider</strong> pour saisir directement le résultat sans quitter le tableau de bord.</li>
        <li><strong>Besoins en recrutement</strong> : total de Sœurs recherchées / Frères recherchés (2 prochains mois), avec un lien vers le détail par Shift.</li>
    </ul>

    <h3>2.2 Coordonnateur d'équipe</h3>
    <p>Même structure que la vue administrateur, mais filtrée aux Shifts qu'il gère (relèves, permutations, appels, besoins de recrutement).</p>

    <h3>2.3 Serviteur</h3>
    <p>Liste de ses propres affectations actives (poste, Shift, jour, horaire, date de début).</p>

    <h2>3. Serviteurs</h2>
    <p>Le <strong>Serviteur</strong> est l'entité centrale de l'application : c'est la personne qui sert dans le Temple, indépendamment du fait qu'elle dispose ou non d'un compte de connexion.</p>

    <h3>3.1 Créer un serviteur</h3>
    <p>Menu <strong>Serviteurs → + Ajouter un Serviteur</strong>. Renseigner nom, prénom, genre, téléphone(s), pieu et adresse (facultatifs sauf nom/prénom). Un serviteur peut aussi être créé à la volée directement depuis la fiche d'un Shift, en lui attribuant un rôle dans le même geste (voir section 5.2).</p>
    <p class="note">À la création, le serviteur reçoit automatiquement le statut <strong>Recommandé</strong> et son parcours d'intégration démarre : toutes les étapes définies dans Paramètres → Étapes du parcours lui sont attribuées, la première passant à « En cours ».</p>

    <h3>3.2 Statuts d'un serviteur</h3>
    <table>
        <tr><th>Statut</th><th>Signification</th></tr>
        <tr><td>Recommandé</td><td>Vient d'être proposé, parcours d'intégration en cours.</td></tr>
        <tr><td>En formation</td><td>En cours de préparation avant service actif.</td></tr>
        <tr><td>Actif</td><td>Peut être affecté à un poste dans un Shift.</td></tr>
        <tr><td>Relevé</td><td>Temporairement retiré du service.</td></tr>
        <tr><td>Retiré</td><td>Ne sert plus (fin de service, déménagement, etc.).</td></tr>
    </table>
    <p>Seuls les serviteurs au statut <strong>Actif</strong> apparaissent dans les listes d'affectation à un poste, filtrées en plus par genre compatible avec le Shift concerné.</p>

    <h3>3.3 Fiche d'un serviteur (onglets)</h3>
    <ul>
        <li><strong>Informations</strong> : coordonnées et informations personnelles.</li>
        <li><strong>Situation</strong> : statut actuel.</li>
        <li><strong>Parcours</strong> : liste des étapes d'intégration, chacune modifiable (statut : en attente / en cours / terminé / ignoré, date, commentaire). La personne qui enregistre une étape est automatiquement notée comme responsable.</li>
        <li><strong>Historique</strong> : liste de tous les postes occupés dans le temps, avec dates de début/fin.</li>
        <li><strong>Compte</strong> : création ou révocation d'un compte de connexion associé (email/mot de passe), pour que le serviteur consulte lui-même ses affectations.</li>
        <li><strong>Confidentialité</strong> : export des données personnelles (RGPD) et anonymisation définitive de la fiche par un administrateur.</li>
    </ul>

    <h2>4. Modèles de Shift</h2>
    <p>Un <strong>modèle de Shift</strong> définit une liste de postes types (ex : Coordonnateur, Coordonnateur Adjoint, Servant/Servante...) qui seront automatiquement créés à chaque nouveau Shift basé sur ce modèle. Cela évite de recréer manuellement les mêmes postes à chaque fois et garantit une structure identique pour tous les Shifts.</p>

    <h3>4.1 Créer un modèle</h3>
    <p>Menu <strong>Modèles de Shift → + Créer un modèle</strong>, avec un nom (ex : « Temple Standard ») et une description facultative.</p>

    <h3>4.2 Gérer les postes d'un modèle</h3>
    <p>Depuis la fiche du modèle, ajouter un poste en tapant simplement son nom. Chaque poste peut être modifié, réordonné (glisser-déposer ou flèches haut/bas) ou retiré individuellement.</p>
    <p class="note">Retirer ou ajouter un poste sur le modèle n'affecte que les futurs Shifts créés à partir de ce modèle — les Shifts déjà créés ne sont pas modifiés rétroactivement. Certains postes (Coordonnatrice, Servante, Scelleur…) sont automatiquement filtrés selon le genre du Shift lors de la proposition d'affectation.</p>

    <h2>5. Shifts</h2>
    <p>Un <strong>Shift</strong> est un créneau de service concret (ex : « Mardi Matin Frères »), rattaché à un jour et un horaire. Le genre attendu des serviteurs qui y sont affectés se déduit automatiquement de son nom (présence de « Sœurs » ou non).</p>

    <h3>5.1 Créer un Shift</h3>
    <p>Menu <strong>Shifts → + Créer un Shift</strong>. Renseigner le nom, le jour, l'heure de début/fin (ou choisir un <strong>Horaire</strong> prédéfini dans Paramètres pour préremplir automatiquement les heures), puis choisir un <strong>modèle de Shift</strong>.</p>
    <p class="note">En sélectionnant un modèle, tous ses postes sont générés automatiquement dans le nouveau Shift — il ne reste plus qu'à les pourvoir.</p>

    <h3>5.2 Rôles du Shift</h3>
    <p>Sur la fiche d'un Shift, la section « Rôles du Shift » liste chaque poste avec son titulaire et sa date d'affectation, ou la mention <em>« Rôle vacant »</em>. Un champ de recherche permet de filtrer rapidement la liste par rôle ou par nom de titulaire ; la colonne « Rôle » reste visible pendant le défilement horizontal du tableau sur petit écran.</p>
    <p>Le bouton <strong>+ Ajouter un serviteur</strong> ouvre un formulaire à deux champs :</p>
    <ul>
        <li><strong>Serviteur</strong> : recherche par nom parmi les serviteurs actifs de genre compatible avec le Shift. Un serviteur déjà affecté à un autre poste de ce même Shift apparaît aussi dans la liste (avec la mention de son rôle actuel) : le sélectionner déplace son affectation vers le nouveau rôle. Si aucun résultat ne correspond, l'option « + Créer … comme nouveau serviteur » permet de le créer à la volée sans quitter la page.</li>
        <li><strong>Rôle</strong> : le poste du modèle à pourvoir (les rôles uniques déjà occupés sur ce Shift, ex. Coordonnateur, n'apparaissent plus dans la liste).</li>
    </ul>
    <p>Le bouton <strong>Retirer</strong> met fin à l'affectation d'un titulaire (l'historique est conservé, pas supprimé) ; le poste devenu vacant n'est jamais laissé affiché indéfiniment, il peut être supprimé via le bouton <strong>Supprimer</strong> tant qu'il est vacant.</p>

    <h2>6. Changement (relèves, permutations, appels)</h2>
    <p>Le menu <strong>Changement</strong> regroupe les trois types de demandes qui font bouger un serviteur d'un poste :</p>
    <table>
        <tr><th>Type</th><th>Usage</th></tr>
        <tr><td>Relève</td><td>Le serviteur quitte définitivement son poste sur ce Shift ; le poste redevient vacant.</td></tr>
        <tr><td>Permutation</td><td>Le serviteur passe d'un Shift à un autre. Nécessite la validation des deux coordonnateurs d'équipe (origine et destination) avant que l'administrateur puisse saisir la décision finale.</td></tr>
        <tr><td>Appel</td><td>Rappel d'un titulaire déjà en poste (ex. reconduction, changement de rôle sur le même Shift).</td></tr>
    </table>
    <p>Chaque demande passe par les statuts <strong>En attente</strong> puis <strong>Traitée</strong> (avec résultat et date saisis par l'administrateur). Le champ « Approuvé par les deux Shifts » n'apparaît que pour une permutation. La page <strong>Serviteurs relevés</strong> (lien en haut du module) conserve l'historique des relèves traitées.</p>

    <h2>7. Recrutement</h2>
    <p>Menu <strong>Recrutement</strong>, accessible aux administrateurs et coordonnateurs d'équipe (limités à leurs Shifts) : pour chaque Shift, saisir le nombre de serviteurs à recruter, une échéance cible facultative et des notes. Le total à recruter s'affiche en résumé sur cette page et sur le tableau de bord.</p>

    <h2>8. Rapports</h2>
    <p>Menu <strong>Rapports</strong>, accessible aux administrateurs :</p>
    <ul>
        <li><strong>Serviteurs par statut</strong> : répartition en un coup d'œil.</li>
        <li><strong>Taux de remplissage des Shifts</strong> : pourcentage de postes pourvus par Shift, filtrable par jour.</li>
        <li><strong>Avancement du parcours de formation</strong> : pourcentage d'étapes terminées, tous serviteurs confondus.</li>
        <li><strong>Export CSV des Serviteurs</strong> (ouvrable dans Excel) et <strong>export PDF du remplissage des Shifts</strong>.</li>
    </ul>

    <h2>9. Paramètres</h2>
    <p>Le menu <strong>Paramètres</strong> centralise la configuration :</p>
    <ul>
        <li><strong>Pieux</strong> : liste des pieux utilisables dans la fiche d'un serviteur (ajout, renommage, suppression).</li>
        <li><strong>Horaires</strong> : créneaux horaires réutilisables (nom, heure de début/fin) pour préremplir rapidement la création d'un Shift.</li>
        <li><strong>Rôles</strong> : modification du nom et de la description des rôles d'accès existants. Les rôles porteurs de permissions codées (Conseil du Temple, Super Administrateur, Coordonnateur d'équipe) sont protégés : ni renommables (identifiant technique), ni supprimables.</li>
        <li><strong>Utilisateurs</strong> : voir qui détient quel rôle, créer un compte, changer un rôle ou suspendre un accès.</li>
        <li><strong>Étapes du parcours</strong> : gestion des étapes du parcours d'intégration appliquées à chaque nouveau serviteur (ajout, renommage, réordonnancement, suppression).</li>
        <li><strong>Journal d'activité</strong> : historique des créations, modifications et suppressions effectuées dans l'application.</li>
    </ul>

    <div class="note" style="margin-top: 30px;">
        Ce document est généré automatiquement depuis l'application et reflète son fonctionnement au moment de la génération. En cas d'évolution de l'application, régénérez-le pour obtenir une version à jour.
    </div>
</body>
</html>
