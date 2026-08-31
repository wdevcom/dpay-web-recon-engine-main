<?php

namespace App\Filament\Resources\MasscollectDomains;

use App\Filament\Resources\MasscollectDomains\Pages\ListMasscollectDomains;
use App\Models\MasscollectDomain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Podział domenowy puli numerów. Świadomie bez tworzenia i edycji z UI:
 * cyfra domeny jest częścią wydanych już numerów rachunków, więc jej
 * zmiana unieważniłaby rozpoznawanie właściciela wpłat.
 */
class MasscollectDomainResource extends Resource
{
    protected static ?string $model = MasscollectDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Domeny masscollect';

    protected static string|\UnitEnum|null $navigationGroup = 'Masscollect';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('digits')
            ->columns([
                TextColumn::make('digits')->label('Cyfra')->badge(),
                TextColumn::make('code')->label('Kod')->searchable(),
                TextColumn::make('name')->label('Nazwa')->limit(45),
                TextColumn::make('tenant.name')->label('Konsument')->placeholder('wspólna'),
                TextColumn::make('lifecycle')->label('Cykl życia')->badge()
                    ->color(fn (string $state) => $state === MasscollectDomain::LIFECYCLE_PERSISTENT ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state) => $state === MasscollectDomain::LIFECYCLE_PERSISTENT ? 'trwały' : 'jednorazowy'),
                TextColumn::make('allocated_count')->label('Wydane')->numeric()->alignRight(),
                TextColumn::make('remaining')->label('Wolne')->alignRight()
                    ->state(fn (MasscollectDomain $r) => number_format($r->remainingCapacity(), 0, ',', ' '))
                    ->color(fn (MasscollectDomain $r) => $r->isRunningLow() ? 'danger' : 'gray'),
                IconColumn::make('is_active')->label('Aktywna')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMasscollectDomains::route('/')];
    }
}
