<?php

namespace App\Filament\Resources\JournalEntries;

use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Księga. Zapisy powstają wyłącznie w procesach księgujących. */
class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Zapisy księgowe';

    protected static string|\UnitEnum|null $navigationGroup = 'Księga';

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
                TextColumn::make('entry_no')->label('Numer')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('posted_at')->label('Księgowanie')->date('Y-m-d')->sortable(),
                TextColumn::make('description')->label('Opis')->limit(50)->searchable()->tooltip(fn (JournalEntry $r) => $r->description),
                TextColumn::make('source_type')->label('Źródło')->badge(),
                TextColumn::make('total')->label('Kwota')->numeric(2)->alignRight(),
                TextColumn::make('currency')->label('Waluta'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => $state === JournalEntry::STATUS_REVERSED ? 'danger' : 'success'),
                TextColumn::make('lines_count')->label('Pozycji')->counts('lines')->alignRight(),
            ])
            ->filters([
                SelectFilter::make('source_type')->label('Źródło')->options([
                    JournalEntry::SRC_MASSCOLLECT     => 'Wpłata masscollect',
                    JournalEntry::SRC_BANK_STATEMENT  => 'Pozycja wyciągu',
                    JournalEntry::SRC_MANUAL          => 'Korekta ręczna',
                    JournalEntry::SRC_REVERSAL        => 'Storno',
                ]),
                SelectFilter::make('status')->options([
                    JournalEntry::STATUS_POSTED   => 'Zaksięgowany',
                    JournalEntry::STATUS_REVERSED => 'Stornowany',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListJournalEntries::route('/')];
    }
}
