<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 hour', '+30 days');
        $end = (clone $start)->modify('+30 minutes');

        return [
            'appointment_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'status' => 'pending',
            'source' => 'manual',
            'reminder_sent' => false,
        ];
    }
}
