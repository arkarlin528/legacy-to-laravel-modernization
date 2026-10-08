<?php

it('rejects a missing API key with the Web API 2 message', function () {
    $response = api(apiKey: '')->get('/api/customers');

    expect($response->getStatusCode())->toBe(401)
        ->and(body($response))->toBe(['Message' => 'Authorization has been denied for this request.']);
});

it('rejects a wrong API key', function () {
    expect(api(apiKey: 'wrong-key')->get('/api/orders')->getStatusCode())->toBe(401);
});

it('returns 404 with a Message for an unknown customer', function () {
    $response = api()->get('/api/customers/999999');

    expect($response->getStatusCode())->toBe(404)
        ->and(body($response))->toBe(['Message' => 'Customer not found']);
});

it('returns 404 with a Message for an unknown order', function () {
    $response = api()->get('/api/orders/999999');

    expect($response->getStatusCode())->toBe(404)
        ->and(body($response))->toBe(['Message' => 'Order not found']);
});

it('answers JSON', function () {
    expect(api()->get('/api/customers/1')->getHeaderLine('Content-Type'))->toContain('application/json');
});
