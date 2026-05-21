<?php

namespace App\Filament\Resources\Reconciliations;

use App\Filament\Resources\Reconciliations\Pages\CreateReconciliation;
use App\Filament\Resources\Reconciliations\Pages\EditReconciliation;
use App\Filament\Resources\Reconciliations\Pages\ListReconciliations;
use App\Filament\Resources\Reconciliations\Schemas\ReconciliationForm;
use App\Filament\Resources\Reconciliations\Tables\ReconciliationsTable;
use App\Models\Reconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Rekoncyliacje';

    protected static string|\UnitEnum|null $navigationGroup = 'Rekoncyliacja';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return ReconciliationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReconciliationsTable::configure($table);
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
            'index' => ListReconciliations::route('/'),
            'create' => CreateReconciliation::route('/create'),
            'edit' => EditReconciliation::route('/{record}/edit'),
        ];
    }
}
