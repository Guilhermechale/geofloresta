<?php

namespace App\Filament\Support;

use App\Support\ImageOptimizer;
use App\Support\Locales;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Blocos de formulário e colunas reutilizados por todos os recursos do painel. */
class Fields
{
    public const LOCALE_NAMES = ['pt' => 'Português', 'fr' => 'Français', 'en' => 'English'];

    /**
     * Separadores PT | FR | EN. $fields recebe o idioma e devolve os campos desse idioma
     * (nomes "campo.idioma"). O separador mostra "traduzido" ou "em falta" conforme $required.
     */
    public static function translatable(Closure $fields, array $required = ['title'], string $label = 'Conteúdo'): Tabs
    {
        $tabs = [];
        foreach (Locales::all() as $locale) {
            $tabs[] = Tab::make(strtoupper($locale))
                ->label(self::LOCALE_NAMES[$locale])
                ->badge(function (Get $get) use ($required, $locale) {
                    foreach ($required as $f) {
                        if (blank(strip_tags((string) $get("{$f}.{$locale}")))) {
                            return 'em falta';
                        }
                    }

                    return 'traduzido';
                })
                ->badgeColor(function (Get $get) use ($required, $locale) {
                    foreach ($required as $f) {
                        if (blank(strip_tags((string) $get("{$f}.{$locale}")))) {
                            return 'warning';
                        }
                    }

                    return 'success';
                })
                ->schema($fields($locale, $locale === Locales::default()));
        }

        return Tabs::make($label)->tabs($tabs)->columnSpanFull()->persistTabInQueryString();
    }

    /** Título com geração automática do slug (só quando o slug ainda está vazio). */
    public static function titleWithSlug(string $locale, bool $required): array
    {
        return [
            TextInput::make("title.{$locale}")->label('Título')->required($required)->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($locale) {
                    if (filled($state) && blank($get("slug.{$locale}"))) {
                        $set("slug.{$locale}", Str::slug($state));
                    }
                }),
            TextInput::make("slug.{$locale}")->label('Slug (URL)')->required($required)->maxLength(160)
                ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->helperText('Minúsculas, números e hífenes. Ao mudar o slug de um conteúdo publicado, o URL antigo redirecciona (301) automaticamente.'),
        ];
    }

    public static function publishing(): Section
    {
        return Section::make('Publicação')->schema([
            Select::make('status')->label('Estado')->options(['draft' => 'Rascunho', 'published' => 'Publicado'])
                ->default('draft')->required()->native(false),
            DateTimePicker::make('published_at')->label('Data de publicação')->seconds(false)
                ->helperText('Vazio = agora, ao publicar. Uma data futura agenda a publicação.'),
        ])->columns(2);
    }

    public static function seo(): Section
    {
        return Section::make('SEO e partilha')->collapsed()->schema([
            self::translatable(fn (string $l) => [
                TextInput::make("seo_title.{$l}")->label('Título SEO')->maxLength(70)->helperText('Vazio = usa o título.'),
                Textarea::make("seo_description.{$l}")->label('Descrição SEO')->maxLength(170)->rows(2)->helperText('Vazio = usa o resumo.'),
            ], [], 'SEO'),
            self::image('og_image', 'Imagem social (1200×630)'),
        ]);
    }

    /** Upload de imagem convertida para WebP (máx. 2400 px) no disco público. */
    public static function image(string $name, string $label, string $dir = 'images'): FileUpload
    {
        return FileUpload::make($name)->label($label)->image()->disk('public')->directory($dir)
            ->maxSize(8192)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => ImageOptimizer::storeAsWebp($file, $dir));
    }

    /** Para repetidores com relação: converte o JSON das traduções em arrays antes de preencher. */
    public static function decodeTranslations(array $fields): Closure
    {
        return function (array $data) use ($fields): array {
            foreach ($fields as $f) {
                if (isset($data[$f]) && is_string($data[$f])) {
                    $data[$f] = json_decode($data[$f], true) ?: ['pt' => $data[$f]];
                }
            }

            return $data;
        };
    }

    /** Campo de texto rico com blocos de código (tutoriais, páginas). */
    public static function richEditor(string $name, string $label = 'Texto'): \Filament\Forms\Components\RichEditor
    {
        return \Filament\Forms\Components\RichEditor::make($name)->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'link', 'code'],
                ['h2', 'h3'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory('conteudo')
            ->fileAttachmentsVisibility('public')
            ->extraInputAttributes(['style' => 'min-height: 20rem']);
    }

    /** Nota sobre gráficos e legendas para o corpo de tutoriais e pesquisas. */
    public static function articleHelp(): string
    {
        $list = collect(\App\Support\Charts\Figures::CATALOGUE)->map(fn ($d, $k) => "[grafico:{$k}] — {$d}")->implode(' · ');

        return 'Gráficos com dados da fonte: escreva a marca num parágrafo sozinho, seguido do parágrafo da legenda (ex.: "Figura 1. … Fonte: …"). Disponíveis: '.$list
            .'. Uma imagem seguida de um parágrafo que comece por "Figura N." fica automaticamente com legenda.';
    }

    public static function translationColumn(): TextColumn
    {
        return TextColumn::make('translations')->label('Idiomas')
            ->state(function ($record) {
                $missing = collect($record->translationStatus())->reject()->keys()->map(fn ($l) => strtoupper($l));

                return $missing->isEmpty() ? 'completo' : 'em falta: '.$missing->implode(', ');
            })
            ->badge()
            ->color(fn (string $state) => $state === 'completo' ? 'success' : 'warning');
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')->label('Estado')->badge()
            ->formatStateUsing(fn (string $state) => $state === 'published' ? 'Publicado' : 'Rascunho')
            ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray')
            ->sortable();
    }
}
