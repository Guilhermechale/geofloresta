<?php

/*
 | Registo das fontes de dados e componentes de terceiros (página "Dados e Licenças").
 | Espelha docs/DADOS-E-LICENCAS.md. "status":
 |   hosted   = alojamos ficheiros derivados (licença verificada)
 |   service  = serviço usado no site (mapas, camadas)
 |   link     = só ligação externa (licença restritiva, desconhecida ou por confirmar)
 |   software = biblioteca / tipo de letra
 | "verified" = data em que a licença foi lida na página oficial (null = por verificar).
 | Atribuições e nomes de licenças não se traduzem.
 */
return [
    [
        'key' => 'openfreemap', 'name' => 'OpenFreeMap / OpenMapTiles / OpenStreetMap', 'use' => 'basemap', 'status' => 'service',
        'url' => 'https://openfreemap.org/', 'licence' => 'ODbL-1.0 (dados OSM); OpenMapTiles schema BSD-3-Clause / CC-BY-4.0',
        'attribution' => 'OpenFreeMap © OpenMapTiles Data from OpenStreetMap', 'verified' => null,
    ],
    [
        'key' => 'gibs', 'name' => 'NASA Global Imagery Browse Services (GIBS)', 'use' => 'imagery', 'status' => 'service',
        'url' => 'https://www.earthdata.nasa.gov/engage/open-data-services-software/earthdata-developer-portal/gibs-api',
        'licence' => 'NASA Earth Science Data — open, no restrictions',
        'attribution' => "We acknowledge the use of imagery provided by services from NASA's Global Imagery Browse Services (GIBS), part of NASA's Earth Science Data and Information System (ESDIS).",
        'verified' => null,
    ],
    [
        'key' => 'firms', 'name' => 'NASA Fire Information for Resource Management System (FIRMS)', 'use' => 'fire', 'status' => 'hosted',
        'url' => 'https://firms.modaps.eosdis.nasa.gov/', 'licence' => 'NASA Earth Science Data — open data policy (no restrictions on reuse)',
        'attribution' => 'We acknowledge the use of imagery from the NASA LANCE FIRMS (https://earthdata.nasa.gov/firms), part of the NASA Earth Science Data and Information System (ESDIS).',
        'verified' => '2026-11-13',
    ],
    [
        'key' => 'worldcover', 'name' => 'ESA WorldCover 10 m (2020 v100, 2021 v200)', 'use' => 'land-cover', 'status' => 'hosted',
        'url' => 'https://esa-worldcover.org/', 'licence' => 'CC-BY-4.0',
        'attribution' => '© ESA WorldCover project 2021 / Contains modified Copernicus Sentinel data (2021) processed by ESA WorldCover consortium',
        'verified' => null,
    ],
    [
        'key' => 'hansen', 'name' => 'Hansen/UMD/Google/USGS/NASA Global Forest Change v1.12 (2000–2024)', 'use' => 'forest-loss', 'status' => 'hosted',
        'url' => 'https://glad.earthengine.app/view/global-forest-change', 'licence' => 'CC-BY-4.0',
        'attribution' => 'Hansen, M. C., P. V. Potapov, R. Moore, M. Hancher, S. A. Turubanova, A. Tyukavina, D. Thau, S. V. Stehman, S. J. Goetz, T. R. Loveland, A. Kommareddy, A. Egorov, L. Chini, C. O. Justice, and J. R. G. Townshend. 2013. "High-Resolution Global Maps of 21st-Century Forest Cover Change." Science 342 (15 November): 850–53.',
        'verified' => null,
    ],
    [
        'key' => 'wri-drivers', 'name' => 'WRI / Google DeepMind — Drivers of forest loss (2001–2024, 1 km)', 'use' => 'forest-loss', 'status' => 'hosted',
        'url' => 'https://data.globalforestwatch.org/', 'licence' => 'CC-BY-4.0',
        'attribution' => 'World Resources Institute and Google DeepMind. Drivers of Forest Loss. Accessed through Global Forest Watch.',
        'verified' => null,
    ],
    [
        'key' => 'hydrosheds', 'name' => 'HydroSHEDS v2 / HydroBASINS / HydroRIVERS', 'use' => 'hydrology', 'status' => 'hosted',
        'url' => 'https://www.hydrosheds.org/', 'licence' => 'CC-BY-4.0 (HydroSHEDS v2 core); HydroBASINS/HydroRIVERS: HydroSHEDS License Agreement',
        'attribution' => 'Lehner, B., Grill G. (2013). Global river hydrography and network routing: baseline data and new approaches to study the world’s large river systems. Hydrological Processes, 27(15): 2171–2186.',
        'verified' => null,
    ],
    [
        'key' => 'jrc-gsw', 'name' => 'JRC Global Surface Water', 'use' => 'hydrology', 'status' => 'link',
        'url' => 'https://global-surface-water.appspot.com/', 'licence' => 'Por confirmar (Copernicus / JRC)',
        'attribution' => 'Pekel, J.-F., Cottam, A., Gorelick, N., Belward, A. S. (2016). High-resolution mapping of global surface water and its long-term changes. Nature 540, 418–422.',
        'verified' => null,
    ],
    [
        'key' => 'naturalearth', 'name' => 'Natural Earth — Admin 1 (províncias)', 'use' => 'boundaries', 'status' => 'hosted',
        'url' => 'https://www.naturalearthdata.com/', 'licence' => 'Public domain',
        'attribution' => 'Made with Natural Earth. Free vector and raster map data @ naturalearthdata.com.', 'verified' => '2026-11-13',
    ],
    [
        'key' => 'geoboundaries', 'name' => 'geoBoundaries — Moçambique ADM2 (distritos), fonte INE / OCHA ROSEA', 'use' => 'boundaries', 'status' => 'hosted',
        'url' => 'https://www.geoboundaries.org/', 'licence' => 'CC-BY-3.0-IGO',
        'attribution' => 'Mozambique INE (Instituto Nacional de Estatística), OCHA ROSEA, via geoBoundaries — Runfola, D. et al. (2020) geoBoundaries: A global database of political administrative boundaries. PLoS ONE 15(4): e0231866.',
        'verified' => '2026-11-13',
    ],
    [
        'key' => 'cci-biomass', 'name' => 'ESA Climate Change Initiative — Biomass (v7)', 'use' => 'biomass', 'status' => 'link',
        'url' => 'https://climate.esa.int/en/projects/biomass/', 'licence' => 'LicenseRef-CEDA-ESA-CCI (termos próprios)',
        'attribution' => 'Santoro, M.; Cartus, O. ESA Biomass Climate Change Initiative (Biomass_cci): Global datasets of forest above-ground biomass. NERC EDS Centre for Environmental Data Analysis.',
        'verified' => null,
    ],
    // Software e tipos de letra
    ['key' => 'maplibre', 'name' => 'MapLibre GL JS', 'use' => 'software', 'status' => 'software', 'url' => 'https://maplibre.org/', 'licence' => 'BSD-3-Clause', 'attribution' => '© MapLibre contributors', 'verified' => null],
    ['key' => 'turf', 'name' => 'Turf.js', 'use' => 'software', 'status' => 'software', 'url' => 'https://turfjs.org/', 'licence' => 'MIT', 'attribution' => '© Turf.js contributors', 'verified' => null],
    ['key' => 'fonts', 'name' => 'Newsreader, IBM Plex Sans, IBM Plex Mono', 'use' => 'software', 'status' => 'software', 'url' => 'https://fontsource.org/', 'licence' => 'OFL-1.1', 'attribution' => 'SIL Open Font License 1.1', 'verified' => null],
    ['key' => 'laravel', 'name' => 'Laravel, Filament', 'use' => 'software', 'status' => 'software', 'url' => 'https://laravel.com/', 'licence' => 'MIT', 'attribution' => '© Taylor Otwell; © Filament', 'verified' => null],
];
