<?php

// Texte d'accompagnement des pages de chaque jeu de données (utilise les champs de la fiche).
return [
    'uses_title' => 'À quoi servent ces données',
    'uses' => [
        'land-cover' => 'Les cartes d’occupation et d’utilisation du sol montrent où se trouvent la forêt, les fourrés, les prairies, l’agriculture, les zones urbaines, l’eau et le sol nu. Elles servent de base aux plans d’aménagement du territoire, aux études d’impact environnemental, aux inventaires forestiers, au calcul des surfaces par classe et à la comparaison du paysage d’une année à l’autre. Elles aident aussi à choisir des zones d’échantillonnage sur le terrain et à valider des classifications faites avec Sentinel-2 ou Landsat.',
        'forest-loss' => 'Les données de perte du couvert arboré indiquent où et en quelle année les arbres ont disparu. Elles servent à mesurer la déforestation par district ou aire protégée, à repérer les points chauds, à suivre les engagements « zéro déforestation » des chaînes d’approvisionnement, à appuyer les projets REDD+ et les rapports de durabilité, et à orienter les contrôles sur le terrain.',
        'fire' => 'Les foyers de chaleur détectés par satellite montrent où il y a eu des brûlis et des incendies. Ils servent à suivre la saison des feux, à alerter les équipes sur le terrain, à estimer la pression du feu sur les forêts et les aires de conservation, à relier les incendies à l’agriculture et à la chasse, et à planifier des pare-feux et des actions de sensibilisation.',
        'hydrology' => 'Les réseaux de rivières et les bassins versants servent à délimiter la zone qui draine vers un point, à étudier les crues et les sécheresses, à planifier l’approvisionnement en eau et l’irrigation, à situer ponts et routes et à définir des zones de protection des sources et des berges. Les niveaux de bassin permettent de travailler des grands bassins régionaux aux petits sous-bassins.',
        'biomass' => 'Les cartes de biomasse aérienne et de carbone estiment la quantité de bois et de carbone dans chaque pixel. Elles servent à calculer les stocks de carbone des forêts, des projets REDD+ et des marchés volontaires du carbone, à préparer des inventaires de gaz à effet de serre et à comparer la biomasse des aires protégées et des zones exploitées.',
        'boundaries' => 'Les limites administratives (pays, provinces, districts) sont la couche de référence de presque toutes les cartes. Elles servent à découper d’autres données sur votre zone, à agréger des statistiques par unité administrative (surface forestière, foyers d’incendie, population), à préparer des cartes de situation et à relier des tableaux de données officielles à la carte.',
        'climate' => 'Les données climatiques (température, précipitations et autres variables) servent à décrire le climat d’une zone d’étude, à analyser tendances et anomalies, à relier le climat aux incendies, à la production agricole ou à la croissance forestière, et à préparer des scénarios d’adaptation au changement climatique.',
    ],
    'howto_title' => 'Comment les utiliser dans QGIS',
    'howto' => [
        'download' => 'Téléchargez le fichier dans le format de votre choix. Le GeoPackage est le plus pratique dans QGIS ; le Shapefile convient aux logiciels plus anciens ; le CSV s’ouvre dans n’importe quel tableur.',
        'source' => 'Ouvrez la source officielle (:source), choisissez la zone ou la tuile qui couvre votre territoire et téléchargez le format disponible.',
        'open' => 'Dans QGIS, faites glisser le fichier dans la fenêtre de la carte ou utilisez Couche → Ajouter une couche.',
        'clip' => 'Découpez sur votre zone d’étude : Vecteur → Outils de géotraitement → Couper, ou Raster → Extraction → Découper un raster selon une couche de masque. Les limites de chaque pays se trouvent dans Données par pays.',
        'project' => 'Avant de mesurer des surfaces ou des distances, reprojetez dans un système métrique, par exemple la zone UTM de votre territoire.',
        'cite' => 'Lorsque vous publiez des cartes ou des rapports, citez la source comme indiqué dans « Attribution ».',
    ],
    'faq_title' => 'Questions fréquentes',
    'faq' => [
        'free_q' => 'Les données « :title » sont-elles gratuites ?',
        'free_a' => 'Oui. Elles sont produites par :source et diffusées sous la licence :licence. Vous pouvez les télécharger sans inscription et sans payer.',
        'commercial_q' => 'Puis-je utiliser ces données dans un travail commercial ?',
        'commercial_a' => 'C’est la licence (:licence) qui définit ce qui est permis. Les licences ouvertes les plus courantes de ce catalogue (CC BY 4.0, ODbL et domaine public) autorisent l’usage commercial avec attribution, mais vérifiez toujours les conditions via le lien de la licence, dans la fiche de cette page.',
        'crs_q' => 'Dans quel système de coordonnées sont les fichiers ?',
        'crs_a' => 'En :crs. Ils s’ouvrent directement dans QGIS, ArcGIS et Google Earth Engine. Pour calculer des surfaces, reprojetez dans un système métrique.',
        'resolution_a' => 'La résolution spatiale est de :resolution.',
        'custom_q' => 'Besoin de ces données uniquement pour votre zone, ou d’une carte prête à l’emploi ?',
        'custom_a' => 'GEOFLORESTA prépare des extraits par pays, province, district ou polygone fourni, avec carte et rapport, pour des clients de tous les pays, sur place ou à distance.',
    ],
    'more_title' => 'Voir aussi',
    'more_countries' => 'Données ouvertes par pays',
    'more_learn' => 'Tutoriels gratuits de SIG et de télédétection',
];
