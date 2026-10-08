<?php

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client for the system under test. Errors are returned, not thrown: status codes and error
 * bodies are part of the contract too.
 */
function api(?string $baseUrl = null, ?string $apiKey = null): Client
{
    return new Client([
        'base_uri' => rtrim($baseUrl ?? getenv('BASE_URL'), '/'),
        'http_errors' => false,
        'timeout' => 15,
        'headers' => array_filter([
            'X-Api-Key' => $apiKey ?? getenv('API_KEY'),
            'Accept' => 'application/json',
        ]),
    ]);
}

function body(ResponseInterface $response): mixed
{
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/** Property names and their order, exactly as v1 clients see them. */
const CUSTOMER_KEYS = ['CustomerId', 'Code', 'Name', 'Email', 'Phone', 'Country', 'IsActive', 'CreatedDate'];
const ORDER_KEYS = ['OrderId', 'OrderNo', 'CustomerId', 'OrderDate', 'Status', 'StatusText', 'Origin', 'Destination', 'Total', 'Currency'];
const ORDER_DETAIL_KEYS = [...ORDER_KEYS, 'Remarks', 'Lines'];
const LINE_KEYS = ['LineNo', 'Description', 'Quantity', 'UnitPrice', 'Amount'];
const INVOICE_KEYS = ['InvoiceId', 'InvoiceNo', 'OrderId', 'InvoiceDate', 'DueDate', 'Amount', 'IsPaid', 'PaidDate'];

/** Legacy dates: local time, no offset, no fraction. */
const LEGACY_DATE = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/';

function uniqueCode(): string
{
    return 'T'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 9));
}
