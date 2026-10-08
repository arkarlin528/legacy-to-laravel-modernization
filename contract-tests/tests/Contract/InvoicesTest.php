<?php

it('lists a customer\'s invoices with the exact v1 shape', function () {
    $invoices = body(api()->get('/api/customers/3/invoices'));

    expect($invoices)->not->toBeEmpty();
    foreach ($invoices as $invoice) {
        expect(array_keys($invoice))->toBe(INVOICE_KEYS)
            ->and($invoice['InvoiceDate'])->toMatch(LEGACY_DATE)
            ->and($invoice['IsPaid'])->toBeBool();
    }
});

it('keeps legacy invoices that are paid but have no payment date', function () {
    // Invoice 9 has PaidFlag = 1 and PaidDt = NULL in the legacy data. Clients saw exactly this.
    $all = [];
    for ($customer = 1; $customer <= 25; $customer++) {
        $all = [...$all, ...body(api()->get("/api/customers/{$customer}/invoices"))];
    }
    $invoice = collect_by_id($all, 9);

    expect($invoice['IsPaid'])->toBeTrue()
        ->and($invoice['PaidDate'])->toBeNull();
});

it('returns 404 for invoices of an unknown customer', function () {
    expect(api()->get('/api/customers/999999/invoices')->getStatusCode())->toBe(404);
});

function collect_by_id(array $invoices, int $id): array
{
    foreach ($invoices as $invoice) {
        if ($invoice['InvoiceId'] === $id) {
            return $invoice;
        }
    }
    throw new RuntimeException("Invoice {$id} not found");
}
