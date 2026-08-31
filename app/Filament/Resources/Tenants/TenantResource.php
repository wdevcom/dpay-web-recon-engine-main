<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\TenantApiKey;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Usługi dpay korzystające z tego mikroserwisu. */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Konsumenci';

    protected static string|\UnitEnum|null $navigationGroup = 'Konfiguracja';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kod')->required()->maxLength(40),
            TextInput::make('name')->label('Nazwa')->required(),
            TextInput::make('webhook_url')->label('Webhook URL')->url()->maxLength(255),
            Toggle::make('is_active')->label('Aktywny')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kod')->searchable(),
                TextColumn::make('name')->label('Nazwa')->searchable(),
                TextColumn::make('api_keys_count')->label('Klucze')->counts('apiKeys')->alignRight(),
                TextColumn::make('virtual_accounts_count')->label('Mikro rachunki')->counts('virtualAccounts')->alignRight(),
                IconColumn::make('is_active')->label('Aktywny')->boolean(),
            ])
            ->recordActions([
                Action::make('issueKey')
                    ->label('Nadaj klucz API')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->modalDescription('Klucz zostanie pokazany jeden raz. Po zamknięciu powiadomienia nie da się go odtworzyć - w bazie jest tylko skrót.')
                    ->action(function (Tenant $record) {
                        [, $plain] = TenantApiKey::issue($record, 'panel '.now()->format('Y-m-d H:i'));

                        Notification::make()
                            ->title('Klucz API dla '.$record->code)
                            ->body($plain)
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTenants::route('/')];
    }
}
