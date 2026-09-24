<?php

namespace Database\Factories;

use App\Models\RewardCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardCategory>
 */
class RewardCategoryFactory extends Factory
{
    protected $model = RewardCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reward_name' => fake()->unique()->words(3, true),
            'short_code' => strtoupper(fake()->unique()->lexify('???')),
            'status' => 1,
            'details' => fake()->sentence(),
        ];
    }
}
