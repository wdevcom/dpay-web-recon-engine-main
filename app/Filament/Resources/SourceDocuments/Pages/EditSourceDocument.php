<?php

namespace App\Filament\Resources\SourceDocuments\Pages;

use App\Filament\Resources\SourceDocuments\SourceDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSourceDocument extends EditRecord
{
    protected static string $resource = SourceDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
