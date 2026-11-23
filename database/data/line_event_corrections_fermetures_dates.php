<?php

/**
 * Corrections SOURCÉES des fermetures et des réouvertures temporaires de lignes (1930-1990)
 * restées sans jour dans la base ou à la précision de l'année : le jour est lu dans l'article Wikipédia de la
 * ligne, à la révision citée en source (chantier « sections-dates-3 » du 23/11/2026, lecture seule de la base,
 * API MediaWiki avec le User-Agent du projet, une requête toutes les 5 secondes). Quand l'article donne la
 * section, la portion est celle de la source ; sinon la correction vaut pour toute la ligne.
 *
 * Même format que line_event_corrections.php (cf. son en-tête) ; appliqué par vff:apply-event-corrections.
 * Les événements d'année de l'import restent en base : un événement manuel au jour est le plus fiable pour le
 * moteur. Les déclassements par décret ou par loi ne sont pas repris : tous ceux que lisent les articles sont
 * déjà au jour (Wikipédia, lignes-oubliées). Types : closure_passenger (fin du service voyageurs),
 * closure_freight (fin du service marchandises), closure (tous trafics), reopening_passenger (réouverture
 * temporaire de guerre), opening_passenger (ouverture aux voyageurs d'une ligne ouverte aux marchandises).
 *
 * 840000 Bricon - Chatillon-sur-Seine : les deux dates de fin de réouverture (30/11/1939 et 04/11/1940) étaient
 * lues comme des réouvertures par l'import Wikipédia ; elles sont neutralisées, la fermeture aux voyageurs les
 * remplace.
 *
 * Pas de tiret long dans les textes (consigne éditoriale) : les tirets des citations sont rendus par « - ».
 *
 * @return list<array<string, mixed>>
 */
return [

    [
        'line' => '016000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1937-10-03', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Montier-en-Der_%C3%A0_%C3%89claron&oldid=207096382',
        'quote' => 'Elle a été fermée au service des voyageurs le 3 octobre 1937 et au service des marchandises le 1 avril 1991.',
        'reason' => 'fermeture au service des voyageurs',
    ],
    [
        'line' => '061000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1989-05-28', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Remiremont_%C3%A0_Cornimont&oldid=218186591',
        'quote' => 'Elle a été fermée au service des voyageurs le 28 mai 1989 et au trafic des marchandises le 27 septembre 1992.',
        'reason' => 'fermeture au service des voyageurs',
    ],
    [
        'line' => '116000', 'action' => 'add', 'type' => 'closure', 'date' => '1973-06-02', 'precision' => 'day',
        'from' => 'Sainte-Marie-aux-Mines', 'to' => 'Lesseux - Frapelle',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_S%C3%A9lestat_%C3%A0_Lesseux_-_Frapelle&oldid=238408776',
        'quote' => 'La section de Sainte-Marie-aux-Mines à Lesseux - Frapelle est fermée le 2 juin 1973 à la suite de la transformation (pour une durée limitée à l\'époque) du tunnel ferroviaire en tunnel routier.',
        'reason' => 'fermeture de la section Sainte-Marie-aux-Mines - Lesseux-Frapelle',
    ],
    [
        'line' => '116000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1980-01-02', 'precision' => 'day',
        'from' => 'Sélestat', 'to' => 'Sainte-Marie-aux-Mines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_S%C3%A9lestat_%C3%A0_Lesseux_-_Frapelle&oldid=238408776',
        'quote' => 'La section de Sélestat à Sainte-Marie-aux-Mines est fermée au trafic des voyageurs le 2 janvier 1980 et le service transféré sur route.',
        'reason' => 'fermeture aux voyageurs, service transféré sur route',
    ],
    [
        'line' => '116000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1990-10-01', 'precision' => 'day',
        'from' => 'Bois-l\'Abbesse', 'to' => 'Sainte-Marie-aux-Mines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_S%C3%A9lestat_%C3%A0_Lesseux_-_Frapelle&oldid=238408776',
        'quote' => 'La section de Bois-l\'Abbesse à Sainte-Marie-aux-Mines est fermée au trafic des marchandises le 1 octobre 1990.',
        'reason' => 'fermeture au trafic des marchandises',
    ],
    [
        'line' => '117000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1947-10-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Val-de-Vill%C3%A9_%C3%A0_Vill%C3%A9&oldid=238410818',
        'quote' => 'Elle est fermée au service voyageurs le 1er octobre 1947 puis au service marchandises le 16 décembre 1973.',
        'reason' => 'fermeture au service voyageurs',
    ],
    [
        'line' => '121000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1945-12-31', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Colmar-Sud_%C3%A0_Bollwiller&oldid=235373533',
        'quote' => 'La desserte voyageurs est abandonnée peu après la Seconde Guerre mondiale, le 31 décembre 1945, et la section Ensisheim - Bollwiller fermée à tout trafic, mais déclassée seulement le 13 décembre 1964.',
        'reason' => 'desserte voyageurs abandonnée',
    ],
    [
        'line' => '122000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1971-07-05', 'precision' => 'day',
        'from' => 'Bollwiller', 'to' => 'Lautenbach',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bollwiller_%C3%A0_Lautenbach&oldid=238407952',
        'quote' => 'La desserte marchandises entre Bollwiller et Lautenbach est par contre poursuivie régulièrement jusqu\'au 5 juillet 1971.',
        'reason' => 'fin de la desserte marchandises',
    ],
    [
        'line' => '123000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1945-12-31', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Neuf-Brisach_%C3%A0_Bantzenheim&oldid=238408634',
        'quote' => 'Le service voyageurs est fermé le 31 décembre 1945.',
        'reason' => 'fermeture du service voyageurs',
    ],
    [
        'line' => '123000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1967-07-17', 'precision' => 'day',
        'from' => 'Neuf-Brisach', 'to' => 'Blodelsheim',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Neuf-Brisach_%C3%A0_Bantzenheim&oldid=238408634',
        'quote' => 'Le service marchandises de la section Neuf-Brisach (Volgelsheim) - Blodelsheim est fermé le 17 juillet 1967.',
        'reason' => 'fermeture du service marchandises de la section Neuf-Brisach - Blodelsheim',
    ],
    [
        'line' => '137000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1944-12-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_vall%C3%A9e_de_Kaysersberg&oldid=241121876',
        'quote' => 'Le dernier train de marchandises circule le 6 décembre 1944.',
        'reason' => 'dernier train de marchandises',
    ],
    [
        'line' => '170000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1980-01-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sarreguemines_%C3%A0_Bliesbruck&oldid=240161789',
        'quote' => 'Elle est ensuite fermée au service marchandises le 1 janvier 1980 à l\'occasion de la fermeture des gares de Bliesbruck et de Folpersviller, le point frontière n\'étant plus utilisé depuis plusieurs années.',
        'reason' => 'fermeture au service marchandises',
    ],
    [
        'line' => '227000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1940-10-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Ormoy-Villers_%C3%A0_Mareuil-sur-Ourcq&oldid=223296975',
        'quote' => 'Sous l\'Occupation, la seconde voie redevenue inutile est à nouveau déposée, et un train mixte quotidien, voyageurs et marchandises, est rétabli le 6 octobre 1940 vu l\'absence de transport routier causé par les pénuries.',
        'reason' => 'train mixte quotidien rétabli (pénurie de transport routier)',
    ],
    [
        'line' => '236000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1940-09-01', 'precision' => 'day',
        'from' => 'Laon', 'to' => 'Solesmes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Laon_au_Cateau&oldid=235957262',
        'quote' => 'Le 1 septembre 1940, un éphémère train léger est rétabli entre Laon et Solesmes, prolongé jusqu\'à Valenciennes le mois suivant.',
        'reason' => 'rétablissement d\'un éphémère train léger entre Laon et Solesmes',
    ],
    [
        'line' => '236000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1942-05-04', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Laon_au_Cateau&oldid=235957262',
        'quote' => 'Mais dès le printemps 1941, son parcours est limité au trajet de Laon au Cateau, puis, au service du 4 mai 1942, il est supprimé et remplacé par un autocar.',
        'reason' => 'train supprimé et remplacé par un autocar au service du 4 mai 1942',
    ],
    [
        'line' => '240000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-07-01', 'precision' => 'day',
        'from' => 'Ferrière-la-Grande', 'to' => 'Cousolre',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Maubeuge_%C3%A0_Fourmies&oldid=235337755',
        'quote' => 'Le 1 juillet 1939, cette ligne sera fermée aux voyageurs, le seul point d\'arrêt entre Ferrière-la-Grande et Cousolre était Colleret.',
        'reason' => 'fermeture aux voyageurs de la section Ferrière-la-Grande - Cousolre',
    ],
    [
        'line' => '240000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1969-09-28', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Maubeuge_%C3%A0_Fourmies&oldid=235337755',
        'quote' => 'Victime du développement de la motorisation individuelle, le service voyageur sera supprimé sur toute la ligne le 28 septembre 1969.',
        'reason' => 'service voyageurs supprimé sur toute la ligne',
    ],
    [
        'line' => '242626', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1965-07-15', 'precision' => 'day',
        'from' => 'Origny-Sainte-Benoite', 'to' => 'Guise',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Saint-Quentin_%C3%A0_Guise&oldid=239837707',
        'quote' => 'et le 15 juillet 1965 le trafic voyageurs entre Origny-Sainte-Benoite et Guise cesse, suivi l\'année suivante de la fermeture définitive de ce tronçon.',
        'reason' => 'cessation du trafic voyageurs entre Origny-Sainte-Benoite et Guise',
    ],
    [
        'line' => '242626', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1968-06-30', 'precision' => 'day',
        'from' => 'Saint-Quentin', 'to' => 'Origny-Sainte-Benoite',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Saint-Quentin_%C3%A0_Guise&oldid=239837707',
        'quote' => 'Le 30 juin 1968, le dernier autorail de la R.T.A quittera à tout jamais le quai n°5 de la Gare de Saint-Quentin à destination d\'Origny-Sainte-Benoîte.',
        'reason' => 'dernier autorail entre Saint-Quentin et Origny-Sainte-Benoite',
    ],
    [
        'line' => '251000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-05-15', 'precision' => 'day',
        'from' => 'Solesmes', 'to' => 'Gussignies',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Escaud%C5%93uvres_%C3%A0_Gussignies&oldid=239202062',
        'quote' => 'Le tronçon de Solesmes à la frontière belge est fermé au trafic voyageurs le 15 mai 1939, celui de (Cambrai) Escaudœuvres à Solesmes le 1er avril 1955.',
        'reason' => 'fermeture aux voyageurs de Solesmes à la frontière belge',
    ],
    [
        'line' => '251000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1955-02-02', 'precision' => 'day',
        'from' => 'Escaudœuvres', 'to' => 'Rieux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Escaud%C5%93uvres_%C3%A0_Gussignies&oldid=239202062',
        'quote' => 'La section Escaudœuvres-Rieux est fermée au trafic marchandises le 2 février 1955, celles de Rieux à Saint-Aubert le 7 avril 1969, de Solesmes à Salesches le 3 novembre 1969, de Bavay à Bettrechies le 7 avril 1969, de Bavay à',
        'reason' => 'fermeture au trafic marchandises',
    ],
    [
        'line' => '251000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1955-04-01', 'precision' => 'day',
        'from' => 'Escaudœuvres', 'to' => 'Solesmes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Escaud%C5%93uvres_%C3%A0_Gussignies&oldid=239202062',
        'quote' => 'Le tronçon de Solesmes à la frontière belge est fermé au trafic voyageurs le 15 mai 1939, celui de (Cambrai) Escaudœuvres à Solesmes le 1er avril 1955.',
        'reason' => 'fermeture aux voyageurs de Cambrai (Escaudœuvres) à Solesmes',
    ],
    [
        'line' => '253000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1969-09-28', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valenciennes-Faubourg-de-Paris_%C3%A0_Hautmont&oldid=240564961',
        'quote' => 'Elle est fermée à la desserte voyageurs le 28 septembre 1969 en totalité, puis au trafic marchandises en 1992 de Marly-lez-Valenciennes à Saultain.',
        'reason' => 'fermeture de la desserte voyageurs sur toute la ligne',
    ],
    [
        'line' => '268000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-07-01', 'precision' => 'day',
        'from' => 'Ascq', 'to' => 'Tourcoing',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Somain_%C3%A0_Halluin&oldid=240846119',
        'quote' => 'La section Ascq - Tourcoing suit seulement six semaines plus tard, le 1 juillet 1939.',
        'reason' => 'fermeture aux voyageurs de la section Ascq - Tourcoing',
    ],
    [
        'line' => '319000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1940-10-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Breteuil-Embranchement_%C3%A0_Breteuil-Ville&oldid=235438571',
        'quote' => 'Le 6 octobre 1940, un train mixte est remis en service les jours ouvrables.',
        'reason' => 'train mixte remis en service',
    ],
    [
        'line' => '322000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1938-11-07', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Canaples_%C3%A0_Longroy_-_Gamaches&oldid=235223311',
        'quote' => '7 novembre 1938, fermeture du trafic voyageurs (temporairement rouvert de 1941 à 1944)',
        'reason' => 'fermeture du trafic voyageurs (temporairement rouvert de 1941 à 1944)',
    ],
    [
        'line' => '332000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-03-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Beauvais_%C3%A0_Gisors-Embranchement&oldid=235341743',
        'quote' => 'Le 6 mars 1939, le service est supprimé pour laisser place au transport routier.',
        'reason' => 'service voyageurs supprimé au profit du transport routier',
    ],
    [
        'line' => '348000', 'action' => 'add', 'type' => 'closure', 'date' => '1951-03-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Chars_%C3%A0_Marines&oldid=224455011',
        'quote' => 'Elle ferme à tous trafics quarante ans plus tard le 1 mars 1951, sur décision du conseil général de Seine-et-Oise et est déclassée le 11 juillet 1953.',
        'reason' => 'fermeture à tous trafics',
    ],
    [
        'line' => '349000', 'action' => 'add', 'type' => 'closure', 'date' => '1953-01-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mont%C3%A9rolier_-_Buchy_%C3%A0_Saint-Sa%C3%ABns&oldid=241331000',
        'quote' => 'Le 1 janvier 1953, la ligne Saint-Saëns - Montérolier-Buchy fermait et, avec elle, disparaissait la dernière voie ferrée d\'intérêt local de Seine-Inférieure.',
        'reason' => 'fermeture de la ligne',
    ],
    [
        'line' => '351000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1949-07-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Barentin_%C3%A0_Caudebec-en-Caux&oldid=238787797',
        'quote' => 'Cette renaissance fut éphémère car le 1 juillet 1949, l\'unique train mixte qui assurait la desserte de l\'itinéraire fut supprimé, le trafic voyageurs disparaissait et entrainait la neutralisation de la section comprise entre Caudebec-Marchandises et Caudebec-Voyageurs.',
        'reason' => 'suppression de l\'unique train mixte, fin du trafic voyageurs',
    ],
    [
        'line' => '361000', 'action' => 'add', 'type' => 'closure', 'date' => '1938-10-02', 'precision' => 'day',
        'from' => 'Rolleville', 'to' => 'Les Ifs',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Havre-Graville_%C3%A0_Tourville-les-Ifs&oldid=238330126',
        'quote' => 'Le 2 octobre 1938, un décret de coordination ferme la section de Rolleville aux Ifs et au-delà jusqu\'à Fécamp.',
        'reason' => 'fermeture par décret de coordination de la section Rolleville - Les Ifs',
    ],
    [
        'line' => '411000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1940-10-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Falaise_%C3%A0_Berjou&oldid=223607550',
        'quote' => '1 mars 1938 (rouverte durant la guerre du 6 octobre 1940 à 1942)',
        'reason' => 'réouverture aux voyageurs durant la guerre',
    ],
    [
        'line' => '412000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1938-03-01', 'precision' => 'day',
        'from' => 'Pont-d\'Ouilly', 'to' => 'Falaise',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=241393779',
        'quote' => 'Le tronçon Berjou-Pont-d\'Ouilly avait déjà été fermé le 3 novembre 1969 (et le tronçon Pont-d\'Ouilly-Falaise l\'avait été le 1 mars 1938).',
        'reason' => 'fermeture aux voyageurs du tronçon Pont-d\'Ouilly - Falaise',
    ],
    [
        'line' => '423000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1953-08-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Alen%C3%A7on_%C3%A0_Cond%C3%A9-sur-Huisne&oldid=239600483',
        'quote' => 'Elle sera fermée au trafic voyageurs le 01 août 1953, au trafic marchandises de Mortagne à Mauves-Corbon le 03 novembre 1969, de Boissy-Maisons-Maugis à Condé-sur-Huisne le 07 mai 1971 et de Mortagne-au-Perche à Alençon le 13 juillet 1989.',
        'reason' => 'fermeture au trafic voyageurs',
    ],
    [
        'line' => '443000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1939-10-02', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Brohini%C3%A8re_%C3%A0_Dinan&oldid=236290054',
        'quote' => 'En raison des problèmes de circulation routière, une exploitation ferroviaire partielle reprendra le 2 octobre 1939.',
        'reason' => 'exploitation ferroviaire partielle reprise',
    ],
    [
        'line' => '462000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1942-05-03', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Laval_%C3%A0_Pouanc%C3%A9&oldid=229059074',
        'quote' => 'Rouverte durant la guerre du 3 octobre 1940 au 3 mai 1942.',
        'reason' => 'fin de la réouverture aux voyageurs de guerre',
    ],
    [
        'line' => '517000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1990-09-30', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Baule-Escoublac_%C3%A0_Gu%C3%A9rande&oldid=231528514',
        'quote' => 'Le 30 septembre 1990, la ligne vers La Baule-Escoublac ferme.',
        'reason' => 'fermeture de la ligne',
    ],
    [
        'line' => '524000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1976-05-30', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Neuville-de-Poitou_%C3%A0_Bressuire&oldid=239600491',
        'quote' => 'Cette section est fermée au trafic voyageur le 30 mai 1976 et à celui des marchandises en 1994 et déposée ensuite.',
        'reason' => 'fermeture au trafic voyageurs',
    ],
    [
        'line' => '538000', 'action' => 'add', 'type' => 'closure', 'date' => '1933-10-08', 'precision' => 'day',
        'from' => 'Aigrefeuille', 'to' => 'Rochefort',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Beno%C3%AEt_%C3%A0_La_Rochelle-Ville&oldid=235342687',
        'quote' => 'L\'embranchement Aigrefeuille-Rochefort sera cependant fermé à tout trafic (voyageurs et fret) le 8 octobre 1933, puis totalement déferré pour faire place après la Seconde Guerre mondiale à la route départementale 5 reliant Aigrefeuille à Rochefort.',
        'reason' => 'fermeture à tout trafic de l\'embranchement Aigrefeuille - Rochefort',
    ],
    [
        'line' => '603000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1964-05-31', 'precision' => 'day',
        'from' => 'Journet', 'to' => 'La Trimouille',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Montmorillon_%C3%A0_Saint-Aigny_-_Le_Blanc&oldid=239210121',
        'quote' => 'En 1935, la ligne n\'est utilisé que par un très faible service de marchandises ferme à ce trafic le 31 mai 1964 de Journet à la Trimouille.',
        'reason' => 'fermeture au trafic de marchandises de Journet à La Trimouille',
    ],
    [
        'line' => '608000', 'action' => 'add', 'type' => 'closure', 'date' => '1988-01-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Roumazi%C3%A8res-Loubert_au_Vigeant&oldid=222943796',
        'quote' => 'Le trafic des marchandises subsistera jusqu\'au 1 janvier 1988, date de la fermeture à tous trafics.',
        'reason' => 'fermeture à tous trafics',
    ],
    [
        'line' => '615100', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1960-04-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Labouheyre_%C3%A0_Mimizan-Plage&oldid=238254512',
        'quote' => 'Le trafic voyageurs est régulier jusqu\'au 1er avril 1960, date à laquelle il est supprimé en raison de la concurrence croissante de l\'automobile et des autocars départementaux.',
        'reason' => 'suppression du service voyageurs',
    ],
    [
        'line' => '627000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-05-15', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Condat_-_Le_Lardin_%C3%A0_Sarlat&oldid=240637977',
        'quote' => 'Le service voyageurs est interrompu le 15 mai 1939, la ligne est fermée le 17 décembre 1951 et déferrée en 1955.',
        'reason' => 'service voyageurs interrompu',
    ],
    [
        'line' => '630000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1940-12-31', 'precision' => 'day',
        'from' => 'Groléjac', 'to' => 'Gourdon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Carsac_%C3%A0_Gourdon&oldid=228421108',
        'quote' => 'Après le transfert de propriété à la SNCF, son nouveau propriétaire supprime le trafic voyageurs sur toute la ligne le 1 juillet 1938, puis le trafic marchandises le 31 décembre 1940 sur la section de Groléjac à Gourdon.',
        'reason' => 'fermeture du trafic marchandises de la section Groléjac - Gourdon',
    ],
    [
        'line' => '666000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1970-09-27', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lourdes_%C3%A0_Pierrefitte-Nestalas&oldid=230536605',
        'quote' => 'Le service voyageur fut fermé en 27 septembre 1970.',
        'reason' => 'fermeture du service voyageurs',
    ],
    [
        'line' => '673000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-04-18', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pamiers_%C3%A0_Limoux&oldid=238677089',
        'quote' => 'Le service voyageur fut fermé en 18 avril 1939 et le 15 mai 1939 pour la section Moulin-Neuf - Pamiers.',
        'reason' => 'fermeture du service voyageurs de Pamiers à Limoux',
    ],
    [
        'line' => '673000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1946-05-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pamiers_%C3%A0_Limoux&oldid=238677089',
        'quote' => 'Il y eut une réouverture temporaire du service voyageur du 5 mai 1941 au 6 mai 1946, effectivement la pénurie de transports routiers conduisit à ajouter une voiture au train de marchandises subsistant.',
        'reason' => 'fin de la réouverture temporaire du service voyageurs',
    ],
    [
        'line' => '674000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-04-18', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Moulin-Neuf_%C3%A0_Lavelanet&oldid=221071157',
        'quote' => 'Le service voyageur fut fermé en 18 avril 1939.',
        'reason' => 'fermeture du service voyageurs',
    ],
    [
        'line' => '674000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1941-05-05', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Moulin-Neuf_%C3%A0_Lavelanet&oldid=221071157',
        'quote' => 'Il y eut une réouverture temporaire du service voyageur du 5 mai 1941 au 6 mai 1946, effectivement la pénurie de transports routiers conduisit à ajouter une voiture au train de marchandises subsistant.',
        'reason' => 'réouverture temporaire du service voyageurs (pénurie de transports routiers)',
    ],
    [
        'line' => '674000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1946-05-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Moulin-Neuf_%C3%A0_Lavelanet&oldid=221071157',
        'quote' => 'Il y eut une réouverture temporaire du service voyageur du 5 mai 1941 au 6 mai 1946, effectivement la pénurie de transports routiers conduisit à ajouter une voiture au train de marchandises subsistant.',
        'reason' => 'fin de la réouverture temporaire du service voyageurs',
    ],
    [
        'line' => '675000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1941-05-05', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bram_%C3%A0_Belv%C3%A8ze&oldid=216790588',
        'quote' => 'Du 5 mai 1941 au 6 mai 1946 a eu lieu réouverture temporaire aux voyageurs avec des trains reliant Bram à Lavelanet et desservant Mirepoix, la pénurie de transports routiers conduisant à ajouter une voiture au train de marchandises.',
        'reason' => 'réouverture temporaire aux voyageurs entre Bram et Lavelanet',
    ],
    [
        'line' => '675000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1946-05-06', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bram_%C3%A0_Belv%C3%A8ze&oldid=216790588',
        'quote' => 'Du 5 mai 1941 au 6 mai 1946 a eu lieu réouverture temporaire aux voyageurs avec des trains reliant Bram à Lavelanet et desservant Mirepoix, la pénurie de transports routiers conduisant à ajouter une voiture au train de marchandises.',
        'reason' => 'fin de la réouverture temporaire aux voyageurs',
    ],
    [
        'line' => '719000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1980-05-31', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Souillac_%C3%A0_Viescamp-sous-Jall%C3%A8s&oldid=235400316',
        'quote' => 'Le 31 mai 1980, circule le dernier train de voyageurs, pendant les années suivantes la SNCF procède simplement au désherbage chimique et à la visite des ouvrages d\'art.',
        'reason' => 'dernier train de voyageurs',
    ],
    [
        'line' => '724000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1980-09-28', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cahors_%C3%A0_Capdenac&oldid=239827854',
        'quote' => 'Les dernières circulations SNCF ont lieu respectivement les 28 septembre 1980 et 1er octobre 1989.',
        'reason' => 'dernière circulation de voyageurs de la SNCF, fermeture de la ligne aux voyageurs',
    ],
    [
        'line' => '724000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1989-10-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cahors_%C3%A0_Capdenac&oldid=239827854',
        'quote' => 'Les dernières circulations SNCF ont lieu respectivement les 28 septembre 1980 et 1er octobre 1989.',
        'reason' => 'dernière circulation de fret de la SNCF',
    ],
    [
        'line' => '726000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1989-09-27', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Berthol%C3%A8ne_%C3%A0_Espalion&oldid=241030523',
        'quote' => 'Elle a été fermée au service des voyageurs le 5 décembre 1938 et au trafic fret le 27 septembre 1989.',
        'reason' => 'fermeture au trafic fret',
    ],
    [
        'line' => '770000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1953-07-15', 'precision' => 'day',
        'from' => 'Charolles', 'to' => 'Clermain',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Moulins_%C3%A0_M%C3%A2con&oldid=237434438',
        'quote' => 'Il ferma au service marchandises en plusieurs étapes : le tronçon reliant Charolles à Clermain est le premier à fermer le 15 juillet 1953, puis le tronçon reliant Clermain à Cluny ferme à son tour en 1957, et le tronçon reliant Paray-le-Monial à Charolles ferme le 1 juin 1987.',
        'reason' => 'fermeture au service marchandises du tronçon Charolles - Clermain',
    ],
    [
        'line' => '771000', 'action' => 'add', 'type' => 'closure_freight', 'date' => '1940-12-25', 'precision' => 'day',
        'from' => 'Saint-Gengoux', 'to' => 'Étiveau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27%C3%89tiveau_%C3%A0_Montchanin&oldid=235341693',
        'quote' => 'Le 25 décembre 1940, la section reliant Saint-Gengoux à Étiveau ferme au service marchandises.',
        'reason' => 'fermeture au service marchandises de la section Saint-Gengoux - Étiveau',
    ],
    [
        'line' => '772000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1968-11-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cluny_%C3%A0_Chalon-sur-Sa%C3%B4ne&oldid=237434386',
        'quote' => 'Elle est fermée au trafic voyageurs le 1 novembre 1968.',
        'reason' => 'fermeture au trafic voyageurs',
    ],
    [
        'line' => '775000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1934-01-01', 'precision' => 'day',
        'from' => 'Brignais', 'to' => 'Givors-Canal',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paray-le-Monial_%C3%A0_Givors-Canal&oldid=237061238',
        'quote' => 'Ainsi, dès le 1 janvier 1934, le service voyageur est supprimé entre Brignais et Givors.',
        'reason' => 'service voyageurs supprimé entre Brignais et Givors',
    ],
    [
        'line' => '840000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1939-09-02', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'réouverture temporaire aux voyageurs',
    ],
    [
        'line' => '840000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-11-30', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'fin de la première réouverture temporaire aux voyageurs',
    ],
    [
        'line' => '840000', 'action' => 'add', 'type' => 'reopening_passenger', 'date' => '1940-06-10', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'seconde réouverture temporaire aux voyageurs',
    ],
    [
        'line' => '840000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1940-11-04', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'fin de la seconde réouverture temporaire aux voyageurs',
    ],
    [
        'line' => '881000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1939-05-15', 'precision' => 'day',
        'from' => 'Saint-Germain-du-Plain', 'to' => 'Chalon-sur-Saône',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Germain-du-Plain_%C3%A0_Lons-le-Saunier&oldid=238375844',
        'quote' => 'Le trafic voyageurs a été supprimé le 1 juillet 1938 entre Lons-le-Saunier et Saint-Germain-du-Plain et le 15 mai 1939 entre cette gare et Chalon-sur-Saône.',
        'reason' => 'suppression du trafic voyageurs entre Saint-Germain-du-Plain et Chalon-sur-Saône',
    ],
    [
        'line' => '889000', 'action' => 'add', 'type' => 'closure', 'date' => '1940-06-19', 'precision' => 'day',
        'from' => 'Villebois', 'to' => 'Montalieu-Vercieu',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Amb%C3%A9rieu_%C3%A0_Montalieu-Vercieu&oldid=240595980',
        'quote' => 'Le 19 juin 1940, la section de Villebois à Montalieu ferme à la suite de la destruction par l\'armée française du pont sur le Rhône.',
        'reason' => 'fermeture de la section Villebois - Montalieu après la destruction du pont sur le Rhône',
    ],
    [
        'line' => '931000', 'action' => 'add', 'type' => 'closure_passenger', 'date' => '1938-10-02', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Orange_%C3%A0_Buis-les-Baronnies&oldid=241521093',
        'quote' => 'L\'exploitation des trains de voyageurs cesse le 2 octobre 1938 à la faveur d\'un service d\'autocars mis en place par la SNCF.',
        'reason' => 'cessation de l\'exploitation des trains de voyageurs, remplacés par des autocars',
    ],
    [
        'line' => '957000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1932-03-01', 'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bobigny_%C3%A0_Sucy_-_Bonneuil&oldid=239269412',
        'quote' => 'Ce n\'est que le 1 mars 1932 qu\'elle a été ouverte au service des voyageurs après la construction des gares intermédiaires.',
        'reason' => 'ouverture au service des voyageurs après la construction des gares intermédiaires',
    ],
    [
        'line' => '840000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'reopening', 'date' => '1939-11-30'],
        'label' => 'Fin de la réouverture temporaire aux voyageurs',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'la date est celle de la fin de la réouverture temporaire, pas d\'une réouverture',
    ],
    [
        'line' => '840000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'reopening_freight', 'date' => '1940-11-04'],
        'label' => 'Fin de la réouverture temporaire aux voyageurs',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bricon_%C3%A0_Ch%C3%A2tillon-sur-Seine&oldid=235377138',
        'quote' => 'Elle est toutefois rouverte du 2 septembre au 30 novembre 1939 et du 10 juin au 4 novembre 1940 et reste ouverte au service des marchandises.',
        'reason' => 'la date est celle de la fin de la réouverture temporaire, pas d\'une réouverture',
    ],
];
