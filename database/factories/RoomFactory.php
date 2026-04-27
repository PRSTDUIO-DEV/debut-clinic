<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(['consultation', 'treatment', 'vip', 'surgery']),
            'floor' => fake()->numberBetween(1, 3),
            'is_active' => true,
        ];
    }
}
