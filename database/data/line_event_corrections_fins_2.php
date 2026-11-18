<?php

/**
 * Corrections SOURCÉES, suite de line_event_corrections_fins.php (chantier « chutes de km »,
 * passe du 2026-10-20) : invariants lines.km_drop_without_event et network.km_out_of_range
 * (réseau secondaire en service en 1950).
 *
 * Mêmes règles de saisie que line_event_corrections_fins.php (fichier généré par
 * storage/app/reports/agents/chutes-km/to_php.py : ce fichier-ci est écrit à la main pour ne pas
 * être écrasé). Une fermeture de toute la ligne sur une ligne qui a des fermetures par section
 * porte les extrémités de la ligne (from / to), sinon elle serait masquée par l'arbitrage
 * (EraTimeline::dedupe). Toujours un permalien Wikipédia (oldid), une citation exacte et une
 * raison. Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$poCorreze = 'https://fr.wikipedia.org/w/index.php?title=PO-Corr%C3%A8ze&oldid=235614381';
$poCorrezeQuote = 'L\'ensemble des lignes a été ouvert en 1904 et fermé le 3 novembre 1969 au trafic voyageur et le 31 mai 1970, à celui des marchandises.';
$allier = 'https://fr.wikipedia.org/w/index.php?title=R%C3%A9seau_ferr%C3%A9_secondaire_de_l%27Allier&oldid=236465242';
$allier1939 = 'La fermeture des lignes intervient alors dans l\'ordre suivant : le 1 juin 1939, les sections : Varennes - Chantelle ; Chantelle - Bézenet ; Bézenet - Commentry - Marcillat ; Chantelle - Ébreuil ; Dompierre - Lapalisse ; Lapalisse - Le Mayet-de-Montagne ; Varennes - Le Donjon ;';
$cher = 'https://fr.wikipedia.org/w/index.php?title=R%C3%A9seau_du_Cher&oldid=240471483';
$cher1948 = 'La fermeture des lignes eut lieu dans l\'ordre suivant : le 1er aout 1948, lignes La Guerche - Argent et Neuilly - Moulin - Jamet à Saint-Satur ;';
$cher1951 = 'le 1 juillet 1951, lignes Bourges - Laugères, Marçais - Saint-Florent et La Guerche - Chateaumeillant.';

return [

    // 715000 Ligne de Seilhac à Treignac (PO-Corrèze) : la phrase d'ensemble de l'article du réseau
    // date aussi la fin du fret (31 mai 1970), que l'import n'avait pas lue ; seule restait une
    // fermeture ESTIMÉE (1970) masquée par la fin des voyageurs, et la ligne restait « fret » jusqu'en 2020.
    [
        'line' => '715000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-11-03',
        'precision' => 'day',
        'source_url' => $poCorreze,
        'quote' => $poCorrezeQuote,
        'reason' => 'fin du service voyageurs sur toute la ligne (fermeture de tout le réseau PO-Corrèze)',
    ],
    [
        'line' => '715000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $poCorreze,
        'quote' => $poCorrezeQuote,
        'reason' => 'fin du fret, dernier trafic commercial sur toute la ligne',
    ],
    [
        'line' => '715000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1972-01-14',
        'precision' => 'day',
        'source_url' => $poCorreze,
        'quote' => 'L\'ensemble des lignes est déclassé par décret le 14 janvier 1972',
        'reason' => 'déclassement de toute la ligne avec l\'ensemble du réseau PO-Corrèze',
    ],

    // 763000 Ligne de Tamnay-Châtillon à Château-Chinon : fin du fret en 2004 (fermeture de la gare
    // bois de Château-Chinon). Les fermetures « toute la ligne » de l'infobox (1938, « partiel ») et de
    // Wikidata (2004) ne sont pas localisables : la ligne restait en service jusqu'en 2010.
    [
        'line' => '763000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2004-01-01',
        'precision' => 'year',
        'from' => 'Tamnay-Châtillon',
        'to' => 'Château-Chinon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Tamnay-Ch%C3%A2tillon_%C3%A0_Ch%C3%A2teau-Chinon&oldid=240349342',
        'quote' => 'En 2004, la fermeture de la gare bois de Château-Chinon a entraîné la fermeture de la ligne au service marchandises.',
        'reason' => 'fin du fret, dernier trafic commercial (voyageurs fermés depuis 1932) ; ligne neutralisée depuis',
    ],

    // 808000 Ligne de Bessèges à Robiac : plus aucun train depuis le 7 juillet 2012 (voie en mauvais
    // état, tronçon Bessèges - Saint-Ambroix suspendu). Seule la fin des voyageurs était datée : la
    // portion restait « fret » jusqu'en 2020.
    [
        'line' => '808000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2012-07-07',
        'precision' => 'day',
        'from' => 'Bessèges',
        'to' => 'Robiac',
        // Source directe confirmée le 2026-10-20 (chantier données à sourcer) : fin de TOUT trafic, pas
        // une fin du fret déduite.
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Teil_%C3%A0_Al%C3%A8s&oldid=239475659',
        'quote' => 'Du fait du mauvais état de la voie, le trafic voyageurs est suspendu le 7 juillet 2012, la ligne est non-exploitée depuis cette date.',
        'reason' => 'suspension durable de tout le tronçon (dont Robiac - Bessèges) : l\'article de la ligne (oldid 235438569) date la fermeture du 7 juillet 2012 « du fait du mauvais état de la voie », celui de la gare de Bessèges (oldid 235030861) : « Service suspendu depuis le 7 juillet 2012 » ; jamais rouverte',
    ],

    // 689000 Ligne de Saint-Germain-du-Puy à Cosne-Cours-sur-Loire : fin du service voyageurs absente.
    [
        'line' => '689000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1966-06-01',
        'precision' => 'month',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Germain-du-Puy_%C3%A0_Cosne-Cours-sur-Loire&oldid=240500309',
        'quote' => 'Le service voyageurs cessa en juin 1966.',
        'reason' => 'fin du service voyageurs sur toute la ligne',
    ],

    // Chemin de fer de Voiron à Saint-Béron : trains remplacés par des autobus le 30 septembre 1936,
    // ligne déclassée en 1939 ; seul « un court tronçon » vers une usine reste jusqu'en 1953 (portion
    // non localisable). La fermeture « 1953 » de l'infobox (fermeture2) la laissait en service 34 km en 1950.
    [
        'line' => '4171',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1936-09-30',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Voiron_%C3%A0_Saint-B%C3%A9ron&oldid=236510781',
        'quote' => 'L\'exploitation ferroviaire durera peu, puisque des autobus remplacent quelques trains dès 1931, pour la remplacer totalement le 30 septembre 1936.',
        'reason' => 'fin de toute exploitation ferroviaire ; le court tronçon de Saint-Béron à une usine électro-métallurgique (fermé en 1953 selon l\'article) n\'est pas localisable',
    ],

    // Réseau ferré secondaire de l'Allier : la date « 1951 » venait de la phrase « Toutes les lignes
    // fermeront entre 1939 et 1951 » (article Réseau du Centre) ; l'article du réseau de l'Allier date
    // chaque section.
    [
        'line' => '4144',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-06-01',
        'precision' => 'day',
        'from' => 'Varennes-sur-Allier',
        'to' => 'Commentry',
        'source_url' => $allier,
        'quote' => $allier1939,
        'reason' => 'Ligne de Varennes à Commentry : sections Varennes - Chantelle, Chantelle - Bézenet et Bézenet - Commentry fermées le 1er juin 1939',
    ],
    [
        'line' => '3572',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-06-01',
        'precision' => 'day',
        'from' => 'Chantelle',
        'to' => 'Ébreuil',
        'source_url' => $allier,
        'quote' => $allier1939,
        'reason' => 'Ligne de Chantelle à Ebreuil fermée le 1er juin 1939',
    ],
    [
        'line' => '4069',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1949-02-01',
        'precision' => 'day',
        'from' => 'Villefranche-d\'Allier',
        'to' => 'Lapeyrouse',
        'source_url' => $allier,
        'quote' => 'Le 1 février 1949, la section Villefranche-d\'Allier - Lapeyrouse ;',
        'reason' => 'fermeture de la section Villefranche-d\'Allier - Lapeyrouse',
    ],
    [
        'line' => '4069',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-01-01',
        'precision' => 'day',
        'from' => 'Sancoins',
        'to' => 'Villefranche-d\'Allier',
        'source_url' => $allier,
        'quote' => 'Le 1 janvier 1951, les sections : Sancoins - Couleuvre ; Couleuvre - Villefranche-d\'Allier.',
        'reason' => 'fermeture des sections Sancoins - Couleuvre et Couleuvre - Villefranche-d\'Allier',
    ],

    // Réseau du Cher : même phrase « entre 1939 et 1951 » ; l'article du réseau date chaque ligne.
    [
        'line' => '4146',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1948-08-01',
        'precision' => 'day',
        'source_url' => $cher,
        'quote' => $cher1948,
        'reason' => 'Ligne de Veaugues à Argent (partie de La Guerche - Argent) fermée le 1er août 1948',
    ],
    [
        'line' => '3865',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1948-08-01',
        'precision' => 'day',
        'source_url' => $cher,
        'quote' => $cher1948,
        'reason' => 'Ligne de Neuilly-Moulin-Jamet à Saint-Satur fermée le 1er août 1948',
    ],
    [
        'line' => '3519',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-07-01',
        'precision' => 'day',
        'from' => 'Bourges',
        'to' => 'Laugère',
        'source_url' => $cher,
        'quote' => $cher1951,
        'reason' => 'Ligne de Bourges à Laugère fermée le 1er juillet 1951',
    ],
    [
        'line' => '4015',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-07-01',
        'precision' => 'day',
        'from' => 'Saint-Florent-sur-Cher',
        'to' => 'Marçais',
        'source_url' => $cher,
        'quote' => $cher1951,
        'reason' => 'Ligne de Saint-Florent à Marçais fermée le 1er juillet 1951',
    ],
    [
        'line' => '3707',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-07-01',
        'precision' => 'day',
        'source_url' => $cher,
        'quote' => $cher1951,
        'reason' => 'Ligne de La Guerche à Châteaumeillant fermée le 1er juillet 1951',
    ],
];
