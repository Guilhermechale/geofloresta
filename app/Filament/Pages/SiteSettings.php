<?php

namespace App\Filament\Pages;

use App\Filament\Support\Fields;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Definições do site. Segredos (chaves, SMTP, GA4) ficam só no .env do servidor e aparecem aqui
 * apenas como estado (configurado / em falta), nunca o valor.
 */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $title = 'Definições do site';

    protected static ?string $navigationLabel = 'Definições';

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public const KEYS = ['contact_email', 'location', 'home_h1', 'home_lede', 'contact_intro'];

    public function mount(): void
    {
        $data = [];
        foreach (self::KEYS as $k) {
            $data[$k] = Setting::get($k);
        }
        $data['contact_email'] ??= config('geofloresta.contact_email');
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Contacto')->schema([
                TextInput::make('contact_email')->label('E-mail público e destino do formulário')->email()->required(),
            ]),
            Fields::translatable(fn (string $l, bool $req) => [
                TextInput::make("location.{$l}")->label('Localização (texto)')->placeholder('Cidade da Beira, Sofala, Moçambique'),
                TextInput::make("home_h1.{$l}")->label('Título da página inicial (vazio = texto padrão)'),
                Textarea::make("home_lede.{$l}")->label('Subtítulo da página inicial (vazio = texto padrão)')->rows(2),
                Textarea::make("contact_intro.{$l}")->label('Introdução da página de contacto')->rows(3),
            ], [], 'Textos'),
            Section::make('Serviços externos (configurados no .env do servidor)')->schema([
                Placeholder::make('ga4')->label('Google Analytics 4')
                    ->content(config('geofloresta.ga4_id') ? 'configurado — a categoria "Analítica" aparece no banner' : 'em falta (GA4_ID vazio) — sem analítica'),
                Placeholder::make('firms')->label('Chave NASA FIRMS')
                    ->content(config('geofloresta.firms_map_key') ? 'configurada' : 'em falta (FIRMS_MAP_KEY vazio) — ver docs/PIPELINE.md'),
                Placeholder::make('smtp')->label('SMTP do formulário')
                    ->content(config('mail.mailers.smtp.username') ? 'configurado ('.config('mail.mailers.smtp.host').')' : 'em falta — mensagens ficam só na base de dados'),
            ])->columns(3),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Gravar')->action('save')];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach (self::KEYS as $k) {
            Setting::updateOrCreate(['key' => $k], ['value' => $state[$k] ?? null]);
        }
        Notification::make()->title('Definições gravadas')->success()->send();
    }
}
