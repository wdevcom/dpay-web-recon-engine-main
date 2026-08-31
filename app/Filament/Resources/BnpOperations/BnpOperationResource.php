<?php

namespace App\Filament\Resources\BnpOperations;

use App\Filament\Resources\BnpOperations\Pages\ListBnpOperations;
use App\Models\BnpOperation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Dziennik wywołań GOconnect. Kolumna MsgId jest tu najważniejsza: przy
 * zleceniach to jedyny klucz pozwalający ustalić u banku, co się stało ze
 * zleceniem, na które nie dostaliśmy odpowiedzi.
 */
class BnpOperationResource extends Resource
{
    protected static ?string $model = BnpOperation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Wywołania GOconnect';

    protected static string|\UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Czas')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('operation')->label('Operacja')->badge()->searchable(),
                TextColumn::make('message_id')->label('MsgId')->fontFamily('mono')->copyable()->limit(24)->searchable(),
                TextColumn::make('account_iban')->label('Rachunek')->limit(28)->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    BnpOperation::STATUS_OK        => 'success',
                    BnpOperation::STATUS_FAILED    => 'danger',
                    BnpOperation::STATUS_UNCERTAIN => 'warning',
                    default                        => 'gray',
                }),
                TextColumn::make('duration_ms')->label('Czas [ms]')->numeric()->alignRight(),
                TextColumn::make('error_message')->label('Błąd')->limit(45)->color('danger')->placeholder('-')
                    ->tooltip(fn (BnpOperation $r) => $r->error_message),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    BnpOperation::STATUS_OK        => 'OK',
                    BnpOperation::STATUS_FAILED    => 'Błąd',
                    BnpOperation::STATUS_UNCERTAIN => 'Niepewne dostarczenie',
                    BnpOperation::STATUS_SENT      => 'Wysłane',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBnpOperations::route('/')];
    }
}
