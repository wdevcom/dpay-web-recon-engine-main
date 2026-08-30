<?php

namespace App\Filament\Resources\SourceDocuments;

use App\Filament\Resources\SourceDocuments\Pages\CreateSourceDocument;
use App\Filament\Resources\SourceDocuments\Pages\EditSourceDocument;
use App\Filament\Resources\SourceDocuments\Pages\ListSourceDocuments;
use App\Filament\Resources\SourceDocuments\Schemas\SourceDocumentForm;
use App\Filament\Resources\SourceDocuments\Tables\SourceDocumentsTable;
use App\Models\SourceDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SourceDocumentResource extends Resource
{
    protected static ?string $model = SourceDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $navigationLabel = 'Pliki źródłowe';

    protected static string|\UnitEnum|null $navigationGroup = 'Rekoncyliacja';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return SourceDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceDocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceDocuments::route('/'),
            'create' => CreateSourceDocument::route('/create'),
            'edit' => EditSourceDocument::route('/{record}/edit'),
        ];
    }
}
