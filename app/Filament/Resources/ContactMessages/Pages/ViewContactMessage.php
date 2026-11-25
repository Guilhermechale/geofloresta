<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

/** Página de uma mensagem: ao abrir, fica marcada como lida (o contador do menu actualiza-se logo). */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        if (! $this->record->read_at) {
            $this->record->update(['read_at' => now()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')->label('Responder')->icon('heroicon-o-arrow-uturn-left')
                ->url(fn (ContactMessage $record) => 'mailto:'.$record->email.'?subject='.rawurlencode('Re: '.($record->subject ?: 'GEOFLORESTA'))),
            Action::make('unread')->label('Marcar como não lida')->icon('heroicon-o-envelope')->color('gray')
                ->action(function (ContactMessage $record) {
                    $record->update(['read_at' => null]);
                    $this->redirect(ContactMessageResource::getUrl('index'));
                }),
            DeleteAction::make(),
        ];
    }
}
