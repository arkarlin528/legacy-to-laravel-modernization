<?php

use App\Enums\OrderStatus;
use App\Http\Legacy\LegacyApi;
use App\Models\Customer;
use App\Services\OrderService;
use Illuminate\Support\Carbon;

it('maps every status to and from its legacy code', function () {
    foreach (OrderStatus::cases() as $status) {
        expect(OrderStatus::fromLegacyCode($status->legacyCode()))->toBe($status);
    }
    expect(fn () => OrderStatus::fromLegacyCode(9))->toThrow(ValueError::class);
});

it('allows cancelling only open and confirmed orders', function () {
    expect(OrderStatus::Open->canBeCancelled())->toBeTrue()
        ->and(OrderStatus::Confirmed->canBeCancelled())->toBeTrue()
        ->and(OrderStatus::Shipped->canBeCancelled())->toBeFalse()
        ->and(OrderStatus::Cancelled->canBeCancelled())->toBeFalse();
});

it('formats dates the way the legacy API did', function () {
    expect(LegacyApi::date(Carbon::parse('2023-12-31 20:00:00', 'UTC')))->toBe('2024-01-01T03:00:00')
        ->and(LegacyApi::date(null))->toBeNull();
});

it('numbers orders from the PostgreSQL sequence, continuing where legacy stopped', function () {
    DB::statement("SELECT setval('order_number_seq', 27, false)");
    $customer = Customer::factory()->create();
    $service = app(OrderService::class);

    $first = $service->create($customer, 'THLCH', 'SGSIN', 'USD', null, [['description' => 'x', 'quantity' => 1, 'unit_price' => '1']]);
    $second = $service->create($customer, 'THLCH', 'SGSIN', 'USD', null, [['description' => 'x', 'quantity' => 1, 'unit_price' => '1']]);

    expect($first->order_no)->toBe(sprintf('ORD-%d-000027', now()->year))
        ->and($second->order_no)->toBe(sprintf('ORD-%d-000028', now()->year));
});
