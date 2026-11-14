<?php

// Overview specific to each country page (calculated from the country's data).
return [
    'title' => 'Overview',
    'in_continent' => [
        'Africa' => 'in Africa', 'Asia' => 'in Asia', 'Europe' => 'in Europe', 'North America' => 'in North and Central America',
        'South America' => 'in South America', 'Oceania' => 'in Oceania', 'Antarctica' => 'in Antarctica', 'Seven seas (open ocean)' => 'in the open ocean',
    ],
    'location' => ':country lies :in_continent, roughly between latitudes :lat0 and :lat1 and longitudes :lon0 and :lon1.',
    'fires_some' => 'In the last 7 days, the VIIRS satellites detected :n7 active fire hotspots in :country (:n24 in the last 24 hours), ranking :rank of the :total countries with hotspots in that period.',
    'fires_none' => 'In the last 7 days, the VIIRS satellites detected no active fire hotspots in :country.',
    'units_both' => 'The geoBoundaries administrative boundaries divide the country into :adm1 first-level and :adm2 second-level units, ready for adding up statistics by region.',
    'units_one' => 'The geoBoundaries administrative boundaries divide the country into :adm1 first-level units.',
    'water' => 'For rivers, lakes and river basins, use the HydroSHEDS files for the :regions region and clip them with the country outline.',
    'service' => 'Do you need a map, a deforestation or fire analysis, or a data extract for :country? GEOFLORESTA works remotely for clients in any country.',
    'service_link' => 'Request a proposal',
    'and' => 'and',
    'dir' => ['N' => 'N', 'S' => 'S', 'E' => 'E', 'W' => 'W'],
];
