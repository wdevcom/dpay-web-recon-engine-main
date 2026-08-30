<?php

namespace App\Filament\Resources\Discrepancies\Pages;

use App\Filament\Resources\Discrepancies\DiscrepancyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiscrepancies extends ListRecords
{
    protected static string $resource = DiscrepancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
