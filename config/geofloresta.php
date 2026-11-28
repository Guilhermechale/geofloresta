<?php

return [
    // Caminho do painel de administração (não óbvio; definido no .env)
    'admin_path' => env('ADMIN_PATH', 'gestao'),

    'locales' => ['pt', 'fr', 'en'],
    'default_locale' => 'pt',

    'contact_email' => env('CONTACT_EMAIL', 'guilhermechale@geofloresta.com'),
    // Google AdSense: identificador público do editor (aparece no ads.txt e na etiqueta de verificação).
    // Só verifica a propriedade do site; os anúncios ainda não são carregados.
    'adsense_client' => env('ADSENSE_CLIENT', 'ca-pub-7090076588420977'),

    // Chaves e identificadores opcionais (gratuitos). Vazios = funcionalidade desligada.
    'firms_map_key' => env('FIRMS_MAP_KEY'),
    'ga4_id' => env('GA4_ID'),

    // Redes sociais (rodapé, Contacto, tutoriais e dados estruturados para o Google). Vazio = não aparece.
    'social' => [
        'youtube' => env('SOCIAL_YOUTUBE', 'https://www.youtube.com/@geofloresta.oficial'),
        'linkedin' => env('SOCIAL_LINKEDIN', 'https://www.linkedin.com/company/geofloresta/'),
        'facebook' => env('SOCIAL_FACEBOOK', 'https://www.facebook.com/share/14ryi5F7Thc/'),
        'tiktok' => env('SOCIAL_TIKTOK', 'https://www.tiktok.com/@geofloresta.oficial'),
        'whatsapp' => env('SOCIAL_WHATSAPP', 'https://whatsapp.com/channel/0029VbDxkEgEQIakSWvSS31N'),
    ],

    // Vídeo de apresentação no YouTube (página inicial). Vazio = secção escondida.
    // Vídeo-guia da ferramenta de uso e cobertura do solo (classificação supervisionada com IA)
    'lulc_video' => [
        'id' => env('LULC_VIDEO_ID', '2conaBVmZl8'),
        'duration' => 'PT1M16S',
        'uploaded' => '2026-11-22T08:00:00+02:00',
    ],
    'intro_video' => [
        'id' => env('INTRO_VIDEO_ID', '1annxj00NGI'),
        'duration' => 'PT1M36S',
        'uploaded' => '2026-11-15T12:00:00+02:00', // ISO 8601 com fuso horário (o Google exige hora e fuso)
    ],

    // Versão do texto de consentimento de cookies: ao mudar, o banner volta a aparecer.
    'consent_version' => env('CONSENT_VERSION', '2026-11-13'),

    // Perda de cobertura arbórea Hansen/UMD (Global Forest Change), lida aos bocados pela ponte /dados-abertos/hansen/{bloco}.
    // Nova versão anual: a vigilância diária (geofloresta:check-sources) avisa quando GFC-<ano+1> fica disponível.
    'hansen' => [
        'version' => env('HANSEN_VERSION', 'v1.13'),
        'last_year' => (int) env('HANSEN_LAST_YEAR', 2025),
        'url' => env('HANSEN_URL', 'https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/Hansen_GFC-2025-v1.13_lossyear_{tile}.tif'),
        'max_bytes' => 16 * 1024 * 1024,
    ],

    // Exemplos por baixo do mapa, em vários continentes (abrem a ferramenta já com a área: ?bbox=oeste,sul,leste,norte); imagens Sentinel-2 reais
    'map_examples' => [
        'deforestation' => [
            ['key' => 'zambezia', 'bbox' => '36.495,-16.555,36.555,-16.495', 'image' => 'img/examples/zambezia.webp'],
            ['key' => 'para', 'bbox' => '-54.12,-8.22,-54.06,-8.16', 'image' => 'img/examples/para.webp'],
            ['key' => 'peten', 'bbox' => '-90.58,17.22,-90.52,17.28', 'image' => 'img/examples/peten.webp'],
            ['key' => 'papua', 'bbox' => '140.66,-8.28,140.72,-8.22', 'image' => 'img/examples/papua.webp'],
        ],
        'lulc' => [
            ['key' => 'urema', 'bbox' => '34.34,-18.96,34.60,-18.80', 'image' => 'img/examples/urema.webp'],
            ['key' => 'tejo', 'bbox' => '-9.20,38.66,-8.95,38.82', 'image' => 'img/examples/tejo.webp'],
            ['key' => 'mekong', 'bbox' => '105.65,9.98,105.85,10.12', 'image' => 'img/examples/mekong.webp'],
            ['key' => 'california', 'bbox' => '-119.95,36.65,-119.70,36.85', 'image' => 'img/examples/california.webp'],
        ],
        'fires' => [
            ['key' => 'niassa', 'bbox' => '37.30,-12.45,37.80,-11.95', 'image' => 'img/examples/niassa.webp'],
            ['key' => 'pantanal', 'bbox' => '-57.70,-18.10,-57.10,-17.50', 'image' => 'img/examples/pantanal.webp'],
            ['key' => 'kalteng', 'bbox' => '113.70,-2.55,114.20,-2.05', 'image' => 'img/examples/kalteng.webp'],
            ['key' => 'sierra', 'bbox' => '-119.60,37.05,-119.00,37.55', 'image' => 'img/examples/sierra.webp'],
        ],
        'ndvi' => [
            ['key' => 'nilo', 'bbox' => '30.80,30.50,31.60,31.20', 'image' => 'img/examples/nilo.webp'],
            ['key' => 'punjab', 'bbox' => '75.40,30.60,76.20,31.20', 'image' => 'img/examples/punjab.webp'],
            ['key' => 'hungria', 'bbox' => '19.60,46.30,20.40,46.90', 'image' => 'img/examples/hungria.webp'],
            ['key' => 'kansas', 'bbox' => '-101.30,37.70,-100.50,38.30', 'image' => 'img/examples/kansas.webp'],
        ],
        'relief' => [
            ['key' => 'kilimanjaro', 'bbox' => '37.20,-3.20,37.50,-2.95', 'image' => 'img/examples/kilimanjaro.webp'],
            ['key' => 'montblanc', 'bbox' => '6.75,45.75,6.98,45.92', 'image' => 'img/examples/montblanc.webp'],
            ['key' => 'everest', 'bbox' => '86.80,27.88,87.05,28.08', 'image' => 'img/examples/everest.webp'],
            ['key' => 'grandcanyon', 'bbox' => '-112.30,35.95,-111.95,36.20', 'image' => 'img/examples/grandcanyon.webp'],
        ],
        'satellite' => [
            ['key' => 'ilha', 'bbox' => '40.66,-15.10,40.81,-14.97', 'image' => 'img/examples/ilha.webp'],
            ['key' => 'veneza', 'bbox' => '12.25,45.38,12.42,45.48', 'image' => 'img/examples/veneza.webp'],
            ['key' => 'dubai', 'bbox' => '55.05,25.05,55.25,25.17', 'image' => 'img/examples/dubai.webp'],
            ['key' => 'panama', 'bbox' => '-79.65,8.90,-79.50,9.05', 'image' => 'img/examples/panama.webp'],
        ],
    ],
];
