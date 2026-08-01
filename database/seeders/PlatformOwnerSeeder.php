<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlatformOwnerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! User::where('email', 'aboussoudaniel@gmail.com')->exists()) {
            User::factory()->create([
                'name' => 'Daniel-Eric Aboussou',
                'email' => 'aboussoudaniel@gmail.com',
                'organisation_id' => null,
                'role_id' => null,
                'is_platform_owner' => true,
            ]);
        }
    }
}
