<?php

namespace App\Http\Requests\LegacyV1;

use Illuminate\Foundation\Http\FormRequest;

/** v1 body: PascalCase fields, legacy validation messages (clients may show them to users). */
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The legacy API defaulted the country to Thailand.
        $this->mergeIfMissing(['Country' => 'TH']);
    }

    public function rules(): array
    {
        return [
            'Code' => ['required', 'string', 'max:10'],
            'Name' => ['required', 'string', 'max:100'],
            'Email' => ['nullable', 'string', 'max:200', 'regex:/@/'],
            'Phone' => ['nullable', 'string', 'max:30'],
            'Country' => ['required', 'string', 'size:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'Code.*' => 'Code is required (max 10 characters).',
            'Name.*' => 'Name is required (max 100 characters).',
            'Email.*' => 'Email is not valid.',
            'Country.*' => 'Country must be a 2-letter code.',
        ];
    }
}
