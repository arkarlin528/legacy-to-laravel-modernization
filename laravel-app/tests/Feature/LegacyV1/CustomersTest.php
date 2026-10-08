<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Carbon;

it('rejects requests without the legacy API key, in the legacy format', function () {
    $this->getJson('/api/customers')
        ->assertUnauthorized()
        ->assertExactJson(['Message' => 'Authorization has been denied for this request.']);
});

it('presents a customer in the exact v1 shape, with UTC converted back to Bangkok time', function () {
    $customer = Customer::factory()->create([
        'code' => 'SGCT', 'name' => 'Saigon Coffee Traders', 'country' => 'VN',
        'created_at' => Carbon::parse('2020-04-20 02:53:00', 'UTC'), // 09:53 in Bangkok
    ]);

    $json = $this->getJson("/api/customers/{$customer->id}", legacyHeaders())->assertOk()->json();

    expect(array_keys($json))->toBe(['CustomerId', 'Code', 'Name', 'Email', 'Phone', 'Country', 'IsActive', 'CreatedDate'])
        ->and($json['CreatedDate'])->toBe('2020-04-20T09:53:00')
        ->and($json['IsActive'])->toBeTrue();
});

it('returns the legacy 404 body', function () {
    $this->getJson('/api/customers/12345', legacyHeaders())
        ->assertNotFound()
        ->assertExactJson(['Message' => 'Customer not found']);
});

it('creates a customer, normalising code and email', function () {
    $this->postJson('/api/customers', [
        'Code' => 'newco', 'Name' => ' New Co ', 'Email' => '  OPS@NEWCO.EXAMPLE.COM ',
    ], legacyHeaders())
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJson(['Code' => 'NEWCO', 'Name' => 'New Co', 'Email' => 'ops@newco.example.com', 'Country' => 'TH']);
});

it('folds validation errors into a Web API 2 ModelState', function () {
    $this->postJson('/api/customers', ['Code' => 'TOO-LONG-CODE', 'Country' => 'THA'], legacyHeaders())
        ->assertStatus(400)
        ->assertExactJson([
            'Message' => 'The request is invalid.',
            'ModelState' => [
                'Code' => ['Code is required (max 10 characters).'],
                'Name' => ['Name is required (max 100 characters).'],
                'Country' => ['Country must be a 2-letter code.'],
            ],
        ]);
});

it('returns 409 for a duplicate code', function () {
    Customer::factory()->create(['code' => 'DUP']);

    $this->postJson('/api/customers', ['Code' => 'dup', 'Name' => 'Again'], legacyHeaders())
        ->assertStatus(409)
        ->assertExactJson(['Message' => 'Customer code already exists']);
});

it('lists a customer\'s invoices across their orders', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create();
    Invoice::create([
        'invoice_no' => 'INV-2026-00001', 'order_id' => $order->id, 'amount' => '150.00',
        'issued_at' => Carbon::parse('2026-01-05 17:00', 'UTC'), 'due_at' => Carbon::parse('2026-02-04 17:00', 'UTC'),
        'is_paid' => true, 'paid_at' => null,
    ]);

    $json = $this->getJson("/api/customers/{$customer->id}/invoices", legacyHeaders())->assertOk()->json();

    expect($json)->toHaveCount(1)
        ->and($json[0])->toMatchArray([
            'InvoiceNo' => 'INV-2026-00001', 'Amount' => 150, 'IsPaid' => true, 'PaidDate' => null,
            'InvoiceDate' => '2026-01-06T00:00:00', // 17:00 UTC = midnight in Bangkok
        ]);
});

it('keeps money as a decimal in JSON', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create(['total' => '958.00']);

    $raw = $this->getJson("/api/orders/{$order->id}", legacyHeaders())->getContent();

    expect($raw)->toContain('"Total":958.0');
});
