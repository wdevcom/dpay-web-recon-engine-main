<?php

namespace App\Filament\Resources\SourceDocuments\Pages;

use App\Filament\Resources\SourceDocuments\SourceDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSourceDocuments extends ListRecords
{
    protected static string $resource = SourceDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
