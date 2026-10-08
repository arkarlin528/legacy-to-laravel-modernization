<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The business rules that used to live in usp_InsertOrderHeader / usp_InsertOrderLine / usp_CancelOrder.
 * Both API versions (v1 compatibility and v2) and the Filament admin call this one class.
 */
class OrderService
{
    /**
     * @param  array<int, array{description: string, quantity: int, unit_price: string|float}>  $lines
     */
    public function create(Customer $customer, string $origin, string $destination, string $currency,
        ?string $remarks, array $lines): Order
    {
        return DB::transaction(function () use ($customer, $origin, $destination, $currency, $remarks, $lines) {
            $now = now();
            $order = Order::create([
                'order_no' => $this->nextOrderNumber($now->year),
                'customer_id' => $customer->id,
                'ordered_at' => $now,
                'status' => OrderStatus::Open,
                'origin' => strtoupper($origin),
                'destination' => strtoupper($destination),
                'remarks' => $remarks,
                'total' => '0.00',
                'currency' => strtoupper($currency),
            ]);

            $total = '0.00';
            foreach (array_values($lines) as $i => $line) {
                // Money in strings + bcmath: never float arithmetic for amounts.
                $unitPrice = number_format((float) $line['unit_price'], 2, '.', '');
                $amount = bcmul((string) $line['quantity'], $unitPrice, 2);
                $order->lines()->create([
                    'line_no' => $i + 1,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);
                $total = bcadd($total, $amount, 2);
            }

            $order->update(['total' => $total]);

            return $order->load('lines');
        });
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Lock the row so two cancels (or a cancel and a ship) can't interleave.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === OrderStatus::Shipped) {
                throw new BusinessRuleException('Shipped orders cannot be cancelled');
            }
            if ($order->status === OrderStatus::Cancelled) {
                throw new BusinessRuleException('Order is already cancelled');
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            return $order->load('lines');
        });
    }

    /** Same format as the legacy system: ORD-{year}-{6 digits}, from a PostgreSQL sequence. */
    private function nextOrderNumber(int $year): string
    {
        $next = DB::selectOne("SELECT nextval('order_number_seq') AS n")->n;

        return sprintf('ORD-%d-%06d', $year, $next);
    }
}
