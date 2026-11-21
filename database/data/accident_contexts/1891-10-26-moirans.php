<?php

// Contexte sourcé du déraillement du train de voyageurs n° 497 entre Voiron et Moirans, en octobre 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-10-26-moirans',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1891, 26 octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1891',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (3 morts et 27 blessés), d\'après Le Petit Journal du 28 octobre 1891.',
        ],
        'vdr-moirans' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1891, L\'accident de Moirans',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1891-laccident-de-moirans/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit les dépêches de Moirans et de Grenoble du 27 octobre 1891 publiées par Le Petit Journal du 28 octobre 1891.',
        ],
        'petit-journal-1891-10-28' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 28 octobre 1891',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1891-10-28',
            'locator' => 'p. 1 et 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k611028f/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train de voyageurs n° 497, attendu à Grenoble à 16 h 38 et tiré par deux locomotives, a passé Voiron à l\'heure et s\'est engagé à 80 km/h dans la descente vers Moirans, une pente que le correspondant du Petit Journal jugeait excessive. À l\'endroit du déraillement, la voie décrit une courbe très prononcée sur un remblai de dix mètres.',
                'en' => 'Passenger train no. 497, due at Grenoble at 4.38 pm and hauled by two engines, passed Voiron on time and started down towards Moirans at 80 km/h, on a gradient that Le Petit Journal\'s correspondent considered excessive. Where it derailed, the line makes a very sharp curve on a ten-metre embankment.',
                'de' => 'Der Personenzug Nr. 497, in Grenoble um 16.38 Uhr erwartet und von zwei Lokomotiven gezogen, passierte Voiron pünktlich und fuhr mit 80 km/h in das Gefälle nach Moirans ein, das der Korrespondent des Petit Journal für übermäßig hielt. An der Unfallstelle beschreibt die Strecke auf einem zehn Meter hohen Damm eine sehr enge Kurve.',
                'sources' => ['vdr-moirans', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Entre Moirans et le cimetière de la commune, la seconde machine a déraillé ; la première a rompu ses attaches et a continué seule jusqu\'à la gare de Moirans. Huit des douze voitures se sont renversées sur le remblai, retenues par le frein au lieu de tomber au bas du talus.',
                'en' => 'Between Moirans and the town cemetery the second engine derailed; the first broke its couplings and ran on alone to Moirans station. Eight of the twelve carriages overturned on the embankment, held by the brake instead of falling to the foot of the slope.',
                'de' => 'Zwischen Moirans und dem Friedhof des Ortes entgleiste die zweite Lokomotive; die erste riss sich los und fuhr allein bis zum Bahnhof Moirans weiter. Acht der zwölf Wagen stürzten auf dem Damm um und wurden von der Bremse gehalten, statt die Böschung hinabzufallen.',
                'sources' => ['vdr-moirans', 'wp-liste-1890'],
            ],
            [
                'fr' => 'Le personnel de la gare, les ouvriers d\'une usine de soieries, les habitants et la gendarmerie de Moirans ont dégagé les victimes ; dix médecins de Grenoble et de Voiron sont venus. Les blessés ont été soignés à l\'hôpital de Moirans, chez le maire et dans des maisons particulières. Le mécanicien et le chauffeur avaient pu sauter de leur machine.',
                'en' => 'The station staff, workers from a silk mill, local people and the Moirans gendarmerie freed the victims; ten doctors came from Grenoble and Voiron. The injured were cared for at the Moirans hospital, at the mayor\'s house and in private homes. The driver and the fireman had managed to jump from their engine.',
                'de' => 'Bahnhofspersonal, Arbeiter einer Seidenfabrik, Einwohner und die Gendarmerie von Moirans bargen die Opfer; zehn Ärzte kamen aus Grenoble und Voiron. Die Verletzten wurden im Krankenhaus von Moirans, beim Bürgermeister und in Privathäusern versorgt. Lokführer und Heizer hatten von ihrer Maschine springen können.',
                'sources' => ['vdr-moirans'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le Petit Journal nomme deux voyageurs tués et une vingtaine de blessés. Les deux morts ont été inhumés le lendemain en présence du sous-préfet de Saint-Marcellin et du maire de Moirans.',
                'en' => 'Le Petit Journal names two passengers killed and about twenty injured. The two dead were buried the next day in the presence of the sub-prefect of Saint-Marcellin and the mayor of Moirans.',
                'de' => 'Le Petit Journal nennt zwei getötete Reisende und etwa zwanzig Verletzte. Die beiden Toten wurden am folgenden Tag in Anwesenheit des Unterpräfekten von Saint-Marcellin und des Bürgermeisters von Moirans beigesetzt.',
                'sources' => ['vdr-moirans'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon le Petit Journal, le déraillement était dû à un écartement de la voie, très probablement causé par un affaissement de terrain. Wikipédia parle d\'une mauvaise coordination du freinage entre les deux machines.',
                'en' => 'According to Le Petit Journal, the derailment was caused by the rails spreading, most probably because the ground had subsided. Wikipedia speaks of poor coordination of braking between the two engines.',
                'de' => 'Laut Le Petit Journal ging die Entgleisung auf eine Spurerweiterung zurück, höchstwahrscheinlich durch eine Bodensenkung verursacht. Wikipedia spricht von schlecht abgestimmtem Bremsen der beiden Lokomotiven.',
                'sources' => ['vdr-moirans', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le préfet de l\'Isère et les procureurs de Grenoble et de Saint-Marcellin sont restés sur place jusqu\'à 23 heures. L\'ingénieur en chef des mines chargé du contrôle du PLM et le président du Comité de l\'exploitation technique des chemins de fer, délégué par le ministre des Travaux publics, se sont rendus à Moirans.',
                'en' => 'The prefect of the Isère and the public prosecutors of Grenoble and Saint-Marcellin stayed at the scene until 11 pm. The chief mining engineer in charge of supervising the PLM and the chairman of the railway technical operations committee, sent by the Minister of Public Works, travelled to Moirans.',
                'de' => 'Der Präfekt des Departements Isère und die Staatsanwälte von Grenoble und Saint-Marcellin blieben bis 23 Uhr an der Unfallstelle. Der für die Aufsicht über die PLM zuständige Chefingenieur der Bergwerke und der Vorsitzende des Ausschusses für den technischen Eisenbahnbetrieb, vom Minister für öffentliche Arbeiten entsandt, reisten nach Moirans.',
                'sources' => ['vdr-moirans'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : deux morts dans la dépêche du lendemain, trois morts et vingt-sept blessés selon Wikipédia. La dépêche rappelle aussi qu\'en juillet 1889 une rencontre de trains en gare de Moirans avait fait cinq morts.',
                'en' => 'The toll differs: two dead in the next day\'s dispatch, three dead and twenty-seven injured according to Wikipedia. The dispatch also recalls that in July 1889 a collision at Moirans station had killed five people.',
                'de' => 'Die Opferzahl weicht ab: zwei Tote in der Depesche vom nächsten Tag, drei Tote und siebenundzwanzig Verletzte laut Wikipedia. Die Depesche erinnert zudem daran, dass im Juli 1889 ein Zusammenstoß im Bahnhof Moirans fünf Tote gefordert hatte.',
                'sources' => ['vdr-moirans', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
