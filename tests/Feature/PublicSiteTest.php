<?php

namespace Tests\Feature;

use App\Models\ConsentLog;
use App\Models\ContactMessage;
use App\Models\Redirect;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        RateLimiter::clear('contact:'.ContactMessage::hashIp('127.0.0.1'));
    }

    public static function pages(): array
    {
        return array_map(fn ($p) => [$p], [
            '/', '/servicos', '/sobre', '/contacto', '/pesquisa', '/pesquisa?q=ndvi', '/mapa-do-site',
            '/politica-de-privacidade', '/politica-de-cookies', '/termos-e-condicoes', '/dados-e-licencas', '/acessibilidade',
            '/fr', '/fr/services', '/fr/a-propos', '/fr/contact', '/fr/politique-de-confidentialite', '/fr/politique-de-cookies',
            '/fr/conditions-d-utilisation', '/fr/donnees-et-licences', '/fr/accessibilite', '/fr/plan-du-site', '/fr/recherche',
            '/en', '/en/services', '/en/about', '/en/contact', '/en/privacy-policy', '/en/cookie-policy', '/en/terms-of-use',
            '/en/data-and-licences', '/en/accessibility', '/en/sitemap', '/en/search',
        ]);
    }

    #[DataProvider('pages')]
    public function test_page_renders_without_missing_translations(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        // Nenhuma chave de tradução à vista (ex.: "site.nav.home", "pages.contact.name")
        $this->assertDoesNotMatchRegularExpression('/>\s*(site|pages|cookies|errors|mail)\.[a-z_]+(\.[a-z_]+)*\s*</', $html);
        $this->assertStringNotContainsString('{{', strip_tags($html));
        $this->assertSame(1, substr_count($html, '<h1'), 'Uma só H1 por página');
    }

    public function test_hreflang_and_canonical(): void
    {
        $html = $this->get('/en/about')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://teste.local/en/about">', $html);
        $this->assertStringContainsString('hreflang="pt" href="https://teste.local/sobre"', $html);
        $this->assertStringContainsString('hreflang="fr" href="https://teste.local/fr/a-propos"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="https://teste.local/sobre"', $html);
        $this->assertStringContainsString('<html lang="en"', $html);
    }

    public function test_cookie_banner_shown_until_choice_and_no_analytics_loaded(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('data-cookie-banner', $html);
        $this->assertStringNotContainsString('googletagmanager', $html);
        $this->assertStringNotContainsString('gf-ga', $html);
        // Sem GA4_ID a categoria Analítica não aparece
        $this->assertStringNotContainsString('name="analytics"', $html);

        $cookie = 'v='.config('geofloresta.consent_version').'&id=7f1d6c1e-1111-4a4a-8a8a-123456789abc&p=1&a=0&t='.now()->timestamp;
        $this->withUnencryptedCookie('gf_consent', $cookie)->get('/')->assertDontSee('data-cookie-banner', false);
        // Versão antiga do texto: volta a perguntar
        $old = str_replace(config('geofloresta.consent_version'), '2000-01-01', $cookie);
        $this->withUnencryptedCookie('gf_consent', $old)->get('/')->assertSee('data-cookie-banner', false);
    }

    public function test_analytics_category_appears_when_ga4_configured(): void
    {
        config(['geofloresta.ga4_id' => 'G-TESTE123']);
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('name="analytics"', $html);
        $this->assertStringContainsString('<meta name="gf-ga" content="G-TESTE123">', $html);
        // O script do Google nunca vem no HTML: só o JS o injecta depois do consentimento
        $this->assertStringNotContainsString('googletagmanager.com/gtag', $html);
    }

    public function test_consent_is_logged_anonymously(): void
    {
        $this->postJson('/consent', ['id' => '7f1d6c1e-1111-4a4a-8a8a-123456789abc', 'preferences' => true, 'analytics' => true, 'locale' => 'fr'])
            ->assertNoContent();
        $log = ConsentLog::first();
        $this->assertSame(['necessary' => true, 'preferences' => true, 'analytics' => false], $log->categories); // sem GA4
        $this->assertSame('fr', $log->locale);
    }

    public function test_cookie_policy_lists_real_cookies_only(): void
    {
        $html = $this->get('/politica-de-cookies')->getContent();
        foreach (['geofloresta_session', 'XSRF-TOKEN', 'gf_consent', 'gf_lang', 'gf-theme'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        $this->assertStringNotContainsString('<code>_ga</code>', $html);
    }

    protected function contactPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Ana Teste', 'email' => 'ana@exemplo.org', 'subject' => 'data',
            'message' => 'Preciso de um recorte de cobertura do solo para Sofala.',
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
        ], $over);
    }

    public function test_contact_saves_to_database_and_sends_mail(): void
    {
        Mail::fake();
        $this->post('/en/contact', $this->contactPayload())->assertRedirect('https://teste.local/en/contact/thank-you');
        $m = ContactMessage::first();
        $this->assertNotNull($m);
        $this->assertSame('en', $m->locale);
        $this->assertNotNull($m->ip_hash);
        $this->assertNotSame('127.0.0.1', $m->ip_hash);
        Mail::assertSent(\App\Mail\ContactReceived::class);
        Mail::assertSent(\App\Mail\ContactConfirmation::class, fn ($mail) => $mail->hasTo('ana@exemplo.org'));
    }

    public function test_contact_saves_even_if_mail_fails(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        $this->post('/contacto', $this->contactPayload())->assertRedirect();
        $this->assertSame(1, ContactMessage::count());
        $this->assertNull(ContactMessage::first()->emailed_at);
    }

    public function test_honeypot_is_silently_dropped(): void
    {
        $this->post('/contacto', $this->contactPayload(['website' => 'http://spam']))->assertRedirect();
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_too_fast_submission_is_rejected(): void
    {
        $this->post('/contacto', $this->contactPayload(['_t' => Crypt::encryptString((string) now()->timestamp)]))
            ->assertSessionHasErrors('message');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_rate_limit_after_five_messages(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contacto', $this->contactPayload())->assertRedirect();
        }
        $this->post('/contacto', $this->contactPayload())->assertSessionHasErrors('message');
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_redirects_410_and_localized_404(): void
    {
        Redirect::create(['from_path' => '/noticia-antiga', 'status_code' => 410]);
        Redirect::create(['from_path' => '/pagina-antiga/', 'to_url' => '/servicos', 'status_code' => 301]);

        $this->get('/noticia-antiga')->assertStatus(410)->assertSee('Conteúdo retirado');
        $this->get('/pagina-antiga')->assertRedirect('/servicos')->assertStatus(301);
        $this->assertSame(1, Redirect::where('from_path', '/pagina-antiga')->value('hits'));
        $this->get('/en/does-not-exist')->assertNotFound()->assertSee('Page not found');
        $this->get('/fr/nexiste-pas')->assertNotFound()->assertSee('Page introuvable');
    }

    public function test_robots_sitemap_security(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /'); // fora de produção
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<loc>https://teste.local/fr/a-propos</loc>', $xml);
        $this->get('/.well-known/security.txt')->assertOk()->assertSee('Contact: mailto:');
    }

    public function test_security_headers(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('googletagmanager', $r->headers->get('Content-Security-Policy'));
    }

    public function test_prune_removes_old_personal_data(): void
    {
        ContactMessage::create(['name' => 'x', 'email' => 'x@x.x', 'message' => 'antiga mensagem', 'created_at' => now()->subMonths(25)]);
        ContactMessage::create(['name' => 'y', 'email' => 'y@y.y', 'message' => 'recente mensagem']);
        $this->artisan('geofloresta:prune')->assertSuccessful();
        $this->assertSame(['y'], ContactMessage::pluck('name')->all());
    }
}
