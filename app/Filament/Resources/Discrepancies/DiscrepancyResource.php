<?php

namespace App\Filament\Resources\Discrepancies;

use App\Filament\Resources\Discrepancies\Pages\ListDiscrepancies;
use App\Models\Approval;
use App\Models\Discrepancy;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Rozjazdy do wyjaśnienia. Każda zmiana statusu zostawia ślad w approvals. */
class DiscrepancyResource extends Resource
{
    protected static ?string $model = Discrepancy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Rozjazdy';

    protected static string|\UnitEnum|null $navigationGroup = 'Rekoncyliacja';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Discrepancy::where('status', Discrepancy::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Wykryty')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('type')->label('Typ')->badge(),
                TextColumn::make('severity')->label('Waga')->badge()->color(fn (string $state) => match ($state) {
                    'critical' => 'danger',
                    'warning'  => 'warning',
                    default    => 'gray',
                }),
                TextColumn::make('amount_minor')->label('Kwota')->alignRight()
                    ->formatStateUsing(fn (int $state) => Money::formatMinorUnits($state))
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'gray'),
                TextColumn::make('tenant.code')->label('Konsument')->placeholder('-'),
                TextColumn::make('virtualAccount.owner_ref')->label('Referencja')->placeholder('-')->searchable(),
                TextColumn::make('description')->label('Opis')->limit(50)->tooltip(fn (Discrepancy $r) => $r->description),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    Discrepancy::STATUS_PENDING      => 'warning',
                    Discrepancy::STATUS_UNDER_REVIEW => 'info',
                    Discrepancy::STATUS_APPROVED     => 'success',
                    Discrepancy::STATUS_REJECTED     => 'danger',
                    default                          => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Discrepancy::STATUS_PENDING      => 'Oczekuje',
                    Discrepancy::STATUS_UNDER_REVIEW => 'W analizie',
                    Discrepancy::STATUS_APPROVED     => 'Zaakceptowany',
                    Discrepancy::STATUS_REJECTED     => 'Odrzucony',
                    Discrepancy::STATUS_WRITTEN_OFF  => 'Spisany',
                ]),
                SelectFilter::make('type')->options([
                    Discrepancy::TYPE_UNIDENTIFIED_PAYMENT => 'Wpłata bez identyfikacji',
                    Discrepancy::TYPE_AMOUNT_MISMATCH      => 'Niezgodna kwota',
                    Discrepancy::TYPE_MISSING_IN_BANK      => 'Brak w banku',
                    Discrepancy::TYPE_MISSING_AT_CONSUMER  => 'Brak u konsumenta',
                    Discrepancy::TYPE_PAYMENT_AFTER_EXPIRY => 'Wpłata po terminie',
                ]),
                SelectFilter::make('severity')->label('Waga')->options([
                    'critical' => 'Krytyczna', 'warning' => 'Ostrzeżenie', 'info' => 'Informacja',
                ]),
            ])
            ->recordActions([
                Action::make('review')->label('W analizę')->icon('heroicon-o-eye')
                    ->visible(fn (Discrepancy $r) => $r->status === Discrepancy::STATUS_PENDING)
                    ->action(fn (Discrepancy $r) => self::transition($r, Discrepancy::STATUS_UNDER_REVIEW, Approval::ACTION_SUBMITTED)),
                Action::make('approve')->label('Zaakceptuj')->icon('heroicon-o-check')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Discrepancy $r) => in_array($r->status, [Discrepancy::STATUS_PENDING, Discrepancy::STATUS_UNDER_REVIEW], true))
                    ->action(fn (Discrepancy $r) => self::transition($r, Discrepancy::STATUS_APPROVED, Approval::ACTION_APPROVED)),
                Action::make('reject')->label('Odrzuć')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Discrepancy $r) => in_array($r->status, [Discrepancy::STATUS_PENDING, Discrepancy::STATUS_UNDER_REVIEW], true))
                    ->action(fn (Discrepancy $r) => self::transition($r, Discrepancy::STATUS_REJECTED, Approval::ACTION_REJECTED)),
            ]);
    }

    private static function transition(Discrepancy $discrepancy, string $status, string $action): void
    {
        $discrepancy->update([
            'status'      => $status,
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        Approval::create([
            'approvable_type' => $discrepancy::class,
            'approvable_id'   => $discrepancy->id,
            'action'          => $action,
            'actor_id'        => auth()->id(),
            'acted_at'        => now(),
        ]);

        Notification::make()->title('Status zmieniony')->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ListDiscrepancies::route('/')];
    }
}
