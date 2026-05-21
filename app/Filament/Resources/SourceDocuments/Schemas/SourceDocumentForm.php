<?php

namespace App\Filament\Resources\SourceDocuments\Schemas;

use App\Models\Provider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class SourceDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider_id')
                ->label('Provider')
                ->options(fn () => Provider::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(function (Select $component, $state, callable $set) {
                    if (! $state) {
                        return;
                    }
                    $provider = Provider::find($state);
                    if ($provider) {
                        $format = config("recon.providers.{$provider->code}.format");
                        $set('format', $format);
                    }
                }),
            Select::make('format')
                ->label('Format')
                ->options([
                    'csv_pko'          => 'PKO Bank CSV',
                    'mt940'            => 'MT940',
                    'sibs_csv'         => 'SIBS Reconciliation CSV',
                    'blik_psp_txt'     => 'BLIK / Nest PSP (fixed width)',
                    'payu_csv'         => 'PayU CSV',
                    'paymentero_csv'   => 'Paymentero CSV',
                    'internal'         => 'Internal export',
                ])
                ->required(),
            DatePicker::make('period_from')->label('Okres od'),
            DatePicker::make('period_to')->label('Okres do'),
            FileUpload::make('storage_path')
                ->label('Plik')
                ->disk(config('recon.storage_disk', 'local'))
                ->directory('source_documents')
                ->preserveFilenames()
                ->storeFileNamesIn('filename')
                ->required()
                ->columnSpanFull(),
        ]);
    }
}
