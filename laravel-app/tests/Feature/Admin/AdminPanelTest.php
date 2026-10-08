<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create()); // factory users are verified
});

it('shows the main admin pages', function (string $url) {
    Order::factory()->withLines()->create();

    $this->get($url)->assertOk();
})->with(['/admin', '/admin/orders', '/admin/customers', '/admin/invoices']);

it('keeps unverified users out', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/admin/orders')->assertForbidden();
});

it('lists orders in the table', function () {
    $orders = Order::factory()->count(3)->create();

    Livewire::test(ListOrders::class)->assertCanSeeTableRecords($orders);
});

it('creates an order through OrderService', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id, 'origin' => 'THLCH', 'destination' => 'SGSIN', 'currency' => 'USD',
            'lines' => [['description' => 'Freight', 'quantity' => 2, 'unit_price' => '10.25']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::sole();
    expect($order->total)->toBe('20.50')
        ->and($order->order_no)->toStartWith('ORD-')
        ->and($order->status)->toBe(OrderStatus::Open);
});

it('cancels from the view page, and hides the action once shipped', function () {
    $open = Order::factory()->withLines()->create();
    $shipped = Order::factory()->status(OrderStatus::Shipped)->create();

    Livewire::test(ViewOrder::class, ['record' => $open->getRouteKey()])->callAction('cancel');
    expect($open->fresh()->status)->toBe(OrderStatus::Cancelled);

    Livewire::test(ViewOrder::class, ['record' => $shipped->getRouteKey()])->assertActionHidden('cancel');
});
