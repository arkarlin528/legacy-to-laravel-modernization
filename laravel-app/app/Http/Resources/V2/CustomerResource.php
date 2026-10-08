<?php

namespace App\Http\Resources\V2;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'orders_count' => $this->whenCounted('orders'),
        ];
    }
}
