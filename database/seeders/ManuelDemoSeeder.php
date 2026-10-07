<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Horaire;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTemplate;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Données de démonstration 100 % FICTIVES, uniquement pour illustrer le mode
 * d'emploi (captures d'écran, cf. tools/manuel/README.md). Aucune personne,
 * adresse e-mail ni téléphone réel : noms inventés, e-mails en @exemple.test,
 * téléphones factices.
 *
 * Garde-fous : refuse de s'exécuter en production, avec une APP_URL de
 * production (daertech.ci), ou sur une base qui contient déjà d'autres
 * organisations que l'organisation de démonstration.
 *
 * À lancer à la main, sur une base jetable :
 *   php artisan db:seed --class=ManuelDemoSeeder
 */
class ManuelDemoSeeder extends Seeder
{
    public const ORGANISATION = 'Temple de démonstration';

    public const DOMAINE = 'exemple.test';

    public const MOT_DE_PASSE = 'demo-password';

    private Organisation $organisation;

    /** @var array<string, User> */
    private array $comptes = [];

    /** @var array<string, Shift> */
    private array $shifts = [];

    /** @var array<string, Servant> */
    private array $servants = [];

    /**
     * @throws RuntimeException
     */
    public function run(): void
    {
        $this->verifierGardeFous();

        $this->call([RoleSeeder::class, WorkflowStepSeeder::class]);

        $this->organisation = Organisation::create([
            'nom' => self::ORGANISATION,
            'pays' => 'Pays de démonstration',
            'langue' => 'fr',
            'statut' => 'actif',
            'license_expires_at' => now()->addDays(45),
        ]);

        $this->creerComptes();
        // Les créations suivantes sont journalisées au nom du Conseil.
        Auth::setUser($this->comptes['conseil']);

        $this->creerPieux();
        $this->creerHoraires();
        $template = $this->creerModele();
        $this->creerShifts($template);
        $this->creerServants();
        $this->creerParcours();
        $this->affecter();
        $this->creerDemandes();
        $this->creerBesoins();
        $this->creerJournal();

        Auth::forgetUser();
    }

    private function verifierGardeFous(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ManuelDemoSeeder refuse de s\'exécuter en production.');
        }

        if (str_contains((string) config('app.url'), 'daertech.ci')) {
            throw new RuntimeException('ManuelDemoSeeder refuse de s\'exécuter avec une APP_URL de production (daertech.ci).');
        }

        if (Organisation::exists()) {
            throw new RuntimeException('ManuelDemoSeeder ne s\'exécute que sur une base vide (jetable) : des organisations existent déjà.');
        }

        if (Servant::withTrashed()->exists() || User::exists()) {
            throw new RuntimeException('ManuelDemoSeeder ne s\'exécute que sur une base vide (jetable) : des données existent déjà.');
        }
    }

    private function creerComptes(): void
    {
        $role = fn (string $slug) => Role::where('slug', $slug)->firstOrFail()->id;

        // clé => [prénom, nom, partie locale de l'e-mail, slug du rôle]
        $liste = [
            'super_admin' => ['Alex', 'Démo-Admin', 'super.admin', 'super_admin'],
            'conseil' => ['Brice', 'Démo-Conseil', 'conseil', 'administrateur'],
            'secretaire' => ['Chloé', 'Démo-Secrétaire', 'secretaire', 'secretaire'],
            'coordonnateur' => ['Denis', 'Démo-Coordo', 'coordonnateur', 'coordonnateur_equipe'],
            'coordonnateur_2' => ['Émile', 'Démo-Coordo-2', 'coordonnateur.deux', 'coordonnateur_equipe'],
            'coordonnateur_3' => ['Fanny', 'Démo-Coordo-3', 'coordonnatrice.trois', 'coordonnateur_equipe'],
            'autres' => ['Gaël', 'Démo-Lecture', 'consultation', 'autres'],
            'suspendu' => ['Hugo', 'Démo-Suspendu', 'suspendu', 'autres'],
        ];

        foreach ($liste as $cle => [$prenom, $famille, $local, $slug]) {
            $user = new User;
            $user->forceFill([
                'organisation_id' => $this->organisation->id,
                'role_id' => $role($slug),
                'name' => $prenom.' '.$famille,
                'prenom' => $prenom,
                'nom' => $famille,
                'email' => $local.'@'.self::DOMAINE,
                'telephone' => '07 00 00 00 0'.(count($this->comptes) % 10),
                'statut' => 'actif',
                'acces_suspendu' => $cle === 'suspendu',
                'email_verified_at' => now(),
                'password' => Hash::make(self::MOT_DE_PASSE),
                'must_change_password' => false,
            ])->save();
            $this->comptes[$cle] = $user;
        }
    }

    private function creerPieux(): void
    {
        $mission = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Mission Modèle', 'type' => 'mission']);
        $district = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'District Démo', 'type' => 'district', 'parent_id' => $mission->id]);
        Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Exemple Nord', 'type' => 'pieu', 'parent_id' => $district->id]);
        Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Exemple Sud', 'type' => 'pieu', 'parent_id' => $district->id]);
        Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Exemple Centre', 'type' => 'pieu']);
    }

    private function creerHoraires(): void
    {
        foreach ([['Matin', '06:30', '12:30'], ['Soir', '12:30', '17:30']] as [$nom, $debut, $fin]) {
            Horaire::create(['organisation_id' => $this->organisation->id, 'nom' => $nom, 'heure_debut' => $debut, 'heure_fin' => $fin]);
        }
    }

    private function creerModele(): ShiftTemplate
    {
        // ShiftTemplateSeeder s'appuie sur la première organisation : ici, la seule.
        $this->call(ShiftTemplateSeeder::class);

        return ShiftTemplate::where('organisation_id', $this->organisation->id)->firstOrFail();
    }

    private function creerShifts(ShiftTemplate $template): void
    {
        $horaires = Horaire::where('organisation_id', $this->organisation->id)->get()->keyBy('nom');
        $postes = $template->positions->keyBy('nom');

        $structure = [
            'Frères' => ['Coordonnateur', 'Coordonnateur Adjoint de la formation', 'Scelleur', 'Servant', 'Servant', 'Servant'],
            'Sœurs' => ['Coordonnatrice', 'Coordonnatrice Adjointe de la formation', 'Servante', 'Servante', 'Servante'],
        ];

        foreach (['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'] as $jour) {
            foreach (['Matin', 'Soir'] as $creneau) {
                foreach ($structure as $genre => $noms) {
                    $nom = ucfirst($jour).' '.$creneau.' '.$genre;
                    $shift = Shift::create([
                        'organisation_id' => $this->organisation->id,
                        'shift_template_id' => $template->id,
                        'nom' => $nom,
                        'jour' => $jour,
                        'heure_debut' => $horaires[$creneau]->heure_debut,
                        'heure_fin' => $horaires[$creneau]->heure_fin,
                        'statut' => 'actif',
                    ]);
                    foreach ($noms as $poste) {
                        $shift->positions()->create([
                            'shift_template_position_id' => $postes[$poste]->id,
                            'nom' => $poste,
                            'ordre' => $postes[$poste]->ordre,
                        ]);
                    }
                    $this->shifts[$nom] = $shift;
                }
            }
        }

        $gestion = [
            'coordonnateur' => 'Mardi Matin Frères',
            'coordonnateur_2' => 'Mercredi Soir Frères',
            'coordonnateur_3' => 'Vendredi Soir Sœurs',
        ];
        foreach ($gestion as $compte => $nomShift) {
            ShiftMember::create([
                'shift_id' => $this->shifts[$nomShift]->id,
                'user_id' => $this->comptes[$compte]->id,
                'role_id' => $this->comptes[$compte]->role_id,
                'date_debut' => now()->subMonths(3)->toDateString(),
                'statut' => 'actif',
            ]);
        }
    }

    private function creerServants(): void
    {
        $pieux = Pieu::where('type', 'pieu')->pluck('id')->all();

        // [Nom, Prénom, genre, statut]
        $liste = [
            // Anciens (affectables)
            ['Kouadio', 'Jean', 'homme', 'actif'], ['Traoré', 'Ibrahim', 'homme', 'actif'], ['Mensah', 'David', 'homme', 'actif'],
            ['Bamba', 'Moussa', 'homme', 'actif'], ['Konan', 'Paul', 'homme', 'actif'], ['Durand', 'Marc', 'homme', 'actif'],
            ['Lambert', 'Luc', 'homme', 'actif'], ['Okoro', 'Samuel', 'homme', 'actif'], ['Diallo', 'Joël', 'homme', 'actif'],
            ['Martin', 'Pierre', 'homme', 'actif'],
            ['Dupont', 'Marie', 'femme', 'actif'], ['Kouadio', 'Aya', 'femme', 'actif'], ['Traoré', 'Fanta', 'femme', 'actif'],
            ['Bamba', 'Salimata', 'femme', 'actif'], ['Konan', 'Adjoua', 'femme', 'actif'], ['Durand', 'Claire', 'femme', 'actif'],
            ['Lambert', 'Sophie', 'femme', 'actif'],
            // Nouveaux
            ['Mensah', 'Esther', 'femme', 'en_formation'], ['Okoro', 'Grace', 'femme', 'en_formation'], ['Diallo', 'Aminata', 'femme', 'en_formation'],
            ['Yao', 'Daniel', 'homme', 'en_formation'], ['Zadi', 'Eric', 'homme', 'en_formation'], ['Koffi', 'Mathieu', 'homme', 'en_formation'],
            // Recommandés
            ['Yao', 'Prisca', 'femme', 'recommande'], ['Martin', 'Julie', 'femme', 'recommande'], ['Nguessan', 'Ruth', 'femme', 'recommande'],
            ['Koffi', 'Sarah', 'femme', 'recommande'], ['Petit', 'Camille', 'femme', 'recommande'], ['Zadi', 'Estelle', 'femme', 'recommande'],
            ['Nguessan', 'Marc', 'homme', 'recommande'], ['Petit', 'Louis', 'homme', 'recommande'], ['Gnahoré', 'Simon', 'homme', 'recommande'],
            ['Soro', 'Thomas', 'homme', 'recommande'],
            // Relevés (relèves traitées)
            ['Soro', 'Anne', 'femme', 'suspendu'], ['Gnahoré', 'Lydie', 'femme', 'suspendu'],
            ['Tano', 'Victor', 'homme', 'suspendu'], ['Tano', 'Rachel', 'femme', 'suspendu'],
        ];

        foreach ($liste as $i => [$nom, $prenom, $genre, $statut]) {
            $servant = Servant::create([
                'organisation_id' => $this->organisation->id,
                'nom' => $nom,
                'prenom' => $prenom,
                'genre' => $genre,
                'statut' => $statut,
                'telephone' => sprintf('07 00 00 00 %02d', $i),
                'pieu_id' => $pieux[$i % count($pieux)],
                'date_appel' => now()->subMonths(6 + $i)->toDateString(),
                'date_debut' => $statut === 'recommande' ? null : now()->subMonths(2 + $i)->toDateString(),
                'adresse' => 'Quartier exemple, ville fictive',
                'appele' => $statut !== 'recommande',
            ]);
            $this->servants["$nom $prenom"] = $servant;
        }

        // Un compte lié à une fiche (colonne « Lié à un servant(e) »).
        $this->servants['Kouadio Aya']->update(['user_id' => $this->comptes['autres']->id]);
    }

    private function creerParcours(): void
    {
        $etapes = WorkflowStep::orderBy('ordre')->get();

        foreach ($this->servants as $servant) {
            $terminees = match ($servant->statut) {
                'actif', 'suspendu', 'retire' => $etapes->count(),
                'en_formation' => 8,
                default => 2,
            };
            foreach ($etapes as $index => $etape) {
                $statut = match (true) {
                    $index < $terminees => 'termine',
                    $index === $terminees => 'en_cours',
                    default => 'en_attente',
                };
                ServantWorkflowStep::create([
                    'servant_id' => $servant->id,
                    'workflow_step_id' => $etape->id,
                    'responsable_id' => $statut !== 'en_attente' ? $this->comptes['secretaire']->id : null,
                    'statut' => $statut,
                    'date' => $statut === 'termine' ? now()->subDays(30 + ($index * 9) + $servant->id) : null,
                ]);
            }
        }
    }

    /**
     * Pourvoit une partie des postes avec des anciens (les autres restent
     * vacants, ce qui nourrit le recrutement et les rapports).
     */
    private function affecter(): void
    {
        $plan = [
            'Mardi Matin Frères' => ['Kouadio Jean', 'Traoré Ibrahim', 'Mensah David', 'Bamba Moussa', 'Konan Paul'],
            'Mercredi Soir Frères' => ['Durand Marc', 'Lambert Luc', 'Okoro Samuel'],
            'Jeudi Matin Frères' => ['Diallo Joël', 'Martin Pierre'],
            'Mardi Matin Sœurs' => ['Dupont Marie', 'Kouadio Aya', 'Traoré Fanta', 'Bamba Salimata'],
            'Vendredi Soir Sœurs' => ['Konan Adjoua', 'Durand Claire', 'Lambert Sophie'],
        ];

        foreach ($plan as $nomShift => $noms) {
            $postes = $this->shifts[$nomShift]->positions()->get();
            foreach ($noms as $i => $nom) {
                Assignment::create([
                    'shift_position_id' => $postes[$i]->id,
                    'servant_id' => $this->servants[$nom]->id,
                    'date_debut' => now()->subMonths(2)->toDateString(),
                    'statut' => 'actif',
                ]);
            }
        }

        // Anciens postes des servants relevés (affectations terminées).
        $anciens = [
            'Soro Anne' => 'Jeudi Matin Sœurs', 'Gnahoré Lydie' => 'Samedi Soir Sœurs',
            'Tano Victor' => 'Samedi Matin Frères', 'Tano Rachel' => 'Mardi Soir Sœurs',
        ];
        foreach ($anciens as $nom => $nomShift) {
            $poste = $this->shifts[$nomShift]->positions()->get()->last();
            Assignment::create([
                'shift_position_id' => $poste->id,
                'servant_id' => $this->servants[$nom]->id,
                'date_debut' => now()->subMonths(8)->toDateString(),
                'date_fin' => now()->subMonths(1)->toDateString(),
                'statut' => 'termine',
            ]);
        }
    }

    private function creerDemandes(): void
    {
        $base = fn (string $type, string $shift, string $servant, array $extra = []) => ShiftTransferRequest::create([
            'organisation_id' => $this->organisation->id,
            'type' => $type,
            'shift_id' => $this->shifts[$shift]->id,
            'servant_id' => $this->servants[$servant]->id,
            'demandeur_id' => $this->comptes['secretaire']->id,
            'motif' => 'Motif d\'exemple saisi pour la démonstration.',
            'date_demande' => now()->subDays(8)->toDateString(),
            ...$extra,
        ]);

        $co1 = $this->comptes['coordonnateur'];
        $co2 = $this->comptes['coordonnateur_2'];
        $co3 = $this->comptes['coordonnateur_3'];
        $conseil = $this->comptes['conseil'];
        $valide = fn (User $u, int $jours) => ['par' => $u->id, 'le' => now()->subDays($jours)];

        // Permutation 1 : en attente du coordonnateur du Shift d'origine.
        $base('permutation', 'Mardi Matin Frères', 'Konan Paul', [
            'shift_destination_id' => $this->shifts['Mercredi Soir Frères']->id,
            'statut' => 'en_attente',
            'motif' => 'Changement de disponibilité : souhaite servir le mercredi après-midi.',
        ]);

        // Permutation 2 : origine validée, en attente du coordonnateur de destination.
        $v = $valide($co1, 4);
        $base('permutation', 'Mardi Matin Frères', 'Bamba Moussa', [
            'shift_destination_id' => $this->shifts['Mercredi Soir Frères']->id,
            'statut' => 'en_attente',
            'validation_chef_origine' => true,
            'validation_chef_origine_par_id' => $v['par'],
            'validation_chef_origine_le' => $v['le'],
            'motif' => 'Rapprochement du domicile : demande le créneau du mercredi soir.',
        ]);

        // Permutation 3 : prête pour décision du Conseil.
        $vo = $valide($co1, 6);
        $vd = $valide($co2, 3);
        $base('permutation', 'Mardi Matin Frères', 'Mensah David', [
            'shift_destination_id' => $this->shifts['Mercredi Soir Frères']->id,
            'statut' => 'en_attente',
            'validation_chef_origine' => true,
            'validation_chef_origine_par_id' => $vo['par'],
            'validation_chef_origine_le' => $vo['le'],
            'validation_chef_destination' => true,
            'validation_chef_destination_par_id' => $vd['par'],
            'validation_chef_destination_le' => $vd['le'],
            'motif' => 'Contraintes professionnelles le mardi matin.',
        ]);

        // Permutation 4 : tranchée (favorable).
        $vo = $valide($co1, 20);
        $vd = $valide($co3, 18);
        $base('permutation', 'Mardi Matin Sœurs', 'Traoré Fanta', [
            'shift_destination_id' => $this->shifts['Vendredi Soir Sœurs']->id,
            'statut' => 'traitee',
            'validation_chef_origine' => true,
            'validation_chef_origine_par_id' => $vo['par'],
            'validation_chef_origine_le' => $vo['le'],
            'validation_chef_destination' => true,
            'validation_chef_destination_par_id' => $vd['par'],
            'validation_chef_destination_le' => $vd['le'],
            'favorable' => true,
            'resultat' => 'Permutation accordée',
            'resultat_date' => now()->subDays(15)->toDateString(),
            'decideur_id' => $conseil->id,
            'date_demande' => now()->subDays(25)->toDateString(),
            'motif' => 'Changement de situation familiale.',
        ]);

        // Relèves traitées (3 en attente de réintégration, 1 déjà réintégrée) et 1 en attente.
        foreach (['Soro Anne' => 'Jeudi Matin Sœurs', 'Gnahoré Lydie' => 'Samedi Soir Sœurs', 'Tano Victor' => 'Samedi Matin Frères'] as $nom => $nomShift) {
            $base('releve', $nomShift, $nom, [
                'statut' => 'traitee',
                'favorable' => true,
                'resultat' => 'Relève accordée',
                'resultat_date' => now()->subMonth()->toDateString(),
                'decideur_id' => $conseil->id,
                'date_demande' => now()->subDays(40)->toDateString(),
                'motif' => 'Départ temporaire pour raison personnelle.',
            ]);
        }
        $base('releve', 'Mardi Soir Sœurs', 'Tano Rachel', [
            'statut' => 'traitee',
            'favorable' => true,
            'resultat' => 'Relève accordée',
            'resultat_date' => now()->subMonths(2)->toDateString(),
            'decideur_id' => $conseil->id,
            'date_demande' => now()->subMonths(3)->toDateString(),
            'reintegre_le' => now()->subDays(10),
            'reintegre_par_id' => $conseil->id,
            'reintegration_commentaire' => 'Retour confirmé.',
        ]);
        $this->servants['Tano Rachel']->update(['statut' => 'actif']);
        $base('releve', 'Mercredi Soir Frères', 'Okoro Samuel', [
            'statut' => 'en_attente',
            'motif' => 'Demande de relève pour raison de santé.',
        ]);

        // Appels en attente.
        $base('appel', 'Jeudi Matin Frères', 'Martin Pierre', ['statut' => 'en_attente', 'motif' => 'Rappel du titulaire pour reconduction.']);
        $base('appel', 'Vendredi Soir Sœurs', 'Lambert Sophie', ['statut' => 'en_attente', 'motif' => 'Changement de rôle sur le même Shift.']);
    }

    private function creerBesoins(): void
    {
        $besoins = [
            ['Mardi Matin Sœurs', 2], ['Mercredi Soir Frères', 1], ['Vendredi Matin Sœurs', 3],
            ['Samedi Soir Frères', 2], ['Jeudi Matin Frères', 1],
        ];
        foreach ($besoins as $i => [$nom, $nombre]) {
            ShiftRecruitmentNeed::create([
                'organisation_id' => $this->organisation->id,
                'shift_id' => $this->shifts[$nom]->id,
                'nombre_a_recruter' => $nombre,
                'echeance' => now()->addDays(20 + $i * 10)->toDateString(),
                'notes' => 'Besoin identifié lors de la revue des effectifs.',
                'updated_by' => $this->comptes['conseil']->id,
            ]);
        }
    }

    /**
     * Quelques entrées de journal explicites, puis répartition de toutes les
     * dates sur les 30 derniers jours (rendu réaliste du journal).
     */
    private function creerJournal(): void
    {
        foreach ([
            ['secretaire', 'Ajout d\'un servant(e) depuis la fiche'],
            ['conseil', 'Changement de statut : Recommandé → Nouveau'],
            ['super_admin', 'Connexion de contrôle'],
        ] as [$compte, $texte]) {
            activity()->causedBy($this->comptes[$compte])->log($texte);
        }

        $ids = DB::table('activity_log')->orderBy('id')->pluck('id');
        $total = max($ids->count(), 1);
        foreach ($ids as $rang => $id) {
            $date = now()->subDays(30)->addSeconds((int) (30 * 86400 * ($rang + 1) / $total) - 600);
            DB::table('activity_log')->where('id', $id)->update(['created_at' => $date, 'updated_at' => $date]);
        }
    }
}
