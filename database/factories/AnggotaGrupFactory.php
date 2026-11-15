<?php

namespace Database\Factories;

use App\Models\AnggotaGrup;
use App\Models\Grup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnggotaGrup>
 */
class AnggotaGrupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grup_id' => Grup::factory(),
            'user_id' => null,
            'nama' => fake()->firstName(),
        ];
    }
}
