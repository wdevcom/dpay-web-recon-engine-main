<?php

namespace App\Filament\Resources\Discrepancies\Pages;

use App\Filament\Resources\Discrepancies\DiscrepancyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiscrepancy extends EditRecord
{
    protected static string $resource = DiscrepancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
