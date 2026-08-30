<?php

namespace App\Filament\Resources\Discrepancies;

use App\Filament\Resources\Discrepancies\Pages\CreateDiscrepancy;
use App\Filament\Resources\Discrepancies\Pages\EditDiscrepancy;
use App\Filament\Resources\Discrepancies\Pages\ListDiscrepancies;
use App\Filament\Resources\Discrepancies\Schemas\DiscrepancyForm;
use App\Filament\Resources\Discrepancies\Tables\DiscrepanciesTable;
use App\Models\Discrepancy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiscrepancyResource extends Resource
{
    protected static ?string $model = Discrepancy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Rozjazdy';

    protected static string|\UnitEnum|null $navigationGroup = 'Rekoncyliacja';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        return (string) Discrepancy::query()->where('status', Discrepancy::STATUS_PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return DiscrepancyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscrepanciesTable::configure($table);
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
            'index' => ListDiscrepancies::route('/'),
            'create' => CreateDiscrepancy::route('/create'),
            'edit' => EditDiscrepancy::route('/{record}/edit'),
        ];
    }
}
