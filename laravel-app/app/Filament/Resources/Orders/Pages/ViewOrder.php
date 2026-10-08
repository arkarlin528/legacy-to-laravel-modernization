<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel order')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Order $record) => $record->status->canBeCancelled())
                ->requiresConfirmation()
                ->modalDescription('The customer will see this order as cancelled. This can\'t be undone.')
                ->action(function (Order $record) {
                    try {
                        app(OrderService::class)->cancel($record);
                        Notification::make()->title("Order {$record->order_no} cancelled")->success()->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
