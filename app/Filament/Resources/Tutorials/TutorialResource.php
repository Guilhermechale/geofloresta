<?php

namespace App\Filament\Resources\Tutorials;

use App\Filament\Support\Fields;
use App\Models\Tutorial;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TutorialResource extends Resource
{
    protected static ?string $model = Tutorial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Tutoriais';

    protected static ?string $modelLabel = 'tutorial';

    protected static ?string $pluralModelLabel = 'tutoriais';

    public const LEVEL_LABELS = ['beginner' => 'Iniciante', 'intermediate' => 'Intermédio', 'advanced' => 'Avançado'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Classificação')->schema([
                Select::make('platform')->label('Plataforma')->options(Tutorial::PLATFORMS)->required()->native(false),
                Select::make('level')->label('Nível')->options(self::LEVEL_LABELS)->default('beginner')->required()->native(false),
                TextInput::make('duration_minutes')->label('Duração (min)')->numeric(),
                Select::make('original_locale')->label('Idioma original')->options(Fields::LOCALE_NAMES)->default('pt')->native(false),
                DatePicker::make('content_updated_at')->label('Conteúdo actualizado em'),
                Toggle::make('featured')->label('Destacar na página inicial'),
                TextInput::make('legacy_slug')->label('Slug antigo do WordPress')->unique(ignoreRecord: true)
                    ->helperText('Mantém o URL antigo em português (/slug). Não alterar nos tutoriais migrados.')->columnSpan(2),
                TextInput::make('colab_url')->label('Abrir no Colab (URL)')->url()->columnSpan(2),
                TextInput::make('gee_url')->label('Abrir no Earth Engine (URL)')->url()->columnSpan(2),
            ])->columns(3),

            Fields::translatable(fn (string $l, bool $req) => [
                ...Fields::titleWithSlug($l, $req),
                Textarea::make("summary.{$l}")->label('Introdução (1–2 frases, aparece por baixo do título e nas listas)')->required($req)->rows(2)->maxLength(400),
                Textarea::make("objectives.{$l}")->label('1. O que vai aprender (um objectivo por linha)')->required($req)->rows(4),
                Textarea::make("prerequisites.{$l}")->label('2. Antes de começar (um requisito por linha)')->required($req)->rows(3),
                Fields::richEditor("body.{$l}", '3. Conteúdo (secções com título H2: "Passo 1: …" ou "1. …")')->helperText(Fields::articleHelp()),
                Fields::richEditor("exercise.{$l}", '4. Exercício prático')->required($req)
                    ->helperText('Tarefa concreta para o leitor aplicar o que aprendeu (passos numerados).'),
                Textarea::make("takeaways.{$l}")->label('5. Pontos-chave (um por linha)')->required($req)->rows(4),
            ], ['title', 'body', 'slug']),

            Section::make('6. Fontes e documentação')->schema([
                Textarea::make('sources')->hiddenLabel()->rows(5)
                    ->helperText('Uma fonte por linha, no formato: Título | https://endereço (o endereço é opcional).'),
            ]),

            Section::make('Anexos (scripts e dados de exemplo)')->schema([
                Repeater::make('attachments')->relationship()->hiddenLabel()->orderColumn('sort')
                    ->mutateRelationshipDataBeforeFillUsing(Fields::decodeTranslations(['label']))
                    ->schema([
                        TextInput::make('label.pt')->label('Etiqueta PT')->required(),
                        TextInput::make('label.fr')->label('Etiqueta FR'),
                        TextInput::make('label.en')->label('Etiqueta EN'),
                        FileUpload::make('path')->label('Ficheiro')->disk('public')->directory('tutoriais/anexos')
                            ->preserveFilenames()->storeFileNamesIn('filename')->maxSize(51200)->required()->columnSpanFull(),
                    ])->columns(3)->addActionLabel('Adicionar anexo')->collapsible(),
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
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->label('Tutorial')->getStateUsing(fn (Tutorial $r) => $r->getTranslation('title', 'pt'))->wrap()
                ->searchable(query: fn ($q, $s) => $q->where('title', 'like', "%{$s}%")),
            TextColumn::make('platform')->label('Plataforma')->formatStateUsing(fn ($s) => Tutorial::PLATFORMS[$s] ?? $s),
            TextColumn::make('level')->label('Nível')->formatStateUsing(fn ($s) => self::LEVEL_LABELS[$s] ?? $s),
            Fields::translationColumn(),
            Fields::statusColumn(),
        ])->filters([
            SelectFilter::make('platform')->label('Plataforma')->options(Tutorial::PLATFORMS),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTutorials::route('/'),
            'create' => Pages\CreateTutorial::route('/create'),
            'edit' => Pages\EditTutorial::route('/{record}/edit'),
        ];
    }
}
