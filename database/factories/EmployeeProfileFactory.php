<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'gender' => fake()->randomElement(['M', 'F']),
            'dob_personal' => fake()->date(),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'nationality' => fake()->country(),
            'religion' => fake()->optional()->word(),
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'personal_email' => fake()->safeEmail(),
            'personal_mobile' => fake()->phoneNumber(),
            'address_uae' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_relation' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend']),
            'emergency_mobile' => fake()->phoneNumber(),
            'home_country' => fake()->numberBetween(1, 250),
            'address_home' => fake()->address(),
            'home_mobile' => fake()->phoneNumber(),
            'home_emergency_name' => fake()->name(),
            'home_emergency_relation' => fake()->randomElement(['Parent', 'Sibling', 'Relative']),
            'home_emergency_mobile' => fake()->phoneNumber(),
            'visa_type' => fake()->randomElement(['visa', 'workpermit']),
            'visa_from' => fake()->country(),
            'dob_passport' => fake()->date(),
            'total_experience' => fake()->randomFloat(1, 0, 30),
            'highest_education' => fake()->randomElement(['High School', 'Bachelor', 'Master', 'PhD']),
        ];
    }
}
