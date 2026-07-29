<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Servant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servant>
 */
class ServantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'genre' => fake()->randomElement(['homme', 'femme']),
            'telephone' => fake()->phoneNumber(),
            'statut' => 'recommande',
        ];
    }
}
