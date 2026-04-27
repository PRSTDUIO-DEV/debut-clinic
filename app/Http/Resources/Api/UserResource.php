<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'position' => $this->position,
            'is_doctor' => (bool) $this->is_doctor,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
        ];
    }
}
