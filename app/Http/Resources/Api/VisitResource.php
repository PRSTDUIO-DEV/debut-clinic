<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'visit_number' => $this->visit_number,
            'visit_date' => optional($this->visit_date)->toDateString(),
            'check_in_at' => optional($this->check_in_at)->toIso8601String(),
            'check_out_at' => optional($this->check_out_at)->toIso8601String(),
            'status' => $this->status,
            'source' => $this->source,
            'vital_signs' => $this->vital_signs,
            'chief_complaint' => $this->chief_complaint,
            'doctor_notes' => $this->doctor_notes,
            'total_amount' => (float) $this->total_amount,
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
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->uuid,
                'invoice_number' => $this->invoice->invoice_number,
                'subtotal' => (float) $this->invoice->subtotal,
                'discount_amount' => (float) $this->invoice->discount_amount,
                'vat_amount' => (float) $this->invoice->vat_amount,
                'total_amount' => (float) $this->invoice->total_amount,
                'status' => $this->invoice->status,
                'items' => $this->invoice->relationLoaded('items') ? $this->invoice->items->map(fn ($i) => [
                    'id' => $i->id,
                    'item_type' => $i->item_type,
                    'item_id' => $i->item_id,
                    'item_name' => $i->item_name,
                    'quantity' => (int) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'discount' => (float) $i->discount,
                    'total' => (float) $i->total,
                    'doctor_id' => $i->doctor_id,
                    'staff_id' => $i->staff_id,
                ]) : null,
                'payments' => $this->invoice->relationLoaded('payments') ? $this->invoice->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'method' => $p->method,
                    'amount' => (float) $p->amount,
                    'bank_id' => $p->bank_id,
                    'mdr_amount' => $p->mdr_amount,
                ]) : null,
            ] : null),
        ];
    }
}
