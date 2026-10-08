<?php

namespace App\Http\Controllers\LegacyV1;

use App\Http\Controllers\Controller;
use App\Http\Legacy\LegacyApi;
use App\Http\Legacy\LegacyPresenter;
use App\Http\Requests\LegacyV1\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;

/**
 * @group Legacy v1 (compatible)
 *
 * The original customer endpoints, served by the new system with an identical contract.
 * Authenticate with the legacy `X-Api-Key` header. New integrations should use /api/v2.
 */
class CustomerController extends Controller
{
    /**
     * List all customers.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     */
    public function index(): JsonResponse
    {
        return LegacyApi::ok(Customer::orderBy('id')->get()->map(LegacyPresenter::customer(...)));
    }

    /**
     * Get one customer.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @response {"CustomerId":4,"Code":"SGCT","Name":"Saigon Coffee Traders","Email":"ops@sgct.example.com","Phone":"+84 2100 1657","Country":"VN","IsActive":true,"CreatedDate":"2020-04-20T09:53:00"}
     * @response 404 {"Message":"Customer not found"}
     * @response 401 {"Message":"Authorization has been denied for this request."}
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::find($id);

        return $customer
            ? LegacyApi::ok(LegacyPresenter::customer($customer))
            : LegacyApi::error(404, 'Customer not found');
    }

    /**
     * Create a customer.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @response 409 {"Message":"Customer code already exists"}
     * @response 400 {"Message":"The request is invalid.","ModelState":{"Name":["Name is required (max 100 characters)."]}}
     *
     * @bodyParam Code string required Up to 10 characters, unique. Example: NEWCO
     * @bodyParam Name string required Example: New Company Ltd
     * @bodyParam Email string Example: ops@newco.example.com
     * @bodyParam Phone string Example: +66 2100 1000
     * @bodyParam Country string 2-letter code, defaults to TH. Example: TH
     */
    public function store(StoreCustomerRequest $request, CustomerService $customers): JsonResponse
    {
        $customer = $customers->create(
            $request->validated('Code'), $request->validated('Name'), $request->validated('Email'),
            $request->validated('Phone'), $request->validated('Country'));

        return LegacyApi::ok(LegacyPresenter::customer($customer), 201, ['Location' => "/api/customers/{$customer->id}"]);
    }

    /**
     * List a customer's invoices.
     *
     * @header X-Api-Key {LEGACY_API_KEY}
     *
     * @response [{"InvoiceId":14,"InvoiceNo":"INV-2024-00014","OrderId":16,"InvoiceDate":"2024-08-10T17:57:00","DueDate":"2024-09-09T17:57:00","Amount":171.42,"IsPaid":true,"PaidDate":"2024-09-03T17:57:00"}]
     */
    public function invoices(int $id): JsonResponse
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return LegacyApi::error(404, 'Customer not found');
        }

        return LegacyApi::ok($customer->invoices()->orderBy('invoices.id')->get()->map(LegacyPresenter::invoice(...)));
    }
}
