<?php

namespace App\Filament\Resources\Providers\Schemas;

use App\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(64),
            TextInput::make('name')->required()->maxLength(160),
            TextInput::make('default_currency')->required()->default('PLN')->maxLength(3),
            TextInput::make('match_window_days')->numeric()->required()->default(3),
            TextInput::make('amount_tolerance')->numeric()->step('0.01')->required()->default('0.00'),
            Select::make('clearing_account_id')->label('Konto clearing')
                ->options(fn () => Account::where('type', Account::TYPE_CLEARING)->pluck('code', 'id')),
            Select::make('commission_account_id')->label('Konto prowizji')
                ->options(fn () => Account::where('type', Account::TYPE_COMMISSION)->pluck('code', 'id')),
            Select::make('bank_account_id')->label('Konto bankowe (payout)')
                ->options(fn () => Account::where('type', Account::TYPE_BANK)->pluck('code', 'id')),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
