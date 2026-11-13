<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\TranslatableRecordPages;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use TranslatableRecordPages;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::previewAction(),
            DeleteAction::make(),
        ];
    }
}