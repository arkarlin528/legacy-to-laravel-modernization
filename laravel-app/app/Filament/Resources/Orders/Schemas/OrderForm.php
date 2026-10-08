<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Create-only form. The order number, date, status and total are set by OrderService. */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        Select::make('customer_id')
                            ->relationship('customer', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('origin')->required()->length(5)->placeholder('THLCH'),
                        TextInput::make('destination')->required()->length(5)->different('origin')->placeholder('SGSIN'),
                        TextInput::make('currency')->required()->length(3)->default('USD'),
                        Textarea::make('remarks')->columnSpan(2),
                    ]),
                Section::make('Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->columns(3)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->schema([
                                TextInput::make('description')->required()->maxLength(200),
                                TextInput::make('quantity')->required()->integer()->minValue(1)->default(1),
                                TextInput::make('unit_price')->required()->numeric()->minValue(0.01)->prefix('$'),
                            ]),
                    ]),
            ]);
    }
}
