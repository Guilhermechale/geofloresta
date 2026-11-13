<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Conteúdo inicial: serviços (textos do site actual, sem agricultura) e páginas institucionais/legais.
 * Idempotente: só cria o que ainda não existe — nunca sobrepõe edições feitas no painel.
 * FR/EN: traduções geradas, a rever (RELATORIO_FINAL.md).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->services();
        $this->pages();
    }

    protected function services(): void
    {
        $services = [
            'gis' => [
                'sort' => 1, 'review' => false,
                'title' => ['pt' => 'Sistemas de Informação Geográfica (SIG)', 'fr' => 'Systèmes d’information géographique (SIG)', 'en' => 'Geographic Information Systems (GIS)'],
                'summary' => [
                    'pt' => 'Planeamento territorial, análise espacial, cartografia digital e produção de mapas temáticos para apoiar a gestão do território.',
                    'fr' => 'Aménagement du territoire, analyse spatiale, cartographie numérique et cartes thématiques pour appuyer la gestion du territoire.',
                    'en' => 'Land-use planning, spatial analysis, digital cartography and thematic maps to support land management.',
                ],
                'items' => [
                    'pt' => ['Planeamento territorial', 'Análise espacial', 'Cartografia digital', 'Produção de mapas temáticos'],
                    'fr' => ['Aménagement du territoire', 'Analyse spatiale', 'Cartographie numérique', 'Cartes thématiques'],
                    'en' => ['Land-use planning', 'Spatial analysis', 'Digital cartography', 'Thematic map production'],
                ],
            ],
            'remote-sensing' => [
                'sort' => 2, 'review' => true, // análise de dados de drones
                'title' => ['pt' => 'Sensoriamento Remoto', 'fr' => 'Télédétection', 'en' => 'Remote Sensing'],
                'summary' => [
                    'pt' => 'Processamento e análise de imagens de satélite e de drones para monitorização ambiental e florestal.',
                    'fr' => 'Traitement et analyse d’images satellite et de drones pour le suivi environnemental et forestier.',
                    'en' => 'Processing and analysis of satellite and drone imagery for environmental and forest monitoring.',
                ],
                'items' => [
                    'pt' => ['Processamento de imagens de satélite', 'Análise de dados de drones', 'Monitorização ambiental', 'Mapeamento florestal'],
                    'fr' => ['Traitement d’images satellite', 'Analyse de données de drones', 'Suivi environnemental', 'Cartographie forestière'],
                    'en' => ['Satellite image processing', 'Drone data analysis', 'Environmental monitoring', 'Forest mapping'],
                ],
            ],
            'ai' => [
                'sort' => 3, 'review' => true, // aprendizagem automática
                'title' => ['pt' => 'Inteligência Artificial', 'fr' => 'Intelligence artificielle', 'en' => 'Artificial Intelligence'],
                'summary' => [
                    'pt' => 'Modelos de aprendizagem automática aplicados a dados geográficos: análise espacial, automação de processos e modelos preditivos para apoio à decisão.',
                    'fr' => 'Modèles d’apprentissage automatique appliqués aux données géographiques : analyse spatiale, automatisation et modèles prédictifs d’aide à la décision.',
                    'en' => 'Machine learning models applied to geographic data: spatial analysis, process automation and predictive models for decision support.',
                ],
                'items' => [
                    'pt' => ['Análise espacial com aprendizagem automática', 'Automação de processos', 'Modelos preditivos', 'Apoio à tomada de decisão'],
                    'fr' => ['Analyse spatiale par apprentissage automatique', 'Automatisation des processus', 'Modèles prédictifs', 'Aide à la décision'],
                    'en' => ['Machine-learning spatial analysis', 'Process automation', 'Predictive models', 'Decision support'],
                ],
            ],
            'environmental' => [
                'sort' => 4, 'review' => true, // estudos de impacto ambiental
                'title' => ['pt' => 'Consultoria Ambiental', 'fr' => 'Conseil environnemental', 'en' => 'Environmental Consultancy'],
                'summary' => [
                    'pt' => 'Consultoria para empresas, instituições e comunidades na gestão dos recursos naturais.',
                    'fr' => 'Conseil aux entreprises, institutions et communautés pour la gestion des ressources naturelles.',
                    'en' => 'Consultancy for companies, institutions and communities on natural resource management.',
                ],
                'items' => [
                    'pt' => ['Estudos de impacto ambiental', 'Gestão florestal sustentável', 'Educação e sensibilização ambiental', 'Projectos de sustentabilidade'],
                    'fr' => ['Études d’impact environnemental', 'Gestion forestière durable', 'Éducation et sensibilisation à l’environnement', 'Projets de durabilité'],
                    'en' => ['Environmental impact assessments', 'Sustainable forest management', 'Environmental education and awareness', 'Sustainability projects'],
                ],
            ],
        ];

        foreach ($services as $key => $s) {
            if (Service::where('key', $key)->exists()) {
                continue;
            }
            $body = [];
            foreach ($s['items'] as $l => $items) {
                $body[$l] = '<ul>'.implode('', array_map(fn ($i) => '<li>'.e($i).'</li>', $items)).'</ul>';
            }
            Service::create([
                'key' => $key, 'sort' => $s['sort'], 'needs_review' => $s['review'],
                'title' => $s['title'], 'summary' => $s['summary'], 'body' => $body, 'status' => 'published',
            ]);
        }
    }

    protected function pages(): void
    {
        $titles = [
            'about' => ['pt' => 'Sobre a GEOFLORESTA', 'fr' => 'À propos de GEOFLORESTA', 'en' => 'About GEOFLORESTA'],
            'privacy' => ['pt' => 'Política de Privacidade', 'fr' => 'Politique de confidentialité', 'en' => 'Privacy Policy'],
            'cookies' => ['pt' => 'Política de Cookies', 'fr' => 'Politique de cookies', 'en' => 'Cookie Policy'],
            'terms' => ['pt' => 'Termos de Utilização', 'fr' => 'Conditions d’utilisation', 'en' => 'Terms of Use'],
            'licences' => ['pt' => 'Dados e Licenças', 'fr' => 'Données et licences', 'en' => 'Data and Licences'],
            'accessibility' => ['pt' => 'Declaração de Acessibilidade', 'fr' => 'Déclaration d’accessibilité', 'en' => 'Accessibility Statement'],
        ];

        foreach ($titles as $key => $title) {
            if (Page::where('key', $key)->exists()) {
                continue;
            }
            $body = [];
            foreach (['pt', 'fr', 'en'] as $l) {
                $file = resource_path("content/{$l}/{$key}.html");
                if (is_file($file)) {
                    $body[$l] = file_get_contents($file);
                }
            }
            Page::create([
                'key' => $key, 'title' => $title, 'body' => $body, 'status' => 'published',
                'content_updated_at' => '2026-11-13',
            ]);
        }
    }
}
