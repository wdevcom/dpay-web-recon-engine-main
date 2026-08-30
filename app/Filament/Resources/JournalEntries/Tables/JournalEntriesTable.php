<?php

namespace App\Filament\Resources\JournalEntries\Tables;

use App\Models\JournalEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JournalEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('entry_no')->label('Numer')->searchable()->copyable(),
                TextColumn::make('posted_at')->date('Y-m-d')->sortable(),
                TextColumn::make('source_type')->badge(),
                TextColumn::make('description')->limit(60)
                    ->tooltip(fn (JournalEntry $r) => $r->description)
                    ->searchable(),
                TextColumn::make('total')->numeric(2)->alignRight(),
                TextColumn::make('currency'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        JournalEntry::STATUS_POSTED   => 'success',
                        JournalEntry::STATUS_DRAFT    => 'warning',
                        JournalEntry::STATUS_REVERSED => 'danger',
                        default                       => 'gray',
                    }),
                TextColumn::make('lines_count')->counts('lines')->label('# linii'),
            ])
            ->filters([
                SelectFilter::make('source_type')->options([
                    JournalEntry::SRC_BANK            => 'Wyciąg bankowy',
                    JournalEntry::SRC_SIBS            => 'SIBS',
                    JournalEntry::SRC_BLIK            => 'BLIK / PSP',
                    JournalEntry::SRC_PAYU            => 'PayU',
                    JournalEntry::SRC_PAYMENTERO      => 'Paymentero',
                    JournalEntry::SRC_INTERNAL_TXN    => 'Internal txn',
                    JournalEntry::SRC_INTERNAL_PAYOUT => 'Internal payout',
                    JournalEntry::SRC_MANUAL          => 'Korekta ręczna',
                ]),
                SelectFilter::make('status')->options([
                    JournalEntry::STATUS_POSTED   => 'Zaksięgowany',
                    JournalEntry::STATUS_DRAFT    => 'Wersja robocza',
                    JournalEntry::STATUS_REVERSED => 'Stornowany',
                ]),
            ])
            ->recordActions([
                Action::make('reverse')
                    ->label('Storno')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (JournalEntry $r) => $r->status === JournalEntry::STATUS_POSTED)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Powód storna')
                            ->required(),
                    ])
                    ->action(function (JournalEntry $record, array $data) {
                        app(\App\Recon\Ledger\JournalPoster::class)
                            ->reverse($record, $data['reason'], auth()->id());
                        Notification::make()->title('Wpis stornowany')->success()->send();
                    }),
            ]);
    }
}
