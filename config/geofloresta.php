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
];
