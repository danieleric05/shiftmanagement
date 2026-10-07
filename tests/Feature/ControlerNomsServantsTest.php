<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Servant;
use App\Support\NomSuspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlerNomsServantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detecte_les_noms_suspects(): void
    {
        $this->assertSame('contient un symbole ou un smiley', NomSuspect::raison('Bla :-)'));
        $this->assertSame('contient un chiffre', NomSuspect::raison('Jean2'));
        $this->assertSame('vide', NomSuspect::raison('  '));
        $this->assertSame('espaces en trop', NomSuspect::raison('Jean  Paul'));
        $this->assertSame('moins de 2 lettres', NomSuspect::raison('A'));
    }

    public function test_accepte_les_noms_normaux(): void
    {
        foreach (['Ahou', 'Kouamé', "N'Guessan", 'Jean-Paul', 'Akré Aboussou', 'Ève', 'Mc.Donald'] as $nom) {
            $this->assertNull(NomSuspect::raison($nom), $nom);
        }
    }

    public function test_la_commande_liste_sans_rien_modifier(): void
    {
        $organisation = Organisation::factory()->create();
        $suspect = Servant::factory()->create(['organisation_id' => $organisation->id, 'nom' => 'Bla :-)', 'prenom' => 'Ahou']);
        Servant::factory()->create(['organisation_id' => $organisation->id, 'nom' => 'Kouamé', 'prenom' => 'Jean']);

        $this->artisan('temple:controler-noms')
            ->expectsOutputToContain('Bla :-)')
            ->assertSuccessful();

        $this->assertSame('Bla :-)', $suspect->fresh()->nom);
    }

    public function test_la_commande_signale_quand_tout_va_bien(): void
    {
        $organisation = Organisation::factory()->create();
        Servant::factory()->create(['organisation_id' => $organisation->id, 'nom' => 'Kouamé', 'prenom' => 'Jean']);

        $this->artisan('temple:controler-noms')->expectsOutput('Aucun nom suspect.')->assertSuccessful();
    }
}
