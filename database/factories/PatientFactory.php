<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'hn' => 'TEMP-'.fake()->unique()->numerify('########'),
            'prefix' => fake()->randomElement(['นาย', 'นาง', 'นางสาว', null]),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'nickname' => fake()->optional()->firstName(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'phone' => '08'.fake()->unique()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
            'line_id' => fake()->optional()->bothify('line_##??'),
            'source' => fake()->randomElement(['walk_in', 'referral', 'online', 'line']),
            'allergies' => fake()->optional()->sentence(),
            'underlying_diseases' => fake()->optional()->sentence(),
            'total_spent' => 0,
            'visit_count' => 0,
        ];
    }
}
