<?php

namespace App\Http\Resources\V2;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'status' => $this->status->value,
            'ordered_at' => $this->ordered_at->toIso8601ZuluString(),
            'origin' => $this->origin,
            'destination' => $this->destination,
            // Money as a string with 2 decimals: no float rounding surprises in any client language.
            'total' => ['amount' => $this->total, 'currency' => $this->currency],
            'remarks' => $this->remarks,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'line_no' => $l->line_no,
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit_price' => $l->unit_price,
                'amount' => $l->amount,
            ])),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'amount' => $i->amount,
                'issued_at' => $i->issued_at->toIso8601ZuluString(),
                'due_at' => $i->due_at->toIso8601ZuluString(),
                'is_paid' => $i->is_paid,
                'paid_at' => $i->paid_at?->toIso8601ZuluString(),
            ])),
            'links' => ['self' => url("/api/v2/orders/{$this->id}")],
        ];
    }
}
