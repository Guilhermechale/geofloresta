<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\Redirect;
use App\Models\Tutorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        // O segredo 2FA não é "fillable" (só o Filament o grava): forceFill no teste.
        $u = (new User)->forceFill([
            'name' => 'Admin', 'email' => 'admin@teste.local', 'password' => 'x-'.str_repeat('a', 20), 'is_admin' => true,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $u->save();

        return $u;
    }

    public static function panelPages(): array
    {
        return array_map(fn ($p) => [$p], [
            '', 'pages', 'pages/create', 'services', 'services/create', 'studies', 'studies/create',
            'datasets', 'datasets/create', 'tutorials', 'tutorials/create',
            'contact-messages', 'redirects', 'redirects/create', 'site-settings',
        ]);
    }

    #[DataProvider('panelPages')]
    public function test_admin_sees_panel_page(string $page): void
    {
        $this->actingAs($this->admin())->get('/painel-teste/'.$page)->assertOk();
    }

    public function test_contact_messages_list_and_view_with_real_messages(): void
    {
        \App\Models\ContactMessage::create(['name' => 'Ana', 'email' => 'ana@exemplo.test', 'subject' => 'proposal', 'message' => 'Preciso de um mapa.', 'locale' => 'pt']);
        \App\Models\ContactMessage::create(['name' => 'Bob', 'email' => 'bob@exemplo.test', 'subject' => null, 'organisation' => null, 'message' => 'Hello', 'locale' => 'en', 'read_at' => now()]);
        $this->actingAs($this->admin())->get('/painel-teste/contact-messages')->assertOk()->assertSee('Ana')->assertSee('Bob')->assertSee('EN');
    }

    public function test_admin_lists_with_records_and_search_work(): void
    {
        $admin = $this->admin();
        \App\Models\Service::create(['key' => 'sig', 'title' => ['pt' => 'SIG'], 'summary' => ['pt' => 'Resumo'], 'needs_review' => true, 'status' => 'published']);
        $this->actingAs($admin)->get('/painel-teste/services')->assertOk()->assertSee('SIG');
        $this->actingAs($admin)->get('/painel-teste/redirects')->assertOk();
        $this->actingAs($admin)->get('/painel-teste/tutorials')->assertOk();
        Dataset::create([
            'slug' => ['pt' => 'cobertura-do-solo'], 'title' => ['pt' => 'Cobertura do solo'], 'summary' => ['pt' => 'Resumo'],
            'theme' => 'land-cover', 'region' => 'sofala', 'source_name' => 'ESA', 'source_url' => 'https://esa-worldcover.org',
            'licence_spdx' => 'CC-BY-4.0', 'attribution' => '© ESA WorldCover project 2021',
        ]);
        // a coluna «Tema» mostra o nome do tema (antes ficava vazia)
        $this->actingAs($admin)->get('/painel-teste/datasets')->assertOk()->assertSee('Uso e cobertura do solo');
        // pesquisa na tabela (antes rebentava)
        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Resources\Datasets\Pages\ListDatasets::class)
            ->searchTable('Cobertura')->assertCanSeeTableRecords(Dataset::all());
        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Resources\Tutorials\Pages\ListTutorials::class)->searchTable('x')->assertOk();
        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Resources\Studies\Pages\ListStudies::class)->searchTable('x')->assertOk();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/painel-teste/datasets')->assertRedirect();
    }

    public function test_non_admin_is_refused(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@teste.local', 'password' => 'y-'.str_repeat('b', 20), 'is_admin' => false]);
        $this->actingAs($user)->get('/painel-teste/datasets')->assertForbidden();
    }

    public function test_edit_pages_render_with_translations(): void
    {
        $d = Dataset::create([
            'slug' => ['pt' => 'cobertura-do-solo', 'en' => 'land-cover'],
            'title' => ['pt' => 'Cobertura do solo', 'en' => 'Land cover'],
            'summary' => ['pt' => 'Resumo'], 'theme' => 'land-cover', 'region' => 'sofala',
            'source_name' => 'ESA', 'source_url' => 'https://esa-worldcover.org', 'licence_spdx' => 'CC-BY-4.0',
            'attribution' => '© ESA WorldCover project 2021',
        ]);
        $this->actingAs($this->admin())->get("/painel-teste/datasets/{$d->id}/edit")
            ->assertOk()->assertSee('Cobertura do solo')->assertSee('Land cover');
        $this->assertSame(['pt' => true, 'fr' => false, 'en' => false], $d->translationStatus());
    }

    public function test_slug_change_on_published_content_creates_301(): void
    {
        $t = Tutorial::create([
            'slug' => ['pt' => 'antigo', 'en' => 'old'], 'title' => ['pt' => 'T', 'en' => 'T'], 'body' => ['pt' => 'x', 'en' => 'x'],
            'platform' => 'qgis', 'status' => 'published',
        ]);
        $t->setTranslation('slug', 'en', 'new')->save();

        $r = Redirect::where('from_path', '/en/tutorials/old')->first();
        $this->assertNotNull($r);
        $this->assertSame('/en/tutorials/new', $r->to_url);
        $this->assertSame(301, (int) $r->status_code);
    }

    public function test_search_text_contains_all_languages(): void
    {
        $t = Tutorial::create([
            'slug' => ['pt' => 'a'], 'title' => ['pt' => 'Índice NDVI', 'fr' => 'Indice NDVI'], 'body' => ['pt' => '<p>texto</p>'],
            'platform' => 'gee',
        ]);
        $this->assertStringContainsString('Índice NDVI', $t->search_text);
        $this->assertStringContainsString('Indice NDVI', $t->search_text);
        $this->assertStringNotContainsString('<p>', $t->search_text);
    }
}
