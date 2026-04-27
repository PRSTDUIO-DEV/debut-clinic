<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'hn' => $this->hn,
            'prefix' => $this->prefix,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'nickname' => $this->nickname,
            'gender' => $this->gender,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'line_id' => $this->line_id,
            'address' => $this->address,
            'allergies' => $this->allergies,
            'underlying_diseases' => $this->underlying_diseases,
            'blood_type' => $this->blood_type,
            'emergency_contact' => $this->emergency_contact,
            'avatar' => $this->avatar,
            'source' => $this->source,
            'customer_group' => $this->whenLoaded('customerGroup', fn () => [
                'id' => $this->customerGroup?->id,
                'name' => $this->customerGroup?->name,
                'discount_rate' => $this->customerGroup?->discount_rate,
            ]),
            'total_spent' => (float) $this->total_spent,
            'visit_count' => (int) $this->visit_count,
            'last_visit_at' => optional($this->last_visit_at)->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
