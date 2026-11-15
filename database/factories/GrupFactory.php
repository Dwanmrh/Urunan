<?php

namespace Database\Factories;

use App\Models\Grup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grup>
 */
class GrupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->words(2, true),
            'dibuat_oleh' => User::factory(),
        ];
    }
}
