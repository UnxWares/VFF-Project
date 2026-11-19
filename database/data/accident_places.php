<?php

/**
 * Table de correspondance de lieux des puces de la liste « Liste d'accidents ferroviaires en France » de
 * Wikipédia dont le lieu n'est pas un lien lisible par machine (puces écartées sous le motif `no_place`).
 *
 * Chaque ligne désigne UNE puce, par sa date et par un extrait de son texte (normalisé : minuscules, sans
 * accents ni ponctuation, voir App\Services\PlaceNames::normalize) : la ligne ne s'applique que si la puce
 * du jour contient encore cet extrait, donc une puce corrigée ou déplacée sur Wikipédia n'est jamais
 * rattachée à un lieu qui n'est plus le sien. Le lieu est celui que le texte de la puce nomme (« en gare de
 * Creil », « tunnel du Crozet ») ; il n'est jamais déduit d'une ligne ni d'un trajet. Une puce qui ne cite
 * que deux gares (« entre Moulins et Villeneuve ») ou une distance (« près de ») n'a pas de ligne ici : elle
 * reste écartée.
 *
 *   - date       : jour de la puce (AAAA-MM-JJ) ;
 *   - needle     : extrait du texte de la puce qui nomme le lieu ;
 *   - commune    : nom de la commune dans le référentiel des communes de VFF (nom actuel) ;
 *   - department : code du département ;
 *   - basis      : ce que le texte dit, en quelques mots (provenance de la correspondance).
 *
 * Source de chaque ligne : la puce elle-même (permalien de la révision lue, gardé dans accident_sources) et
 * la presse d'époque qu'elle cite. La fiche créée reste « à vérifier » (note `place_from_table`) : seule une
 * seconde source indépendante la confirme (vff:verify-accidents).
 */
return [
    ['date' => '1836-10-11', 'needle' => 'station de givors', 'commune' => 'Givors', 'department' => '69', 'basis' => 'près de la station de Givors'],
    ['date' => '1837-01-15', 'needle' => 'a terrenoire', 'commune' => 'Saint-Étienne', 'department' => '42', 'basis' => 'à Terrenoire (ancienne commune, aujourd\'hui dans Saint-Étienne)'],
    ['date' => '1862-11-15', 'needle' => 'tunnel de thiviers', 'commune' => 'Thiviers', 'department' => '24', 'basis' => 'sortie du tunnel de Thiviers'],
    ['date' => '1869-02-19', 'needle' => 'tunnel des buttes chaumont', 'commune' => 'Paris', 'department' => '75', 'basis' => 'entrée du tunnel des Buttes-Chaumont'],
    ['date' => '1869-07-03', 'needle' => 'en gare de creil', 'commune' => 'Creil', 'department' => '60', 'basis' => 'en gare de Creil'],
    ['date' => '1871-09-27', 'needle' => 'gare d eze', 'commune' => 'Èze', 'department' => '06', 'basis' => 'à proximité de la gare d\'Èze'],
    ['date' => '1872-06-16', 'needle' => 'gare de cherbourg', 'commune' => 'Cherbourg-en-Cotentin', 'department' => '50', 'basis' => 'à l\'approche de la gare de Cherbourg'],
    ['date' => '1884-05-14', 'needle' => 'avant saint denis', 'commune' => 'Saint-Denis', 'department' => '93', 'basis' => 'avant la gare de Saint-Denis'],
    ['date' => '1886-02-02', 'needle' => 'en gare de fontvielle', 'commune' => 'Fontvieille', 'department' => '13', 'basis' => 'en gare de Fontvielle (Fontvieille)'],
    ['date' => '1886-05-05', 'needle' => 'en gare d angers', 'commune' => 'Angers', 'department' => '49', 'basis' => 'en gare d\'Angers'],
    ['date' => '1889-03-10', 'needle' => 'arriver a honfleur', 'commune' => 'Honfleur', 'department' => '14', 'basis' => 'peu avant d\'arriver à Honfleur'],
    ['date' => '1892-02-22', 'needle' => 'a saint denis', 'commune' => 'Saint-Denis', 'department' => '93', 'basis' => 'au lieudit Le Landy, à Saint-Denis'],
    ['date' => '1892-08-29', 'needle' => 'a langon', 'commune' => 'Langon', 'department' => '33', 'basis' => 'à Langon, sur le pont de la Garonne'],
    ['date' => '1907-06-17', 'needle' => 'en gare du havre', 'commune' => 'Le Havre', 'department' => '76', 'basis' => 'en gare du Havre'],
    ['date' => '1907-12-13', 'needle' => 'gare de vallerois le bois', 'commune' => 'Vallerois-le-Bois', 'department' => '70', 'basis' => 'à l\'arrêt en gare de Vallerois-le-Bois'],
    ['date' => '1908-10-05', 'needle' => 'en gare de dijon', 'commune' => 'Dijon', 'department' => '21', 'basis' => 'en gare de Dijon'],
    ['date' => '1911-02-14', 'needle' => 'gare de courville', 'commune' => 'Courville-sur-Eure', 'department' => '28', 'basis' => 'à l\'entrée de la gare de Courville (Eure-et-Loir)'],
    ['date' => '1913-12-02', 'needle' => 'en gare de rang du fliers', 'commune' => 'Rang-du-Fliers', 'department' => '62', 'basis' => 'en gare de Rang-du-Fliers'],
    ['date' => '1914-08-04', 'needle' => 'a bricquebec', 'commune' => 'Bricquebec', 'department' => '50', 'basis' => 'à Bricquebec (Manche)'],
    ['date' => '1914-08-23', 'needle' => 'bifurcation de saint laurent pres d epinal', 'commune' => 'Épinal', 'department' => '88', 'basis' => 'bifurcation de Saint-Laurent, près d\'Épinal'],
    ['date' => '1916-09-24', 'needle' => 'gare de triage d arenc', 'commune' => 'Marseille', 'department' => '13', 'basis' => 'gare de triage d\'Arenc, près de Marseille'],
    ['date' => '1918-03-13', 'needle' => 'gare de saint jean de maurienne', 'commune' => 'Saint-Jean-de-Maurienne', 'department' => '73', 'basis' => 'en gare de Saint-Jean-de-Maurienne'],
    ['date' => '1919-05-31', 'needle' => 'en gare d amiens', 'commune' => 'Amiens', 'department' => '80', 'basis' => 'sur un aiguillage en gare d\'Amiens'],
    ['date' => '1919-06-08', 'needle' => 'halte de saint pierre', 'commune' => 'Calais', 'department' => '62', 'basis' => 'halte de Saint-Pierre, près de Calais'],
    ['date' => '1920-02-04', 'needle' => 'gare de triage de perrigny', 'commune' => 'Perrigny-lès-Dijon', 'department' => '21', 'basis' => 'gare de triage de Perrigny, près de Dijon'],
    ['date' => '1920-09-03', 'needle' => 'gare des aubrais', 'commune' => 'Fleury-les-Aubrais', 'department' => '45', 'basis' => 'en gare des Aubrais'],
    ['date' => '1920-12-12', 'needle' => 'gare d armentieres', 'commune' => 'Armentières', 'department' => '59', 'basis' => 'à la sortie de la gare d\'Armentières'],
    ['date' => '1925-11-01', 'needle' => 'a poissy', 'commune' => 'Poissy', 'department' => '78', 'basis' => 'au passage à niveau n° 4, à Poissy'],
    ['date' => '1926-10-29', 'needle' => 'a liancourt', 'commune' => 'Liancourt', 'department' => '60', 'basis' => 'à Liancourt (Oise)'],
    ['date' => '1927-08-25', 'needle' => 'chemin de fer du montenvers', 'commune' => 'Chamonix-Mont-Blanc', 'department' => '74', 'basis' => 'chemin de fer du Montenvers, au départ du sommet'],
    ['date' => '1928-08-02', 'needle' => 'gare du mans', 'commune' => 'Le Mans', 'department' => '72', 'basis' => 'à l\'entrée en gare du Mans'],
    ['date' => '1940-10-15', 'needle' => 'au bourget', 'commune' => 'Le Bourget', 'department' => '93', 'basis' => 'au Bourget'],
    ['date' => '1971-03-20', 'needle' => 'tunnel du crozet', 'commune' => 'Le Crozet', 'department' => '42', 'basis' => 'tunnel du Crozet (Loire)'],
    ['date' => '1982-07-02', 'needle' => 'rochefort sur mer', 'commune' => 'Rochefort', 'department' => '17', 'basis' => 'Rochefort-sur-Mer'],
    ['date' => '2019-01-29', 'needle' => 'sainte neomaye', 'commune' => 'Sainte-Néomaye', 'department' => '79', 'basis' => 'au passage à niveau de Sainte-Néomaye (Deux-Sèvres)'],
];
