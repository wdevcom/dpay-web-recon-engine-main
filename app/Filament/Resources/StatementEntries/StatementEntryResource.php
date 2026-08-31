<?php

namespace App\Filament\Resources\StatementEntries;

use App\Banking\Reconcile\MatchStatementEntries;
use App\Filament\Resources\StatementEntries\Pages\ListStatementEntries;
use App\Models\StatementEntry;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Pozycje z wyciągów i historii rachunków. */
class StatementEntryResource extends Resource
{
    protected static ?string $model = StatementEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Operacje bankowe';

    protected static string|\UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = StatementEntry::where('match_status', StatementEntry::MATCH_SUSPENSE)->count();

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
                TextColumn::make('booking_date')->label('Księgowanie')->date('Y-m-d')->sortable(),
                TextColumn::make('direction')->label('Kier.')->badge()
                    ->color(fn (string $state) => $state === StatementEntry::DIR_CREDIT ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state) => $state === StatementEntry::DIR_CREDIT ? 'uznanie' : 'obciążenie'),
                TextColumn::make('amount_minor')->label('Kwota')->alignRight()
                    ->formatStateUsing(fn (int $state) => Money::formatMinorUnits($state)),
                TextColumn::make('counterparty_name')->label('Kontrahent')->limit(28)->searchable(),
                TextColumn::make('remittance_text')->label('Tytuł')->limit(35)->searchable()->tooltip(fn (StatementEntry $r) => $r->remittance_text),
                TextColumn::make('detected_virtual_account')->label('Mikro rachunek')->fontFamily('mono')->limit(28)->placeholder('-')->searchable(),
                TextColumn::make('virtualAccount.owner_ref')->label('Referencja')->placeholder('-'),
                TextColumn::make('match_status')->label('Dopasowanie')->badge()->color(fn (string $state) => match ($state) {
                    StatementEntry::MATCH_MATCHED   => 'success',
                    StatementEntry::MATCH_SUSPENSE  => 'danger',
                    StatementEntry::MATCH_UNMATCHED => 'warning',
                    default                         => 'gray',
                }),
                TextColumn::make('journalEntry.entry_no')->label('Zapis')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('match_status')->label('Dopasowanie')->options([
                    StatementEntry::MATCH_UNMATCHED => 'Nieprzetworzone',
                    StatementEntry::MATCH_MATCHED   => 'Dopasowane',
                    StatementEntry::MATCH_SUSPENSE  => 'Na koncie przejściowym',
                    StatementEntry::MATCH_IGNORED   => 'Pominięte',
                ]),
                SelectFilter::make('bank_account_id')->label('Rachunek')->relationship('bankAccount', 'iban'),
                SelectFilter::make('direction')->label('Kierunek')->options([
                    StatementEntry::DIR_CREDIT => 'Uznania',
                    StatementEntry::DIR_DEBIT  => 'Obciążenia',
                ]),
            ])
            ->headerActions([
                Action::make('match')
                    ->label('Dopasuj nieprzetworzone')
                    ->icon('heroicon-o-sparkles')
                    ->action(function () {
                        $stats = app(MatchStatementEntries::class)->handle();

                        Notification::make()
                            ->title('Dopasowywanie zakończone')
                            ->body(sprintf('Dopasowane: %d, na koncie przejściowym: %d', $stats['matched'], $stats['suspense']))
                            ->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStatementEntries::route('/')];
    }
}
