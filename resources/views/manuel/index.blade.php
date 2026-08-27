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
            <li>Servants</li>
            <li>Modèles de Shift</li>
            <li>Shifts</li>
            <li>Gouvernance</li>
            <li>Rapports</li>
            <li>Paramètres</li>
        </ol>
    </div>

    <h2>1. Connexion</h2>
    <p>L'accès à l'application se fait via la page de connexion, avec l'email et le mot de passe fournis par l'administrateur.</p>
    <p>Selon le rôle du compte, les menus disponibles diffèrent :</p>
    <ul>
        <li><span class="badge">Administrateur</span> / <span class="badge">Super Administrateur</span> : accès à tous les modules (Servants, Shifts, Modèles de Shift, Gouvernance, Rapports, Paramètres).</li>
        <li><span class="badge">Membre</span> : accès à son propre espace personnel.</li>
    </ul>

    <h2>2. Tableau de bord</h2>
    <p>Le tableau de bord administrateur donne une vue d'ensemble immédiate :</p>
    <ul>
        <li><strong>Servants</strong> : nombre de servants Actifs, En formation, En attente (recommandés) et Suspendus.</li>
        <li><strong>Shifts actifs</strong> : nombre total de Shifts en cours d'utilisation.</li>
        <li><strong>Composition de chaque Shift</strong> : pour chaque Shift, la liste de ses postes avec, pour chacun, soit le nom du servant titulaire, soit la mention <em>« Personne manquante »</em> si le poste est vacant.</li>
        <li><strong>Affectation directe</strong> : sur un poste vacant, un sélecteur de servant et un bouton <em>Affecter</em> permettent de combler le manque sans quitter le tableau de bord.</li>
        <li><strong>Demandes en attente</strong> : nombre d'avis et de demandes de retrait non encore traités (voir section Gouvernance).</li>
    </ul>

    <h2>3. Servants</h2>
    <p>Le <strong>Servant</strong> est l'entité centrale de l'application : c'est la personne qui sert dans le Temple, indépendamment du fait qu'elle dispose ou non d'un compte de connexion.</p>

    <h3>3.1 Créer un Servant</h3>
    <p>Menu <strong>Servants → + Ajouter un Servant</strong>. Renseigner nom, prénom, genre, téléphone(s), pieu et adresse (facultatifs sauf nom/prénom).</p>
    <p class="note">À la création, le Servant reçoit automatiquement le statut <strong>Recommandé</strong> et son parcours d'intégration démarre : toutes les étapes définies dans Paramètres → Étapes du parcours lui sont attribuées, la première passant à « En cours ».</p>

    <h3>3.2 Statuts d'un Servant</h3>
    <table>
        <tr><th>Statut</th><th>Signification</th></tr>
        <tr><td>Recommandé</td><td>Vient d'être proposé, parcours d'intégration en cours.</td></tr>
        <tr><td>En formation</td><td>En cours de préparation avant service actif.</td></tr>
        <tr><td>Actif</td><td>Peut être affecté à un poste dans un Shift.</td></tr>
        <tr><td>Suspendu</td><td>Temporairement retiré du service.</td></tr>
        <tr><td>Retiré</td><td>Ne sert plus (fin de service, déménagement, etc.).</td></tr>
    </table>
    <p>Seuls les Servants au statut <strong>Actif</strong> apparaissent dans les listes d'affectation à un poste.</p>

    <h3>3.3 Fiche d'un Servant (onglets)</h3>
    <ul>
        <li><strong>Informations</strong> : coordonnées et informations personnelles.</li>
        <li><strong>Situation</strong> : statut actuel.</li>
        <li><strong>Parcours</strong> : liste des étapes d'intégration, chacune modifiable (statut : en attente / en cours / terminé / ignoré, date, commentaire). La personne qui enregistre une étape est automatiquement notée comme responsable.</li>
        <li><strong>Historique</strong> : liste de tous les postes occupés dans le temps, avec dates de début/fin.</li>
    </ul>

    <h2>4. Modèles de Shift</h2>
    <p>Un <strong>modèle de Shift</strong> définit une liste de postes types (ex : Présidence, Matronne, Greffier...) qui seront automatiquement créés à chaque nouveau Shift basé sur ce modèle. Cela évite de recréer manuellement les mêmes postes à chaque fois et garantit une structure identique pour tous les Shifts.</p>

    <h3>4.1 Créer un modèle</h3>
    <p>Menu <strong>Modèles de Shift → + Créer un modèle</strong>, avec un nom (ex : « Temple Standard ») et une description facultative.</p>

    <h3>4.2 Gérer les postes d'un modèle</h3>
    <p>Depuis la fiche du modèle, ajouter un poste en tapant simplement son nom (ex : « Coordinateur Adjoint des OP »). Chaque poste peut être retiré individuellement.</p>
    <p class="note">Retirer ou ajouter un poste sur le modèle n'affecte que les futurs Shifts créés à partir de ce modèle — les Shifts déjà créés ne sont pas modifiés rétroactivement.</p>

    <h2>5. Shifts</h2>
    <p>Un <strong>Shift</strong> est un créneau de service concret (ex : « Mardi Matin »), rattaché à un jour et un horaire.</p>

    <h3>5.1 Créer un Shift</h3>
    <p>Menu <strong>Shifts → + Créer un Shift</strong>. Renseigner le nom, le jour, l'heure de début/fin (ou choisir un <strong>Horaire</strong> prédéfini dans Paramètres pour préremplir automatiquement les heures), puis choisir un <strong>modèle de Shift</strong>.</p>
    <p class="note">En sélectionnant un modèle, tous ses postes sont générés automatiquement dans le nouveau Shift — il ne reste plus qu'à les pourvoir.</p>

    <h3>5.2 Postes du Shift</h3>
    <p>Sur la fiche d'un Shift, la section « Postes du Shift » liste chaque poste avec :</p>
    <ul>
        <li>Le nom du servant titulaire et sa date d'affectation, ou la mention <em>« Poste vacant »</em>.</li>
        <li>Un bouton <strong>Affecter</strong> (avec sélecteur de servant actif) pour pourvoir un poste vacant.</li>
        <li>Un bouton <strong>Retirer</strong> pour mettre fin à l'affectation d'un titulaire (l'historique est conservé, pas supprimé).</li>
    </ul>
    <p>Le bouton <strong>+ Ajouter un poste</strong> permet d'ajouter un poste supplémentaire propre à ce Shift uniquement (par exemple, un second poste « Servant » un jour où il y a plus de volontaires que de postes prévus par le modèle). Un poste ajouté ainsi peut être retiré tant qu'il est vacant via le bouton <strong>Supprimer</strong>.</p>

    <h2>6. Gouvernance</h2>
    <p>Ce module gère les décisions administratives concernant un Servant : avis, demandes de retrait, ou autres demandes.</p>

    <h3>6.1 Créer une demande</h3>
    <p>Menu <strong>Gouvernance → + Nouvelle demande</strong> : choisir le Servant concerné, le type (Avis / Retrait / Autre) et décrire le motif.</p>

    <h3>6.2 Valider ou rejeter</h3>
    <p>Chaque demande en attente peut être <strong>Validée</strong> ou <strong>Rejetée</strong>, avec un commentaire de décision facultatif.</p>
    <p class="note">Valider une demande de type <strong>Retrait</strong> met automatiquement à jour le statut du Servant concerné à « Retiré ».</p>

    <h2>7. Rapports</h2>
    <p>Menu <strong>Rapports</strong>, accessible aux administrateurs :</p>
    <ul>
        <li><strong>Servants par statut</strong> : répartition en un coup d'œil.</li>
        <li><strong>Taux de remplissage des Shifts</strong> : pourcentage de postes pourvus par Shift.</li>
        <li><strong>Avancement du parcours de formation</strong> : pourcentage d'étapes terminées, tous Servants confondus.</li>
        <li><strong>Export CSV des Servants</strong> (ouvrable dans Excel) et <strong>export PDF du remplissage des Shifts</strong>.</li>
    </ul>

    <h2>8. Paramètres</h2>
    <p>Le menu <strong>Paramètres</strong> centralise la configuration :</p>
    <ul>
        <li><strong>Pieux</strong> : liste des pieux utilisables dans la fiche d'un Servant (ajout, renommage, suppression).</li>
        <li><strong>Horaires</strong> : créneaux horaires réutilisables (nom, heure de début/fin) pour préremplir rapidement la création d'un Shift.</li>
        <li><strong>Rôles</strong> : modification du nom et de la description des rôles d'accès existants.</li>
        <li><strong>Étapes du parcours</strong> : gestion des étapes du parcours d'intégration appliquées à chaque nouveau Servant (ajout, renommage, réordonnancement, suppression).</li>
    </ul>

    <div class="note" style="margin-top: 30px;">
        Ce document est généré automatiquement depuis l'application et reflète son fonctionnement au moment de la génération. En cas d'évolution de l'application, régénérez-le pour obtenir une version à jour.
    </div>
</body>
</html>
