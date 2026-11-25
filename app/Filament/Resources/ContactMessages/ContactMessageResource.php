<?php

namespace App\Filament\Resources\ContactMessages;

use App\Models\BlockedSender;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Mensagens do formulário de contacto: só leitura (gravadas sempre na base de dados). */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Contacto';

    protected static ?string $modelLabel = 'mensagem';

    protected static ?string $pluralModelLabel = 'mensagens de contacto';

    public static function getNavigationBadge(): ?string
    {
        $n = ContactMessage::inbox()->whereNull('read_at')->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('name')->label('Nome'),
                TextEntry::make('email')->label('E-mail')->copyable(),
                TextEntry::make('organisation')->label('Organização')->placeholder('—'),
                TextEntry::make('subject')->label('Assunto')->placeholder('—'),
                TextEntry::make('locale')->label('Idioma')->formatStateUsing(fn (?string $state) => strtoupper((string) $state)),
                TextEntry::make('created_at')->label('Recebida')->dateTime('d/m/Y H:i'),
                TextEntry::make('emailed_at')->label('E-mail enviado')->dateTime('d/m/Y H:i')->placeholder('não enviado'),
                TextEntry::make('message')->label('Mensagem')->columnSpanFull()->prose(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            IconColumn::make('read_at')->label('Lida')->boolean()->getStateUsing(fn (ContactMessage $record) => (bool) $record->read_at)
                ->trueIcon(Heroicon::OutlinedEnvelopeOpen)->trueColor('gray')
                ->falseIcon(Heroicon::OutlinedEnvelope)->falseColor('warning')
                ->tooltip(fn (bool $state) => $state ? 'Lida' : 'Por ler'),
            TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('name')->label('Nome')->searchable(),
            TextColumn::make('email')->label('E-mail')->searchable(),
            TextColumn::make('subject')->label('Assunto')->limit(40),
            TextColumn::make('locale')->label('Idioma')->formatStateUsing(fn (?string $state) => strtoupper((string) $state)),
            TextColumn::make('spam_reason')->label('Spam')->badge()->color('warning')->placeholder('')
                ->formatStateUsing(fn (?string $state) => ['manual' => 'Marcado', 'blocked' => 'Bloqueado', 'lookalike' => 'Domínio falso'][$state] ?? $state),
        ])->filters([
            TernaryFilter::make('read_at')->label('Lidas')->nullable(),
        ])->recordActions([
            ViewAction::make(),
            ...self::spamActions(),
            Action::make('reply')->label('Responder')->icon('heroicon-o-arrow-uturn-left')
                ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.($r->subject ?: 'GEOFLORESTA'))),
            DeleteAction::make(),
        ])->toolbarActions([BulkActionGroup::make([
            BulkAction::make('spam')->label('Marcar como spam')->icon('heroicon-o-shield-exclamation')->color('warning')
                ->action(fn (Collection $records) => $records->each->markSpam())->deselectRecordsAfterCompletion(),
            DeleteBulkAction::make(),
        ])]);
    }

    /** Acções de spam e bloqueio (na lista e na página da mensagem). */
    public static function spamActions(): array
    {
        return [
            Action::make('spam')->label('Spam')->icon('heroicon-o-shield-exclamation')->color('warning')
                ->visible(fn (ContactMessage $record) => ! $record->spam_at)
                ->requiresConfirmation()->modalHeading('Marcar como spam?')
                ->modalDescription('A mensagem sai da caixa de entrada e deixa de contar nas notificações.')
                ->action(function (ContactMessage $record) {
                    $record->markSpam();
                    Notification::make()->title('Mensagem movida para o spam')->success()->send();
                }),
            Action::make('block')->label('Bloquear')->icon('heroicon-o-no-symbol')->color('danger')
                ->modalHeading('Bloquear remetente')
                ->modalDescription('As próximas mensagens deste remetente vão directamente para o spam, sem e-mail nem notificação. O remetente não é avisado.')
                ->schema([
                    Radio::make('kind')->label('O que bloquear')->required()->default('email')
                        ->options(fn (ContactMessage $record) => [
                            'email' => 'Só este endereço ('.$record->email.')',
                            'domain' => 'Todo o domínio ('.BlockedSender::domainOf($record->email).')',
                        ])
                        ->helperText('Não bloqueie domínios públicos como gmail.com ou hotmail.com: bloquearia toda a gente.'),
                ])
                ->action(function (ContactMessage $record, array $data) {
                    $b = BlockedSender::block($record->email, $data['kind'], 'Bloqueado a partir da mensagem #'.$record->id);
                    $same = $data['kind'] === 'domain'
                        ? ContactMessage::inbox()->where('email', 'like', '%@'.$b->value)
                        : ContactMessage::inbox()->where('email', $record->email);
                    $same->get()->each->markSpam('blocked');
                    $record->spam_at ?: $record->markSpam('blocked');
                    Notification::make()->title('Remetente bloqueado: '.$b->value)->success()->send();
                }),
            Action::make('notSpam')->label('Não é spam')->icon('heroicon-o-inbox-arrow-down')->color('gray')
                ->visible(fn (ContactMessage $record) => (bool) $record->spam_at)
                ->action(function (ContactMessage $record) {
                    $record->markNotSpam();
                    Notification::make()->title('Mensagem devolvida à caixa de entrada')->success()->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
