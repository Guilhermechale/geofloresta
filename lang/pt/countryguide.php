<?php

// Resumo próprio de cada página de país (calculado a partir dos dados do país).
return [
    'title' => 'Resumo',
    'in_continent' => [
        'Africa' => 'em África', 'Asia' => 'na Ásia', 'Europe' => 'na Europa', 'North America' => 'na América do Norte e Central',
        'South America' => 'na América do Sul', 'Oceania' => 'na Oceânia', 'Antarctica' => 'na Antárctida', 'Seven seas (open ocean)' => 'em pleno oceano',
    ],
    'location' => 'Este país fica :in_continent e estende-se aproximadamente entre as latitudes :lat0 e :lat1 e as longitudes :lon0 e :lon1.',
    'fires_some' => 'Nos últimos 7 dias, os satélites VIIRS detectaram :n7 focos de calor activos neste país (:n24 nas últimas 24 horas): é o :rank.º valor mais alto entre os :total países com focos nesse período.',
    'fires_none' => 'Nos últimos 7 dias, os satélites VIIRS não detectaram focos de calor activos neste país.',
    'units_both' => 'Os limites administrativos do geoBoundaries dividem o país em :adm1 unidades de primeiro nível e :adm2 de segundo nível, prontas para somar estatísticas por região.',
    'units_one' => 'Os limites administrativos do geoBoundaries dividem o país em :adm1 unidades de primeiro nível.',
    'water' => 'Para rios, lagos e bacias hidrográficas, use os ficheiros HydroSHEDS da região :regions e recorte-os com o contorno do país.',
    'service' => 'Precisa de um mapa, de uma análise de desflorestação ou de incêndios, ou de um recorte de dados para :country? A GEOFLORESTA trabalha à distância para clientes em qualquer país.',
    'service_link' => 'Pedir uma proposta',
    'and' => 'e',
    'dir' => ['N' => 'N', 'S' => 'S', 'E' => 'E', 'W' => 'O'],
];
