<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Tutorials\TutorialResource;
use App\Filament\Support\Fields;
use App\Models\Course;
use App\Models\Tutorial;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Tutoriais e Cursos';

    protected static ?string $modelLabel = 'curso';

    protected static ?string $pluralModelLabel = 'cursos';

    public static function form(Schema $schema): Schema
    {
        $tutorials = fn () => Tutorial::query()->orderBy('id')->get()
            ->mapWithKeys(fn (Tutorial $t) => [$t->id => $t->getTranslation('title', 'pt')])->all();

        return $schema->components([
            Section::make('Classificação')->schema([
                Select::make('platform')->label('Plataforma')->options(Tutorial::PLATFORMS)->native(false),
                Select::make('level')->label('Nível')->options(TutorialResource::LEVEL_LABELS)->default('beginner')->native(false),
                Select::make('availability')->label('Disponibilidade')->options(['available' => 'Disponível', 'preparing' => 'Em preparação'])->default('preparing')->required()->native(false),
                TextInput::make('sort')->label('Ordem')->numeric()->default(0),
            ])->columns(4),

            Fields::translatable(fn (string $l, bool $req) => [
                ...Fields::titleWithSlug($l, $req),
                Textarea::make("summary.{$l}")->label('Resumo')->rows(3),
            ], ['title', 'slug']),

            Section::make('Módulos e lições')->schema([
                Repeater::make('modules')->relationship()->hiddenLabel()->orderColumn('sort')
                    ->mutateRelationshipDataBeforeFillUsing(Fields::decodeTranslations(['title']))
                    ->schema([
                        TextInput::make('title.pt')->label('Módulo (PT)')->required(),
                        TextInput::make('title.fr')->label('Module (FR)'),
                        TextInput::make('title.en')->label('Module (EN)'),
                        Repeater::make('lessons')->relationship()->label('Lições')->orderColumn('sort')
                            ->mutateRelationshipDataBeforeFillUsing(Fields::decodeTranslations(['title']))
                            ->schema([
                                TextInput::make('title.pt')->label('Lição (PT)')->required(),
                                TextInput::make('title.fr')->label('Leçon (FR)'),
                                TextInput::make('title.en')->label('Lesson (EN)'),
                                Select::make('tutorial_id')->label('Tutorial')->options($tutorials)->searchable()->columnSpanFull(),
                            ])->columns(3)->columnSpanFull()->addActionLabel('Adicionar lição'),
                    ])->columns(3)->addActionLabel('Adicionar módulo')->collapsible()
                    ->itemLabel(fn (array $state) => $state['title']['pt'] ?? null),
            ]),
            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->columns([
            TextColumn::make('title')->label('Curso')->getStateUsing(fn (Course $r) => $r->getTranslation('title', 'pt')),
            TextColumn::make('availability')->label('Disponibilidade')->badge()
                ->formatStateUsing(fn ($s) => $s === 'available' ? 'Disponível' : 'Em preparação')
                ->color(fn ($s) => $s === 'available' ? 'success' : 'gray'),
            TextColumn::make('modules_count')->counts('modules')->label('Módulos'),
            Fields::translationColumn(),
            Fields::statusColumn(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
