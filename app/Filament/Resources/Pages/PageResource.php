<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Support\Fields;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'páginas';

    protected static ?int $navigationSort = 1;

    public const KEY_LABELS = [
        'about' => 'Sobre', 'privacy' => 'Política de Privacidade', 'cookies' => 'Política de Cookies',
        'terms' => 'Termos de Utilização', 'licences' => 'Dados e Licenças', 'accessibility' => 'Declaração de Acessibilidade',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('key')->label('Página')->options(self::KEY_LABELS)->required()
                    ->unique(ignoreRecord: true)->disabledOn('edit')->native(false),
                DatePicker::make('content_updated_at')->label('Última actualização (mostrada na página)'),
            ])->columns(2),
            Fields::translatable(fn (string $l, bool $req) => [
                TextInput::make("title.{$l}")->label('Título')->required($req)->maxLength(255),
                Textarea::make("summary.{$l}")->label('Resumo / introdução')->rows(2),
                Fields::richEditor("body.{$l}"),
            ], ['title', 'body']),
            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label('Página')->formatStateUsing(fn ($state) => self::KEY_LABELS[$state] ?? $state),
            TextColumn::make('title')->label('Título')->getStateUsing(fn (Page $r) => $r->getTranslation('title', 'pt')),
            Fields::translationColumn(),
            Fields::statusColumn(),
            TextColumn::make('content_updated_at')->label('Actualizada')->date('d/m/Y'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
