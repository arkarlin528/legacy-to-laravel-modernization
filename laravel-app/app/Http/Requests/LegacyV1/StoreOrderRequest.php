<?php

namespace App\Http\Requests\LegacyV1;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    private const LINE_MESSAGE = 'Each line needs a Description, a Quantity > 0 and a UnitPrice > 0.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['Currency' => 'USD']);
    }

    public function rules(): array
    {
        return [
            'CustomerId' => ['bail', 'required', 'integer', 'min:1', 'exists:customers,id'],
            'Origin' => ['required', 'string', 'size:5'],
            'Destination' => ['required', 'string', 'size:5'],
            'Currency' => ['required', 'string', 'size:3'],
            'Remarks' => ['nullable', 'string'],
            'Lines' => ['required', 'array', 'min:1'],
            'Lines.*.Description' => ['required', 'string', 'max:200'],
            'Lines.*.Quantity' => ['required', 'integer', 'min:1'],
            'Lines.*.UnitPrice' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'CustomerId.exists' => 'Customer does not exist.',
            'CustomerId.*' => 'CustomerId is required.',
            'Origin.*' => 'Origin must be a 5-letter port code.',
            'Destination.*' => 'Destination must be a 5-letter port code.',
            'Currency.*' => 'Currency must be a 3-letter code.',
            'Lines.required' => 'At least one line is required.',
            'Lines.array' => 'At least one line is required.',
            'Lines.min' => 'At least one line is required.',
            'Lines.*.*' => self::LINE_MESSAGE,
        ];
    }

    /** @return array<int, array{description: string, quantity: int, unit_price: string}> */
    public function lines(): array
    {
        return array_map(fn (array $l) => [
            'description' => $l['Description'],
            'quantity' => (int) $l['Quantity'],
            'unit_price' => (string) $l['UnitPrice'],
        ], $this->validated('Lines'));
    }
}
