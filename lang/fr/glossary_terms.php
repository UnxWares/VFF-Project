<?php

// Textes du glossaire (fr) : nom, développé du sigle et définition, par clé du registre
// resources/content/glossary/terms.php. Lu par le serveur seulement (Translations::SERVER_ONLY).
return [
    'ligne' => [
        'name' => 'Ligne',
        'definition' => 'Voie ferrée qui relie deux points du réseau et porte un nom officiel. Sur le réseau ferré national, chaque ligne a aussi un code à six chiffres.',
    ],
    'portion' => [
        'name' => 'Portion',
        'definition' => 'Partie d’une ligne comprise entre deux lieux, avec ses propres dates et son propre état (ouverte, fermée, déposée…). L’encyclopédie raconte l’histoire d’une ligne portion par portion.',
    ],
    'troncon' => [
        'name' => 'Tronçon',
        'definition' => 'Morceau de tracé tel que la carte le dessine : c’est l’unité géométrique dont l’assemblage forme les lignes. À ne pas confondre avec la portion, qui est un bout de ligne daté.',
    ],
    'section-nommee' => [
        'name' => 'Section nommée',
        'definition' => 'Ensemble reconnu sous un nom propre, par exemple une LGV, qui peut regrouper plusieurs lignes ou portions de lignes.',
    ],
    'trace' => [
        'name' => 'Tracé',
        'definition' => 'Parcours d’une ligne sur le terrain, dessiné sur la carte. Il peut être connu avec une précision très variable selon les sources.',
    ],
    'pk' => [
        'name' => 'PK',
        'full' => 'Point kilométrique',
        'definition' => 'Distance en kilomètres depuis l’origine d’une ligne : le repère qui sert à situer une gare, un ouvrage ou un passage à niveau le long de cette ligne. Les PK sont propres à chaque ligne.',
    ],
    'voie-unique' => [
        'name' => 'Voie unique',
        'definition' => 'Ligne dont une seule voie sert aux deux sens de circulation : les trains ne se croisent qu’en gare ou en des points aménagés.',
    ],
    'double-voie' => [
        'name' => 'Double voie',
        'definition' => 'Ligne à deux voies, chacune réservée en principe à un sens de circulation.',
    ],
    'voie-banalisee' => [
        'name' => 'Voie banalisée',
        'definition' => 'Voie équipée pour que les trains puissent y circuler dans un sens comme dans l’autre, dans des conditions de sécurité quasi identiques, sans appliquer les règles habituelles de la voie unique. Le terme désigne aussi le régime d’exploitation d’une ligne ou d’une section de ligne ainsi équipée.',
    ],
    'voie-partagee' => [
        'name' => 'Voie partagée',
        'definition' => 'Portion de voie empruntée par plusieurs lignes à la fois. Elle est dessinée une seule fois sur la carte, et chacune des lignes concernées la compte dans son parcours.',
    ],
    'ecartement' => [
        'name' => 'Écartement',
        'definition' => 'Distance entre les faces intérieures des deux rails. L’écartement standard, celui du réseau ferré national, est de 1 435 mm.',
    ],
    'ecartement-metrique' => [
        'name' => 'Écartement métrique',
        'definition' => 'Écartement de 1 000 mm entre les rails, plus étroit que l’écartement standard. Il équipe de nombreuses lignes secondaires, de montagne ou touristiques.',
    ],
    'voie-etroite' => [
        'name' => 'Voie étroite',
        'definition' => 'Voie dont l’écartement est inférieur à l’écartement standard de 1 435 mm (métrique, 600 mm…).',
    ],
    'cremaillere' => [
        'name' => 'Crémaillère',
        'definition' => 'Rail denté posé entre les rails de roulement, dans lequel s’engrène une roue dentée du train. Il permet de gravir des pentes que l’adhérence seule ne permettrait pas.',
    ],
    'rampe' => [
        'name' => 'Rampe',
        'definition' => 'Portion en montée dans le sens de la marche (en descente, on parle de pente). La déclivité s’exprime en ‰ : 10 ‰ signifie 10 mm de dénivelé par mètre, soit 1 %.',
    ],
    'declivite' => [
        'name' => 'Déclivité (‰)',
        'definition' => 'Pente d’une voie : le rapport entre la différence d’altitude et la distance parcourue, exprimé en millièmes (‰). Une déclivité de 10 ‰ correspond à 10 mètres de dénivelé pour 1 000 mètres de voie. Elle se lit sur le profil en long, repérée par PK.',
    ],
    'profil-en-long' => [
        'name' => 'Profil en long',
        'definition' => 'Coupe verticale le long d’une ligne : les altitudes et les déclivités successives, repérées par PK.',
    ],
    'ouvrage-d-art' => [
        'name' => 'Ouvrage d’art',
        'definition' => 'Construction qui permet à la voie de franchir un obstacle ou de traverser un relief : pont, viaduc, tunnel, tranchée couverte, mur de soutènement.',
    ],
    'viaduc' => [
        'name' => 'Viaduc',
        'definition' => 'Ouvrage d’art qui porte la voie au-dessus d’une rivière ou d’un autre obstacle, avec une hauteur ou une longueur plus grande que ce que ce seul franchissement exigerait.',
    ],
    'tunnel' => [
        'name' => 'Tunnel',
        'definition' => 'Galerie souterraine qui livre passage à la voie.',
    ],
    'passage-a-niveau' => [
        'name' => 'PN',
        'full' => 'Passage à niveau',
        'definition' => 'Croisement au même niveau d’une voie ferrée et d’une route ou d’un chemin, protégé ou non par des barrières et des signaux.',
    ],
    'raccordement' => [
        'name' => 'Raccordement',
        'definition' => 'Connexion entre deux lignes, généralement par des appareils de voie, qui permet de faire passer des trains de l’une à l’autre.',
    ],
    'bifurcation' => [
        'name' => 'Bifurcation',
        'definition' => 'Point où une ligne se sépare en deux ; par extension, l’installation qui l’équipe.',
    ],
    'bifurcation-lgv' => [
        'name' => 'Bifurcation et raccordement de LGV',
        'definition' => 'Sur une LGV, point où la ligne se divise en deux branches, ou voie courte qui la relie à une ligne classique pour que les trains passent de l’une à l’autre, souvent grâce à un saut-de-mouton. Ces raccordements permettent aux TGV de prolonger leur parcours sur le réseau classique.',
    ],
    'gare-de-bifurcation' => [
        'name' => 'Gare de bifurcation',
        'definition' => 'Gare établie à l’endroit où deux lignes se séparent, et où les voyageurs peuvent changer de ligne. Beaucoup de grandes gares de correspondance doivent leur existence à une bifurcation.',
    ],
    'ite' => [
        'name' => 'ITE',
        'full' => 'Installation terminale embranchée',
        'definition' => 'Voie ferrée qui relie le réseau principal à une entreprise, une usine, un entrepôt ou une zone portuaire, pour y transporter des marchandises sans rupture de charge. On parle aussi d’embranchement particulier.',
    ],
    'aiguillage' => [
        'name' => 'Aiguillage',
        'definition' => 'Appareil de voie qui permet à un train de passer d’une voie à une autre.',
    ],
    'gare' => [
        'name' => 'Gare',
        'definition' => 'Installation ferroviaire où les trains s’arrêtent pour les voyageurs ou les marchandises, ou où ils se croisent et se forment.',
    ],
    'halte' => [
        'name' => 'Halte',
        'definition' => 'Point d’arrêt dépourvu de personnel et/ou de bâtiment voyageurs, qui ne comporte généralement qu’un simple quai.',
    ],
    'pang' => [
        'name' => 'PANG',
        'full' => 'Point d’arrêt non géré',
        'definition' => 'Expression du classement administratif des gares en France pour désigner une halte sans personnel.',
    ],
    'gare-de-triage' => [
        'name' => 'Gare de triage',
        'definition' => 'Gare spécialisée où les wagons de marchandises isolés sont triés pour être incorporés dans de nouveaux trains de fret.',
    ],
    'embarcadere' => [
        'name' => 'Embarcadère',
        'definition' => 'Nom ancien d’une gare, surtout d’une gare terminus, employé au XIXe siècle pour les premiers chemins de fer. Il survit dans certains noms de gares, comme la gare embarcadère de Sceaux.',
    ],
    'lgv' => [
        'name' => 'LGV',
        'full' => 'Ligne à grande vitesse',
        'definition' => 'Ligne construite spécialement pour la circulation de trains à grande vitesse, selon l’UIC pour une vitesse d’au moins 250 km/h. Sur la ligne elle-même, la signalisation est en cabine (TVM ou ETCS), sans BAL.',
    ],
    'ligne-classique' => [
        'name' => 'Ligne classique',
        'definition' => 'Ligne qui n’est pas une ligne à grande vitesse : tracé ancien ou modernisé, vitesses plus modestes.',
    ],
    'voie-verte' => [
        'name' => 'Voie verte',
        'definition' => 'Voie de communication autonome réservée aux déplacements non motorisés (piétons, cyclistes, rollers). L’encyclopédie recense celles qui sont aménagées sur d’anciennes voies ferrées.',
    ],
    'signalisation' => [
        'name' => 'Signalisation',
        'definition' => 'Ensemble des signaux et des dispositifs qui commandent la marche des trains et en assurent la sécurité.',
    ],
    'cantonnement' => [
        'name' => 'Cantonnement',
        'definition' => 'Moyen d’assurer un espacement suffisant entre deux trains qui circulent dans le même sens sur une même voie, en divisant la ligne en cantons. Il ne doit pas être confondu avec le contrôle de vitesse.',
    ],
    'canton' => [
        'name' => 'Canton',
        'definition' => 'Section de ligne dont le cantonnement surveille l’occupation pour espacer les trains. Avec le BAL, un signal lumineux est placé à l’entrée de chaque canton.',
    ],
    'circuit-de-voie' => [
        'name' => 'Circuit de voie',
        'definition' => 'Dispositif électrique qui détecte la présence d’un train sur un canton : les essieux mettent en court-circuit les deux files de rails.',
    ],
    'bal' => [
        'name' => 'BAL',
        'full' => 'Block automatique lumineux',
        'definition' => 'Mode de cantonnement où des signaux lumineux se commandent seuls selon l’occupation des cantons, que détectent des circuits de voie. C’est le cantonnement courant des lignes principales classiques.',
    ],
    'bapr' => [
        'name' => 'BAPR',
        'full' => 'Block automatique à permissivité restreinte',
        'definition' => 'Variante du block automatique pour les lignes à trafic moyen, où un BAL serait trop coûteux : les cantons sont longs et les règles de franchissement des signaux sont adaptées à ce mode d’exploitation.',
    ],
    'bm' => [
        'name' => 'BM',
        'full' => 'Block manuel',
        'definition' => 'Mode de cantonnement où des agents de postes successifs s’annoncent les trains par appareils de block ou par téléphone et vérifient que chaque train est complet avant de rendre la voie libre. BM désigne le block manuel de double voie.',
    ],
    'bmu' => [
        'name' => 'BMU',
        'full' => 'Block manuel unifié',
        'definition' => 'Variante du block manuel de la double voie, de type SNCF : des appareils relient les deux postes voisins, et un agent autorise l’entrée d’un train dans le canton qui les sépare. Sa version pour la voie unique est le BMVU.',
    ],
    'bmvu' => [
        'name' => 'BMVU',
        'full' => 'Block manuel de voie unique',
        'definition' => 'Variante du block manuel pour voie unique : elle empêche d’envoyer un train vers la gare suivante tant qu’un train venant en sens inverse y est engagé.',
    ],
    'commande-centralisee' => [
        'name' => 'Commande centralisée',
        'definition' => 'Système qui commande à distance, depuis un poste central, les aiguillages et les signaux de plusieurs gares ou postes d’une ligne. Elle porte aussi les noms de commande centralisée du réseau (CCR) et de poste de commande à distance (PCD). Ce n’est pas un mode de cantonnement : elle se combine avec le BAL, le BAPR, le block manuel ou le cantonnement téléphonique, qui espacent les trains, et ne remplace aucun d’eux.',
    ],
    'ccvu' => [
        'name' => 'CCVU',
        'full' => 'Commande centralisée de voie unique',
        'definition' => 'Commande centralisée d’une ligne à voie unique : un poste central commande les aiguillages et les signaux des gares où les trains se croisent, et affiche en temps réel la position des trains. Elle remplace les échanges téléphoniques entre gares du block manuel. Ce n’est pas un mode de cantonnement.',
    ],
    'controle-de-vitesse' => [
        'name' => 'Contrôle de vitesse',
        'definition' => 'Système qui surveille la vitesse d’un train et déclenche un freinage s’il dépasse la vitesse autorisée ou franchit un signal fermé. L’encyclopédie le distingue du cantonnement : le KVB, par exemple, contrôle la vitesse mais n’espace pas les trains.',
    ],
    'kvb' => [
        'name' => 'KVB',
        'full' => 'Contrôle de vitesse par balises',
        'definition' => 'Système français de contrôle de vitesse : des balises posées dans la voie transmettent au train les informations des signaux, et le train freine seul s’il dépasse la vitesse permise. Ce n’est pas un mode de cantonnement.',
    ],
    'signalisation-en-cabine' => [
        'name' => 'Signalisation en cabine',
        'definition' => 'Signalisation dont les indications arrivent directement dans la cabine de conduite, au lieu d’être lues sur des signaux le long de la voie. Elle est indispensable à grande vitesse.',
    ],
    'tvm' => [
        'name' => 'TVM',
        'full' => 'Transmission voie-machine',
        'definition' => 'Signalisation en cabine des lignes à grande vitesse françaises : des signaux codés transmis par les rails indiquent au train la vitesse à respecter. Elle assure à la fois l’espacement des trains et le contrôle de vitesse. Elle existe en deux versions, TVM 300 et TVM 430, la seconde équipant des lignes plus récentes.',
    ],
    'tvm-300' => [
        'name' => 'TVM 300',
        'definition' => 'Première version de la transmission voie-machine, mise en service avec la LGV Sud-Est en 1981 et reprise sur la LGV Atlantique. Le code transmis par les rails donne au train la vitesse à ne pas dépasser sur le canton suivant ; elle a été conçue pour des vitesses allant jusqu’à 300 km/h.',
    ],
    'tvm-430' => [
        'name' => 'TVM 430',
        'definition' => 'Version suivante de la transmission voie-machine, introduite avec la LGV Nord en 1993 et reprise sur des LGV plus récentes. Elle transmet davantage d’informations et supervise un freinage continu, ce qui permet de rapprocher les trains et de rouler jusqu’à 320 km/h.',
    ],
    'etcs' => [
        'name' => 'ETCS',
        'full' => 'European Train Control System',
        'definition' => 'Système européen de contrôle des trains et de signalisation en cabine, élément de l’ERTMS. Au niveau 1, des balises transmettent par points l’autorisation de marche et les signaux latéraux subsistent ; au niveau 2, elle passe en continu par radio GSM-R.',
    ],
    'ertms' => [
        'name' => 'ERTMS',
        'full' => 'European Rail Traffic Management System',
        'definition' => 'Cadre européen d’interopérabilité de la signalisation ferroviaire. Il réunit l’ETCS pour le contrôle des trains et le GSM-R pour la radio.',
    ],
    'ertms-niveau-1' => [
        'name' => 'ERTMS niveau 1',
        'definition' => 'Premier niveau de l’ETCS : des balises (eurobalises) posées dans la voie transmettent au train, en des points précis, l’autorisation de mouvement. Les signaux latéraux et la détection des trains par la voie sont conservés ; le train ne reçoit donc l’information qu’au passage d’une balise.',
    ],
    'ertms-niveau-2' => [
        'name' => 'ERTMS niveau 2',
        'definition' => 'Deuxième niveau de l’ETCS : l’autorisation de mouvement est transmise en continu par la radio GSM-R et affichée en cabine. Les eurobalises ne servent plus qu’à repérer la position du train ; la détection des trains reste faite au sol (circuits de voie ou compteurs d’essieux) et les signaux latéraux deviennent facultatifs.',
    ],
    'gsm-r' => [
        'name' => 'GSM-R',
        'full' => 'Global System for Mobile Communications - Railway',
        'definition' => 'Radio numérique des chemins de fer, dérivée du GSM, qui sert aux communications entre trains et postes de commande et porte les messages de l’ETCS de niveau 2.',
    ],
    'poste-d-aiguillage' => [
        'name' => 'Poste d’aiguillage',
        'definition' => 'Installation d’où des agents commandent les aiguillages et les signaux d’une zone.',
    ],
    'prci' => [
        'name' => 'PRCI',
        'full' => 'Poste à relais à commande informatique',
        'definition' => 'Poste d’aiguillage où les enclenchements entre aiguilles et signaux sont réalisés par des relais, et la commande des itinéraires par un calculateur : l’agent commande des itinéraires entiers depuis un pupitre ou un écran, et non plus chaque appareil.',
    ],
    'electrification' => [
        'name' => 'Électrification',
        'definition' => 'Équipement d’une ligne en alimentation électrique, par catenaire ou par troisième rail, pour des trains à traction électrique.',
    ],
    'catenaire' => [
        'name' => 'Caténaire',
        'definition' => 'Ensemble de câbles suspendus au-dessus de la voie, qui alimentent en électricité les trains par leur pantographe.',
    ],
    'pantographe' => [
        'name' => 'Pantographe',
        'definition' => 'Dispositif articulé placé sur le toit d’un engin électrique, qui frotte contre la catenaire pour capter le courant.',
    ],
    'electrification-1500' => [
        'name' => 'Électrification 1,5 kV continu',
        'definition' => 'Alimentation par caténaire en courant continu sous 1 500 volts. Elle équipe notamment l’ancien réseau du Midi et les grands axes qui relient Paris au sud.',
    ],
    'electrification-25000' => [
        'name' => 'Électrification 25 kV 50 Hz',
        'definition' => 'Alimentation par caténaire en courant alternatif sous 25 000 volts à 50 hertz. Elle équipe toutes les lignes à grande vitesse et une grande part de la moitié nord du pays ; elle permet d’espacer davantage les sous-stations.',
    ],
    'troisieme-rail' => [
        'name' => 'Troisième rail',
        'definition' => 'Technique de captage du courant par un rail d’alimentation posé à côté des rails de roulement, utilisée en particulier dans les réseaux de métro et de trains de banlieue.',
    ],
    'sous-station' => [
        'name' => 'Sous-station',
        'definition' => 'Installation qui reçoit l’électricité du réseau public, la transforme (et la redresse en courant continu) puis l’envoie à la catenaire.',
    ],
    'traction-diesel' => [
        'name' => 'Traction diesel',
        'definition' => 'Traction par un moteur thermique (à combustion interne) : autorails et locomotives, notamment sur les lignes non électrifiées.',
    ],
    'traction-vapeur' => [
        'name' => 'Traction vapeur',
        'definition' => 'Traction par locomotive à vapeur, employée de la naissance du chemin de fer jusque dans les années 1950 et aujourd’hui réservée très localement aux trains touristiques et historiques.',
    ],
    'bimode' => [
        'name' => 'Bimode',
        'definition' => 'Se dit d’un engin capable de rouler à la fois sous catenaire et en autonomie avec un moteur thermique, pour franchir des sections électrifiées ou non.',
    ],
    'service-voyageurs' => [
        'name' => 'Service voyageurs',
        'definition' => 'Circulation de trains ouverts au public pour transporter des personnes, par opposition au transport de marchandises.',
    ],
    'fret' => [
        'name' => 'Fret',
        'definition' => 'Transport de marchandises par train, en wagons ou en trains entiers.',
    ],
    'fret-seul' => [
        'name' => 'Fret seul',
        'definition' => 'Ligne ou portion où ne circulent plus que des trains de marchandises, les trains de voyageurs ayant disparu.',
    ],
    'trafic-mixte' => [
        'name' => 'Trafic mixte',
        'definition' => 'Ligne où circulent à la fois des trains de voyageurs et des trains de marchandises.',
    ],
    'fermeture-voyageurs' => [
        'name' => 'Fermeture au service voyageurs',
        'definition' => 'Arrêt des trains de voyageurs sur une ligne ou une portion. La ligne peut continuer à servir au fret, et elle n’est pas pour autant déclassée.',
    ],
    'fermeture-fret' => [
        'name' => 'Fermeture au fret',
        'definition' => 'Fin du transport de marchandises sur une ligne ou une portion. La ligne peut garder des trains de voyageurs ; elle n’est fermée que lorsque ni les voyageurs ni le fret ne l’utilisent plus.',
    ],
    'fermeture' => [
        'name' => 'Fermeture',
        'definition' => 'Fin de l’exploitation d’une ligne, d’une portion ou d’une gare. Sur le réseau ferré national, une ligne est fermée quand SNCF Réseau en a prononcé la fermeture, après consultation de la région. Elle se distingue de la dépose des voies et du déclassement.',
    ],
    'suspension-de-service' => [
        'name' => 'Suspension de service',
        'definition' => 'Arrêt de la circulation sur une ligne ou une portion, sans fermeture prononcée : travaux, voie dégradée ou raison de sécurité. La ligne reste dans le réseau et peut rouvrir ; une suspension qui dure peut précéder la fermeture.',
    ],
    'reouverture' => [
        'name' => 'Réouverture',
        'definition' => 'Reprise de la circulation commerciale sur une ligne, une portion ou une gare qui avait été fermée.',
    ],
    'mise-en-service' => [
        'name' => 'Mise en service',
        'definition' => 'Date à partir de laquelle une ligne, une portion ou une gare accueille ses premières circulations commerciales.',
    ],
    'vitesse-maximale' => [
        'name' => 'Vitesse maximale nominale',
        'definition' => 'Plus rapide des vitesses autorisées pour les trains sur une tranche de ligne, sans compter les limitations temporaires (définition du jeu de données de SNCF Réseau Open Data).',
    ],
    'sillon' => [
        'name' => 'Sillon',
        'definition' => 'Créneau horaire que le gestionnaire de l’infrastructure réserve à un train sur son parcours, avec ses heures de passage en chaque point.',
    ],
    'gestionnaire-infrastructure' => [
        'name' => 'Gestionnaire d’infrastructure',
        'definition' => 'Organisme chargé d’entretenir et d’exploiter les voies, les installations de signalisation et d’alimentation, et d’attribuer les sillons. Sur le réseau ferré national, c’est SNCF Réseau.',
    ],
    'entreprise-ferroviaire' => [
        'name' => 'Entreprise ferroviaire',
        'definition' => 'Entreprise qui fait circuler des trains, de voyageurs ou de marchandises, sur le réseau géré par un gestionnaire d’infrastructure.',
    ],
    'rfn' => [
        'name' => 'RFN',
        'full' => 'Réseau ferré national',
        'definition' => 'Ensemble des lignes ferroviaires dont SNCF Réseau est le gestionnaire en France. Les lignes qui en sortent sont dites déclassées.',
    ],
    'ter' => [
        'name' => 'TER',
        'full' => 'Transport express régional',
        'definition' => 'Marque de SNCF Voyageurs pour les services régionaux exploités dans le cadre de conventions passées avec les régions, qui en sont les autorités organisatrices (hors Île-de-France, où la marque est Transilien).',
    ],
    'rer' => [
        'name' => 'RER',
        'full' => 'Réseau express régional',
        'definition' => 'Réseau de transport en commun au gabarit ferroviaire qui dessert Paris et son agglomération, formé de cinq lignes (A à E). Sa partie centrale, construite entre 1962 et 1977, traverse Paris en souterrain.',
    ],
    'intercites' => [
        'name' => 'Intercités',
        'definition' => 'Marque de la SNCF pour ses trains de grandes lignes hors TGV. L’État en est l’autorité organisatrice : on les nomme trains d’équilibre du territoire.',
    ],
    'uic' => [
        'name' => 'UIC',
        'full' => 'Union internationale des chemins de fer',
        'definition' => 'Organisation internationale du rail, à l’origine du système des codes réseau : celui de la SNCF est 87, par lequel commencent les codes d’identification des gares françaises.',
    ],
    'chemin-de-fer-touristique' => [
        'name' => 'Chemin de fer touristique',
        'definition' => 'Infrastructure ferroviaire le plus souvent fermée au service commercial, parcourue par des véhicules souvent anciens pour faire découvrir un patrimoine ferroviaire ou l’environnement de la ligne.',
    ],
    'velorail' => [
        'name' => 'Vélorail',
        'definition' => 'Cycle à pédales qui roule sur les rails d’une voie ferrée qui n’est plus parcourue par des trains, pour la promenade.',
    ],
    'concession' => [
        'name' => 'Concession',
        'definition' => 'Contrat par lequel la puissance publique confie à une compagnie la construction et l’exploitation d’une ligne pour une durée fixée.',
    ],
    'grandes-compagnies' => [
        'name' => 'Grandes compagnies',
        'definition' => 'Les six grandes compagnies privées entre lesquelles les conventions de 1859 ont réparti les lignes concédées : Nord, Est, Ouest, Paris-Orléans, PLM et Midi. L’État a racheté l’Ouest en 1908 ; les autres ont été regroupées avec les réseaux de l’État et d’Alsace-Lorraine dans la SNCF le 1er janvier 1938.',
    ],
    'plm' => [
        'name' => 'PLM',
        'full' => 'Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée',
        'definition' => 'Compagnie privée de 1857 à 1938, l’une des plus importantes de France ; elle desservait le sud-est du pays depuis la gare de Lyon à Paris.',
    ],
    'po' => [
        'name' => 'PO',
        'full' => 'Compagnie du chemin de fer de Paris à Orléans',
        'definition' => 'L’une des cinq grandes compagnies privées regroupées dans la SNCF en 1938 ; elle desservait le sud-ouest du pays depuis la gare d’Austerlitz à Paris.',
    ],
    'etat' => [
        'name' => 'Chemins de fer de l’État',
        'definition' => 'Administration créée par décret le 25 mai 1878 pour reprendre des concessions de petites compagnies en difficulté ; elle a racheté la compagnie de l’Ouest en novembre 1908.',
    ],
    'sncf' => [
        'name' => 'SNCF',
        'full' => 'Société nationale des chemins de fer français',
        'definition' => 'Entreprise ferroviaire publique créée le 1er janvier 1938 par convention entre l’État et les compagnies préexistantes, en application du décret-loi du 31 août 1937. Société anonyme d’économie mixte à l’origine, établissement public en 1983, société anonyme à capitaux publics depuis le 1er janvier 2020, elle est la société mère d’un groupe dont fait partie SNCF Réseau.',
    ],
    'sncf-reseau' => [
        'name' => 'SNCF Réseau',
        'definition' => 'Gestionnaire de l’infrastructure ferroviaire française, créé le 1er janvier 2015 par le regroupement du personnel de Réseau ferré de France, de SNCF Infra et de la Direction de la circulation ferroviaire. Depuis 2020, c’est l’une des sociétés du groupe SNCF.',
    ],
    'rff' => [
        'name' => 'RFF',
        'full' => 'Réseau ferré de France',
        'definition' => 'Établissement public créé le 13 février 1997 par scission de la SNCF et propriétaire du réseau ferré national. Il a disparu le 31 décembre 2014 : SNCF Réseau lui a succédé le 1er janvier 2015.',
    ],
    'plan-freycinet' => [
        'name' => 'Plan Freycinet',
        'definition' => 'Programme de travaux publics lancé en 1879 par le ministre Charles de Freycinet, qui prévoit surtout la construction de chemins de fer (la loi du 17 juillet 1879 y ajoute environ 8 800 km de lignes nouvelles), mais aussi de canaux et d’installations portuaires.',
    ],
    'cfil' => [
        'name' => 'CFIL',
        'full' => 'Chemin de fer d’intérêt local',
        'definition' => 'Chemin de fer secondaire affecté au transport de voyageurs et de marchandises à l’intérieur d’un département, par opposition au chemin de fer d’intérêt général. La loi du 12 juillet 1865 a autorisé les départements et les communes à en établir, directement ou par concession, sous le contrôle de l’État.',
    ],
    'declassement' => [
        'name' => 'Déclassement',
        'definition' => 'Décision de SNCF Réseau, prise après l’autorisation de fermeture sans maintien de la voie, qui fait sortir une ligne du réseau ferré national et la fait passer du domaine public au domaine privé : les terrains peuvent alors être vendus. Elle est distincte de la fermeture et de la dépose des voies.',
    ],
    'depose' => [
        'name' => 'Dépose',
        'definition' => 'Retrait physique de la voie (rails, traverses). Une ligne peut être déposée lorsqu’elle est fermée ou déclassée ; son emprise peut subsister.',
    ],
    'desaffectation' => [
        'name' => 'Désaffectation',
        'definition' => 'Constat qu’un terrain ou une installation ne sert plus au service public ferroviaire. Elle précède le déclassement, qui fait ensuite sortir le bien du domaine public ; elle ne supprime pas la voie, qui peut rester posée.',
    ],
    'monument-historique' => [
        'name' => 'Monument historique',
        'definition' => 'Protection que l’État accorde à un bâtiment ou à un ouvrage d’intérêt d’histoire ou d’art : le classement est le niveau le plus élevé, l’inscription le niveau inférieur. Des gares, des halles, des rotondes et des viaducs en bénéficient ; le label « Architecture contemporaine remarquable » distingue des édifices plus récents.',
    ],
    'ere' => [
        'name' => 'Ère',
        'definition' => 'Instantané du réseau à une date donnée : la carte historique en propose un par décennie, de 1830 à aujourd’hui, tandis que les fiches gardent les dates exactes des événements.',
    ],
    'materiel-roulant' => [
        'name' => 'Matériel roulant',
        'definition' => 'Ensemble des véhicules ferroviaires : locomotives, automotrices, autorails, rames, voitures, wagons, tramways et engins de service.',
    ],
    'serie' => [
        'name' => 'Série',
        'definition' => 'Groupe de véhicules construits sur le même modèle et portant des numéros consécutifs. L’encyclopédie présente le matériel série par série.',
    ],
    'famille-et-version' => [
        'name' => 'Famille et version d’un matériel',
        'definition' => 'Plusieurs séries peuvent appartenir à une même famille, qui partage une conception et un nom commercial (Regio 2N) ; chaque version a sa propre désignation (Z 56600). Quand une source cite une version, VFF relie la ligne à cette version et non à toute la famille.',
    ],
    'materiel-preserve' => [
        'name' => 'Matériel préservé',
        'definition' => 'Exemplaire d’un matériel roulant retiré du service et conservé dans un musée, par une association, sur une ligne touristique ou sur un site. VFF indique pour chacun son numéro, son état, sa protection éventuelle au titre des monuments historiques et la source.',
    ],
    'locomotive' => [
        'name' => 'Locomotive',
        'definition' => 'Véhicule ferroviaire qui fournit l’énergie motrice d’un train.',
    ],
    'automotrice' => [
        'name' => 'Automotrice',
        'definition' => 'Véhicule ferroviaire conçu pour transporter des voyageurs et qui assure sa propre propulsion, sans locomotive.',
    ],
    'autorail' => [
        'name' => 'Autorail',
        'definition' => 'Véhicule à moteur thermique qui transporte lui-même des voyageurs, utilisé surtout sur les lignes non électrifiées.',
    ],
    'element-automoteur' => [
        'name' => 'Élément automoteur',
        'definition' => 'Matériel qui roule seul grâce à ses propres moteurs, sans locomotive, formé d’une ou plusieurs caisses accouplées en permanence. Le terme regroupe automotrices électriques, autorails et bimodes ; la SNCF parle d’EAD (diesel), d’EAE (électrique) ou d’EAB (bimode).',
    ],
    'rame-a-turbine-a-gaz' => [
        'name' => 'Rame à turbine à gaz',
        'definition' => 'Rame automotrice dont les moteurs sont des turbines à gaz brûlant du carburant diesel, construite en France dans les années 1970 : d’abord des éléments (ETG), puis des rames (RTG), encore en service au début des années 2000.',
    ],
    'rame' => [
        'name' => 'Rame',
        'definition' => 'Ensemble de véhicules accouplés qui forme un train, par exemple une rame de TGV ou une rame automotrice.',
    ],
    'tgv' => [
        'name' => 'TGV',
        'full' => 'Train à grande vitesse',
        'definition' => 'Train à grande vitesse de la SNCF. Les premières rames ont circulé commercialement en 1981 sur la LGV Sud-Est.',
    ],
    'voiture' => [
        'name' => 'Voiture',
        'definition' => 'Véhicule remorqué destiné au transport de voyageurs.',
    ],
    'wagon' => [
        'name' => 'Wagon',
        'definition' => 'Véhicule remorqué destiné au transport de marchandises.',
    ],
    'bogie' => [
        'name' => 'Bogie',
        'definition' => 'Chariot situé sous un véhicule ferroviaire, qui porte les essieux et donc les roues. Il est mobile par rapport au châssis du véhicule et s’oriente dans les courbes.',
    ],
    'formule-d-essieux' => [
        'name' => 'Formule d’essieux',
        'definition' => 'Notation normalisée (classification UIC) qui décrit la disposition des essieux moteurs d’un engin : BB désigne deux bogies de deux essieux moteurs chacun, CC deux bogies de trois.',
    ],
    'tramway' => [
        'name' => 'Tramway',
        'definition' => 'Mode de transport en commun urbain ou interurbain à roues en acier sur rails, en site propre ou encastré dans la voirie, et véhicule qui l’assure.',
    ],
    'engin-de-service' => [
        'name' => 'Engin de service',
        'definition' => 'Véhicule destiné à l’entretien de la voie et de la caténaire, au dépannage ou aux travaux, et non au transport commercial.',
    ],
];
