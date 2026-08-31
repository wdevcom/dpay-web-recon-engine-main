<?php

namespace App\Filament\Resources\BankAccounts;

use App\Banking\Statements\PullAccountHistory;
use App\Banking\Statements\SyncBankAccounts;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Models\BankAccount;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rejestr rachunków w BNP.
 *
 * Rachunki dodaje się tutaj ręcznie, bo GOconnect nie ma operacji
 * zwracającej ich listę - kanał odpowiada tylko na pytanie o rachunek,
 * który już znamy.
 */
class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Rachunki BNP';

    protected static string|\UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('iban')->label('IBAN')->required()->maxLength(34),
            TextInput::make('name')->label('Nazwa')->required(),
            Select::make('purpose')->label('Przeznaczenie')->required()->options([
                BankAccount::PURPOSE_OPERATIONAL => 'Bieżący',
                BankAccount::PURPOSE_MASSCOLLECT => 'Masowy (masscollect)',
                BankAccount::PURPOSE_SETTLEMENT  => 'Rozliczeniowy providera',
            ])->default(BankAccount::PURPOSE_OPERATIONAL),
            TextInput::make('currency')->label('Waluta')->default('PLN')->maxLength(3),
            Toggle::make('is_active')->label('Aktywny')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('iban')
            ->columns([
                TextColumn::make('iban')->label('IBAN')->searchable()->copyable(),
                TextColumn::make('name')->label('Nazwa')->searchable()->limit(40),
                TextColumn::make('purpose')->label('Przeznaczenie')->badge(),
                TextColumn::make('available_balance_minor')
                    ->label('Dostępne')->alignRight()
                    ->formatStateUsing(fn (?int $state) => $state === null ? '-' : Money::formatMinorUnits($state)),
                TextColumn::make('booked_balance_minor')
                    ->label('Księgowe')->alignRight()
                    ->formatStateUsing(fn (?int $state) => $state === null ? '-' : Money::formatMinorUnits($state)),
                TextColumn::make('balance_synced_at')->label('Salda z')->dateTime('Y-m-d H:i')->since(),
                TextColumn::make('sync_error')->label('Błąd')->color('danger')->limit(30)->placeholder('-'),
                IconColumn::make('is_active')->label('Aktywny')->boolean(),
            ])
            ->recordActions([
                Action::make('sync')
                    ->label('Pobierz saldo')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (BankAccount $record) {
                        $updated = app(SyncBankAccounts::class)->syncOne($record);

                        $updated->sync_error
                            ? Notification::make()->title('Bank odmówił')->body($updated->sync_error)->danger()->send()
                            : Notification::make()->title('Saldo odświeżone')->success()->send();
                    }),
                Action::make('pull')
                    ->label('Pobierz operacje')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (BankAccount $record) {
                        try {
                            $statement = app(PullAccountHistory::class)->handle($record);
                            Notification::make()
                                ->title('Pobrano operacje')
                                ->body($statement->new_entries_count.' nowych z '.$statement->entries_count)
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Pobieranie nieudane')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBankAccounts::route('/')];
    }
}
