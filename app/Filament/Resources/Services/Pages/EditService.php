<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\TranslatableRecordPages;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use TranslatableRecordPages;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::previewAction(),
            DeleteAction::make(),
        ];
    }
}