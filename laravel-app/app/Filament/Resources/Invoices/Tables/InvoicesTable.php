<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('invoice_no')->label('Invoice')->searchable()->fontFamily('mono'),
                TextColumn::make('order.order_no')->label('Order')->searchable()->fontFamily('mono'),
                TextColumn::make('order.customer.name')->label('Customer')->limit(28),
                TextColumn::make('issued_at')->date()->sortable(),
                TextColumn::make('due_at')->date()->sortable()
                    ->color(fn ($record) => ! $record->is_paid && $record->due_at->isPast() ? 'danger' : null),
                TextColumn::make('amount')->money('USD')->sortable()->alignEnd(),
                IconColumn::make('is_paid')->label('Paid')->boolean(),
                TextColumn::make('paid_at')->date()->placeholder('—')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_paid')->label('Paid'),
                Filter::make('overdue')
                    ->query(fn (Builder $q) => $q->where('is_paid', false)->where('due_at', '<', now())),
                // Data-quality view: legacy rows marked paid without a payment date (see migration report).
                Filter::make('paid_without_date')
                    ->label('Paid but no payment date')
                    ->query(fn (Builder $q) => $q->where('is_paid', true)->whereNull('paid_at')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
