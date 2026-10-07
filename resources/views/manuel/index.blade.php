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
        h3 { page-break-after: avoid; font-size: 13px; margin-top: 14px; margin-bottom: 4px; color: #111827; }
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
        table.fig { margin: 8px 0 12px 0; page-break-inside: avoid; }
        table.fig td { border: 0; padding: 0; text-align: center; }
        table.fig img { width: 82%; border: 1px solid #d1d5db; }
        table.fig .legende { font-size: 10.5px; color: #6b7280; margin-top: 3px; }
    </style>
</head>
<body>
@php
    $fig = function (string $cle) use ($figures) {
        $f = $figures[$cle] ?? null;
        if (! $f || ! $f['src']) { return ''; }
        return '<table class="fig"><tr><td><img style="width: '.$f['largeur'].'%" src="'.$f['src'].'" alt="'.e($f['legende']).'"><div class="legende">Figure '.$f['n'].' — '.e($f['legende']).'</div></td></tr></table>';
    };
    $ref = function (string $cle) use ($figures) {
        $f = $figures[$cle] ?? null;
        return $f && $f['src'] ? ' (voir Figure '.$f['n'].')' : '';
    };
@endphp
    <div class="cover">
        <h1>Temple Shift Management</h1>
        <p>Mode d'emploi de l'application</p>
        <p class="meta">Généré le {{ $genereLe }}</p>
    </div>

    <h2 class="no-break">Sommaire</h2>
    <div class="toc">
        <ol>
            <li>Connexion, sécurité et rôles</li>
            <li>Tableau de bord</li>
            <li>Servant(e)s</li>
            <li>Modèles de Shift</li>
            <li>Shifts</li>
            <li>Changement (relèves, permutations, appels)</li>
            <li>Recrutement</li>
            <li>Rapports</li>
            <li>Paramètres</li>
        </ol>
    </div>

    <h2>1. Connexion, sécurité et rôles</h2>

    <h3>1.1 Se connecter</h3>
    <p>L'accès à l'application se fait via la page de connexion, avec l'email et le mot de passe fournis par le Conseil du Temple (ou le Super Administrateur). La page de connexion est utilisable sur téléphone : le champ mot de passe reste visible lorsque le clavier mobile s'ouvre.</p>
    {!! $fig('01-connexion') !!}
    <ul>
        <li><strong>Mot de passe temporaire</strong> : un compte créé depuis Paramètres → Utilisateurs reçoit un mot de passe temporaire. À la première connexion, l'application redirige vers la page <strong>Mon profil</strong> avec le message « Veuillez changer votre mot de passe temporaire avant de continuer » ; aucune autre page n'est accessible tant que le nouveau mot de passe n'est pas enregistré.</li>
        <li><strong>Mot de passe oublié</strong> : le lien « Mot de passe oublié ? » de la page de connexion envoie par e-mail un lien de réinitialisation. L'envoi part de l'adresse configurée sur le serveur (par exemple no-reply@daertech.ci lorsque la messagerie est configurée) ; sans messagerie configurée, aucun e-mail n'est envoyé.</li>
    </ul>

    <h3>1.2 Rôles et droits</h3>
    <p>Selon le rôle du compte, les menus et les actions disponibles diffèrent :</p>
    <ul>
        <li><span class="badge">Super Administrateur</span> : accès à tout, y compris la page <strong>Paramètres → Rôles</strong> qui lui est réservée, et la gestion des utilisateurs.</li>
        <li><span class="badge">Conseil du Temple</span> : gestion des Shifts, des Servant(e)s, des Modèles de Shift, du Recrutement, du Changement, des Rapports et des Paramètres, dont les utilisateurs de son organisation. N'a <strong>pas</strong> accès à la page Rôles.</li>
        <li><span class="badge">Coordonnateur</span> : rôle qui « gère des Shifts » (réglage actif d'office, voir Paramètres → Rôles) ; tableau de bord, Recrutement et Changement, limités aux Shifts qu'il gère. Dans le Changement, il ne traite que les <strong>permutations</strong> (ni relèves, ni appels) et valide celles qui concernent ses Shifts. Il consulte en lecture seule les autres Shifts via « Mon Shift » et peut modifier la fiche des servant(e)s affectés à ses Shifts (sauf le compte de connexion).</li>
        <li><span class="badge">Secrétaire</span> : liste, ajout et modification des servant(e)s, vue « Recommandés », gestion des relèves, des appels et des permutations jusqu'à la décision finale. Pas d'accès aux Paramètres, aux Rapports, aux Shifts ni au Recrutement. Elle ne valide pas une permutation à la place des coordonnateurs.</li>
        <li><span class="badge">Autres</span> : accès en lecture seule — consultation du tableau de bord, des Shifts, des Servant(e)s, des Recommandés, du Changement, du Recrutement et des Rapports, sans pouvoir rien modifier. Pas d'accès aux Paramètres. Dans son profil, il ne peut modifier que son mot de passe.</li>
        <li><span class="badge">Servant(e)</span> (compte de connexion facultatif) : accès à son propre espace — ses affectations et sa fiche personnelle uniquement.</li>
    </ul>

    <h3>1.3 Tableaux : tri, affichage et actions</h3>
    <p>Toutes les listes de l'application (utilisateurs, servant(e)s, Shifts, demandes de changement, recrutement, rapports, modèles, paramètres, journal d'activité, tableaux de bord) fonctionnent de la même façon :</p>
    {!! $fig('26-utilisateurs-mobile') !!}
    <ul>
        <li><strong>Affichage</strong> : les tableaux tiennent toujours dans la largeur de l'écran, sans défilement horizontal. Sur grand écran, les colonnes secondaires peuvent être regroupées sous la colonne principale (ex. « Statut : Nouveau ») quand la place manque. Sur téléphone et tablette (écran plus étroit qu'un ordinateur portable), chaque ligne devient une <strong>carte</strong> : libellé / valeur, actions en bas de la carte. Un texte trop long (e-mail…) est raccourci par « … » : survolez-le pour le lire en entier.</li>
        <li><strong>Trier</strong> : tous les tableaux se trient. Sur grand écran, cliquez sur le titre d'une colonne (flèche à côté du titre) ; un second clic inverse l'ordre (croissant ↔ décroissant). En affichage en cartes, utilisez le sélecteur <strong>Trier par</strong> au-dessus de la liste et le bouton <strong>Croissant / Décroissant</strong>. Sur les listes paginées (Servant(e)s, Paramètres → Utilisateurs, Shifts, Changement, Servant(e)s relevé(e)s, Journal d'activité), le tri s'applique à toute la liste, pas seulement à la page affichée, et il est conservé avec la recherche, les filtres et le changement de page.</li>
        <li><strong>Actions</strong> : l'action principale d'une ligne (Voir, Modifier, Traiter…) est un bouton visible ; les autres (Délier, Supprimer, Retirer…) sont regroupées dans le menu <strong>⋯</strong> (« Autres actions »). Au clavier : Entrée ou flèche bas pour l'ouvrir, flèches haut/bas (Début/Fin) pour choisir, Entrée pour valider, Échap pour fermer et revenir au bouton.</li>
        <li><strong>Fenêtres modales</strong> : modifier, traiter ou détailler une ligne ouvre une <strong>fenêtre</strong> au-dessus de la page, avec des champs de taille normale (il n'y a plus d'édition directement dans le tableau). Échap, un clic à l'extérieur ou <strong>Annuler</strong> ferme la fenêtre sans enregistrer, et le curseur revient sur le bouton d'origine. C'est le cas notamment de : <strong>Statuer</strong> sur une relève (tableau de bord), <strong>Affecter</strong> un servant(e) à un rôle vacant et <strong>Modifier les étapes</strong> d'une affectation (fiche d'un Shift, Mon Shift), <strong>Traiter</strong> / <strong>Détails</strong> d'une demande de changement, <strong>Besoin de recrutement</strong> d'un Shift, modification d'un poste de modèle, des <strong>Rôles</strong>, <strong>Pieux</strong>, <strong>Horaires</strong>, <strong>Étapes du parcours</strong>, du détail d'une activité du journal et des <strong>Licences</strong> (espace propriétaire).</li>
        <li><strong>Listes déroulantes avec recherche</strong> (choix d'un servant(e), d'un Shift…) : tapez pour filtrer, flèches haut/bas (Début/Fin) pour parcourir, Entrée pour choisir, Échap pour refermer la liste sans fermer la fenêtre.</li>
    </ul>

    <h2>2. Tableau de bord</h2>
    <p>Le contenu du tableau de bord dépend du rôle du compte connecté.</p>

    <h3>2.1 Conseil du Temple / Super Administrateur</h3>
    {!! $fig('02-tableau-bord-conseil') !!}
    <ul>
        <li><strong>Shifts</strong> : liens directs vers chaque fiche de Shift, regroupés Frères / Sœurs, avec le nombre de postes vacants.</li>
        <li><strong>Demandes de relève, de permutation et d'appel</strong> : les 5 dernières de chaque type, avec un lien « Afficher tout » vers le module Changement. Pour une relève, le bouton <strong>Statuer</strong> ouvre une fenêtre où saisir directement le résultat et sa date sans quitter le tableau de bord ; pour une permutation ou un appel, <strong>Statuer →</strong> (ou <strong>Suivre →</strong> tant que les coordonnateurs n'ont pas validé) mène à la demande dans le module Changement. Chaque liste se trie comme les autres tableaux (section 1.3).</li>
        <li><strong>Besoins en recrutement</strong> : total de Sœurs recherchées / Frères recherchés (2 prochains mois), avec un lien vers le détail par Shift.</li>
    </ul>

    <h3>2.2 Coordonnateur</h3>
    <p>Même structure que la vue du Conseil, mais filtrée aux Shifts qu'il gère : ses Shifts, les permutations qui les concernent et leurs besoins de recrutement (les relèves et les appels n'y figurent pas).</p>
    {!! $fig('23-tableau-bord-coordonnateur') !!}

    <h3>2.3 Secrétaire</h3>
    <p>La Secrétaire n'a pas de tableau de bord dédié : à la connexion, elle arrive directement sur la liste des Servant(e)s.</p>
    {!! $fig('24-servants-secretaire') !!}

    <h3>2.4 Autres</h3>
    <p>Même tableau de bord que le Conseil du Temple, en consultation seule : les boutons d'action (dont <strong>Statuer</strong>) n'y sont pas proposés.</p>
    {!! $fig('25-autres-tableau-bord') !!}

    <h3>2.5 Servant(e)</h3>
    <p>Liste de ses propres affectations actives (poste, Shift, jour, horaire, date de début).</p>

    <h2>3. Servant(e)s</h2>
    <p>Le <strong>Servant(e)</strong> est l'entité centrale de l'application : c'est la personne qui sert dans le Temple, indépendamment du fait qu'elle dispose ou non d'un compte de connexion.</p>
    {!! $fig('03-servants-liste') !!}

    <p>La liste <strong>Servant(e)s</strong> (et la vue « Recommandés ») est <strong>paginée à 30 par page</strong> : la recherche, les filtres (statut, pieu) et le tri (Nom, Prénom, Statut ou Pieu, voir section 1.3) s'appliquent à toute la liste, pas seulement à la page affichée, et sont conservés quand on change de page. Le Conseil du Temple et le Super Administrateur peuvent en plus, sur grand écran, réordonner les colonnes en glissant leurs titres (ou au clavier avec les flèches gauche/droite sur la poignée) ; l'ordre est mémorisé pour leur compte et le bouton <strong>Réinitialiser l'ordre</strong> revient à l'ordre par défaut.</p>

    <h3>3.1 Créer un servant(e)</h3>
    <p>Menu <strong>Servant(e)s → + Ajouter un Servant(e)</strong> (Conseil du Temple, Super Administrateur ou Secrétaire). Renseigner nom, prénom, genre, téléphone(s), pieu et adresse (facultatifs sauf nom/prénom). Le champ <strong>Pieu / District / Mission</strong> ne propose que des pieux (ceux définis dans Paramètres → Pieux). Un servant(e) peut aussi être créé à la volée directement depuis la fiche d'un Shift, en lui attribuant un rôle dans le même geste (voir section 5.2).</p>
    <p class="note">À la création, le servant(e) reçoit automatiquement le statut <strong>Recommandé</strong> et son parcours d'intégration démarre : toutes les étapes définies dans Paramètres → Étapes du parcours lui sont attribuées, la première passant à « En cours ».</p>

    <h3>3.2 Vue « Recommandés »</h3>
    <p>Le menu <strong>Recommandés</strong> affiche la liste des servant(e)s au statut <strong>Recommandé</strong>, c'est-à-dire les personnes récemment proposées, en attente d'intégration. Le statut « Nouveau » est un autre statut : les servant(e)s au statut « Nouveau » n'apparaissent <strong>pas</strong> dans cette vue, ils se retrouvent dans la liste Servant(e)s (filtre « Nouveau »).</p>
    {!! $fig('05-recommandes') !!}

    <h3>3.3 Statuts d'un servant(e)</h3>
    <table>
        <tr><th>Statut</th><th>Signification</th></tr>
        <tr><td>Recommandé</td><td>Vient d'être proposé, en attente d'intégration (vue « Recommandés »).</td></tr>
        <tr><td>Nouveau</td><td>A commencé à servir récemment.</td></tr>
        <tr><td>Ancien</td><td>N'est plus « nouveau » ; peut être affecté à un poste dans un Shift.</td></tr>
        <tr><td>Relevé</td><td>Temporairement retiré du service (géré par les relèves).</td></tr>
        <tr><td>Permutant</td><td>Ne sert plus (fin de service, déménagement, etc.) ; géré par les permutations.</td></tr>
    </table>
    <p><strong>Changer le statut</strong> : le Conseil du Temple et le Super Administrateur passent librement un servant(e) de Recommandé, Nouveau ou Ancien à l'un des deux autres statuts, dans les deux sens, depuis l'onglet <strong>Situation</strong> de la fiche (sélecteur « Changer le statut », avec confirmation) ou depuis le formulaire <strong>Modifier</strong>. Chaque changement est inscrit au journal d'activité (statut avant/après). La Secrétaire et le coordonnateur ne changent pas le statut. Un servant(e) relevé(e) ou permutant ne change pas de statut par ce sélecteur : il revient par la <strong>réintégration</strong> (section 3.5).</p>
    <p class="note">Le parcours d'intégration (étapes) reste un outil de suivi : il n'a <strong>aucun effet</strong> sur le statut. Un servant(e) peut passer « Ancien » même si toutes ses étapes ne sont pas terminées.</p>
    <p>Seuls les servant(e)s au statut <strong>Ancien</strong> apparaissent dans les listes d'affectation à un poste, filtrées en plus par genre compatible avec le Shift concerné.</p>

    <h3>3.4 Fiche d'un servant(e) (onglets)</h3>
    {!! $fig('04-servant-situation') !!}
    <ul>
        <li><strong>Informations</strong> : coordonnées et informations personnelles.</li>
        <li><strong>Situation</strong> : statut actuel et, pour le Conseil du Temple, sélecteur de changement de statut.</li>
        <li><strong>Parcours</strong> : liste des étapes d'intégration, chacune modifiable (statut : en attente / en cours / terminé / ignoré, date, commentaire). La personne qui enregistre une étape est automatiquement notée comme responsable.</li>
        <li><strong>Historique</strong> : liste de tous les postes occupés dans le temps, avec dates de début/fin.</li>
        <li><strong>Compte</strong> : création ou révocation d'un compte de connexion associé (email/mot de passe), pour que le servant(e) consulte lui-même ses affectations. Réservé au Conseil du Temple et au Super Administrateur.</li>
        <li><strong>Confidentialité</strong> : export des données personnelles (RGPD) et anonymisation définitive de la fiche, réservés au Conseil du Temple et au Super Administrateur.</li>
    </ul>

    <h3>3.5 Réintégrer un servant(e) relevé(e)</h3>
    <p>Un servant(e) relevé(e) (relève traitée ou statut « Relevé ») peut revenir : bouton <strong>Réintégrer</strong> dans l'onglet <strong>Situation</strong> de sa fiche ou sur la page <strong>Servant(e)s relevé(e)s</strong>. On peut, en option, le replacer directement sur un poste d'un Shift (genre et postes uniques respectés). Le servant(e) repasse au statut « Ancien », quel que soit l'avancement de son parcours (un servant(e) au statut « Permutant » revient aussi par ce bouton). La relève reste dans l'historique ; la réintégration est notée sur la fiche et dans le journal d'activité. Réservé au Conseil du Temple et au Super Administrateur.</p>
    {!! $fig('11-releves') !!}

    <h3>3.6 Supprimer définitivement un servant(e)</h3>
    <p>Pour corriger une erreur de saisie (ex. un membre du Conseil inscrit par erreur comme servant), l'onglet <strong>Confidentialité</strong> propose <strong>Supprimer définitivement</strong> : il faut taper le nom complet du servant(e) ou le mot <strong>SUPPRIMER</strong>. Sont effacés la fiche, la photo, les affectations, le parcours et l'historique de relèves/permutations/appels. <strong>Irréversible.</strong> Un compte de connexion lié à la fiche est conservé (seul le lien disparaît). Le journal garde une trace de l'action, sans données personnelles. Pour garder l'historique, préférer l'anonymisation. Réservé au Conseil du Temple et au Super Administrateur.</p>
    <p>La fiche peut être modifiée par le Conseil du Temple, le Super Administrateur, la Secrétaire, ainsi que par le coordonnateur d'un Shift où le servant(e) a une affectation active.</p>

    <h2>4. Modèles de Shift</h2>
    <p>Un <strong>modèle de Shift</strong> définit une liste de postes types (ex : Coordonnateur, Coordonnatrice, Coordonnateur Adjoint, Servant/Servante, Scelleur...) que l'on peut pourvoir dans les Shifts rattachés à ce modèle. Cela garantit une structure identique pour tous les Shifts.</p>

    <h3>4.1 Créer un modèle</h3>
    <p>Menu <strong>Modèles de Shift → + Créer un modèle</strong>, avec un nom (ex : « Temple Standard ») et une description facultative.</p>
    {!! $fig('08-modele-shift') !!}

    <h3>4.2 Gérer les postes d'un modèle</h3>
    <p>Depuis la fiche du modèle, ajouter un poste en tapant simplement son nom. Chaque poste peut être renommé (<strong>Modifier le nom</strong>, dans une fenêtre), réordonné (glisser-déposer ou flèches haut/bas) ou retiré (menu ⋯). La liste des postes garde l'ordre défini à la main (pas de tri par colonne) ; la liste des modèles, elle, se trie par Nom ou nombre de Postes.</p>
    <p>Un poste féminin se place toujours juste sous le poste masculin correspondant (Coordonnateur / Coordonnatrice, Coordonnateur Adjoint / Coordonnatrice Adjointe, Servant / Servante…) : les deux postes forment un bloc qui se déplace d'un seul tenant. Le poste <strong>Scelleur</strong> n'existe qu'au masculin et reste seul.</p>
    <p class="note">Un poste ajouté au modèle devient disponible à l'ajout sur les Shifts rattachés à ce modèle ; les postes déjà présents sur un Shift ne sont pas modifiés rétroactivement. Les postes féminins (Coordonnatrice, Servante…) et masculins (Coordonnateur, Servant, Scelleur…) sont automatiquement filtrés selon le genre du Shift lors de la proposition d'affectation.</p>

    <h2>5. Shifts</h2>
    <p>Un <strong>Shift</strong> est un créneau de service concret (ex : « Mardi Matin Frères »), rattaché à un jour et un horaire. Le genre attendu des servant(e)s qui y sont affectés se déduit automatiquement de son nom (présence de « Sœurs » ou non). La liste des Shifts est paginée (20 Shifts par page).</p>
    {!! $fig('06-shifts-liste') !!}
    {!! $fig('07-shift-fiche') !!}

    <h3>5.1 Modifier un Shift</h3>
    <p>L'organisation fonctionne avec un ensemble fixe de Shifts (créneaux Frères / Sœurs récurrents) : l'application ne permet pas d'en créer de nouveaux. Depuis la fiche d'un Shift, le bouton de modification permet d'ajuster son nom, son jour d'activité, ses heures de début/fin et son statut.</p>
    <p class="note">Les postes d'un Shift proviennent de son <strong>modèle de Shift</strong> : seuls les postes de ce modèle peuvent y être pourvus.</p>

    <h3>5.2 Rôles du Shift</h3>
    <p>Sur la fiche d'un Shift, la section « Rôles du Shift » liste chaque poste avec son titulaire et sa date d'affectation, ou la mention <em>« Rôle vacant »</em>. Un champ de recherche permet de filtrer rapidement la liste par rôle ou par nom de titulaire ; la liste se trie par Rôle, Titulaire, Appel et étapes (section 1.3) et s'affiche en cartes sur téléphone.</p>
    <p>Sur un rôle vacant, le bouton <strong>Affecter</strong> ouvre une fenêtre pour choisir le servant(e) (mêmes règles que ci-dessous). Sur un rôle pourvu, <strong>Modifier les étapes</strong> ouvre une fenêtre pour cocher Protection de l'enfance, Badge et Photo (également depuis <strong>Mon Shift</strong> pour le coordonnateur du Shift).</p>
    <p>Le bouton <strong>+ Ajouter un servant(e)</strong> ouvre un formulaire à deux champs :</p>
    <ul>
        <li><strong>Servant(e)</strong> : recherche par nom parmi les servant(e)s au statut Ancien de genre compatible avec le Shift. Un servant(e) déjà affecté à un autre poste de ce même Shift apparaît aussi dans la liste (avec la mention de son rôle actuel) : le sélectionner déplace son affectation vers le nouveau rôle. Si aucun résultat ne correspond, l'option « + Créer … comme nouveau servant(e) » permet de le créer à la volée sans quitter la page.</li>
        <li><strong>Rôle</strong> : le poste du modèle à pourvoir (les rôles uniques déjà occupés sur ce Shift, ex. Coordonnateur, n'apparaissent plus dans la liste).</li>
    </ul>
    <p>Le bouton <strong>Retirer</strong> (menu ⋯) met fin à l'affectation d'un titulaire (l'historique est conservé, pas supprimé) ; le poste devenu vacant n'est jamais laissé affiché indéfiniment, il peut être supprimé via <strong>Supprimer le rôle</strong> (menu ⋯) tant qu'il est vacant.</p>

    <h2>6. Changement (relèves, permutations, appels)</h2>
    <p>Le menu <strong>Changement</strong> regroupe les trois types de demandes qui font bouger un servant(e) d'un poste :</p>
    {!! $fig('09-changement-liste') !!}
    <table>
        <tr><th>Type</th><th>Usage</th><th>Gérée par</th></tr>
        <tr><td>Relève</td><td>Le servant(e) quitte définitivement son poste sur ce Shift ; le poste redevient vacant.</td><td>Conseil du Temple, Super Administrateur, Secrétaire</td></tr>
        <tr><td>Permutation</td><td>Le servant(e) passe d'un Shift à un autre (de genre compatible).</td><td>Conseil du Temple, Super Administrateur, Secrétaire ; validation par les coordonnateurs des deux Shifts</td></tr>
        <tr><td>Appel</td><td>Rappel d'un titulaire déjà en poste (ex. reconduction, changement de rôle sur le même Shift).</td><td>Conseil du Temple, Super Administrateur, Secrétaire</td></tr>
    </table>
    <p>Chaque demande passe par les statuts <strong>En attente</strong> puis <strong>Traitée</strong> (avec résultat et date saisis par le Conseil du Temple ou la Secrétaire). La page <strong>Servant(e)s relevé(e)s</strong> (lien en haut du module) conserve l'historique des relèves traitées.</p>
    <p>La liste des demandes (30 par page) se trie par Servant(e), Type, Shift, Date de demande ou Statut (par défaut : demandes les plus récentes en premier). Le bouton <strong>Traiter</strong> (demande en attente que vous pouvez faire avancer) ou <strong>Détails</strong> (sinon) ouvre une fenêtre avec le suivi complet, les validations des coordonnateurs et, le cas échéant, la saisie du résultat et de sa date. <strong>Nouvelle demande</strong> s'ouvre aussi dans une fenêtre ; <strong>Supprimer la demande</strong> se trouve dans le menu ⋯.</p>
    <p class="note">Retour d'un servant(e) relevé(e) : le Conseil du Temple utilise le bouton <strong>Réintégrer</strong> de cette page (voir section 3.5). La relève reste affichée, avec la mention « Réintégré(e) le … par … ». Une même personne peut être relevée puis réintégrée plusieurs fois.</p>

    <h3>6.1 Circuit d'une permutation</h3>
    <ol>
        <li><strong>Demande</strong> : le Conseil du Temple, le Super Administrateur ou la Secrétaire soumet la demande (servant(e), Shift d'origine, Shift de destination, motif). Les coordonnateurs des deux Shifts sont prévenus.</li>
        <li><strong>Validation des coordonnateurs</strong> : seuls les coordonnateurs des deux Shifts concernés — celui du Shift d'origine, puis celui du Shift de destination — se prononcent, chacun une seule fois, avec les boutons <strong>Valider</strong> ou <strong>Refuser</strong>. Un refus de l'un d'eux clôture immédiatement la demande.</li>
        <li><strong>Décision finale</strong> : une fois les deux validations obtenues, le Conseil du Temple (ou la Secrétaire) rend la décision finale en saisissant le résultat et sa date. Tant que les deux validations manquent, la décision est refusée par l'application.</li>
    </ol>
    <p class="note">Le Conseil du Temple, le Super Administrateur et la Secrétaire ne valident pas à la place d'un coordonnateur (sauf s'ils sont eux-mêmes coordonnateurs du Shift concerné).</p>

    <h3>6.2 Frise de suivi et états</h3>
    <p>Chaque permutation affiche une frise de suivi :</p>
    {!! $fig('10-changement-detail') !!}
    <ul>
        <li><strong>Initiée par</strong> : nom (et rôle) de l'auteur de la demande, avec sa date ;</li>
        <li><strong>Validation du coordonnateur du shift</strong> d'origine, puis de destination : « Validée » ou « Refusée », avec le nom du coordonnateur et la date, ou « En attente » ;</li>
        <li><strong>Validée par les deux coordonnateurs</strong> (lorsque c'est le cas), avec la date de la dernière validation ;</li>
        <li><strong>Décision du Conseil</strong> : favorable ou défavorable, résultat, auteur et date de la décision.</li>
    </ul>
    <p>Un badge résume l'état de la demande :</p>
    <table>
        <tr><th>État</th><th>Signification</th></tr>
        <tr><td>En attente du coordonnateur de …</td><td>Il manque la validation du coordonnateur du Shift indiqué (ou des deux Shifts).</td></tr>
        <tr><td>Prête pour décision du Conseil</td><td>Les deux coordonnateurs ont validé ; la décision finale peut être saisie.</td></tr>
        <tr><td>Tranchée</td><td>La décision finale a été rendue (favorable ou défavorable).</td></tr>
        <tr><td>Refusée par le coordonnateur de …</td><td>Un coordonnateur a refusé : la demande est clôturée.</td></tr>
    </table>

    <h2>7. Recrutement</h2>
    <p>Menu <strong>Recrutement</strong>, accessible au Conseil du Temple, au Super Administrateur et aux coordonnateurs (limités à leurs Shifts) : pour chaque Shift, le bouton <strong>Modifier</strong> ouvre la fenêtre <strong>Besoin de recrutement</strong> : nombre de servant(e)s à recruter, échéance cible facultative et notes. La liste se trie par Shift, À recruter, Échéance ou Notes. Le total à recruter s'affiche en résumé sur cette page et sur le tableau de bord.</p>
    {!! $fig('12-recrutement') !!}

    <h2>8. Rapports</h2>
    <p>Menu <strong>Rapports</strong>, accessible au Conseil du Temple et au Super Administrateur :</p>
    {!! $fig('13-rapports') !!}
    <ul>
        <li><strong>Servant(e)s par statut</strong> : répartition en un coup d'œil.</li>
        <li><strong>Taux de remplissage des Shifts</strong> : pourcentage de postes pourvus par Shift, filtrable par jour.</li>
        <li><strong>Avancement du parcours de formation</strong> : pourcentage d'étapes terminées, tous servant(e)s confondus.</li>
        <li><strong>Export CSV des Servant(e)s</strong> (ouvrable dans Excel) et <strong>export PDF du remplissage des Shifts</strong>.</li>
    </ul>

    <h2>9. Paramètres</h2>
    <p>Le menu <strong>Paramètres</strong> (Conseil du Temple et Super Administrateur) centralise la configuration :</p>
    {!! $fig('14-parametres') !!}
    <ul>
        <li><strong>Pieux</strong> : liste des pieux utilisables dans la fiche d'un servant(e) (ajout, suppression ; <strong>Modifier</strong> ouvre une fenêtre de renommage). Tri par Nom, Type, Rattaché(e) à ou Unités rattachées.</li>
        <li><strong>Horaires</strong> : créneaux horaires réutilisables (nom, heure de début/fin), modifiables dans une fenêtre ; tri par Nom, Début ou Fin.</li>
        <li><strong>Rôles</strong> (réservé au Super Administrateur) : création (<strong>Nouveau rôle</strong>) et modification dans une fenêtre, suppression (menu ⋯), et choix des rôles qui « gèrent des Shifts ». Le rôle <strong>Coordonnateur</strong> gère des Shifts d'office (y compris sur une installation neuve). Les rôles porteurs de permissions codées (Super Administrateur, Conseil du Temple, Coordonnateur, Secrétaire) sont protégés : ni renommables (identifiant technique), ni supprimables. Tri par Rôle, Clé technique, Type, Description ou Gère des shifts.</li>
        <li><strong>Utilisateurs</strong> : voir qui détient quel rôle, créer un compte (bouton <strong>Nouveau compte</strong>, avec mot de passe temporaire, voir section 1.1), changer un rôle, le statut ou l'accès d'un compte. La liste est paginée (30 utilisateurs par page) ; un champ de recherche filtre par nom ou par e-mail, et des listes filtrent par rôle, par statut et par accès. Toutes les colonnes se trient (Nom, Prénom, E-mail, Rôle, Statut, Accès, Shifts gérés, Lié à un servant(e)).
            <ul>
                <li><strong>Modifier</strong> (icône crayon) ouvre une fenêtre : nom, prénom, téléphone, rôle, statut et accès. L'e-mail y est affiché mais ne se modifie pas ici (la personne le change depuis « Mon profil »). Pour un coordonnateur, la même fenêtre permet d'ajouter ou de retirer ses <strong>Shifts gérés</strong> (enregistré immédiatement).</li>
                <li><strong>Délier du servant(e)</strong> (menu ⋯) : rompt le lien entre le compte de connexion et la fiche servant(e), après confirmation. <strong>Rien n'est supprimé</strong> : le compte et la fiche du servant(e) sont conservés, seule la colonne « Lié à un servant(e) » se vide. L'action est inscrite au journal d'activité. Mêmes droits que les autres actions sur les comptes.</li>
                <li><strong>Supprimer le compte</strong> (menu ⋯) : possible aussi pour un compte lié à un servant(e) ; la fiche du servant(e) est <strong>conservée</strong>, seul le compte de connexion est supprimé (le lien est rompu puis le compte supprimé, et la déliaison est journalisée). On ne peut pas supprimer son propre compte. Depuis la fiche du servant(e), l'onglet <strong>Compte</strong> permet toujours de révoquer (supprimer) le compte lié.</li>
                <li><strong>Statut</strong> du titulaire du compte : <strong>Recommandé</strong>, <strong>Nouveau</strong> ou <strong>Ancien</strong>, comme pour les servant(e)s. Le Conseil du Temple et le Super Administrateur le choisissent à la création ou via <strong>Modifier</strong>. Un compte existant ou créé sans précision est « Ancien ». Le statut n'a <strong>aucun effet</strong> sur la connexion.</li>
                <li><strong>Accès au compte suspendu</strong> (case à cocher, avec confirmation) : bloque le compte. La personne ne peut plus se connecter et, si elle était connectée, elle est déconnectée à sa requête suivante. Décocher la case rétablit l'accès. La colonne <strong>Accès</strong> affiche « Autorisé » ou « Accès suspendu ».</li>
                <li>Garde-fous : on ne peut pas suspendre l'accès à son propre compte ; le dernier Super Administrateur en mesure de se connecter ne peut être ni bloqué, ni supprimé, ni changer de rôle ; un Conseil du Temple ne voit ni ne modifie les comptes Super Administrateur.</li>
                <li>Chaque changement de statut et chaque suspension ou rétablissement d'accès est inscrit au <strong>journal d'activité</strong> (auteur, compte concerné, avant/après).</li>
            </ul>
        </li>
        <li><strong>Étapes du parcours</strong> : gestion des étapes du parcours d'intégration appliquées à chaque nouveau servant(e) (ajout, renommage dans une fenêtre, réordonnancement, suppression). La liste s'affiche par défaut dans l'ordre du parcours et peut se trier par Ordre, Étape ou Clé technique.</li>
        <li><strong>Journal d'activité</strong> : historique des créations, modifications et suppressions effectuées dans l'application (30 par page, recherche par auteur). Par défaut, les plus récentes en premier ; la liste se trie par Date, Action, Sur quoi ou Par qui, sur tout le journal. Le bouton <strong>Détails</strong> ouvre une fenêtre avec les valeurs avant/après.</li>
        <li><strong>Licence</strong> (Conseil du Temple et Super Administrateur) : consultation en lecture seule de la licence de son organisation — état (Valide, Expire bientôt, Expirée ou Sans date d'expiration), date d'expiration, temps restant mis à jour chaque minute et niveau d'alerte. Pour renouveler la licence, contactez le propriétaire de la plateforme.</li>
        <li><strong>Mode d'emploi</strong> : téléchargement de ce document au format PDF.</li>
    </ul>
    {!! $fig('15-utilisateurs') !!}
    {!! $fig('16-utilisateurs-modification') !!}
    {!! $fig('17-roles') !!}
    {!! $fig('18-pieux') !!}
    {!! $fig('19-horaires') !!}
    {!! $fig('20-parcours') !!}
    {!! $fig('21-journal') !!}
    {!! $fig('22-licence') !!}
    <p class="note">Bandeau de licence : tant que la licence datée n'a pas expiré, un bandeau en haut de chaque page indique au Conseil du Temple et au Super Administrateur le temps restant. Le bouton <strong>Masquer</strong> le cache jusqu'au lendemain (sur ce navigateur) ; il n'est pas proposé à 7 jours ou moins de l'échéance, ni sur le bandeau « Licence expirée ».</p>

    <div class="note" style="margin-top: 30px;">
        Ce document est généré automatiquement depuis l'application et reflète son fonctionnement au moment de la génération. En cas d'évolution de l'application, régénérez-le pour obtenir une version à jour.
    </div>
</body>
</html>
