<?php

// Tutorial: curvas de nível da sua área em 2 minutos (PT, FR, EN). Lido por create.php no servidor.
$img = '/storage/tutoriais/curvas-de-nivel';
$fig = fn (string $file, int $w, int $h, string $alt, string $cap) => "<figure><img src=\"{$img}/{$file}\" alt=\"".htmlspecialchars($alt, ENT_QUOTES)."\" width=\"{$w}\" height=\"{$h}\" loading=\"lazy\" decoding=\"async\"><figcaption>{$cap}</figcaption></figure>";

return [
    'slug' => [
        'pt' => 'curvas-de-nivel-da-sua-area-em-2-minutos-gratis',
        'fr' => 'courbes-de-niveau-de-votre-zone-en-2-minutes-gratuitement',
        'en' => 'contour-lines-for-your-area-in-2-minutes-free',
    ],
    'title' => [
        'pt' => 'Curvas de nível da sua área em 2 minutos, grátis e sem instalar nada',
        'fr' => 'Courbes de niveau de votre zone en 2 minutes, gratuitement et sans rien installer',
        'en' => 'Contour lines for your area in 2 minutes, free and with nothing to install',
    ],
    'summary' => [
        'pt' => 'Desenhe a sua área no mapa da GEOFLORESTA e descarregue o modelo de elevação (GeoTIFF) e as curvas de nível (GeoJSON) de qualquer lugar do mundo, prontos a abrir no QGIS.',
        'fr' => 'Dessinez votre zone sur la carte de GEOFLORESTA et téléchargez le modèle d’élévation (GeoTIFF) et les courbes de niveau (GeoJSON) de n’importe quel lieu du monde, prêts à ouvrir dans QGIS.',
        'en' => 'Draw your area on the GEOFLORESTA map and download the elevation model (GeoTIFF) and contour lines (GeoJSON) for anywhere in the world, ready to open in QGIS.',
    ],
    'seo_title' => [
        'pt' => 'Curvas de nível grátis da sua área em 2 minutos (GeoTIFF e GeoJSON)',
        'fr' => 'Courbes de niveau gratuites de votre zone en 2 minutes (GeoTIFF et GeoJSON)',
        'en' => 'Free contour lines for your area in 2 minutes (GeoTIFF and GeoJSON)',
    ],
    'seo_description' => [
        'pt' => 'Descarregue grátis as curvas de nível e o modelo de elevação de qualquer área do mundo, sem instalar nada. Passo a passo, abrir no QGIS e que intervalo escolher.',
        'fr' => 'Téléchargez gratuitement les courbes de niveau et le modèle d’élévation de n’importe quelle zone du monde, sans rien installer. Pas à pas, QGIS et choix de l’équidistance.',
        'en' => 'Download free contour lines and an elevation model for any area in the world, with nothing to install. Step by step, opening in QGIS and choosing the interval.',
    ],
    'objectives' => [
        'pt' => "Perceber o que são curvas de nível e um modelo digital de elevação\nGerar as curvas de nível de qualquer área no mapa da GEOFLORESTA\nEscolher o intervalo certo para a escala do seu trabalho\nAbrir, rotular e reprojectar os ficheiros no QGIS\nConhecer os limites destes dados antes de os usar num projecto",
        'fr' => "Comprendre ce que sont les courbes de niveau et un modèle numérique d’élévation\nGénérer les courbes de niveau de n’importe quelle zone sur la carte de GEOFLORESTA\nChoisir l’équidistance adaptée à l’échelle de votre travail\nOuvrir, étiqueter et reprojeter les fichiers dans QGIS\nConnaître les limites de ces données avant de les utiliser dans un projet",
        'en' => "Understand what contour lines and a digital elevation model are\nGenerate contour lines for any area on the GEOFLORESTA map\nChoose the right interval for the scale of your work\nOpen, label and reproject the files in QGIS\nKnow the limits of these data before using them in a project",
    ],
    'prerequisites' => [
        'pt' => "Um navegador actualizado (Chrome, Edge, Firefox ou Safari)\nPara o passo 5: QGIS instalado (gratuito, em qgis.org)\nNão precisa de conta nem de registo",
        'fr' => "Un navigateur à jour (Chrome, Edge, Firefox ou Safari)\nPour l’étape 5 : QGIS installé (gratuit, sur qgis.org)\nAucun compte ni inscription nécessaires",
        'en' => "An up-to-date browser (Chrome, Edge, Firefox or Safari)\nFor step 5: QGIS installed (free, at qgis.org)\nNo account or registration needed",
    ],
    'body' => [
        'pt' => <<<HTML
<p>Curvas de nível estão em quase todos os trabalhos de campo: monografias, planos de maneio florestal, estudos de bacias hidrográficas, projectos de estradas e de regadio. Até agora, obtê-las exigia descarregar mosaicos de satélite, juntá-los, recortá-los e processá-los num SIG. No mapa da GEOFLORESTA isso faz-se em cerca de dois minutos, para qualquer lugar do mundo, sem instalar nada e sem criar conta.</p>
<h2>1. O que são curvas de nível</h2>
<p>Uma <strong>curva de nível</strong> é uma linha que une pontos com a mesma altitude. Num mapa topográfico, as curvas mostram a forma do terreno: quando estão <strong>juntas</strong>, a encosta é íngreme; quando estão <strong>afastadas</strong>, o terreno é plano. Curvas fechadas e concêntricas indicam um morro (ou uma depressão), e curvas em forma de «V» apontam para os vales e linhas de água.</p>
<p>As curvas são calculadas a partir de um <strong>modelo digital de elevação</strong> (MDE ou DEM, em inglês): uma imagem em que cada píxel guarda a altitude do terreno, em metros. A ferramenta da GEOFLORESTA entrega-lhe os dois ficheiros: o modelo de elevação e as curvas já calculadas.</p>
<h2>2. De onde vêm os dados</h2>
<p>A altitude vem dos <strong>Terrain Tiles</strong>, uma colecção aberta e mundial publicada no programa AWS Open Data. Junta os melhores modelos de elevação abertos de cada região: o <strong>SRTM</strong> (cerca de 30 m na maior parte do mundo, incluindo toda a África), o GMTED2010, o ETOPO1 (para os oceanos) e modelos nacionais mais detalhados em alguns países. A atribuição completa vai dentro dos ficheiros que descarrega e está na página <a href="/dados-e-licencas">Dados e Licenças</a>.</p>
<p>O cálculo é feito <strong>no seu navegador</strong>: o mapa descarrega os mosaicos de elevação da área, monta uma grelha em coordenadas WGS 84, recorta-a pela sua área e calcula as curvas. Nada é enviado para os nossos servidores.</p>
<h2>3. Passo a passo: as curvas da sua área em 2 minutos</h2>
<ol>
<li><strong>Abra o mapa.</strong> Vá a <a href="/mapas">Mapas</a> e, se a sua área não for em Moçambique, escolha o país no topo do painel.</li>
<li><strong>Veja o relevo (opcional).</strong> Em «Camadas», ligue <em>Relevo (sombreamento)</em> e <em>Altitude (cores)</em>. Ajuda a perceber o terreno antes de escolher a área.</li>
<li><strong>Escolha a área de interesse.</strong> Em Moçambique pode escolher uma província ou um distrito. Em qualquer país pode usar o país inteiro ou carregar em <em>Desenhar polígono</em>, clicar nos vértices no mapa e terminar com <em>Concluir polígono</em> (ou com um duplo clique).</li>
<li><strong>Prepare o relevo.</strong> No bloco «Relevo (modelo de elevação)», carregue em <em>Preparar o relevo desta área</em>. Em poucos segundos aparece a resolução e as altitudes mínima, máxima e média.</li>
<li><strong>Escolha o intervalo das curvas.</strong> O site sugere um intervalo de acordo com o desnível da área; pode mudá-lo (veja a secção 4).</li>
<li><strong>Descarregue.</strong> <em>Altitude (GeoTIFF)</em> dá-lhe o modelo de elevação; <em>Curvas de nível (GeoJSON)</em> dá-lhe as linhas, cada uma com o campo <code>altitude_m</code>.</li>
</ol>
{$fig('fig1-mapa-relevo.webp', 1600, 1009, 'Mapa da GEOFLORESTA com as camadas de relevo ligadas e um polígono desenhado sobre a serra de Chimanimani', '<strong>Figura 1.</strong> Camadas «Relevo (sombreamento)» e «Altitude (cores)» ligadas e uma área desenhada sobre a serra de Chimanimani, na fronteira entre Moçambique e o Zimbabué.')}
{$fig('fig2-painel-relevo.webp', 574, 1212, 'Painel do relevo com a resolução, as altitudes, o intervalo das curvas e os botões de download', '<strong>Figura 2.</strong> Depois de «Preparar o relevo desta área»: resolução de cerca de 36 m, altitudes de 131 a 2 426 m, intervalo de 20 m e os dois botões de download.')}
<p>A resolução depende do tamanho da área: até alguns milhares de km² (um distrito, uma bacia, uma área de concessão) obtém cerca de 30–40 m; áreas maiores, como uma província inteira ou um país, saem com menos detalhe, e o site mostra sempre a resolução real. Se precisar de uma área grande com o máximo detalhe, divida-a em várias áreas mais pequenas.</p>
<h2>4. Que intervalo escolher</h2>
<p>O intervalo (equidistância) é a diferença de altitude entre duas curvas seguidas. Um intervalo pequeno mostra mais detalhe, mas num terreno montanhoso as linhas ficam tão juntas que o mapa deixa de se ler.</p>
<table>
<thead><tr><th>Intervalo</th><th>Quando usar</th></tr></thead>
<tbody>
<tr><td>10 m</td><td>Áreas pequenas e pouco acidentadas: planícies, zonas costeiras, uma machamba ou um bairro.</td></tr>
<tr><td>20 m</td><td>Distritos, bacias pequenas e terrenos ondulados. É um bom ponto de partida.</td></tr>
<tr><td>50 m</td><td>Serras e terrenos montanhosos, ou áreas de algumas centenas de km².</td></tr>
<tr><td>100–200 m</td><td>Mapas de enquadramento de províncias e regiões montanhosas extensas.</td></tr>
<tr><td>500 m</td><td>Países inteiros e mapas de pequena escala.</td></tr>
</tbody>
</table>
<p>Lembre-se de que um intervalo não pode ser mais fino do que a precisão dos dados: com um modelo de cerca de 30 m, curvas de 1 ou 2 m não teriam significado.</p>
<h2>5. Abrir e preparar no QGIS</h2>
<ol>
<li><strong>Abra os ficheiros:</strong> arraste o GeoTIFF e o GeoJSON para a janela do QGIS. Os dois estão em WGS 84 (EPSG:4326) e ficam sobrepostos.</li>
<li><strong>Destaque as curvas mestras:</strong> nas propriedades da camada de curvas, em <em>Simbologia</em>, escolha <em>Baseado em regras</em> e crie uma regra com a expressão <code>"altitude_m" % 100 = 0</code>, com uma linha mais grossa. As restantes curvas ficam com uma linha fina.</li>
<li><strong>Rotule as curvas:</strong> em <em>Etiquetas</em>, escolha <em>Etiquetas simples</em>, valor <code>altitude_m</code> e posicionamento curvo sobre a linha. Para não sobrecarregar o mapa, mostre só as curvas mestras (use a mesma expressão como filtro).</li>
<li><strong>Faça o sombreamento:</strong> na <em>Caixa de ferramentas de processamento</em>, procure «hillshade» e aplique-o ao GeoTIFF. Ponha o sombreamento por baixo das curvas, com alguma transparência.</li>
<li><strong>Reprojecte antes de medir:</strong> para medir áreas, distâncias ou declives, guarde as camadas numa projecção métrica (por exemplo, a zona UTM da sua área) com <em>Exportar → Guardar elementos como…</em>. Nesse mesmo menu pode guardar as curvas em Shapefile ou GeoPackage.</li>
</ol>
{$fig('fig3-curvas-qgis.webp', 1505, 960, 'Curvas de nível de 20 m com as curvas de 100 m mais grossas e rotuladas, sobre o sombreamento do terreno à volta do Monte Binga', '<strong>Figura 3.</strong> Os dois ficheiros descarregados num SIG: curvas de 20 m (as de 100 m mais grossas, com a altitude) sobre o sombreamento calculado a partir do GeoTIFF. O triângulo marca o ponto mais alto da área, junto ao Monte Binga (2 436 m), o ponto mais alto de Moçambique.')}
<h2>6. Limitações a ter em conta</h2>
<ul>
<li><strong>Não substitui um levantamento topográfico.</strong> Com 30–40 m de resolução e alguns metros de erro vertical, estes dados servem para mapas, estudos, ensino e planeamento, mas não para projectos de engenharia, cálculos de volumes ou demarcação de terrenos.</li>
<li><strong>Floresta e edifícios:</strong> o SRTM mede a superfície que o radar «vê». Em florestas densas e zonas urbanas, a altitude pode incluir parte das copas ou dos edifícios.</li>
<li><strong>Valores negativos</strong> são profundidades no mar (batimetria do ETOPO1), e não erros.</li>
<li><strong>Resolução variável:</strong> áreas grandes saem com menos detalhe. Confira sempre a resolução indicada antes de descarregar.</li>
<li><strong>Cite a fonte</strong> nos seus mapas e relatórios, como indicado nos ficheiros e na página Dados e Licenças.</li>
</ul>
<h2>7. Perguntas frequentes</h2>
<p><strong>É mesmo grátis?</strong> Sim. Os dados são abertos e a ferramenta não pede conta nem pagamento.</p>
<p><strong>Funciona fora de Moçambique?</strong> Sim, em qualquer país do mundo: escolha o país no mapa e desenhe a sua área.</p>
<p><strong>Preciso de Shapefile.</strong> Abra o GeoJSON no QGIS e use <em>Exportar → Guardar elementos como…</em> com o formato «ESRI Shapefile».</p>
<p><strong>Porque é que a resolução saiu baixa?</strong> A área é grande. Desenhe uma área mais pequena ou divida a sua em várias partes.</p>
<p><strong>Posso usar os dados num trabalho comercial?</strong> As fontes usadas são abertas e pedem atribuição. Confirme os termos de cada fonte na página Dados e Licenças antes de um uso comercial.</p>
<p>Precisa de curvas de 1 m, de um levantamento com drone ou de um mapa topográfico pronto para o seu projecto? <a href="/contacto?assunto=data">Peça uma proposta à GEOFLORESTA</a>.</p>
HTML,
        'fr' => <<<HTML
<p>Les courbes de niveau sont présentes dans presque tous les travaux de terrain : mémoires, plans d’aménagement forestier, études de bassins versants, projets de routes et d’irrigation. Jusqu’ici, les obtenir demandait de télécharger des tuiles satellite, de les assembler, de les découper et de les traiter dans un SIG. Sur la carte de GEOFLORESTA, cela se fait en deux minutes environ, pour n’importe quel lieu du monde, sans rien installer et sans créer de compte.</p>
<h2>1. Que sont les courbes de niveau</h2>
<p>Une <strong>courbe de niveau</strong> est une ligne qui relie des points de même altitude. Sur une carte topographique, les courbes montrent la forme du terrain : <strong>rapprochées</strong>, la pente est forte ; <strong>espacées</strong>, le terrain est plat. Des courbes fermées et concentriques indiquent une colline (ou une dépression), et des courbes en « V » pointent vers les vallées et les cours d’eau.</p>
<p>Les courbes sont calculées à partir d’un <strong>modèle numérique d’élévation</strong> (MNE, ou DEM en anglais) : une image dont chaque pixel contient l’altitude du terrain, en mètres. L’outil de GEOFLORESTA vous fournit les deux fichiers : le modèle d’élévation et les courbes déjà calculées.</p>
<h2>2. D’où viennent les données</h2>
<p>L’altitude provient des <strong>Terrain Tiles</strong>, une collection ouverte et mondiale publiée dans le programme AWS Open Data. Elle réunit les meilleurs modèles d’élévation ouverts de chaque région : le <strong>SRTM</strong> (environ 30 m dans la plupart du monde, y compris toute l’Afrique), le GMTED2010, l’ETOPO1 (pour les océans) et des modèles nationaux plus détaillés dans certains pays. L’attribution complète figure dans les fichiers téléchargés et sur la page <a href="/fr/donnees-et-licences">Données et licences</a>.</p>
<p>Le calcul se fait <strong>dans votre navigateur</strong> : la carte télécharge les tuiles d’élévation de la zone, construit une grille en coordonnées WGS 84, la découpe selon votre zone et calcule les courbes. Rien n’est envoyé à nos serveurs.</p>
<h2>3. Pas à pas : les courbes de votre zone en 2 minutes</h2>
<ol>
<li><strong>Ouvrez la carte.</strong> Allez sur <a href="/fr/cartes">Cartes</a> et, si votre zone n’est pas au Mozambique, choisissez le pays en haut du panneau.</li>
<li><strong>Affichez le relief (facultatif).</strong> Dans « Couches », activez <em>Relief (ombrage)</em> et <em>Altitude (couleurs)</em>. Cela aide à comprendre le terrain avant de choisir la zone.</li>
<li><strong>Choisissez la zone d’intérêt.</strong> Au Mozambique, vous pouvez choisir une province ou un district. Dans tous les pays, vous pouvez utiliser le pays entier ou cliquer sur <em>Dessiner un polygone</em>, cliquer sur les sommets et terminer avec <em>Terminer le polygone</em> (ou un double-clic).</li>
<li><strong>Préparez le relief.</strong> Dans le bloc « Relief (modèle d’élévation) », cliquez sur <em>Préparer le relief de cette zone</em>. En quelques secondes s’affichent la résolution et les altitudes minimale, maximale et moyenne.</li>
<li><strong>Choisissez l’équidistance.</strong> Le site propose une valeur selon le dénivelé de la zone ; vous pouvez la modifier (voir la section 4).</li>
<li><strong>Téléchargez.</strong> <em>Altitude (GeoTIFF)</em> fournit le modèle d’élévation ; <em>Courbes de niveau (GeoJSON)</em> fournit les lignes, chacune avec le champ <code>altitude_m</code>.</li>
</ol>
{$fig('fig1-mapa-relevo.webp', 1600, 1009, 'Carte de GEOFLORESTA avec les couches de relief activées et un polygone dessiné sur les monts Chimanimani', '<strong>Figure 1.</strong> Couches de relief et d’altitude activées et une zone dessinée sur les monts Chimanimani, à la frontière entre le Mozambique et le Zimbabwe.')}
{$fig('fig2-painel-relevo.webp', 574, 1212, 'Panneau du relief avec la résolution, les altitudes, l’équidistance et les boutons de téléchargement', '<strong>Figure 2.</strong> Après « Préparer le relief de cette zone » : résolution d’environ 36 m, altitudes de 131 à 2 426 m, équidistance de 20 m et les deux boutons de téléchargement (capture de l’interface en portugais).')}
<p>La résolution dépend de la taille de la zone : jusqu’à quelques milliers de km² (un district, un bassin, une concession), vous obtenez environ 30–40 m ; les zones plus grandes, comme une province entière ou un pays, ont moins de détail, et le site affiche toujours la résolution réelle. Pour une grande zone au détail maximal, divisez-la en plusieurs zones plus petites.</p>
<h2>4. Quelle équidistance choisir</h2>
<p>L’équidistance est la différence d’altitude entre deux courbes successives. Une petite équidistance montre plus de détail, mais en montagne les lignes deviennent si serrées que la carte devient illisible.</p>
<table>
<thead><tr><th>Équidistance</th><th>Quand l’utiliser</th></tr></thead>
<tbody>
<tr><td>10 m</td><td>Petites zones peu accidentées : plaines, zones côtières, une parcelle ou un quartier.</td></tr>
<tr><td>20 m</td><td>Districts, petits bassins et terrains vallonnés. Un bon point de départ.</td></tr>
<tr><td>50 m</td><td>Massifs et terrains montagneux, ou zones de quelques centaines de km².</td></tr>
<tr><td>100–200 m</td><td>Cartes de situation de provinces et de grandes régions montagneuses.</td></tr>
<tr><td>500 m</td><td>Pays entiers et cartes à petite échelle.</td></tr>
</tbody>
</table>
<p>Une équidistance ne peut pas être plus fine que la précision des données : avec un modèle d’environ 30 m, des courbes de 1 ou 2 m n’auraient pas de sens.</p>
<h2>5. Ouvrir et préparer dans QGIS</h2>
<ol>
<li><strong>Ouvrez les fichiers :</strong> faites glisser le GeoTIFF et le GeoJSON dans la fenêtre de QGIS. Les deux sont en WGS 84 (EPSG:4326) et se superposent.</li>
<li><strong>Mettez en valeur les courbes maîtresses :</strong> dans les propriétés de la couche de courbes, onglet <em>Symbologie</em>, choisissez <em>Ensemble de règles</em> et créez une règle avec l’expression <code>"altitude_m" % 100 = 0</code> et une ligne plus épaisse.</li>
<li><strong>Étiquetez les courbes :</strong> dans <em>Étiquettes</em>, choisissez <em>Étiquettes simples</em>, valeur <code>altitude_m</code> et placement courbé sur la ligne. Pour ne pas surcharger la carte, n’étiquetez que les courbes maîtresses.</li>
<li><strong>Créez l’ombrage :</strong> dans la <em>Boîte à outils de traitements</em>, cherchez « hillshade » et appliquez-le au GeoTIFF. Placez l’ombrage sous les courbes, avec un peu de transparence.</li>
<li><strong>Reprojetez avant de mesurer :</strong> pour mesurer des surfaces, des distances ou des pentes, enregistrez les couches dans une projection métrique (par exemple la zone UTM de votre territoire) avec <em>Exporter → Sauvegarder les entités sous…</em>. Ce même menu permet d’enregistrer les courbes en Shapefile ou en GeoPackage.</li>
</ol>
{$fig('fig3-curvas-qgis.webp', 1505, 960, 'Courbes de niveau de 20 m avec les courbes de 100 m plus épaisses et étiquetées, sur l’ombrage du terrain autour du mont Binga', '<strong>Figure 3.</strong> Les deux fichiers téléchargés dans un SIG : courbes de 20 m (celles de 100 m plus épaisses, avec l’altitude) sur l’ombrage calculé à partir du GeoTIFF. Le triangle marque le point le plus haut de la zone, près du mont Binga (2 436 m), point culminant du Mozambique.')}
<h2>6. Limites à connaître</h2>
<ul>
<li><strong>Ne remplace pas un levé topographique.</strong> Avec 30–40 m de résolution et quelques mètres d’erreur verticale, ces données conviennent aux cartes, aux études, à l’enseignement et à la planification, mais pas aux projets d’ingénierie, aux calculs de volumes ni au bornage.</li>
<li><strong>Forêts et bâtiments :</strong> le SRTM mesure la surface « vue » par le radar. En forêt dense et en ville, l’altitude peut inclure une partie des cimes ou des bâtiments.</li>
<li><strong>Les valeurs négatives</strong> sont des profondeurs en mer (bathymétrie ETOPO1), pas des erreurs.</li>
<li><strong>Résolution variable :</strong> les grandes zones ont moins de détail. Vérifiez toujours la résolution affichée avant de télécharger.</li>
<li><strong>Citez la source</strong> dans vos cartes et rapports, comme indiqué dans les fichiers et sur la page Données et licences.</li>
</ul>
<h2>7. Questions fréquentes</h2>
<p><strong>Est-ce vraiment gratuit ?</strong> Oui. Les données sont ouvertes et l’outil ne demande ni compte ni paiement.</p>
<p><strong>Cela fonctionne-t-il hors du Mozambique ?</strong> Oui, dans tous les pays : choisissez le pays sur la carte et dessinez votre zone.</p>
<p><strong>J’ai besoin d’un Shapefile.</strong> Ouvrez le GeoJSON dans QGIS et utilisez <em>Exporter → Sauvegarder les entités sous…</em> avec le format « ESRI Shapefile ».</p>
<p><strong>Pourquoi la résolution est-elle faible ?</strong> La zone est grande. Dessinez une zone plus petite ou divisez la vôtre en plusieurs parties.</p>
<p><strong>Puis-je utiliser les données dans un travail commercial ?</strong> Les sources utilisées sont ouvertes et demandent une attribution. Vérifiez les conditions de chaque source sur la page Données et licences avant un usage commercial.</p>
<p>Besoin de courbes de 1 m, d’un levé par drone ou d’une carte topographique prête pour votre projet ? <a href="/fr/contact?assunto=data">Demandez une proposition à GEOFLORESTA</a>.</p>
HTML,
        'en' => <<<HTML
<p>Contour lines appear in almost every piece of fieldwork: theses, forest management plans, river basin studies, road and irrigation projects. Until now, getting them meant downloading satellite tiles, merging them, clipping them and processing them in a GIS. On the GEOFLORESTA map it takes about two minutes, for anywhere in the world, with nothing to install and no account to create.</p>
<h2>1. What contour lines are</h2>
<p>A <strong>contour line</strong> joins points of equal elevation. On a topographic map, contours show the shape of the land: when they are <strong>close together</strong>, the slope is steep; when they are <strong>far apart</strong>, the ground is flat. Closed, concentric contours mark a hill (or a depression), and V-shaped contours point to valleys and streams.</p>
<p>Contours are calculated from a <strong>digital elevation model</strong> (DEM): an image in which each pixel holds the ground elevation in metres. The GEOFLORESTA tool gives you both files: the elevation model and the ready-made contours.</p>
<h2>2. Where the data come from</h2>
<p>Elevation comes from <strong>Terrain Tiles</strong>, an open global collection published through the AWS Open Data programme. It combines the best open elevation models for each region: <strong>SRTM</strong> (about 30 m in most of the world, including all of Africa), GMTED2010, ETOPO1 (for the oceans) and more detailed national models in some countries. The full attribution is included in the downloaded files and on the <a href="/en/data-and-licences">Data and Licences</a> page.</p>
<p>The calculation runs <strong>in your browser</strong>: the map downloads the elevation tiles for the area, builds a grid in WGS 84 coordinates, clips it to your area and calculates the contours. Nothing is sent to our servers.</p>
<h2>3. Step by step: your area’s contours in 2 minutes</h2>
<ol>
<li><strong>Open the map.</strong> Go to <a href="/en/maps">Maps</a> and, if your area is not in Mozambique, choose the country at the top of the panel.</li>
<li><strong>Show the relief (optional).</strong> Under “Layers”, turn on <em>Relief (hillshade)</em> and <em>Elevation (colours)</em>. It helps you read the terrain before choosing the area.</li>
<li><strong>Choose the area of interest.</strong> In Mozambique you can pick a province or district. In any country you can use the whole country or click <em>Draw polygon</em>, click the vertices on the map and finish with <em>Finish polygon</em> (or a double-click).</li>
<li><strong>Prepare the relief.</strong> In the “Relief (elevation model)” block, click <em>Prepare the relief of this area</em>. Within seconds you see the resolution and the minimum, maximum and mean elevation.</li>
<li><strong>Choose the contour interval.</strong> The site suggests one based on the area’s relief; you can change it (see section 4).</li>
<li><strong>Download.</strong> <em>Elevation (GeoTIFF)</em> gives you the elevation model; <em>Contour lines (GeoJSON)</em> gives you the lines, each with an <code>altitude_m</code> field.</li>
</ol>
{$fig('fig1-mapa-relevo.webp', 1600, 1009, 'GEOFLORESTA map with the relief layers on and a polygon drawn over the Chimanimani mountains', '<strong>Figure 1.</strong> Relief and elevation layers turned on and an area drawn over the Chimanimani mountains, on the border between Mozambique and Zimbabwe.')}
{$fig('fig2-painel-relevo.webp', 574, 1212, 'Relief panel with the resolution, elevations, contour interval and download buttons', '<strong>Figure 2.</strong> After “Prepare the relief of this area”: about 36 m resolution, elevations from 131 to 2,426 m, a 20 m interval and the two download buttons (screenshot of the Portuguese interface).')}
<p>Resolution depends on the size of the area: up to a few thousand km² (a district, a catchment, a concession) you get about 30–40 m; larger areas, such as a whole province or country, have less detail, and the site always shows the actual resolution. If you need a large area at full detail, split it into several smaller areas.</p>
<h2>4. Which interval to choose</h2>
<p>The contour interval is the elevation difference between two consecutive contours. A small interval shows more detail, but in mountains the lines get so close that the map becomes unreadable.</p>
<table>
<thead><tr><th>Interval</th><th>When to use it</th></tr></thead>
<tbody>
<tr><td>10 m</td><td>Small, gentle areas: plains, coastal zones, a farm or a neighbourhood.</td></tr>
<tr><td>20 m</td><td>Districts, small catchments and rolling terrain. A good starting point.</td></tr>
<tr><td>50 m</td><td>Hills and mountains, or areas of a few hundred km².</td></tr>
<tr><td>100–200 m</td><td>Overview maps of provinces and large mountain regions.</td></tr>
<tr><td>500 m</td><td>Whole countries and small-scale maps.</td></tr>
</tbody>
</table>
<p>An interval cannot be finer than the accuracy of the data: with a model of about 30 m, 1 or 2 m contours would be meaningless.</p>
<h2>5. Open and style them in QGIS</h2>
<ol>
<li><strong>Open the files:</strong> drag the GeoTIFF and the GeoJSON into the QGIS window. Both are in WGS 84 (EPSG:4326) and overlay each other.</li>
<li><strong>Highlight index contours:</strong> in the contour layer’s properties, under <em>Symbology</em>, choose <em>Rule-based</em> and add a rule with the expression <code>"altitude_m" % 100 = 0</code> and a thicker line.</li>
<li><strong>Label the contours:</strong> under <em>Labels</em>, choose <em>Single labels</em>, value <code>altitude_m</code> and curved placement on the line. To keep the map readable, label only the index contours.</li>
<li><strong>Add a hillshade:</strong> in the <em>Processing Toolbox</em>, search for “hillshade” and run it on the GeoTIFF. Place it under the contours with some transparency.</li>
<li><strong>Reproject before measuring:</strong> to measure areas, distances or slopes, save the layers in a metric projection (for example your area’s UTM zone) with <em>Export → Save Features As…</em>. The same menu saves the contours as Shapefile or GeoPackage.</li>
</ol>
{$fig('fig3-curvas-qgis.webp', 1505, 960, '20 m contour lines with thicker, labelled 100 m contours over the hillshade around Mount Binga', '<strong>Figure 3.</strong> The two downloaded files in a GIS: 20 m contours (100 m ones thicker and labelled) over a hillshade calculated from the GeoTIFF. The triangle marks the highest point of the area, near Mount Binga (2,436 m), the highest point in Mozambique.')}
<h2>6. Limitations to keep in mind</h2>
<ul>
<li><strong>Not a substitute for a topographic survey.</strong> With 30–40 m resolution and a few metres of vertical error, these data suit maps, studies, teaching and planning, but not engineering design, volume calculations or boundary surveys.</li>
<li><strong>Forests and buildings:</strong> SRTM measures the surface the radar “sees”. In dense forest and urban areas, elevation may include part of the canopy or buildings.</li>
<li><strong>Negative values</strong> are depths at sea (ETOPO1 bathymetry), not errors.</li>
<li><strong>Variable resolution:</strong> large areas have less detail. Always check the resolution shown before downloading.</li>
<li><strong>Cite the source</strong> in your maps and reports, as shown in the files and on the Data and Licences page.</li>
</ul>
<h2>7. Frequently asked questions</h2>
<p><strong>Is it really free?</strong> Yes. The data are open and the tool needs no account or payment.</p>
<p><strong>Does it work outside Mozambique?</strong> Yes, in any country: choose the country on the map and draw your area.</p>
<p><strong>I need a Shapefile.</strong> Open the GeoJSON in QGIS and use <em>Export → Save Features As…</em> with the “ESRI Shapefile” format.</p>
<p><strong>Why is the resolution low?</strong> The area is large. Draw a smaller area or split yours into several parts.</p>
<p><strong>Can I use the data in commercial work?</strong> The sources used are open and require attribution. Check each source’s terms on the Data and Licences page before commercial use.</p>
<p>Need 1 m contours, a drone survey or a ready-made topographic map for your project? <a href="/en/contact?assunto=data">Request a proposal from GEOFLORESTA</a>.</p>
HTML,
    ],
    'exercise' => [
        'pt' => '<p>Faça um mapa topográfico simples do seu distrito (ou da área onde trabalha).</p><ol><li>No mapa da GEOFLORESTA, escolha ou desenhe a sua área e descarregue o GeoTIFF e as curvas de 20 m.</li><li>No QGIS, crie o sombreamento a partir do GeoTIFF e ponha as curvas por cima, com as curvas mestras de 100 m mais grossas e rotuladas.</li><li>Acrescente o limite do distrito (em <a href="/dados/paises">Dados por país</a>) e, se quiser, rios do OpenStreetMap.</li><li>No compositor de impressão, junte título, escala, norte, legenda e a fonte dos dados, e exporte em PDF.</li><li>Identifique no mapa o ponto mais alto, a encosta mais íngreme e uma linha de água. Que intervalo tornou o mapa mais legível?</li></ol>',
        'fr' => '<p>Réalisez une carte topographique simple de votre district (ou de votre zone de travail).</p><ol><li>Sur la carte de GEOFLORESTA, choisissez ou dessinez votre zone et téléchargez le GeoTIFF et les courbes de 20 m.</li><li>Dans QGIS, créez l’ombrage à partir du GeoTIFF et placez les courbes au-dessus, avec les courbes maîtresses de 100 m plus épaisses et étiquetées.</li><li>Ajoutez la limite du district (dans <a href="/fr/donnees/pays">Données par pays</a>) et, si vous le souhaitez, les rivières d’OpenStreetMap.</li><li>Dans le composeur d’impression, ajoutez titre, échelle, nord, légende et source des données, puis exportez en PDF.</li><li>Repérez le point le plus haut, la pente la plus forte et un cours d’eau. Quelle équidistance rend la carte la plus lisible ?</li></ol>',
        'en' => '<p>Make a simple topographic map of your district (or the area you work in).</p><ol><li>On the GEOFLORESTA map, choose or draw your area and download the GeoTIFF and the 20 m contours.</li><li>In QGIS, create a hillshade from the GeoTIFF and place the contours on top, with the 100 m index contours thicker and labelled.</li><li>Add the district boundary (from <a href="/en/data/countries">Data by country</a>) and, if you like, OpenStreetMap rivers.</li><li>In the print layout, add a title, scale bar, north arrow, legend and data source, and export to PDF.</li><li>Find the highest point, the steepest slope and a stream on the map. Which interval made the map most readable?</li></ol>',
    ],
    'takeaways' => [
        'pt' => "Curvas juntas indicam encostas íngremes; curvas afastadas indicam terreno plano\nNo mapa da GEOFLORESTA obtém o modelo de elevação (GeoTIFF) e as curvas (GeoJSON) de qualquer área do mundo, grátis\nA resolução é de cerca de 30–40 m em áreas pequenas e diminui em áreas grandes\nEscolha o intervalo pela escala do trabalho: 10–20 m para áreas pequenas, 50–100 m para serras e províncias\nReprojecte para UTM antes de medir e cite sempre a fonte\nEstes dados servem para mapas e estudos, não para projectos de engenharia",
        'fr' => "Des courbes rapprochées indiquent une forte pente ; des courbes espacées, un terrain plat\nSur la carte de GEOFLORESTA, vous obtenez gratuitement le modèle d’élévation (GeoTIFF) et les courbes (GeoJSON) de n’importe quelle zone du monde\nLa résolution est d’environ 30–40 m pour les petites zones et diminue pour les grandes\nChoisissez l’équidistance selon l’échelle : 10–20 m pour les petites zones, 50–100 m pour les massifs et les provinces\nReprojetez en UTM avant de mesurer et citez toujours la source\nCes données conviennent aux cartes et aux études, pas aux projets d’ingénierie",
        'en' => "Close contours mean steep slopes; widely spaced contours mean flat ground\nThe GEOFLORESTA map gives you the elevation model (GeoTIFF) and contours (GeoJSON) for any area in the world, free\nResolution is about 30–40 m for small areas and coarser for large ones\nChoose the interval by the scale of your work: 10–20 m for small areas, 50–100 m for mountains and provinces\nReproject to UTM before measuring and always cite the source\nThese data suit maps and studies, not engineering design",
    ],
    'sources' => "Terrain Tiles — AWS Open Data Registry | https://registry.opendata.aws/terrain-tiles/\nTilezen Joerd — atribuição das fontes de elevação | https://github.com/tilezen/joerd/blob/master/docs/attribution.md\nUSGS — Shuttle Radar Topography Mission (SRTM) | https://www.usgs.gov/centers/eros/science/usgs-eros-archive-digital-elevation-shuttle-radar-topography-mission-srtm-1\nQGIS — documentação oficial | https://docs.qgis.org/\nMapa da GEOFLORESTA (ferramenta usada neste tutorial) | https://geofloresta.com/mapas",
    'cover_alt' => 'Relevo da serra de Chimanimani, na fronteira entre Moçambique e o Zimbabué, com cores por altitude, sombreamento e curvas de nível',
    'cover_credit' => 'Relevo: Terrain Tiles (Mapzen/Tilezen, AWS Open Data; SRTM e outras fontes). Serra de Chimanimani. Processamento: GEOFLORESTA',
];
