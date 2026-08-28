<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration pour tester l'app en local de bout en bout :
 * parcours d'intégration des servants existants (jusque-là tous vides),
 * pipeline de recrutement (candidats/entretiens), besoins de recrutement,
 * et quelques demandes de relève/permutation/appel supplémentaires.
 *
 * Volontairement absent de DatabaseSeeder : à lancer à la main
 * (php artisan db:seed --class=DevTestDataSeeder), jamais en prod.
 */
class DevTestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private Organisation $organisation;

    private User $admin;

    public function run(): void
    {
        $this->organisation = Organisation::firstOrFail();
        $this->admin = User::where('organisation_id', $this->organisation->id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['administrateur', 'super_admin']))
            ->firstOrFail();

        $this->backfillParcoursServants();
        $this->creerCoordonnateursSupplementaires();
        $this->remplirBesoinsRecrutement();
        $this->creerCandidatsEtEntretiens();
        $this->creerTransfertsSupplementaires();

        $this->command->info('Données de test créées avec succès.');
    }

    /**
     * Aucun servant existant n'a de parcours d'intégration (seuls les
     * servants créés depuis l'app en reçoivent un) : ni "Postes du Shift"
     * (étapes cochables) ni "Édition du servant" (onglet Parcours) n'ont
     * quoi que ce soit à afficher. On génère un parcours cohérent avec le
     * statut actuel de chaque servant.
     */
    private function backfillParcoursServants(): void
    {
        $etapes = WorkflowStep::orderBy('ordre')->get();
        if ($etapes->isEmpty()) {
            return;
        }

        $servantsSansParcours = Servant::where('organisation_id', $this->organisation->id)
            ->whereDoesntHave('workflowSteps')
            ->get();

        foreach ($servantsSansParcours as $servant) {
            $nombreTerminees = match ($servant->statut) {
                'actif', 'suspendu', 'retire' => $etapes->count(),
                'en_formation' => (int) floor($etapes->count() * 0.7),
                default => 1,
            };

            foreach ($etapes as $index => $etape) {
                $statut = match (true) {
                    $index < $nombreTerminees => 'termine',
                    $index === $nombreTerminees => 'en_cours',
                    default => 'en_attente',
                };

                ServantWorkflowStep::create([
                    'servant_id' => $servant->id,
                    'workflow_step_id' => $etape->id,
                    'responsable_id' => $statut !== 'en_attente' ? $this->admin->id : null,
                    'statut' => $statut,
                    'date' => $statut === 'termine' ? now()->subDays(random_int(10, 400)) : null,
                ]);
            }
        }

        $this->command->info("Parcours généré pour {$servantsSansParcours->count()} servants.");
    }

    /**
     * Un seul shift a un coordonnateur d'équipe assigné (le compte de test) :
     * impossible de tester la double validation d'une permutation, ni la
     * plupart des vues "Mon shift"/dashboard coordonnateur sur d'autres
     * shifts. On ajoute deux coordonnateurs supplémentaires sur deux autres
     * shifts.
     */
    private function creerCoordonnateursSupplementaires(): void
    {
        $roleCoordo = Role::where('slug', 'coordonnateur_equipe')->firstOrFail();

        $affectations = [
            ['email' => 'coordonnateur.mercredi@example.com', 'name' => 'Coordo Mercredi Matin', 'shift_nom' => 'Mercredi Matin Frères'],
            ['email' => 'coordonnateur.vendredi@example.com', 'name' => 'Coordo Vendredi Soir', 'shift_nom' => 'Vendredi Soir Sœurs'],
        ];

        foreach ($affectations as $a) {
            $shift = Shift::where('organisation_id', $this->organisation->id)->where('nom', $a['shift_nom'])->first();
            if (! $shift) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'],
                    'password' => Hash::make('password'),
                    'organisation_id' => $this->organisation->id,
                    'role_id' => $roleCoordo->id,
                ]
            );

            ShiftMember::firstOrCreate(
                ['shift_id' => $shift->id, 'user_id' => $user->id],
                ['role_id' => $roleCoordo->id, 'date_debut' => now()->subMonths(3)->toDateString(), 'statut' => 'actif']
            );
        }
    }

    /**
     * Quelques besoins de recrutement réalistes (le widget "Sœurs/Frères
     * recherchés" du dashboard est vide sinon).
     */
    private function remplirBesoinsRecrutement(): void
    {
        $besoins = [
            ['shift' => 'Mardi Matin Sœurs', 'nombre' => 2],
            ['shift' => 'Mercredi Soir Frères', 'nombre' => 1],
            ['shift' => 'Vendredi Matin Sœurs', 'nombre' => 3],
            ['shift' => 'Samedi Soir Frères', 'nombre' => 2],
        ];

        foreach ($besoins as $b) {
            $shift = Shift::where('organisation_id', $this->organisation->id)->where('nom', $b['shift'])->first();
            if (! $shift) {
                continue;
            }

            ShiftRecruitmentNeed::updateOrCreate(
                ['shift_id' => $shift->id],
                [
                    'organisation_id' => $this->organisation->id,
                    'nombre_a_recruter' => $b['nombre'],
                    'echeance' => now()->addDays(random_int(20, 60))->toDateString(),
                    'notes' => 'Besoin identifié lors de la revue trimestrielle des effectifs.',
                    'updated_by' => $this->admin->id,
                ]
            );
        }
    }

    /**
     * Pipeline de recrutement complet (candidats à tous les stades +
     * entretiens passés et à venir) : jusqu'ici entièrement vide, donc le
     * widget "Nouveaux servants appelés" du dashboard et la page Entretiens
     * n'avaient rien à montrer.
     */
    private function creerCandidatsEtEntretiens(): void
    {
        if (Candidate::where('organisation_id', $this->organisation->id)->exists()) {
            $this->command->info('Candidats déjà présents, étape ignorée.');

            return;
        }

        $shifts = Shift::where('organisation_id', $this->organisation->id)->get()->keyBy('nom');
        $noms = [
            ['nom' => 'Kouakou', 'prenom' => 'Aya Grace'],
            ['nom' => 'Traore', 'prenom' => 'Ibrahim'],
            ['nom' => 'Yao', 'prenom' => 'Marie-Solange'],
            ['nom' => 'Konan', 'prenom' => 'Kouassi Roland'],
            ['nom' => 'Diabate', 'prenom' => 'Fatoumata'],
            ['nom' => 'Bamba', 'prenom' => 'Souleymane'],
            ['nom' => 'N\'Guessan', 'prenom' => 'Affoue Carine'],
            ['nom' => 'Ouattara', 'prenom' => 'Drissa'],
            ['nom' => 'Kone', 'prenom' => 'Adjoua Rachel'],
            ['nom' => 'Assamoi', 'prenom' => 'Yann-Eric'],
        ];

        $plan = [
            ['statut' => 'nouveau'],
            ['statut' => 'nouveau'],
            ['statut' => 'appele'],
            ['statut' => 'appele'],
            ['statut' => 'entretien_planifie', 'entretien' => ['statut' => 'planifie', 'jours' => 5]],
            ['statut' => 'entretien_planifie', 'entretien' => ['statut' => 'planifie', 'jours' => 10]],
            ['statut' => 'entretien_realise', 'entretien' => ['statut' => 'realise', 'jours' => -7, 'favorable' => true]],
            ['statut' => 'entretien_realise', 'entretien' => ['statut' => 'realise', 'jours' => -3, 'favorable' => false]],
            ['statut' => 'converti', 'entretien' => ['statut' => 'realise', 'jours' => -20, 'favorable' => true]],
            ['statut' => 'abandonne'],
        ];

        $shiftsDisponibles = $shifts->values();

        foreach ($plan as $index => $etape) {
            $identite = $noms[$index];
            $shift = $shiftsDisponibles[$index % $shiftsDisponibles->count()];

            $candidate = Candidate::create([
                'organisation_id' => $this->organisation->id,
                'nom' => $identite['nom'],
                'prenom' => $identite['prenom'],
                'telephone' => '07'.random_int(10000000, 99999999),
                'shift_souhaite_id' => $shift->id,
                'date_appel' => in_array($etape['statut'], ['nouveau'], true) ? null : now()->subDays(random_int(5, 30)),
                'statut' => $etape['statut'],
                'notes' => 'Candidat de démonstration.',
            ]);

            if (! isset($etape['entretien'])) {
                continue;
            }

            $e = $etape['entretien'];
            $dateEntretien = now()->addDays($e['jours']);

            Interview::create([
                'organisation_id' => $this->organisation->id,
                'candidate_id' => $candidate->id,
                'shift_souhaite_id' => $shift->id,
                'planifie_par' => $this->admin->id,
                'date_entretien' => $dateEntretien->toDateString(),
                'heure_entretien' => null,
                'engagement_vu' => $e['statut'] === 'realise',
                'statut' => $e['statut'],
                'resultat' => $e['statut'] === 'realise' ? ($e['favorable'] ? 'Entretien concluant, candidat retenu.' : 'Engagement insuffisant, non retenu pour le moment.') : null,
                'shift_affecte_id' => $e['statut'] === 'realise' && $e['favorable'] ? $shift->id : null,
                'decideur_id' => $e['statut'] === 'realise' ? $this->admin->id : null,
                'decided_at' => $e['statut'] === 'realise' ? $dateEntretien : null,
            ]);
        }

        $this->command->info('10 candidats et leurs entretiens créés.');
    }

    /**
     * Ajoute des demandes "appel" (type tout juste introduit, aucune donnée
     * encore) et une permutation en attente (pour tester la double
     * validation par les deux coordonnateurs d'équipe, jusque-là intestable
     * faute d'un deuxième shift géré).
     */
    private function creerTransfertsSupplementaires(): void
    {
        if (ShiftTransferRequest::where('organisation_id', $this->organisation->id)->where('type', 'appel')->exists()) {
            $this->command->info('Transferts "appel" déjà présents, étape ignorée.');

            return;
        }

        $servantSurShift = fn (string $nomShift) => Servant::where('organisation_id', $this->organisation->id)
            ->whereHas('assignments', fn ($q) => $q->where('statut', 'actif')
                ->whereHas('shiftPosition.shift', fn ($sq) => $sq->where('nom', $nomShift)))
            ->first();

        $appels = [
            ['shift' => 'Jeudi Matin Frères', 'motif' => "Appel à servir sur ce poste suite à une vacance."],
            ['shift' => 'Samedi Matin Sœurs', 'motif' => "Appel complémentaire pour renforcer l'équipe."],
        ];

        foreach ($appels as $a) {
            $shift = Shift::where('organisation_id', $this->organisation->id)->where('nom', $a['shift'])->first();
            $servant = $servantSurShift($a['shift']);
            if (! $shift || ! $servant) {
                continue;
            }

            ShiftTransferRequest::create([
                'organisation_id' => $this->organisation->id,
                'type' => 'appel',
                'shift_id' => $shift->id,
                'servant_id' => $servant->id,
                'demandeur_id' => $this->admin->id,
                'motif' => $a['motif'],
                'date_demande' => now()->subDays(random_int(1, 10))->toDateString(),
                'statut' => 'en_attente',
            ]);
        }

        $origine = Shift::where('organisation_id', $this->organisation->id)->where('nom', 'Mercredi Matin Frères')->first();
        $destination = Shift::where('organisation_id', $this->organisation->id)->where('nom', 'Vendredi Soir Sœurs')->first();
        $servantPermutation = $servantSurShift('Mercredi Matin Frères');

        if ($origine && $destination && $servantPermutation) {
            ShiftTransferRequest::create([
                'organisation_id' => $this->organisation->id,
                'type' => 'permutation',
                'shift_id' => $origine->id,
                'shift_destination_id' => $destination->id,
                'servant_id' => $servantPermutation->id,
                'demandeur_id' => $this->admin->id,
                'motif' => 'Changement de disponibilité personnelle, demande de permutation vers un autre créneau.',
                'date_demande' => now()->subDays(3)->toDateString(),
                'statut' => 'en_attente',
            ]);
        }

        $this->command->info('Demandes de transfert supplémentaires créées.');
    }
}
