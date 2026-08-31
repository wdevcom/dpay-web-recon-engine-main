<?php

namespace App\Filament\Resources\JournalLines\Pages;

use App\Filament\Resources\JournalLines\JournalLineResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalLines extends ListRecords
{
    protected static string $resource = JournalLineResource::class;
}
