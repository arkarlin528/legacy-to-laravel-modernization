<?php

namespace App\Http\Controllers\LegacyV1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Legacy\LegacyApi;
use App\Http\Legacy\LegacyPresenter;
use App\Http\Requests\LegacyV1\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Legacy v1 (compatible)
 */
class OrderController extends Controller
{
    /**
     * List orders.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @queryParam customerId integer Filter by customer. Example: 3
     * @queryParam status integer Legacy status code: 1 Open, 2 Confirmed, 3 Shipped, 4 Cancelled. Example: 3
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->orderBy('id');

        if ($request->filled('customerId')) {
            $query->where('customer_id', (int) $request->query('customerId'));
        }
        if ($request->filled('status')) {
            $status = OrderStatus::tryFrom(match ((int) $request->query('status')) {
                1 => 'open', 2 => 'confirmed', 3 => 'shipped', 4 => 'cancelled', default => '',
            });
            if (! $status) {
                return LegacyApi::ok([]); // the stored procedure simply matched nothing
            }
            $query->where('status', $status);
        }

        return LegacyApi::ok($query->get()->map(LegacyPresenter::orderHeader(...)));
    }

    /**
     * Get one order with its lines.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @response {"OrderId":17,"OrderNo":"ORD-2023-000006","CustomerId":3,"OrderDate":"2023-02-18T08:58:00","Status":3,"StatusText":"Shipped","Origin":"THBKK","Destination":"VNSGN","Total":643.45,"Currency":"USD","Remarks":"Customer will collect from port","Lines":[{"LineNo":1,"Description":"Documentation fee","Quantity":3,"UnitPrice":29.67,"Amount":89.01}]}
     * @response 404 {"Message":"Order not found"}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with('lines')->find($id);

        return $order
            ? LegacyApi::ok(LegacyPresenter::orderDetail($order))
            : LegacyApi::error(404, 'Order not found');
    }

    /**
     * Create an order.
     *
     * The total is calculated from the lines and the order number is assigned by the server.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @bodyParam CustomerId integer required Example: 3
     * @bodyParam Origin string required 5-letter UN/LOCODE. Example: THLCH
     * @bodyParam Destination string required Example: SGSIN
     * @bodyParam Currency string Defaults to USD. Example: USD
     * @bodyParam Remarks string Example: Fragile goods
     * @bodyParam Lines object[] required
     * @bodyParam Lines[].Description string required Example: Ocean freight 20GP
     * @bodyParam Lines[].Quantity integer required Example: 1
     * @bodyParam Lines[].UnitPrice number required Example: 950
     */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create(
            Customer::findOrFail($request->validated('CustomerId')),
            $request->validated('Origin'), $request->validated('Destination'), $request->validated('Currency'),
            $request->validated('Remarks'), $request->lines());

        return LegacyApi::ok(LegacyPresenter::orderDetail($order), 201, ['Location' => "/api/orders/{$order->id}"]);
    }

    /**
     * Cancel an open or confirmed order.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @response 409 {"Message":"Shipped orders cannot be cancelled"}
     */
    public function cancel(int $id, OrderService $orders): JsonResponse
    {
        $order = Order::find($id);
        if (! $order) {
            return LegacyApi::error(404, 'Order not found');
        }

        return LegacyApi::ok(LegacyPresenter::orderDetail($orders->cancel($order)));
    }
}
