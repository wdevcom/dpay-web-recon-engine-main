<?php

namespace App\Filament\Resources\JournalEntries;

use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Resources\JournalEntries\Tables\JournalEntriesTable;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Księga (journal)';

    protected static string|\UnitEnum|null $navigationGroup = 'Księga';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return JournalEntriesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // Journal entries are created by the ledger service, not via the UI.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
        ];
    }
}
