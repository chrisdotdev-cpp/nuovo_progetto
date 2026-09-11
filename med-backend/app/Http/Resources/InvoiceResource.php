<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'number'      => $this->number,
            'patient_id'  => $this->patient_id,
            'issue_date'  => $this->issue_date?->toDateString(),
            'due_date'    => $this->due_date?->toDateString(),
            'subtotal'    => (float) $this->subtotal,
            'tax_rate'    => (float) $this->tax_rate,
            'tax_amount'  => (float) $this->tax_amount,
            'total'       => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'balance'     => $this->balance,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'items'       => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments'    => PaymentResource::collection($this->whenLoaded('payments')),
            'patient'     => new PatientResource($this->whenLoaded('patient')),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
