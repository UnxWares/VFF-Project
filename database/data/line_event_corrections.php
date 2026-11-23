<?php

/**
 * Corrections SOURCÉES des événements de lignes (line_events), appliquées par
 * vff:apply-event-corrections dans la chaîne vff:rebuild-history (après les imports,
 * avant la résolution des portions). Rejouable : chaque import peut réécrire ses
 * événements, la correction est réappliquée ensuite.
 *
 * Une correction ne remplace pas une règle de parseur : elle sert quand la source
 * structurée (lignes-oubliees) ou le texte ne permettent pas de déduire le fait par
 * une règle générale. Toujours une source vérifiable (permalien Wikipédia, fiche…).
 *
 * Actions :
 *   - add        : ajoute un événement (source « manual », la plus fiable pour le moteur) ;
 *   - neutralize : l'événement d'une source n'est pas une fermeture (desserte réduite,
 *                  trains saisonniers maintenus) : il devient « other » (Événement), sans
 *                  effet sur la carte, et garde sa citation ;
 *   - relocate   : l'événement porte sur une autre portion (from / to) : portion corrigée,
 *                  à résoudre de nouveau.
 *
 * Champs : line (code SNCF ou id), action, type, date (AAAA-MM-JJ), precision
 * (day | month | year), from / to (lieux), pk_start / pk_end (m), match (source, type,
 * date[, from] de l'événement visé), label (neutralize), source_url, quote, reason.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array<string, mixed>>
 */
return [
    // Audit du 2026-10-19 : fermetures aux voyageurs contredites par une desserte actuelle.

    // 884000 Bourg-en-Bresse - Bellegarde (ligne du Haut-Bugey) : fermée en 1990 entre La Cluse
    // et Bellegarde, rouverte après la modernisation, parcourue depuis par les TGV Paris - Genève.
    [
        'line' => '884000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2010-12-01',
        'precision' => 'month',
        'from' => 'La Cluse',
        'to' => 'Bellegarde',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourg-en-Bresse_%C3%A0_Bellegarde&oldid=240169436',
        'quote' => 'La modernisation de la partie exploitée et la réouverture de la partie neutralisée, en décembre 2010, constituent un élément de maillage du réseau TGV français et TGV Lyria entre Genève, Evian et Paris.',
        'reason' => 'réouverture de la section neutralisée depuis 1990 (projet du Haut-Bugey), parcourue par les TGV Paris - Genève et Paris - Évian',
    ],

    // 473000 Auray - Quiberon : le 6 mars 1972, seul le service omnibus est supprimé. Les trains
    // directs d'été (voitures Corail depuis Paris) desservent toujours Quiberon, les navettes du
    // SIVOM circulent dès 1979 et le « Tire-Bouchon » estival depuis 1985 : desserte saisonnière,
    // donc voyageurs (et mixte), jamais « fret seul ».
    [
        'line' => '473000',
        'action' => 'neutralize',
        'match' => ['source' => 'lignes-oubliees', 'type' => 'closure_passenger', 'date' => '1972-03-06'],
        'label' => 'Réduction de la desserte voyageurs',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Auray_%C3%A0_Quiberon&oldid=239856571',
        'quote' => '6 mars 1972 : fermeture de la ligne au service des voyageurs omnibus. Elle est encore utilisée par deux trains aller-retour quotidiens, un express de nuit via Nantes et l\'autre équipé de quatre voitures Corail [...] uniquement pendant les saisons estivales.',
        'reason' => 'fin du service omnibus ; trains directs d\'été maintenus, puis « Tire-Bouchon » estival depuis 1985',
    ],

    // 317000 Rochy-Condé - Soissons : le 15 mai 1939, le service voyageurs « entre Beauvais et
    // Estrées-Saint-Denis » cesse ; sur cette ligne, c'est la section Rochy-Condé - Estrées-Saint-Denis
    // (Beauvais est au-delà de Rochy-Condé). Estrées - Compiègne garde ses trains (Amiens - Compiègne).
    [
        'line' => '317000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1939-05-15', 'from' => 'Beauvais'],
        'from' => 'Rochy-Condé',
        'to' => 'Estrées-Saint-Denis',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Rochy-Cond%C3%A9_%C3%A0_Soissons&oldid=239341121',
        'quote' => 'la suppression du service voyageurs devenant effectif dès le 15 mai 1939, il n\'y aura pas d\'autorails en service régulier entre Rochy-Condé et Estrées.',
        'reason' => 'section de la ligne fermée aux voyageurs le 15 mai 1939 : Rochy-Condé - Estrées-Saint-Denis (Beauvais est sur une autre ligne)',
    ],

    // Audit Corse du 2026-10-19 (sources croisées : articles Wikipédia de la ligne, des Chemins de fer de la
    // Corse et des gares, Bejui, Davies, Favreu ADECEC 2004, Annales des Mines, notice Mérimée du viaduc du Vecchio).
    // =================================================================================
    // 995000 Bastia - Ajaccio : ouvertures par section.
    // Bastia - Corte 01/02/1888 et Ajaccio - Bocognano 01/12/1888 sont déjà en base et justes.
    // La liste « Chronologie » de l'article donne à tort Vivario - Vizzavona au 03/12/1894 ;
    // le texte du même article, l'article CFC, les gares et l'Annales des Mines (arrêté du
    // 08/11/1892 sur la « ligne de Vizzavona à Vivario ») montrent l'ordre : Bocognano -
    // Vizzavona (tunnel) 14/07/1889, Vizzavona - Vivario 09/10/1892, Corte - Vivario
    // (viaduc du Vecchio) 03/12/1894.
    // =================================================================================
    [
        'line' => '995000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1894-12-03', 'from' => 'Vivario'],
        'from' => 'Corte',
        'to' => 'Vivario',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bastia_%C3%A0_Ajaccio&oldid=239140710',
        'quote' => 'Entre-temps, la section de Vizzavona à Vivario a été livrée au service commercial le 9 octobre, et l\'ultime section de Corte à Vivario par le pont du Vecchio n\'est inaugurée que le 3 décembre 1894. Cette date marque l\'achèvement de la ligne centrale',
        'reason' => 'la section ouverte le 3 décembre 1894 est Corte - Vivario (viaduc du Vecchio, construit 1891-1893 selon Mérimée PA00099257), pas Vivario - Vizzavona : texte de l\'article (Bejui 2001 ; Davies 2002 ; Carenco 1998), article CFC, gare de Corte, train-corse.com ; la liste « Chronologie » de l\'article se contredit',
    ],
    [
        'line' => '995000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1889-07-14',
        'precision' => 'day',
        'from' => 'Bocognano',
        'to' => 'Vizzavona',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bastia_%C3%A0_Ajaccio&oldid=239140710',
        'quote' => 'Le 14 juillet 1889, suit la section de Bocognano à Vizzavona.',
        'reason' => 'ouverture par le tunnel de Vizzavona (entrée nord à la gare de Vizzavona, sortie sud côté Bocognano) : article de ligne (Bejui ; Davies ; Carenco), article CFC (Conseil général de la Corse 1892), gares de Vizzavona et de Bocognano, article du tunnel (Silvani 1990)',
    ],
    [
        'line' => '995000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-10-09',
        'precision' => 'day',
        'from' => 'Vizzavona',
        'to' => 'Vivario',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Corse&oldid=240000834',
        'quote' => 'Le 9 octobre 1892, n\'est donc ouverte que la section difficile de Vizzavona à Vivario, parcourable depuis Ajaccio.',
        'reason' => 'article CFC et gares de Vivario, Tattone et Vizzavona (source primaire citée : rapport de l\'ingénieur en chef du contrôle, Conseil général de la Corse 1893 p. 77), texte de l\'article de ligne (Bejui), Annales des Mines (arrêté du 8 novembre 1892 organisant le contrôle de la ligne de Vizzavona à Vivario)',
    ],
    // La fermeture du 01/07/1953 « Folelli-Orezza - Casamozza » (chronologie de l'article
    // Bastia - Ajaccio) concerne la ligne de la côte orientale, pas la ligne 995000 (portion
    // non résolue, sans effet sur la carte mais fausse dans la chronologie de 995000).
    [
        'line' => '995000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1953-07-01'],
        'label' => 'Fermeture de la ligne de la côte orientale (Casamozza - Folelli-Orezza)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bastia_%C3%A0_Ajaccio&oldid=239140710',
        'quote' => 'fermeture de la section Folelli-Orezza - Casamozza ; Casamozza n\'est plus une gare de bifurcation vers la ligne centrale.',
        'reason' => 'la section fermée appartient à la ligne de Casamozza à Porto-Vecchio (côte orientale) ; la ligne Bastia - Ajaccio reste ouverte',
    ],

    // =================================================================================
    // Ligne de la côte orientale (Casamozza - Porto-Vecchio), id 3550.
    // En base : seulement OHM (ouverture 01/02/1888 et fermetures 1943 / 03/06/1953 sur les
    // 17,7 km cartographiés au nord). Rien au sud de Prunete : ouvertures 1888-1935 et
    // fermeture de 1943 manquantes ; 03/06/1953 (OHM) contredit par 4 sources (01/07/1953).
    // =================================================================================
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-02-01',
        'precision' => 'day',
        'from' => 'Casamozza',
        'to' => 'Tallone',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_la_c%C3%B4te_orientale_corse&oldid=238030554',
        'quote' => 'Ce sera finalement en même temps que la section de Bastia à Corte, le 1er février 1888, que la ligne sera officiellement inaugurée jusqu\'à Tallone.',
        'reason' => 'première section ouverte avec Bastia - Corte : article de ligne (Bejui), article CFC, gare de Ghisonaccia, train-corse.com, Ferrovia della Costa orientale (it) ; OHM date aussi du 1er février 1888 la partie nord du tracé',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-06-17',
        'precision' => 'day',
        'from' => 'Tallone',
        'to' => 'Ghisonaccia',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Corse&oldid=240000834',
        'quote' => 'La ligne de la côte orientale sera ouverte jusqu\'à Ghisonaccia le 17 juin 1888.',
        'reason' => 'prolongement jusqu\'à Ghisonaccia, terminus jusqu\'en 1930 : article CFC, article de ligne (« Le 17 juin, la ligne est ouverte jusqu\'à Ghisonaccia »), gare de Ghisonaccia, train-corse.com, K. Favreu (ADECEC 2004) p. 6',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'opening',
        'date' => '1930-09-15',
        'precision' => 'day',
        'from' => 'Ghisonaccia',
        'to' => 'Solenzara',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_la_c%C3%B4te_orientale_corse&oldid=238030554',
        'quote' => 'Il faudra attendre le 15 septembre 1930 pour que la section Ghisonaccia - Sari-Solenzara soit enfin ouverte à la circulation.',
        'reason' => 'article de ligne (Bejui p. 35-37), article CFC, K. Favreu (ADECEC 2004) p. 6, train-corse.com, Bahnstrecke Casamozza-Porto-Vecchio (de)',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'opening',
        'date' => '1935-09-21',
        'precision' => 'day',
        'from' => 'Solenzara',
        'to' => 'Porto-Vecchio',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_la_c%C3%B4te_orientale_corse&oldid=238030554',
        'quote' => 'L\'inauguration du tronçon Sari-Solenzara - Porto-Vecchio a lieu le 21 septembre 1935, comme dernière ligne à voie métrique ouverte en France',
        'reason' => 'article de ligne (Bejui), article CFC, K. Favreu (ADECEC 2004) p. 6, train-corse.com, Bahnstrecke Casamozza-Porto-Vecchio (de)',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'closure',
        'date' => '1943-09-08',
        'precision' => 'day',
        'from' => 'Folelli',
        'to' => 'Porto-Vecchio',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Corse&oldid=240000834',
        'quote' => 'À la suite de nombreuses destructions d\'ouvrages d\'arts pendant la Seconde Guerre mondiale, les trains cessèrent de circuler le 8 septembre 1943 entre Folelli - Orezza et Porto-Vecchio.',
        'reason' => 'fin du trafic après la destruction de 18 ponts par les troupes allemandes en retraite (septembre 1943) ; jamais remise en état : article CFC (Davies p. 211), article de ligne, K. Favreu p. 6, Bahnstrecke Casamozza-Porto-Vecchio (de) ; date du jour = arrêt du trafic, destructions étalées sur septembre 1943',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'closure',
        'date' => '1953-07-01',
        'precision' => 'day',
        'from' => 'Casamozza',
        'to' => 'Folelli',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_la_c%C3%B4te_orientale_corse&oldid=238030554',
        'quote' => 'Le tronçon de Casamozza à Folelli - Orezza, long de 10,9 km, continue de fonctionner jusqu\'au 1er juillet 1953.',
        'reason' => 'fermeture du dernier tronçon (10,9 km) : article de ligne (Davies p. 211 ; Bejui p. 53-55), article CFC, K. Favreu (ADECEC 2004) p. 18, Bahnstrecke Casamozza-Porto-Vecchio (de) ; Ferrovia della Costa orientale (it) : dernier jour le 30 juin 1953 ; la date OHM du 3 juin 1953 est isolée',
    ],
    [
        'line' => '3550', // Ligne de Casamozza à Porto-Vecchio (slug ligne-de-casamozza-a-porto-vecchio)
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1958-02-14',
        'precision' => 'day',
        'from' => 'Casamozza',
        'to' => 'Porto-Vecchio',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_la_c%C3%B4te_orientale_corse&oldid=238030554',
        'quote' => 'L\'administration centrale craint toutefois de heurter la population en proclamant officiellement l\'abandon de tout projet de remise en service, et diffère le déclassement officiel jusqu\'au 14 février 1958 pour la totalité du parcours Casamozza - Porto-Vecchio.',
        'reason' => 'déclassement officiel de toute la ligne : article de ligne (Davies p. 211 ; Bejui p. 53-55), Bahnstrecke Casamozza-Porto-Vecchio (de, mêmes sources) ; texte du déclassement non retrouvé',
    ],

    // 981000 Paris-Nord - Paris-Gare-de-Lyon (RER D) : aucun événement, donc absente des cartes avant
    // 2030. Le tunnel Châtelet - Gare du Nord (construit par la RATP, RER B et D sur les mêmes voies)
    // ouvre en 1981 ; les tunnels propres au RER D de Châtelet à Gare de Lyon en 1995.
    [
        'line' => '981000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1981-12-10',
        'precision' => 'day',
        'from' => 'Châtelet - Les Halles',
        'to' => 'Paris-Nord',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_B_du_RER_d%27%C3%8Ele-de-France&oldid=237656878',
        'quote' => 'Le 10 décembre 1981, la ligne est prolongée de Châtelet - Les Halles à la gare souterraine de Paris Gare du Nord.',
        'reason' => 'ouverture du tunnel Châtelet - Gare du Nord (RER B), emprunté aussi par le RER D depuis 1987',
    ],
    [
        'line' => '981000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1995-09-24',
        'precision' => 'day',
        'from' => 'Châtelet - Les Halles',
        'to' => 'Gare de Lyon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tunnel_Ch%C3%A2telet-Gare_du_Nord&oldid=240343251',
        'quote' => 'Le 24 septembre 1995, l\'interconnexion du RER D avec les trains de banlieue de la gare de Lyon est effective grâce à la construction de deux nouveaux tunnels parallèles à ceux du RER A.',
        'reason' => 'tunnels propres au RER D entre Châtelet - Les Halles et Gare de Lyon',
    ],

    // Tramway de Pithiviers à Toury (ligne 3905, sans code) : l'exploitation touristique n'est connue de
    // l'import des exploitations associatives qu'« attestée » en 2026 ; elle a commencé le 23 avril 1966
    // (événement daté dans database/data/lignes_hors_rfn.php).
    [
        'line' => '3905',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2026-01-01'],
        'label' => 'Exploitation touristique attestée en 2026 (commencée en 1966)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Pithiviers_%C3%A0_Toury&oldid=234550923',
        'quote' => 'l\'exploitation a pu commencer le 23 avril 1966, ainsi que l\'ouverture du musée des transports de Pithiviers dans les locaux de l\'ancien dépôt.',
        'reason' => 'date d\'attestation, pas de début : le train touristique circule depuis 1966',
    ],

    // 552000 Ligne de Sceaux (RER B, RATP) : aucune ouverture localisée (« Enfer », « Limours » hors tracé),
    // donc absente de toutes les cartes historiques et en partie « fermée » en 2030 (gare de Palaiseau).
    // Partie Laplace - Bourg-la-Reine de la même ouverture : portion déclarée par emprise dans
    // database/data/lignes_hors_rfn.php (le résolveur ne trouve pas de chemin continu sur le tracé).
    [
        'line' => '552000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-06-23',
        'precision' => 'day',
        'from' => 'Denfert-Rochereau',
        'to' => 'Gentilly',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
        'quote' => 'Il est inauguré le 7 juin 1846, et ouvert au public le 23 juin.',
        'reason' => 'ligne de la barrière d\'Enfer à Sceaux, inaugurée le 7 juin 1846, ouverte au public le 23 juin',
    ],
    [
        'line' => '552000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-07-28',
        'precision' => 'day',
        'from' => 'Bourg-la-Reine',
        'to' => 'Orsay-Ville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
        'quote' => 'Le tronçon est inauguré jusqu\'à Orsay le 28 juillet 1854.',
        'reason' => 'prolongement de Bourg-la-Reine à Orsay',
    ],
    [
        'line' => '552000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-08-26',
        'precision' => 'day',
        'from' => 'Orsay-Ville',
        'to' => 'Saint-Rémy-lès-Chevreuse',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
        'quote' => 'L\'inauguration de ce dernier tronçon a lieu le 26 août 1867.',
        'reason' => 'section d\'Orsay à Limours, dont le tracé actuel ne garde que la partie jusqu\'à Saint-Rémy-lès-Chevreuse',
    ],
    [
        'line' => '552000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1893-01-01',
        'precision' => 'year',
        'from' => 'Bourg-la-Reine',
        'to' => 'Robinson',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
        'quote' => 'La nouvelle gare de Sceaux créée sur le tracé inauguré en 1893.',
        'reason' => 'nouveau tracé de la branche de Sceaux - Robinson (légende de l\'article ; date au jour non relevée)',
    ],
    [
        'line' => '552000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-01-01',
        'precision' => 'year',
        'from' => 'Luxembourg',
        'to' => 'Denfert-Rochereau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
        'quote' => 'En 1895, elle est prolongée au nord dans Paris jusqu\'à la gare du Luxembourg.',
        'reason' => 'prolongement souterrain de Denfert à Luxembourg',
    ],

    // Chemin de fer des Chanteraines (ligne 4489, hors RFN, sans code) : l'ouverture « toute la ligne » de
    // Wikidata (1981, à l'année) rouvrirait au fret, dès 1981, le prolongement de juillet 1991 vers Les Tilliers.
    // La ligne n'a jamais eu d'autre exploitation que le train touristique (événements datés dans
    // database/data/lignes_hors_rfn.php).
    [
        'line' => '4489',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1981-01-01'],
        'label' => 'Mise en service de la première section (train touristique)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_des_Chanteraines&oldid=239572322',
        'quote' => 'Juillet 1991 : extension du parc permettant le prolongement de la ligne jusqu\'au nouveau terminus Les Tilliers RER, en correspondance avec le RER C.',
        'reason' => 'ligne construite pour le train touristique, ouverte par sections en 1981 et 1991',
    ],

    // Fermetures pour travaux suivies d'une réouverture que le texte ne date pas d'une façon
    // lisible par une règle (vérifié le 2026-10-19 ; l'invariant segments.current_closed_on_served_station
    // signalait ces portions fermées en 2030 sur des gares voyageurs de tronçons SNCF « Exploitée »).

    // 525000 Les Sables-d'Olonne - Tours : Chantonnay - Bressuire fermée par SNCF Réseau le
    // 20 novembre 2019 (oxydation du rail), rouverte peu avant le confinement de mars 2020, de
    // nouveau fermée, puis « entièrement rouverte au début de l'été 2020 ».
    [
        'line' => '525000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2020-07-01',
        'precision' => 'month',
        'from' => 'Chantonnay',
        'to' => 'Bressuire',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_des_Sables-d%27Olonne_%C3%A0_Tours&oldid=239596144',
        'quote' => 'La ligne est entièrement rouverte au début de l\'été 2020.',
        'reason' => 'réouverture après la fermeture technique du 20 novembre 2019 (Chantonnay - Bressuire) ; desserte TER maintenue depuis (ralentie à 40 km/h en août 2026)',
    ],

    // 325000 Épinay-Villetaneuse - Le Tréport-Mers : Beauvais - Le Tréport fermée le 28 mai 2018 pour
    // la modernisation (autocars de remplacement), Beauvais - Abancourt rouverte le 8 février 2020,
    // Abancourt - Le Tréport le 1er août 2020.
    [
        'line' => '325000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2020-02-08',
        'precision' => 'day',
        'from' => 'Beauvais',
        'to' => 'Abancourt',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27%C3%89pinay_-_Villetaneuse_au_Tr%C3%A9port_-_Mers&oldid=235341674',
        'quote' => 'Du 27 mai 2018 au 8 février 2020, tous les trains sont supprimés',
        'reason' => 'fin de la fermeture pour travaux (modernisation 2018-2020) entre Beauvais et Abancourt',
    ],
    [
        'line' => '325000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2020-08-01',
        'precision' => 'day',
        'from' => 'Abancourt',
        'to' => 'Le Tréport',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27%C3%89pinay_-_Villetaneuse_au_Tr%C3%A9port_-_Mers&oldid=235341674',
        'quote' => 'la partie Abancourt-Le Tréport ayant rouvert, quant à elle, le 1er août 2020',
        'reason' => 'fin de la fermeture pour travaux (modernisation 2018-2020) entre Abancourt et Le Tréport',
    ],

    // 800000 Givors-Canal - Grezan (rive droite du Rhône) : la réouverture aux voyageurs du 29 août 2022
    // porte sur Pont-Saint-Esprit - Nîmes-Centre (TER Pont-Saint-Esprit - Bagnols - Avignon / Nîmes) ;
    // Nîmes-Centre est au-delà de la bifurcation de Grezan, bout de la ligne. Saint-Péray et Le Teil,
    // « voyageurs » dans liste-des-gares, ne sont pas desservies par train (prolongement au Teil reporté).
    [
        'line' => '800000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'reopening_passenger', 'date' => '2022-08-29'],
        'from' => 'Pont-Saint-Esprit',
        'to' => 'Grezan',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Givors-Canal_%C3%A0_Grezan&oldid=238687433',
        'quote' => 'Le 29 août 2022, la ligne a rouvert au trafic voyageurs entre les gares de Pont Saint-Esprit et de Nîmes-Centre.',
        'reason' => 'portion rouverte aux voyageurs : Pont-Saint-Esprit - Grezan (puis Nîmes-Centre, sur une autre ligne) ; le reste de la rive droite reste fret seul',
    ],

    // 996000 Ponte-Leccia - Calvi : le « tramway de la Balagne » (L'Île-Rousse - Calvi) est une desserte
    // voyageurs saisonnière de l'exploitant commercial (EPIC CFC), pas une exploitation associative.
    // vff:import-heritage-lines la lisait comme train touristique (Wikidata Q1069379) : le tronçon
    // passait « patrimoine » et perdait son trafic voyageurs (cf. LineClassifier, exploitation associative).
    [
        'line' => '996000',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1983-01-01'],
        'label' => 'Desserte saisonnière des trains-tramways de la Balagne (CFC)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Ponte-Leccia_%C3%A0_Calvi&oldid=235342565',
        'quote' => 'Sur la section de ligne L\'Île Rousse à Calvi, jusqu\'à 6 aller-retours supplémentaires sont assurés en juillet-août constituant ce qu\'il est convenu d\'appeler le « tramway de la Balagne », ou encore le « train des plages ».',
        'reason' => 'service voyageurs saisonnier des Chemins de fer de la Corse, exploitant commercial de la ligne',
    ],
];
