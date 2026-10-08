<?php

namespace App\Http\Controllers\V2;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreOrderRequest;
use App\Http\Resources\V2\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * @group v2 Orders
 *
 * @authenticated
 */
class OrderController extends Controller
{
    /**
     * List orders (paginated, newest first).
     *
     * @queryParam customer_id integer Example: 3
     * @queryParam status string open, confirmed, shipped or cancelled. Example: shipped
     * @queryParam per_page integer 1-100, default 25. Example: 25
     *
     * @apiResourceCollection App\Http\Resources\V2\OrderResource
     *
     * @apiResourceModel App\Models\Order paginate=25 with=customer
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Query parameters (tells Scribe these are not a request body)
        $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $orders = Order::query()
            ->with('customer')
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('ordered_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Get an order with its lines and invoices.
     *
     * @apiResource App\Http\Resources\V2\OrderResource
     *
     * @apiResourceModel App\Models\Order with=customer,lines
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['customer', 'lines', 'invoices']));
    }

    /** Create an order. */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create(
            Customer::findOrFail($request->validated('customer_id')),
            $request->validated('origin'), $request->validated('destination'),
            $request->validated('currency', 'USD'), $request->validated('remarks'),
            $request->validated('lines'));

        return (new OrderResource($order->load('customer')))->response()->setStatusCode(201);
    }

    /**
     * Cancel an order.
     *
     * @response 409 {"message":"Shipped orders cannot be cancelled"}
     */
    public function cancel(Order $order, OrderService $orders): OrderResource
    {
        return new OrderResource($orders->cancel($order)->load('customer'));
    }
}
