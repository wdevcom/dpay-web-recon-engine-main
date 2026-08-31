<?php

namespace App\Filament\Resources\VirtualAccounts;

use App\Filament\Resources\VirtualAccounts\Pages\ListVirtualAccounts;
use App\Models\VirtualAccount;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Wydane mikro rachunki. Podgląd operatorski - numery wydaje wyłącznie API. */
class VirtualAccountResource extends Resource
{
    protected static ?string $model = VirtualAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $navigationLabel = 'Mikro rachunki';

    protected static string|\UnitEnum|null $navigationGroup = 'Masscollect';

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
                TextColumn::make('iban')->label('IBAN')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('domain.code')->label('Domena')->badge(),
                TextColumn::make('tenant.code')->label('Konsument'),
                TextColumn::make('owner_type')->label('Typ')->toggleable(),
                TextColumn::make('owner_ref')->label('Referencja')->searchable()->copyable(),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    VirtualAccount::STATUS_ACTIVE    => 'success',
                    VirtualAccount::STATUS_ALLOCATED => 'gray',
                    VirtualAccount::STATUS_EXPIRED   => 'warning',
                    VirtualAccount::STATUS_RELEASED  => 'danger',
                    default                          => 'gray',
                }),
                TextColumn::make('expected_amount_minor')->label('Oczekiwane')->alignRight()
                    ->formatStateUsing(fn (?int $state) => $state === null ? '-' : Money::formatMinorUnits($state)),
                TextColumn::make('received_amount_minor')->label('Wpłynęło')->alignRight()
                    ->formatStateUsing(fn (?int $state) => Money::formatMinorUnits((int) $state)),
                TextColumn::make('payments_count')->label('Wpłat')->numeric()->alignRight(),
                TextColumn::make('expires_at')->label('Wygasa')->dateTime('Y-m-d H:i')->placeholder('bezterminowo'),
                TextColumn::make('created_at')->label('Wydany')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('masscollect_domain_id')->label('Domena')->relationship('domain', 'code'),
                SelectFilter::make('tenant_id')->label('Konsument')->relationship('tenant', 'code'),
                SelectFilter::make('status')->options([
                    VirtualAccount::STATUS_ALLOCATED => 'Wydany',
                    VirtualAccount::STATUS_ACTIVE    => 'Z wpłatami',
                    VirtualAccount::STATUS_EXPIRED   => 'Wygasły',
                    VirtualAccount::STATUS_RELEASED  => 'Zamknięty',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListVirtualAccounts::route('/')];
    }
}
