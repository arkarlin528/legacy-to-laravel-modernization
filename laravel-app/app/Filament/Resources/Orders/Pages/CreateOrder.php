<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use App\Services\OrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /** Same code path as POST /api/orders and /api/v2/orders. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(OrderService::class)->create(
            Customer::findOrFail($data['customer_id']),
            $data['origin'], $data['destination'], $data['currency'], $data['remarks'] ?? null,
            $data['lines']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
