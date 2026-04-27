<?php

namespace Database\Factories;

use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            'code' => 'PRC-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['Injection', 'Laser', 'Consultation', 'Surgery']),
            'price' => fake()->randomFloat(2, 500, 30000),
            'cost' => fake()->randomFloat(2, 100, 10000),
            'duration_minutes' => fake()->randomElement([20, 30, 45, 60, 90]),
            'doctor_fee_rate' => 30,
            'staff_commission_rate' => 5,
            'follow_up_days' => fake()->randomElement([0, 7, 14, 30]),
            'is_active' => true,
        ];
    }
}
