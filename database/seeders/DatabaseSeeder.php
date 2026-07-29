<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OrganisationSeeder::class,
            WorkflowStepSeeder::class,
            ShiftTemplateSeeder::class,
            PieuSeeder::class,
            HoraireSeeder::class,
            ShiftSeeder::class,
            AssignmentSeeder::class,
        ]);

        $superAdmin = \App\Models\Role::where('slug', 'super_admin')->first();
        $organisation = \App\Models\Organisation::first();

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'organisation_id' => $organisation->id,
            'role_id' => $superAdmin->id,
        ]);
    }
}
