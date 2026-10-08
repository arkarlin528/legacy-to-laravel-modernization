<?php

namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'origin' => ['required', 'string', 'size:5', 'alpha'],
            'destination' => ['required', 'string', 'size:5', 'alpha', 'different:origin'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
