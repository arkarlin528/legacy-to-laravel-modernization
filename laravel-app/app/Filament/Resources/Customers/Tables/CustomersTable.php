<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->placeholder('—'),
                TextColumn::make('country')->badge()->color('gray')->sortable(),
                TextColumn::make('orders_count')->label('Orders')->sortable()->alignEnd(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Customer since')->date()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('country')->options(fn () => Customer::query()
                    ->distinct()->orderBy('country')->pluck('country', 'country')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
