<script setup>
import LegalLayout from './Layout.vue';
</script>

<template>
    <LegalLayout title="Politique de confidentialité">
        <p class="italic text-neutral-500">
            Dernière mise à jour : [à compléter]. Ce document est un modèle de base — à faire relire et
            personnaliser (coordonnées réelles, base légale locale applicable) avant de le considérer comme
            juridiquement opposable.
        </p>

        <h2>1. Données collectées</h2>
        <p>Dans le cadre de l'utilisation du Service, les données suivantes peuvent être traitées :</p>
        <ul>
            <li>Compte utilisateur : nom, email, téléphone.</li>
            <li>Fiche membre (« Servant ») : nom, prénom, genre, téléphone, adresse, date de naissance, photo.</li>
            <li>Données d'usage : affectations aux Shifts, demandes de gouvernance, parcours d'intégration.</li>
        </ul>

        <h2>2. Finalité</h2>
        <p>
            Ces données sont utilisées exclusivement pour permettre à l'organisation cliente de gérer ses
            plannings, ses membres et sa gouvernance interne. Elles ne sont ni vendues, ni partagées à des
            tiers à des fins commerciales.
        </p>

        <h2>3. Hébergement</h2>
        <p>
            Les données sont hébergées chez un prestataire cloud tiers (Railway). Aucune donnée n'est
            transférée en dehors de ce qui est nécessaire au fonctionnement du Service.
        </p>

        <h2>4. Durée de conservation</h2>
        <p>
            Les données sont conservées tant que l'organisation utilise le Service, et supprimées sur demande
            de l'administrateur de l'organisation ou à la clôture du compte.
        </p>

        <h2>5. Vos droits</h2>
        <p>
            Toute personne dont les données sont traitées peut demander, auprès de l'administrateur de son
            organisation (ou en contactant [email de contact à compléter]), l'exercice des droits suivants :
        </p>
        <ul>
            <li>
                <strong>Rectification</strong> : l'administrateur, ou le chef d'équipe du Shift concerné, peut
                corriger à tout moment les informations d'une fiche membre.
            </li>
            <li>
                <strong>Accès et portabilité</strong> : l'administrateur peut exporter, au format structuré,
                l'ensemble des données personnelles détenues sur une fiche membre (identité, parcours,
                historique d'affectations).
            </li>
            <li>
                <strong>Effacement</strong> : l'administrateur peut anonymiser une fiche membre — nom, photo,
                date de naissance, téléphone et adresse sont alors définitivement effacés. L'historique
                d'affectations est conservé, dissocié de l'identité de la personne, pour l'intégrité des
                données de l'organisation.
            </li>
        </ul>

        <h2>6. Cookies</h2>
        <p>
            Le Service utilise uniquement un cookie de session strictement nécessaire à l'authentification.
            Aucun cookie de suivi ou publicitaire n'est utilisé.
        </p>

        <h2>7. Contact</h2>
        <p>Pour toute question relative à cette politique : [email de contact à compléter].</p>
    </LegalLayout>
</template>
