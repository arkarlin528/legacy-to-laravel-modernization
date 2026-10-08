<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

it('issues a Sanctum token for valid credentials only', function () {
    $user = User::factory()->create(['email' => 'api@example.com', 'password' => 'correct-horse']);

    $this->postJson('/api/v2/tokens', ['email' => 'api@example.com', 'password' => 'wrong', 'device_name' => 'cli'])
        ->assertUnprocessable();

    $token = $this->postJson('/api/v2/tokens', ['email' => 'api@example.com', 'password' => 'correct-horse', 'device_name' => 'cli'])
        ->assertCreated()
        ->json('token');

    $this->withToken($token)->getJson('/api/v2/customers')->assertOk();
    expect($user->tokens()->count())->toBe(1);
});

it('requires a token and does not accept the legacy key', function () {
    $this->getJson('/api/v2/customers', legacyHeaders())
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('pages and searches customers in snake_case', function () {
    actingAsApiUser();
    Customer::factory()->create(['name' => 'Chao Phraya Rice Exports', 'code' => 'CPRE']);
    // Fixed filler names: random company names like "Price LLC" would also match "rice".
    Customer::factory()->count(30)->sequence(fn ($s) => ['name' => "Filler Customer {$s->index}"])->create();

    $this->getJson('/api/v2/customers?search=rice')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'CPRE')
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'is_active', 'created_at', 'orders_count']], 'links', 'meta']);

    $this->getJson('/api/v2/customers?per_page=10')->assertJsonPath('meta.total', 31)->assertJsonCount(10, 'data');
});

it('returns UTC ISO-8601 timestamps and money as strings', function () {
    actingAsApiUser();
    $order = Order::factory()->withLines()->create(['ordered_at' => '2026-03-01 02:00:00+00']);

    $this->getJson("/api/v2/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.ordered_at', '2026-03-01T02:00:00Z')
        ->assertJsonPath('data.total', ['amount' => '301.50', 'currency' => 'USD'])
        ->assertJsonPath('data.status', 'open')
        ->assertJsonCount(2, 'data.lines');
});

it('filters orders by status name', function () {
    actingAsApiUser();
    Order::factory()->status(OrderStatus::Shipped)->count(2)->create();
    Order::factory()->status(OrderStatus::Open)->create();

    $this->getJson('/api/v2/orders?status=shipped')->assertJsonCount(2, 'data');
    $this->getJson('/api/v2/orders?status=lost')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('creates and cancels an order through the same service as v1', function () {
    actingAsApiUser();
    $customer = Customer::factory()->create();

    $created = $this->postJson('/api/v2/orders', [
        'customer_id' => $customer->id, 'origin' => 'THLCH', 'destination' => 'SGSIN',
        'lines' => [['description' => 'Freight', 'quantity' => 2, 'unit_price' => 950.25]],
    ])->assertCreated()->json('data');

    expect($created['total'])->toBe(['amount' => '1900.50', 'currency' => 'USD']);

    $this->postJson("/api/v2/orders/{$created['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->postJson("/api/v2/orders/{$created['id']}/cancel")
        ->assertStatus(409)
        ->assertExactJson(['message' => 'Order is already cancelled']);
});

it('validates v2 orders with standard Laravel errors', function () {
    actingAsApiUser();

    $this->postJson('/api/v2/orders', ['origin' => 'THLCH', 'destination' => 'THLCH', 'lines' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'destination', 'lines']);
});
