<?php

// Supporting text on each dataset page (uses the fields of the record).
return [
    'uses_title' => 'What these data are for',
    'uses' => [
        'land-cover' => 'Land use and land cover maps show where there is forest, shrubland, grassland, cropland, built-up areas, water and bare soil. They are the basis for land-use plans, environmental impact assessments, forest inventories, area statistics by class and comparing the landscape between years. They also help to choose field sampling sites and to validate classifications made with Sentinel-2 or Landsat imagery.',
        'forest-loss' => 'Tree cover loss data show where and in which year trees disappeared. They are used to measure deforestation by district or protected area, find hotspots, monitor zero-deforestation commitments in supply chains, support REDD+ projects and sustainability reports, and guide enforcement on the ground.',
        'fire' => 'Satellite-detected hotspots show where burning and wildfires took place. They are used to follow the fire season, alert field teams, estimate fire pressure on forests and conservation areas, relate fires to farming and hunting, and plan firebreaks and community awareness campaigns.',
        'hydrology' => 'River networks and river basins are used to delineate the area draining to a point, study floods and droughts, plan water supply and irrigation, site bridges and roads, and define protection zones for springs and riverbanks. Basin levels let you work from large regional basins down to small sub-basins.',
        'biomass' => 'Above-ground biomass and carbon maps estimate how much wood and carbon there is in each pixel. They are used to calculate forest carbon stocks for REDD+ projects and voluntary carbon markets, prepare greenhouse gas inventories, and compare biomass in protected and logged areas.',
        'boundaries' => 'Administrative boundaries (country, provinces, districts) are the reference layer of almost every map. They are used to clip other data to your area, add up statistics by administrative unit (forest area, fire hotspots, population), prepare location maps and join official data tables to the map.',
        'climate' => 'Climate data (temperature, rainfall and other variables) are used to describe the climate of a study area, analyse trends and anomalies, relate climate to fires, crop yields or forest growth, and prepare climate change adaptation scenarios.',
    ],
    'howto_title' => 'How to use them in QGIS',
    'howto' => [
        'download' => 'Download the file in the format you prefer. GeoPackage is the most practical in QGIS; Shapefile suits older software; CSV opens in any spreadsheet.',
        'source' => 'Open the official source (:source), choose the area or tile covering your territory and download the available format.',
        'open' => 'In QGIS, drag the file onto the map window or use Layer → Add Layer.',
        'clip' => 'Clip to your study area: Vector → Geoprocessing Tools → Clip, or Raster → Extraction → Clip Raster by Mask Layer. The boundaries of every country are in Data by country.',
        'project' => 'Before measuring areas or distances, reproject to a metric system, for example the UTM zone of your area.',
        'cite' => 'When publishing maps or reports, cite the source as shown under “Attribution”.',
    ],
    'faq_title' => 'Frequently asked questions',
    'faq' => [
        'free_q' => 'Is “:title” free?',
        'free_a' => 'Yes. The data are produced by :source and distributed under the :licence licence. You can download them without registering and without paying.',
        'commercial_q' => 'Can I use these data in commercial work?',
        'commercial_a' => 'The licence (:licence) defines what is allowed. The most common open licences in this catalogue (CC BY 4.0, ODbL and public domain) allow commercial use with attribution, but always check the terms through the licence link in this page’s record.',
        'crs_q' => 'Which coordinate system are the files in?',
        'crs_a' => ':crs. They open directly in QGIS, ArcGIS and Google Earth Engine. To calculate areas, reproject to a metric system.',
        'resolution_a' => 'The spatial resolution is :resolution.',
        'custom_q' => 'Do you need these data only for your area, or a ready-made map?',
        'custom_a' => 'GEOFLORESTA prepares extracts by country, province, district or your own polygon, with a map and report, for clients in any country, on site or remotely.',
    ],
    'more_title' => 'See also',
    'more_countries' => 'Open data by country',
    'more_learn' => 'Free GIS and remote sensing tutorials',
];
