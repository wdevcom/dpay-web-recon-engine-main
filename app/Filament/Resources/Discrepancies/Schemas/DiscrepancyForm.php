<?php

namespace App\Filament\Resources\Discrepancies\Schemas;

use App\Models\Discrepancy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DiscrepancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider_id')->relationship('provider', 'name'),
            Select::make('reconciliation_id')->relationship('reconciliation', 'group_no')->searchable(),
            Select::make('type')->required()->options([
                Discrepancy::TYPE_OVER                  => 'Nadpłata (over)',
                Discrepancy::TYPE_SHORT                 => 'Niedobór (short)',
                Discrepancy::TYPE_DUPLICATE             => 'Duplikat',
                Discrepancy::TYPE_MISSING               => 'Brak po jednej stronie',
                Discrepancy::TYPE_WRONG_AMOUNT          => 'Niepoprawna kwota',
                Discrepancy::TYPE_UNEXPECTED_FEE        => 'Nieoczekiwana prowizja',
                Discrepancy::TYPE_REFUND_OUTSIDE_SYSTEM => 'Zwrot pozasystemowy',
                Discrepancy::TYPE_COMMISSION_SETTLEMENT => 'Rozliczenie prowizji',
                Discrepancy::TYPE_OTHER                 => 'Inne',
            ]),
            TextInput::make('amount')->numeric()->step('0.01')->required(),
            TextInput::make('currency')->default('PLN')->maxLength(3)->required(),
            Select::make('severity')->options([
                'info'     => 'Info',
                'warning'  => 'Warning',
                'critical' => 'Critical',
            ])->default('warning')->required(),
            Select::make('status')->options([
                Discrepancy::STATUS_PENDING      => 'Oczekuje',
                Discrepancy::STATUS_UNDER_REVIEW => 'W trakcie weryfikacji',
                Discrepancy::STATUS_APPROVED     => 'Zaakceptowana',
                Discrepancy::STATUS_REJECTED     => 'Odrzucona',
                Discrepancy::STATUS_WRITTEN_OFF  => 'Spisana',
            ])->default(Discrepancy::STATUS_PENDING)->required(),
            Select::make('assignee_id')->relationship('assignee', 'name'),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Textarea::make('proposed_resolution')->label('Propozycja rozwiązania')->rows(3)->columnSpanFull(),
        ]);
    }
}
