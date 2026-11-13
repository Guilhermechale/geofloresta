<?php

/*
 * Modelo único de pesquisa (artigo científico). O resumo, as palavras-chave e as referências são
 * extraídos do texto original; o texto científico dos autores não é alterado (só a caixa dos títulos).
 */
return [
    1 => [
        'title' => 'O perfil dos vetores de desflorestamento em Moçambique: quem lidera a destruição?',
        'authors' => 'GEOFLORESTA', 'article_type' => 'original', 'venue' => 'GEOFLORESTA, Beira',
    ],
    2 => [
        'title' => 'Radiografia de 25 anos de perda de cobertura arbórea em Moçambique (2001–2025): uma análise por limiares de densidade de copa',
        'authors' => 'GEOFLORESTA', 'article_type' => 'original', 'venue' => 'GEOFLORESTA, Beira',
    ],
    3 => [
        'title' => 'Análise espaço-temporal da extensão das inundações durante as estações chuvosas de diferentes anos na cidade da Beira, Moçambique',
        'authors' => 'GEOFLORESTA', 'article_type' => 'original', 'venue' => 'GEOFLORESTA, Beira',
    ],
    4 => [
        'title' => 'Estimativa de biomassa florestal na Reserva Especial do Niassa: uma revisão sobre a integração de Sentinel-2, GEDI LiDAR e Random Forest',
        'authors' => 'Guilherme Francisco Fernando Chale', 'article_type' => 'review', 'venue' => 'GEOFLORESTA, Beira',
        'remove_sections' => ['Apresentação da Pesquisa'],
    ],
];
