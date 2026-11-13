<?php

namespace App\Filament\Resources\Datasets;

use App\Filament\Support\Fields;
use App\Models\Dataset;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Dados';

    protected static ?string $modelLabel = 'dataset';

    protected static ?string $pluralModelLabel = 'datasets';

    public const THEME_LABELS = [
        'land-cover' => 'Uso e cobertura do solo', 'forest-loss' => 'Perda de cobertura arbórea', 'fire' => 'Incêndios',
        'hydrology' => 'Hidrologia', 'biomass' => 'Biomassa e carbono', 'boundaries' => 'Limites administrativos', 'climate' => 'Clima',
    ];

    public const LICENCES = [
        'CC-BY-4.0' => 'CC BY 4.0', 'CC-BY-3.0-IGO' => 'CC BY 3.0 IGO', 'CC0-1.0' => 'CC0 1.0', 'ODbL-1.0' => 'ODbL 1.0', 'CC-BY-SA-4.0' => 'CC BY-SA 4.0',
        'LicenseRef-HydroSHEDS' => 'HydroSHEDS License Agreement', 'LicenseRef-CEDA-ESA-CCI' => 'ESA CCI / CEDA (termos próprios)',
        'LicenseRef-NASA-Open-Data' => 'NASA — dados abertos (sem restrições, com atribuição)',
        'LicenseRef-Public-Domain' => 'Domínio público', 'LicenseRef-Other' => 'Outra (ver ligação)',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Classificação')->schema([
                Select::make('theme')->label('Tema')->options(self::THEME_LABELS)->required()->native(false),
                Select::make('region')->label('Região')->options(array_combine(Dataset::REGIONS, array_map(fn ($r) => ucwords(str_replace('-', ' ', $r)), Dataset::REGIONS)))->required()->native(false),
                TextInput::make('year_start')->label('Ano inicial')->numeric(),
                TextInput::make('year_end')->label('Ano final')->numeric(),
                Select::make('hosting')->label('Alojamento')->options(['hosted' => 'Alojado aqui (licença permite)', 'external' => 'Só ligação externa'])->default('hosted')->required()->native(false)
                    ->helperText('"Ligar primeiro, alojar depois": só alojar se a licença permitir uso comercial e redistribuição.'),
                Select::make('update_frequency')->label('Actualização')->options(['static' => 'Versão fixa', 'periodic' => 'Actualização periódica', 'near-real-time' => 'Quase em tempo real'])->default('static')->native(false),
                Toggle::make('featured')->label('Destacar na página inicial'),
            ])->columns(3),

            Fields::translatable(fn (string $l, bool $req) => [
                ...Fields::titleWithSlug($l, $req),
                Textarea::make("summary.{$l}")->label('Resumo (1–2 frases)')->required($req)->rows(2)->maxLength(400),
                Fields::richEditor("description.{$l}", 'Descrição'),
                Textarea::make("processing.{$l}")->label('Método de processamento')->rows(4),
                Textarea::make("limitations.{$l}")->label('Limitações')->rows(3),
            ], ['title', 'summary', 'slug']),

            Section::make('Fonte, licença e metadados')->schema([
                TextInput::make('source_name')->label('Fonte')->required(),
                TextInput::make('source_url')->label('Ligação oficial')->url()->required(),
                Select::make('licence_spdx')->label('Licença (SPDX)')->options(self::LICENCES)->required()->native(false),
                TextInput::make('licence_url')->label('Texto da licença (URL)')->url(),
                Textarea::make('attribution')->label('Atribuição exacta')->required()->rows(2)->columnSpanFull()
                    ->helperText('Copiar o texto pedido pela fonte. Não se traduz.'),
                Textarea::make('citation')->label('Citação')->rows(2)->columnSpanFull()->helperText('Não se traduz.'),
                TextInput::make('version')->label('Versão'),
                DatePicker::make('accessed_at')->label('Data de acesso'),
                TextInput::make('resolution')->label('Resolução'),
                TextInput::make('crs')->label('Sistema de coordenadas')->placeholder('EPSG:4326'),
                Textarea::make('preview_geojson')->label('GeoJSON simplificado para o mini-mapa')->rows(3)->columnSpanFull()
                    ->helperText('Contorno ou extensão, em WGS 84. Gerado pelo pipeline (pipeline/).'),
            ])->columns(2),

            Section::make('Ficheiros para descarregar')->schema([
                Repeater::make('files')->relationship()->hiddenLabel()->orderColumn('sort')
                    ->mutateRelationshipDataBeforeFillUsing(Fields::decodeTranslations(['label']))
                    ->schema([
                        Select::make('format')->label('Formato')->options(Dataset::FORMATS)->required()->native(false),
                        TextInput::make('label.pt')->label('Etiqueta PT')->placeholder('ex.: Sofala'),
                        TextInput::make('label.fr')->label('Etiqueta FR'),
                        TextInput::make('label.en')->label('Etiqueta EN'),
                        FileUpload::make('path')->label('Ficheiro (ZIP com LEIAME, LICENCA e CITACAO)')->disk('datasets')->directory('uploads')
                            ->visibility('private')->preserveFilenames()->storeFileNamesIn('filename')->required()->columnSpan(2)
                            ->helperText('Ficheiros muito grandes: enviar por SSH para storage/app/datasets/ e usar "php artisan geofloresta:attach".'),
                        Placeholder::make('integrity')->label('Tamanho e SHA-256')
                            ->content(fn ($record) => $record ? format_bytes((int) $record->size_bytes).' · '.$record->sha256 : 'calculados ao gravar')
                            ->columnSpan(2),
                    ])->columns(4)->addActionLabel('Adicionar ficheiro')->collapsible()
                    ->itemLabel(fn (array $state) => ($state['format'] ?? '').' '.($state['label']['pt'] ?? '')),
            ])->visible(fn ($get) => $get('hosting') !== 'external'),

            Fields::publishing(),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->label('Dataset')->getStateUsing(fn (Dataset $r) => $r->getTranslation('title', 'pt'))->wrap()
                ->searchable(query: fn ($q, $s) => $q->where('title', 'like', "%{$s}%")),
            TextColumn::make('theme')->label('Tema')->formatStateUsing(fn ($s) => self::THEME_LABELS[$s] ?? $s),
            TextColumn::make('licence_spdx')->label('Licença')->fontFamily('mono'),
            TextColumn::make('hosting')->label('Alojamento')->badge()->formatStateUsing(fn ($s) => $s === 'external' ? 'Ligação externa' : 'Alojado'),
            TextColumn::make('files_count')->counts('files')->label('Ficheiros'),
            IconColumn::make('featured')->label('Destaque')->boolean(),
            Fields::translationColumn(),
            Fields::statusColumn(),
        ])->filters([
            SelectFilter::make('theme')->label('Tema')->options(self::THEME_LABELS),
            SelectFilter::make('status')->label('Estado')->options(['draft' => 'Rascunho', 'published' => 'Publicado']),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatasets::route('/'),
            'create' => Pages\CreateDataset::route('/create'),
            'edit' => Pages\EditDataset::route('/{record}/edit'),
        ];
    }
}
