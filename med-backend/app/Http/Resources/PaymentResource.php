<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'invoice_id'      => $this->invoice_id,
            'amount'          => (float) $this->amount,
            'method'          => $this->method,
            'status'          => $this->status,
            'transaction_ref' => $this->transaction_ref,
            'paid_at'         => $this->paid_at?->toIso8601String(),
        ];
    }
}
