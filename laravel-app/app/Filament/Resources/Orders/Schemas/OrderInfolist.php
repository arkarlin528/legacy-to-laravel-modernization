<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('order_no')->label('Order')->fontFamily('mono')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('customer.name')->label('Customer'),
                        TextEntry::make('ordered_at')->dateTime(),
                        TextEntry::make('origin'),
                        TextEntry::make('destination'),
                        TextEntry::make('total')->money(fn ($record) => $record->currency)->weight('bold'),
                        TextEntry::make('remarks')->placeholder('—'),
                    ]),
                Section::make('Lines')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->columns(5)
                            ->table([
                                RepeatableEntry\TableColumn::make('#'),
                                RepeatableEntry\TableColumn::make('Description'),
                                RepeatableEntry\TableColumn::make('Qty'),
                                RepeatableEntry\TableColumn::make('Unit price'),
                                RepeatableEntry\TableColumn::make('Amount'),
                            ])
                            ->schema([
                                TextEntry::make('line_no'),
                                TextEntry::make('description'),
                                TextEntry::make('quantity'),
                                TextEntry::make('unit_price')->money('USD'),
                                TextEntry::make('amount')->money('USD'),
                            ]),
                    ]),
                Section::make('Invoices')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->hiddenLabel()
                            ->placeholder('Not invoiced yet')
                            ->table([
                                RepeatableEntry\TableColumn::make('Invoice'),
                                RepeatableEntry\TableColumn::make('Issued'),
                                RepeatableEntry\TableColumn::make('Due'),
                                RepeatableEntry\TableColumn::make('Amount'),
                                RepeatableEntry\TableColumn::make('Paid'),
                            ])
                            ->schema([
                                TextEntry::make('invoice_no'),
                                TextEntry::make('issued_at')->date(),
                                TextEntry::make('due_at')->date(),
                                TextEntry::make('amount')->money('USD'),
                                TextEntry::make('is_paid')
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Paid' : 'Unpaid')
                                    ->badge()
                                    ->color(fn (bool $state) => $state ? 'success' : 'warning'),
                            ]),
                    ]),
            ]);
    }
}
