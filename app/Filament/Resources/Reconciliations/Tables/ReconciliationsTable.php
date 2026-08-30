<?php

namespace App\Filament\Resources\Reconciliations\Tables;

use App\Models\Reconciliation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('group_no')->label('Grupa')->searchable()->copyable(),
                TextColumn::make('provider.name')->label('Provider'),
                TextColumn::make('period_from')->date('Y-m-d')->label('Od'),
                TextColumn::make('period_to')->date('Y-m-d')->label('Do'),
                TextColumn::make('expected_total')->numeric(2)->alignRight()->label('Oczekiwane'),
                TextColumn::make('actual_total')->numeric(2)->alignRight()->label('Faktyczne'),
                TextColumn::make('difference')->numeric(2)->alignRight()
                    ->color(fn (Reconciliation $r) => \Brick\Math\BigDecimal::of((string) $r->difference)->isZero() ? 'success' : 'danger'),
                TextColumn::make('currency'),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    Reconciliation::STATUS_BALANCED         => 'success',
                    Reconciliation::STATUS_WITH_DISCREPANCY => 'danger',
                    Reconciliation::STATUS_OPEN             => 'warning',
                    default                                 => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Reconciliation::STATUS_OPEN             => 'Otwarte',
                    Reconciliation::STATUS_BALANCED         => 'Zbalansowane',
                    Reconciliation::STATUS_WITH_DISCREPANCY => 'Z rozjazdem',
                    Reconciliation::STATUS_CLOSED           => 'Zamknięte',
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
