<?php

namespace App\Filament\Resources\BlockedSenders;

use App\Models\BlockedSender;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Remetentes bloqueados no formulário de contacto (endereços ou domínios). Apagar = desbloquear. */
class BlockedSenderResource extends Resource
{
    protected static ?string $model = BlockedSender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Contacto';

    protected static ?string $modelLabel = 'remetente bloqueado';

    protected static ?string $pluralModelLabel = 'remetentes bloqueados';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Radio::make('kind')->label('Tipo')->required()->default('email')->inline()
                    ->options(['email' => 'Endereço de e-mail', 'domain' => 'Domínio inteiro']),
                TextInput::make('value')->label('Endereço ou domínio')->required()->maxLength(190)->unique(ignoreRecord: true)
                    ->helperText('Ex.: nome@exemplo.com ou exemplo.com (sem @). Não bloqueie domínios públicos como gmail.com.')
                    ->dehydrateStateUsing(fn (?string $state) => ltrim(strtolower(trim((string) $state)), '@')),
                TextInput::make('note')->label('Nota')->maxLength(255),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('value')->label('Endereço ou domínio')->searchable(),
            TextColumn::make('kind')->label('Tipo')->badge()->formatStateUsing(fn (?string $state) => $state === 'domain' ? 'Domínio' : 'E-mail'),
            TextColumn::make('note')->label('Nota')->limit(50)->placeholder(''),
            TextColumn::make('created_at')->label('Desde')->dateTime('d/m/Y H:i'),
        ])->recordActions([
            DeleteAction::make()->label('Desbloquear')->modalHeading('Desbloquear remetente?'),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->label('Desbloquear seleccionados')])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedSenders::route('/'),
            'create' => Pages\CreateBlockedSender::route('/create'),
        ];
    }
}
