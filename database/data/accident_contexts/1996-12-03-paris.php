<?php

// Contexte sourcé de l'attentat de la station Port-Royal du RER B du 3 décembre 1996 (recherche du 21/11/2026).
return [
    'slug' => '1996-12-03-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Attentat du 3 décembre 1996 à la gare de Port-Royal du RER B',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Résumé, enquête, commémoration',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Attentat_du_3_d%C3%A9cembre_1996_%C3%A0_la_gare_de_Port-Royal_du_RER_B&oldid=240729767',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article sourcé (lu sur les 100 000 premiers caractères) : heure, engin, bilan, enquête non aboutie ; ne mentionne aucun procès.',
        ],
        'wp-en' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => '1996 Paris RER bombing',
            'publisher' => 'Wikipedia (English)',
            'author' => null,
            'published' => null,
            'locator' => 'Infobox, investigation',
            'url' => 'https://en.wikipedia.org/w/index.php?title=1996_Paris_RER_bombing&oldid=1383097829',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => "Court article : 4 morts, 91 blessés dans l'encadré, mais les titres de presse de 1996 qu'il cite donnent 2 morts et 85 blessés ; aucune condamnation mentionnée.",
        ],
        'an-1996-qg2039' => [
            'provider' => 'assemblee-nationale',
            'kind' => 'parliament',
            'reliability' => 'official',
            'title' => 'Terrorisme (question au Gouvernement n° 2039, 10e législature)',
            'publisher' => 'Assemblée nationale',
            'author' => null,
            'published' => '1996-12-04',
            'locator' => 'Question au Gouvernement n° 2039, réponse du Premier ministre',
            'url' => 'https://questions.assemblee-nationale.fr/dyn/10/questions/QANR5L10QG2039.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'PDF lu avec pdftotext, séance du lendemain de l\'attentat : deux morts annoncés, plan Vigipirate renforcé, renforts de plus de 1 800 militaires, information judiciaire confiée à trois juges, aucune revendication, similitudes avec les attentats de l\'été 1995.',
        ],
        'ina-port-royal' => [
            'provider' => 'ina',
            'kind' => 'video',
            'reliability' => 'press',
            'title' => 'Attaque de la station Port-Royal',
            'publisher' => 'Institut national de l\'audiovisuel',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://mediaclip.ina.fr/en/cac96068712-attack-on-port-royal-station.html',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Reportage d\'archive repéré, non lu (lien seul imposé par les droits).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 décembre 1996 à 18 h 02, une bombe explose à la station Port-Royal, à Paris, dans une rame MI 79 du RER B circulant en direction de Saint-Rémy-lès-Chevreuse.',
                'en' => 'On 3 December 1996 at 6.02 pm, a bomb exploded at Port-Royal station in Paris, in an MI 79 RER B train heading towards Saint-Rémy-lès-Chevreuse.',
                'de' => 'Am 3. Dezember 1996 um 18.02 Uhr explodierte im Bahnhof Port-Royal in Paris eine Bombe in einem Zug der RER B (Baureihe MI 79) in Richtung Saint-Rémy-lès-Chevreuse.',
                'sources' => ['wp-fr'],
            ],
        ],
        'course' => [
            [
                'fr' => "L'engin, une bonbonne de gaz remplie de poudre noire et de soufre, additionnée de clous, était caché dans un sac et déclenché par un minuteur ménager. Le sac avait été déposé dans la rame au départ, à la gare de l'aéroport Charles-de-Gaulle 2 TGV.",
                'en' => 'The device, a gas canister filled with black powder and sulphur with nails added, was hidden in a bag and set off by a household timer. The bag had been left on the train at its departure from Charles-de-Gaulle Airport 2 TGV station.',
                'de' => 'Der Sprengsatz, eine mit Schwarzpulver und Schwefel gefüllte Gasflasche mit hinzugefügten Nägeln, war in einer Tasche versteckt und wurde von einer Haushaltszeitschaltuhr ausgelöst. Die Tasche war bei der Abfahrt am Bahnhof Flughafen Charles-de-Gaulle 2 TGV im Zug abgestellt worden.',
                'sources' => ['wp-fr'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan retenu est de quatre morts et 91 blessés ; deux personnes sont mortes le jour même. Les premiers titres de presse de 1996 évoquaient deux morts et 85 blessés.',
                'en' => 'The accepted toll is four dead and 91 injured; two people died the same day. The first press headlines of 1996 reported two dead and 85 injured.',
                'de' => 'Die anerkannte Bilanz lautet vier Tote und 91 Verletzte; zwei Personen starben noch am selben Tag. Die ersten Pressemeldungen von 1996 sprachen von zwei Toten und 85 Verletzten.',
                'sources' => ['wp-fr', 'wp-en'],
            ],
            [
                'fr' => 'Le 4 décembre 1996, lors de la séance de questions au Gouvernement à l\'Assemblée nationale, le Premier ministre fait état de deux personnes tuées ; le bilan est ensuite porté à quatre morts. Il évoque le dispositif d\'aide aux victimes et leur indemnisation par le fonds de garantie des victimes d\'infractions terroristes.',
                'en' => 'On 4 December 1996, during question time in the National Assembly, the Prime Minister referred to two people killed; the toll was later raised to four dead. He described the victim support arrangements and compensation through the guarantee fund for victims of terrorist offences.',
                'de' => 'Am 4. Dezember 1996 sprach der Premierminister in der Fragestunde der Nationalversammlung von zwei Todesopfern; die Bilanz wurde später auf vier Tote erhöht. Er erläuterte die Hilfe für die Opfer und ihre Entschädigung durch den Garantiefonds für Opfer terroristischer Straftaten.',
                'sources' => ['an-1996-qg2039'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => "L'attentat n'a pas été revendiqué et ses auteurs n'ont pas été identifiés dans les documents lus. La composition de l'engin, proche de ceux de 1995, a fait suspecter la piste du GIA, sans attribution établie. Trois juges antiterroristes ont été chargés de l'instruction et le plan Vigipirate a été réactivé.",
                'en' => 'The attack was not claimed and, in the documents read, its perpetrators have not been identified. The composition of the device, close to those of 1995, led to suspicion of the GIA, with no established attribution. Three anti-terrorism judges were put in charge of the investigation and the Vigipirate security plan was reactivated.',
                'de' => 'Der Anschlag wurde nicht reklamiert, und seine Täter wurden in den gelesenen Dokumenten nicht identifiziert. Die Zusammensetzung des Sprengsatzes, ähnlich denen von 1995, ließ die GIA vermuten, ohne dass eine Zuordnung feststeht. Drei Untersuchungsrichter für Terrorismus übernahmen die Ermittlungen, und der Sicherheitsplan Vigipirate wurde wieder in Kraft gesetzt.',
                'sources' => ['wp-fr', 'wp-en'],
            ],
            [
                'fr' => 'Le lendemain de l\'attentat, le Premier ministre annonce devant l\'Assemblée nationale la remise en vigueur du plan Vigipirate renforcé, avec plus de 1 800 militaires envoyés en renfort dont 800 à Paris, et l\'ouverture d\'une information judiciaire confiée à trois juges. Il indique qu\'aucune revendication n\'a été formulée et qu\'aucune piste ne peut être privilégiée, tout en relevant de grandes similitudes avec les attentats de l\'été 1995.',
                'en' => 'The day after the attack, the Prime Minister told the National Assembly that the reinforced Vigipirate plan was back in force, with more than 1,800 soldiers sent as reinforcements, 800 of them in Paris, and that a judicial investigation had been opened under three judges. He said that no claim had been made and that no lead could be favoured, while noting strong similarities with the attacks of summer 1995.',
                'de' => 'Am Tag nach dem Anschlag teilte der Premierminister der Nationalversammlung mit, dass der verstärkte Plan Vigipirate wieder in Kraft sei, mit mehr als 1 800 zusätzlichen Soldaten, davon 800 in Paris, und dass ein Ermittlungsverfahren unter drei Richtern eröffnet worden sei. Er erklärte, es gebe kein Bekennerschreiben und keine Spur könne bevorzugt werden, wies aber auf große Ähnlichkeiten mit den Anschlägen des Sommers 1995 hin.',
                'sources' => ['an-1996-qg2039'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Une plaque à la mémoire des victimes est apposée à la station et fleurie aux dates anniversaires.',
                'en' => 'A plaque in memory of the victims is fixed at the station and is decorated with flowers on anniversary dates.',
                'de' => 'Im Bahnhof befindet sich eine Gedenktafel für die Opfer, die an den Jahrestagen mit Blumen geschmückt wird.',
                'sources' => ['wp-fr', 'wp-en'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => "Les chiffres de victimes varient : 4 morts et 91 blessés dans les encadrés et la fiche actuelle, 2 morts et 85 blessés dans les titres de presse de 1996 cités par l'article anglais, sans que l'écart soit expliqué (bilan révisé probable). L'heure de 18 h 02 provient du seul article français. Aucune correction n'est appliquée.",
                'en' => 'Casualty figures vary: 4 dead and 91 injured in the infoboxes and the current record, 2 dead and 85 injured in the 1996 press headlines cited by the English article, with no explanation of the gap (a revised toll is likely). The time of 6.02 pm comes from the French article alone. No correction is applied.',
                'de' => 'Die Opferzahlen weichen ab: 4 Tote und 91 Verletzte in den Infoboxen und im aktuellen Datensatz, 2 Tote und 85 Verletzte in den vom englischen Artikel zitierten Pressemeldungen von 1996, ohne Erklärung der Differenz (wahrscheinlich ein revidiertes Ergebnis). Die Uhrzeit 18.02 Uhr stammt allein aus dem französischen Artikel. Es wird keine Korrektur vorgenommen.',
                'sources' => ['wp-fr', 'wp-en'],
            ],
            [
                'fr' => 'Le chiffre de deux morts figure dans la réponse du Premier ministre du 4 décembre 1996, le lendemain des faits ; les quatre morts de la fiche correspondent au bilan retenu ensuite par les articles d\'encyclopédie, sans document officiel lu qui en donne la date.',
                'en' => 'The figure of two dead appears in the Prime Minister\'s reply of 4 December 1996, the day after the event; the four dead in the record correspond to the toll later given by the encyclopaedia articles, with no official document read that gives its date.',
                'de' => 'Die Zahl von zwei Toten steht in der Antwort des Premierministers vom 4. Dezember 1996, dem Tag nach dem Anschlag; die vier Toten im Datensatz entsprechen der später von den Enzyklopädieartikeln genannten Bilanz, ohne dass ein gelesenes amtliches Dokument deren Datum nennt.',
                'sources' => ['an-1996-qg2039', 'wp-fr'],
            ],
        ],
    ],
    'corrections' => [],
];
