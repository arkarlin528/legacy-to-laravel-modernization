<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group v2 Customers
 *
 * @authenticated
 */
class CustomerController extends Controller
{
    /**
     * List customers (paginated).
     *
     * @queryParam search string Matches code or name. Example: rice
     * @queryParam active boolean Example: true
     * @queryParam per_page integer 1-100, default 25. Example: 25
     *
     * @apiResourceCollection App\Http\Resources\V2\CustomerResource
     *
     * @apiResourceModel App\Models\Customer paginate=25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Query parameters (tells Scribe these are not a request body)
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'ilike', '%'.$request->string('search').'%')
                ->orWhere('name', 'ilike', '%'.$request->string('search').'%')))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * Get one customer.
     *
     * @apiResource App\Http\Resources\V2\CustomerResource
     *
     * @apiResourceModel App\Models\Customer
     */
    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->loadCount('orders'));
    }
}
