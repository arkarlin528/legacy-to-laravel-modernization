<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;

it('filters by legacy numeric status codes', function () {
    $customer = Customer::factory()->create();
    Order::factory()->for($customer)->status(OrderStatus::Shipped)->create();
    Order::factory()->for($customer)->status(OrderStatus::Open)->create();

    $json = $this->getJson("/api/orders?customerId={$customer->id}&status=3", legacyHeaders())->assertOk()->json();

    expect($json)->toHaveCount(1)
        ->and($json[0]['Status'])->toBe(3)
        ->and($json[0]['StatusText'])->toBe('Shipped');
});

it('returns an empty list for an unknown status code, like the stored procedure', function () {
    Order::factory()->create();

    $this->getJson('/api/orders?status=9', legacyHeaders())->assertOk()->assertExactJson([]);
});

it('creates an order with a sequence number and a bcmath total', function () {
    $customer = Customer::factory()->create();

    $json = $this->postJson('/api/orders', [
        'CustomerId' => $customer->id, 'Origin' => 'thlch', 'Destination' => 'SGSIN',
        'Lines' => [
            ['Description' => 'Freight', 'Quantity' => 3, 'UnitPrice' => 0.1],
            ['Description' => 'Fees', 'Quantity' => 1, 'UnitPrice' => 0.2],
        ],
    ], legacyHeaders())->assertCreated()->json();

    // Sequences are not transactional, so RefreshDatabase doesn't reset them: check the format only.
    expect($json['OrderNo'])->toMatch('/^ORD-'.now()->year.'-\d{6}$/')
        ->and($json['Total'])->toEqual(0.5) // 3 x 0.1 + 0.2, exact (no float drift)
        ->and($json['Origin'])->toBe('THLCH')
        ->and($json['Status'])->toBe(1)
        ->and($json['Lines'])->toHaveCount(2);
});

it('collapses nested line errors into one Lines entry', function () {
    $customer = Customer::factory()->create();

    $this->postJson('/api/orders', [
        'CustomerId' => $customer->id, 'Origin' => 'THLCH', 'Destination' => 'SGSIN',
        'Lines' => [['Description' => '', 'Quantity' => 0, 'UnitPrice' => -1]],
    ], legacyHeaders())
        ->assertStatus(400)
        ->assertExactJson([
            'Message' => 'The request is invalid.',
            'ModelState' => ['Lines' => ['Each line needs a Description, a Quantity > 0 and a UnitPrice > 0.']],
        ]);
});

it('refuses to cancel shipped or already cancelled orders', function (OrderStatus $status, string $message) {
    $order = Order::factory()->status($status)->create();

    $this->postJson("/api/orders/{$order->id}/cancel", [], legacyHeaders())
        ->assertStatus(409)
        ->assertExactJson(['Message' => $message]);
})->with([
    'shipped' => [OrderStatus::Shipped, 'Shipped orders cannot be cancelled'],
    'cancelled' => [OrderStatus::Cancelled, 'Order is already cancelled'],
]);

it('cancels an open order', function () {
    $order = Order::factory()->withLines()->create();

    $this->postJson("/api/orders/{$order->id}/cancel", [], legacyHeaders())
        ->assertOk()
        ->assertJson(['Status' => 4, 'StatusText' => 'Cancelled']);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('answers unknown routes and methods in the legacy format', function () {
    $this->getJson('/api/nope', legacyHeaders())
        ->assertNotFound()
        ->assertExactJson(['Message' => 'No HTTP resource was found that matches the request URI.']);

    $this->deleteJson('/api/orders/1', [], legacyHeaders())->assertStatus(405);
});
