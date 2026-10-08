<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            // Codes appear on old paperwork and integrations: fixed once created.
                            ->disabledOn('edit')
                            ->dehydrateStateUsing(fn (string $state) => strtoupper(trim($state))),
                        TextInput::make('name')->required()->maxLength(100),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(200)
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : null),
                        TextInput::make('phone')->tel()->maxLength(30),
                        TextInput::make('country')
                            ->required()
                            ->length(2)
                            ->default('TH')
                            ->dehydrateStateUsing(fn (string $state) => strtoupper($state)),
                        Toggle::make('is_active')->label('Active')->default(true),
                    ]),
            ]);
    }
}
