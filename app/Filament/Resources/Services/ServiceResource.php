<?php

namespace App\Filament\Resources\Services;

use App\Filament\Support\Fields;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?string $modelLabel = 'serviço';

    protected static ?string $pluralModelLabel = 'serviços';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('key')->label('Identificador interno')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('sort')->label('Ordem')->numeric()->default(0),
                Toggle::make('needs_review')->label('Para o utilizador confirmar')
                    ->helperText('Marca afirmações que precisam de confirmação antes de publicar.'),
            ])->columns(3),
            Fields::translatable(fn (string $l, bool $req) => [
                TextInput::make("title.{$l}")->label('Título')->required($req)->maxLength(255),
                Textarea::make("summary.{$l}")->label('Resumo')->required($req)->rows(3),
                Fields::richEditor("body.{$l}", 'Descrição'),
            ], ['title', 'summary']),
            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('title')->label('Serviço')->getStateUsing(fn (Service $r) => $r->getTranslation('title', 'pt')),
            IconColumn::make('needs_review')->label('A confirmar')->boolean(),
            Fields::translationColumn(),
            Fields::statusColumn(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
