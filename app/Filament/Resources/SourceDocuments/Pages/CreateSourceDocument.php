<?php

namespace App\Filament\Resources\SourceDocuments\Pages;

use App\Filament\Resources\SourceDocuments\SourceDocumentResource;
use App\Models\SourceDocument;
use App\Recon\Pipelines\IngestSourceDocument;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateSourceDocument extends CreateRecord
{
    protected static string $resource = SourceDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = Storage::disk(config('recon.storage_disk', 'local'));
        $absolute = $disk->path($data['storage_path']);
        $data['file_hash'] = hash_file('sha256', $absolute);
        $data['uploaded_by'] = auth()->id();
        $data['received_via'] = 'upload';
        $data['received_at'] = now();
        $data['parse_status'] = SourceDocument::STATUS_PENDING;
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var SourceDocument $doc */
        $doc = $this->record;
        try {
            app(IngestSourceDocument::class)->handle($doc);
            Notification::make()
                ->title('Plik zaparsowany')
                ->body($doc->fresh()->rows_count.' wierszy')
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Parsowanie nie powiodło się — możesz spróbować ponownie z listy')
                ->body($e->getMessage())
                ->danger()->send();
        }
    }
}
