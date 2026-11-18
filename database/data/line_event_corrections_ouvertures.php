<?php

/**
 * Corrections SOURCÉES des OUVERTURES par section (chantier du 2026-10-19) : lignes
 * pionnières (1827-1845) et lignes datées par leur seule première section (Wikidata P1619,
 * infobox), dont l'article donne pourtant les ouvertures section par section sous une forme
 * que l'extraction automatique ne lit pas (liste « Mises en service », tableau, texte).
 *
 * Même format que line_event_corrections.php (cf. son en-tête) ; appliqué par
 * vff:apply-event-corrections. Une source par correction (permalien), citation courte.
 * archeoferroviaire : années par section, avec crédit (J. Randé, F. Librini, K. Moreau) et
 * lien vers l'article, usage non commercial (cf. database/data/source_rights.php).
 *
 * Pas de tiret long dans les textes (consigne éditoriale) : les tirets des citations sont
 * rendus par « - ».
 *
 * @return list<array<string, mixed>>
 */

return (static function (): array {
    $wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

    // Permaliens des articles cités.
    $stelyon = $wp('Ligne de Saint-Étienne à Lyon', 239974225);
    $moret = $wp('Ligne de Moret - Veneux-les-Sablons à Lyon-Perrache', 240126993);
    $andrezieux = $wp('Ligne de Saint-Étienne à Andrézieux', 239572220);
    $cevennes = $wp('Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 240237870);
    $austerlitz = $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 236613197);
    $corbeil = $wp('Ligne de Villeneuve-Saint-Georges à Montargis', 236077985);
    $strasbale = $wp('Compagnie du chemin de fer de Strasbourg à Bâle', 240254440);
    $carcassonne = $wp('Ligne de Carcassonne à Rivesaltes', 240398534);
    $polt = $wp('Ligne des Aubrais - Orléans à Montauban-Ville-Bourbon', 235779400);
    $neussargues = $wp('Ligne de Béziers à Neussargues', 240391025);
    $penne = $wp('Ligne de Penne-d\'Agenais à Tonneins', 240282097);
    $oiry = $wp('Ligne de Oiry - Mareuil à Romilly-sur-Seine', 238819450);
    $bologne = $wp('Ligne de Bologne à Pagny-sur-Meuse', 240518941);
    $elbeuf = $wp('Ligne de Saint-Georges-Motel à Grand-Quevilly', 237211403);
    $gauburge = $wp('Ligne de Sainte-Gauburge à Mesnil-Mauger', 236766714);
    $portbou = $wp('Ligne de Narbonne à Port-Bou (frontière)', 238350351);
    $eygurande = $wp('Gare d\'Eygurande - Merlines', 238846095);
    $miecaze = $wp('Gare de Miécaze', 210576789);
    $stamand = $wp('Gare de Saint-Amand-Montrond - Orval', 228270753);
    $hirondelles = $wp('Ligne d\'Andelot-en-Montagne à La Cluse', 236687646);
    $vierzontours = $wp('Ligne de Vierzon à Saint-Pierre-des-Corps', 236330049);
    $benauge = $wp('Ligne de Bordeaux-Benauge à La Sauvetat-du-Dropt', 236503554);
    $briouze = $wp('Ligne de Briouze à Couterne', 233804148);
    $vallorbe = $wp('Ligne de Dijon-Ville à Vallorbe (frontière)', 237099102);
    $doleville = $wp('Gare de Dole-Ville', 229620089);
    $andelot = $wp('Gare d\'Andelot', 235030281);
    $frasne = $wp('Gare de Frasne', 238931079);
    $chartresbx = $wp('Ligne de Chartres à Bordeaux-Saint-Jean', 240356620);
    $pons = $wp('Gare de Pons', 235033822);
    $jonzac = $wp('Gare de Jonzac', 240118137);
    $montendre = $wp('Gare de Montendre', 230580626);

    $cevennesQuote = 'Dates de concession et d\'ouverture des sections de la ligne : ';
    $strasbaleQuote = 'Tableau « Date ouverture au public » (source : Bazaine) : ';
    $poltQuote = 'La ligne des Aubrais - Orléans à Montauban est constituée de différentes sections mises en service entre 1847 et 1893 : ';
    $neussarguesQuote = 'La compagnie met en service sa ligne par section au fur et à mesure de l\'avancement des travaux : ';

    return [
        // =================================================================================
        // 750000 Moret - Lyon-Perrache : le chemin de fer de Saint-Étienne à Lyon (Seguin),
        // ouvert par sections de 1830 à 1832. Seules les sections couvertes par OHM étaient
        // datées : l'ouverture « Le Coteau - Lyon-Perrache (tracé définitif) » de 1857 datait
        // le reste du tronçon.
        // =================================================================================
        [
            // Fiche-contenus (2026-10-20) : ouverture au seul fret (houille) ; voyageurs le 03/04/1832
            // (EraTimeline::FIRST_PASSENGER_SERVICE : aucun voyageur en France avant le 01/03/1832).
            'line' => '750000', 'action' => 'add', 'type' => 'opening_freight', 'date' => '1830-06-28', 'precision' => 'day',
            'from' => 'Givors', 'to' => 'Rive-de-Gier',
            'source_url' => $stelyon,
            'quote' => 'La première partie à ouvrir était entre Givors et Rive-de-Gier, qui fut achevée le 28 juin 1830.',
            'reason' => 'première section du chemin de fer de Saint-Étienne à Lyon (Seguin), ouverte aux marchandises',
        ],
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1832-04-03', 'precision' => 'day',
            'from' => 'Givors', 'to' => 'Rive-de-Gier',
            'source_url' => $stelyon,
            'quote' => 'Le 3 avril 1832, la section jusqu\'à Lyon a été employée pour le transport de marchandises, quelques passagers étant acceptés',
            'reason' => 'premiers voyageurs du chemin de fer de Saint-Étienne à Lyon, avec l\'ouverture de la section de Lyon (aucun avant 1832)',
        ],
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening', 'date' => '1832-04-03', 'precision' => 'day',
            'from' => 'Givors', 'to' => 'Lyon-Perrache',
            'source_url' => $stelyon,
            'quote' => 'Le 3 avril 1832, la section jusqu\'à Lyon a été employée pour le transport de marchandises, quelques passagers étant acceptés',
            'reason' => 'section de Givors à Lyon du chemin de fer de Saint-Étienne à Lyon',
        ],
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening', 'date' => '1832-10-01', 'precision' => 'day',
            'from' => 'Rive-de-Gier', 'to' => 'Saint-Étienne-Châteaucreux',
            'source_url' => $stelyon,
            'quote' => 'La dernière section, à Saint-Étienne, a été ouverte le 1er octobre 1832 pour l\'usage de passagers seulement, le fret étant accepté quelques mois plus tard.',
            'reason' => 'dernière section du chemin de fer de Saint-Étienne à Lyon (tracé actuel par Châteaucreux, ancien terminus au Pont-de-l\'Âne à 2 km)',
        ],
        // Saint-Étienne - Andrézieux (1827) : sur le tracé actuel, La Fouillouse - Saint-Just-sur-Loire
        // (La Terrasse - La Fouillouse est le tracé de 1857, cf. OHM ; l'ancien passait par Villars).
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening_freight', 'date' => '1827-06-30', 'precision' => 'day',
            'from' => 'La Fouillouse', 'to' => 'Saint-Just-sur-Loire',
            'source_url' => $moret,
            'quote' => 'Le tronçon entre Saint-Étienne et Saint-Just-sur-Loire est issu de la concession [...] de la ligne de Saint-Étienne à Andrézieux [...]. Cette ligne a été mise en service le 30 juin 1827',
            'reason' => 'ligne de Saint-Étienne à Andrézieux, première voie ferrée de France, sur la partie commune avec le tracé actuel ; transport de la houille (voyageurs le 01/03/1832)',
        ],
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1832-03-01', 'precision' => 'day',
            'from' => 'La Fouillouse', 'to' => 'Saint-Just-sur-Loire',
            'source_url' => $andrezieux,
            'quote' => 'Le 1er mars 1832, la ligne est ouverte aux voyageurs.',
            'reason' => 'premier service voyageurs de la ligne de Saint-Étienne à Andrézieux, sur la partie commune avec le tracé actuel',
        ],
        // Andrézieux - Roanne (1833) : de Saint-Just-sur-Loire à Balbigny sur le tracé actuel ; au-delà,
        // l'ancien tracé par Neulise (ligne 3161) a été remplacé en 1857-1858 par la vallée de la Loire.
        [
            'line' => '750000', 'action' => 'add', 'type' => 'opening', 'date' => '1833-03-01', 'precision' => 'month',
            'from' => 'Saint-Just-sur-Loire', 'to' => 'Balbigny',
            'source_url' => $moret,
            'quote' => 'Le tronçon entre Saint-Just-sur-Loire et Le Coteau est issu de la concession de la ligne « d\'Andrézieux à Roanne » [...]. Cette ligne a été mise en service en totalité en mars 1833 par la Compagnie du chemin de fer de la Loire.',
            'reason' => 'chemin de fer d\'Andrézieux à Roanne, partie reprise par le tracé actuel (Balbigny - Le Coteau : tracé de 1857-1858)',
        ],
        [
            'line' => '4014', // Ligne de Saint-Etienne-Terrasse à La Fouillouse : ancien tracé de 1827 par Villars
            'action' => 'add', 'type' => 'opening_freight', 'date' => '1827-06-30', 'precision' => 'day',
            'source_url' => $andrezieux,
            'quote' => 'Longue de vingt-et-un kilomètres avec ses embranchements industriels et ouverte le 30 juin 1827, cette voie ferrée à vocation industrielle était destinée au transport de la houille du Forez',
            'reason' => 'tracé d\'origine de la ligne de Saint-Étienne à Andrézieux entre La Terrasse et La Fouillouse par Villars (archeoferroviaire), remplacé en 1857 (fermeture OHM) ; toute la ligne',
        ],
        [
            'line' => '4014', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1832-03-01', 'precision' => 'day',
            'source_url' => $andrezieux,
            'quote' => 'Le 1er mars 1832, la ligne est ouverte aux voyageurs.',
            'reason' => 'premier service voyageurs de la ligne de Saint-Étienne à Andrézieux (tracé d\'origine par Villars) ; toute la ligne',
        ],
        [
            'line' => '780000', 'action' => 'add', 'type' => 'opening_passenger', 'date' => '1832-03-01', 'precision' => 'day',
            'source_url' => $andrezieux,
            'quote' => 'Le 1er mars 1832, la ligne est ouverte aux voyageurs.',
            'reason' => 'premier service voyageurs de la ligne de Saint-Étienne à Andrézieux, dont le terminus était au Pont-de-l\'Âne ; toute la ligne',
        ],
        [
            'line' => '3161', // Ligne de Balbigny à L'Hôpital : ancien tracé d'Andrézieux - Roanne par Neulise
            'action' => 'add', 'type' => 'opening', 'date' => '1833-03-01', 'precision' => 'month',
            'source_url' => $moret,
            'quote' => 'la ligne « d\'Andrézieux à Roanne » [...]. Cette ligne a été mise en service en totalité en mars 1833 par la Compagnie du chemin de fer de la Loire.',
            'reason' => 'ancien tracé du chemin de fer d\'Andrézieux à Roanne par Neulise, abandonné en 1857 (fermeture OHM) ; toute la ligne ouverte en mars 1833',
        ],

        // =================================================================================
        // 790000 Saint-Germain-des-Fossés - Nîmes (ligne des Cévennes) : la chronologie de l'article
        // (liste en colonnes) n'était pas extraite ; seule l'ouverture Wikidata 1840 (Alès - Nîmes)
        // datait toute la ligne.
        // =================================================================================
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1840-08-19', 'precision' => 'day',
            'from' => 'Alès', 'to' => 'Nîmes', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'19 août 1840 : Alès - Nîmes (47 km)',
            'reason' => 'chemin de fer d\'Alais à Beaucaire (Talabot), section d\'Alès à Nîmes',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1841-01-01', 'precision' => 'month',
            'from' => 'Alès', 'to' => 'La Levade', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'janvier 1841 : Alès - La Levade (17 km, concession le 12 mai 1836)',
            'reason' => 'chemin de fer d\'Alais à la Grand-Combe',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-05-07', 'precision' => 'day',
            'from' => 'Saint-Germain-des-Fossés', 'to' => 'Clermont-Ferrand', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'7 mai 1855 : Saint-Germain-des-Fossés - Gannat - Clermont-Ferrand (65 km, concession le 27 mars 1852)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-07-02', 'precision' => 'day',
            'from' => 'Clermont-Ferrand', 'to' => 'Issoire', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'2 juillet 1855 : Clermont-Ferrand - Issoire (35 km, concession le 21 avril 1853)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-09-03', 'precision' => 'day',
            'from' => 'Issoire', 'to' => 'Brassac-les-Mines', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'3 septembre 1855 : Issoire - Brassac-les-Mines (18 km, concession le 21 avril 1853)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1856-05-08', 'precision' => 'day',
            'from' => 'Brassac-les-Mines', 'to' => 'Arvant', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'8 mai 1856 : Brassac-les-Mines - Arvant (6 km, concession le 21 avril 1853)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1857-05-01', 'precision' => 'day',
            'from' => 'Arvant', 'to' => 'Brioude', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'1 mai 1857 : Arvant - Brioude (10 km, concession le 7 avril 1855)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1866-12-10', 'precision' => 'day',
            'from' => 'Brioude', 'to' => 'Langeac', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'10 décembre 1866 : Brioude - Langeac (31 km, concession le 9 avril 1862)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-08-12', 'precision' => 'day',
            'from' => 'Villefort', 'to' => 'La Levade', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'12 août 1867 : Villefort - La Levade (31 km, concession le 9 avril 1862)',
            'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '790000', 'action' => 'add', 'type' => 'opening', 'date' => '1870-05-16', 'precision' => 'day',
            'from' => 'Langeac', 'to' => 'Villefort', 'source_url' => $cevennes,
            'quote' => $cevennesQuote.'16 mai 1870 : Langeac - Villefort (107 km, concession le 9 avril 1862)',
            'reason' => 'chronologie de l\'article',
        ],

        // =================================================================================
        // Paris - Corbeil (Compagnie de Paris à Orléans), septembre 1840 : Paris - Juvisy (570000)
        // et Juvisy - Corbeil (745000). L'événement Wikipédia « Juvisy - Corbeil 1840 » était posé
        // sur 570000, où Corbeil n'est pas : 745000 n'avait aucune date avant 1863.
        // =================================================================================
        [
            'line' => '570000', 'action' => 'add', 'type' => 'opening', 'date' => '1840-09-20', 'precision' => 'day',
            'from' => 'Paris-Austerlitz', 'to' => 'Juvisy', 'source_url' => $austerlitz,
            'quote' => 'La section de l\'embarcadère d\'Orléans à Paris (future gare d\'Orléans, puis gare d\'Austerlitz) à Juvisy est ouverte à l\'exploitation le 20 septembre 1840, en même temps que l\'antenne de Juvisy à Corbeil',
            'reason' => 'première section de la ligne de Paris à Orléans',
        ],
        [
            'line' => '570000', 'action' => 'add', 'type' => 'opening', 'date' => '1843-05-05', 'precision' => 'day',
            'from' => 'Juvisy', 'to' => 'Les Aubrais-Orléans', 'source_url' => $austerlitz,
            'quote' => 'La section de Juvisy à Orléans est ouverte trois ans plus tard, le 5 mai 1843',
            'reason' => 'achèvement de la ligne de Paris à Orléans',
        ],
        [
            'line' => '570000', 'action' => 'neutralize',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1847-07-20'],
            'label' => 'Ouverture d\'Orléans à Bourges par Vierzon (ligne 590000) : Paris - Orléans devient un tronc commun',
            'source_url' => $austerlitz,
            'quote' => 'Le 20 juillet 1847, l\'ouverture de la ligne d\'Orléans à Bourges par Vierzon par la Compagnie du chemin de fer du Centre fit de la section de Paris à Orléans un tronc commun',
            'reason' => 'ouverture d\'une autre ligne, pas de Paris - Orléans (ouverte en 1840-1843)',
        ],
        [
            'line' => '745000', 'action' => 'add', 'type' => 'opening', 'date' => '1840-09-17', 'precision' => 'day',
            'from' => 'Juvisy', 'to' => 'Corbeil-Essonnes', 'source_url' => $corbeil,
            'quote' => 'Le 17 septembre 1840, la Compagnie du chemin de fer de Paris à Orléans ouvre la ligne de Paris-Austerlitz à Corbeil suivant la rive gauche de la Seine.',
            'reason' => 'ligne de Juvisy à Corbeil (1840-1857), embranchement de la ligne de Paris à Orléans',
        ],
        [
            'line' => '745000', 'action' => 'relocate',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1865-01-05'],
            'from' => 'Corbeil-Essonnes', 'to' => 'Maisse', 'source_url' => $corbeil,
            'quote' => 'Le prolongement jusqu\'à La Ferté-Alais et Maisse, long de trente-trois kilomètres, ouvre le 5 janvier 1865.',
            'reason' => 'prolongement de Corbeil à Maisse par La Ferté-Alais (seule l\'arrivée était citée)',
        ],
        [
            'line' => '745000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-05-06', 'precision' => 'day',
            'from' => 'Maisse', 'to' => 'Montargis', 'source_url' => $corbeil,
            'quote' => 'Les travaux du dernier tronçon de Maisse à Montargis, long de soixante kilomètres, [...] s\'achèvent en mai 1867. Le 6 mai, le tronçon est mis en service',
            'reason' => 'dernier tronçon de la ligne',
        ],

        // =================================================================================
        // 115000 Strasbourg - Saint-Louis (Koechlin) : ouverte par sections en 1840-1841. L'article
        // de la ligne ne donnait que l'ouverture complète (22/08/1841) ; celui de la compagnie
        // donne les dates par section (tableau, d'après Bazaine). Concordant avec de.wikipedia
        // (Statistique centrale des chemins de fer, 1869) : Benfeld - Colmar et Mulhouse - Saint-Louis
        // en 1840, le reste en 1841.
        // =================================================================================
        [
            'line' => '115000', 'action' => 'add', 'type' => 'opening', 'date' => '1840-10-19', 'precision' => 'day',
            'from' => 'Benfeld', 'to' => 'Colmar', 'source_url' => $strasbale,
            'quote' => $strasbaleQuote.'Benfeld à Colmar : 19 octobre 1840',
            'reason' => 'première section du chemin de fer de Strasbourg à Bâle',
        ],
        [
            'line' => '115000', 'action' => 'add', 'type' => 'opening', 'date' => '1840-10-26', 'precision' => 'day',
            'from' => 'Mulhouse-Ville', 'to' => 'Saint-Louis', 'source_url' => $strasbale,
            'quote' => $strasbaleQuote.'Mulhouse à Saint-Louis : 26 octobre 1840',
            'reason' => 'section de Mulhouse à Saint-Louis (inaugurée le 25 octobre 1840)',
        ],
        [
            'line' => '115000', 'action' => 'add', 'type' => 'opening', 'date' => '1841-05-01', 'precision' => 'day',
            'from' => 'Strasbourg-Koenigshoffen', 'to' => 'Benfeld', 'source_url' => $strasbale,
            'quote' => $strasbaleQuote.'Koenigshoffen à Benfeld : 1er mai 1841',
            'reason' => 'section de Koenigshoffen (Strasbourg) à Benfeld',
        ],
        [
            'line' => '115000', 'action' => 'add', 'type' => 'opening', 'date' => '1841-08-15', 'precision' => 'day',
            'from' => 'Colmar', 'to' => 'Mulhouse-Ville', 'source_url' => $strasbale,
            'quote' => $strasbaleQuote.'Colmar à Mulhouse : 15 août 1841',
            'reason' => 'dernière section ; service de bout en bout le 22 août 1841',
        ],

        // =================================================================================
        // 676000 Carcassonne - Rivesaltes : la dépose « vers 1990 » est celle du défilé de
        // Pierre-Lys (Quillan - Saint-Martin-Lys, déclassé en 1991), pas d'Axat - Saint-Martin-Lys,
        // rouvert en 1960 et parcouru par le Train du Pays Cathare : posée de Quillan à Axat, elle
        // faisait passer 2,5 km de « déposée » (1990) à « en service » (2000).
        // =================================================================================
        [
            'line' => '676000', 'action' => 'relocate',
            'match' => ['source' => 'wikipedia', 'type' => 'dismantling', 'date' => '1990-01-01'],
            'from' => 'Quillan', 'to' => 'Saint-Martin-Lys', 'source_url' => $carcassonne,
            'quote' => 'démantèlement de la traversée du défilé de Pierre-Lys entre Quillan et Axat (la plateforme existe encore, mais la voie a été complètement déposée vers 1990).',
            'reason' => 'le défilé de Pierre-Lys est entre Quillan et Saint-Martin-Lys (section déclassée le 16/12/1991, PK 402,0 à 409,4) ; Axat - Saint-Martin-Lys, rouvert en 1960, garde sa voie',
        ],
        // =================================================================================
        // Lignes datées par leur seule première section (Wikidata P1619 / infobox) : excédent des
        // ères 1870-1890. Ouvertures par section tirées de l'article (liste non extraite par le
        // parseur) ou des articles des gares.
        // =================================================================================

        // 590000 POLT : Limoges - Brive - Cahors - Montauban ouvert de 1884 à 1893, affiché dès 1847.
        // (« Vierzon - Limoges, 2 juin 1856 » n'est pas repris : Vierzon - Châteauroux date de 1847.)
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1847-07-20', 'precision' => 'day',
            'from' => 'Les Aubrais-Orléans', 'to' => 'Vierzon-Ville', 'source_url' => $polt,
            'quote' => $poltQuote.'Orléans - Vierzon, 20 juillet 1847', 'reason' => 'liste « Mises en service » de l\'article',
        ],
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1893-07-01', 'precision' => 'day',
            'from' => 'Limoges-Bénédictins', 'to' => 'Brive-la-Gaillarde', 'source_url' => $polt,
            'quote' => $poltQuote.'Limoges - Uzerche - Brive-la-Gaillarde, le 1 juillet 1893', 'reason' => 'liste « Mises en service » de l\'article',
        ],
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1891-06-01', 'precision' => 'day',
            'from' => 'Brive-la-Gaillarde', 'to' => 'Souillac', 'source_url' => $polt,
            'quote' => $poltQuote.'Brive-la-Gaillarde - Souillac, le 1 juin 1891', 'reason' => 'liste « Mises en service » de l\'article',
        ],
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1889-06-16', 'precision' => 'day',
            'from' => 'Souillac', 'to' => 'Cazoulès', 'source_url' => $polt,
            'quote' => $poltQuote.'Souillac - Cazoulès, le 16 juin 1889', 'reason' => 'liste « Mises en service » de l\'article',
        ],
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1891-07-01', 'precision' => 'day',
            'from' => 'Cazoulès', 'to' => 'Cahors', 'source_url' => $polt,
            'quote' => $poltQuote.'Cazoulès - Cahors, 1 juillet 1891', 'reason' => 'liste « Mises en service » de l\'article',
        ],
        [
            'line' => '590000', 'action' => 'add', 'type' => 'opening', 'date' => '1884-04-10', 'precision' => 'day',
            'from' => 'Cahors', 'to' => 'Montauban-Ville-Bourbon', 'source_url' => $polt,
            'quote' => $poltQuote.'Cahors - Montauban-Ville-Bourbon, 10 avril 1884', 'reason' => 'liste « Mises en service » de l\'article',
        ],

        // 722000 Béziers - Neussargues : prolongée de 1872 à 1888 au-delà de La Tour-sur-Orb.
        // (La Tour-sur-Orb - Le Bousquet-d'Orb, 11/05/1872 : la gare du Bousquet-d'Orb est sur la commune de
        // La Tour-sur-Orb, portion confondue ; couverte par Bédarieux - La Tour-sur-Orb 1858.)
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1874-10-18', 'precision' => 'day',
            'from' => 'Le Bousquet-d\'Orb', 'to' => 'Millau', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'18 octobre 1874 du Bousquet-d\'Orb à Millau', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1880-05-14', 'precision' => 'day',
            'from' => 'Millau', 'to' => 'Sévérac-le-Château', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'14 mai 1880, de Millau à Sévérac-le-Château', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1883-08-14', 'precision' => 'day',
            'from' => 'Sévérac-le-Château', 'to' => 'Banassac-La Canourgue', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'14 août 1883, de Sévérac-le-Château à Banassac-La Canourgue', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1884-05-03', 'precision' => 'day',
            'from' => 'Banassac-La Canourgue', 'to' => 'Marvejols', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'3 mai 1884, de Banassac-La Canourgue à Marvejols', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1887-05-09', 'precision' => 'day',
            'from' => 'Marvejols', 'to' => 'Saint-Chély-d\'Apcher', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'9 mai 1887, de Marvejols à Saint-Chély', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1888-05-27', 'precision' => 'day',
            'from' => 'Saint-Chély-d\'Apcher', 'to' => 'Saint-Flour-Chaudes-Aigues', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'27 mai 1888, de Saint-Chély à Saint-Flour', 'reason' => 'liste des mises en service de l\'article',
        ],
        [
            'line' => '722000', 'action' => 'add', 'type' => 'opening', 'date' => '1888-11-10', 'precision' => 'day',
            'from' => 'Saint-Flour-Chaudes-Aigues', 'to' => 'Neussargues', 'source_url' => $neussargues,
            'quote' => $neussarguesQuote.'10 novembre 1888, de Saint-Flour à Neussargues', 'reason' => 'liste des mises en service de l\'article',
        ],

        // 695000 Bourges - Miécaze : seul Bourges - Montluçon (1861) existait avant 1882 ; tout le
        // reste était affiché dès 1861 (Wikidata P1619).
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1861-12-09', 'precision' => 'day',
            'from' => 'La Chapelle-Saint-Ursin-Bourg', 'to' => 'Montluçon-Ville', 'source_url' => $stamand,
            'quote' => 'La station de « Saint-Amand » est mise en service le 9 décembre 1861 par la Compagnie du chemin de fer de Paris à Orléans (PO), lorsqu\'elle ouvre à l\'exploitation sa ligne de Bourges à Montluçon.',
            'reason' => 'article de la gare de Saint-Amand-Montrond - Orval (le tracé de la ligne commence à La Chapelle-Saint-Ursin, à la sortie de Bourges)',
        ],
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1885-10-25', 'precision' => 'day',
            'from' => 'Montluçon-Ville', 'to' => 'Auzances', 'source_url' => $eygurande,
            'quote' => 'les travaux menés rapidement de sorte que le 25 octobre 1885 la section de Montluçon à Auzances était ouverte à l\'exploitation.',
            'reason' => 'article de la gare d\'Eygurande - Merlines',
        ],
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1887-06-13', 'precision' => 'day',
            'from' => 'Auzances', 'to' => 'Eygurande-Merlines', 'source_url' => $eygurande,
            'quote' => 'Le 13 juin 1887, la section terminale d\'Auzances à Eygurande - Merlines était ouverte au trafic.',
            'reason' => 'article de la gare d\'Eygurande - Merlines',
        ],
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1882-11-05', 'precision' => 'day',
            'from' => 'Eygurande-Merlines', 'to' => 'Bort-les-Orgues', 'source_url' => $eygurande,
            'quote' => 'Le 5 novembre 1882, la gare d\'Eygurande - Merlines s\'ouvre vers le sud et Largnac sur ce qui deviendra la ligne de Bourges à Miécaze, avant de se prolonger vers Mauriac en juillet 1893 puis vers Miécaze.',
            'reason' => 'ligne d\'Eygurande à Largnac par Bort-les-Orgues (gare de Bort mise en service en 1882)',
        ],
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1893-07-01', 'precision' => 'month',
            'from' => 'Bort-les-Orgues', 'to' => 'Mauriac', 'source_url' => $eygurande,
            'quote' => 'la gare d\'Eygurande - Merlines s\'ouvre vers le sud et Largnac [...], avant de se prolonger vers Mauriac en juillet 1893 puis vers Miécaze.',
            'reason' => 'prolongement vers Mauriac (infobox de la ligne : achèvement en 1893)',
        ],
        [
            'line' => '695000', 'action' => 'add', 'type' => 'opening', 'date' => '1891-12-24', 'precision' => 'day',
            'from' => 'Mauriac', 'to' => 'Miécaze', 'source_url' => $miecaze,
            'quote' => 'Elle devient une gare de bifurcation le 24 décembre 1891 avec l\'ouverture de la ligne de Mauriac à Miécaze',
            'reason' => 'article de la gare de Miécaze',
        ],

        // 634000 Penne-d'Agenais - Tonneins : Villeneuve - Tonneins ouvert en 1894.
        [
            'line' => '634000', 'action' => 'add', 'type' => 'opening', 'date' => '1869-12-20', 'precision' => 'day',
            'from' => 'Penne-d\'Agenais', 'to' => 'Villeneuve-sur-Lot', 'source_url' => $penne,
            'quote' => 'Le 20 décembre 1869 entre Penne-d\'Agenais et Villeneuve-sur-Lot.', 'reason' => 'ouvertures listées dans l\'article',
        ],
        [
            'line' => '634000', 'action' => 'add', 'type' => 'opening', 'date' => '1894-07-01', 'precision' => 'day',
            'from' => 'Villeneuve-sur-Lot', 'to' => 'Tonneins', 'source_url' => $penne,
            'quote' => 'Le 1 juillet 1894 entre Villeneuve-sur-Lot et Tonneins.', 'reason' => 'ouvertures listées dans l\'article',
        ],

        // 010000 Oiry-Mareuil - Romilly-sur-Seine : ouverte par sections de 1870 à 1872.
        [
            'line' => '010000', 'action' => 'add', 'type' => 'opening', 'date' => '1870-08-12', 'precision' => 'day',
            'from' => 'Oiry-Mareuil', 'to' => 'Bergères-lès-Vertus', 'source_url' => $oiry,
            'quote' => '12 août 1870 : de Oiry à Bergères-lès-Vertus', 'reason' => 'ouvertures listées dans l\'article',
        ],
        [
            'line' => '010000', 'action' => 'add', 'type' => 'opening', 'date' => '1871-08-20', 'precision' => 'day',
            'from' => 'Bergères-lès-Vertus', 'to' => 'Fère-Champenoise', 'source_url' => $oiry,
            'quote' => '20 août 1871 : de Bergères-les-Vertus à Fère-Champenoise', 'reason' => 'ouvertures listées dans l\'article',
        ],
        [
            'line' => '010000', 'action' => 'add', 'type' => 'opening', 'date' => '1871-11-21', 'precision' => 'day',
            'from' => 'Fère-Champenoise', 'to' => 'Sézanne', 'source_url' => $oiry,
            'quote' => '21 novembre 1871 : de Fère-Champenoise à Sézanne', 'reason' => 'ouvertures listées dans l\'article',
        ],
        [
            'line' => '010000', 'action' => 'add', 'type' => 'opening', 'date' => '1872-05-11', 'precision' => 'day',
            'from' => 'Sézanne', 'to' => 'Romilly-sur-Seine', 'source_url' => $oiry,
            'quote' => '11 mai 1872 : de Sézanne à Romilly-sur-Seine', 'reason' => 'ouvertures listées dans l\'article',
        ],

        // 026000 Bologne - Pagny-sur-Meuse : Neufchâteau - Pagny ouvert en 1873.
        [
            'line' => '026000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-08-14', 'precision' => 'day',
            'from' => 'Bologne', 'to' => 'Neufchâteau', 'source_url' => $bologne,
            'quote' => 'Dates d\'ouverture : de Bologne à Neufchâteau, le 14 août 1867', 'reason' => 'dates d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '026000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-02-01', 'precision' => 'day',
            'from' => 'Vaucouleurs', 'to' => 'Pagny-sur-Meuse', 'source_url' => $bologne,
            'quote' => 'Dates d\'ouverture : [...] de Vaucouleurs à Pagny-sur-Meuse, le 1 février 1873', 'reason' => 'dates d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '026000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-04-14', 'precision' => 'day',
            'from' => 'Neufchâteau', 'to' => 'Vaucouleurs', 'source_url' => $bologne,
            'quote' => 'Dates d\'ouverture : [...] de Neufchâteau à Vaucouleurs, le 14 avril 1873.', 'reason' => 'dates d\'ouverture listées dans l\'article',
        ],

        // 370000 Saint-Georges-Motel - Grand-Quevilly : assemblage de lignes ouvertes de 1872 à 1883.
        [
            'line' => '370000', 'action' => 'add', 'type' => 'opening', 'date' => '1872-05-10', 'precision' => 'day',
            'from' => 'Louviers', 'to' => 'Acquigny', 'source_url' => $elbeuf,
            'quote' => 'La ligne actuelle est l\'assemblage de lignes ouvertes en différentes étapes dans les années 1870-1880 : entre Louviers et Acquigny, le 10 mai 1872',
            'reason' => 'étapes d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '370000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-05-01', 'precision' => 'day',
            'from' => 'Saint-Georges-Motel', 'to' => 'Acquigny', 'source_url' => $elbeuf,
            'quote' => 'entre Saint-Georges-Motel et Acquigny, le 1 mai 1873', 'reason' => 'étapes d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '370000', 'action' => 'add', 'type' => 'opening', 'date' => '1875-08-15', 'precision' => 'day',
            'from' => 'Louviers', 'to' => 'Caudebec-lès-Elbeuf', 'source_url' => $elbeuf,
            'quote' => 'entre Louviers et Caudebec-lès-Elbeuf, le 15 août 1875', 'reason' => 'étapes d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '370000', 'action' => 'add', 'type' => 'opening', 'date' => '1876-01-14', 'precision' => 'day',
            'from' => 'Caudebec-lès-Elbeuf', 'to' => 'Elbeuf-Ville', 'source_url' => $elbeuf,
            'quote' => 'entre Caudebec-lès-Elbeuf et Elbeuf-Ville, le 14 janvier 1876', 'reason' => 'étapes d\'ouverture listées dans l\'article',
        ],
        [
            'line' => '370000', 'action' => 'add', 'type' => 'opening', 'date' => '1883-01-08', 'precision' => 'day',
            'from' => 'Elbeuf-Ville', 'to' => 'Rouen-Orléans', 'source_url' => $elbeuf,
            'quote' => 'entre Rouen-Orléans et Elbeuf-Ville le 8 janvier 1883', 'reason' => 'étapes d\'ouverture listées dans l\'article',
        ],

        // 402000 Sainte-Gauburge - Mesnil-Mauger : ouverte en trois temps (1880-1881).
        [
            'line' => '402000', 'action' => 'add', 'type' => 'opening', 'date' => '1880-11-22', 'precision' => 'day',
            'from' => 'Sainte-Gauburge', 'to' => 'Gacé', 'source_url' => $gauburge,
            'quote' => 'La ligne est ouverte en trois temps : Le 22 novembre 1880, section de Sainte-Gauburge à Gacé', 'reason' => 'ouverture en trois temps (article)',
        ],
        [
            'line' => '402000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-10-09', 'precision' => 'day',
            'from' => 'Gacé', 'to' => 'Ticheville-Le Sap', 'source_url' => $gauburge,
            'quote' => 'Le 9 octobre 1881, section de Gacé à Ticheville-Le Sap', 'reason' => 'ouverture en trois temps (article)',
        ],
        [
            'line' => '402000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-12-30', 'precision' => 'day',
            'from' => 'Ticheville-Le Sap', 'to' => 'Le Mesnil-Mauger', 'source_url' => $gauburge,
            'quote' => 'Le 30 décembre 1881, section de Ticheville-Le Sap au Mesnil-Mauger.', 'reason' => 'ouverture en trois temps (article)',
        ],

        // 677000 Narbonne - Port-Bou : la première section (Narbonne - gare du Vernet, aux portes
        // de Perpignan, 20/02/1858) n'était pas extraite ; Le Vernet - Perpignan suit le 12/07/1858.
        [
            'line' => '677000', 'action' => 'add', 'type' => 'opening', 'date' => '1858-02-20', 'precision' => 'day',
            'from' => 'Narbonne', 'to' => 'Perpignan', 'source_url' => $portbou,
            'quote' => '20 février 1858, mise en service de Narbonne à la gare du Vernet (Perpignan), 12 juillet 1858, mise en service du Vernet à Perpignan',
            'reason' => 'chronologie de l\'article (gare du Vernet, au nord de Perpignan : même année)',
        ],
        // =================================================================================
        // 878000 Andelot-en-Montagne - La Cluse (ligne des Hirondelles et Saint-Claude - La Cluse) :
        // la chronologie de l'article ne passait que pour 1890-1900 ; Andelot - Champagnole (1867),
        // Oyonnax - La Cluse (1885), Saint-Claude - Oyonnax (1889) et Morez - Saint-Claude (1912)
        // manquaient (Morez - Saint-Claude apparaissait dès l'ouverture Wikidata 1867).
        // =================================================================================
        [
            'line' => '878000', 'action' => 'add', 'type' => 'opening', 'date' => '1867-07-14', 'precision' => 'day',
            'from' => 'Andelot', 'to' => 'Champagnole', 'source_url' => $hirondelles,
            'quote' => 'Chronologie : 14 juillet 1867, d\'Andelot à Champagnole', 'reason' => 'embranchement d\'Andelot à Champagnole (Palau : « Andelot-Champagnole : 15 juillet 1867 »)',
        ],
        [
            'line' => '878000', 'action' => 'add', 'type' => 'opening', 'date' => '1885-05-16', 'precision' => 'day',
            'from' => 'Oyonnax', 'to' => 'La Cluse', 'source_url' => $hirondelles,
            'quote' => 'Chronologie : 16 mai 1885, d\'Oyonnax à La Cluse', 'reason' => 'chronologie de l\'article (même date dans « Dates d\'ouverture des autres sections »)',
        ],
        [
            'line' => '878000', 'action' => 'add', 'type' => 'opening', 'date' => '1889-07-10', 'precision' => 'day',
            'from' => 'Saint-Claude', 'to' => 'Oyonnax', 'source_url' => $hirondelles,
            'quote' => 'Chronologie : 10 juillet 1889, de Saint-Claude à Oyonnax', 'reason' => 'chronologie de l\'article',
        ],
        [
            'line' => '878000', 'action' => 'add', 'type' => 'opening', 'date' => '1912-08-01', 'precision' => 'month',
            'from' => 'Morez', 'to' => 'Saint-Claude', 'source_url' => $hirondelles,
            'quote' => 'Chronologie : 1 août 1912, de Morez à Saint-Claude. [...] Dates d\'ouverture des autres sections : de Morez à Saint-Claude : 10 août 1912',
            'reason' => 'dernière section des Hirondelles (1er ou 10 août 1912 selon le passage de l\'article : mois retenu)',
        ],

        // 981000 Paris-Nord - Paris-Gare-de-Lyon (tunnels du RER B et D) : code SNCF réel ; ouvertures 1981 et
        // 1995 déjà corrigées dans line_event_corrections.php.
        // 593000 Vierzon - Saint-Pierre-des-Corps : « Construite en 1860 et mise en service le 18 octobre
        // 1869 » : l'année de construction était lue comme une ouverture de toute la ligne (ère 1860).
        [
            'line' => '593000', 'action' => 'neutralize',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1860-01-01'],
            'label' => 'Construction de la ligne', 'source_url' => $vierzontours,
            'quote' => 'Construite en 1860 et mise en service le 18 octobre 1869, la ligne a d\'abord été à voie unique',
            'reason' => '1860 est l\'année de construction ; ouverture le 18 octobre 1869 (Wikidata : 1869)',
        ],

        // 637000 Bordeaux-Bénauge - La Sauvetat-du-Dropt : Bordeaux - La Sauve au printemps 1873,
        // La Sauve - La Sauvetat-du-Dropt (prolongement vers Eymet, plan Freycinet) en 1899.
        // L'« ouverture » 1870 de l'article est la guerre de 1870 qui retarde les travaux.
        [
            'line' => '637000', 'action' => 'add', 'type' => 'opening', 'date' => '1873-01-01', 'precision' => 'year',
            'from' => 'Bordeaux-Bénauge', 'to' => 'La Sauve', 'source_url' => $benauge,
            'quote' => 'Après avoir subi des retards du fait de la guerre de 1870-71, la ligne est ouverte au public au printemps 1873.',
            'reason' => 'ligne d\'intérêt local de Bordeaux à La Sauve',
        ],
        [
            'line' => '637000', 'action' => 'add', 'type' => 'opening', 'date' => '1899-01-01', 'precision' => 'year',
            'from' => 'La Sauve', 'to' => 'La Sauvetat-du-Dropt', 'source_url' => $benauge,
            'quote' => 'La ligne de La Sauve à Eymet est déclarée d\'utilité publique et concédée à titre définitif à la compagnie du chemin de fer de Paris à Orléans par une loi le 30 juillet 1885. Ce prolongement sera inauguré en 1899.',
            'reason' => 'prolongement de La Sauve vers Eymet par La Sauvetat-du-Dropt',
        ],
        [
            'line' => '637000', 'action' => 'neutralize',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1870-01-01'],
            'label' => 'Travaux retardés par la guerre de 1870-1871', 'source_url' => $benauge,
            'quote' => 'Après avoir subi des retards du fait de la guerre de 1870-71, la ligne est ouverte au public au printemps 1873.',
            'reason' => 'pas d\'ouverture en 1870 : ouverture au printemps 1873',
        ],

        // 434000 Briouze - La Ferté-Macé (article « Ligne de Briouze à Couterne »). Couterne - La Ferté-Macé,
        // ouvert le 15 mars 1881, est la ligne 433000 : database/data/line_event_corrections_couterne.php.
        [
            'line' => '434000', 'action' => 'add', 'type' => 'opening', 'date' => '1869-12-06', 'precision' => 'day',
            'from' => 'Briouze', 'to' => 'La Ferté-Macé', 'source_url' => $briouze,
            'quote' => 'Mise en service : De Briouze à La Ferté-Macé, le 6 décembre 1869 (Compagnie du chemin de fer d\'intérêt local de Briouze à La Ferté-Macé).',
            'reason' => 'mises en service listées dans l\'article',
        ],

        // 850000 Dijon - Vallorbe : Frasne - Vallorbe (tunnel du Mont-d'Or) ouvert en 1915, affiché dès
        // 1855. Les deux autres ouvertures datent le reste, sinon il prendrait 1915 de sa voisine.
        [
            'line' => '850000', 'action' => 'add', 'type' => 'opening', 'date' => '1855-06-25', 'precision' => 'day',
            'from' => 'Dijon-Ville', 'to' => 'Dole-Ville', 'source_url' => $doleville,
            'quote' => 'La gare de Dole-ville, a été construite par la Compagnie du chemin de fer de Paris à Lyon (PLM). Elle a été mise en service le 25 juin 1855',
            'reason' => 'section de Dijon à Dole (infobox de la ligne : 1855)',
        ],
        [
            'line' => '850000', 'action' => 'add', 'type' => 'opening', 'date' => '1862-11-01', 'precision' => 'month',
            'from' => 'Mouchard', 'to' => 'Frasne', 'source_url' => $andelot,
            'quote' => 'section de Mouchard à Pontarlier [...] L\'ouverture et l\'inauguration de la gare a lieu en même temps que la section, le 15 novembre 1862.',
            'reason' => 'section de Mouchard à Pontarlier par Andelot et Frasne (gare de Frasne mise en service le 6 novembre 1862 : '.$frasne.')',
        ],
        [
            'line' => '850000', 'action' => 'add', 'type' => 'opening', 'date' => '1915-05-01', 'precision' => 'month',
            'from' => 'Frasne', 'to' => 'Vallorbe', 'source_url' => $vallorbe,
            'quote' => 'La section Frasne-Vallorbe, comprenant le tunnel sous le mont d\'Or, n\'a été mise en service qu\'en mai 1915, obligeant auparavant les trains Paris-Lausanne à passer par Pontarlier',
            'reason' => 'tunnel du Mont-d\'Or',
        ],
        // 500000 Chartres - Bordeaux : l'article décrit chaque section dans un sous-titre « De X à Y »
        // (« Elle est mise en service le… ») ; deux de ces phrases étaient posées sur toute la ligne
        // (1876, 1879), ce qui l'affichait entière dès 1876-1879.
        [
            'line' => '500000', 'action' => 'relocate',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1876-01-30'],
            'from' => 'Saumur', 'to' => 'Montreuil-Bellay', 'source_url' => $chartresbx,
            'quote' => 'De Saumur à Montreuil-Bellay : [...] Elle est mise en service par la compagnie le 30 janvier 1876.',
            'reason' => 'phrase du sous-titre « De Saumur à Montreuil-Bellay »',
        ],
        [
            'line' => '500000', 'action' => 'relocate',
            'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1879-03-31'],
            'from' => 'Bessé-sur-Braye', 'to' => 'Château-du-Loir', 'source_url' => $chartresbx,
            'quote' => 'De Bessé-sur-Braye à Château-du-Loir : [...] Elle est mise en service par le PO, le 31 mars 1879.',
            'reason' => 'phrase du sous-titre « De Bessé-sur-Braye à Château-du-Loir » (Brou - Courtalain et Courtalain - Bessé : 1883 et 1885, par l\'État)',
        ],
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-10-17', 'precision' => 'day',
            'from' => 'Niort', 'to' => 'Saint-Jean-d\'Angély', 'source_url' => $chartresbx,
            'quote' => 'De Niort à Saint-Jean-d\'Angély : [...] Elle est ouverte le 17 octobre 1881.',
            'reason' => 'phrase du sous-titre « De Niort à Saint-Jean-d\'Angély »',
        ],
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening_freight', 'date' => '1911-05-08', 'precision' => 'day',
            'from' => 'Saint-Jean-d\'Angély', 'to' => 'Saintes', 'source_url' => $chartresbx,
            'quote' => 'Le ministère des travaux publics, autorise le directeur de l\'Administration des chemins de fer de l\'État à ouvrir au service des trains directs de marchandises la section de Saint-Jean-d\'Angély à Saintes le 8 mai 1911',
            'reason' => 'dernière section de la ligne (infobox : achèvement en 1911)',
        ],
        // 500000, partie sud (Compagnie des Charentes, ligne de Saintes à Coutras) : articles des gares.
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening', 'date' => '1869-03-25', 'precision' => 'day',
            'from' => 'Beillant', 'to' => 'Pons', 'source_url' => $pons,
            'quote' => 'un édifice flambant neuf qui accueille les premiers passagers lors de l\'ouverture de la ligne Saint-Sever-de-Saintonge - Pons (depuis la gare de Beillant) le 25 mars 1869.',
            'reason' => 'article de la gare de Pons',
        ],
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening', 'date' => '1870-01-26', 'precision' => 'day',
            'from' => 'Pons', 'to' => 'Jonzac', 'source_url' => $jonzac,
            'quote' => 'La gare de Jonzac, est mise en service le 26 janvier 1870 par la Compagnie des chemins de fer des Charentes, lorsqu\'elle ouvre à l\'exploitation la section de ligne entre la gare de Pons et celle de la sous-préfecture de Jonzac',
            'reason' => 'article de la gare de Jonzac',
        ],
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening', 'date' => '1871-11-06', 'precision' => 'day',
            'from' => 'Jonzac', 'to' => 'Montendre', 'source_url' => $montendre,
            'quote' => 'mise en service = 6 novembre 1871 (infobox de la gare de Montendre)',
            'reason' => 'mise en service de la gare de Montendre, terminus de la section prolongée depuis Jonzac',
        ],
        [
            'line' => '500000', 'action' => 'add', 'type' => 'opening', 'date' => '1874-01-01', 'precision' => 'year',
            'from' => 'Montendre', 'to' => 'Cavignac', 'source_url' => $montendre,
            'quote' => 'Elle était raccordée à Coutras, dans le département voisin de la Gironde, centre ferroviaire qui assurait la correspondance avec la ligne du Paris-Orléans pour pouvoir rallier Bordeaux dès l\'année 1874.',
            'reason' => 'achèvement de la ligne de Saintes à Coutras par Cavignac (Wikidata : ouverture 1874)',
        ],
    ];
})();
