<?php

namespace App\Http\Requests\Api\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->header('X-Branch-Id');

        return [
            'prefix' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'id_card' => ['nullable', 'string', 'size:13'],
            'phone' => [
                'nullable', 'string', 'max:20',
                Rule::unique('patients', 'phone')->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at')),
            ],
            'email' => ['nullable', 'email', 'max:100'],
            'line_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'allergies' => ['nullable', 'string'],
            'underlying_diseases' => ['nullable', 'string'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:100'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact.relation' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', Rule::in(['walk_in', 'referral', 'online', 'line'])],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('branch_id', $branchId)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'เบอร์โทรนี้มีในระบบแล้ว',
        ];
    }
}
