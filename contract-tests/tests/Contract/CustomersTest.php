<?php

it('lists customers with the exact v1 shape', function () {
    $customers = body(api()->get('/api/customers'));

    expect($customers)->toBeArray()->not->toBeEmpty();
    foreach ($customers as $customer) {
        expect(array_keys($customer))->toBe(CUSTOMER_KEYS)
            ->and($customer['CustomerId'])->toBeInt()
            ->and($customer['IsActive'])->toBeBool()
            ->and($customer['CreatedDate'])->toMatch(LEGACY_DATE);
    }
    // Ordered by id, like the stored procedure.
    $ids = array_column($customers, 'CustomerId');
    $sorted = $ids;
    sort($sorted);
    expect($ids)->toBe($sorted);
});

it('returns a known customer exactly', function () {
    $c = body(api()->get('/api/customers/4'));

    expect($c)->toMatchArray([
        'CustomerId' => 4,
        'Code' => 'SGCT',
        'Name' => 'Saigon Coffee Traders',
        'Country' => 'VN',
        'IsActive' => true,
        // Stored by the legacy app in local time; the new system stores UTC and must convert back.
        'CreatedDate' => '2020-04-20T09:53:00',
    ])
        // Accepted, documented change: the migration trims and lower-cases emails.
        ->and(strtolower(trim($c['Email'])))->toBe('ops@sgct.example.com');
});

it('reports inactive customers', function () {
    expect(body(api()->get('/api/customers/8'))['IsActive'])->toBeFalse();
});

it('treats a lower-case active flag as active', function () {
    // Customer 3 has IsActive = 'y' in the legacy table.
    expect(body(api()->get('/api/customers/3'))['IsActive'])->toBeTrue();
});

it('creates a customer and returns 201 with a Location header', function () {
    $code = uniqueCode();

    $response = api()->post('/api/customers', ['json' => [
        'Code' => $code, 'Name' => 'Contract Test Co', 'Email' => 'qa@contract.example.com', 'Phone' => '+66 2000 0000',
    ]]);

    expect($response->getStatusCode())->toBe(201);
    $created = body($response);
    expect(array_keys($created))->toBe(CUSTOMER_KEYS)
        ->and($created)->toMatchArray(['Code' => $code, 'Name' => 'Contract Test Co', 'Country' => 'TH', 'IsActive' => true])
        ->and($response->getHeaderLine('Location'))->toBe("/api/customers/{$created['CustomerId']}");

    expect(body(api()->get("/api/customers/{$created['CustomerId']}")))->toBe($created);
});

it('rejects a duplicate code with 409', function () {
    $response = api()->post('/api/customers', ['json' => ['Code' => 'CPRE', 'Name' => 'Duplicate']]);

    expect($response->getStatusCode())->toBe(409)
        ->and(body($response))->toBe(['Message' => 'Customer code already exists']);
});

it('reports invalid fields in ModelState', function () {
    $response = api()->post('/api/customers', ['json' => ['Code' => '', 'Country' => 'THA']]);

    expect($response->getStatusCode())->toBe(400);
    $error = body($response);
    expect($error['Message'])->toBe('The request is invalid.')
        ->and(array_keys($error['ModelState']))->toEqualCanonicalizing(['Code', 'Name', 'Country'])
        ->and($error['ModelState']['Country'])->toBe(['Country must be a 2-letter code.']);
});
