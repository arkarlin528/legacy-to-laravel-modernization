<?php

it('lists orders with the exact v1 shape', function () {
    $orders = body(api()->get('/api/orders'));

    expect(count($orders))->toBeGreaterThanOrEqual(160);
    foreach (array_slice($orders, 0, 20) as $order) {
        expect(array_keys($order))->toBe(ORDER_KEYS)
            ->and($order['Status'])->toBeIn([1, 2, 3, 4])
            ->and($order['OrderNo'])->toMatch('/^ORD-\d{4}-\d{6}$/')
            ->and($order['OrderDate'])->toMatch(LEGACY_DATE);
    }
});

it('filters by customer and legacy status code', function () {
    $orders = body(api()->get('/api/orders', ['query' => ['customerId' => 3, 'status' => 3]]));

    expect($orders)->not->toBeEmpty();
    foreach ($orders as $order) {
        expect($order['CustomerId'])->toBe(3)
            ->and($order['Status'])->toBe(3)
            ->and($order['StatusText'])->toBe('Shipped');
    }
});

it('returns a known order exactly, including its legacy total', function () {
    $order = body(api()->get('/api/orders/17'));

    expect(array_keys($order))->toBe(ORDER_DETAIL_KEYS)
        ->and($order)->toMatchArray([
            'OrderNo' => 'ORD-2023-000006',
            'CustomerId' => 3,
            'OrderDate' => '2023-02-18T08:58:00',
            'Status' => 3,
            'StatusText' => 'Shipped',
            'Origin' => 'THBKK',
            'Destination' => 'VNSGN',
            // 10.00 more than the lines: an old bug. Kept, because that is what was invoiced.
            'Total' => 643.45,
            'Currency' => 'USD',
            'Remarks' => 'Customer will collect from port',
        ])
        ->and($order['Lines'])->toHaveCount(4)
        ->and(array_keys($order['Lines'][0]))->toBe(LINE_KEYS)
        ->and($order['Lines'][3])->toBe([
            'LineNo' => 4, 'Description' => 'Cargo insurance', 'Quantity' => 3, 'UnitPrice' => 116.29, 'Amount' => 348.87,
        ]);
});

it('creates an order, numbers it and totals the lines', function () {
    $response = api()->post('/api/orders', ['json' => [
        'CustomerId' => 3, 'Origin' => 'thlch', 'Destination' => 'SGSIN', 'Remarks' => 'contract test',
        'Lines' => [
            ['Description' => 'Ocean freight 20GP', 'Quantity' => 2, 'UnitPrice' => 950.5],
            ['Description' => 'Customs clearance', 'Quantity' => 1, 'UnitPrice' => 120],
        ],
    ]]);

    expect($response->getStatusCode())->toBe(201);
    $order = body($response);
    expect(array_keys($order))->toBe(ORDER_DETAIL_KEYS)
        ->and($order['OrderNo'])->toMatch('/^ORD-'.date('Y').'-\d{6}$/')
        ->and($order)->toMatchArray([
            'Status' => 1, 'StatusText' => 'Open', 'Origin' => 'THLCH', 'Destination' => 'SGSIN',
            'Total' => 2021, 'Currency' => 'USD',
        ])
        ->and($order['Lines'][0]['Amount'])->toEqual(1901)
        ->and($response->getHeaderLine('Location'))->toBe("/api/orders/{$order['OrderId']}");
});

it('cancels an open order once, then refuses', function () {
    $order = body(api()->post('/api/orders', ['json' => [
        'CustomerId' => 5, 'Origin' => 'MYPKG', 'Destination' => 'IDJKT',
        'Lines' => [['Description' => 'Trucking to port', 'Quantity' => 1, 'UnitPrice' => 200]],
    ]]));

    $cancelled = api()->post("/api/orders/{$order['OrderId']}/cancel");
    expect($cancelled->getStatusCode())->toBe(200)
        ->and(body($cancelled))->toMatchArray(['Status' => 4, 'StatusText' => 'Cancelled']);

    $again = api()->post("/api/orders/{$order['OrderId']}/cancel");
    expect($again->getStatusCode())->toBe(409)
        ->and(body($again))->toBe(['Message' => 'Order is already cancelled']);
});

it('refuses to cancel a shipped order', function () {
    $response = api()->post('/api/orders/17/cancel');

    expect($response->getStatusCode())->toBe(409)
        ->and(body($response))->toBe(['Message' => 'Shipped orders cannot be cancelled']);
});

it('validates new orders', function () {
    $response = api()->post('/api/orders', ['json' => [
        'CustomerId' => 3, 'Origin' => 'TH', 'Destination' => 'SGSIN', 'Lines' => [],
    ]]);

    expect($response->getStatusCode())->toBe(400);
    $state = body($response)['ModelState'];
    expect($state)->toHaveKeys(['Origin', 'Lines'])
        ->and($state['Lines'])->toBe(['At least one line is required.']);
});

it('rejects lines without a positive quantity', function () {
    $response = api()->post('/api/orders', ['json' => [
        'CustomerId' => 3, 'Origin' => 'THLCH', 'Destination' => 'SGSIN',
        'Lines' => [['Description' => 'x', 'Quantity' => 0, 'UnitPrice' => 10]],
    ]]);

    expect($response->getStatusCode())->toBe(400)
        ->and(body($response)['ModelState'])->toBe(['Lines' => ['Each line needs a Description, a Quantity > 0 and a UnitPrice > 0.']]);
});

it('rejects an unknown customer', function () {
    $response = api()->post('/api/orders', ['json' => [
        'CustomerId' => 999999, 'Origin' => 'THLCH', 'Destination' => 'SGSIN',
        'Lines' => [['Description' => 'x', 'Quantity' => 1, 'UnitPrice' => 10]],
    ]]);

    expect($response->getStatusCode())->toBe(400)
        ->and(body($response)['ModelState'])->toBe(['CustomerId' => ['Customer does not exist.']]);
});
