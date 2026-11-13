<?php

namespace App\Filament\Resources\Tutorials\Pages;

use App\Filament\Resources\Tutorials\TutorialResource;
use App\Filament\Support\TranslatableRecordPages;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTutorial extends EditRecord
{
    use TranslatableRecordPages;

    protected static string $resource = TutorialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::previewAction(),
            DeleteAction::make(),
        ];
    }
}