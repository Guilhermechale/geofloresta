<?php

namespace App\Filament\Resources\Redirects;

use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $modelLabel = 'redirecção';

    protected static ?string $pluralModelLabel = 'redirecções';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('from_path')->label('Caminho antigo')->required()->placeholder('/noticia-antiga')
                    ->unique(ignoreRecord: true)->helperText('Só o caminho, sem domínio.'),
                Select::make('status_code')->label('Tipo')->options([
                    301 => '301 — mudou definitivamente', 302 => '302 — temporário', 410 => '410 — retirado de propósito',
                ])->default(301)->required()->live()->native(false),
                TextInput::make('to_url')->label('Destino')->placeholder('/servicos ou https://...')
                    ->required(fn (Get $get) => (int) $get('status_code') !== 410)
                    ->visible(fn (Get $get) => (int) $get('status_code') !== 410),
                TextInput::make('note')->label('Nota')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('from_path')->columns([
            TextColumn::make('from_path')->label('De')->searchable()->fontFamily('mono'),
            TextColumn::make('status_code')->label('Código')->badge()
                ->color(fn ($state) => (int) $state === 410 ? 'danger' : 'gray'),
            TextColumn::make('to_url')->label('Para')->searchable()->fontFamily('mono')->placeholder('—'),
            TextColumn::make('source')->label('Origem')->formatStateUsing(fn ($state) => ['manual' => 'Manual', 'slug' => 'Mudança de slug', 'migration' => 'Migração'][$state] ?? $state),
            TextColumn::make('hits')->label('Acessos')->numeric()->sortable(),
            TextColumn::make('last_hit_at')->label('Último acesso')->dateTime('d/m/Y')->sortable(),
        ])->filters([
            SelectFilter::make('status_code')->label('Código')->options([301 => '301', 302 => '302', 410 => '410']),
            SelectFilter::make('source')->label('Origem')->options(['manual' => 'Manual', 'slug' => 'Mudança de slug', 'migration' => 'Migração']),
        ])->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
