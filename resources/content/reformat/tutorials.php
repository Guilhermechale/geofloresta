<?php

/*
 * Modelo único de tutorial: metadados e partes estruturadas de cada tutorial existente.
 * 'headings' => [texto antigo => texto novo]; 'remove_sections' => títulos H2 cuja secção sai do corpo
 * (passou para um campo); 'prepend' => HTML inserido no início; 'append_after' => [título => HTML].
 */
return [
    1 => [
        'title' => 'Queimadas, incêndios florestais e focos de calor: como distinguir, acompanhar e prevenir',
        'summary' => 'Aprenda a diferença entre queimada, incêndio e foco de calor, como os satélites detectam o fogo e onde acompanhar os focos em Moçambique em quase tempo real.',
        'platform' => 'other', 'level' => 'beginner', 'duration' => 30,
        'objectives' => "Distinguir queimada, incêndio florestal e foco de calor\nExplicar como surge e como se propaga um incêndio (triângulo do fogo, tipos de incêndio)\nPerceber como os satélites detectam o fogo e quais são os limites desses dados\nLer correctamente números de focos de calor nas notícias\nUsar o monitor de incêndios da GEOFLORESTA e o NASA FIRMS",
        'prerequisites' => "Não precisa de conhecimentos prévios\nUm navegador actualizado (computador ou telemóvel)",
        'headings' => [
            'Queimada, incêndio florestal e foco de calor: qual é a diferença?' => '1. Queimada, incêndio florestal e foco de calor: qual é a diferença',
            'Como surge um incêndio florestal?' => '2. Como surge um incêndio florestal',
            'Como se comportam os incêndios florestais?' => '3. Como se comportam os incêndios',
            'Que impactos têm os incêndios florestais?' => '4. Impactos na saúde, na natureza e na economia',
            'Como é que os satélites detectam o fogo?' => '5. Como os satélites detectam o fogo',
            'Foco de calor é o mesmo que incêndio?' => '6. Porque um foco de calor não é um incêndio',
            'Grandes incêndios das últimas décadas' => '7. Grandes incêndios das últimas décadas',
            'Como prevenir incêndios florestais?' => '8. Como prevenir incêndios florestais',
            'Como acompanhar os incêndios em quase tempo real?' => '9. Onde acompanhar os incêndios em quase tempo real',
            'Perguntas frequentes sobre incêndios florestais' => '10. Perguntas frequentes',
            'Porque vale a pena compreender os incêndios florestais' => '11. Conclusão',
        ],
        'exercise' => <<<'HTML'
<p>Vai acompanhar os focos de calor da sua província com dados reais dos satélites VIIRS.</p>
<ol>
<li>Abra o <a href="/mapas">monitor de incêndios da GEOFLORESTA</a>.</li>
<li>Em <strong>Área de interesse</strong>, escolha a sua província (ou um distrito) e, em <strong>Período</strong>, seleccione <em>Últimos 7 dias</em>.</li>
<li>Observe as cores dos pontos: laranja são detecções das últimas 24 horas, amarelo de 24 a 48 horas e castanho de 2 a 7 dias.</li>
<li>Active a camada <strong>Imagem de satélite do dia</strong> e procure plumas de fumo junto aos focos mais recentes.</li>
<li>Carregue em <strong>Descarregar CSV</strong> e abra o ficheiro numa folha de cálculo. Conte os focos por dia (coluna da data de aquisição).</li>
<li>Responda: o número de focos corresponde ao número de incêndios? Use o que aprendeu na secção 6 para justificar.</li>
</ol>
<p>Para comparar com outros países, repita o exercício no <a href="https://firms.modaps.eosdis.nasa.gov/map/" rel="noopener">mapa do NASA FIRMS</a>.</p>
HTML,
        'takeaways' => "Queimada é fogo planeado; incêndio é fogo sem controlo; foco de calor é um registo de satélite\nO fogo precisa de combustível, calor e oxigénio: a prevenção actua sobretudo sobre o combustível e as ignições\nMais de 90% dos incêndios têm causa humana e podem ser evitados\nContar focos não é contar incêndios: os focos servem para comparar tendências no tempo e no espaço\nO NASA FIRMS e o monitor da GEOFLORESTA mostram as detecções de fogo activo em quase tempo real",
        'sources' => "NASA FIRMS — Fire Information for Resource Management System | https://firms.modaps.eosdis.nasa.gov/\nCopernicus EFFIS — European Forest Fire Information System | https://effis.jrc.ec.europa.eu/\nINPE / TerraBrasilis — Situação actual dos focos | https://terrabrasilis.dpi.inpe.br/queimadas/situacao-atual/situacao_atual/\nGoogle Earth Engine — documentação | https://developers.google.com/earth-engine\nDig Fire Extintores — Tetraedro do fogo (2026) | https://digfire.com.br/tetraedro-do-fogo/\nBarbosa, A. C. M. C. — Aula 3: Classificação dos incêndios (GEF152, 2011) | https://gef152.blogspot.com/2011/10/aula-3-classificacao-dos-incendios.html",
    ],

    2 => [
        'title' => 'Google Earth Engine: calcular NDVI, NDWI, CMRI e NBR com Sentinel-2 e Landsat, passo a passo',
        'summary' => 'Filtre e limpe de nuvens imagens Sentinel-2 e Landsat 8/9, calcule quatro índices espectrais e exporte-os em GeoTIFF, sem instalar nenhum programa.',
        'platform' => 'gee', 'level' => 'intermediate', 'duration' => 60,
        'objectives' => "Definir uma área de interesse no Code Editor do Google Earth Engine\nCarregar Sentinel-2 e Landsat 8/9 e remover nuvens com as bandas de qualidade\nCriar composições medianas sem nuvens\nCalcular e interpretar NDVI, NDWI, CMRI e NBR\nExportar os resultados em GeoTIFF para o Google Drive e abri-los no QGIS",
        'prerequisites' => "Conta Google activa\nAcesso aprovado ao Google Earth Engine (registo gratuito em code.earthengine.google.com)\nNoções básicas de JavaScript: variáveis, funções e objectos\nEspaço livre no Google Drive",
        'remove_sections' => ['O que vai precisar antes de começar'],
        'headings' => [
            'Considerações finais' => 'Conclusão',
            '4.4 NBRI: áreas queimadas' => '4.4 NBR: áreas queimadas',
            'Explore os índices no mapa interativo' => 'Explore os índices no mapa interactivo',
        ],
        'exercise' => <<<'HTML'
<p>Aplique o mesmo método a Moçambique, numa zona costeira com mangal junto à Beira, e meça a severidade de uma queimada.</p>
<ol>
<li>Substitua a área de interesse pelo estuário do Pungué, junto à Beira:
<pre><code class="language-javascript">var aoi = ee.Geometry.Rectangle([34.60, -19.95, 35.00, -19.60]);</code></pre></li>
<li>Execute de novo os passos 2 a 4 e compare o <strong>CMRI</strong> com o resultado da Amazónia: onde aparecem os valores mais altos?</li>
<li>Calcule a diferença do NBR (dNBR) entre o início e o fim da época seca:
<pre><code class="language-javascript">var antes = s2.filterDate('2024-05-01', '2024-06-30').map(maskS2clouds).median().clip(aoi);
var depois = s2.filterDate('2024-10-01', '2024-11-30').map(maskS2clouds).median().clip(aoi);
var dnbr = antes.normalizedDifference(['B8', 'B12'])
  .subtract(depois.normalizedDifference(['B8', 'B12'])).rename('dNBR');
Map.addLayer(dnbr, {min: -0.2, max: 0.6, palette: ['green', 'yellow', 'red']}, 'dNBR');</code></pre></li>
<li>Os valores de dNBR acima de cerca de 0,1 indicam perda de vegetação entre as duas datas. Confirme no <a href="/mapas">monitor de incêndios</a> se houve focos nessa zona.</li>
<li>Exporte o dNBR com <code>Export.image.toDrive</code> (escala de 10 m) e abra-o no QGIS.</li>
</ol>
HTML,
        'takeaways' => "O Earth Engine processa as imagens nos servidores da Google: basta um navegador\nUse COPERNICUS/S2_SR_HARMONIZED e Landsat Collection 2 nível 2 (reflectância de superfície)\nRemova nuvens com SCL e MSK_CLDPRB (Sentinel-2) ou QA_PIXEL (Landsat) e use composições medianas\nNo Landsat Collection 2 aplique o factor de escala (×0,0000275 − 0,2) antes de calcular índices\nNDVI = vegetação, NDWI = água, CMRI = mangal, NBR = áreas queimadas\nAs exportações só correm depois de carregar em Run no separador Tasks",
        'sources' => "Google Earth Engine — documentação oficial | https://developers.google.com/earth-engine\nCatálogo: COPERNICUS/S2_SR_HARMONIZED | https://developers.google.com/earth-engine/datasets/catalog/COPERNICUS_S2_SR_HARMONIZED\nCatálogo: LANDSAT/LC08/C02/T1_L2 | https://developers.google.com/earth-engine/datasets/catalog/LANDSAT_LC08_C02_T1_L2\nCatálogo: LANDSAT/LC09/C02/T1_L2 | https://developers.google.com/earth-engine/datasets/catalog/LANDSAT_LC09_C02_T1_L2",
    ],

    3 => [
        'title' => 'Landsat 10: o que muda com as 26 bandas e como preparar-se já com o Landsat 9',
        'summary' => 'Conheça as novidades do Landsat 10 (26 bandas, 10 a 20 m de resolução, compatibilidade com o Sentinel-2) e comece já a trabalhar com imagens Landsat 9.',
        'platform' => 'web', 'level' => 'beginner', 'duration' => 25,
        'objectives' => "Explicar o que distingue o Landsat 10 das missões anteriores\nConhecer o instrumento LandIS: bandas, resoluções e revisita\nIdentificar as principais aplicações em água, agricultura, florestas e geologia\nPerceber a integração com o Sentinel-2\nDescarregar uma imagem Landsat 9 no USGS EarthExplorer",
        'prerequisites' => "Noções básicas de sensoriamento remoto (veja o tutorial sobre satélites de observação da Terra)\nConta gratuita no USGS EarthExplorer (para o exercício)",
        'headings' => [
            'Porque o Landsat 10 é diferente de tudo o que veio antes' => '1. Porque o Landsat 10 é diferente',
            'O que muda na prática com o Landsat 10' => '2. O que muda: bandas, resolução e revisita',
            'Água mais bem monitorizada do que nunca' => '3. Aplicações: qualidade da água',
            'Agricultura de precisão com mais detalhe' => '4. Aplicações: agricultura de precisão',
            'Florestas, incêndios e risco ambiental' => '5. Aplicações: florestas, incêndios e risco ambiental',
            'Geologia e mapeamento mineral' => '6. Aplicações: geologia e mapeamento mineral',
            'Integração com o Sentinel-2 e com o futuro da observação da Terra' => '7. Integração com o Sentinel-2',
            'Quando o Landsat 10 deve ser lançado' => '8. Calendário da missão',
            'Um novo padrão para a geotecnologia mundial' => '9. Conclusão',
        ],
        'replace' => ['Em países com grandes áreas florestais, como o Brasil,' => 'Em países com grandes áreas florestais, como Moçambique ou o Brasil,'],
        'exercise' => <<<'HTML'
<p>Enquanto o Landsat 10 não é lançado, o Landsat 9 é o ponto de partida. Descarregue uma imagem da sua região.</p>
<ol>
<li>Entre em <a href="https://earthexplorer.usgs.gov/" rel="noopener">earthexplorer.usgs.gov</a> com a sua conta gratuita.</li>
<li>Em <strong>Search Criteria</strong>, escreva o nome da sua cidade (por exemplo, Beira) ou clique no mapa para marcar a área, e defina um intervalo de datas na estação seca (Maio a Outubro).</li>
<li>Em <strong>Data Sets</strong>, escolha <em>Landsat → Landsat Collection 2 Level-2 → Landsat 8-9 OLI/TIRS C2 L2</em>.</li>
<li>Em <strong>Additional Criteria</strong>, limite a cobertura de nuvens a menos de 20% e carregue em <strong>Results</strong>.</li>
<li>Escolha uma cena, veja a pré-visualização e descarregue o produto. As bandas SR_B2 a SR_B7 são a reflectância de superfície.</li>
<li>Compare as 7 bandas ópticas desta imagem com as 26 bandas previstas para o Landsat 10 (Figura 1): que aplicações deste tutorial ainda não são possíveis com o Landsat 9?</li>
</ol>
HTML,
        'takeaways' => "O Landsat 10 passa de multiespectral a superespectral: 26 bandas em vez de 11\nAs bandas VSWIR terão 10 a 20 m e as térmicas 60 m de resolução\nA revisita de 18 dias e a órbita mantêm a continuidade com mais de 50 anos de arquivo Landsat\nCinco bandas são compatíveis com o Sentinel-2, o que facilita usar os dois em conjunto\nO lançamento está previsto para 2031 e os dados continuarão abertos e gratuitos",
        'sources' => "USGS Landsat Missions — Landsat 10 | https://www.usgs.gov/landsat-missions/landsat-10\nNASA Science — Landsat 10 | https://science.nasa.gov/mission/landsat-10/\nNASA Science — Landsat 10 Mission Details | https://science.nasa.gov/mission/landsat-10/mission-details/\nUSGS — Spectral comparison of Landsat 8-9 and Landsat 10 | https://www.usgs.gov/media/images/spectral-comparison-landsat-8-9-and-landsat-10\nNASA Science — Landsat 10 Bands | https://science.nasa.gov/image-detail/amf-cb125127-f4c8-4756-a663-d591b3a07950-2/\nUSGS EarthExplorer | https://earthexplorer.usgs.gov/",
    ],

    4 => [
        'title' => 'Como analisar 85 anos de temperatura com dados ERA5: o exemplo da África Subsaariana (1940–2025)',
        'summary' => 'Veja como uma série ERA5 de 1940 a 2025 mostra um aquecimento de +1,56 °C na África Subsaariana e aprenda a calcular a sua própria série de temperatura em Python.',
        'platform' => 'colab', 'level' => 'intermediate', 'duration' => 45,
        'objectives' => "Perceber o que é uma reanálise climática e para que serve o ERA5\nInterpretar uma série longa de temperatura média anual\nComparar períodos (década de 1940 e 2020–2025) sem cair em erros de arredondamento\nDescarregar dados ERA5 do Copernicus Climate Data Store\nCalcular uma média regional ponderada pela área em Python (xarray)",
        'prerequisites' => "Conta gratuita no Copernicus Climate Data Store (cds.climate.copernicus.eu) e chave da API\nGoogle Colab ou Python 3 com xarray, netCDF4 e cdsapi\nNoções básicas de Python",
        'prepend' => '<h2>1. Porquê analisar séries longas de temperatura</h2>',
        'headings' => [
            '1. O que revelam os dados de temperatura?' => '2. Resultados do exemplo: o que revelam os dados',
            '2. Comparação entre a década de 1940 e o período recente' => '3. Comparar períodos: década de 1940 e 2020–2025',
            '3. Resumo dos resultados da análise' => '4. Tabela-resumo dos indicadores',
            '4. Por que é importante acompanhar o aquecimento regional?' => '5. Porque é importante acompanhar o aquecimento regional',
            '5. Como o ERA5 e o geoprocessamento ajudam a compreender o clima?' => '6. O que é o ERA5 e como se processa',
            '6. Principais conclusões' => '7. Conclusão',
        ],
        'exercise' => <<<'HTML'
<p>Calcule uma série de temperatura média anual com o mesmo tipo de dados, num caderno do Google Colab.</p>
<ol>
<li>Crie uma conta no <a href="https://cds.climate.copernicus.eu/" rel="noopener">Copernicus Climate Data Store</a>, aceite a licença do conjunto <em>ERA5 monthly averaged data on single levels</em> e copie a sua chave da API para o ficheiro <code>~/.cdsapirc</code>, como indicado na página do CDS.</li>
<li>Instale as bibliotecas e descarregue a temperatura a 2 m (aqui, uma caixa sobre a África Austral, para o pedido ser leve):
<pre><code class="language-python">!pip install -q cdsapi xarray netCDF4
import cdsapi
c = cdsapi.Client()
c.retrieve('reanalysis-era5-single-levels-monthly-means', {
    'product_type': ['monthly_averaged_reanalysis'],
    'variable': ['2m_temperature'],
    'year': [str(a) for a in range(1940, 2026)],
    'month': [f'{m:02d}' for m in range(1, 13)],
    'time': ['00:00'],
    'area': [-10, 30, -27, 41],   # Norte, Oeste, Sul, Leste (Moçambique e arredores)
    'data_format': 'netcdf',
}, 'era5_t2m.nc')</code></pre></li>
<li>Calcule a média anual ponderada pela área (as células junto aos pólos são mais pequenas, por isso pesam menos):
<pre><code class="language-python">import numpy as np, xarray as xr
ds = xr.open_dataset('era5_t2m.nc')
t = ds['t2m'] - 273.15                       # kelvin → °C
pesos = np.cos(np.deg2rad(ds['latitude']))
anual = t.weighted(pesos).mean(['latitude', 'longitude']).groupby('valid_time.year').mean()
print(anual.sel(year=slice(1940, 1949)).mean().item(), anual.sel(year=slice(2020, 2025)).mean().item())
anual.plot()</code></pre></li>
<li>Compare as médias de 1940–1949 e 2020–2025 da sua área com os valores da secção 3. Lembre-se: a caixa inclui oceano e a média regional esconde diferenças locais.</li>
</ol>
HTML,
        'takeaways' => "O ERA5 combina observações e modelos numa série consistente desde 1940\nNa África Subsaariana, a média de 2020–2025 (21,3 °C) ficou +1,56 °C acima da década de 1940\nCompare períodos com os valores sem arredondamento e diga sempre quais períodos usou\nUma média regional é uma visão geral: os impactos variam com o local, a estação e a chuva\nAs médias espaciais devem ser ponderadas pela área das células (cosseno da latitude)",
        'sources' => "ERA5 monthly averaged data on single levels — Copernicus Climate Change Service (C3S) | https://cds.climate.copernicus.eu/datasets/reanalysis-era5-single-levels-monthly-means\nHersbach, H. et al. (2020). The ERA5 global reanalysis. Quarterly Journal of the Royal Meteorological Society, 146, 1999–2049 | https://doi.org/10.1002/qj.3803\nDocumentação do xarray | https://docs.xarray.dev/",
    ],

    5 => [
        'title' => 'El Niño e La Niña: como funcionam, como se monitorizam e o que esperar em 2026–2027',
        'summary' => 'Perceba o ciclo El Niño–Oscilação Sul, os índices usados para o monitorizar e os efeitos habituais no mundo e em Moçambique, com a situação de Novembro de 2026.',
        'platform' => 'web', 'level' => 'beginner', 'duration' => 25,
        'objectives' => "Explicar o que é o El Niño e como surge no Pacífico equatorial\nDistinguir as fases El Niño, La Niña e neutra do ciclo ENOS\nConhecer os índices de monitorização (Niño 3.4, ONI, IOS)\nInterpretar os boletins da OMM e da NOAA\nRelacionar o El Niño com secas e chuvas na África Austral",
        'prerequisites' => "Não precisa de conhecimentos prévios\nAcesso à internet para consultar os boletins da NOAA e da OMM",
        'headings' => [
            'O que é o El Niño?' => '1. O que é o El Niño',
            'Como e onde surge o El Niño?' => '2. Como e onde surge',
            'El Niño e La Niña: duas faces do mesmo ciclo' => '3. El Niño e La Niña: duas fases do mesmo ciclo',
            'Como os cientistas monitorizam e classificam o El Niño?' => '4. Como se monitoriza e classifica',
            'O que está a acontecer actualmente no mundo (Setembro de 2026)' => '5. Situação em Setembro de 2026',
            'Que efeitos o El Niño costuma provocar no mundo?' => '6. Efeitos habituais no mundo e em Moçambique',
            'Um ponto essencial: intensidade não é sinónimo de impacto igual em todo o lado' => '7. Intensidade não significa o mesmo impacto em todo o lado',
            'Porque vale a pena compreender o El Niño' => '8. Conclusão',
        ],
        'exercise' => <<<'HTML'
<p>Aprenda a classificar episódios de El Niño e La Niña com o índice oficial da NOAA.</p>
<ol>
<li>Abra a tabela do <a href="https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/ensostuff/ONI_v5.php" rel="noopener">Oceanic Niño Index (ONI) da NOAA</a>. Cada valor é a anomalia média de três meses consecutivos na região Niño 3.4.</li>
<li>Um episódio de El Niño corresponde a pelo menos cinco valores seguidos iguais ou superiores a +0,5 °C; La Niña, a cinco valores seguidos iguais ou inferiores a −0,5 °C.</li>
<li>Localize os anos de 2015–2016 e 2023–2024. Qual foi o valor máximo do ONI em cada um? Pela classificação habitual (fraco &lt; 1,0; moderado 1,0–1,4; forte 1,5–1,9; muito forte ≥ 2,0), como os classificaria?</li>
<li>Procure na imprensa ou nos relatórios do INAM notícias de seca no sul de Moçambique nesses anos e compare com a secção 6.</li>
<li>Consulte a última <a href="https://wmo.int/" rel="noopener">actualização El Niño/La Niña da OMM</a> e verifique se a previsão da secção 5 se confirmou.</li>
</ol>
HTML,
        'takeaways' => "O El Niño é a fase quente do ciclo ENOS no Pacífico equatorial; a La Niña é a fase fria\nO índice Niño 3.4 (e o ONI, a sua média de três meses) é o principal indicador\nCada episódio dura normalmente 9 a 12 meses e classifica-se de fraco a muito forte\nEm anos de El Niño, o sul e o centro da África Austral tendem a ter menos chuva\nA intensidade do episódio não determina sozinha o impacto em cada região",
        'sources' => "World Meteorological Organization — El Niño/La Niña Update (Agosto de 2026) | https://wmo.int/\nNOAA Climate Prediction Center — ENSO: Recent Evolution, Current Status and Predictions | https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/enso_advisory/\nNOAA CPC — Oceanic Niño Index (ONI) | https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/ensostuff/ONI_v5.php\nWorld Meteorological Organization — El Niño/La Niña background (2003)",
    ],

    6 => [
        'title' => 'Extrair rios e corpos de água do OpenStreetMap com o Overpass Turbo e exportar para Shapefile',
        'summary' => 'Extraia a rede de rios, canais e corpos de água de uma província (exemplo: Sofala) a partir do OpenStreetMap e converta-a para Shapefile, sem instalar programas.',
        'platform' => 'web', 'level' => 'beginner', 'duration' => 30,
        'objectives' => "Escrever uma consulta Overpass QL para uma área administrativa\nFiltrar linhas de água (rios, ribeiros, canais) e polígonos de água\nExportar o resultado em GeoJSON\nConverter o GeoJSON para Shapefile no Mapshaper\nAvaliar a qualidade e as limitações dos dados do OpenStreetMap",
        'prerequisites' => "Navegador actualizado\nQGIS (opcional, para abrir o resultado)",
        'headings' => [
            'Passo 1: Aceder ao Overpass Turbo e Configurar o Editor' => 'Passo 1: Abrir o Overpass Turbo e preparar o editor',
            'Passo 2: Inserir o Código, Executar e Visualizar os Dados' => 'Passo 2: Inserir a consulta, executar e ver os dados',
            'Passo 3: Exportar e Baixar os Dados em Formato GeoJSON' => 'Passo 3: Exportar os dados em GeoJSON',
            'Passo 4: Importar, Converter e Baixar em Formato Shapefile (.SHP)' => 'Passo 4: Converter para Shapefile no Mapshaper',
            'Vectorização via API OpenStreetMap' => 'Vantagens e limitações dos dados do OpenStreetMap',
        ],
        'replace' => [
            'para limpar o ambiente e preparar o console' => 'e apague o exemplo que aparece no editor',
            'delimitar a busca hidrografia' => 'delimitar a busca de hidrografia',
            'clique no botão <strong>Executar</strong> (índice verde com símbolo de “Play” no canto superior esquerdo)' => 'clique no botão <strong>Executar</strong>, no canto superior esquerdo',
        ],
        'promote_h3' => ['Vectorização via API OpenStreetMap'],
        'exercise' => <<<'HTML'
<p>Repita o processo para outra província e compare com uma fonte independente.</p>
<ol>
<li>No Overpass Turbo, troque <code>"Sofala"</code> por <code>"Zambézia"</code> (o nível administrativo 4 corresponde às províncias) e execute de novo.</li>
<li>Para ficar só com os rios principais, troque <code>"river|stream|canal"</code> por <code>"river"</code> nas duas linhas de <code>waterway</code>.</li>
<li>Exporte em GeoJSON, converta para Shapefile no Mapshaper e abra o resultado no QGIS.</li>
<li>No QGIS, mostre o atributo <code>name</code> como rótulo: quantos rios têm nome registado?</li>
<li>Compare com o conjunto <a href="/dados/rios-e-bacias-hydrosheds">Rios e bacias (HydroSHEDS)</a> do catálogo: onde é que o OpenStreetMap tem mais ou menos detalhe?</li>
</ol>
<p>Ao publicar mapas com estes dados, indique sempre: «© contribuidores do OpenStreetMap (ODbL)».</p>
HTML,
        'takeaways' => "O Overpass Turbo consulta o OpenStreetMap directamente no navegador\nA área de pesquisa define-se pelo nome e pelo admin_level (4 = província em Moçambique)\nwaterway = linhas de água; natural=water = polígonos de água\nO Mapshaper converte GeoJSON em Shapefile em segundos\nA qualidade depende do mapeamento voluntário: confirme com outras fontes e cite a licença ODbL",
        'sources' => "Overpass Turbo | https://overpass-turbo.eu/\nOpenStreetMap Wiki — Overpass API / Language Guide | https://wiki.openstreetmap.org/wiki/Overpass_API/Language_Guide\nOpenStreetMap Wiki — Key:waterway | https://wiki.openstreetmap.org/wiki/Key:waterway\nMapshaper | https://mapshaper.org/\nOpenStreetMap — direitos de autor e licença (ODbL) | https://www.openstreetmap.org/copyright",
    ],

    7 => [
        'title' => 'Árvores contra o calor urbano: identificar ilhas de calor com satélite e planear a arborização',
        'summary' => 'Perceba como as árvores arrefecem as cidades, conheça a regra 3-30-300 e mapeie a temperatura da superfície e o NDVI da sua cidade com Landsat no Google Earth Engine.',
        'platform' => 'gee', 'level' => 'intermediate', 'duration' => 40,
        'objectives' => "Explicar o que é uma ilha de calor urbana e porque se forma\nDescrever os dois mecanismos de arrefecimento das árvores: sombra e evapotranspiração\nAplicar a regra 3-30-300 ao planeamento de um bairro\nCalcular a temperatura da superfície (LST) e o NDVI com Landsat 9\nMedir a relação entre vegetação e temperatura numa cidade",
        'prerequisites' => "Acesso ao Google Earth Engine\nTer feito o tutorial de índices espectrais no Earth Engine (recomendado)",
        'remove_boxes' => true,
        'headings' => [
            '1. Anatomia do Sobreaquecimento Urbano: O Fenómeno das Ilhas de Calor' => '1. O que são as ilhas de calor urbanas',
            '2. A Ciência do Arrefecimento Vegetal: Sombreado e Evapotranspiração' => '2. Como as árvores arrefecem a cidade: sombra e evapotranspiração',
            '3. A Evidência Científica e a Regra do 3-30-300' => '3. A evidência científica e a regra 3-30-300',
            '4. O Papel Estratégico do Planeamento Geospacial (SIG e Detecção Remota)' => '4. Como o SIG e a detecção remota orientam a arborização',
            '1. Sombreado Direto (Bloqueio de Radiação)' => 'Sombra directa (bloqueio da radiação)',
            '2. Evapotranspiração (Arrefecimento Evaporativo)' => 'Evapotranspiração (arrefecimento evaporativo)',
            'Árvores à Vista' => '3 árvores à vista',
            'Cobertura de Copa' => '30% de cobertura de copa',
            'Metros de Distância' => '300 metros até um espaço verde',
        ],
        'replace' => [
            '“caniços urbanos”' => '“desfiladeiros urbanos” (<em>urban canyons</em>)',
            'o ecologista urbano Kornelis van den Bosch' => 'o especialista em florestas urbanas Cecil Konijnendijk van den Bosch',
            'calor sintético' => 'calor',
        ],
        'exercise' => <<<'HTML'
<p>Mapeie a temperatura da superfície e a vegetação da Beira (ou da sua cidade) e verifique se as zonas mais verdes são mais frescas.</p>
<ol>
<li>No <a href="https://code.earthengine.google.com/" rel="noopener">Code Editor</a>, cole o código seguinte e carregue em <strong>Run</strong>:
<pre><code class="language-javascript">var cidade = ee.Geometry.Rectangle([34.82, -19.88, 34.92, -19.76]); // Beira
var l9 = ee.ImageCollection('LANDSAT/LC09/C02/T1_L2')
  .filterBounds(cidade)
  .filterDate('2024-10-01', '2025-03-31')          // estação quente
  .filter(ee.Filter.lt('CLOUD_COVER', 20));

function preparar(img) {
  var limpo = img.select('QA_PIXEL').bitwiseAnd(parseInt('11010', 2)).eq(0);
  var sr = img.select('SR_B.').multiply(0.0000275).add(-0.2);
  var ndvi = sr.normalizedDifference(['SR_B5', 'SR_B4']).rename('NDVI');
  var lst = img.select('ST_B10').multiply(0.00341802).add(149.0).subtract(273.15).rename('LST');
  return ndvi.addBands(lst).updateMask(limpo);
}

var comp = l9.map(preparar).median().clip(cidade);
Map.centerObject(cidade, 13);
Map.addLayer(comp.select('LST'), {min: 25, max: 45, palette: ['blue', 'yellow', 'red']}, 'Temperatura da superfície (°C)');
Map.addLayer(comp.select('NDVI'), {min: 0, max: 0.8, palette: ['white', 'green']}, 'NDVI');

print(comp.select(['NDVI', 'LST']).reduceRegion({
  reducer: ee.Reducer.pearsonsCorrelation(), geometry: cidade, scale: 30, maxPixels: 1e9
}));</code></pre></li>
<li>Alterne as duas camadas: onde estão as zonas mais quentes? Coincidem com bairros sem árvores?</li>
<li>Leia na consola o valor de <code>correlation</code>. Um valor negativo indica que, onde há mais vegetação, a superfície é mais fresca.</li>
<li>Escolha um bairro quente e estime, com a camada NDVI, se cumpre a meta de 30% de cobertura de copa da regra 3-30-300.</li>
</ol>
HTML,
        'takeaways' => "Asfalto, betão e edifícios retêm calor e criam ilhas de calor urbanas\nAs árvores arrefecem por sombra (superfícies 11 a 25 °C mais frescas) e por evapotranspiração (ar 1 a 5 °C mais fresco)\nA regra 3-30-300: 3 árvores à vista, 30% de copa por bairro, 300 m até um espaço verde\nCom Landsat calcula-se a temperatura da superfície (banda ST_B10) e o NDVI da mesma imagem\nCruzar calor, vegetação e população indica onde plantar primeiro",
        'sources' => "FAO — Guidelines on urban and peri-urban forestry (2016) | https://www.fao.org/3/i6210e/i6210e.pdf\nIPCC — AR6, Climate Change 2022: Impacts, Adaptation and Vulnerability | https://www.ipcc.ch/report/ar6/wg2/\nU.S. EPA — Heat Islands | https://www.epa.gov/heatislands\nKonijnendijk, C. C. (2023). Evidence-based guidelines for greener, healthier, more resilient neighbourhoods: Introducing the 3–30–300 rule. Journal of Forestry Research, 34, 821–830 | https://doi.org/10.1007/s11676-022-01523-z\nUSGS — Landsat Collection 2 Level-2 Science Products | https://www.usgs.gov/landsat-missions/landsat-collection-2-level-2-science-products",
    ],

    8 => [
        'title' => 'Para além do Google Earth: monitorizar florestas e terras com imagens multiespectrais e radar',
        'summary' => 'Descubra o que as imagens multiespectrais, o radar e a inteligência artificial mostram que o Google Earth não mostra, e compare-os gratuitamente no Copernicus Browser.',
        'platform' => 'web', 'level' => 'beginner', 'duration' => 25,
        'objectives' => "Perceber os limites das imagens estáticas do Google Earth\nExplicar o que o infravermelho (NIR, SWIR) e o radar (SAR) acrescentam\nConhecer o papel da inteligência artificial na transformação de imagens em alertas\nIdentificar usos em carbono, risco de incêndio e relatórios ESG\nComparar cor natural, NDVI e radar no Copernicus Browser",
        'prerequisites' => "Não precisa de conhecimentos prévios\nNavegador actualizado (o Copernicus Browser pode pedir um registo gratuito)",
        'remove_boxes' => true,
        'headings' => [
            '1. O Problema das Imagens Desactualizadas e Estáticas' => '1. O problema das imagens desactualizadas',
            '2. Ver o “Invisível”: A Magia Além da Luz Visível' => '2. Ver o invisível: infravermelho e radar',
            '3. Da Imagem ao Diagnóstico: A Inteligência Artificial em Acção' => '3. Da imagem ao diagnóstico: inteligência artificial',
            '4. O Impacto Directo no Seu Negócio' => '4. O que muda na gestão de propriedades e projectos',
            'O Futuro da Gestão Territorial Já Chegou' => '5. Conclusão',
            'Detecção Precoce de Stress Hídrico e Saúde' => 'Detecção precoce de stress hídrico',
            'Visão Através de Nuvens e Fumo' => 'Ver através de nuvens e fumo',
            'Alertas Automáticos' => 'Alertas automáticos',
            'Cálculo de Bioma e Carbono' => 'Estimativa de biomassa e carbono',
            'Diagnóstico Automatizado' => 'Diagnóstico automatizado',
        ],
        'replace' => [
            'Entenda o que a tecnologia orbital de alta precisão consegue ver que o olho humano e as ferramentas gratuitas deixam passar.' => 'Neste tutorial vê o que a observação da Terra profissional mostra e o Google Earth deixa passar, e experimenta-o com dados gratuitos.',
        ],
        'exercise' => <<<'HTML'
<p>Veja com os seus olhos a diferença entre uma imagem «bonita» e uma imagem de análise, com dados gratuitos do programa Copernicus.</p>
<ol>
<li>Abra o <a href="https://browser.dataspace.copernicus.eu/" rel="noopener">Copernicus Browser</a> e procure uma área agrícola ou florestal que conheça (por exemplo, Chimoio ou o vale do Zambeze).</li>
<li>Escolha <strong>Sentinel-2 L2A</strong>, uma data recente com poucas nuvens e a visualização <em>True color</em>. Guarde mentalmente a imagem.</li>
<li>Mude para <em>False color</em> (infravermelho): a vegetação vigorosa aparece a vermelho intenso. Que campos parecem mais fracos?</li>
<li>Mude para <em>NDVI</em> e compare: as zonas fracas confirmam-se?</li>
<li>Escolha agora uma data de chuvas com muitas nuvens. Mude para <strong>Sentinel-1</strong> (radar): a área continua visível através das nuvens?</li>
<li>Compare a data da imagem mais recente do Copernicus com a data das imagens do Google Earth da mesma zona.</li>
</ol>
HTML,
        'takeaways' => "As imagens do Google Earth podem ter meses ou anos; o Sentinel-2 revisita cada local a cada 5 dias\nO infravermelho revela stress da vegetação antes de ser visível a olho nu\nO radar (SAR) vê através de nuvens e fumo, de dia e de noite\nA inteligência artificial transforma imagens em alertas, mapas de biomassa e relatórios\nDados auditáveis são essenciais para créditos de carbono, licenças ambientais e ESG",
        'sources' => "Copernicus Browser | https://browser.dataspace.copernicus.eu/\nESA — Sentinel-2 | https://sentiwiki.copernicus.eu/web/s2-mission\nESA — Sentinel-1 | https://sentiwiki.copernicus.eu/web/s1-mission\nNASA Earthdata — What is Synthetic Aperture Radar? | https://www.earthdata.nasa.gov/learn/earth-observation-data-basics/sar",
    ],

    9 => [
        'title' => 'Satélites de observação da Terra: sensores, resoluções e como escolher a imagem certa',
        'summary' => 'Perceba como funcionam os satélites de observação da Terra, a diferença entre sensores ópticos e radar, as quatro resoluções de uma imagem e que satélites gratuitos usar em cada caso.',
        'platform' => 'web', 'level' => 'beginner', 'duration' => 25,
        'objectives' => "Explicar o que é um satélite de observação da Terra e o sensoriamento remoto\nDistinguir sensores ópticos (passivos) e radar (activos)\nConhecer as quatro resoluções: espacial, espectral, temporal e radiométrica\nEscolher entre Sentinel-2, Landsat 8/9 e Sentinel-1 conforme o objectivo\nIdentificar aplicações em gestão florestal e agricultura",
        'prerequisites' => "Não precisa de conhecimentos prévios\nNavegador actualizado para o exercício",
        'remove_boxes' => true,
        'headings' => [
            '1. O que é um Satélite de Observação da Terra?' => '1. O que é um satélite de observação da Terra',
            '2. Óptico vs. Radar (SAR): Qual a diferença?' => '2. Sensores ópticos e radar (SAR): qual a diferença',
            '3. As 4 Resoluções Essenciais' => '3. As quatro resoluções de uma imagem',
            '4. Aplicabilidade na Gestão Florestal e Agricultura' => '4. Aplicações na gestão florestal e na agricultura',
            'Principais Satélites Gratuitos para Uso Profissional' => '5. Satélites gratuitos para uso profissional',
            'Sensores Ópticos (Passivos)' => 'Sensores ópticos (passivos)',
            'Sensores de Radar / SAR (Ativos)' => 'Sensores radar / SAR (activos)',
            'Resolução Espacial' => 'Resolução espacial',
            'Resolução Espectral' => 'Resolução espectral',
            'Resolução Temporal' => 'Resolução temporal',
            'Na Gestão Florestal' => 'Na gestão florestal',
            'Na Agricultura de Precisão' => 'Na agricultura de precisão',
        ],
        'replace' => [
            'Ao escolher uma imagem satelital para o seu projeto, quatro características definem a sua utilidade:' => 'Ao escolher uma imagem de satélite para o seu projecto, quatro características definem a sua utilidade:',
            'Série histórica (desde 1972), uso do solo e geologia.' => 'Série histórica do programa Landsat (desde 1972), uso do solo e geologia.',
        ],
        'append_after' => [
            'Resolução temporal' => '<h3>Resolução radiométrica</h3><p>O número de níveis de brilho que o sensor consegue distinguir. O Sentinel-2 e o Landsat 8 registam 12 bits (4096 níveis) e o Landsat 9 regista 14 bits, o que permite separar diferenças subtis de reflectância, por exemplo em água ou em vegetação muito densa.</p>',
        ],
        'exercise' => <<<'HTML'
<p>Escolha o satélite certo para três situações reais e confirme a escolha no Copernicus Browser.</p>
<ol>
<li>Para cada caso, escolha entre Sentinel-2, Landsat 8/9 e Sentinel-1 (use a tabela da secção 5):
<ul>
<li>a) Mapear as áreas inundadas na Beira durante um ciclone, com o céu coberto;</li>
<li>b) Comparar a cobertura florestal do Niassa em 1990 e hoje;</li>
<li>c) Acompanhar o vigor de uma machamba de milho a cada semana.</li>
</ul></li>
<li>Abra o <a href="https://browser.dataspace.copernicus.eu/" rel="noopener">Copernicus Browser</a>, procure a Beira e compare uma imagem <strong>Sentinel-2</strong> e uma <strong>Sentinel-1</strong> de Março de 2025 (época das chuvas).</li>
<li>Em qual das duas consegue ver o terreno? Isso confirma a sua resposta à alínea a)?</li>
<li>Respostas sugeridas: a) Sentinel-1; b) Landsat (o único com arquivo desde os anos 1980); c) Sentinel-2.</li>
</ol>
HTML,
        'takeaways' => "Os satélites de observação registam a radiação reflectida ou emitida pela superfície\nSensores ópticos precisam de luz solar e céu limpo; o radar funciona com nuvens, fumo e de noite\nQuatro resoluções definem uma imagem: espacial, espectral, temporal e radiométrica\nSentinel-2 para detalhe e frequência, Landsat para séries longas, Sentinel-1 para épocas de chuva\nTodos estes dados são gratuitos e abertos",
        'sources' => "ESA — Sentinel-2 | https://sentiwiki.copernicus.eu/web/s2-mission\nESA — Sentinel-1 | https://sentiwiki.copernicus.eu/web/s1-mission\nUSGS — Landsat 9 | https://www.usgs.gov/landsat-missions/landsat-9\nCopernicus Browser | https://browser.dataspace.copernicus.eu/\nNASA Earthdata — Remote sensing basics | https://www.earthdata.nasa.gov/learn/earth-observation-data-basics/remote-sensing",
    ],
];
