<?php

namespace App\Filament\Resources\Reconciliations\Schemas;

use App\Models\Reconciliation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReconciliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group_no')->required()->unique(ignoreRecord: true),
            Select::make('provider_id')->relationship('provider', 'name')->required(),
            DatePicker::make('period_from')->required(),
            DatePicker::make('period_to')->required(),
            TextInput::make('currency')->default('PLN')->maxLength(3),
            Select::make('status')->options([
                Reconciliation::STATUS_OPEN             => 'Otwarte',
                Reconciliation::STATUS_BALANCED         => 'Zbalansowane',
                Reconciliation::STATUS_WITH_DISCREPANCY => 'Z rozjazdem',
                Reconciliation::STATUS_CLOSED           => 'Zamknięte',
            ])->default(Reconciliation::STATUS_OPEN)->required(),
        ]);
    }
}
