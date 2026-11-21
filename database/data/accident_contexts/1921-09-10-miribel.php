<?php

// Contexte sourcé de l'accident des Échets (Miribel) du 10 septembre 1921 (recherche du 21/11/2026).
return [
    'slug' => '1921-09-10-miribel',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire des Échets',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_des_%C3%89chets&oldid=239207030',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé : contexte de guerre, enquête, procès de 1922, débats parlementaires.',
        ],
        'wp-de' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Les Échets',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://de.wikipedia.org/wiki/Eisenbahnunfall_von_Les_Échets',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Bilan de 38 morts ; s\'appuie sur Semmens, Katastrophen auf Schienen (1996), p. 62.',
        ],
        'wp-en-gare' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Les Échets station',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://en.wikipedia.org/wiki/Les_%C3%89chets_station',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Situe la gare au PK 20,727 de la ligne de Lyon à Bourg-en-Bresse ; 39 morts, plus de 60 blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 10 septembre 1921 vers 22 h 35, l\'express Strasbourg-Lyon de la Compagnie PLM arrive à la gare des Échets (commune de Miribel, Ain), sur la ligne de Lyon à Bourg-en-Bresse dite ligne des Dombes. La gare est située au point kilométrique 20,727.',
                'en' => 'On 10 September 1921 at about 22:35, the PLM Strasbourg-Lyon express arrived at Les Échets station (commune of Miribel, Ain), on the Lyon to Bourg-en-Bresse line, known as the Dombes line. The station is at kilometre point 20.727.',
                'de' => 'Am 10. September 1921 gegen 22.35 Uhr erreichte der Schnellzug Straßburg-Lyon der PLM den Bahnhof Les Échets (Gemeinde Miribel, Ain) an der Strecke Lyon-Bourg-en-Bresse, der Dombes-Strecke. Der Bahnhof liegt bei Streckenkilometer 20,727.',
                'sources' => ['wp-fr', 'wp-en-gare'],
            ],
            [
                'fr' => 'Pendant la Première Guerre mondiale, l\'une des deux voies de la ligne avait été déposée pour les besoins du front. Les voies d\'évitement des gares utilisaient des aiguillages de récupération au rayon très serré, qui limitaient la vitesse à 20 km/h. Les anciens rapports de 1919 signalaient déjà les dangers de la section à voie unique.',
                'en' => 'During the First World War, one of the line\'s two tracks had been lifted for the needs of the front. Station loops used salvaged points with a very tight radius, which limited speed to 20 km/h. Reports from 1919 had already pointed out the dangers of the single-track section.',
                'de' => 'Im Ersten Weltkrieg war eines der beiden Gleise für die Front abgebaut worden. In den Bahnhöfen kamen Überholgleise mit wiederverwendeten Weichen von sehr engem Radius zum Einsatz, die die Geschwindigkeit auf 20 km/h begrenzten. Berichte von 1919 hatten bereits auf die Gefahren des eingleisigen Abschnitts hingewiesen.',
                'sources' => ['wp-fr', 'wp-de'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a déraillé à l\'aiguillage en abordant la courbe à une vitesse très supérieure à la limite de 20 km/h. Plusieurs voitures se sont télescopées et certaines ont percuté la halle à marchandises de la gare. Trois des voitures à caisse en bois ont été détruites.',
                'en' => 'The train derailed at the points as it took the curve at a speed well above the 20 km/h limit. Several carriages telescoped and some struck the station\'s goods shed. Three of the wooden-bodied carriages were destroyed.',
                'de' => 'Der Zug entgleiste an der Weiche, als er die Kurve mit weit über der Grenze von 20 km/h liegender Geschwindigkeit befuhr. Mehrere Wagen schoben sich ineinander, einige prallten gegen den Güterschuppen des Bahnhofs. Drei der Wagen mit Holzaufbau wurden zerstört.',
                'sources' => ['wp-fr', 'wp-de'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait 39 morts, dont 18 militaires, et environ 60 blessés.',
                'en' => 'The accident killed 39 people, 18 of them soldiers, and injured about 60.',
                'de' => 'Bei dem Unfall starben 39 Menschen, darunter 18 Soldaten, und etwa 60 wurden verletzt.',
                'sources' => ['wp-fr', 'wp-en-gare'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les enquêteurs ont constaté que la lampe du signal de la gare était éteinte de nuit, ce qui a probablement empêché le mécanicien de voir l\'aiguillage à temps. L\'enregistrement de vitesse montrait que le train avait respecté les limites aux quatre gares précédentes.',
                'en' => 'Investigators found that the station\'s signal lamp was out at night, which probably prevented the driver from seeing the points in time. The speed recording showed that the train had kept within the limits at the four previous stations.',
                'de' => 'Die Ermittler stellten fest, dass die Signallampe des Bahnhofs nachts erloschen war, was dem Lokführer wahrscheinlich die rechtzeitige Sicht auf die Weiche verwehrte. Die Geschwindigkeitsaufzeichnung zeigte, dass der Zug an den vier vorigen Bahnhöfen die Grenzwerte eingehalten hatte.',
                'sources' => ['wp-fr'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le mécanicien a d\'abord été incarcéré puis libéré sur ordre du ministre en attendant son procès. Au tribunal de Trévoux, en 1922, il a été condamné à une amende symbolique de 50 francs avec sursis ; le ministère public a reconnu d\'importantes circonstances atténuantes et évoqué la responsabilité possible de la compagnie et de l\'administration. Le ministre n\'a pas fait appel.',
                'en' => 'The driver was first jailed, then released on the minister\'s orders pending trial. At the Trévoux court in 1922 he was given a symbolic suspended fine of 50 francs; the prosecution acknowledged major mitigating circumstances and raised the possible responsibility of the company and the administration. The minister did not appeal.',
                'de' => 'Der Lokführer wurde zunächst inhaftiert und dann auf Anordnung des Ministers bis zum Prozess freigelassen. Vor dem Gericht in Trévoux wurde er 1922 zu einer symbolischen Geldstrafe von 50 Franc auf Bewährung verurteilt; die Staatsanwaltschaft räumte erhebliche mildernde Umstände ein und verwies auf eine mögliche Verantwortung von Gesellschaft und Verwaltung. Der Minister legte keine Berufung ein.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Des débats parlementaires en 1921 et 1922 ont critiqué l\'organisation des secours de la PLM et le comportement de ses services de direction et de contrôle. Un décret du 14 avril 1922 a accordé la médaille d\'honneur des chemins de fer au chef de gare.',
                'en' => 'Parliamentary debates in 1921 and 1922 criticised the PLM\'s organisation of the rescue and the conduct of its management and inspection services. A decree of 14 April 1922 awarded the railway medal of honour to the station master.',
                'de' => 'Parlamentsdebatten 1921 und 1922 kritisierten die Organisation der Rettung durch die PLM und das Verhalten ihrer Leitungs- und Aufsichtsdienste. Ein Dekret vom 14. April 1922 verlieh dem Bahnhofsvorsteher die Ehrenmedaille der Eisenbahn.',
                'sources' => ['wp-fr', 'wp-en-gare'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : 39 morts et environ 60 blessés (Wikipédia français, Wikipedia anglais), 38 morts (Wikipedia allemand, d\'après Semmens). Le titre d\'un journal cité par la page anglaise annonçait près de 100 victimes dont 38 morts.',
                'en' => 'The toll varies: 39 dead and about 60 injured (French and English Wikipedia), 38 dead (German Wikipedia, after Semmens). The headline of a newspaper cited by the English page reported nearly 100 casualties including 38 dead.',
                'de' => 'Die Bilanz schwankt: 39 Tote und etwa 60 Verletzte (französische und englische Wikipedia), 38 Tote (deutsche Wikipedia nach Semmens). Die Schlagzeile einer auf der englischen Seite zitierten Zeitung nannte fast 100 Opfer, darunter 38 Tote.',
                'sources' => ['wp-fr', 'wp-en-gare', 'wp-de'],
            ],
        ],
    ],
];
