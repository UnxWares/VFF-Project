<?php

// Contexte sourcé de la collision de deux trains de marchandises entre Saint-Cyr-sur-Mer et La Ciotat, ligne de Marseille à Vintimille, de novembre 1894 (recherche du 21/11/2026).
return [
    'slug' => '1894-11-10-la-ciotat',
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
            'locator' => '1894',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1894',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 10 novembre 1894, d\'après Le Petit Journal : sur la ligne de Marseille à Nice, l\'après-midi, dans une tranchée entre Saint-Cyr et La Ciotat, un train de ballast allant vers Marseille heurte un train de marchandises allant à Toulon ; un mécanicien et un conducteur-chef tués, treize employés blessés.',
        ],
        'ddb-1894-11-13-velbert' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Toulon, 11. Nov.',
            'publisher' => 'Velberter Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-11-13',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/A422KNFLDYLK6YZCXZTOUGX3HPHU6H2H?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Toulon du 11 novembre (même texte dans la Solinger Zeitung du 12 et le Märkisches Tageblatt du 14) : entre La Ciotat et Saint-Cyr, deux trains de marchandises se sont heurtés ; un chef de train et un mécanicien tués, plusieurs agents grièvement blessés, dégâts importants.',
        ],
        'ddb-1894-11-12-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Toulon 11. Nov.',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-11-12',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/H3LDRNDMXVLULHZYYQ64T4CORDVVF32Y?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Autre version de la dépêche de Toulon du 11 novembre : collision de deux trains de marchandises près de La Ciotat ; les mécaniciens et les chefs de train des deux trains tués, 15 agents grièvement blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 10 novembre 1894, l\'après-midi, un train de ballast allant vers Marseille et un train de marchandises allant à Toulon circulaient sur la ligne de Marseille à Nice, entre Saint-Cyr-sur-Mer et La Ciotat.',
                'en' => 'On the afternoon of 10 November 1894 a ballast train heading for Marseille and a goods train bound for Toulon were running on the Marseille to Nice line between Saint-Cyr-sur-Mer and La Ciotat.',
                'de' => 'Am Nachmittag des 10. November 1894 fuhren ein Schotterzug in Richtung Marseille und ein Güterzug nach Toulon auf der Strecke Marseille-Nizza zwischen Saint-Cyr-sur-Mer und La Ciotat.',
                'sources' => ['wp-liste-1890', 'ddb-1894-11-13-velbert'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les deux trains se sont heurtés dans une tranchée. Les dégâts matériels ont été importants.',
                'en' => 'The two trains collided in a cutting. The damage was heavy.',
                'de' => 'Die beiden Züge stießen in einem Einschnitt zusammen. Der Sachschaden war bedeutend.',
                'sources' => ['wp-liste-1890', 'ddb-1894-11-13-velbert'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un mécanicien et un chef de train ont été tués. La liste de Wikipédia compte treize employés blessés, la dépêche de Toulon « plusieurs agents » grièvement blessés.',
                'en' => 'A driver and a guard were killed. The Wikipedia list counts thirteen railwaymen injured, the Toulon dispatch "several staff" seriously injured.',
                'de' => 'Ein Lokführer und ein Zugführer wurden getötet. Die Wikipedia-Liste zählt dreizehn verletzte Bedienstete, die Depesche aus Toulon „mehrere Angestellte“ schwer verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1894-11-13-velbert'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une autre version de la dépêche de Toulon, parue dans le Schwäbischer Merkur et le Merseburger Kreisblatt, dit tués les mécaniciens et les chefs de train des deux trains, soit quatre morts, et compte 15 blessés graves.',
                'en' => 'Another version of the Toulon dispatch, printed in the Schwäbischer Merkur and the Merseburger Kreisblatt, says the drivers and guards of both trains were killed, which makes four dead, and counts 15 seriously injured.',
                'de' => 'Eine andere Fassung der Depesche aus Toulon im Schwäbischen Merkur und im Merseburger Kreisblatt meldet Lokführer und Zugführer beider Züge als getötet, also vier Tote, und zählt 15 Schwerverletzte.',
                'sources' => ['ddb-1894-11-12-merkur', 'ddb-1894-11-13-velbert'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1894-11-13-velbert'],
            'fr' => 'Un mécanicien et un chef de train tués selon la liste de Wikipédia et la dépêche de Toulon du 11 novembre 1894 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'A driver and a guard killed according to the Wikipedia list and the Toulon dispatch of 11 November 1894; the record gave no number of deaths.',
            'de' => 'Ein Lokführer und ein Zugführer getötet nach der Wikipedia-Liste und der Depesche aus Toulon vom 11. November 1894; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
