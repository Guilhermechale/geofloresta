<?php

namespace App\Filament\Resources\BlockedSenders\Pages;

use App\Filament\Resources\BlockedSenders\BlockedSenderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedSender extends CreateRecord
{
    protected static string $resource = BlockedSenderResource::class;

    protected function getRedirectUrl(): string
    {
        return BlockedSenderResource::getUrl('index');
    }
}
