<?php

namespace App\Filament\Resources\SourceDocuments\Tables;

use App\Models\SourceDocument;
use App\Recon\Pipelines\IngestSourceDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SourceDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('provider.name')->label('Provider')->sortable(),
                TextColumn::make('format')->badge(),
                TextColumn::make('filename')->limit(40)->tooltip(fn (SourceDocument $r) => $r->filename)->searchable(),
                TextColumn::make('period_from')->date('Y-m-d')->label('Od'),
                TextColumn::make('period_to')->date('Y-m-d')->label('Do'),
                TextColumn::make('rows_count')->label('# wierszy')->numeric()->sortable(),
                TextColumn::make('parse_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        SourceDocument::STATUS_PARSED  => 'success',
                        SourceDocument::STATUS_PARSING => 'warning',
                        SourceDocument::STATUS_PENDING => 'gray',
                        SourceDocument::STATUS_FAILED  => 'danger',
                        default                        => 'gray',
                    }),
                TextColumn::make('received_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider_id')
                    ->label('Provider')
                    ->relationship('provider', 'name'),
                SelectFilter::make('parse_status')->options([
                    SourceDocument::STATUS_PENDING => 'Oczekuje',
                    SourceDocument::STATUS_PARSING => 'Parsuje',
                    SourceDocument::STATUS_PARSED  => 'Zaparsowany',
                    SourceDocument::STATUS_FAILED  => 'Błąd',
                ]),
            ])
            ->recordActions([
                Action::make('parse')
                    ->label('Parsuj')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (SourceDocument $r) => in_array($r->parse_status, [SourceDocument::STATUS_PENDING, SourceDocument::STATUS_FAILED], true))
                    ->action(function (SourceDocument $record) {
                        try {
                            app(IngestSourceDocument::class)->handle($record);
                            Notification::make()
                                ->title('Plik zaparsowany')
                                ->body($record->fresh()->rows_count.' wierszy zaimportowano')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Parsowanie nie udane')
                                ->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
