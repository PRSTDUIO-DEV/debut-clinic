<?php

namespace App\Http\Requests\Api\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->header('X-Branch-Id');

        return [
            'patient_uuid' => ['required', Rule::exists('patients', 'uuid')->where('branch_id', $branchId)],
            'doctor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('branch_id', $branchId)],
            'procedure_id' => ['nullable', 'integer', Rule::exists('procedures', 'id')->where('branch_id', $branchId)],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'source' => ['nullable', Rule::in(['manual', 'follow_up', 'crm', 'online', 'walk_in'])],
            'follow_up_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
