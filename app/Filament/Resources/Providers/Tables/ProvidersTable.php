<?php

namespace App\Filament\Resources\Providers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->badge(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('default_currency')->label('CCY'),
                TextColumn::make('match_window_days')->label('Okno (dni)')->numeric(),
                TextColumn::make('amount_tolerance')->numeric(2)->label('Tolerancja'),
                TextColumn::make('clearingAccount.code')->label('Clearing')->placeholder('—'),
                TextColumn::make('commissionAccount.code')->label('Prowizja')->placeholder('—'),
                IconColumn::make('is_active')->boolean(),
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
