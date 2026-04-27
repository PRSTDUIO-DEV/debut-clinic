<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'DC'.fake()->unique()->numerify('##'),
            'address' => fake()->address(),
            'phone' => '02-'.fake()->numerify('###-####'),
            'email' => fake()->unique()->safeEmail(),
            'opening_time' => '09:00',
            'closing_time' => '20:00',
            'is_active' => true,
            'settings' => [],
        ];
    }
}
