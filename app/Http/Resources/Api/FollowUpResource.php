<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpResource extends JsonResource
{
    public function toArray($request): array
    {
        $today = now()->startOfDay();
        $due = $this->follow_up_date ? $this->follow_up_date->copy()->startOfDay() : null;
        $daysOverdue = $due ? max(0, $today->diffInDays($due, false) * -1) : 0;

        return [
            'id' => $this->id,
            'follow_up_date' => optional($this->follow_up_date)->toDateString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'contact_attempts' => (int) $this->contact_attempts,
            'last_contacted_at' => optional($this->last_contacted_at)->toIso8601String(),
            'days_overdue' => $daysOverdue,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->uuid,
                'hn' => $this->patient?->hn,
                'name' => $this->patient ? trim($this->patient->first_name.' '.$this->patient->last_name) : null,
                'phone' => $this->patient?->phone,
                'line_id' => $this->patient?->line_id,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->relationLoaded('doctor') && $this->doctor ? [
                'id' => $this->doctor->uuid,
                'name' => $this->doctor->name,
            ] : null),
            'procedure' => $this->whenLoaded('procedure', fn () => $this->procedure ? [
                'id' => $this->procedure->id,
                'code' => $this->procedure->code,
                'name' => $this->procedure->name,
            ] : null),
            'visit_id' => $this->visit_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
