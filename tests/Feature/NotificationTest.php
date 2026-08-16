<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTransferRequest as ShiftTransferRequestModel;
use App\Models\User;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilisateur_peut_marquer_une_notification_comme_lue(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);
        $admin = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $role->id]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'nom' => 'Shift Test', 'jour' => 'mardi',
            'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $demande = ShiftTransferRequestModel::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $admin->id, 'motif' => 'Test',
            'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);

        $admin->notify(new NouvelleDemandeTransfert($demande));
        $notification = $admin->unreadNotifications()->firstOrFail();

        $this->actingAs($admin)
            ->patch("/notifications/{$notification->id}/lu")
            ->assertRedirect();

        $this->assertNotNull($admin->fresh()->notifications()->find($notification->id)->read_at);
    }

    public function test_utilisateur_ne_peut_pas_marquer_la_notification_dun_autre_comme_lue(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);
        $admin = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $role->id]);
        $autreUser = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $role->id]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'nom' => 'Shift Test', 'jour' => 'mardi',
            'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $demande = ShiftTransferRequestModel::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $admin->id, 'motif' => 'Test',
            'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);

        $admin->notify(new NouvelleDemandeTransfert($demande));
        $notification = $admin->unreadNotifications()->firstOrFail();

        $this->actingAs($autreUser)
            ->patch("/notifications/{$notification->id}/lu")
            ->assertNotFound();
    }
}
