<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(120),
            TextInput::make('name')->required()->maxLength(160),
            Select::make('type')
                ->required()
                ->options([
                    Account::TYPE_BANK        => 'Bankowe',
                    Account::TYPE_CLEARING    => 'Clearing',
                    Account::TYPE_MERCHANT    => 'Merchant',
                    Account::TYPE_COMMISSION  => 'Prowizja',
                    Account::TYPE_SUSPENSE    => 'Suspense',
                    Account::TYPE_DISCREPANCY => 'Rozjazd',
                    Account::TYPE_REVENUE     => 'Przychód',
                    Account::TYPE_EXPENSE     => 'Koszt',
                    Account::TYPE_EQUITY      => 'Kapitał',
                ]),
            TextInput::make('currency')->default('PLN')->maxLength(3)->required(),
            TextInput::make('external_ref')->label('Ref. zewnętrzny')->maxLength(120),
            Select::make('parent_id')
                ->label('Konto nadrzędne')
                ->options(fn () => Account::query()->orderBy('code')->pluck('code', 'id'))
                ->searchable(),
            Select::make('provider_id')
                ->label('Provider')
                ->relationship('provider', 'name'),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
