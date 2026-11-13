<?php

namespace App\Filament\Resources\Studies;

use App\Filament\Support\Fields;
use App\Models\Study;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StudyResource extends Resource
{
    protected static ?string $model = Study::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?string $modelLabel = 'estudo publicado';

    protected static ?string $pluralModelLabel = 'estudos publicados';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Referência')->schema([
                TextInput::make('slug')->label('Slug (URL em português: /slug)')->required()->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->helperText('Nos artigos migrados é o slug do WordPress: não alterar, para manter o URL.'),
                TextInput::make('authors')->label('Autores')->maxLength(255),
                TextInput::make('venue')->label('Revista / instituição')->maxLength(255),
                TextInput::make('year')->label('Ano')->numeric()->minValue(1950)->maxValue(2100),
                TextInput::make('doi')->label('DOI')->maxLength(255),
                TextInput::make('external_url')->label('Ligação ao artigo')->url(),
                Select::make('original_locale')->label('Idioma original')->options(Fields::LOCALE_NAMES)->default('pt')->native(false),
            ])->columns(2),
            Fields::translatable(fn (string $l, bool $req) => [
                TextInput::make("title.{$l}")->label('Título')->required($req)->maxLength(255)
                    ->helperText('Títulos oficiais de artigos científicos não se traduzem: deixe FR/EN vazios nesse caso.'),
                Textarea::make("summary.{$l}")->label('Resumo')->rows(3),
                Fields::richEditor("body.{$l}"),
            ]),
            Section::make('Imagem')->schema([
                Fields::image('cover_image', 'Imagem de capa'),
                TextInput::make('cover_alt')->label('Texto alternativo')->maxLength(255),
            ])->columns(2)->collapsed(),
            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('year', 'desc')->columns([
            TextColumn::make('title')->label('Título')->getStateUsing(fn (Study $r) => $r->getTranslation('title', 'pt'))->wrap()->searchable(query: fn ($q, $s) => $q->where('title', 'like', "%{$s}%")),
            TextColumn::make('year')->label('Ano')->sortable(),
            Fields::translationColumn(),
            Fields::statusColumn(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudies::route('/'),
            'create' => Pages\CreateStudy::route('/create'),
            'edit' => Pages\EditStudy::route('/{record}/edit'),
        ];
    }
}
