<?php

// Texto de apoio das páginas de cada conjunto de dados (usa os campos da ficha).
return [
    'uses_title' => 'Para que servem estes dados',
    'uses' => [
        'land-cover' => 'Os mapas de uso e cobertura do solo mostram onde há floresta, matagal, pastagem, agricultura, áreas urbanas, água e solo nu. São a base para planos de ordenamento do território, estudos de impacto ambiental, inventários florestais, cálculo de áreas por classe e para comparar a paisagem entre anos. Também servem para escolher áreas de amostragem no terreno e para validar classificações feitas com imagens Sentinel-2 ou Landsat.',
        'forest-loss' => 'Os dados de perda de cobertura arbórea indicam onde e em que ano as árvores desapareceram. Servem para medir a desflorestação por distrito ou área protegida, identificar pontos críticos, acompanhar compromissos de desflorestação zero em cadeias de abastecimento, apoiar projectos REDD+ e relatórios de sustentabilidade, e para orientar a fiscalização no terreno.',
        'fire' => 'Os focos de calor detectados por satélite mostram onde houve queimadas e incêndios. Servem para acompanhar a época de queimadas, avisar equipas no terreno, estimar a pressão do fogo sobre florestas e áreas de conservação, relacionar incêndios com agricultura e caça, e para planear aceiros e acções de sensibilização nas comunidades.',
        'hydrology' => 'As redes de rios e as bacias hidrográficas servem para delimitar a área que drena para um ponto, estudar cheias e secas, planear sistemas de abastecimento de água e de rega, localizar pontes e estradas, e definir zonas de protecção de nascentes e margens. Os níveis de bacia permitem trabalhar desde grandes bacias regionais até pequenas sub-bacias.',
        'biomass' => 'Os mapas de biomassa aérea e de carbono estimam quanta madeira e carbono existem em cada pixel. Servem para calcular stocks de carbono em florestas, projectos REDD+ e mercados voluntários de carbono, inventários de gases com efeito de estufa, e para comparar a biomassa de áreas protegidas com a das áreas exploradas.',
        'boundaries' => 'Os limites administrativos (país, províncias, distritos) são a camada de referência de quase todos os mapas. Servem para recortar outros dados para a sua área, somar estatísticas por unidade administrativa (área de floresta, focos de incêndio, população), preparar mapas de enquadramento e ligar tabelas de dados oficiais ao mapa.',
        'climate' => 'Os dados climáticos (temperatura, precipitação e outras variáveis) servem para descrever o clima de uma área de estudo, analisar tendências e anomalias, relacionar o clima com incêndios, produção agrícola ou crescimento florestal, e para preparar cenários de adaptação às alterações climáticas.',
    ],
    'howto_title' => 'Como usar no QGIS',
    'howto' => [
        'download' => 'Descarregue o ficheiro no formato que preferir. O GeoPackage é o mais prático no QGIS; o Shapefile serve para programas mais antigos; o CSV abre em qualquer folha de cálculo.',
        'source' => 'Abra a fonte oficial (:source), escolha a área ou o mosaico que cobre a sua zona e descarregue o formato disponível.',
        'open' => 'No QGIS, arraste o ficheiro para a janela do mapa ou use Camada → Adicionar camada.',
        'clip' => 'Recorte para a sua área de estudo: Vector → Geoprocessamento → Recortar, ou Raster → Extracção → Recortar raster por camada de máscara. Os limites de cada país estão em Dados por país.',
        'project' => 'Antes de medir áreas ou distâncias, reprojecte para um sistema métrico, por exemplo a zona UTM da sua área.',
        'cite' => 'Ao publicar mapas ou relatórios, cite a fonte como indicado em «Atribuição».',
    ],
    'faq_title' => 'Perguntas frequentes',
    'faq' => [
        'free_q' => 'Os dados «:title» são gratuitos?',
        'free_a' => 'Sim. Os dados são produzidos por :source e distribuídos sob a licença :licence. Pode descarregá-los sem registo e sem pagar.',
        'commercial_q' => 'Posso usar estes dados num trabalho comercial?',
        'commercial_a' => 'É a licença (:licence) que define o que é permitido. As licenças abertas mais comuns neste catálogo (CC BY 4.0, ODbL e domínio público) permitem uso comercial com atribuição, mas confirme sempre os termos na ligação da licença, na ficha desta página.',
        'crs_q' => 'Em que sistema de coordenadas estão os ficheiros?',
        'crs_a' => 'Em :crs. Abrem directamente no QGIS, no ArcGIS e no Google Earth Engine. Para calcular áreas, reprojecte para um sistema métrico.',
        'resolution_a' => 'A resolução espacial é :resolution.',
        'custom_q' => 'Precisa destes dados só para a sua área, ou de um mapa pronto?',
        'custom_a' => 'A GEOFLORESTA prepara recortes por país, província, distrito ou polígono próprio, com mapa e relatório, para clientes de qualquer país, presencialmente ou à distância.',
    ],
    'more_title' => 'Ver também',
    'more_countries' => 'Dados abertos por país',
    'more_learn' => 'Tutoriais gratuitos de SIG e sensoriamento remoto',
];
