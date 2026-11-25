<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /** Caixa de entrada (sem spam) e Spam, em separadores. */
    public function getTabs(): array
    {
        $spam = ContactMessage::spam()->count();

        return [
            'inbox' => Tab::make('Caixa de entrada')->icon('heroicon-o-inbox')->modifyQueryUsing(fn ($query) => $query->whereNull('spam_at')),
            'spam' => Tab::make('Spam')->icon('heroicon-o-shield-exclamation')->badge($spam ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->whereNotNull('spam_at')),
        ];
    }
}
