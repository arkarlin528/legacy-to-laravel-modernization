<?php

namespace App\Http\Legacy;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;

/**
 * Maps the new models to the exact v1 JSON contract: property names, order, types and formats.
 * Contract tests in ../contract-tests run the same assertions against the old and the new system.
 */
final class LegacyPresenter
{
    public static function customer(Customer $c): array
    {
        return [
            'CustomerId' => $c->id,
            'Code' => $c->code,
            'Name' => $c->name,
            'Email' => $c->email,
            'Phone' => $c->phone,
            'Country' => $c->country,
            'IsActive' => $c->is_active,
            'CreatedDate' => LegacyApi::date($c->created_at),
        ];
    }

    public static function orderHeader(Order $o): array
    {
        return [
            'OrderId' => $o->id,
            'OrderNo' => $o->order_no,
            'CustomerId' => $o->customer_id,
            'OrderDate' => LegacyApi::date($o->ordered_at),
            'Status' => $o->status->legacyCode(),
            'StatusText' => $o->status->getLabel(),
            'Origin' => $o->origin,
            'Destination' => $o->destination,
            'Total' => LegacyApi::money($o->total),
            'Currency' => $o->currency,
        ];
    }

    public static function orderDetail(Order $o): array
    {
        return self::orderHeader($o) + [
            'Remarks' => $o->remarks,
            'Lines' => $o->lines->map(fn (OrderLine $l) => [
                'LineNo' => $l->line_no,
                'Description' => $l->description,
                'Quantity' => $l->quantity,
                'UnitPrice' => LegacyApi::money($l->unit_price),
                'Amount' => LegacyApi::money($l->amount),
            ])->all(),
        ];
    }

    public static function invoice(Invoice $i): array
    {
        return [
            'InvoiceId' => $i->id,
            'InvoiceNo' => $i->invoice_no,
            'OrderId' => $i->order_id,
            'InvoiceDate' => LegacyApi::date($i->issued_at),
            'DueDate' => LegacyApi::date($i->due_at),
            'Amount' => LegacyApi::money($i->amount),
            'IsPaid' => $i->is_paid,
            'PaidDate' => LegacyApi::date($i->paid_at),
        ];
    }
}
