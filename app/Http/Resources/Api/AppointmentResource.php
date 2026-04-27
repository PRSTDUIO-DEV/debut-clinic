<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'appointment_date' => optional($this->appointment_date)->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'source' => $this->source,
            'reminder_sent' => (bool) $this->reminder_sent,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->uuid,
                'hn' => $this->patient?->hn,
                'name' => $this->patient ? trim($this->patient->first_name.' '.$this->patient->last_name) : null,
                'phone' => $this->patient?->phone,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->uuid,
                'name' => $this->doctor?->name,
            ]),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room?->id,
                'name' => $this->room?->name,
            ]),
            'procedure' => $this->whenLoaded('procedure', fn () => [
                'id' => $this->procedure?->id,
                'code' => $this->procedure?->code,
                'name' => $this->procedure?->name,
            ]),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
