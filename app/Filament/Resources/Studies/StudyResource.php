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

    protected static string|UnitEnum|null $navigationGroup = 'Pesquisas';

    protected static ?string $modelLabel = 'pesquisa';

    protected static ?string $pluralModelLabel = 'pesquisas';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Referência')->schema([
                TextInput::make('slug')->label('Slug (URL em português: /slug)')->required()->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->helperText('Nos artigos migrados é o slug do WordPress: não alterar, para manter o URL.'),
                Select::make('article_type')->label('Tipo de artigo')->options(['original' => 'Artigo original', 'review' => 'Artigo de revisão'])
                    ->default('original')->required()->native(false),
                TextInput::make('authors')->label('Autores (separados por ;)')->maxLength(255)
                    ->helperText('Nome completo, ex.: Guilherme Francisco Fernando Chale; Outro Autor. Vazio = GEOFLORESTA.'),
                TextInput::make('venue')->label('Publicado em (revista / instituição)')->maxLength(255)->placeholder('GEOFLORESTA, Beira'),
                TextInput::make('year')->label('Ano')->numeric()->minValue(1950)->maxValue(2100),
                TextInput::make('doi')->label('DOI')->maxLength(255),
                TextInput::make('external_url')->label('Ligação ao artigo')->url(),
                Select::make('original_locale')->label('Idioma original')->options(Fields::LOCALE_NAMES)->default('pt')->native(false),
            ])->columns(2),
            Fields::translatable(fn (string $l, bool $req) => [
                TextInput::make("title.{$l}")->label('Título')->required($req)->maxLength(255)
                    ->helperText('Títulos oficiais de artigos científicos não se traduzem: deixe FR/EN vazios nesse caso.'),
                Textarea::make("summary.{$l}")->label('Síntese (1–2 frases para listas e partilhas)')->required($req)->rows(2)->maxLength(400),
                Textarea::make("abstract.{$l}")->label('Resumo científico')->required($req)->rows(6),
                TextInput::make("keywords.{$l}")->label('Palavras-chave (separadas por ;)')->required($req),
                Fields::richEditor("body.{$l}", 'Texto do artigo: 1. Introdução · 2. Material e métodos · 3. Resultados · 4. Discussão · 5. Conclusão')
                    ->helperText('Não inclua o resumo, as referências nem o "Como citar": são gerados a partir dos campos. '.Fields::articleHelp()),
            ]),
            Section::make('Referências')->schema([
                Textarea::make('references')->hiddenLabel()->rows(8)->helperText('Uma referência por linha, no estilo APA.'),
            ]),
            Section::make('Imagem')->schema([
                Fields::image('cover_image', 'Imagem de capa'),
                TextInput::make('cover_alt')->label('Texto alternativo')->maxLength(255),
                TextInput::make('cover_credit')->label('Crédito da imagem')->helperText('Fonte e licença da imagem de capa, ex.: Sentinel-2 cloudless 2016 por EOX, CC BY 4.0')->maxLength(400),
            ])->columns(2)->collapsed(),
            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('year', 'desc')->columns([
            TextColumn::make('title')->label('Título')->getStateUsing(fn (Study $r) => $r->getTranslation('title', 'pt'))->wrap()->searchable(query: fn ($query, string $search) => $query->where('title', 'like', "%{$search}%")),
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
