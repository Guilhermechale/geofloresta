<?php

// Tutorial: classificação supervisionada com Random Forest no Google Earth Engine (PT, FR, EN).
// Lido por criar-random-forest.php. O código JavaScript é o mesmo nas três línguas; só os comentários mudam.
$img = '/storage/tutoriais/random-forest';
$fig = fn (string $file, int $w, int $h, string $alt, string $cap) => "<figure><img src=\"{$img}/{$file}\" alt=\"".htmlspecialchars($alt, ENT_QUOTES)."\" width=\"{$w}\" height=\"{$h}\" loading=\"lazy\" decoding=\"async\"><figcaption>{$cap}</figcaption></figure>";

$blocks = [
    'image' => <<<'JS'
// @area@
var area = ee.Geometry.Rectangle([84.25, 27.45, 84.65, 27.75]);
Map.centerObject(area, 11);

// @s2@
var csPlus = ee.ImageCollection('GOOGLE/CLOUD_SCORE_PLUS/V1/S2_HARMONIZED');
var s2 = ee.ImageCollection('COPERNICUS/S2_SR_HARMONIZED')
  .filterBounds(area)
  .filterDate('2025-10-01', '2025-11-30')
  .linkCollection(csPlus, ['cs_cdf'])
  .map(function (img) {
    // @mask@
    return img.updateMask(img.select('cs_cdf').gte(0.6))
      .select(['B2', 'B3', 'B4', 'B8', 'B11', 'B12'])
      .divide(10000);
  })
  .median();

// @idx@
var ndvi = s2.normalizedDifference(['B8', 'B4']).rename('NDVI');
var ndwi = s2.normalizedDifference(['B3', 'B8']).rename('NDWI');
var elevation = ee.Image('NASA/NASADEM_HGT/001').select('elevation');
var slope = ee.Terrain.slope(elevation).rename('slope');

var image = s2.addBands([ndvi, ndwi, elevation, slope]).clip(area);
var bands = image.bandNames();
print('@bands@', bands);

Map.addLayer(image, {bands: ['B4', 'B3', 'B2'], min: 0, max: 0.15}, '@natural@');
Map.addLayer(image, {bands: ['B8', 'B11', 'B4'], min: 0, max: 0.4}, '@false@', false);
JS,
    'drawn' => <<<'JS'
// @drawn@
var samples = forest.merge(grassland).merge(cropland)
  .merge(builtup).merge(sand).merge(water);
JS,
    'auto' => <<<'JS'
// @auto@
var worldcover = ee.ImageCollection('ESA/WorldCover/v200').first()
  .remap([10, 20, 30, 40, 50, 60, 80], [0, 1, 1, 2, 3, 4, 5])
  .rename('landcover');
// @pure@
var pure = worldcover.reduceNeighborhood({
  reducer: ee.Reducer.countDistinct(),
  kernel: ee.Kernel.square(2)
}).eq(1);
var samples = worldcover.updateMask(pure).stratifiedSample({
  numPoints: 250, classBand: 'landcover', region: area,
  scale: 20, seed: 42, geometries: true
});
JS,
    'train' => <<<'JS'
// @extract@
var data = image.sampleRegions({
  collection: samples, properties: ['landcover'], scale: 20, geometries: true
}).randomColumn('random', 42);

// @split@
var training = data.filter(ee.Filter.lt('random', 0.7));
var validation = data.filter(ee.Filter.gte('random', 0.7));
print('@ntrain@', training.size(), '@nval@', validation.size());

// @rf@
var classifier = ee.Classifier.smileRandomForest({numberOfTrees: 100, seed: 42})
  .train({features: training, classProperty: 'landcover', inputProperties: bands});

var classified = image.classify(classifier);
var palette = ['1f6b3a', 'a9c25d', 'f2c14e', 'd1495b', 'd8c8a8', '2f7fc1'];
Map.addLayer(classified, {min: 0, max: 5, palette: palette}, 'Random Forest');
JS,
    'accuracy' => <<<'JS'
// @acc@
var tested = validation.classify(classifier);
var matrix = tested.errorMatrix('landcover', 'classification');
print('@cm@', matrix);
print('@oa@', matrix.accuracy());
print('Kappa', matrix.kappa());
print('@pa@', matrix.producersAccuracy());
print('@ua@', matrix.consumersAccuracy());
JS,
    'results' => <<<'JS'
// @imp@
var importance = ee.Dictionary(classifier.explain().get('importance'));
print(ui.Chart.feature.byProperty(ee.Feature(null, importance))
  .setChartType('ColumnChart')
  .setOptions({title: '@impt@', legend: {position: 'none'}}));

// @areas@
var areas = ee.Image.pixelArea().divide(10000).addBands(classified)
  .reduceRegion({
    reducer: ee.Reducer.sum().group({groupField: 1, groupName: 'landcover'}),
    geometry: area, scale: 20, maxPixels: 1e10
  });
print('@ha@', areas.get('groups'));
JS,
    'export' => <<<'JS'
// @export@
Export.image.toDrive({
  image: classified.toByte(),
  description: 'random_forest_chitwan_2025',
  folder: 'GEOFLORESTA',
  region: area,
  scale: 20,
  crs: 'EPSG:32645',
  maxPixels: 1e10
});
JS,
];

$comments = [
    'pt' => [
        '@area@' => '1. Área de estudo: Chitwan (Nepal). Para outra área, troque o rectângulo ou desenhe um polígono.',
        '@s2@' => '2. Sentinel-2 (reflectância de superfície) da estação seca, sem nuvens, numa só imagem (mediana).',
        '@mask@' => 'Cloud Score+: fica só o píxel com pontuação >= 0,6 (céu limpo); reflectância de 0 a 1.',
        '@idx@' => '3. Variáveis extra: índices de vegetação e de água, altitude e declive.',
        '@bands@' => 'Variáveis', '@natural@' => 'Sentinel-2 cor natural', '@false@' => 'Sentinel-2 falsa cor',
        '@drawn@' => '4A. Amostras desenhadas por si: junte as seis colecções (propriedade landcover de 0 a 5).',
        '@auto@' => '4B. Amostras automáticas a partir do ESA WorldCover 2021 (para testar já).',
        '@pure@' => 'Só píxeis no meio de uma janela 5 x 5 da mesma classe (amostras «puras»).',
        '@extract@' => '5. Valores das variáveis em cada amostra.',
        '@split@' => '70 % para treinar e 30 % para validar.',
        '@ntrain@' => 'Amostras de treino', '@nval@' => 'de validação',
        '@rf@' => '6. Treinar o Random Forest (100 árvores) e classificar a imagem.',
        '@acc@' => '7. Precisão com as amostras de validação (que o modelo não viu).',
        '@cm@' => 'Matriz de confusão', '@oa@' => 'Precisão global', '@pa@' => 'Acerto do produtor', '@ua@' => 'Acerto do utilizador',
        '@imp@' => '8. Importância de cada variável.', '@impt@' => 'Importância das variáveis',
        '@areas@' => '9. Área de cada classe, em hectares.', '@ha@' => 'Área por classe (ha)',
        '@export@' => '10. Exportar o mapa para o Google Drive (GeoTIFF, UTM 45N). Corre no separador Tasks.',
    ],
    'fr' => [
        '@area@' => '1. Zone d’étude : Chitwan (Népal). Pour une autre zone, changez le rectangle ou dessinez un polygone.',
        '@s2@' => '2. Sentinel-2 (réflectance de surface) de saison sèche, sans nuages, en une seule image (médiane).',
        '@mask@' => 'Cloud Score+ : on garde les pixels avec un score >= 0,6 (ciel clair) ; réflectance de 0 à 1.',
        '@idx@' => '3. Variables supplémentaires : indices de végétation et d’eau, altitude et pente.',
        '@bands@' => 'Variables', '@natural@' => 'Sentinel-2 couleurs naturelles', '@false@' => 'Sentinel-2 fausses couleurs',
        '@drawn@' => '4A. Échantillons dessinés par vous : réunir les six collections (propriété landcover de 0 à 5).',
        '@auto@' => '4B. Échantillons automatiques à partir d’ESA WorldCover 2021 (pour tester tout de suite).',
        '@pure@' => 'Seulement les pixels au centre d’une fenêtre 5 x 5 de la même classe (échantillons « purs »).',
        '@extract@' => '5. Valeurs des variables pour chaque échantillon.',
        '@split@' => '70 % pour l’apprentissage et 30 % pour la validation.',
        '@ntrain@' => 'Échantillons d’apprentissage', '@nval@' => 'de validation',
        '@rf@' => '6. Entraîner le Random Forest (100 arbres) et classer l’image.',
        '@acc@' => '7. Précision avec les échantillons de validation (que le modèle n’a pas vus).',
        '@cm@' => 'Matrice de confusion', '@oa@' => 'Précision globale', '@pa@' => 'Précision du producteur', '@ua@' => 'Précision de l’utilisateur',
        '@imp@' => '8. Importance de chaque variable.', '@impt@' => 'Importance des variables',
        '@areas@' => '9. Surface de chaque classe, en hectares.', '@ha@' => 'Surface par classe (ha)',
        '@export@' => '10. Exporter la carte vers Google Drive (GeoTIFF, UTM 45N). À lancer dans l’onglet Tasks.',
    ],
    'en' => [
        '@area@' => '1. Study area: Chitwan (Nepal). For another area, change the rectangle or draw a polygon.',
        '@s2@' => '2. Dry-season, cloud-free Sentinel-2 surface reflectance in a single image (median).',
        '@mask@' => 'Cloud Score+: keep only pixels scoring >= 0.6 (clear sky); reflectance from 0 to 1.',
        '@idx@' => '3. Extra variables: vegetation and water indices, elevation and slope.',
        '@bands@' => 'Variables', '@natural@' => 'Sentinel-2 natural colour', '@false@' => 'Sentinel-2 false colour',
        '@drawn@' => '4A. Samples you drew: merge the six collections (landcover property from 0 to 5).',
        '@auto@' => '4B. Automatic samples from ESA WorldCover 2021 (to test right away).',
        '@pure@' => 'Only pixels in the middle of a 5 x 5 window of the same class ("pure" samples).',
        '@extract@' => '5. Variable values at each sample.',
        '@split@' => '70% for training and 30% for validation.',
        '@ntrain@' => 'Training samples', '@nval@' => 'validation',
        '@rf@' => '6. Train the Random Forest (100 trees) and classify the image.',
        '@acc@' => '7. Accuracy with the validation samples (which the model has not seen).',
        '@cm@' => 'Confusion matrix', '@oa@' => 'Overall accuracy', '@pa@' => 'Producer’s accuracy', '@ua@' => 'User’s accuracy',
        '@imp@' => '8. Importance of each variable.', '@impt@' => 'Variable importance',
        '@areas@' => '9. Area of each class, in hectares.', '@ha@' => 'Area by class (ha)',
        '@export@' => '10. Export the map to Google Drive (GeoTIFF, UTM 45N). Run it from the Tasks tab.',
    ],
];

// Comentários com mais de 72 caracteres passam para a linha seguinte (sem deslizar para o lado no telemóvel)
$wrap = fn (string $src) => preg_replace_callback('#^( *)// (.{73,})$#mu', fn ($m) => $m[1].'// '.str_replace("\n", "\n".$m[1].'// ', wordwrap($m[2], 72, "\n", true)), $src);
$js = fn (string $block, string $l) => $wrap(strtr($blocks[$block], $comments[$l]));
$code = fn (string $block, string $l) => '<pre><code class="language-js">'.htmlspecialchars($js($block, $l), ENT_NOQUOTES).'</code></pre>';

// Script completo (opção B activa; a opção A fica comentada) para descarregar
$header = [
    'pt' => "// GEOFLORESTA · Classificação supervisionada com Random Forest no Google Earth Engine\n// Tutorial: https://geofloresta.com/tutoriais/classificacao-supervisionada-random-forest-google-earth-engine\n// Cole no Code Editor (code.earthengine.google.com) e carregue em Run.\n\n",
    'fr' => "// GEOFLORESTA · Classification supervisée avec Random Forest dans Google Earth Engine\n// Tutoriel : https://geofloresta.com/fr/tutoriels/classification-supervisee-random-forest-google-earth-engine\n// Collez dans le Code Editor (code.earthengine.google.com) et cliquez sur Run.\n\n",
    'en' => "// GEOFLORESTA · Supervised classification with Random Forest in Google Earth Engine\n// Tutorial: https://geofloresta.com/en/tutorials/supervised-classification-random-forest-google-earth-engine\n// Paste into the Code Editor (code.earthengine.google.com) and click Run.\n\n",
];
$script = fn (string $l) => $header[$l].$js('image', $l)."\n\n".preg_replace('/^/m', '// ', $js('drawn', $l))."\n\n".$js('auto', $l)."\n\n".$js('train', $l)."\n\n".$js('accuracy', $l)."\n\n".$js('results', $l)."\n\n".$js('export', $l)."\n";
$dl = fn (string $l, string $label) => "<p><a class=\"btn\" href=\"{$img}/random-forest-gee-{$l}.js\" download>{$label}</a></p>";

$pt = [
    'image' => $code('image', 'pt'), 'drawn' => $code('drawn', 'pt'), 'auto' => $code('auto', 'pt'), 'train' => $code('train', 'pt'),
    'accuracy' => $code('accuracy', 'pt'), 'results' => $code('results', 'pt'), 'export' => $code('export', 'pt'),
];
$fr = array_combine(array_keys($pt), array_map(fn ($b) => $code($b, 'fr'), array_keys($pt)));
$en = array_combine(array_keys($pt), array_map(fn ($b) => $code($b, 'en'), array_keys($pt)));

return [
    'scripts' => ['pt' => $script('pt'), 'fr' => $script('fr'), 'en' => $script('en')],
    'images' => ['fig1-composicao-sentinel2.webp', 'fig2-amostras.webp', 'fig3-mapa-classificado.webp', 'fig4-precisao-importancia.webp'],
    'slug' => [
        'pt' => 'classificacao-supervisionada-random-forest-google-earth-engine',
        'fr' => 'classification-supervisee-random-forest-google-earth-engine',
        'en' => 'supervised-classification-random-forest-google-earth-engine',
    ],
    'title' => [
        'pt' => 'Classificação supervisionada com Random Forest no Google Earth Engine: mapa de uso do solo com inteligência artificial',
        'fr' => 'Classification supervisée avec Random Forest dans Google Earth Engine : carte d’occupation du sol par intelligence artificielle',
        'en' => 'Supervised classification with Random Forest in Google Earth Engine: a land cover map with artificial intelligence',
    ],
    'summary' => [
        'pt' => 'Passo a passo, com código completo e comentado: imagem Sentinel-2 sem nuvens, amostras de treino, Random Forest, matriz de confusão, Kappa, área por classe e exportação para o QGIS. Exemplo real em Chitwan, no Nepal.',
        'fr' => 'Pas à pas, avec un code complet et commenté : image Sentinel-2 sans nuages, échantillons d’apprentissage, Random Forest, matrice de confusion, Kappa, surface par classe et export vers QGIS. Exemple réel à Chitwan, au Népal.',
        'en' => 'Step by step, with complete commented code: a cloud-free Sentinel-2 image, training samples, Random Forest, confusion matrix, Kappa, area by class and export to QGIS. A real example in Chitwan, Nepal.',
    ],
    'seo_title' => [
        'pt' => 'Classificação supervisionada Random Forest no Google Earth Engine (código)',
        'fr' => 'Classification supervisée Random Forest dans Google Earth Engine (code)',
        'en' => 'Random Forest supervised classification in Google Earth Engine (code)',
    ],
    'seo_description' => [
        'pt' => 'Aprenda a classificar o uso do solo com Random Forest e Sentinel-2 no Google Earth Engine: amostras, treino, matriz de confusão, Kappa, áreas e exportação. Código grátis.',
        'fr' => 'Apprenez à classer l’occupation du sol avec Random Forest et Sentinel-2 dans Google Earth Engine : échantillons, matrice de confusion, Kappa, surfaces et export. Code gratuit.',
        'en' => 'Learn to classify land cover with Random Forest and Sentinel-2 in Google Earth Engine: samples, training, confusion matrix, Kappa, areas and export. Free code.',
    ],
    'objectives' => [
        'pt' => "Perceber o que é a classificação supervisionada e como funciona o Random Forest\nPreparar uma imagem Sentinel-2 sem nuvens com índices e relevo\nRecolher boas amostras de treino e de validação\nTreinar o modelo, classificar a imagem e avaliar a precisão (matriz de confusão e Kappa)\nCalcular a área de cada classe e exportar o mapa para o QGIS",
        'fr' => "Comprendre la classification supervisée et le fonctionnement du Random Forest\nPréparer une image Sentinel-2 sans nuages avec des indices et le relief\nCollecter de bons échantillons d’apprentissage et de validation\nEntraîner le modèle, classer l’image et évaluer la précision (matrice de confusion et Kappa)\nCalculer la surface de chaque classe et exporter la carte vers QGIS",
        'en' => "Understand supervised classification and how Random Forest works\nPrepare a cloud-free Sentinel-2 image with indices and terrain\nCollect good training and validation samples\nTrain the model, classify the image and assess accuracy (confusion matrix and Kappa)\nCalculate the area of each class and export the map to QGIS",
    ],
    'prerequisites' => [
        'pt' => "Uma conta Google com acesso ao Google Earth Engine (gratuito para uso não comercial, em earthengine.google.com)\nNoções básicas de sensoriamento remoto (bandas e índices como o NDVI)\nPara o passo final: QGIS instalado (gratuito, em qgis.org)\nNão precisa de saber programar: o código está completo e comentado",
        'fr' => "Un compte Google avec accès à Google Earth Engine (gratuit pour un usage non commercial, sur earthengine.google.com)\nDes notions de télédétection (bandes et indices comme le NDVI)\nPour l’étape finale : QGIS installé (gratuit, sur qgis.org)\nPas besoin de savoir programmer : le code est complet et commenté",
        'en' => "A Google account with access to Google Earth Engine (free for non-commercial use, at earthengine.google.com)\nBasic remote sensing knowledge (bands and indices such as NDVI)\nFor the final step: QGIS installed (free, at qgis.org)\nNo programming experience needed: the code is complete and commented",
    ],
    'body' => [
        'pt' => <<<HTML
<p>Mapas de uso e cobertura do solo estão na base de quase todos os estudos ambientais: inventários florestais, avaliações de impacto, planos de ordenamento, monitoria do desmatamento e projectos de carbono. Hoje, a forma mais usada de os produzir a partir de imagens de satélite é a <strong>classificação supervisionada com Random Forest</strong>, um método de <strong>inteligência artificial</strong> (aprendizagem automática) simples de aplicar, robusto e gratuito no Google Earth Engine.</p>
<p>Neste tutorial faz um mapa com seis classes (floresta, pradaria e matagal, agricultura, área urbana, areia e solo exposto, e água) para a região de <strong>Chitwan, no Nepal</strong>: a cidade de Bharatpur, os rios Narayani e Rapti, os campos agrícolas do vale e a floresta de sal do Parque Nacional de Chitwan, Património Mundial da UNESCO. Depois de perceber o método, aplica-o à sua própria área em qualquer país.</p>
<h2>1. O que é a classificação supervisionada e o Random Forest</h2>
<p>Na <strong>classificação supervisionada</strong>, é o analista que «ensina» o computador. Primeiro, indica exemplos de cada classe: pontos ou polígonos onde sabe que há floresta, água, campos agrícolas, etc. O algoritmo aprende como cada classe aparece nas bandas da imagem e depois atribui uma classe a todos os píxeis. É diferente da classificação <em>não supervisionada</em> (por exemplo, k-means), em que o computador agrupa os píxeis sozinho e o analista só dá nome aos grupos no fim.</p>
<p>O <strong>Random Forest</strong> (floresta aleatória), proposto por Leo Breiman em 2001, é um conjunto de muitas <strong>árvores de decisão</strong>. Cada árvore faz perguntas simples aos dados («o NDVI é maior que 0,6?», «a banda SWIR é menor que 0,1?») até chegar a uma classe. Para que as árvores não sejam todas iguais, cada uma aprende com uma amostra aleatória dos exemplos e, em cada pergunta, só pode escolher entre algumas variáveis sorteadas. No fim, <strong>as árvores votam</strong> e ganha a classe mais votada.</p>
<p>Porque é tão usado em sensoriamento remoto:</p>
<ul>
<li><strong>Dá bons resultados com poucas amostras</strong> e lida bem com dados com algum ruído.</li>
<li><strong>Aceita variáveis muito diferentes</strong> (reflectâncias, índices, altitude, declive) sem ter de as normalizar.</li>
<li><strong>Quase não precisa de afinação:</strong> 100 a 500 árvores e os valores padrão costumam funcionar.</li>
<li><strong>Indica a importância de cada variável</strong>, o que ajuda a perceber o modelo.</li>
</ul>
<h2>2. Preparar a imagem: Sentinel-2 sem nuvens, índices e relevo</h2>
<p>Abra o <a href="https://code.earthengine.google.com" target="_blank" rel="noopener">Code Editor do Earth Engine</a>, crie um script novo e cole o primeiro bloco. O código define a área, junta todas as imagens Sentinel-2 de Outubro e Novembro de 2025 (início da estação seca no Nepal), retira as nuvens com o <strong>Cloud Score+</strong> e calcula a <strong>mediana</strong> de cada píxel. Juntamos ainda o <strong>NDVI</strong> (vegetação), o <strong>NDWI</strong> (água), a <strong>altitude</strong> e o <strong>declive</strong> do NASADEM. Estas variáveis extra ajudam muito o modelo a separar classes parecidas.</p>
{$pt['image']}
<p>Carregue em <em>Run</em>. A imagem aparece no mapa; ligue também a camada de falsa cor no gestor de camadas. Na falsa cor, a vegetação aparece em tons de laranja e castanho, a água em azul forte e as áreas agrícolas e urbanas em azul-claro.</p>
{$fig('pt/fig1-composicao-sentinel2.webp', 1600, 736, 'Composição Sentinel-2 de Chitwan em cor natural e em falsa cor', '<strong>Figura 1.</strong> Mediana Sentinel-2 de Outubro e Novembro de 2025 em Chitwan (cerca de 40 × 33 km). À esquerda, cor natural; à direita, falsa cor (NIR, SWIR, vermelho), em que a floresta, os campos, os rios e as cidades se distinguem com facilidade.')}
<h2>3. Recolher as amostras de treino</h2>
<p>As amostras são a parte mais importante do trabalho: <strong>um modelo nunca é melhor do que as amostras com que aprende</strong>. Há duas formas de as obter.</p>
<p><strong>Opção A: desenhar as amostras (recomendada para trabalhos reais).</strong> No mapa do Code Editor, passe o rato sobre <em>Geometry Imports</em> e carregue em <em>+ new layer</em>. Na roda dentada da camada, mude o nome para <code>forest</code>, escolha <em>Import as: FeatureCollection</em> e adicione uma propriedade <code>landcover</code> com o valor <code>0</code>. Repita para as outras classes:</p>
<table>
<thead><tr><th>Valor de <code>landcover</code></th><th>Nome da camada</th><th>Classe</th><th>Onde procurar em Chitwan</th></tr></thead>
<tbody>
<tr><td>0</td><td><code>forest</code></td><td>Floresta</td><td>Parque Nacional (a sul do rio Rapti) e as encostas a norte</td></tr>
<tr><td>1</td><td><code>grassland</code></td><td>Pradaria e matagal</td><td>Pradarias de capim alto ao longo do Rapti e do Narayani</td></tr>
<tr><td>2</td><td><code>cropland</code></td><td>Agricultura</td><td>Campos do vale, à volta de Bharatpur e Ratnanagar</td></tr>
<tr><td>3</td><td><code>builtup</code></td><td>Área urbana</td><td>Centro de Bharatpur, estradas e vilas</td></tr>
<tr><td>4</td><td><code>sand</code></td><td>Areia e solo exposto</td><td>Bancos de areia e cascalho nos leitos dos rios</td></tr>
<tr><td>5</td><td><code>water</code></td><td>Água</td><td>Rios Narayani e Rapti e lagos</td></tr>
</tbody>
</table>
<p>Desenhe pequenos polígonos (ou pontos) bem dentro de cada classe e junte-os com este bloco:</p>
{$pt['drawn']}
<p>Dicas para boas amostras:</p>
<ul>
<li><strong>Quantidade:</strong> pelo menos 30 a 50 polígonos ou 100 pontos por classe, sobretudo nas classes mais difíceis.</li>
<li><strong>Distribuição:</strong> espalhe as amostras por toda a área, não só num canto da imagem.</li>
<li><strong>Pureza:</strong> desenhe longe das fronteiras entre classes, para cada polígono ter uma só classe.</li>
<li><strong>Variedade:</strong> inclua as variações de cada classe (floresta densa e aberta, campos com e sem cultura, rios largos e estreitos).</li>
<li><strong>Confirme:</strong> use a imagem de alta resolução de fundo (<em>Satellite</em>) e, se puder, dados de campo.</li>
</ul>
<p><strong>Opção B: amostras automáticas (para testar já).</strong> Se quer ver o método a funcionar antes de desenhar, gere 250 pontos por classe a partir do <a href="/dados">mapa ESA WorldCover 2021</a>, só em píxeis «puros» (no meio de uma janela de 5 × 5 píxeis da mesma classe). Use <strong>só uma das opções</strong>: a A ou a B.</p>
{$pt['auto']}
<p>A opção B é óptima para aprender e para criar uma primeira versão do mapa, mas tem um limite: o modelo aprende também os erros do WorldCover e as mudanças que houve desde 2021 (por exemplo, rios que mudaram de leito).</p>
{$fig('pt/fig2-amostras.webp', 1600, 1400, 'Amostras de treino e de validação das seis classes sobre a imagem Sentinel-2 de Chitwan', '<strong>Figura 2.</strong> As 1 500 amostras (250 por classe) geradas com a opção B: círculos para o treino (70 %) e triângulos para a validação (30 %).')}
<h2>4. Treinar o Random Forest e classificar a imagem</h2>
<p>Este bloco lê os valores das 10 variáveis em cada amostra, separa ao acaso <strong>70 % para treino e 30 % para validação</strong>, treina um Random Forest com <strong>100 árvores</strong> e classifica a imagem inteira.</p>
{$pt['train']}
<p>Em poucos segundos aparece no mapa a camada «Random Forest». Compare-a com a imagem: ligue e desligue a camada e procure zonas mal classificadas. Se encontrar erros sistemáticos, acrescente amostras nesses sítios e volte a correr o script. A melhoria das amostras é um processo iterativo.</p>
{$fig('pt/fig3-mapa-classificado.webp', 1600, 1460, 'Mapa de uso e cobertura do solo de Chitwan classificado com Random Forest', '<strong>Figura 3.</strong> Resultado do Random Forest em Chitwan, com píxeis de 20 m. A floresta do Parque Nacional (a sul) e das encostas (a norte) contrasta com o vale agrícola, a cidade de Bharatpur e os rios Narayani e Rapti.')}
<h2>5. Avaliar a precisão</h2>
<p>Um mapa sem avaliação de precisão não pode ser usado com confiança. Classificamos agora as amostras de <strong>validação</strong>, que o modelo não viu no treino, e comparamos a classe prevista com a verdadeira:</p>
{$pt['accuracy']}
<p>Como ler os resultados:</p>
<ul>
<li><strong>Matriz de confusão:</strong> as linhas são a classe verdadeira e as colunas a classe prevista. A diagonal mostra os acertos; o resto mostra que classes se confundem.</li>
<li><strong>Precisão global:</strong> a percentagem de amostras bem classificadas. Acima de 85 % é, em geral, um bom resultado para mapas de uso do solo.</li>
<li><strong>Índice Kappa:</strong> mede o acordo descontando o acerto que se teria por acaso. Acima de 0,8 é considerado um acordo forte.</li>
<li><strong>Acerto do produtor:</strong> de toda a floresta que existe, quanto o mapa apanhou (erros de omissão).</li>
<li><strong>Acerto do utilizador:</strong> do que o mapa diz que é floresta, quanto é mesmo floresta (erros de comissão). É o que interessa a quem usa o mapa. Na consola aparece como «List (1 element)»: abra a seta para ver os seis valores.</li>
</ul>
{$fig('pt/fig4-precisao-importancia.webp', 1600, 693, 'Matriz de confusão do Random Forest em Chitwan e gráfico da importância das variáveis', '<strong>Figura 4.</strong> À esquerda, a matriz de confusão das 450 amostras de validação: precisão global de 84,9 % e Kappa de 0,82. À direita, a importância relativa de cada variável no modelo.')}
<p>No nosso teste, a <strong>floresta</strong> foi a classe mais fiável (99 % de acerto do utilizador). As maiores confusões foram entre <strong>areia e água</strong>, nos leitos dos rios, que mudam de ano para ano, e entre <strong>agricultura e pradaria</strong>, que no fim da época das chuvas podem ter um aspecto muito parecido. São os sítios onde vale a pena acrescentar amostras ou variáveis, por exemplo imagens de outra estação.</p>
<p>Os números da figura foram obtidos pela GEOFLORESTA com os mesmos dados Sentinel-2, as mesmas variáveis, as amostras da opção B e um Random Forest de 100 árvores. Ao correr o script no Earth Engine, obtivemos os mesmos 84,9 % e Kappa de 0,82. Se mudar as amostras, a área ou o <code>seed</code>, os valores mudam um pouco.</p>
<h2>6. Importância das variáveis e área de cada classe</h2>
{$pt['results']}
<p>O gráfico mostra quanto cada variável contribuiu para separar as classes. Em Chitwan, o <strong>NDVI</strong> e o <strong>NDWI</strong> foram as variáveis mais úteis, seguidos da banda azul (B2) e da <strong>altitude</strong>, que ajuda a separar a floresta das encostas dos campos do vale. Se uma variável tiver uma importância muito baixa, pode experimentar retirá-la.</p>
<p>As áreas aparecem na consola como uma lista de classes (0 a 5) com a soma em hectares. No nosso resultado:</p>
<table>
<thead><tr><th>Classe</th><th>Área (ha)</th><th>% da área</th></tr></thead>
<tbody>
<tr><td>Floresta</td><td>77 052</td><td>56,2</td></tr>
<tr><td>Agricultura</td><td>37 916</td><td>27,7</td></tr>
<tr><td>Pradaria e matagal</td><td>10 492</td><td>7,7</td></tr>
<tr><td>Área urbana</td><td>6 496</td><td>4,7</td></tr>
<tr><td>Areia e solo exposto</td><td>3 003</td><td>2,2</td></tr>
<tr><td>Água</td><td>2 041</td><td>1,5</td></tr>
</tbody>
</table>
<p>Atenção: a área contada píxel a píxel no mapa tem os erros da classificação. Em relatórios, o mais correcto é <strong>ajustar as áreas com a matriz de confusão</strong> (método de Olofsson et al., 2014), que também dá o intervalo de confiança de cada área.</p>
<h2>7. Exportar o mapa e terminar no QGIS</h2>
{$pt['export']}
<p>Depois de correr o script, abra o separador <em>Tasks</em> (à direita) e carregue em <em>Run</em> na tarefa <code>random_forest_chitwan_2025</code>. O GeoTIFF fica na pasta GEOFLORESTA do seu Google Drive, em UTM 45N (EPSG:32645). Para outra área, troque o EPSG pela zona UTM correspondente.</p>
<ol>
<li>Arraste o ficheiro para o QGIS.</li>
<li>Em <em>Propriedades → Simbologia</em>, escolha <em>Valores únicos em banda única (Paletted/Unique values)</em>, carregue em <em>Classificar</em> e dê a cada valor (0 a 5) o nome e a cor da classe.</li>
<li>Acrescente a legenda, a escala, o norte e a fonte («Sentinel-2, ESA/Copernicus; classificação Random Forest no Google Earth Engine») no <em>Compositor de impressão</em>.</li>
<li>Para vectorizar o resultado, use <em>Raster → Conversão → Poligonizar</em>.</li>
</ol>
<p>Prefere o script completo num só ficheiro? Descarregue-o, cole-o no Code Editor e carregue em <em>Run</em>. Vem com a opção B activa e a opção A comentada.</p>
{$dl('pt', 'Descarregar o script completo (.js)')}
<h2>8. Limitações e boas práticas</h2>
<ul>
<li><strong>Amostras vizinhas inflacionam a precisão.</strong> Pontos de treino e de validação muito próximos são quase iguais, e a precisão parece melhor do que é. Num trabalho real, valide com amostras independentes, de preferência recolhidas no campo ou separadas por polígono, e não por píxel.</li>
<li><strong>Classes raras ficam sobrestimadas</strong> quando se usa o mesmo número de amostras em todas as classes. Em Chitwan, a pradaria ocupa 7,7 % do mapa, mais do que no WorldCover. Confirme com imagens de alta resolução e ajuste as amostras.</li>
<li><strong>Uma só estação não chega para tudo.</strong> Culturas, pradarias e solo exposto mudam ao longo do ano. Juntar medianas de duas estações (seca e chuvosa) costuma melhorar a separação.</li>
<li><strong>Nuvens e sombras:</strong> em regiões muito nubladas, alargue o período ou junte imagens de radar Sentinel-1, que vêem através das nuvens.</li>
<li><strong>Uso comercial:</strong> o Earth Engine é gratuito para investigação, ensino e organizações sem fins lucrativos; o uso comercial requer uma licença.</li>
</ul>
<h2>9. Perguntas frequentes</h2>
<p><strong>Quantas árvores devo usar?</strong> Entre 100 e 500. Acima disso, a precisão quase não muda e o cálculo fica mais lento.</p>
<p><strong>Posso usar Landsat em vez de Sentinel-2?</strong> Sim. Troque a colecção por <code>LANDSAT/LC09/C02/T1_L2</code> (ou a do Landsat 8), ajuste os nomes e a escala das bandas e use 30 m. É útil para mapas de anos anteriores a 2017.</p>
<p><strong>Funciona noutro país?</strong> Sim, em qualquer lugar do mundo. Mude a área, as datas (escolha a estação seca do seu país) e o EPSG da exportação, e recolha amostras da sua região.</p>
<p><strong>Random Forest ou deep learning?</strong> As redes neurais profundas podem ser mais precisas em imagens de muito alta resolução, mas exigem milhares de amostras e computadores potentes. Para Sentinel-2 e Landsat, o Random Forest continua a ser uma das melhores escolhas.</p>
<p>Precisa de um mapa de uso do solo validado no campo, de uma análise de mudanças ao longo dos anos ou de formação para a sua equipa? <a href="/contacto?assunto=proposal">Peça uma proposta à GEOFLORESTA</a>.</p>
HTML,
        'fr' => <<<HTML
<p>Les cartes d’occupation et d’utilisation du sol sont à la base de presque toutes les études environnementales : inventaires forestiers, études d’impact, plans d’aménagement, suivi de la déforestation et projets carbone. Aujourd’hui, la méthode la plus utilisée pour les produire à partir d’images satellite est la <strong>classification supervisée avec Random Forest</strong>, une méthode d’<strong>intelligence artificielle</strong> (apprentissage automatique) simple à appliquer, robuste et gratuite dans Google Earth Engine.</p>
<p>Dans ce tutoriel, vous réalisez une carte de six classes (forêt, prairie et fourrés, agriculture, zone urbaine, sable et sol nu, eau) pour la région de <strong>Chitwan, au Népal</strong> : la ville de Bharatpur, les rivières Narayani et Rapti, les champs de la vallée et la forêt de sal du parc national de Chitwan, inscrit au patrimoine mondial de l’UNESCO. Une fois la méthode comprise, vous l’appliquez à votre propre zone, dans n’importe quel pays.</p>
<h2>1. La classification supervisée et le Random Forest</h2>
<p>Dans la <strong>classification supervisée</strong>, c’est l’analyste qui « enseigne » à l’ordinateur. Il indique d’abord des exemples de chaque classe : des points ou des polygones où il sait qu’il y a de la forêt, de l’eau, des champs, etc. L’algorithme apprend comment chaque classe apparaît dans les bandes de l’image, puis attribue une classe à tous les pixels. C’est différent de la classification <em>non supervisée</em> (par exemple k-means), où l’ordinateur regroupe seul les pixels et où l’analyste ne nomme les groupes qu’à la fin.</p>
<p>Le <strong>Random Forest</strong> (forêt aléatoire), proposé par Leo Breiman en 2001, est un ensemble de nombreux <strong>arbres de décision</strong>. Chaque arbre pose des questions simples aux données (« le NDVI est-il supérieur à 0,6 ? », « la bande SWIR est-elle inférieure à 0,1 ? ») jusqu’à arriver à une classe. Pour que les arbres ne soient pas identiques, chacun apprend sur un tirage aléatoire des exemples et, à chaque question, ne choisit qu’entre quelques variables tirées au sort. À la fin, <strong>les arbres votent</strong> et la classe la plus votée l’emporte.</p>
<p>Pourquoi il est si utilisé en télédétection :</p>
<ul>
<li><strong>Il donne de bons résultats avec peu d’échantillons</strong> et tolère un certain bruit dans les données.</li>
<li><strong>Il accepte des variables très différentes</strong> (réflectances, indices, altitude, pente) sans normalisation.</li>
<li><strong>Il ne demande presque aucun réglage :</strong> 100 à 500 arbres et les valeurs par défaut suffisent généralement.</li>
<li><strong>Il indique l’importance de chaque variable</strong>, ce qui aide à comprendre le modèle.</li>
</ul>
<h2>2. Préparer l’image : Sentinel-2 sans nuages, indices et relief</h2>
<p>Ouvrez le <a href="https://code.earthengine.google.com" target="_blank" rel="noopener">Code Editor d’Earth Engine</a>, créez un nouveau script et collez le premier bloc. Le code définit la zone, réunit toutes les images Sentinel-2 d’octobre et novembre 2025 (début de la saison sèche au Népal), retire les nuages avec <strong>Cloud Score+</strong> et calcule la <strong>médiane</strong> de chaque pixel. On ajoute aussi le <strong>NDVI</strong> (végétation), le <strong>NDWI</strong> (eau), l’<strong>altitude</strong> et la <strong>pente</strong> issues du NASADEM. Ces variables supplémentaires aident beaucoup le modèle à séparer des classes proches.</p>
{$fr['image']}
<p>Cliquez sur <em>Run</em>. L’image apparaît sur la carte ; activez aussi la couche en fausses couleurs dans le gestionnaire de couches. En fausses couleurs, la végétation apparaît en orange et brun, l’eau en bleu vif et les zones agricoles et urbaines en bleu clair.</p>
{$fig('fr/fig1-composicao-sentinel2.webp', 1600, 736, 'Composition Sentinel-2 de Chitwan en couleurs naturelles et en fausses couleurs', '<strong>Figure 1.</strong> Médiane Sentinel-2 d’octobre et novembre 2025 à Chitwan (environ 40 × 33 km). À gauche, couleurs naturelles ; à droite, fausses couleurs (PIR, SWIR, rouge), où la forêt, les champs, les rivières et les villes se distinguent facilement.')}
<h2>3. Collecter les échantillons d’apprentissage</h2>
<p>Les échantillons sont la partie la plus importante du travail : <strong>un modèle n’est jamais meilleur que les échantillons dont il apprend</strong>. Il y a deux façons de les obtenir.</p>
<p><strong>Option A : dessiner les échantillons (recommandée pour un vrai travail).</strong> Sur la carte du Code Editor, survolez <em>Geometry Imports</em> et cliquez sur <em>+ new layer</em>. Dans la roue dentée de la couche, renommez-la <code>forest</code>, choisissez <em>Import as: FeatureCollection</em> et ajoutez une propriété <code>landcover</code> de valeur <code>0</code>. Répétez pour les autres classes :</p>
<table>
<thead><tr><th>Valeur de <code>landcover</code></th><th>Nom de la couche</th><th>Classe</th><th>Où chercher à Chitwan</th></tr></thead>
<tbody>
<tr><td>0</td><td><code>forest</code></td><td>Forêt</td><td>Parc national (au sud de la Rapti) et versants au nord</td></tr>
<tr><td>1</td><td><code>grassland</code></td><td>Prairie et fourrés</td><td>Prairies de hautes herbes le long de la Rapti et de la Narayani</td></tr>
<tr><td>2</td><td><code>cropland</code></td><td>Agriculture</td><td>Champs de la vallée, autour de Bharatpur et Ratnanagar</td></tr>
<tr><td>3</td><td><code>builtup</code></td><td>Zone urbaine</td><td>Centre de Bharatpur, routes et villages</td></tr>
<tr><td>4</td><td><code>sand</code></td><td>Sable et sol nu</td><td>Bancs de sable et de graviers dans le lit des rivières</td></tr>
<tr><td>5</td><td><code>water</code></td><td>Eau</td><td>Rivières Narayani et Rapti et lacs</td></tr>
</tbody>
</table>
<p>Dessinez de petits polygones (ou des points) bien à l’intérieur de chaque classe, puis réunissez-les avec ce bloc :</p>
{$fr['drawn']}
<p>Conseils pour de bons échantillons :</p>
<ul>
<li><strong>Quantité :</strong> au moins 30 à 50 polygones ou 100 points par classe, surtout pour les classes difficiles.</li>
<li><strong>Répartition :</strong> répartissez les échantillons sur toute la zone, pas seulement dans un coin de l’image.</li>
<li><strong>Pureté :</strong> dessinez loin des limites entre classes, pour que chaque polygone n’ait qu’une seule classe.</li>
<li><strong>Variété :</strong> incluez les variantes de chaque classe (forêt dense et claire, champs cultivés ou non, grandes et petites rivières).</li>
<li><strong>Vérifiez :</strong> utilisez l’image haute résolution en fond (<em>Satellite</em>) et, si possible, des données de terrain.</li>
</ul>
<p><strong>Option B : échantillons automatiques (pour tester tout de suite).</strong> Pour voir la méthode fonctionner avant de dessiner, générez 250 points par classe à partir de la <a href="/fr/donnees">carte ESA WorldCover 2021</a>, uniquement sur des pixels « purs » (au centre d’une fenêtre de 5 × 5 pixels de la même classe). Utilisez <strong>une seule option</strong> : A ou B.</p>
{$fr['auto']}
<p>L’option B est idéale pour apprendre et produire une première version de la carte, mais elle a une limite : le modèle apprend aussi les erreurs de WorldCover et les changements survenus depuis 2021 (par exemple, des rivières qui ont changé de lit).</p>
{$fig('fr/fig2-amostras.webp', 1600, 1400, 'Échantillons d’apprentissage et de validation des six classes sur l’image Sentinel-2 de Chitwan', '<strong>Figure 2.</strong> Les 1 500 échantillons (250 par classe) générés avec l’option B : cercles pour l’apprentissage (70 %) et triangles pour la validation (30 %).')}
<h2>4. Entraîner le Random Forest et classer l’image</h2>
<p>Ce bloc lit les valeurs des 10 variables pour chaque échantillon, sépare au hasard <strong>70 % pour l’apprentissage et 30 % pour la validation</strong>, entraîne un Random Forest de <strong>100 arbres</strong> et classe toute l’image.</p>
{$fr['train']}
<p>En quelques secondes, la couche « Random Forest » apparaît sur la carte. Comparez-la avec l’image : activez et désactivez la couche et cherchez les zones mal classées. Si vous trouvez des erreurs systématiques, ajoutez des échantillons à ces endroits et relancez le script. L’amélioration des échantillons est un processus itératif.</p>
{$fig('fr/fig3-mapa-classificado.webp', 1600, 1460, 'Carte d’occupation du sol de Chitwan classée avec Random Forest', '<strong>Figure 3.</strong> Résultat du Random Forest à Chitwan, avec des pixels de 20 m. La forêt du parc national (au sud) et des versants (au nord) contraste avec la vallée agricole, la ville de Bharatpur et les rivières Narayani et Rapti.')}
<h2>5. Évaluer la précision</h2>
<p>Une carte sans évaluation de la précision ne peut pas être utilisée en confiance. On classe maintenant les échantillons de <strong>validation</strong>, que le modèle n’a pas vus pendant l’apprentissage, et on compare la classe prédite à la vraie :</p>
{$fr['accuracy']}
<p>Comment lire les résultats :</p>
<ul>
<li><strong>Matrice de confusion :</strong> les lignes sont la vraie classe et les colonnes la classe prédite. La diagonale montre les bonnes réponses ; le reste montre quelles classes se confondent.</li>
<li><strong>Précision globale :</strong> le pourcentage d’échantillons bien classés. Au-delà de 85 %, c’est en général un bon résultat pour une carte d’occupation du sol.</li>
<li><strong>Indice Kappa :</strong> mesure l’accord en retirant la part due au hasard. Au-delà de 0,8, l’accord est considéré comme fort.</li>
<li><strong>Précision du producteur :</strong> de toute la forêt qui existe, quelle part la carte a trouvée (erreurs d’omission).</li>
<li><strong>Précision de l’utilisateur :</strong> de ce que la carte appelle forêt, quelle part est vraiment de la forêt (erreurs de commission). C’est ce qui compte pour l’utilisateur de la carte. Dans la console, elle s’affiche comme « List (1 element) » : ouvrez la flèche pour voir les six valeurs.</li>
</ul>
{$fig('fr/fig4-precisao-importancia.webp', 1600, 693, 'Matrice de confusion du Random Forest à Chitwan et graphique de l’importance des variables', '<strong>Figure 4.</strong> À gauche, la matrice de confusion des 450 échantillons de validation : précision globale de 84,9 % et Kappa de 0,82. À droite, l’importance relative de chaque variable.')}
<p>Dans notre test, la <strong>forêt</strong> a été la classe la plus fiable (99 % de précision de l’utilisateur). Les principales confusions concernent le <strong>sable et l’eau</strong>, dans le lit des rivières qui change d’une année à l’autre, et l’<strong>agriculture et la prairie</strong>, qui peuvent se ressembler beaucoup en fin de saison des pluies. C’est là qu’il vaut la peine d’ajouter des échantillons ou des variables, par exemple des images d’une autre saison.</p>
<p>Les chiffres de la figure ont été obtenus par GEOFLORESTA avec les mêmes données Sentinel-2, les mêmes variables, les échantillons de l’option B et un Random Forest de 100 arbres. En lançant le script dans Earth Engine, nous avons obtenu les mêmes 84,9 % et un Kappa de 0,82. Si vous changez les échantillons, la zone ou le <code>seed</code>, les valeurs changent un peu.</p>
<h2>6. Importance des variables et surface de chaque classe</h2>
{$fr['results']}
<p>Le graphique montre la contribution de chaque variable à la séparation des classes. À Chitwan, le <strong>NDVI</strong> et le <strong>NDWI</strong> ont été les variables les plus utiles, suivis de la bande bleue (B2) et de l’<strong>altitude</strong>, qui aide à séparer la forêt des versants des champs de la vallée. Si une variable a une importance très faible, vous pouvez essayer de la retirer.</p>
<p>Les surfaces apparaissent dans la console sous forme d’une liste de classes (0 à 5) avec la somme en hectares. Dans notre résultat :</p>
<table>
<thead><tr><th>Classe</th><th>Surface (ha)</th><th>% de la zone</th></tr></thead>
<tbody>
<tr><td>Forêt</td><td>77 052</td><td>56,2</td></tr>
<tr><td>Agriculture</td><td>37 916</td><td>27,7</td></tr>
<tr><td>Prairie et fourrés</td><td>10 492</td><td>7,7</td></tr>
<tr><td>Zone urbaine</td><td>6 496</td><td>4,7</td></tr>
<tr><td>Sable et sol nu</td><td>3 003</td><td>2,2</td></tr>
<tr><td>Eau</td><td>2 041</td><td>1,5</td></tr>
</tbody>
</table>
<p>Attention : la surface comptée pixel par pixel contient les erreurs de la classification. Dans un rapport, il est plus juste d’<strong>ajuster les surfaces avec la matrice de confusion</strong> (méthode d’Olofsson et al., 2014), qui donne aussi l’intervalle de confiance de chaque surface.</p>
<h2>7. Exporter la carte et finir dans QGIS</h2>
{$fr['export']}
<p>Après avoir lancé le script, ouvrez l’onglet <em>Tasks</em> (à droite) et cliquez sur <em>Run</em> pour la tâche <code>random_forest_chitwan_2025</code>. Le GeoTIFF arrive dans le dossier GEOFLORESTA de votre Google Drive, en UTM 45N (EPSG:32645). Pour une autre zone, remplacez l’EPSG par la zone UTM correspondante.</p>
<ol>
<li>Faites glisser le fichier dans QGIS.</li>
<li>Dans <em>Propriétés → Symbologie</em>, choisissez <em>Valeurs uniques / Palette</em>, cliquez sur <em>Classer</em> et donnez à chaque valeur (0 à 5) le nom et la couleur de sa classe.</li>
<li>Ajoutez la légende, l’échelle, le nord et la source (« Sentinel-2, ESA/Copernicus ; classification Random Forest dans Google Earth Engine ») dans la <em>Mise en page</em>.</li>
<li>Pour vectoriser le résultat, utilisez <em>Raster → Conversion → Polygoniser</em>.</li>
</ol>
<p>Vous préférez le script complet en un seul fichier ? Téléchargez-le, collez-le dans le Code Editor et cliquez sur <em>Run</em>. L’option B y est active et l’option A en commentaire.</p>
{$dl('fr', 'Télécharger le script complet (.js)')}
<h2>8. Limites et bonnes pratiques</h2>
<ul>
<li><strong>Des échantillons voisins gonflent la précision.</strong> Des points d’apprentissage et de validation très proches sont presque identiques, et la précision paraît meilleure qu’elle ne l’est. Dans un vrai travail, validez avec des échantillons indépendants, de préférence collectés sur le terrain ou séparés par polygone et non par pixel.</li>
<li><strong>Les classes rares sont surestimées</strong> quand on utilise le même nombre d’échantillons pour toutes les classes. À Chitwan, la prairie occupe 7,7 % de la carte, plus que dans WorldCover. Vérifiez avec des images haute résolution et ajustez les échantillons.</li>
<li><strong>Une seule saison ne suffit pas toujours.</strong> Cultures, prairies et sol nu changent au fil de l’année. Combiner les médianes de deux saisons (sèche et humide) améliore souvent la séparation.</li>
<li><strong>Nuages et ombres :</strong> dans les régions très nuageuses, élargissez la période ou ajoutez des images radar Sentinel-1, qui voient à travers les nuages.</li>
<li><strong>Usage commercial :</strong> Earth Engine est gratuit pour la recherche, l’enseignement et les organisations à but non lucratif ; l’usage commercial nécessite une licence.</li>
</ul>
<h2>9. Questions fréquentes</h2>
<p><strong>Combien d’arbres utiliser ?</strong> Entre 100 et 500. Au-delà, la précision change à peine et le calcul devient plus lent.</p>
<p><strong>Puis-je utiliser Landsat au lieu de Sentinel-2 ?</strong> Oui. Remplacez la collection par <code>LANDSAT/LC09/C02/T1_L2</code> (ou celle de Landsat 8), adaptez les noms et l’échelle des bandes et utilisez 30 m. C’est utile pour les cartes antérieures à 2017.</p>
<p><strong>Cela fonctionne-t-il dans un autre pays ?</strong> Oui, partout dans le monde. Changez la zone, les dates (choisissez la saison sèche de votre pays) et l’EPSG de l’export, et collectez des échantillons de votre région.</p>
<p><strong>Random Forest ou deep learning ?</strong> Les réseaux de neurones profonds peuvent être plus précis sur des images à très haute résolution, mais ils exigent des milliers d’échantillons et des ordinateurs puissants. Pour Sentinel-2 et Landsat, le Random Forest reste l’un des meilleurs choix.</p>
<p>Besoin d’une carte d’occupation du sol validée sur le terrain, d’une analyse des changements sur plusieurs années ou d’une formation pour votre équipe ? <a href="/fr/contact?assunto=proposal">Demandez une proposition à GEOFLORESTA</a>.</p>
HTML,
        'en' => <<<HTML
<p>Land use and land cover maps underpin almost every environmental study: forest inventories, impact assessments, land-use plans, deforestation monitoring and carbon projects. Today, the most widely used way to produce them from satellite imagery is <strong>supervised classification with Random Forest</strong>, an <strong>artificial intelligence</strong> (machine learning) method that is simple to apply, robust and free in Google Earth Engine.</p>
<p>In this tutorial you make a six-class map (forest, grassland and shrubland, cropland, built-up, sand and bare soil, and water) of the <strong>Chitwan region in Nepal</strong>: the city of Bharatpur, the Narayani and Rapti rivers, the farmland of the valley and the sal forest of Chitwan National Park, a UNESCO World Heritage Site. Once you understand the method, you apply it to your own area in any country.</p>
<h2>1. What supervised classification and Random Forest are</h2>
<p>In <strong>supervised classification</strong>, the analyst “teaches” the computer. First, you give examples of each class: points or polygons where you know there is forest, water, cropland and so on. The algorithm learns how each class looks in the image bands and then assigns a class to every pixel. This differs from <em>unsupervised</em> classification (for example k-means), where the computer groups the pixels by itself and the analyst only names the groups at the end.</p>
<p><strong>Random Forest</strong>, proposed by Leo Breiman in 2001, is an ensemble of many <strong>decision trees</strong>. Each tree asks the data simple questions (“is NDVI greater than 0.6?”, “is the SWIR band lower than 0.1?”) until it reaches a class. To keep the trees from being identical, each one learns from a random sample of the examples and, at each question, can only choose among a few randomly drawn variables. In the end, <strong>the trees vote</strong> and the class with the most votes wins.</p>
<p>Why it is so popular in remote sensing:</p>
<ul>
<li><strong>It performs well with few samples</strong> and copes with some noise in the data.</li>
<li><strong>It accepts very different variables</strong> (reflectances, indices, elevation, slope) without normalising them.</li>
<li><strong>It needs almost no tuning:</strong> 100 to 500 trees and the default settings usually work.</li>
<li><strong>It reports the importance of each variable</strong>, which helps you understand the model.</li>
</ul>
<h2>2. Prepare the image: cloud-free Sentinel-2, indices and terrain</h2>
<p>Open the <a href="https://code.earthengine.google.com" target="_blank" rel="noopener">Earth Engine Code Editor</a>, create a new script and paste the first block. The code defines the area, gathers every Sentinel-2 image from October and November 2025 (the start of the dry season in Nepal), removes clouds with <strong>Cloud Score+</strong> and computes the <strong>median</strong> of each pixel. We also add <strong>NDVI</strong> (vegetation), <strong>NDWI</strong> (water), <strong>elevation</strong> and <strong>slope</strong> from NASADEM. These extra variables help the model a lot to separate similar classes.</p>
{$en['image']}
<p>Click <em>Run</em>. The image appears on the map; also turn on the false-colour layer in the layer manager. In false colour, vegetation shows in orange and brown, water in deep blue and cropland and built-up areas in light blue.</p>
{$fig('en/fig1-composicao-sentinel2.webp', 1600, 736, 'Sentinel-2 composite of Chitwan in natural colour and false colour', '<strong>Figure 1.</strong> Sentinel-2 median for October and November 2025 in Chitwan (about 40 × 33 km). Left, natural colour; right, false colour (NIR, SWIR, red), where forest, fields, rivers and towns are easy to tell apart.')}
<h2>3. Collect the training samples</h2>
<p>Samples are the most important part of the job: <strong>a model is never better than the samples it learns from</strong>. There are two ways to get them.</p>
<p><strong>Option A: draw the samples (recommended for real work).</strong> On the Code Editor map, hover over <em>Geometry Imports</em> and click <em>+ new layer</em>. In the layer’s gear icon, rename it <code>forest</code>, choose <em>Import as: FeatureCollection</em> and add a <code>landcover</code> property with the value <code>0</code>. Repeat for the other classes:</p>
<table>
<thead><tr><th><code>landcover</code> value</th><th>Layer name</th><th>Class</th><th>Where to look in Chitwan</th></tr></thead>
<tbody>
<tr><td>0</td><td><code>forest</code></td><td>Forest</td><td>The National Park (south of the Rapti) and the northern slopes</td></tr>
<tr><td>1</td><td><code>grassland</code></td><td>Grassland and shrubland</td><td>Tall grasslands along the Rapti and Narayani</td></tr>
<tr><td>2</td><td><code>cropland</code></td><td>Cropland</td><td>Valley fields around Bharatpur and Ratnanagar</td></tr>
<tr><td>3</td><td><code>builtup</code></td><td>Built-up</td><td>Central Bharatpur, roads and villages</td></tr>
<tr><td>4</td><td><code>sand</code></td><td>Sand and bare soil</td><td>Sand and gravel bars in the riverbeds</td></tr>
<tr><td>5</td><td><code>water</code></td><td>Water</td><td>The Narayani and Rapti rivers and lakes</td></tr>
</tbody>
</table>
<p>Draw small polygons (or points) well inside each class and merge them with this block:</p>
{$en['drawn']}
<p>Tips for good samples:</p>
<ul>
<li><strong>Quantity:</strong> at least 30 to 50 polygons or 100 points per class, especially for the harder classes.</li>
<li><strong>Distribution:</strong> spread the samples across the whole area, not just one corner of the image.</li>
<li><strong>Purity:</strong> draw away from class boundaries, so that each polygon holds a single class.</li>
<li><strong>Variety:</strong> include the variations within each class (dense and open forest, fields with and without crops, wide and narrow rivers).</li>
<li><strong>Check:</strong> use the high-resolution basemap (<em>Satellite</em>) and, if you can, field data.</li>
</ul>
<p><strong>Option B: automatic samples (to test right away).</strong> To see the method working before drawing, generate 250 points per class from the <a href="/en/data">ESA WorldCover 2021 map</a>, only on “pure” pixels (in the middle of a 5 × 5 pixel window of the same class). Use <strong>only one option</strong>: A or B.</p>
{$en['auto']}
<p>Option B is great for learning and for a first version of the map, but it has a limit: the model also learns WorldCover’s errors and any changes since 2021 (for example, rivers that have shifted course).</p>
{$fig('en/fig2-amostras.webp', 1600, 1400, 'Training and validation samples of the six classes over the Sentinel-2 image of Chitwan', '<strong>Figure 2.</strong> The 1,500 samples (250 per class) generated with option B: circles for training (70%) and triangles for validation (30%).')}
<h2>4. Train the Random Forest and classify the image</h2>
<p>This block reads the values of the 10 variables at each sample, randomly splits them into <strong>70% for training and 30% for validation</strong>, trains a Random Forest with <strong>100 trees</strong> and classifies the whole image.</p>
{$en['train']}
<p>Within seconds, the “Random Forest” layer appears on the map. Compare it with the image: toggle the layer on and off and look for misclassified areas. If you find systematic errors, add samples in those places and run the script again. Improving the samples is an iterative process.</p>
{$fig('en/fig3-mapa-classificado.webp', 1600, 1460, 'Land cover map of Chitwan classified with Random Forest', '<strong>Figure 3.</strong> Random Forest result for Chitwan, with 20 m pixels. The forest of the National Park (south) and the slopes (north) contrasts with the farmed valley, the city of Bharatpur and the Narayani and Rapti rivers.')}
<h2>5. Assess the accuracy</h2>
<p>A map without an accuracy assessment cannot be used with confidence. We now classify the <strong>validation</strong> samples, which the model did not see during training, and compare the predicted class with the true one:</p>
{$en['accuracy']}
<p>How to read the results:</p>
<ul>
<li><strong>Confusion matrix:</strong> rows are the true class and columns the predicted class. The diagonal shows the correct answers; the rest shows which classes get confused.</li>
<li><strong>Overall accuracy:</strong> the percentage of correctly classified samples. Above 85% is generally a good result for land cover maps.</li>
<li><strong>Kappa index:</strong> measures agreement after removing what would be expected by chance. Above 0.8 is considered strong agreement.</li>
<li><strong>Producer’s accuracy:</strong> of all the forest that exists, how much the map captured (omission errors).</li>
<li><strong>User’s accuracy:</strong> of what the map calls forest, how much really is forest (commission errors). This is what matters to the map user. The console shows it as “List (1 element)”: expand the arrow to see the six values.</li>
</ul>
{$fig('en/fig4-precisao-importancia.webp', 1600, 693, 'Random Forest confusion matrix for Chitwan and variable importance chart', '<strong>Figure 4.</strong> Left, the confusion matrix of the 450 validation samples: overall accuracy of 84.9% and Kappa of 0.82. Right, the relative importance of each variable in the model.')}
<p>In our test, <strong>forest</strong> was the most reliable class (99% user’s accuracy). The main confusions were between <strong>sand and water</strong>, in riverbeds that shift from year to year, and between <strong>cropland and grassland</strong>, which can look very similar at the end of the rainy season. That is where it pays to add samples or variables, such as imagery from another season.</p>
<p>The figures were produced by GEOFLORESTA with the same Sentinel-2 data, the same variables, the option B samples and a 100-tree Random Forest. Running the script in Earth Engine gave the same 84.9% and a Kappa of 0.82. If you change the samples, the area or the <code>seed</code>, the numbers will shift slightly.</p>
<h2>6. Variable importance and area of each class</h2>
{$en['results']}
<p>The chart shows how much each variable contributed to separating the classes. In Chitwan, <strong>NDVI</strong> and <strong>NDWI</strong> were the most useful variables, followed by the blue band (B2) and <strong>elevation</strong>, which helps separate hillside forest from valley fields. If a variable has very low importance, you can try removing it.</p>
<p>The areas appear in the console as a list of classes (0 to 5) with the sum in hectares. In our result:</p>
<table>
<thead><tr><th>Class</th><th>Area (ha)</th><th>% of area</th></tr></thead>
<tbody>
<tr><td>Forest</td><td>77,052</td><td>56.2</td></tr>
<tr><td>Cropland</td><td>37,916</td><td>27.7</td></tr>
<tr><td>Grassland and shrubland</td><td>10,492</td><td>7.7</td></tr>
<tr><td>Built-up</td><td>6,496</td><td>4.7</td></tr>
<tr><td>Sand and bare soil</td><td>3,003</td><td>2.2</td></tr>
<tr><td>Water</td><td>2,041</td><td>1.5</td></tr>
</tbody>
</table>
<p>Note: area counted pixel by pixel carries the classification errors. In reports, it is better to <strong>adjust the areas with the confusion matrix</strong> (the method of Olofsson et al., 2014), which also gives a confidence interval for each area.</p>
<h2>7. Export the map and finish in QGIS</h2>
{$en['export']}
<p>After running the script, open the <em>Tasks</em> tab (on the right) and click <em>Run</em> on the <code>random_forest_chitwan_2025</code> task. The GeoTIFF lands in the GEOFLORESTA folder of your Google Drive, in UTM 45N (EPSG:32645). For another area, replace the EPSG with the matching UTM zone.</p>
<ol>
<li>Drag the file into QGIS.</li>
<li>In <em>Properties → Symbology</em>, choose <em>Paletted/Unique values</em>, click <em>Classify</em> and give each value (0 to 5) its class name and colour.</li>
<li>Add the legend, scale bar, north arrow and source (“Sentinel-2, ESA/Copernicus; Random Forest classification in Google Earth Engine”) in the <em>Print Layout</em>.</li>
<li>To vectorise the result, use <em>Raster → Conversion → Polygonize</em>.</li>
</ol>
<p>Prefer the full script in a single file? Download it, paste it into the Code Editor and click <em>Run</em>. Option B is active and option A is commented out.</p>
{$dl('en', 'Download the full script (.js)')}
<h2>8. Limitations and good practice</h2>
<ul>
<li><strong>Neighbouring samples inflate accuracy.</strong> Training and validation points that are very close together are nearly identical, so accuracy looks better than it is. In real work, validate with independent samples, ideally collected in the field or split by polygon rather than by pixel.</li>
<li><strong>Rare classes get overestimated</strong> when every class has the same number of samples. In Chitwan, grassland covers 7.7% of the map, more than in WorldCover. Check against high-resolution imagery and adjust the samples.</li>
<li><strong>One season is not always enough.</strong> Crops, grasslands and bare soil change through the year. Combining medians from two seasons (dry and wet) often improves separation.</li>
<li><strong>Clouds and shadows:</strong> in very cloudy regions, widen the date range or add Sentinel-1 radar imagery, which sees through clouds.</li>
<li><strong>Commercial use:</strong> Earth Engine is free for research, education and non-profit organisations; commercial use requires a licence.</li>
</ul>
<h2>9. Frequently asked questions</h2>
<p><strong>How many trees should I use?</strong> Between 100 and 500. Beyond that, accuracy barely changes and computation gets slower.</p>
<p><strong>Can I use Landsat instead of Sentinel-2?</strong> Yes. Replace the collection with <code>LANDSAT/LC09/C02/T1_L2</code> (or Landsat 8’s), adapt the band names and scaling, and use 30 m. This is useful for maps from before 2017.</p>
<p><strong>Does it work in another country?</strong> Yes, anywhere in the world. Change the area, the dates (choose your country’s dry season) and the export EPSG, and collect samples from your region.</p>
<p><strong>Random Forest or deep learning?</strong> Deep neural networks can be more accurate on very high-resolution imagery, but they need thousands of samples and powerful computers. For Sentinel-2 and Landsat, Random Forest remains one of the best choices.</p>
<p>Need a field-validated land cover map, a multi-year change analysis or training for your team? <a href="/en/contact?assunto=proposal">Request a proposal from GEOFLORESTA</a>.</p>
HTML,
    ],
    'exercise' => [
        'pt' => "1. Corra o script completo com a opção B e anote a precisão global e o Kappa que obteve.\n2. Mude a área para o seu distrito (troque o rectângulo e as datas para a estação seca do seu país) e volte a correr.\n3. Desenhe as suas próprias amostras (opção A), com pelo menos 30 polígonos por classe, e compare a precisão com a da opção B.\n4. Retire a altitude e o declive da imagem e veja como mudam a precisão e a importância das variáveis.\n5. Exporte o mapa, abra-o no QGIS ao lado do ESA WorldCover e descreva as três maiores diferenças.",
        'fr' => "1. Lancez le script complet avec l’option B et notez la précision globale et le Kappa obtenus.\n2. Remplacez la zone par votre district (changez le rectangle et les dates pour la saison sèche de votre pays) et relancez.\n3. Dessinez vos propres échantillons (option A), avec au moins 30 polygones par classe, et comparez la précision avec celle de l’option B.\n4. Retirez l’altitude et la pente de l’image et observez comment changent la précision et l’importance des variables.\n5. Exportez la carte, ouvrez-la dans QGIS à côté d’ESA WorldCover et décrivez les trois plus grandes différences.",
        'en' => "1. Run the full script with option B and note the overall accuracy and Kappa you get.\n2. Change the area to your district (replace the rectangle and set the dates to your country’s dry season) and run it again.\n3. Draw your own samples (option A), with at least 30 polygons per class, and compare the accuracy with option B.\n4. Remove elevation and slope from the image and see how accuracy and variable importance change.\n5. Export the map, open it in QGIS next to ESA WorldCover and describe the three largest differences.",
    ],
    'takeaways' => [
        'pt' => "O Random Forest junta muitas árvores de decisão que votam; é robusto, rápido e quase não precisa de afinação\nA qualidade do mapa depende sobretudo das amostras de treino: quantidade, distribuição, pureza e variedade\nÍndices (NDVI, NDWI) e relevo ajudam muito a separar classes parecidas\nValide sempre com amostras que o modelo não viu e apresente a matriz de confusão, a precisão global e o Kappa\nPara relatórios, ajuste as áreas com a matriz de confusão em vez de contar só os píxeis",
        'fr' => "Le Random Forest réunit de nombreux arbres de décision qui votent ; il est robuste, rapide et demande peu de réglages\nLa qualité de la carte dépend surtout des échantillons d’apprentissage : quantité, répartition, pureté et variété\nLes indices (NDVI, NDWI) et le relief aident beaucoup à séparer des classes proches\nValidez toujours avec des échantillons que le modèle n’a pas vus et présentez la matrice de confusion, la précision globale et le Kappa\nDans un rapport, ajustez les surfaces avec la matrice de confusion au lieu de compter seulement les pixels",
        'en' => "Random Forest combines many voting decision trees; it is robust, fast and needs little tuning\nMap quality depends mostly on the training samples: quantity, distribution, purity and variety\nIndices (NDVI, NDWI) and terrain help a lot to separate similar classes\nAlways validate with samples the model has not seen and report the confusion matrix, overall accuracy and Kappa\nFor reports, adjust areas with the confusion matrix rather than just counting pixels",
    ],
    'sources' => "Breiman, L. (2001). Random Forests. Machine Learning 45, 5–32 | https://doi.org/10.1023/A:1010933404324\n"
        ."Belgiu, M. & Drăguţ, L. (2016). Random forest in remote sensing: a review of applications and future directions | https://doi.org/10.1016/j.isprsjprs.2016.01.011\n"
        ."Olofsson, P. et al. (2014). Good practices for estimating area and assessing accuracy of land change | https://doi.org/10.1016/j.rse.2014.02.015\n"
        ."Google Earth Engine: Supervised Classification | https://developers.google.com/earth-engine/guides/classification\n"
        ."Copernicus Sentinel-2 Surface Reflectance (Harmonized) | https://developers.google.com/earth-engine/datasets/catalog/COPERNICUS_S2_SR_HARMONIZED\n"
        ."Cloud Score+ S2_HARMONIZED | https://developers.google.com/earth-engine/datasets/catalog/GOOGLE_CLOUD_SCORE_PLUS_V1_S2_HARMONIZED\n"
        ."ESA WorldCover 10 m 2021 v200 | https://esa-worldcover.org\n"
        ."NASADEM | https://developers.google.com/earth-engine/datasets/catalog/NASA_NASADEM_HGT_001",
    'cover_alt' => 'Imagem de satélite de Chitwan, no Nepal, que se transforma num mapa de uso do solo classificado com Random Forest',
    'cover_credit' => 'Contém dados Copernicus Sentinel-2 modificados (2025). Chitwan, Nepal. Classificação Random Forest: GEOFLORESTA',
];
