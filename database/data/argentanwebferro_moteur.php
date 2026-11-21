<?php

/**
 * Décisions relues pour verser les faits d'Argentan Web Ferroviaire au moteur
 * (vff:apply-argentanwebferro, règles dans App\Services\ArgentanWebFerro\EngineFacts).
 * Chantier « donnees-corrections », 21/11/2026, question 19 de QUESTIONS.md.
 *
 *   - skip  : empreinte d'un fait (argentanwebferro_facts.fingerprint) → raison pour laquelle il
 *             n'entre pas au moteur (contredit par des sources plus sûres, nommées) ;
 *   - stops : empreinte d'un arrêt absent de VFF → confirmation par une autre source : name (nom
 *             écrit comme dans VFF), line (code SNCF ou slug, si l'article couvre plusieurs lignes),
 *             pk_m (facultatif, PK de la ligne si la source le donne), source_url (permalien),
 *             quote (citation courte), reason.
 *
 * Pas de tiret long dans les textes.
 *
 * @return array{skip: array<string, string>, stops: array<string, array<string, mixed>>}
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // Contradictions et faits ambigus, tranchés un par un avec les autres sources (permaliens dans la raison).
    'skip' => [
        'aacfd19a82230ec7c15979858d1fb49c67585b68' => '402000, ouverture Gacé - Ticheville datée du 10/09/1881 : jour et mois inversés ; le 9 octobre 1881 selon Wikipédia (Ligne de Sainte-Gauburge à Mesnil-Mauger, oldid 236766714, « Le 9 octobre 1881, section de Gacé à Ticheville-Le Sap »), repris par le CRGPG et cpl14.fr',
        '48c6edfe905d47934f241a5b4bd139d71b0ec477' => '432000, fin du trafic Pré-en-Pail - Couterne en 1990 : le 15/07/1989 selon Wikipédia (Ligne d\'Alençon à Domfront, oldid 235337672, d\'après Historail) et le 13/07/1989 selon OpenHistoricalMap',
        '0e9ba5a624198c7839d79783730e1c026676aa7e' => '432000, fin de tout trafic en octobre 2008 : derniers convois le 5/08/2008 selon Wikipédia (oldid 235337672, « jusqu\'au jour du 5 août 2008 où deux ultimes convois ») et OpenHistoricalMap',
        'd44531716cdcca79e016786956a9800bac2f4f14' => '433000, fin des marchandises Couterne - Bagnoles en 1938 : en 1954 selon Wikipédia (Ligne de Briouze à Couterne, oldid 233804148), déclassement du 12/11/1954 au Journal officiel ; 1938 est l\'année de la fin des voyageurs d\'Alençon - Domfront',
        '3b04b8b388a2724ac092890d9c8655621c6a3f7a' => 'Trun - Carrouges, fermeture de toute la ligne en 1936 : seule Argentan - Trun ferme « vers 1936 », le reste le 8/06/1937 selon Wikipédia (Ligne de Carrouges à Trun, oldid 227472487) et les Voies ferrées économiques de l\'Orne (oldid 224373566, « fermeture en 1937 ») ; VFF garde 1937',
        '2ad4a6567806e8851acd64768e4fb66626d6b85c' => 'Trun - Carrouges, dernier train le 6/08/1938 : aucune autre source ; Wikipédia (oldid 227472487) date la fermeture intégrale du 8 juin 1937',
        '15ece7389a11ca9e2c23f214fecc4f06c81838f3' => 'Caen - Flers - Laval, concession en 1860 : 1860 est l\'année de la déclaration d\'utilité publique (décrets du 31/08 et du 3/10/1860) ; concession par la convention du 1er mai 1863 (Ligne de Caen à Cerisy-Belle-Étoile, oldid 241393779 ; Ligne de La Chapelle-Anthenaise à Flers, oldid 237590911)',
        '6f10cabf6df3c8dd0575e042f5c33ab5b8f823a8' => 'Flers - Domfront, fermeture définitive en 1991 : le 1er octobre 1990 selon Wikipédia (Ligne de La Chapelle-Anthenaise à Flers, oldid 237590911, d\'après Historail) et OpenHistoricalMap',
        '71bdf92b9351a3b368fc831175712457801e257c' => '436000, « ouverture totale en 1874 » de Flers à Laval : résumé de l\'article ; Wikipédia (oldid 237590911) date chaque section (La Chapelle-Anthenaise - Mayenne 6/11/1866, Flers - Domfront 18/05/1874, Domfront - Mayenne 21/09/1874)',
        '7da37d484d823b65a2c819d8be8f0d3f4ce945a8' => '412000, fermeture Saint-Rémy - Berjou en 1970 : 1970 est la fin des voyageurs de toute la ligne ; les marchandises y roulent jusqu\'au 31/12/1978 selon Wikipédia (Ligne de Caen à Cerisy-Belle-Étoile)',
        'bcedc5dca20a2c639c7474956bca8a72a82ab4c9' => '402000, fermeture définitive en 1989 : dernier trafic marchandises Gacé - Le Mesnil-Mauger le 30/09/1990 et déclassement le 22/02/1991 selon Wikipédia (oldid 236766714) et lignes-oubliees',
        '72fc71cffec50a8522d371a25e1e7ad666492ca2' => '415000, fin des marchandises en 1991 sans portion : l\'article parle de la radiale Lison - Dol, dont des parties gardent un trafic ; appliquée à toute la ligne, elle ferait passer en voyageurs seuls des sections non vérifiées',
        'f714b37aef6e5c2e78c2871f2a64b9940dc6d8c9' => '402000, train mixte de 1941 « pour quelques temps » : sans date de fin, la réouverture aux voyageurs durerait jusqu\'en 1990 sur la carte',
        '484fc1143e58d8fbdfcd61b36939863aabf6f2df' => 'Caen à la Mer, voyageurs en autocars en 1937 « sauf service d\'été et trains directs » : une desserte saisonnière reste une desserte voyageurs (docs/METHODE-CLASSIFICATION.md, Desserte du jour, règle 1)',
        '38f5b697cf2fe3648c1f6a5d0b2c9e38e04bc184' => 'Caen à la Mer, reprise du service ferroviaire en 1939 : n\'a de sens qu\'après la fermeture de 1937, écartée (desserte d\'été maintenue)',
    ],

    // Arrêts absents de VFF confirmés par le schéma de la ligne dans Wikipédia. Non confirmés, donc absents :
    // Courgeout (423000), Saint-Pierre-d'Entremont (Montsecret - Les Maures). Anciens noms de gares connues, donc
    // pas ajoutés : Croisilles-Harcourt (Thury-Harcourt), Saint-Rémy-sur-Orne (Saint-Rémy (Calvados)),
    // Ambrières-les-Vallées (Ambrières-Centre, à confirmer). Citation (« quote ») : le nom tel que le schéma l'écrit.
    'stops' => [
        '8e4ac5d53391b770793de2e548766c33b2aab284' => ['name' => 'Le Rendez-Vous', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Le Rendez-Vous', 'reason' => 'halte du Rendez-Vous au PK 29,1 du schéma (28 chez Argentan Web Ferroviaire)'],
        'a1d9229f654d430a4a5863ce49fc1b449243a270' => ['name' => 'Moulins-la-Marche', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Moulins-la-Marche', 'reason' => 'gare de Moulins-la-Marche au PK 26,1 du schéma (25,1 chez Argentan Web Ferroviaire)'],
        '380b4fa8190d621903bd4de1051e349fa9fa5fae' => ['name' => 'Bonsmoulins', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Bonsmoulins', 'reason' => 'halte de Bonsmoulins au PK 20,6 du schéma (21,5 chez Argentan Web Ferroviaire)'],
        '213382402729faae144bff6d0260434cd29548e5' => ['name' => 'Soligny-la-Trappe', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Soligny-la-Trappe', 'reason' => 'gare de Soligny-la-Trappe au PK 17,0 du schéma'],
        'c8c60033c1f6be1ec4693c949163ef1aeddcd648' => ['name' => 'Lignerolles', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Lignerolles', 'reason' => 'gare de Lignerolles au PK 11,4 du schéma'],
        'b4968a290365c7f0b39d929d3cad70c6cdd8a353' => ['name' => 'Saint-Hilaire-le-Châtel', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'Saint-Hilaire-le-Châtel', 'reason' => 'halte de Saint-Hilaire-le-Châtel au PK 8,9 du schéma'],
        '52820c146b3854fef32959787b4de94e7265ace9' => ['name' => 'La Jarretière', 'source_url' => $wp('Ligne de Mortagne-au-Perche à Sainte-Gauburge', 188618272), 'quote' => 'La Jarretière', 'reason' => 'halte de La Jarretière au PK 6,7 du schéma'],
        'b9f23f2d12be911f3d64629d097563158a7a2b65' => ['name' => 'Villiers-sous-Mortagne', 'line' => '424000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mortagne-au-Perche à L\'Aigle', 238379196), 'quote' => 'Villiers-sous-Mortagne', 'reason' => 'halte au PK 5,3 du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        '88bb5031a4aa2b3637d82a6828fceea545807111' => ['name' => 'Feings', 'line' => '424000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mortagne-au-Perche à L\'Aigle', 238379196), 'quote' => 'Feings', 'reason' => 'halte au PK 8,1 du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        'a278207d6ceb3cdad5abf3931d8bef75ac218d7f' => ['name' => 'Tourouvre', 'line' => '424000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mortagne-au-Perche à L\'Aigle', 238379196), 'quote' => 'Tourouvre', 'reason' => 'gare au PK 13,5 du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        '4607112385fa118d1841e2cec1317830ce5ee84a' => ['name' => 'Randonnai - Irai', 'line' => '424000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mortagne-au-Perche à L\'Aigle', 238379196), 'quote' => 'Randonnai - Irai', 'reason' => 'gare au PK 21,953 du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        'cf1e82ae06ac6ab5e660ec87ca8c00e238889b60' => ['name' => 'Saint-Ouen-sur-Iton', 'line' => '424000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mortagne-au-Perche à L\'Aigle', 238379196), 'quote' => 'Saint-Ouen-sur-Iton', 'reason' => 'halte du schéma, entre Crulai et Saint-Michel-de-la-Forêt ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        '668158fb407f79a976566a267eb31c0c708966d9' => ['name' => 'Vaunoise - Saint-Fulgent', 'line' => '426000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mamers à Mortagne-au-Perche', 238378699), 'quote' => 'Vaunoise - Saint-Fulgent', 'reason' => 'gare du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        'b9cabdc515cf8d3c8ae53006cf968160a0b3263e' => ['name' => 'Igé', 'line' => '426000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mamers à Mortagne-au-Perche', 238378699), 'quote' => 'Igé', 'reason' => 'halte du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        '094ca272f51f8b6c9e47edbab4338d73ac393d47' => ['name' => 'Bellême', 'line' => '426000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mamers à Mortagne-au-Perche', 238378699), 'quote' => 'Bellême', 'reason' => 'gare « Bellême - Saint-Martin » du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        'eaa08e9aa6378a18fd683a8c0e025da1f8f528ff' => ['name' => 'La Herse', 'line' => '426000', 'pk_m' => null, 'source_url' => $wp('Ligne de Mamers à Mortagne-au-Perche', 238378699), 'quote' => 'La Herse', 'reason' => 'halte du schéma ; PK de l\'article cumulés depuis Mamers, gare placée par sa commune'],
        'e8a3644e5fe6d82677e44c0911f5773014f47ce5' => ['name' => 'Thoiré', 'line' => '427000', 'pk_m' => null, 'source_url' => $wp('Ligne de La Hutte - Coulombiers à Mamers', 238880846), 'quote' => 'Thoiré', 'reason' => 'halte du schéma entre Chérancé et Les Mées (PK 5,9 environ, 8,9 chez Argentan Web Ferroviaire) : PK discordants, gare placée par sa commune'],
        '837651870f029670b7d3aae58d24a90ed3ba58a1' => ['name' => 'Dorceau', 'source_url' => $wp('Ligne d\'Alençon à Condé-sur-Huisne', 239600483), 'quote' => 'Dorceau', 'reason' => 'halte du schéma entre Rémalard (PK 57,88) et Condé-sur-Huisne (66,3)'],
        '5ec173e2747178d4ab9e80c38b842b4a51f148c2' => ['name' => 'Cisai-Saint-Aubin', 'source_url' => $wp('Ligne de Sainte-Gauburge à Mesnil-Mauger', 236766714), 'quote' => 'Cisai-Saint-Aubin', 'reason' => 'gare au PK 11,6 du schéma (11,4 chez Argentan Web Ferroviaire)'],
        '3e032c83563904886b80956dbbac8a8349d1ae0d' => ['name' => 'Mardilly', 'source_url' => $wp('Ligne de Sainte-Gauburge à Mesnil-Mauger', 236766714), 'quote' => 'Mardilly', 'reason' => 'halte du schéma'],
        '13f01ae250cd5391c5ab91d3397f4d10550bff91' => ['name' => 'Neuville-sur-Touques', 'source_url' => $wp('Ligne de Sainte-Gauburge à Mesnil-Mauger', 236766714), 'quote' => 'Neuville-sur-Touques', 'reason' => 'halte du schéma'],
        '5fbb46adb6304f023a9afa30533b5689df70321d' => ['name' => 'Ticheville-Le Sap', 'source_url' => $wp('Ligne de Sainte-Gauburge à Mesnil-Mauger', 236766714), 'quote' => 'Ticheville-Le Sap', 'reason' => 'gare au PK 29,988 du schéma'],
        'a99a4f9ece67dd90ad5c21988cac92264f20ebf2' => ['name' => 'La Chapelle-d\'Andaine', 'source_url' => $wp('Ligne d\'Alençon à Domfront', 235337672), 'quote' => 'La Chapelle-d\'Andaine', 'reason' => 'gare du schéma entre Couterne (PK 45,755) et Juvigny (55,498) ; La Chapelle-Moche, nom de la commune jusqu\'en 1961'],
        'e73b305666121b089977f86ccb5c460baec5f3d0' => ['name' => 'La Baroche-sous-Lucé', 'source_url' => $wp('Ligne d\'Alençon à Domfront', 235337672), 'quote' => 'La Baroche-sous-Lucé', 'reason' => 'halte du schéma'],
        '39d9ad18d97fcd0755ea903aabe1d39f7bd06294' => ['name' => 'Saint-Bômer - Champsecret', 'line' => '436000', 'source_url' => $wp('Ligne de La Chapelle-Anthenaise à Flers', 237590911), 'quote' => 'Saint-Bômer - Champsecret', 'reason' => 'gare au PK 354,96 du schéma'],
        '1be9eff45b435309407d6df51b703febbb2709db' => ['name' => 'Saint-Loup-du-Gast', 'line' => '436000', 'source_url' => $wp('Ligne de La Chapelle-Anthenaise à Flers', 237590911), 'quote' => 'Saint-Loup-du-Gast', 'reason' => 'halte au PK 320,0 du schéma'],
        'e0ff939694df6d98b6cd9a666e05e06ca18ecfb2' => ['name' => 'Trun', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Trun', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        'e228a9ec1e020794d3cbf420dae89e9fda40c9da' => ['name' => 'Villedieu-lès-Bailleul', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Villedieu-lès-Bailleul', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        'cec98d8af6c23e7e4d3df5af69cbf37f089317d1' => ['name' => 'Bailleul', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Bailleul', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        '7cee9ef9367dda3ed86e0ea88b89cb18d44012c4' => ['name' => 'Sévigny', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Sévigny', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        'be9df99cf306cf34aca320949dde3cb203d5daeb' => ['name' => 'Fleuré', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Fleuré', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        '02f1ca3e40f346a473b8f1b45c570d76cd29b8bf' => ['name' => 'Boucé', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Boucé', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        '78cd8291c842d4a30e9e731a432bfc4b4978bbf3' => ['name' => 'Vieux-Pont', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Vieux-Pont', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        '8668a42d188103fc8dad9dfa190ac4a745746c75' => ['name' => 'Rânes', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Rânes', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
        '37ccb5cdb0c6bfb35c747194c2a807eda98532c5' => ['name' => 'Carrouges', 'source_url' => $wp('Ligne de Carrouges à Trun', 227472487), 'quote' => 'Carrouges', 'reason' => 'arrêt de la liste des gares de l\'article (sans PK), placé par sa commune'],
    ],
];
