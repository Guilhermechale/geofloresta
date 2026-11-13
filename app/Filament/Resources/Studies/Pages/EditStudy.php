<?php

namespace App\Filament\Resources\Studies\Pages;

use App\Filament\Resources\Studies\StudyResource;
use App\Filament\Support\TranslatableRecordPages;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudy extends EditRecord
{
    use TranslatableRecordPages;

    protected static string $resource = StudyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::previewAction(),
            DeleteAction::make(),
        ];
    }
}