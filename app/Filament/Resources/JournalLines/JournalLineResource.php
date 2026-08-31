<?php

namespace App\Filament\Resources\JournalLines;

use App\Filament\Resources\JournalLines\Pages\ListJournalLines;
use App\Models\JournalLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Pozycje zapisów - to jest właściwy widok kontrolny: pozwala dojść od
 * konta do pojedynczej strony zapisu i z powrotem do operacji bankowej.
 */
class JournalLineResource extends Resource
{
    protected static ?string $model = JournalLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Pozycje księgi';

    protected static string|\UnitEnum|null $navigationGroup = 'Księga';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('entry.entry_no')->label('Zapis')->searchable()->fontFamily('mono'),
                TextColumn::make('entry.posted_at')->label('Księgowanie')->date('Y-m-d'),
                TextColumn::make('account.code')->label('Konto')->searchable(),
                TextColumn::make('account.name')->label('Nazwa konta')->limit(35),
                TextColumn::make('debit')->label('WN')->numeric(2)->alignRight(),
                TextColumn::make('credit')->label('MA')->numeric(2)->alignRight(),
                TextColumn::make('memo')->label('Opis')->limit(30)->placeholder('-'),
                TextColumn::make('external_ref')->label('Referencja')->limit(28)->placeholder('-')->searchable(),
            ])
            ->filters([
                SelectFilter::make('account_id')->label('Konto')->relationship('account', 'code'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListJournalLines::route('/')];
    }
}
