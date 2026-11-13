<?php

namespace Database\Seeders;

use App\Models\Dataset;
use Illuminate\Database\Seeder;

/**
 * Entradas do catálogo que, por agora, são só "Ligação externa":
 * - rasters que exigem GDAL (processados no computador do utilizador com pipeline/), ou
 * - fontes cuja licença ainda não foi confirmada para alojar.
 * Idempotente: só cria o que falta.
 */
class ExternalDatasetsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->entries() as $e) {
            if (Dataset::where('slug', 'like', '%"pt":"'.$e['slug']['pt'].'"%')->exists()) {
                continue;
            }
            Dataset::create($e + ['hosting' => 'external', 'status' => 'published', 'region' => 'mozambique', 'crs' => 'EPSG:4326', 'accessed_at' => '2026-11-13']);
        }
    }

    protected function entries(): array
    {
        $pending = [
            'pt' => 'Os recortes por província estão em preparação e serão publicados aqui depois de verificados.',
            'fr' => 'Les extraits par province sont en préparation et seront publiés ici après vérification.',
            'en' => 'Per-province extracts are in preparation and will be published here once checked.',
        ];

        return [
            [
                'slug' => ['pt' => 'cobertura-do-solo-esa-worldcover-2021', 'fr' => 'occupation-du-sol-esa-worldcover-2021', 'en' => 'land-cover-esa-worldcover-2021'],
                'title' => ['pt' => 'Uso e cobertura do solo — ESA WorldCover 2021 (10 m)', 'fr' => 'Occupation du sol — ESA WorldCover 2021 (10 m)', 'en' => 'Land cover — ESA WorldCover 2021 (10 m)'],
                'summary' => [
                    'pt' => 'Mapa global de cobertura do solo a 10 m com 11 classes, produzido pela ESA a partir de imagens Sentinel-1 e Sentinel-2 de 2021.',
                    'fr' => 'Carte mondiale d’occupation du sol à 10 m en 11 classes, produite par l’ESA à partir d’images Sentinel-1 et Sentinel-2 de 2021.',
                    'en' => 'Global 10 m land cover map with 11 classes, produced by ESA from 2021 Sentinel-1 and Sentinel-2 imagery.',
                ],
                'description' => array_map(fn ($t) => "<p>{$t}</p>", $pending),
                'theme' => 'land-cover', 'year_start' => 2021, 'year_end' => 2021, 'resolution' => '10 m',
                'source_name' => 'ESA WorldCover', 'source_url' => 'https://esa-worldcover.org/en/data-access',
                'licence_spdx' => 'CC-BY-4.0', 'licence_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'attribution' => '© ESA WorldCover project 2021 / Contains modified Copernicus Sentinel data (2021) processed by ESA WorldCover consortium',
                'citation' => 'Zanaga, D., Van De Kerchove, R., Daems, D., et al. (2022). ESA WorldCover 10 m 2021 v200. Zenodo.',
                'version' => 'v200', 'update_frequency' => 'static',
            ],
            [
                'slug' => ['pt' => 'perda-de-cobertura-arborea-hansen-gfc', 'fr' => 'perte-couvert-arbore-hansen-gfc', 'en' => 'tree-cover-loss-hansen-gfc'],
                'title' => ['pt' => 'Perda de cobertura arbórea 2001–2024 — Hansen/UMD Global Forest Change v1.12', 'fr' => 'Perte du couvert arboré 2001–2024 — Hansen/UMD Global Forest Change v1.12', 'en' => 'Tree cover loss 2001–2024 — Hansen/UMD Global Forest Change v1.12'],
                'summary' => [
                    'pt' => 'Cobertura arbórea em 2000 e perda anual de 2001 a 2024 a cerca de 30 m, da Universidade de Maryland (laboratório GLAD).',
                    'fr' => 'Couvert arboré en 2000 et perte annuelle de 2001 à 2024 à environ 30 m, de l’Université du Maryland (laboratoire GLAD).',
                    'en' => 'Tree cover in 2000 and annual loss from 2001 to 2024 at about 30 m, from the University of Maryland (GLAD lab).',
                ],
                'description' => [
                    'pt' => "<p>{$pending['pt']} Os ficheiros são obtidos na fonte original (UMD/GLAD), não pelo Earth Engine.</p>",
                    'fr' => "<p>{$pending['fr']} Les fichiers sont obtenus à la source (UMD/GLAD), et non via Earth Engine.</p>",
                    'en' => "<p>{$pending['en']} Files are obtained from the original source (UMD/GLAD), not through Earth Engine.</p>",
                ],
                'limitations' => [
                    'pt' => '«Perda de cobertura arbórea» não é sinónimo de desflorestamento: inclui cortes em plantações, incêndios e outras perturbações. A licença da versão 1.12 tem de ser confirmada na página oficial antes de alojarmos recortes.',
                    'fr' => '« Perte du couvert arboré » n’est pas synonyme de déforestation : elle inclut les coupes en plantation, les incendies et d’autres perturbations. La licence de la version 1.12 doit être confirmée sur la page officielle avant d’héberger des extraits.',
                    'en' => '“Tree cover loss” is not the same as deforestation: it includes plantation harvest, fire and other disturbances. The licence of version 1.12 must be confirmed on the official page before we host extracts.',
                ],
                'theme' => 'forest-loss', 'year_start' => 2001, 'year_end' => 2024, 'resolution' => '1 arc-second (~30 m)',
                'source_name' => 'Hansen / UMD / Google / USGS / NASA', 'source_url' => 'https://glad.earthengine.app/view/global-forest-change',
                'licence_spdx' => 'CC-BY-4.0', 'licence_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'attribution' => 'Hansen, M. C., P. V. Potapov, R. Moore, M. Hancher, S. A. Turubanova, A. Tyukavina, D. Thau, S. V. Stehman, S. J. Goetz, T. R. Loveland, A. Kommareddy, A. Egorov, L. Chini, C. O. Justice, and J. R. G. Townshend. 2013. "High-Resolution Global Maps of 21st-Century Forest Cover Change." Science 342 (15 November): 850–53. Data available on-line from: https://glad.earthengine.app/view/global-forest-change.',
                'version' => 'GFC-2024-v1.12', 'update_frequency' => 'periodic',
            ],
            [
                'slug' => ['pt' => 'causas-da-perda-florestal-wri', 'fr' => 'causes-perte-forestiere-wri', 'en' => 'drivers-of-forest-loss-wri'],
                'title' => ['pt' => 'Causas da perda de floresta 2001–2024 — WRI / Google DeepMind (1 km)', 'fr' => 'Causes de la perte forestière 2001–2024 — WRI / Google DeepMind (1 km)', 'en' => 'Drivers of forest loss 2001–2024 — WRI / Google DeepMind (1 km)'],
                'summary' => [
                    'pt' => 'Classificação, a 1 km, da causa dominante da perda de cobertura arbórea (agricultura permanente e itinerante, exploração florestal, incêndios, entre outras).',
                    'fr' => 'Classification à 1 km de la cause dominante de la perte du couvert arboré (agriculture permanente et itinérante, exploitation forestière, incendies, entre autres).',
                    'en' => 'Classification at 1 km of the dominant driver of tree cover loss (permanent and shifting agriculture, logging, wildfire, among others).',
                ],
                'description' => array_map(fn ($t) => "<p>{$t}</p>", $pending),
                'theme' => 'forest-loss', 'year_start' => 2001, 'year_end' => 2024, 'resolution' => '1 km',
                'source_name' => 'World Resources Institute, Google DeepMind', 'source_url' => 'https://data.globalforestwatch.org/',
                'licence_spdx' => 'CC-BY-4.0', 'licence_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'attribution' => 'World Resources Institute and Google DeepMind. Drivers of Forest Loss (2001–2024). Accessed through Global Forest Watch.',
                'update_frequency' => 'periodic',
            ],
            [
                'slug' => ['pt' => 'rios-e-bacias-hydrosheds', 'fr' => 'rivieres-et-bassins-hydrosheds', 'en' => 'rivers-and-basins-hydrosheds'],
                'title' => ['pt' => 'Rios e bacias hidrográficas — HydroSHEDS (HydroRIVERS, HydroBASINS)', 'fr' => 'Rivières et bassins versants — HydroSHEDS (HydroRIVERS, HydroBASINS)', 'en' => 'Rivers and river basins — HydroSHEDS (HydroRIVERS, HydroBASINS)'],
                'summary' => [
                    'pt' => 'Rede hidrográfica vectorial e bacias hidrográficas aninhadas derivadas de dados de elevação, do projecto HydroSHEDS.',
                    'fr' => 'Réseau hydrographique vectoriel et bassins versants emboîtés dérivés de données d’altitude, du projet HydroSHEDS.',
                    'en' => 'Vector river network and nested river basins derived from elevation data, from the HydroSHEDS project.',
                ],
                'description' => [
                    'pt' => '<p>Só ligação externa até lermos as restrições de redistribuição do acordo de licença do HydroRIVERS/HydroBASINS. O núcleo HydroSHEDS v2 está em CC BY 4.0.</p>',
                    'fr' => '<p>Lien externe uniquement, en attendant la lecture des restrictions de redistribution du contrat de licence HydroRIVERS/HydroBASINS. Le cœur HydroSHEDS v2 est sous CC BY 4.0.</p>',
                    'en' => '<p>External link only until we have read the redistribution restrictions in the HydroRIVERS/HydroBASINS licence agreement. The HydroSHEDS v2 core is CC BY 4.0.</p>',
                ],
                'theme' => 'hydrology', 'resolution' => '15 arc-second (~500 m)',
                'source_name' => 'HydroSHEDS (WWF, McGill University)', 'source_url' => 'https://www.hydrosheds.org/',
                'licence_spdx' => 'LicenseRef-HydroSHEDS', 'licence_url' => null,
                'attribution' => 'Lehner, B., Grill G. (2013). Global river hydrography and network routing: baseline data and new approaches to study the world’s large river systems. Hydrological Processes, 27(15): 2171–2186. Data available at www.hydrosheds.org.',
                'update_frequency' => 'static',
            ],
            [
                'slug' => ['pt' => 'biomassa-e-carbono-esa-cci', 'fr' => 'biomasse-et-carbone-esa-cci', 'en' => 'biomass-and-carbon-esa-cci'],
                'title' => ['pt' => 'Biomassa aérea e stock de carbono — ESA CCI Biomass', 'fr' => 'Biomasse aérienne et stock de carbone — ESA CCI Biomass', 'en' => 'Above-ground biomass and carbon stock — ESA CCI Biomass'],
                'summary' => [
                    'pt' => 'Mapas globais de biomassa aérea (Mg/ha) da Climate Change Initiative da ESA, e o método para estimar o stock de carbono a partir deles.',
                    'fr' => 'Cartes mondiales de biomasse aérienne (Mg/ha) de la Climate Change Initiative de l’ESA, et la méthode pour en estimer le stock de carbone.',
                    'en' => 'Global above-ground biomass maps (Mg/ha) from ESA’s Climate Change Initiative, and the method to estimate carbon stock from them.',
                ],
                'description' => [
                    'pt' => '<p>A licença dos dados CCI Biomass tem termos próprios do CEDA; por isso mostramos só a ligação à fonte até confirmarmos que permite alojar derivados.</p><h3>Método do stock de carbono</h3><p>Carbono (Mg C/ha) = Biomassa aérea (Mg/ha) × 0,47, a fracção de carbono por defeito do IPCC (Directrizes de 2006, volume 4, capítulo 4). Para CO₂ equivalente multiplica-se por 44/12. A incerteza combina o desvio-padrão por píxel publicado com o mapa de biomassa e a incerteza da fracção de carbono.</p>',
                    'fr' => '<p>La licence des données CCI Biomass comporte des conditions propres au CEDA ; nous affichons donc seulement le lien vers la source jusqu’à confirmation de l’autorisation d’héberger des produits dérivés.</p><h3>Méthode du stock de carbone</h3><p>Carbone (Mg C/ha) = Biomasse aérienne (Mg/ha) × 0,47, fraction de carbone par défaut du GIEC (Lignes directrices 2006, volume 4, chapitre 4). Pour l’équivalent CO₂, multiplier par 44/12. L’incertitude combine l’écart-type par pixel publié avec la carte de biomasse et l’incertitude de la fraction de carbone.</p>',
                    'en' => '<p>CCI Biomass data have their own CEDA licence terms, so we only show the link to the source until we confirm that derived products may be hosted.</p><h3>Carbon stock method</h3><p>Carbon (Mg C/ha) = Above-ground biomass (Mg/ha) × 0.47, the IPCC default carbon fraction (2006 Guidelines, volume 4, chapter 4). For CO₂ equivalent multiply by 44/12. Uncertainty combines the per-pixel standard deviation published with the biomass map and the uncertainty of the carbon fraction.</p>',
                ],
                'theme' => 'biomass', 'resolution' => '100 m',
                'source_name' => 'ESA Climate Change Initiative — Biomass (CEDA)', 'source_url' => 'https://climate.esa.int/en/projects/biomass/',
                'licence_spdx' => 'LicenseRef-CEDA-ESA-CCI', 'licence_url' => null,
                'attribution' => 'Santoro, M.; Cartus, O. ESA Biomass Climate Change Initiative (Biomass_cci): Global datasets of forest above-ground biomass. NERC EDS Centre for Environmental Data Analysis.',
                'update_frequency' => 'periodic',
            ],
        ];
    }
}
