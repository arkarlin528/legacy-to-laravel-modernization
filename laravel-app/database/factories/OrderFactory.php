<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_no' => 'ORD-'.now()->year.'-'.fake()->unique()->numerify('9#####'),
            'customer_id' => Customer::factory(),
            'ordered_at' => fake()->dateTimeBetween('-1 year'),
            'status' => OrderStatus::Open,
            'origin' => 'THLCH',
            'destination' => 'SGSIN',
            'remarks' => null,
            'total' => '0.00',
            'currency' => 'USD',
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /** Adds lines and sets the total to their sum, like OrderService does. */
    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $total = '0.00';
            for ($i = 1; $i <= $count; $i++) {
                $line = OrderLine::create([
                    'order_id' => $order->id,
                    'line_no' => $i,
                    'description' => "Line {$i}",
                    'quantity' => $i,
                    'unit_price' => '100.50',
                    'amount' => bcmul((string) $i, '100.50', 2),
                ]);
                $total = bcadd($total, $line->amount, 2);
            }
            $order->update(['total' => $total]);
        });
    }
}
