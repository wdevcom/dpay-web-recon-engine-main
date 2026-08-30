<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Models\Account;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->copyable(),
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('type')->badge()->color(fn (string $state) => match ($state) {
                    Account::TYPE_BANK        => 'info',
                    Account::TYPE_CLEARING    => 'warning',
                    Account::TYPE_MERCHANT    => 'success',
                    Account::TYPE_COMMISSION  => 'primary',
                    Account::TYPE_DISCREPANCY => 'danger',
                    default                   => 'gray',
                }),
                TextColumn::make('currency'),
                TextColumn::make('provider.name')->label('Provider')->placeholder('—'),
                TextColumn::make('balance')
                    ->label('Saldo (DR - CR)')
                    ->state(fn (Account $r) => (string) $r->balance())
                    ->alignRight()
                    ->numeric(decimalPlaces: 2),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    Account::TYPE_BANK        => 'Bankowe',
                    Account::TYPE_CLEARING    => 'Clearing',
                    Account::TYPE_MERCHANT    => 'Merchant',
                    Account::TYPE_COMMISSION  => 'Prowizja',
                    Account::TYPE_SUSPENSE    => 'Suspense',
                    Account::TYPE_DISCREPANCY => 'Rozjazd',
                ]),
                SelectFilter::make('provider_id')->relationship('provider', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
