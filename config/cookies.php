<?php

/*
 | Inventário REAL de cookies e armazenamento local usados pelo site.
 | A tabela da Política de Cookies e o painel "Personalizar" são gerados a partir daqui.
 | Ao acrescentar um script ou cookie novo: registar aqui E subir CONSENT_VERSION no .env.
 |
 | category: necessary | preferences | analytics
 | requires: chave de config que tem de estar preenchida para o item existir (ex.: GA4)
 */
return [
    'categories' => ['necessary', 'preferences', 'analytics'],

    'items' => [
        ['name' => 'geofloresta_session', 'type' => 'cookie', 'category' => 'necessary', 'duration' => ['minutes' => 120], 'purpose' => 'session', 'party' => 'first'],
        ['name' => 'XSRF-TOKEN', 'type' => 'cookie', 'category' => 'necessary', 'duration' => ['minutes' => 120], 'purpose' => 'csrf', 'party' => 'first'],
        ['name' => 'gf_consent', 'type' => 'cookie', 'category' => 'necessary', 'duration' => ['months' => 6], 'purpose' => 'consent', 'party' => 'first'],
        ['name' => 'gf_lang', 'type' => 'cookie', 'category' => 'preferences', 'duration' => ['months' => 12], 'purpose' => 'lang', 'party' => 'first', 'note' => 'lang_session'],
        ['name' => 'gf-theme', 'type' => 'localStorage', 'category' => 'preferences', 'duration' => ['until_cleared' => true], 'purpose' => 'theme', 'party' => 'first', 'note' => 'theme_session'],
        ['name' => '_ga', 'type' => 'cookie', 'category' => 'analytics', 'duration' => ['months' => 24], 'purpose' => 'ga', 'party' => 'google', 'requires' => 'geofloresta.ga4_id'],
        ['name' => '_ga_<ID>', 'type' => 'cookie', 'category' => 'analytics', 'duration' => ['months' => 24], 'purpose' => 'ga_session', 'party' => 'google', 'requires' => 'geofloresta.ga4_id'],
    ],

    // Validade da escolha de consentimento (depois volta a perguntar)
    'consent_months' => 6,
];
