<?php

namespace App\Filament\Resources\ContactMessages;

use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
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
        $n = ContactMessage::whereNull('read_at')->count();

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
                TextEntry::make('locale')->label('Idioma')->formatStateUsing(fn ($s) => strtoupper($s)),
                TextEntry::make('created_at')->label('Recebida')->dateTime('d/m/Y H:i'),
                TextEntry::make('emailed_at')->label('E-mail enviado')->dateTime('d/m/Y H:i')->placeholder('não enviado'),
                TextEntry::make('message')->label('Mensagem')->columnSpanFull()->prose(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            IconColumn::make('read_at')->label('Lida')->boolean()->getStateUsing(fn ($r) => (bool) $r->read_at),
            TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('name')->label('Nome')->searchable(),
            TextColumn::make('email')->label('E-mail')->searchable(),
            TextColumn::make('subject')->label('Assunto')->limit(40),
            TextColumn::make('locale')->label('Idioma')->formatStateUsing(fn ($s) => strtoupper($s)),
        ])->filters([
            TernaryFilter::make('read_at')->label('Lidas')->nullable(),
        ])->recordActions([
            ViewAction::make()->after(fn (ContactMessage $r) => $r->read_at ?: $r->update(['read_at' => now()])),
            Action::make('reply')->label('Responder')->icon('heroicon-o-arrow-uturn-left')
                ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.($r->subject ?: 'GEOFLORESTA'))),
            DeleteAction::make(),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
