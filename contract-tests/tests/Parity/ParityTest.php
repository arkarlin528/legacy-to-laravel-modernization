<?php

/*
 * Side-by-side comparison: the same GET requests against the legacy service and the new one must
 * return identical status codes and JSON. Run during the shadow phase, before switching any route:
 *
 *   LEGACY_URL=http://127.0.0.1:5100 CANDIDATE_URL=http://127.0.0.1:8000 vendor/bin/pest --testsuite=Parity
 *
 * The only accepted difference is email normalisation (trimmed, lower-case), agreed with the
 * business and documented in docs/case-study.md.
 */

beforeEach(function () {
    if (! getenv('LEGACY_URL') || ! getenv('CANDIDATE_URL')) {
        $this->markTestSkipped('Set LEGACY_URL and CANDIDATE_URL to run the parity check.');
    }
});

function normalise(mixed $value): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $key === 'Email' && is_string($item) ? strtolower(trim($item)) : normalise($item);
        }

        return $out;
    }

    return $value;
}

dataset('read endpoints', function () {
    yield '/api/customers';
    yield '/api/orders';
    yield '/api/orders?customerId=3&status=3';
    yield '/api/orders?status=4';
    yield '/api/customers/999999';
    yield '/api/orders/999999';
    foreach (range(1, 25) as $id) {
        yield "/api/customers/{$id}";
        yield "/api/customers/{$id}/invoices";
    }
    foreach (range(1, 160) as $id) {
        yield "/api/orders/{$id}";
    }
});

it('answers exactly like the legacy service', function (string $path) {
    $legacy = api(getenv('LEGACY_URL'))->get($path);
    $candidate = api(getenv('CANDIDATE_URL'))->get($path);

    expect($candidate->getStatusCode())->toBe($legacy->getStatusCode())
        ->and(normalise(body($candidate)))->toBe(normalise(body($legacy)));
})->with('read endpoints');
