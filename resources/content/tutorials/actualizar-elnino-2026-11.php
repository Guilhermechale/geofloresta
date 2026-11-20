<?php
// Actualiza a secção 5 do tutorial El Niño (PT/FR/EN) com a situação de Novembro de 2026.
// Fontes: NOAA CPC (12/11/2026), OMM (3/9/2026, 21/10/2026, 9/11/2026), IRI (19/10/2026).
// Guarda cópia de segurança dos campos antigos em storage/app/backup-elnino-<data>.json.
$app = '/home/u923245343/geofloresta';
require $app.'/vendor/autoload.php';
$laravel = require $app.'/bootstrap/app.php';
$laravel->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = App\Models\Tutorial::where('legacy_slug', 'o-que-e-o-el-nino-e-o-que-esta-a-acontecer-actualmente')->firstOrFail();
$backup = $app.'/storage/app/backup-elnino-'.date('Ymd-His').'.json';
file_put_contents($backup, json_encode([
    'body' => $t->getTranslations('body'), 'summary' => $t->getTranslations('summary'),
    'seo_description' => $t->getTranslations('seo_description'), 'sources' => $t->sources,
    'content_updated_at' => (string) $t->content_updated_at,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "cópia de segurança: {$backup}\n";

$sec = [
    'pt' => <<<'HTML'
<h2>5. Situação em Novembro de 2026</h2>
    <p>Segundo a Discussão de Diagnóstico do ENOS do Centro de Previsão Climática da NOAA (CPC), publicada a <strong>12 de Novembro de 2026</strong>, mantém-se o <strong>Aviso de El Niño</strong> (<em>El Niño Advisory</em>) e o fenómeno <strong>continua a intensificar-se</strong>. As anomalias da temperatura da superfície do mar ultrapassaram os <strong>+4,0 °C</strong> no Pacífico equatorial leste e alargaram-se para oeste. Os índices subiram para <strong>+2,5 °C no Niño 3.4</strong>, +3,6 °C no Niño 3 e +4,4 °C no Niño 1+2. Abaixo da superfície, a termoclina está mais funda do que o normal, com anomalias acima de <strong>+10 °C</strong> em profundidade no Pacífico leste.</p>
    <p>A atmosfera acompanha o oceano: o Índice de Oscilação Sul mantém-se consistentemente negativo, os ventos de oeste junto à superfície estendem-se do Pacífico ocidental ao centro-leste, a chuva e a convecção estão reforçadas no Pacífico central e oriental e enfraquecidas sobre a Indonésia. Os valores variam conforme o índice: a NOAA classifica a intensidade histórica com o índice relativo (RONI), enquanto o IRI (Universidade de Columbia) usa o índice tradicional, que atingiu <strong>+2,84 °C em Setembro</strong> e <strong>+3,3 °C</strong> na semana centrada a 14 de Outubro de 2026.</p>
    <p><strong>O que se espera.</strong> A NOAA prevê que o El Niño continue a intensificar-se antes de começar a enfraquecer no início de 2027, com uma probabilidade <strong>superior a 79 %</strong> de se manter <strong>forte a muito forte até Janeiro–Março de 2027</strong>. A probabilidade de ser um evento histórico, acima de todos os El Niño desde 1950 (RONI trimestral de +2,5 °C ou mais), é de cerca de <strong>60 %</strong> para Outubro–Dezembro e Novembro–Janeiro. Na avaliação do IRI de 19 de Outubro, os 27 modelos analisados prevêem um El Niño «muito forte» (Niño 3.4 ≥ +2,0 °C) de Outubro–Dezembro de 2026 a Dezembro–Fevereiro de 2027, com condições moderadas a fortes até Maio–Julho de 2027.</p>
    <p>A OMM, na actualização de Setembro, já tinha indicado uma probabilidade próxima de 100 % de o El Niño persistir até Fevereiro de 2027, e a actualização sazonal de 21 de Outubro aponta o <strong>pico para cerca de Dezembro de 2026</strong>. No relatório sobre o Estado do Clima em 2026, apresentado a 9 de Novembro na COP31, a OMM refere que a temperatura média global de Janeiro a Setembro de 2026 esteve cerca de <strong>1,47 °C acima do nível pré-industrial</strong> e que o El Niño deverá ser o mais forte de que há registo em Dezembro. A próxima discussão da NOAA está prevista para 10 de Dezembro de 2026.</p>
    <p><strong>E em Moçambique?</strong> A época chuvosa (Novembro a Março) coincide com o pico do El Niño. Em episódios fortes, o sul e o centro do país tendem a receber menos chuva, como em 2015–2016 (ver secção 6), mas o impacto de cada ano depende também do Oceano Índico. Para decisões locais, siga as previsões sazonais e os avisos do <strong>INAM</strong> (Instituto Nacional de Meteorologia).</p>

HTML,
    'fr' => <<<'HTML'
<h2>5. Situation en novembre 2026</h2>
    <p>Selon la Discussion diagnostique ENSO du Centre de prévision climatique de la NOAA (CPC), publiée le <strong>12 novembre 2026</strong>, l’<strong>avis El Niño</strong> (<em>El Niño Advisory</em>) est maintenu et le phénomène <strong>continue de se renforcer</strong>. Les anomalies de température de surface de la mer ont dépassé <strong>+4,0 °C</strong> dans le Pacifique équatorial est et se sont étendues vers l’ouest. Les indices ont atteint <strong>+2,5 °C pour Niño 3.4</strong>, +3,6 °C pour Niño 3 et +4,4 °C pour Niño 1+2. Sous la surface, la thermocline est plus profonde que la normale, avec des anomalies supérieures à <strong>+10 °C</strong> en profondeur dans le Pacifique est.</p>
    <p>L’atmosphère suit l’océan : l’indice d’oscillation australe reste nettement négatif, les vents d’ouest près de la surface s’étendent du Pacifique occidental au centre-est, et la convection et les pluies sont renforcées dans le Pacifique central et oriental et affaiblies au-dessus de l’Indonésie. Les valeurs varient selon l’indice : la NOAA classe l’intensité historique avec l’indice relatif (RONI), tandis que l’IRI (Université Columbia) utilise l’indice traditionnel, qui a atteint <strong>+2,84 °C en septembre</strong> et <strong>+3,3 °C</strong> la semaine centrée sur le 14 octobre 2026.</p>
    <p><strong>Ce qui est attendu.</strong> La NOAA prévoit qu’El Niño continue de se renforcer avant de commencer à faiblir début 2027, avec une probabilité <strong>supérieure à 79 %</strong> qu’il reste <strong>fort à très fort jusqu’en janvier–mars 2027</strong>. La probabilité d’un événement historique, supérieur à tous les El Niño depuis 1950 (RONI trimestriel de +2,5 °C ou plus), est d’environ <strong>60 %</strong> pour octobre–décembre et novembre–janvier. Dans l’évaluation de l’IRI du 19 octobre, les 27 modèles analysés prévoient un El Niño « très fort » (Niño 3.4 ≥ +2,0 °C) d’octobre–décembre 2026 à décembre–février 2027, avec des conditions modérées à fortes jusqu’en mai–juillet 2027.</p>
    <p>L’OMM avait déjà indiqué, dans sa mise à jour de septembre, une probabilité proche de 100 % qu’El Niño persiste jusqu’en février 2027, et sa mise à jour saisonnière du 21 octobre situe le <strong>pic vers décembre 2026</strong>. Dans son rapport sur l’état du climat en 2026, présenté le 9 novembre à la COP31, l’OMM indique que la température moyenne mondiale de janvier à septembre 2026 a été d’environ <strong>1,47 °C au-dessus du niveau préindustriel</strong> et qu’El Niño devrait être le plus fort jamais enregistré en décembre. La prochaine discussion de la NOAA est prévue le 10 décembre 2026.</p>
    <p><strong>Et au Mozambique ?</strong> La saison des pluies (novembre à mars) coïncide avec le pic d’El Niño. Lors des épisodes forts, le sud et le centre du pays reçoivent souvent moins de pluie, comme en 2015–2016 (voir section 6), mais l’impact de chaque année dépend aussi de l’océan Indien. Pour les décisions locales, suivez les prévisions saisonnières et les alertes de l’<strong>INAM</strong> (Institut national de météorologie du Mozambique).</p>

HTML,
    'en' => <<<'HTML'
<h2>5. The situation in November 2026</h2>
    <p>According to the ENSO Diagnostic Discussion from NOAA’s Climate Prediction Center (CPC), issued on <strong>12 November 2026</strong>, the <strong>El Niño Advisory</strong> remains in place and the event <strong>continues to strengthen</strong>. Sea surface temperature anomalies exceeded <strong>+4.0 °C</strong> in the eastern equatorial Pacific and extended farther west. The indices rose to <strong>+2.5 °C in Niño 3.4</strong>, +3.6 °C in Niño 3 and +4.4 °C in Niño 1+2. Below the surface, the thermocline is deeper than average, with anomalies above <strong>+10 °C</strong> at depth in the eastern Pacific.</p>
    <p>The atmosphere is following the ocean: the Southern Oscillation Index remains consistently negative, low-level westerly winds extend from the western to the east-central Pacific, and convection and rainfall are enhanced over the central and eastern Pacific and suppressed over Indonesia. Values differ depending on the index: NOAA ranks historical strength with the relative index (RONI), while the IRI (Columbia University) uses the traditional index, which reached <strong>+2.84 °C in September</strong> and <strong>+3.3 °C</strong> in the week centred on 14 October 2026.</p>
    <p><strong>What to expect.</strong> NOAA expects El Niño to keep strengthening before it begins to weaken in early 2027, with a <strong>greater than 79%</strong> chance of a <strong>strong to very strong El Niño through January–March 2027</strong>. The chance of a historic event, exceeding every El Niño since 1950 (a 3-month RONI of +2.5 °C or more), is about <strong>60%</strong> for October–December and November–January. In the IRI assessment of 19 October, all 27 models analysed forecast a “very strong” El Niño (Niño 3.4 ≥ +2.0 °C) from October–December 2026 to December–February 2027, with moderate to strong conditions lasting until May–July 2027.</p>
    <p>The WMO had already put the chance of El Niño persisting until February 2027 at nearly 100% in its September update, and its seasonal update of 21 October places the <strong>peak around December 2026</strong>. In its State of the Climate 2026 report, released on 9 November at COP31, the WMO notes that the global mean temperature for January–September 2026 was about <strong>1.47 °C above the pre-industrial level</strong> and that El Niño is expected to be the strongest on record by December. NOAA’s next discussion is scheduled for 10 December 2026.</p>
    <p><strong>And in Mozambique?</strong> The rainy season (November to March) coincides with El Niño’s peak. In strong episodes, the south and centre of the country tend to receive less rain, as in 2015–2016 (see section 6), but each year’s impact also depends on the Indian Ocean. For local decisions, follow the seasonal forecasts and warnings from <strong>INAM</strong> (Mozambique’s National Institute of Meteorology).</p>

HTML,
];
$summary = [
    'pt' => 'Perceba o ciclo El Niño–Oscilação Sul, os índices usados para o monitorizar e os efeitos habituais no mundo e em Moçambique, com a situação de Novembro de 2026.',
    'fr' => 'Comprenez le cycle El Niño–Oscillation australe, les indices utilisés pour le suivre et ses effets habituels dans le monde et au Mozambique, avec la situation de novembre 2026.',
    'en' => 'Understand the El Niño–Southern Oscillation cycle, the indices used to monitor it and its usual effects around the world and in Mozambique, with the situation as of November 2026.',
];
$seo = [
    'pt' => 'O que é o El Niño, como se mede e o que esperar até 2027: situação de Novembro de 2026 (NOAA, OMM), índices Niño 3.4 e ONI e efeitos em Moçambique.',
    'fr' => 'Qu’est-ce qu’El Niño, comment le mesurer et à quoi s’attendre jusqu’en 2027 : situation de novembre 2026 (NOAA, OMM), indices Niño 3.4 et ONI.',
    'en' => 'What El Niño is, how it is measured and what to expect through 2027: the November 2026 situation (NOAA, WMO), the Niño 3.4 and ONI indices.',
];

foreach (['pt', 'fr', 'en'] as $l) {
    $b = (string) $t->getTranslation('body', $l, false);
    $n = 0;
    $b = preg_replace('#<h2>5\.[^<]*</h2>.*?(?=<figure>)#s', str_replace(['\\', '$'], ['\\\\', '\\$'], $sec[$l]), $b, 1, $n);
    if ($n !== 1) {
        throw new RuntimeException("secção 5 não encontrada em {$l}");
    }
    $t->setTranslation('body', $l, $b);
    $t->setTranslation('summary', $l, $summary[$l]);
    $t->setTranslation('seo_description', $l, $seo[$l]);
    echo "{$l}: secção 5 substituída (".strlen($b)." bytes)\n";
}
$t->sources = "NOAA Climate Prediction Center — ENSO Diagnostic Discussion (12 de Novembro de 2026) | https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/enso_advisory/ensodisc.shtml\n"
    ."World Meteorological Organization — El Niño set to become very strong, raising risks of extreme weather into 2027 (3 de Setembro de 2026) | https://wmo.int/media/news/el-nino-set-become-very-strong-raising-risks-of-extreme-weather-2027\n"
    ."World Meteorological Organization — State of the climate in 2026 (9 de Novembro de 2026) | https://wmo.int/media/news/state-of-climate-2026-gives-new-meaning-phrase-charts\n"
    ."IRI, Columbia University — ENSO Quick Look e Technical ENSO Update (19 de Outubro de 2026) | https://iri.columbia.edu/our-expertise/climate/forecasts/enso/current/\n"
    ."World Meteorological Organization — El Niño/La Niña Update (Agosto de 2026) | https://wmo.int/resources/publication-series/el-ninola-nina-updates/august-2026\n"
    ."NOAA CPC — Oceanic Niño Index (ONI) | https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/ensostuff/ONI_v5.php\n"
    ."World Meteorological Organization — El Niño/La Niña background (2003)";
$t->content_updated_at = now()->toDateString();
$t->save();
echo "guardado; actualizado em {$t->content_updated_at}\n";
foreach (['pt', 'fr', 'en'] as $l) {
    echo $t->url($l)."\n";
}
