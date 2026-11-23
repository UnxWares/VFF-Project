<?php

/**
 * Corrections SOURCÉES des OUVERTURES de lignes voyageurs encore ouvertes, restées à la précision de l'année
 * (infobox « mise en service », Wikidata P1619, OpenHistoricalMap) ou à un 1er janvier : le jour est lu dans la
 * chronologie ou la liste des « mises en service » de l'article de la ligne, à la révision citée en source
 * (chantier « sections-dates » du 23/11/2026, lecture seule de la base, API MediaWiki avec le User-Agent du
 * projet, une requête toutes les 5 secondes). Quand l'article donne la section, la portion est celle de la source.
 *
 * Même format que line_event_corrections.php (cf. son en-tête) ; appliqué par vff:apply-event-corrections.
 * Les événements d'année de l'import restent en base : un événement manuel au jour est le plus fiable pour le
 * moteur. Ni électrifications ni fermetures ici. Une « inauguration » citée par l'article est datée comme telle
 * (le motif le dit) quand l'article ne donne pas d'autre date de mise en service.
 *
 * Désaccords entre sources, laissés non tranchés (voir le rapport sections-dates.md) : 242000 Compiègne - Noyon
 * (20/02/1849 dans l'article contre 26/02/1849 importé), 579000 (15/04/1867 pour toute la ligne en introduction
 * contre 31/05 et 16/10/1867 par section dans le corps), 316000 (28/06/1857 importé contre 1er septembre 1857
 * dans la bibliographie), 441000 (27 juin 1864 : inauguration de la gare de Saint-Malo, pas relevée).
 *
 * Pas de tiret long dans les textes (consigne éditoriale) : les tirets des citations sont rendus par « - ».
 *
 * @return list<array<string, mixed>>
 */
return [

    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1849-07-05', 'precision' => 'day',
        'from' => 'Paris-Est', 'to' => 'Meaux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '5 juillet 1849 : de Paris à Meaux ;',
        'reason' => 'première section de la ligne de Paris à Strasbourg',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1849-08-26', 'precision' => 'day',
        'from' => 'Meaux', 'to' => 'Épernay',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '26 août 1849 : de Meaux à Épernay ;',
        'reason' => 'section de Meaux à Épernay',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1849-11-10', 'precision' => 'day',
        'from' => 'Épernay', 'to' => 'Châlons-en-Champagne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '10 novembre 1849 : d\'Épernay à Châlons-sur-Marne ;',
        'reason' => 'section d\'Épernay à Châlons-sur-Marne (Châlons-en-Champagne)',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1850-07-10', 'precision' => 'day',
        'from' => 'Frouard', 'to' => 'Nancy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '10 juillet 1850 : de Nancy à Frouard (et embranchement vers Metz) ;',
        'reason' => 'section de Nancy à Frouard (avec embranchement vers Metz)',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1850-09-05', 'precision' => 'day',
        'from' => 'Châlons-en-Champagne', 'to' => 'Vitry-le-François',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '5 septembre 1850 : de Châlons-sur-Marne à Vitry-le-François ;',
        'reason' => 'section de Châlons-sur-Marne à Vitry-le-François',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1851-05-27', 'precision' => 'day',
        'from' => 'Vitry-le-François', 'to' => 'Bar-le-Duc',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '27 mai 1851 : de Vitry-le-François à Bar-le-Duc ;',
        'reason' => 'section de Vitry-le-François à Bar-le-Duc',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1851-05-29', 'precision' => 'day',
        'from' => 'Strasbourg', 'to' => 'Sarrebourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '29 mai 1851 : de Strasbourg à Sarrebourg ;',
        'reason' => 'section de Strasbourg à Sarrebourg',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1851-11-15', 'precision' => 'day',
        'from' => 'Bar-le-Duc', 'to' => 'Commercy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '15 novembre 1851 : de Bar-le-Duc à Commercy ;',
        'reason' => 'section de Bar-le-Duc à Commercy',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1852-06-19', 'precision' => 'day',
        'from' => 'Commercy', 'to' => 'Frouard',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '19 juin 1852 : de Commercy à Frouard ;',
        'reason' => 'section de Commercy à Frouard',
    ],
    [
        'line' => '070000', 'action' => 'add', 'type' => 'opening', 'date' => '1852-08-12', 'precision' => 'day',
        'from' => 'Nancy', 'to' => 'Sarrebourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=239157019',
        'quote' => '12 août 1852 : de Nancy à Sarrebourg.',
        'reason' => 'dernière section, de Nancy à Sarrebourg : la ligne est ouverte de bout en bout',
    ],

    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1847-10-18', 'precision' => 'day',
        'from' => 'Barbentane - Rognonas', 'to' => 'Saint-Chamas',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 18 octobre 1847, de Barbentane - Rognonas à Saint-Chamas ;',
        'reason' => 'section de Marseille à Avignon : Barbentane - Rognonas à Saint-Chamas',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1847-11-01', 'precision' => 'day',
        'from' => 'Saint-Chamas', 'to' => 'Pas-des-Lanciers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 1 novembre 1847, de Saint-Chamas à Pas-des-Lanciers ;',
        'reason' => 'section de Marseille à Avignon : Saint-Chamas à Pas-des-Lanciers',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1849-03-05', 'precision' => 'day',
        'from' => 'Avignon', 'to' => 'Pas-des-Lanciers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 5 mars 1849, d\'Avignon à Pas-des-Lanciers (avec la mise en service du viaduc sur la Durance).',
        'reason' => 'section d\'Avignon à Pas-des-Lanciers, avec le viaduc de la Durance',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1851-06-22', 'precision' => 'day',
        'from' => 'Tonnerre', 'to' => 'Dijon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'L\'ouverture au public de la section Dijon - Chalon-sur-Saône eut lieu le 1 septembre 1849 sans solennité. L\'inauguration de la section de ligne de Tonnerre à Dijon eut lieu le 1 juin 1851 et l\'ouverture au public le 22 juin 1851.',
        'reason' => 'ouverture au public de Tonnerre à Dijon (inauguration du 1er juin 1851)',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1854-06-29', 'precision' => 'day',
        'from' => 'Avignon', 'to' => 'Valence',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 29 juin 1854 d\'Avignon à Orange et d\'Orange à Valence ;',
        'reason' => 'section d\'Avignon à Valence par Orange',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-04-16', 'precision' => 'day',
        'from' => 'Valence', 'to' => 'Lyon-Guillotière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 16 avril 1855 de Valence à Lyon-Guillotière ;',
        'reason' => 'section de Valence à Lyon-Guillotière',
    ],
    [
        'line' => '830000', 'action' => 'add', 'type' => 'opening', 'date' => '1856-10-10', 'precision' => 'day',
        'from' => 'Lyon-Guillotière', 'to' => 'Lyon-Perrache',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
        'quote' => 'le 10 octobre 1856 de Lyon-Guillotière à Lyon-Perrache.',
        'reason' => 'raccordement de Lyon-Guillotière à Lyon-Perrache',
    ],

    [
        'line' => '001000', 'action' => 'add', 'type' => 'opening', 'date' => '1856-07-07', 'precision' => 'day',
        'from' => 'Noisy-le-Sec', 'to' => 'Nogent-sur-Marne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Mulhouse-Ville&oldid=235342496',
        'quote' => 'Le tronçon est inauguré le 7 juillet 1856 : huit allers-retours relient Paris à Nogent les jours ouvrables, quinze les dimanches et jours fériés, avec l\'importante fréquentation des Parisiens flânant dans la vallée de la Marne.',
        'reason' => 'première section de la ligne de Paris à Mulhouse, inaugurée avec service de huit allers-retours quotidiens',
    ],

    [
        'line' => '530000', 'action' => 'add', 'type' => 'opening', 'date' => '1866-12-30', 'precision' => 'day',
        'from' => 'Nantes', 'to' => 'La Roche-sur-Yon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nantes-Orl%C3%A9ans_%C3%A0_Saintes&oldid=240846237',
        'quote' => 'La Compagnie d\'Orléans inaugure le tronçon Nantes-La Roche-sur-Yon le 30 décembre 1866, dans le cadre d\'un projet ambitieux visant à relier Nantes à Limoges via Chantonnay et Niort.',
        'reason' => 'section de Nantes à La Roche-sur-Yon (inauguration)',
    ],
    [
        'line' => '530000', 'action' => 'add', 'type' => 'opening', 'date' => '1871-03-14', 'precision' => 'day',
        'from' => 'La Roche-sur-Yon', 'to' => 'La Rochelle',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nantes-Orl%C3%A9ans_%C3%A0_Saintes&oldid=240846237',
        'quote' => 'La Compagnie des Charentes prend le relais, ouvrant successivement les sections Rochefort-Saintes le 15 avril 1867, La Roche-sur-Yon-La Rochelle le 14 mars 1871, et enfin La Rochelle-Rochefort le 29 décembre 1873.',
        'reason' => 'section de La Roche-sur-Yon à La Rochelle',
    ],
    [
        'line' => '530000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-12-29', 'precision' => 'day',
        'from' => 'La Rochelle', 'to' => 'Rochefort',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nantes-Orl%C3%A9ans_%C3%A0_Saintes&oldid=240846237',
        'quote' => 'La Compagnie des Charentes prend le relais, ouvrant successivement les sections Rochefort-Saintes le 15 avril 1867, La Roche-sur-Yon-La Rochelle le 14 mars 1871, et enfin La Rochelle-Rochefort le 29 décembre 1873.',
        'reason' => 'section de La Rochelle à Rochefort',
    ],
    [
        'line' => '530000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-04-15', 'precision' => 'day',
        'from' => 'Rochefort', 'to' => 'Saintes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nantes-Orl%C3%A9ans_%C3%A0_Saintes&oldid=240846237',
        'quote' => 'La Compagnie des Charentes prend le relais, ouvrant successivement les sections Rochefort-Saintes le 15 avril 1867, La Roche-sur-Yon-La Rochelle le 14 mars 1871, et enfin La Rochelle-Rochefort le 29 décembre 1873.',
        'reason' => 'section de Rochefort à Saintes',
    ],

    [
        'line' => '415000', 'action' => 'add', 'type' => 'opening', 'date' => '1860-05-01', 'precision' => 'day',
        'from' => 'Lison', 'to' => 'Saint-Lô',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lison_%C3%A0_Lamballe&oldid=240889108',
        'quote' => 'La liaison de Lison à Saint-Lô est ouverte à l’exploitation le 1 mai 1860. À cette date, les stations d\'Airel et de Pont-Hébert ont leurs installations définitives achevées, mais celles de La Meauffe ne seront opérationnelles que dans les jours suivants.',
        'reason' => 'section de Lison à Saint-Lô',
    ],

    [
        'line' => '229000', 'action' => 'add', 'type' => 'opening', 'date' => '1860-06-04', 'precision' => 'day',
        'from' => 'La Plaine', 'to' => 'Sevran',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Plaine_%C3%A0_Hirson_et_Anor_(fronti%C3%A8re)&oldid=241251469',
        'quote' => 'Elle ouvre la ligne par sections. Le 4 juin 1860, elle met en service la voie de la gare de Paris-Nord à la gare de Sevran puis, le 31 août 1861, de Sevran à Villers-Cotterêts et enfin, le 2 février 1862, jusqu\'à Soissons.',
        'reason' => 'première section, du côté de Paris-Nord à Sevran',
    ],
    [
        'line' => '229000', 'action' => 'add', 'type' => 'opening', 'date' => '1861-08-31', 'precision' => 'day',
        'from' => 'Sevran', 'to' => 'Villers-Cotterêts',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Plaine_%C3%A0_Hirson_et_Anor_(fronti%C3%A8re)&oldid=241251469',
        'quote' => 'Elle ouvre la ligne par sections. Le 4 juin 1860, elle met en service la voie de la gare de Paris-Nord à la gare de Sevran puis, le 31 août 1861, de Sevran à Villers-Cotterêts et enfin, le 2 février 1862, jusqu\'à Soissons.',
        'reason' => 'section de Sevran à Villers-Cotterêts',
    ],
    [
        'line' => '229000', 'action' => 'add', 'type' => 'opening', 'date' => '1862-02-02', 'precision' => 'day',
        'from' => 'Villers-Cotterêts', 'to' => 'Soissons',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Plaine_%C3%A0_Hirson_et_Anor_(fronti%C3%A8re)&oldid=241251469',
        'quote' => 'Elle ouvre la ligne par sections. Le 4 juin 1860, elle met en service la voie de la gare de Paris-Nord à la gare de Sevran puis, le 31 août 1861, de Sevran à Villers-Cotterêts et enfin, le 2 février 1862, jusqu\'à Soissons.',
        'reason' => 'section de Villers-Cotterêts à Soissons',
    ],

    [
        'line' => '242000', 'action' => 'add', 'type' => 'opening', 'date' => '1847-10-20', 'precision' => 'day',
        'from' => 'Creil', 'to' => 'Compiègne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Creil_%C3%A0_Jeumont&oldid=236310314',
        'quote' => 'Le 20 octobre 1847, de Creil à Compiègne via Pont-Sainte-Maxence',
        'reason' => 'section de Creil à Compiègne par Pont-Sainte-Maxence',
    ],
    [
        'line' => '242000', 'action' => 'add', 'type' => 'opening', 'date' => '1849-02-20', 'precision' => 'day',
        'from' => 'Compiègne', 'to' => 'Noyon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Creil_%C3%A0_Jeumont&oldid=236310314',
        'quote' => 'Le 20 février 1849, de Compiègne à Noyon',
        'reason' => 'section de Compiègne à Noyon',
    ],
    [
        'line' => '242000', 'action' => 'add', 'type' => 'opening', 'date' => '1850-01-01', 'precision' => 'day',
        'from' => 'Chauny', 'to' => 'Tergnier',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Creil_%C3%A0_Jeumont&oldid=236310314',
        'quote' => 'Le 1 janvier 1850, de Chauny à Tergnier',
        'reason' => 'section de Chauny à Tergnier, effectivement ouverte un 1er janvier',
    ],
    [
        'line' => '242000', 'action' => 'add', 'type' => 'opening', 'date' => '1850-05-23', 'precision' => 'day',
        'from' => 'Tergnier', 'to' => 'Saint-Quentin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Creil_%C3%A0_Jeumont&oldid=236310314',
        'quote' => 'Le 23 mai 1850, de Tergnier à Saint-Quentin',
        'reason' => 'section de Tergnier à Saint-Quentin',
    ],
    [
        'line' => '242000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-08-11', 'precision' => 'day',
        'from' => 'Saint-Quentin', 'to' => 'Jeumont',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Creil_%C3%A0_Jeumont&oldid=236310314',
        'quote' => 'Le 11 août 1855, de Saint-Quentin à Jeumont et Erquelinnes (Belgique)',
        'reason' => 'section de Saint-Quentin à Jeumont et Erquelinnes',
    ],

    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1858-12-14', 'precision' => 'day',
        'from' => 'Mohon', 'to' => 'Donchery',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Mohon à Donchery, le 14 décembre 1858 ;',
        'reason' => 'section de Mohon à Donchery',
    ],
    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1859-05-17', 'precision' => 'day',
        'from' => 'Donchery', 'to' => 'Sedan',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Donchery à Sedan, le 17 mai 1859 ;',
        'reason' => 'section de Donchery à Sedan',
    ],
    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1861-09-23', 'precision' => 'day',
        'from' => 'Sedan', 'to' => 'Carignan',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Sedan à Carignan, le 23 septembre 1861 ;',
        'reason' => 'section de Sedan à Carignan',
    ],
    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1862-04-23', 'precision' => 'day',
        'from' => 'Carignan', 'to' => 'Montmédy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Carignan à Montmédy, le 23 avril 1862 ;',
        'reason' => 'section de Carignan à Montmédy',
    ],
    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1862-08-01', 'precision' => 'day',
        'from' => 'Montmédy', 'to' => 'Pierrepont',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Montmédy à Pierrepont, le 1 août 1862 ;',
        'reason' => 'section de Montmédy à Pierrepont',
    ],
    [
        'line' => '204000', 'action' => 'add', 'type' => 'opening', 'date' => '1863-04-25', 'precision' => 'day',
        'from' => 'Pierrepont', 'to' => 'Thionville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mohon_%C3%A0_Thionville&oldid=235494653',
        'quote' => 'De Pierrepont à Thionville, 25 avril 1863.',
        'reason' => 'dernière section, de Pierrepont à Thionville',
    ],

    [
        'line' => '042000', 'action' => 'add', 'type' => 'opening', 'date' => '1857-06-24', 'precision' => 'day',
        'from' => 'Blainville-Damelevières', 'to' => 'Épinal',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Blainville_-_Damelevi%C3%A8res_%C3%A0_Lure&oldid=240009224',
        'quote' => 'de Blainville à Épinal, le 24 juin 1857 ;',
        'reason' => 'section de Blainville à Épinal',
    ],
    [
        'line' => '042000', 'action' => 'add', 'type' => 'opening', 'date' => '1863-09-24', 'precision' => 'day',
        'from' => 'Épinal', 'to' => 'Aillevillers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Blainville_-_Damelevi%C3%A8res_%C3%A0_Lure&oldid=240009224',
        'quote' => 'd\'Épinal à Aillevillers, le 24 septembre 1863, terminant ainsi la liaison Vesoul - Nancy ;',
        'reason' => 'section d\'Épinal à Aillevillers',
    ],
    [
        'line' => '042000', 'action' => 'add', 'type' => 'opening', 'date' => '1878-04-25', 'precision' => 'day',
        'from' => 'Aillevillers', 'to' => 'Lure',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Blainville_-_Damelevi%C3%A8res_%C3%A0_Lure&oldid=240009224',
        'quote' => 'd\'Aillevillers à Lure, le 25 avril 1878, ligne devenu nécessaire après l\'annexion de l\'Alsace-Lorraine.',
        'reason' => 'section d\'Aillevillers à Lure',
    ],

    [
        'line' => '897000', 'action' => 'add', 'type' => 'opening', 'date' => '1866-07-05', 'precision' => 'day',
        'from' => 'Aix-les-Bains-Le Revard', 'to' => 'Annecy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Aix-les-Bains-Le_Revard_%C3%A0_Annemasse&oldid=240496723',
        'quote' => 'd\'Aix-les-Bains à Annecy le 5 juillet 1866,',
        'reason' => 'section d\'Aix-les-Bains à Annecy',
    ],
    [
        'line' => '897000', 'action' => 'add', 'type' => 'opening', 'date' => '1883-07-10', 'precision' => 'day',
        'from' => 'La Roche-sur-Foron', 'to' => 'Annemasse',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Aix-les-Bains-Le_Revard_%C3%A0_Annemasse&oldid=240496723',
        'quote' => 'de La Roche-sur-Foron à Annemasse le 10 juillet 1883,',
        'reason' => 'section de La Roche-sur-Foron à Annemasse',
    ],

    [
        'line' => '899000', 'action' => 'add', 'type' => 'opening', 'date' => '1879-10-27', 'precision' => 'day',
        'from' => 'Saint-Pierre-d\'Albigny', 'to' => 'Albertville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Pierre-d\'Albigny_%C3%A0_Bourg-Saint-Maurice&oldid=238227992',
        'quote' => '27 octobre 1879 : de Saint-Pierre-d\'Albigny à Albertville',
        'reason' => 'section de Saint-Pierre-d\'Albigny à Albertville',
    ],
    [
        'line' => '899000', 'action' => 'add', 'type' => 'opening', 'date' => '1893-06-01', 'precision' => 'day',
        'from' => 'Albertville', 'to' => 'Moûtiers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Pierre-d\'Albigny_%C3%A0_Bourg-Saint-Maurice&oldid=238227992',
        'quote' => '1 juin 1893 : d\'Albertville à Moûtiers',
        'reason' => 'section d\'Albertville à Moûtiers',
    ],
    [
        'line' => '899000', 'action' => 'add', 'type' => 'opening', 'date' => '1913-11-20', 'precision' => 'day',
        'from' => 'Moûtiers', 'to' => 'Bourg-Saint-Maurice',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Pierre-d\'Albigny_%C3%A0_Bourg-Saint-Maurice&oldid=238227992',
        'quote' => '20 novembre 1913 : de Moûtiers à Bourg-Saint-Maurice',
        'reason' => 'dernière section, de Moûtiers à Bourg-Saint-Maurice',
    ],

    [
        'line' => '925000', 'action' => 'add', 'type' => 'opening', 'date' => '1868-12-29', 'precision' => 'day',
        'from' => 'Avignon', 'to' => 'Cavaillon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Avignon_%C3%A0_Miramas&oldid=240727914',
        'quote' => 'd’Avignon à Cavaillon, le 29 décembre 1868 ;',
        'reason' => 'section d\'Avignon à Cavaillon',
    ],
    [
        'line' => '925000', 'action' => 'add', 'type' => 'opening', 'date' => '1872-11-25', 'precision' => 'day',
        'from' => 'Cavaillon', 'to' => 'Cheval-Blanc',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Avignon_%C3%A0_Miramas&oldid=240727914',
        'quote' => 'de Cavaillon à Cheval-Blanc, le 25 novembre 1872 ;',
        'reason' => 'section de Cavaillon à Cheval-Blanc',
    ],
    [
        'line' => '925000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-05-28', 'precision' => 'day',
        'from' => 'Cheval-Blanc', 'to' => 'Miramas',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Avignon_%C3%A0_Miramas&oldid=240727914',
        'quote' => 'de Cheval-Blanc à Miramas, le 28 mai 1873.',
        'reason' => 'dernière section, de Cheval-Blanc à Miramas',
    ],

    [
        'line' => '506000', 'action' => 'add', 'type' => 'opening', 'date' => '1898-01-02', 'precision' => 'day',
        'from' => 'Thorigné', 'to' => 'Montmirail - Melleray',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Thorign%C3%A9_%C3%A0_Courtalain_-_Saint-Pellerin&oldid=235580556',
        'quote' => 'de Thorigné à Montmirail - Melleray, le 2 janvier 1898,',
        'reason' => 'section de Thorigné à Montmirail - Melleray, ouverte un 2 janvier',
    ],
    [
        'line' => '506000', 'action' => 'add', 'type' => 'opening', 'date' => '1900-07-12', 'precision' => 'day',
        'from' => 'Montmirail - Melleray', 'to' => 'Courtalain - Saint-Pellerin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Thorign%C3%A9_%C3%A0_Courtalain_-_Saint-Pellerin&oldid=235580556',
        'quote' => 'de Montmirail - Melleray à Courtalain - Saint-Pellerin, le 12 juillet 1900.',
        'reason' => 'dernière section, de Montmirail - Melleray à Courtalain - Saint-Pellerin',
    ],

    [
        'line' => '140000', 'action' => 'add', 'type' => 'opening', 'date' => '1851-07-24', 'precision' => 'day',
        'from' => 'Rémilly', 'to' => 'Metz-Ville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Metz-Ville&oldid=235298897',
        'quote' => 'Rémilly à Metz : 24 juillet 1851, par la Compagnie du chemin de fer de Paris à Strasbourg ;',
        'reason' => 'section de Rémilly à Metz',
    ],
    [
        'line' => '140000', 'action' => 'add', 'type' => 'opening', 'date' => '1872-11-01', 'precision' => 'day',
        'from' => 'Sarraltroff', 'to' => 'Berthelming',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Metz-Ville&oldid=235298897',
        'quote' => 'Sarraltroff à Berthelming : 1 novembre 1872, par la Société anonyme des chemins de fer de Lorraine ;',
        'reason' => 'section de Sarraltroff à Berthelming',
    ],
    [
        'line' => '140000', 'action' => 'add', 'type' => 'opening', 'date' => '1877-10-15', 'precision' => 'day',
        'from' => 'Réding', 'to' => 'Sarraltroff',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Metz-Ville&oldid=235298897',
        'quote' => 'Réding à Sarraltroff : 15 octobre 1877, par la Direction générale impériale des chemins de fer d\'Alsace-Lorraine (EL) ;',
        'reason' => 'section de Réding à Sarraltroff',
    ],
    [
        'line' => '140000', 'action' => 'add', 'type' => 'opening', 'date' => '1877-12-10', 'precision' => 'day',
        'from' => 'Berthelming', 'to' => 'Rémilly',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Metz-Ville&oldid=235298897',
        'quote' => 'Berthelming à Rémilly et doublement de la voie de Sarraltroff à Berthelming : 10 décembre 1877, par l\'EL.',
        'reason' => 'section de Berthelming à Rémilly, dernière section',
    ],

    [
        'line' => '130000', 'action' => 'add', 'type' => 'opening', 'date' => '1839-09-01', 'precision' => 'day',
        'from' => 'Lutterbach', 'to' => 'Thann',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lutterbach_%C3%A0_Kruth&oldid=235373537',
        'quote' => 'Elle est la troisième ligne de chemin de fer de France à avoir été ouverte au trafic passager. Le tronçon de Lutterbach à Thann, partie de la ligne originelle de Mulhouse à Thann, est inauguré le 1 septembre 1839 et sera financé par les industriels de la région.',
        'reason' => 'section de Lutterbach à Thann (inauguration), première ligne ouverte aux voyageurs hors Saint-Étienne - Lyon',
    ],

    [
        'line' => '896000', 'action' => 'add', 'type' => 'opening', 'date' => '1901-07-25', 'precision' => 'day',
        'from' => 'Le Fayet', 'to' => 'Chamonix',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Gervais-les-Bains-Le_Fayet_%C3%A0_Vallorcine_(fronti%C3%A8re)&oldid=240212251',
        'quote' => 'la section Le Fayet - Chamonix est inaugurée le 12 juillet 1901. Elle ouvre au public le 25 juillet ; un grand succès commercial s\'ensuit.',
        'reason' => 'section du Fayet à Chamonix : inaugurée le 12 juillet, ouverte au public le 25 juillet 1901',
    ],

    [
        'line' => '041000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-12-31', 'precision' => 'day',
        'from' => 'Barisey-la-Côte', 'to' => 'Favières',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Barisey-la-C%C3%B4te_%C3%A0_Frenelle-la-Grande_-_Puzieux&oldid=207096707',
        'quote' => 'La section de Barisey-la-Côte à Favières a été ouverte à l\'exploitation le 31 décembre 1881 par la Compagnie des chemins de fer de l\'Est qui en assurait provisoirement l\'exploitation dans l\'attente d\'une éventuelle concession.',
        'reason' => 'section de Barisey-la-Côte à Favières',
    ],

    [
        'line' => '593000', 'action' => 'add', 'type' => 'opening', 'date' => '1869-10-18', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Vierzon_%C3%A0_Saint-Pierre-des-Corps&oldid=236330049',
        'quote' => 'Construite en 1860 et mise en service le 18 octobre 1869, la ligne a d\'abord été à voie unique avant d\'être mise à double voie entre 1868 et 1907, date de construction du pont métallique de Montrichard à double voie.',
        'reason' => 'mise en service de la ligne de Vierzon à Saint-Pierre-des-Corps',
    ],

    [
        'line' => '698000', 'action' => 'add', 'type' => 'opening', 'date' => '1906-07-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Ch%C3%A2tre_%C3%A0_Gu%C3%A9ret&oldid=237915661',
        'quote' => 'Elle a été ouverte à l\'exploitation, par le PO ainsi que ses dix stations intermédiaires et ses deux haltes, le 1 juillet 1906.',
        'reason' => 'ouverture à l\'exploitation de la ligne, par le PO',
    ],

    [
        'line' => '746000', 'action' => 'add', 'type' => 'opening', 'date' => '1897-06-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Corbeil-Essonnes_%C3%A0_Montereau&oldid=241117450',
        'quote' => 'Elle a été mise en service le 1 juin 1897 et inaugurée le 27 juin 1897.',
        'reason' => 'mise en service de la ligne (inaugurée le 27 juin 1897)',
    ],

    [
        'line' => '466000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-12-28', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Ch%C3%A2teaubriant_%C3%A0_Rennes&oldid=234893924',
        'quote' => 'Elle a été ouverte à l\'exploitation par l\'État le 28 décembre 1881.',
        'reason' => 'ouverture à l\'exploitation par l\'État',
    ],

    [
        'line' => '886000', 'action' => 'add', 'type' => 'opening', 'date' => '1866-09-01', 'precision' => 'day',
        'from' => 'Sathonay', 'to' => 'Bourg-en-Bresse',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lyon-Saint-Clair_%C3%A0_Bourg-en-Bresse&oldid=236080081',
        'quote' => 'Le 1er septembre 1866, la Compagnie inaugure la section de Sathonay à Bourg-en-Bresse.',
        'reason' => 'section de Sathonay à Bourg-en-Bresse (inauguration)',
    ],

    [
        'line' => '234000', 'action' => 'add', 'type' => 'opening', 'date' => '1882-05-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d\'Anizy-Pinon_%C3%A0_Chauny&oldid=235337677',
        'quote' => 'Elle a été finalement mise en service le 1 mai 1882. À cette date, elle est parcourue par 4 trains par jour dans chaque sens (2 le matin et 2 le soir)',
        'reason' => 'mise en service de la ligne',
    ],

    [
        'line' => '427000', 'action' => 'add', 'type' => 'opening', 'date' => '1880-08-30', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Hutte_-_Coulombiers_%C3%A0_Mamers&oldid=238880846',
        'quote' => 'La ligne est mise en service entre le 30 août 1880 par le ministère des Travaux Publics qui l\'exploita en régie directe, la Compagnie des chemins de fer de l\'Ouest n\'ayant pas souhaiter financer les travaux de la ligne, considérant, limitée par les investissements déjà importants',
        'reason' => 'mise en service de la ligne par l\'administration des Travaux publics',
    ],

    [
        'line' => '834000', 'action' => 'add', 'type' => 'opening_freight', 'date' => '2017-12-10', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Contournement_ferroviaire_de_N%C3%AEmes_et_de_Montpellier&oldid=240117712',
        'quote' => 'Elle est mise en service le 10 décembre 2017 pour le trafic de fret et l\'est le 7 juillet 2018 pour les voyageurs (notamment TGV).',
        'reason' => 'mise en service pour le fret du contournement de Nîmes et de Montpellier',
    ],
    [
        'line' => '834000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '2018-07-07', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Contournement_ferroviaire_de_N%C3%AEmes_et_de_Montpellier&oldid=240117712',
        'quote' => 'Elle est mise en service le 10 décembre 2017 pour le trafic de fret et l\'est le 7 juillet 2018 pour les voyageurs (notamment TGV).',
        'reason' => 'mise en service aux voyageurs du contournement de Nîmes et de Montpellier',
    ],

    [
        'line' => '082000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1857-08-31', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Reims_%C3%A0_Laon&oldid=229229574',
        'quote' => 'La Compagnie des Ardennes met en service les 52 kilomètres en 1857, le 31 août pour les voyageurs et le 15 octobre pour les marchandises.',
        'reason' => 'mise en service de la ligne de Reims à Laon par la Compagnie des Ardennes, voyageurs',
    ],
    [
        'line' => '082000', 'action' => 'add', 'type' => 'opening_freight', 'date' => '1857-10-15', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Reims_%C3%A0_Laon&oldid=229229574',
        'quote' => 'La Compagnie des Ardennes met en service les 52 kilomètres en 1857, le 31 août pour les voyageurs et le 15 octobre pour les marchandises.',
        'reason' => 'mise en service de la ligne de Reims à Laon par la Compagnie des Ardennes, marchandises',
    ],

    [
        'line' => '579000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-05-31', 'precision' => 'day',
        'from' => 'Beillant', 'to' => 'Cognac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Beillant_%C3%A0_Angoul%C3%AAme&oldid=236391733',
        'quote' => 'Le premier train circule entre Rochefort et Saintes le 15 avril 1867, entre Saintes et Cognac le 31 mai, et entre Cognac et Angoulême le 16 octobre.',
        'reason' => 'section de Beillant (Saintes) à Cognac',
    ],
    [
        'line' => '579000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-10-16', 'precision' => 'day',
        'from' => 'Cognac', 'to' => 'Angoulême',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Beillant_%C3%A0_Angoul%C3%AAme&oldid=236391733',
        'quote' => 'Le premier train circule entre Rochefort et Saintes le 15 avril 1867, entre Saintes et Cognac le 31 mai, et entre Cognac et Angoulême le 16 octobre.',
        'reason' => 'section de Cognac à Angoulême',
    ],
];
