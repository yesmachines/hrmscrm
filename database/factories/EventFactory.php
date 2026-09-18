<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');

        return [
            'event_type_id' => EventType::factory(),
            'organisation_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+2 hours'),
            'external_link' => fake()->boolean(30) ? fake()->url() : null,
            'status' => 'published',
            'created_by' => null,
            'employee_id' => null,
            'file_path' => null,
            'show_dashboard' => true,
        ];
    }
}
