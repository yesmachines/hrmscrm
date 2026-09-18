<?php

namespace Database\Factories;

use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventType>
 */
class EventTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_code' => strtoupper(fake()->unique()->lexify('EVT_????')),
            'event_name' => fake()->words(2, true),
            'event_source' => fake()->randomElement(['manual', 'system']),
            'priority' => fake()->numberBetween(0, 10),
            'icon_path' => null,
            'status' => 1,
        ];
    }
}
