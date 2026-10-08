<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('order_no')->label('Order')->searchable()->fontFamily('mono'),
                TextColumn::make('customer.name')->searchable()->limit(30),
                TextColumn::make('ordered_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('route')
                    ->state(fn ($record) => "{$record->origin} → {$record->destination}")
                    ->fontFamily('mono'),
                TextColumn::make('total')->money(fn ($record) => $record->currency)->sortable()->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('customer')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
