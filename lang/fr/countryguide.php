<?php

// Résumé propre à chaque page pays (calculé à partir des données du pays).
return [
    'title' => 'En bref',
    'in_continent' => [
        'Africa' => 'en Afrique', 'Asia' => 'en Asie', 'Europe' => 'en Europe', 'North America' => 'en Amérique du Nord et centrale',
        'South America' => 'en Amérique du Sud', 'Oceania' => 'en Océanie', 'Antarctica' => 'en Antarctique', 'Seven seas (open ocean)' => 'en plein océan',
    ],
    'location' => 'Ce pays se situe :in_continent et s’étend approximativement entre les latitudes :lat0 et :lat1 et les longitudes :lon0 et :lon1.',
    'fires_some' => 'Au cours des 7 derniers jours, les satellites VIIRS ont détecté :n7 foyers de chaleur actifs dans ce pays (:n24 au cours des dernières 24 heures), ce qui le place au rang :rank sur les :total pays ayant des foyers sur cette période.',
    'fires_none' => 'Au cours des 7 derniers jours, les satellites VIIRS n’ont détecté aucun foyer de chaleur actif dans ce pays.',
    'units_both' => 'Les limites administratives geoBoundaries divisent le pays en :adm1 unités de premier niveau et :adm2 de deuxième niveau, prêtes pour agréger des statistiques par région.',
    'units_one' => 'Les limites administratives geoBoundaries divisent le pays en :adm1 unités de premier niveau.',
    'water' => 'Pour les rivières, les lacs et les bassins versants, utilisez les fichiers HydroSHEDS de la région :regions et découpez-les avec le contour du pays.',
    'service' => 'Besoin d’une carte, d’une analyse de la déforestation ou des incendies, ou d’un extrait de données pour ce pays (:country) ? GEOFLORESTA travaille à distance pour des clients de tous les pays.',
    'service_link' => 'Demander une proposition',
    'and' => 'et',
    'dir' => ['N' => 'N', 'S' => 'S', 'E' => 'E', 'W' => 'O'],
];
