<?php

namespace Database\Factories;

use App\Enums\CaraBagi;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tagihan>
 */
class TagihanFactory extends Factory
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
            'judul' => fake()->words(3, true),
            'total' => 100000,
            'dibayar_oleh' => AnggotaGrup::factory(),
            'cara_bagi' => CaraBagi::Rata,
            'tanggal' => now()->toDateString(),
        ];
    }
}
