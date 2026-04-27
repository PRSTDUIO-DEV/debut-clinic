<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'is_primary' => (bool) ($this->pivot->is_primary ?? false),
        ];
    }
}
