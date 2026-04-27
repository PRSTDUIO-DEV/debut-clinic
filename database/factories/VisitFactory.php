<?php

namespace Database\Factories;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'visit_number' => 'VN-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'visit_date' => now()->toDateString(),
            'check_in_at' => now(),
            'status' => 'in_progress',
            'source' => 'walk_in',
            'total_amount' => 0,
        ];
    }
}
