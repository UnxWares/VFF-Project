<?php

// Textes du glossaire (en) : nom, développé du sigle et définition, par clé du registre
// resources/content/glossary/terms.php. Lu par le serveur seulement (Translations::SERVER_ONLY).
return [
    'ligne' => [
        'name' => 'Line',
        'definition' => 'A railway that links two points of the network and has an official name. On the national rail network, each line also has a six-digit code.',
    ],
    'portion' => [
        'name' => 'Section',
        'definition' => 'Part of a line between two places, with its own dates and its own status (open, closed, lifted…). The encyclopaedia tells the history of a line section by section.',
    ],
    'troncon' => [
        'name' => 'Segment',
        'definition' => 'A piece of route as the map draws it: the geometric unit from which lines are assembled. Not to be confused with a section, which is a dated part of a line.',
    ],
    'section-nommee' => [
        'name' => 'Named section',
        'definition' => 'A set known under its own name, for example a high-speed line, which may group several lines or parts of lines.',
    ],
    'trace' => [
        'name' => 'Route',
        'definition' => 'The course of a line on the ground, drawn on the map. Its accuracy varies widely depending on the sources.',
    ],
    'pk' => [
        'name' => 'PK',
        'full' => 'Kilometre point (point kilométrique)',
        'definition' => 'Distance in kilometres from the start of a line: the reference used to locate a station, a structure or a level crossing along that line. Kilometre points are specific to each line.',
    ],
    'voie-unique' => [
        'name' => 'Single track',
        'definition' => 'A line with one track used for both directions: trains can only pass each other at stations or at purpose-built points.',
    ],
    'double-voie' => [
        'name' => 'Double track',
        'definition' => 'A line with two tracks, each normally reserved for one direction of travel.',
    ],
    'voie-banalisee' => [
        'name' => 'Bidirectional track (voie banalisée)',
        'definition' => 'A track equipped so that trains can run on it in either direction under almost identical safety conditions, without applying the usual single-track rules. The term also names the working regime of a line or section so equipped.',
    ],
    'ecartement' => [
        'name' => 'Track gauge',
        'definition' => 'The distance between the inner faces of the two rails. Standard gauge, used on the national rail network, is 1,435 mm.',
    ],
    'ecartement-metrique' => [
        'name' => 'Metre gauge',
        'definition' => 'A gauge of 1,000 mm between the rails, narrower than standard gauge. It is found on many secondary, mountain and tourist lines.',
    ],
    'voie-etroite' => [
        'name' => 'Narrow gauge',
        'definition' => 'Track whose gauge is smaller than the standard 1,435 mm (metre gauge, 600 mm and so on).',
    ],
    'cremaillere' => [
        'name' => 'Rack railway',
        'definition' => 'A toothed rail laid between the running rails, in which a cogwheel of the train meshes. It makes it possible to climb gradients that adhesion alone could not manage.',
    ],
    'rampe' => [
        'name' => 'Gradient',
        'definition' => 'A rising stretch in the direction of travel (a falling one is a downgrade). The steepness is given in ‰ (per mille): 10‰ means 10 mm of height difference per metre, that is 1%.',
    ],
    'profil-en-long' => [
        'name' => 'Gradient profile',
        'definition' => 'A vertical section along a line: the successive heights and gradients, located by kilometre point.',
    ],
    'ouvrage-d-art' => [
        'name' => 'Engineering structure',
        'definition' => 'A construction that lets the track cross an obstacle or pass through relief: bridge, viaduct, tunnel, cut-and-cover tunnel, retaining wall.',
    ],
    'viaduc' => [
        'name' => 'Viaduct',
        'definition' => 'An engineering structure carrying the track over a river or another obstacle, with a height or length greater than that crossing alone would require.',
    ],
    'tunnel' => [
        'name' => 'Tunnel',
        'definition' => 'An underground gallery that gives passage to the track.',
    ],
    'passage-a-niveau' => [
        'name' => 'PN',
        'full' => 'Level crossing (passage à niveau)',
        'definition' => 'A place where a railway crosses a road or path at the same level, protected or not by barriers and signals.',
    ],
    'raccordement' => [
        'name' => 'Connecting line',
        'definition' => 'A connection between two lines, usually through track equipment, that lets trains pass from one to the other.',
    ],
    'bifurcation' => [
        'name' => 'Junction',
        'definition' => 'The point where one line splits in two; by extension, the installation that serves it.',
    ],
    'ite' => [
        'name' => 'ITE',
        'full' => 'Installation terminale embranchée (private siding)',
        'definition' => 'A railway that links the main network to a company, factory, warehouse or port area so that goods can be carried without transhipment. It is also called an embranchement particulier.',
    ],
    'aiguillage' => [
        'name' => 'Points (turnout)',
        'definition' => 'Track equipment that allows a train to move from one track to another.',
    ],
    'gare' => [
        'name' => 'Station',
        'definition' => 'A railway installation where trains stop for passengers or goods, or where they pass each other and are made up.',
    ],
    'halte' => [
        'name' => 'Halt',
        'definition' => 'A stopping place with no staff and/or no passenger building, usually just a platform.',
    ],
    'pang' => [
        'name' => 'PANG',
        'full' => 'Point d’arrêt non géré (unstaffed halt)',
        'definition' => 'An expression used in the administrative classification of stations in France for a halt with no staff.',
    ],
    'gare-de-triage' => [
        'name' => 'Marshalling yard',
        'definition' => 'A specialised station where individual goods wagons are sorted to be made up into new freight trains.',
    ],
    'lgv' => [
        'name' => 'LGV',
        'full' => 'Ligne à grande vitesse (high-speed line)',
        'definition' => 'A line built specially for high-speed trains, according to the UIC for a speed of at least 250 km/h. On the line itself, signalling is in the cab (TVM or ETCS), with no BAL.',
    ],
    'ligne-classique' => [
        'name' => 'Conventional line',
        'definition' => 'A line that is not a high-speed line: an older or upgraded route with lower speeds.',
    ],
    'voie-verte' => [
        'name' => 'Greenway',
        'definition' => 'A self-contained route reserved for non-motorised travel (walkers, cyclists, rollerbladers). The encyclopaedia lists those built on former railways.',
    ],
    'signalisation' => [
        'name' => 'Signalling',
        'definition' => 'All the signals and devices that control the running of trains and keep them safe.',
    ],
    'cantonnement' => [
        'name' => 'Block system',
        'definition' => 'The means of keeping enough distance between two trains running in the same direction on the same track, by dividing the line into block sections. It must not be confused with speed supervision.',
    ],
    'canton' => [
        'name' => 'Block section',
        'definition' => 'A stretch of line whose occupation the block system monitors in order to space trains apart. With BAL, a colour-light signal stands at the entrance of each block section.',
    ],
    'circuit-de-voie' => [
        'name' => 'Track circuit',
        'definition' => 'An electrical device that detects a train on a block section: the axles short-circuit the two rails.',
    ],
    'bal' => [
        'name' => 'BAL',
        'full' => 'Block automatique lumineux (automatic colour-light block)',
        'definition' => 'A block system in which colour-light signals change on their own according to whether the block sections are occupied, as detected by track circuits. It is the usual block system on conventional main lines.',
    ],
    'bapr' => [
        'name' => 'BAPR',
        'full' => 'Block automatique à permissivité restreinte (automatic block with restricted permissiveness)',
        'definition' => 'A variant of automatic block for lines with moderate traffic, where a BAL would be too costly: block sections are long and the rules for passing signals are adapted to this way of working.',
    ],
    'bm' => [
        'name' => 'BM',
        'full' => 'Block manuel (manual block)',
        'definition' => 'A block system in which staff at successive posts announce trains to each other by block instruments or telephone and check that each train is complete before clearing the line. On its own, BM means the double-track manual block.',
    ],
    'bmvu' => [
        'name' => 'BMVU',
        'full' => 'Block manuel de voie unique (single-track manual block)',
        'definition' => 'The manual block variant for single track: it prevents a train being sent towards the next station while a train coming the other way is already committed to it.',
    ],
    'controle-de-vitesse' => [
        'name' => 'Speed supervision',
        'definition' => 'A system that monitors a train’s speed and triggers braking if it exceeds the permitted speed or passes a closed signal. The encyclopaedia keeps it apart from the block system: KVB, for instance, supervises speed but does not space trains.',
    ],
    'kvb' => [
        'name' => 'KVB',
        'full' => 'Contrôle de vitesse par balises (speed control by beacons)',
        'definition' => 'A French speed supervision system: beacons in the track send signal information to the train, which brakes by itself if it exceeds the permitted speed. It is not a block system.',
    ],
    'signalisation-en-cabine' => [
        'name' => 'In-cab signalling',
        'definition' => 'Signalling whose indications reach the driver’s cab directly instead of being read from signals beside the track. It is essential at high speed.',
    ],
    'tvm' => [
        'name' => 'TVM',
        'full' => 'Transmission voie-machine (track-to-train transmission)',
        'definition' => 'In-cab signalling on French high-speed lines: coded signals sent through the rails tell the train the speed to respect. It both spaces trains and supervises speed. It exists in two versions, TVM 300 and TVM 430, the latter equipping more recent lines.',
    ],
    'etcs' => [
        'name' => 'ETCS',
        'full' => 'European Train Control System',
        'definition' => 'The European train control and in-cab signalling system, a component of ERTMS. At level 1, beacons transmit the movement authority at points and lineside signals remain; at level 2 it is sent continuously by GSM-R radio.',
    ],
    'ertms' => [
        'name' => 'ERTMS',
        'full' => 'European Rail Traffic Management System',
        'definition' => 'The European framework for interoperable railway signalling. It combines ETCS for train control and GSM-R for radio.',
    ],
    'gsm-r' => [
        'name' => 'GSM-R',
        'full' => 'Global System for Mobile Communications - Railway',
        'definition' => 'The railways’ digital radio, derived from GSM, used for communication between trains and control centres and carrying ETCS level 2 messages.',
    ],
    'poste-d-aiguillage' => [
        'name' => 'Signal box',
        'definition' => 'An installation from which staff operate the points and signals of an area.',
    ],
    'electrification' => [
        'name' => 'Electrification',
        'definition' => 'Equipping a line with an electric supply, by overhead line or third rail, for electrically hauled trains.',
    ],
    'catenaire' => [
        'name' => 'Overhead line (catenary)',
        'definition' => 'A set of cables hung above the track that supply trains with electricity through their pantograph.',
    ],
    'pantographe' => [
        'name' => 'Pantograph',
        'definition' => 'A hinged device on the roof of an electric vehicle that rubs against the overhead line to collect current.',
    ],
    'electrification-1500' => [
        'name' => '1.5 kV DC electrification',
        'definition' => 'Supply through an overhead line with direct current at 1,500 volts. It equips in particular the former Midi network and the main routes linking Paris to the south.',
    ],
    'electrification-25000' => [
        'name' => '25 kV 50 Hz electrification',
        'definition' => 'Supply through an overhead line with alternating current at 25,000 volts and 50 hertz. It equips all the high-speed lines and much of the northern half of the country; it allows substations to be spaced further apart.',
    ],
    'troisieme-rail' => [
        'name' => 'Third rail',
        'definition' => 'A way of collecting current from a supply rail laid beside the running rails, used in particular on metro and suburban rail networks.',
    ],
    'sous-station' => [
        'name' => 'Substation',
        'definition' => 'An installation that takes electricity from the public grid, transforms it (and rectifies it to direct current) and feeds it into the overhead line.',
    ],
    'traction-diesel' => [
        'name' => 'Diesel traction',
        'definition' => 'Traction by an internal combustion engine: railcars and locomotives, notably on lines without electrification.',
    ],
    'traction-vapeur' => [
        'name' => 'Steam traction',
        'definition' => 'Traction by steam locomotive, used from the birth of the railway until the 1950s and now confined, in a few places, to heritage and tourist trains.',
    ],
    'bimode' => [
        'name' => 'Bi-mode',
        'definition' => 'Describes a vehicle able to run both under an overhead line and on its own with a diesel engine, to cover electrified and non-electrified sections.',
    ],
    'service-voyageurs' => [
        'name' => 'Passenger service',
        'definition' => 'Running trains open to the public to carry people, as opposed to carrying goods.',
    ],
    'fret' => [
        'name' => 'Freight',
        'definition' => 'Carrying goods by train, in wagons or as whole trains.',
    ],
    'fret-seul' => [
        'name' => 'Freight only',
        'definition' => 'A line or section where only goods trains still run, passenger trains having ceased.',
    ],
    'trafic-mixte' => [
        'name' => 'Mixed traffic',
        'definition' => 'A line on which both passenger trains and goods trains run.',
    ],
    'fermeture-voyageurs' => [
        'name' => 'Closure to passenger service',
        'definition' => 'The end of passenger trains on a line or section. The line may keep carrying freight, and it has not necessarily been declassified.',
    ],
    'fermeture' => [
        'name' => 'Closure',
        'definition' => 'The end of operation of a line, a section or a station. On the national rail network, a line is closed when SNCF Réseau has pronounced its closure, after consulting the region. It differs from the lifting of the track and from declassification.',
    ],
    'reouverture' => [
        'name' => 'Reopening',
        'definition' => 'The resumption of commercial traffic on a line, a section or a station that had closed.',
    ],
    'mise-en-service' => [
        'name' => 'Entry into service',
        'definition' => 'The date from which a line, section or station carries its first commercial services.',
    ],
    'vitesse-maximale' => [
        'name' => 'Nominal maximum speed',
        'definition' => 'The fastest of the speeds permitted for trains on a stretch of line, not counting temporary speed restrictions (the definition used by the SNCF Réseau Open Data dataset).',
    ],
    'sillon' => [
        'name' => 'Train path',
        'definition' => 'The time slot that the infrastructure manager reserves for a train along its route, with its times at each point.',
    ],
    'gestionnaire-infrastructure' => [
        'name' => 'Infrastructure manager',
        'definition' => 'The body responsible for maintaining and operating the track, signalling and power supply, and for allocating train paths. On the national rail network, it is SNCF Réseau.',
    ],
    'entreprise-ferroviaire' => [
        'name' => 'Railway undertaking',
        'definition' => 'A company that runs trains, of passengers or goods, on the network run by an infrastructure manager.',
    ],
    'rfn' => [
        'name' => 'RFN',
        'full' => 'Réseau ferré national (French national rail network)',
        'definition' => 'The set of French railway lines managed by SNCF Réseau. Lines that leave it are said to be declassified.',
    ],
    'ter' => [
        'name' => 'TER',
        'full' => 'Transport express régional (regional express transport)',
        'definition' => 'An SNCF Voyageurs brand for regional services run under agreements with the regions, which are the organising authorities (except in Île-de-France, where the brand is Transilien).',
    ],
    'rer' => [
        'name' => 'RER',
        'full' => 'Réseau express régional (regional express network)',
        'definition' => 'A suburban and regional rail network serving Paris and its conurbation, made up of five lines (A to E). Its central part, built between 1962 and 1977, crosses Paris underground.',
    ],
    'intercites' => [
        'name' => 'Intercités',
        'definition' => 'The SNCF brand for its long-distance trains other than TGVs. The State is the organising authority: they are called trains d’équilibre du territoire.',
    ],
    'uic' => [
        'name' => 'UIC',
        'full' => 'Union internationale des chemins de fer (International Union of Railways)',
        'definition' => 'The international railway organisation, behind the system of network codes: the SNCF’s is 87, with which the identification codes of French stations begin.',
    ],
    'chemin-de-fer-touristique' => [
        'name' => 'Heritage railway',
        'definition' => 'A railway infrastructure most often closed to commercial service, run with often historic vehicles to show visitors railway heritage or the setting of the line.',
    ],
    'velorail' => [
        'name' => 'Rail bike',
        'definition' => 'A pedal-powered cycle that runs on the rails of a railway no longer used by trains, for leisure.',
    ],
    'concession' => [
        'name' => 'Concession',
        'definition' => 'A contract by which a public authority entrusts a company with building and operating a line for a fixed period.',
    ],
    'grandes-compagnies' => [
        'name' => 'Big companies (grandes compagnies)',
        'definition' => 'The six large private companies among which the 1859 agreements divided the conceded lines: Nord, Est, Ouest, Paris-Orléans, PLM and Midi. The State bought the Ouest in 1908; the others were merged with the State and Alsace-Lorraine networks into the SNCF on 1 January 1938.',
    ],
    'plm' => [
        'name' => 'PLM',
        'full' => 'Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée',
        'definition' => 'A private company from 1857 to 1938, one of the most important in France; it served the south-east of the country from the Gare de Lyon in Paris.',
    ],
    'po' => [
        'name' => 'PO',
        'full' => 'Compagnie du chemin de fer de Paris à Orléans',
        'definition' => 'One of the five large private companies merged into the SNCF in 1938; it served the south-west of the country from the Gare d’Austerlitz in Paris.',
    ],
    'etat' => [
        'name' => 'State railways (Chemins de fer de l’État)',
        'definition' => 'An administration created by decree on 25 May 1878 to take over the concessions of small companies in difficulty; it bought the Ouest company in November 1908.',
    ],
    'sncf' => [
        'name' => 'SNCF',
        'full' => 'Société nationale des chemins de fer français (French national railway company)',
        'definition' => 'The public railway company created on 1 January 1938 by agreement between the State and the existing companies, under the decree-law of 31 August 1937. Originally a mixed-economy limited company, a public body from 1983, and a limited company with public capital since 1 January 2020, it is the parent of a group that includes SNCF Réseau.',
    ],
    'sncf-reseau' => [
        'name' => 'SNCF Réseau',
        'definition' => 'Manager of the French railway infrastructure, created on 1 January 2015 by bringing together the staff of Réseau ferré de France, SNCF Infra and the Direction de la circulation ferroviaire. Since 2020 it has been one of the companies of the SNCF group.',
    ],
    'rff' => [
        'name' => 'RFF',
        'full' => 'Réseau ferré de France',
        'definition' => 'A public body created on 13 February 1997 by splitting off from the SNCF, and owner of the national rail network. It ceased to exist on 31 December 2014: SNCF Réseau succeeded it on 1 January 2015.',
    ],
    'plan-freycinet' => [
        'name' => 'Freycinet plan',
        'definition' => 'A public works programme launched in 1879 by the minister Charles de Freycinet, providing mainly for railway building (the law of 17 July 1879 adds about 8,800 km of new lines), but also canals and port facilities.',
    ],
    'cfil' => [
        'name' => 'CFIL',
        'full' => 'Chemin de fer d’intérêt local (local-interest railway)',
        'definition' => 'A secondary railway carrying passengers and goods within a département, as opposed to a general-interest railway. The law of 12 July 1865 authorised départements and communes to build them, directly or by concession, under State supervision.',
    ],
    'declassement' => [
        'name' => 'Declassification',
        'definition' => 'A decision by SNCF Réseau, taken after authorisation to close without keeping the track, that removes a line from the national rail network and moves it from the public to the private domain: the land can then be sold. It is separate from closure and from lifting the track.',
    ],
    'depose' => [
        'name' => 'Lifted track',
        'definition' => 'The physical removal of the track (rails, sleepers). A line can be lifted once it is closed or declassified; its right of way may remain.',
    ],
    'monument-historique' => [
        'name' => 'Historic monument',
        'definition' => 'Protection granted by the French State to a building or structure of historical or artistic interest: listing as “classé” is the highest level, “inscrit” the level below. Stations, train sheds, roundhouses and viaducts are among them; the “Architecture contemporaine remarquable” label singles out more recent buildings.',
    ],
    'ere' => [
        'name' => 'Era',
        'definition' => 'A snapshot of the network at a given date: the historical map offers one per decade, from 1830 to today, while the sheets keep the exact dates of events.',
    ],
    'materiel-roulant' => [
        'name' => 'Rolling stock',
        'definition' => 'All railway vehicles: locomotives, multiple units, railcars, trainsets, coaches, wagons, trams and service vehicles.',
    ],
    'serie' => [
        'name' => 'Class (series)',
        'definition' => 'A group of vehicles built to the same design and carrying consecutive numbers. The encyclopaedia presents rolling stock class by class.',
    ],
    'locomotive' => [
        'name' => 'Locomotive',
        'definition' => 'A railway vehicle that provides the motive power of a train.',
    ],
    'automotrice' => [
        'name' => 'Multiple unit (automotrice)',
        'definition' => 'A railway vehicle designed to carry passengers that provides its own propulsion, with no locomotive.',
    ],
    'autorail' => [
        'name' => 'Railcar (autorail)',
        'definition' => 'A vehicle with a combustion engine that carries passengers itself, used mostly on non-electrified lines.',
    ],
    'rame' => [
        'name' => 'Trainset',
        'definition' => 'A set of coupled vehicles forming a train, for example a TGV trainset or an electric multiple unit.',
    ],
    'tgv' => [
        'name' => 'TGV',
        'full' => 'Train à grande vitesse (high-speed train)',
        'definition' => 'SNCF’s high-speed train. The first trainsets ran commercially in 1981 on the LGV Sud-Est.',
    ],
    'voiture' => [
        'name' => 'Coach',
        'definition' => 'A hauled vehicle for carrying passengers.',
    ],
    'wagon' => [
        'name' => 'Wagon',
        'definition' => 'A hauled vehicle for carrying goods.',
    ],
    'bogie' => [
        'name' => 'Bogie',
        'definition' => 'A truck under a railway vehicle that carries the axles, and so the wheels. It moves relative to the vehicle’s frame and steers itself through curves.',
    ],
    'formule-d-essieux' => [
        'name' => 'Wheel arrangement',
        'definition' => 'A standardised notation (UIC classification) describing a vehicle’s driven axles: BB means two bogies with two driven axles each, CC two bogies with three.',
    ],
    'tramway' => [
        'name' => 'Tram',
        'definition' => 'A form of urban or inter-urban public transport with steel wheels on rails, either on its own right of way or embedded in the road, and the vehicle that provides it.',
    ],
    'engin-de-service' => [
        'name' => 'Service vehicle',
        'definition' => 'A vehicle intended for maintaining the track and overhead line, for recovery or for works, and not for commercial transport.',
    ],
];
