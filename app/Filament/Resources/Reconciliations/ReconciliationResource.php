<?php

namespace App\Filament\Resources\Reconciliations;

use App\Filament\Resources\Reconciliations\Pages\ListReconciliations;
use App\Models\Reconciliation;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Zestawienia z konsumentami - zapisywane przy każdym wywołaniu API. */
class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Zestawienia';

    protected static string|\UnitEnum|null $navigationGroup = 'Rekoncyliacja';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('group_no')->label('Numer')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('tenant.code')->label('Konsument'),
                TextColumn::make('domain.code')->label('Domena')->placeholder('wszystkie'),
                TextColumn::make('period_from')->label('Od')->date('Y-m-d'),
                TextColumn::make('period_to')->label('Do')->date('Y-m-d'),
                TextColumn::make('bank_total_minor')->label('Bank')->alignRight()
                    ->formatStateUsing(fn (int $state) => Money::formatMinorUnits($state)),
                TextColumn::make('counterparty_total_minor')->label('Konsument')->alignRight()
                    ->formatStateUsing(fn (int $state) => Money::formatMinorUnits($state)),
                TextColumn::make('difference_minor')->label('Różnica')->alignRight()
                    ->formatStateUsing(fn (int $state) => Money::formatMinorUnits($state))
                    ->color(fn (int $state) => $state === 0 ? 'success' : 'danger'),
                TextColumn::make('matched_count')->label('Zgodne')->alignRight(),
                TextColumn::make('unmatched_count')->label('Rozjazdy')->alignRight()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    Reconciliation::STATUS_BALANCED         => 'success',
                    Reconciliation::STATUS_WITH_DISCREPANCY => 'danger',
                    Reconciliation::STATUS_OPEN             => 'warning',
                    default                                 => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Reconciliation::STATUS_BALANCED         => 'Zgodne',
                    Reconciliation::STATUS_WITH_DISCREPANCY => 'Z rozjazdem',
                    Reconciliation::STATUS_CLOSED           => 'Zamknięte',
                ]),
                SelectFilter::make('tenant_id')->label('Konsument')->relationship('tenant', 'code'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReconciliations::route('/')];
    }
}
