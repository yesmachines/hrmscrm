<?php

namespace Database\Factories;

use App\Models\Reward;
use App\Models\RewardCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => RewardCategory::factory(),
            'submitted_by' => 1,
            'claim_no' => 'REW-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'description' => fake()->paragraph(),
            'amount' => fake()->randomFloat(2, 100, 2500),
            'document_file' => null,
            'submitted_date' => now(),
            'status' => 'pending',
        ];
    }
}
