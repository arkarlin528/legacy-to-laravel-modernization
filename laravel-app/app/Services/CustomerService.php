<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;

class CustomerService
{
    public function create(string $code, string $name, ?string $email, ?string $phone, string $country): Customer
    {
        $code = strtoupper(trim($code));
        if (Customer::where('code', $code)->exists()) {
            throw new BusinessRuleException('Customer code already exists');
        }

        try {
            return Customer::create([
                'code' => $code,
                'name' => trim($name),
                // New data is stored clean; the migrator cleaned the old data the same way.
                'email' => $email === null ? null : strtolower(trim($email)),
                'phone' => $phone,
                'country' => strtoupper($country),
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same code at the same moment: the unique index decides.
            throw new BusinessRuleException('Customer code already exists');
        }
    }
}
