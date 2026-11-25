<?php

namespace App\Filament\Resources\BlockedSenders\Pages;

use App\Filament\Resources\BlockedSenders\BlockedSenderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlockedSenders extends ListRecords
{
    protected static string $resource = BlockedSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Bloquear remetente')];
    }
}
