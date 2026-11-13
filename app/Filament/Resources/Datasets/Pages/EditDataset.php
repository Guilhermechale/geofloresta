<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Support\TranslatableRecordPages;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDataset extends EditRecord
{
    use TranslatableRecordPages;

    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::previewAction(),
            DeleteAction::make(),
        ];
    }
}