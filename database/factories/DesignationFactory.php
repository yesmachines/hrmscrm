<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => 1,
            'title' => fake()->jobTitle(),
            'shortcode' => strtoupper(fake()->lexify('???')),
            'status' => 1,
        ];
    }
}
