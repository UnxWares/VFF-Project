<?php

// Textes du glossaire (de) : nom, développé du sigle et définition, par clé du registre
// resources/content/glossary/terms.php. Lu par le serveur seulement (Translations::SERVER_ONLY).
return [
    'ligne' => [
        'name' => 'Strecke',
        'definition' => 'Eisenbahnverbindung zwischen zwei Punkten des Netzes mit einem amtlichen Namen. Im französischen Staatsnetz trägt jede Strecke außerdem eine sechsstellige Nummer.',
    ],
    'portion' => [
        'name' => 'Abschnitt',
        'definition' => 'Teil einer Strecke zwischen zwei Orten, mit eigenen Daten und eigenem Zustand (in Betrieb, stillgelegt, zurückgebaut …). Die Enzyklopädie erzählt die Geschichte einer Strecke Abschnitt für Abschnitt.',
    ],
    'troncon' => [
        'name' => 'Segment (Streckensegment)',
        'definition' => 'Ein auf der Karte gezeichnetes Stück Streckenverlauf: die geometrische Einheit, aus der sich die Strecken zusammensetzen. Nicht zu verwechseln mit dem Abschnitt, einem datierten Teil einer Strecke.',
    ],
    'section-nommee' => [
        'name' => 'Benannter Abschnitt',
        'definition' => 'Eine unter einem eigenen Namen bekannte Gesamtheit, zum Beispiel eine Hochgeschwindigkeitsstrecke, die mehrere Strecken oder Streckenteile umfassen kann.',
    ],
    'trace' => [
        'name' => 'Streckenverlauf',
        'definition' => 'Verlauf einer Strecke im Gelände, auf der Karte eingezeichnet. Seine Genauigkeit hängt stark von den Quellen ab.',
    ],
    'pk' => [
        'name' => 'PK',
        'full' => 'Streckenkilometer (point kilométrique)',
        'definition' => 'Entfernung in Kilometern vom Anfang einer Strecke: der Bezugspunkt, mit dem ein Bahnhof, ein Bauwerk oder ein Bahnübergang entlang dieser Strecke verortet wird. Die Zählung gilt jeweils nur für die eigene Strecke.',
    ],
    'voie-unique' => [
        'name' => 'Eingleisige Strecke',
        'definition' => 'Strecke mit nur einem Gleis für beide Fahrtrichtungen: Züge können sich nur in Bahnhöfen oder an dafür vorgesehenen Stellen begegnen.',
    ],
    'double-voie' => [
        'name' => 'Zweigleisige Strecke',
        'definition' => 'Strecke mit zwei Gleisen, von denen im Regelfall jedes für eine Fahrtrichtung vorgesehen ist.',
    ],
    'voie-banalisee' => [
        'name' => 'Gleis für beide Richtungen (voie banalisée)',
        'definition' => 'Gleis, das so ausgerüstet ist, dass Züge es in beiden Richtungen unter nahezu gleichen Sicherheitsbedingungen befahren können, ohne dass die üblichen Regeln der eingleisigen Strecke gelten. Der Begriff bezeichnet auch die Betriebsweise einer so ausgerüsteten Strecke oder eines Streckenabschnitts.',
    ],
    'voie-partagee' => [
        'name' => 'Gemeinsam genutztes Gleis',
        'definition' => 'Gleisabschnitt, den mehrere Strecken gleichzeitig nutzen. Er wird auf der Karte nur einmal gezeichnet, und jede betroffene Strecke rechnet ihn zu ihrem Verlauf.',
    ],
    'ecartement' => [
        'name' => 'Spurweite',
        'definition' => 'Abstand zwischen den Innenseiten der beiden Schienen. Die Normalspur, die im französischen Staatsnetz gilt, beträgt 1.435 mm.',
    ],
    'ecartement-metrique' => [
        'name' => 'Meterspur',
        'definition' => 'Spurweite von 1.000 mm zwischen den Schienen, schmaler als die Normalspur. Sie findet sich auf vielen Nebenbahnen, Gebirgsbahnen und touristischen Strecken.',
    ],
    'voie-etroite' => [
        'name' => 'Schmalspur',
        'definition' => 'Gleis, dessen Spurweite kleiner ist als die Normalspur von 1.435 mm (Meterspur, 600 mm usw.).',
    ],
    'cremaillere' => [
        'name' => 'Zahnradbahn',
        'definition' => 'Zahnstange zwischen den Fahrschienen, in die ein Zahnrad des Fahrzeugs eingreift. Damit lassen sich Steigungen überwinden, die allein durch Haftreibung nicht zu bewältigen wären.',
    ],
    'rampe' => [
        'name' => 'Steigung',
        'definition' => 'Ansteigender Streckenabschnitt in Fahrtrichtung (abfallend spricht man von Gefälle). Die Neigung wird in ‰ angegeben: 10 ‰ bedeuten 10 mm Höhenunterschied je Meter, also 1 %.',
    ],
    'declivite' => [
        'name' => 'Neigung (‰)',
        'definition' => 'Steigung oder Gefälle eines Gleises: das Verhältnis zwischen Höhenunterschied und zurückgelegter Strecke, in Promille (‰) angegeben. Eine Neigung von 10 ‰ entspricht 10 Metern Höhenunterschied auf 1 000 Meter Gleis. Sie ist im Höhenprofil abzulesen, verortet mit dem Streckenkilometer (PK).',
    ],
    'profil-en-long' => [
        'name' => 'Längsschnitt',
        'definition' => 'Vertikalschnitt entlang einer Strecke: die aufeinanderfolgenden Höhen und Neigungen, verortet über den Streckenkilometer.',
    ],
    'ouvrage-d-art' => [
        'name' => 'Kunstbauwerk (Ingenieurbauwerk)',
        'definition' => 'Bauwerk, mit dem das Gleis ein Hindernis überquert oder ein Gelände durchquert: Brücke, Viadukt, Tunnel, Einhausung, Stützmauer.',
    ],
    'viaduc' => [
        'name' => 'Viadukt',
        'definition' => 'Kunstbauwerk, das das Gleis über einen Fluss oder ein anderes Hindernis führt und dabei höher oder länger ist, als die bloße Überquerung es erfordern würde.',
    ],
    'tunnel' => [
        'name' => 'Tunnel',
        'definition' => 'Unterirdischer Gang, durch den das Gleis geführt wird.',
    ],
    'passage-a-niveau' => [
        'name' => 'PN',
        'full' => 'Bahnübergang (passage à niveau)',
        'definition' => 'Stelle, an der eine Bahnstrecke eine Straße oder einen Weg höhengleich kreuzt, mit oder ohne Schranken und Signale gesichert.',
    ],
    'raccordement' => [
        'name' => 'Verbindungsstrecke',
        'definition' => 'Verbindung zwischen zwei Strecken, meist über Weichen, über die Züge von der einen auf die andere wechseln können.',
    ],
    'bifurcation' => [
        'name' => 'Abzweigung',
        'definition' => 'Stelle, an der sich eine Strecke in zwei teilt; im weiteren Sinn die dort befindliche Anlage.',
    ],
    'bifurcation-lgv' => [
        'name' => 'Abzweigung und Verbindungskurve einer LGV',
        'definition' => 'Auf einer LGV ein Punkt, an dem sich die Strecke in zwei Äste teilt, oder eine kurze Verbindung zu einer Altbaustrecke, über die die Züge von der einen auf die andere wechseln, oft mit einem Überwerfungsbauwerk. Diese Verbindungen ermöglichen es den TGV, ihre Fahrt auf dem klassischen Netz fortzusetzen.',
    ],
    'gare-de-bifurcation' => [
        'name' => 'Abzweigbahnhof',
        'definition' => 'Bahnhof an der Stelle, an der sich zwei Strecken trennen und an dem die Reisenden von einer Strecke auf die andere umsteigen können. Er kann in einem kleinen Ort liegen: 1867 nannte die Jury der Weltausstellung Tergnier als Beispiel für die Bedeutung eines Abzweigbahnhofs in einem „Ort ohne Bedeutung“.',
    ],
    'ite' => [
        'name' => 'ITE',
        'full' => 'Installation terminale embranchée (Gleisanschluss)',
        'definition' => 'Gleis, das das Hauptnetz mit einem Unternehmen, einer Fabrik, einem Lager oder einem Hafengebiet verbindet, damit Güter ohne Umladen befördert werden können. Man spricht auch von embranchement particulier (Privatgleisanschluss).',
    ],
    'aiguillage' => [
        'name' => 'Weiche',
        'definition' => 'Gleisanlage, die einem Zug den Übergang von einem Gleis auf ein anderes ermöglicht.',
    ],
    'gare' => [
        'name' => 'Bahnhof',
        'definition' => 'Bahnanlage, in der Züge für Reisende oder Güter halten oder in der sie sich begegnen und zusammengestellt werden.',
    ],
    'halte' => [
        'name' => 'Haltepunkt',
        'definition' => 'Haltestelle ohne Personal und/oder ohne Empfangsgebäude, in der Regel nur ein einfacher Bahnsteig.',
    ],
    'pang' => [
        'name' => 'PANG',
        'full' => 'Point d’arrêt non géré (unbesetzter Haltepunkt)',
        'definition' => 'Bezeichnung aus der Verwaltungseinstufung der Bahnhöfe in Frankreich für einen Haltepunkt ohne Personal.',
    ],
    'gare-de-triage' => [
        'name' => 'Rangierbahnhof',
        'definition' => 'Spezialisierter Bahnhof, in dem einzelne Güterwagen sortiert und zu neuen Güterzügen zusammengestellt werden.',
    ],
    'embarcadere' => [
        'name' => 'Embarcadère (frühe Endstation)',
        'definition' => 'Im 19. Jahrhundert gebräuchlicher Name für den Bahnhof der ersten Eisenbahnen: Paris-Saint-Lazare, 1837 für die Strecke von Paris nach Saint-Germain-en-Laye eröffnet, war damals ein „embarcadère“. Das Wort hat sich in einigen Bahnhofsnamen erhalten, etwa im Bahnhof Embarcadère de Sceaux.',
    ],
    'lgv' => [
        'name' => 'LGV',
        'full' => 'Ligne à grande vitesse (Hochgeschwindigkeitsstrecke)',
        'definition' => 'Eigens für Hochgeschwindigkeitszüge gebaute Strecke, nach der UIC für eine Geschwindigkeit von mindestens 250 km/h. Auf der Strecke selbst wird im Führerstand signalisiert (TVM oder ETCS), ein BAL gibt es dort nicht.',
    ],
    'ligne-classique' => [
        'name' => 'Konventionelle Strecke',
        'definition' => 'Strecke, die keine Hochgeschwindigkeitsstrecke ist: älterer oder ausgebauter Streckenverlauf mit geringeren Geschwindigkeiten.',
    ],
    'voie-verte' => [
        'name' => 'Bahntrassenradweg (voie verte)',
        'definition' => 'Eigenständiger Verkehrsweg für nicht motorisierte Fortbewegung (Fußgänger, Radfahrer, Inlineskater). Die Enzyklopädie verzeichnet jene, die auf ehemaligen Bahnstrecken angelegt wurden.',
    ],
    'signalisation' => [
        'name' => 'Signaltechnik (Signalisierung)',
        'definition' => 'Gesamtheit der Signale und Einrichtungen, die die Fahrt der Züge steuern und sichern.',
    ],
    'cantonnement' => [
        'name' => 'Streckenblock (cantonnement)',
        'definition' => 'Mittel, zwischen zwei Zügen, die in gleicher Richtung auf demselben Gleis fahren, einen ausreichenden Abstand zu sichern, indem die Strecke in Blockabschnitte unterteilt wird. Er ist nicht mit der Geschwindigkeitsüberwachung zu verwechseln.',
    ],
    'canton' => [
        'name' => 'Blockabschnitt (canton)',
        'definition' => 'Streckenabschnitt, dessen Besetzung der Streckenblock überwacht, um die Züge auf Abstand zu halten. Beim BAL steht am Anfang jedes Blockabschnitts ein Lichtsignal.',
    ],
    'circuit-de-voie' => [
        'name' => 'Gleisstromkreis',
        'definition' => 'Elektrische Einrichtung, die einen Zug in einem Blockabschnitt erkennt: Die Achsen schließen die beiden Schienen kurz.',
    ],
    'bal' => [
        'name' => 'BAL',
        'full' => 'Block automatique lumineux (selbsttätiger Streckenblock mit Lichtsignalen)',
        'definition' => 'Blocksystem, bei dem Lichtsignale sich selbsttätig nach der Besetzung der Blockabschnitte richten, die Gleisstromkreise erfassen. Es ist der übliche Streckenblock auf konventionellen Hauptstrecken.',
    ],
    'bapr' => [
        'name' => 'BAPR',
        'full' => 'Block automatique à permissivité restreinte (selbsttätiger Streckenblock mit eingeschränkter Permissivität)',
        'definition' => 'Variante des selbsttätigen Streckenblocks für Strecken mit mittlerem Verkehr, auf denen ein BAL zu teuer wäre: Die Blockabschnitte sind lang, und die Regeln für das Passieren von Signalen sind auf diese Betriebsweise abgestimmt.',
    ],
    'bm' => [
        'name' => 'BM',
        'full' => 'Block manuel (nicht selbsttätiger Streckenblock)',
        'definition' => 'Blocksystem, bei dem Bedienstete aufeinanderfolgender Posten die Züge einander über Blockgeräte oder Telefon melden und prüfen, ob jeder Zug vollständig ist, bevor sie die Strecke freigeben. BM bezeichnet den Streckenblock der zweigleisigen Strecke.',
    ],
    'bmu' => [
        'name' => 'BMU',
        'full' => 'Block manuel unifié (einheitlicher Handblock)',
        'definition' => 'Variante des nicht selbsttätigen Streckenblocks für zweigleisige Strecken, Bauart SNCF: Geräte verbinden die beiden benachbarten Stellwerke, und ein Fahrdienstleiter erlaubt einem Zug die Einfahrt in den Blockabschnitt zwischen ihnen. Die Ausführung für eingleisige Strecken ist der BMVU.',
    ],
    'bmvu' => [
        'name' => 'BMVU',
        'full' => 'Block manuel de voie unique (Streckenblock der eingleisigen Strecke)',
        'definition' => 'Variante des nicht selbsttätigen Streckenblocks für eingleisige Strecken: Sie verhindert, dass ein Zug zum nächsten Bahnhof geschickt wird, solange dort ein Gegenzug eingefahren ist.',
    ],
    'commande-centralisee' => [
        'name' => 'Zentrale Fernsteuerung',
        'definition' => 'System, das von einer Zentrale aus die Weichen und Signale mehrerer Bahnhöfe oder Stellwerke einer Strecke fernbedient. Es heißt auch commande centralisée du réseau (CCR) oder poste de commande à distance (PCD). Es ist kein Blocksystem: Es wird mit BAL, BAPR, Handblock oder Fernsprechblock kombiniert, die die Züge im Abstand halten, und ersetzt keines davon.',
    ],
    'ccvu' => [
        'name' => 'CCVU',
        'full' => 'Commande centralisée de voie unique (zentrale Fernsteuerung einer eingleisigen Strecke)',
        'definition' => 'Zentrale Fernsteuerung einer eingleisigen Strecke: Eine Zentrale bedient die Weichen und Signale der Kreuzungsbahnhöfe und zeigt die Position der Züge in Echtzeit an. Sie ersetzt die Telefonabsprachen zwischen den Bahnhöfen des Handblocks. Es ist kein Blocksystem.',
    ],
    'controle-de-vitesse' => [
        'name' => 'Geschwindigkeitsüberwachung (Zugbeeinflussung)',
        'definition' => 'System, das die Geschwindigkeit eines Zuges überwacht und eine Bremsung auslöst, wenn er die zulässige Geschwindigkeit überschreitet oder ein haltzeigendes Signal überfährt. Die Enzyklopädie trennt es vom Streckenblock: KVB etwa überwacht die Geschwindigkeit, hält die Züge aber nicht auf Abstand.',
    ],
    'kvb' => [
        'name' => 'KVB',
        'full' => 'Contrôle de vitesse par balises (Geschwindigkeitskontrolle durch Balisen)',
        'definition' => 'Französisches punktförmiges Zugbeeinflussungssystem: Balisen im Gleis übermitteln dem Zug die Signalinformationen, und der Zug bremst selbsttätig, wenn er die zulässige Geschwindigkeit überschreitet. Es ist kein Blocksystem und nicht mit der deutschen Linienzugbeeinflussung (LZB) gleichzusetzen.',
    ],
    'signalisation-en-cabine' => [
        'name' => 'Führerstandssignalisierung',
        'definition' => 'Signalisierung, deren Angaben direkt im Führerstand ankommen, statt an Signalen neben dem Gleis abgelesen zu werden. Bei hoher Geschwindigkeit ist sie unverzichtbar.',
    ],
    'tvm' => [
        'name' => 'TVM',
        'full' => 'Transmission voie-machine (Übertragung Strecke-Fahrzeug)',
        'definition' => 'Führerstandssignalisierung der französischen Hochgeschwindigkeitsstrecken: Über die Schienen übertragene codierte Signale teilen dem Zug die einzuhaltende Geschwindigkeit mit. Sie hält die Züge auf Abstand und überwacht zugleich die Geschwindigkeit. Es gibt sie in zwei Versionen, TVM 300 und TVM 430, wobei die zweite jüngere Strecken ausrüstet.',
    ],
    'tvm-300' => [
        'name' => 'TVM 300',
        'definition' => 'Erste, analoge Version der TVM (Übertragung zwischen Gleis und Fahrzeug): Der Gleisstromkreis UM 71 überträgt über die Schienen einen von 18 Geschwindigkeitswerten, die stufenweise überwacht werden, ohne Zielentfernung und mittleres Profil. Sie ist auf der LGV Sud-Est (erste kommerzielle Inbetriebnahme am 27. September 1981, anfangs mit TVM 260, dann 270), der LGV Atlantique (24. September 1989) und dem Nordabschnitt der LGV Rhône-Alpes (13. Dezember 1992) installiert.',
    ],
    'tvm-430' => [
        'name' => 'TVM 430',
        'definition' => 'Digitale Version der TVM, zuerst auf der LGV Nord eingebaut (erster Abschnitt von Gonesse bis Arras am 23. Mai 1993 in Betrieb genommen), dann auf neueren Hochgeschwindigkeitsstrecken. Ihre 27-Bit-Nachricht enthält einen Geschwindigkeitswert, eine Zielentfernung und ein mittleres Profil, sodass der Zug einer durchgehenden Geschwindigkeitsüberwachungskurve folgen kann, auch bei einer Notbremsung; sie kodiert Geschwindigkeiten bis 320 km/h.',
    ],
    'etcs' => [
        'name' => 'ETCS',
        'full' => 'European Train Control System (Europäisches Zugsicherungssystem)',
        'definition' => 'Europäisches Zugsicherungs- und Führerstandssignalisierungssystem, Bestandteil des ERTMS. In Level 1 übertragen Balisen die Fahrerlaubnis punktuell, und die Signale an der Strecke bleiben; in Level 2 läuft sie laufend über den Funk GSM-R.',
    ],
    'ertms' => [
        'name' => 'ERTMS',
        'full' => 'European Rail Traffic Management System (Europäisches Eisenbahnverkehrsleitsystem)',
        'definition' => 'Europäischer Rahmen für interoperable Eisenbahnsignalisierung. Er vereint ETCS für die Zugsicherung und GSM-R für den Funk.',
    ],
    'ertms-niveau-1' => [
        'name' => 'ERTMS Level 1',
        'definition' => 'Erste Stufe des ETCS: im Gleis liegende Balisen (Eurobalisen) übermitteln dem Zug die Fahrerlaubnis an bestimmten Punkten. Die Signale am Streckenrand und die gleisseitige Zugortung bleiben bestehen; der Zug erhält die Information also nur beim Überfahren einer Balise.',
    ],
    'ertms-niveau-2' => [
        'name' => 'ERTMS Level 2',
        'definition' => 'Zweite Stufe des ETCS: Der Zug meldet seine Position fortlaufend an das Radio Block Centre (RBC), das ihm die Fahrerlaubnis über den GSM-R-Funk zurücksendet; sie wird im Führerstand angezeigt. Die Eurobalisen dienen nur noch zur Ortung des Zuges; die Zugortung erfolgt weiterhin am Boden (Gleisstromkreise).',
    ],
    'gsm-r' => [
        'name' => 'GSM-R',
        'full' => 'Global System for Mobile Communications - Railway (digitaler Zugfunk)',
        'definition' => 'Digitaler Funk der Eisenbahnen auf Grundlage von GSM, der für die Verständigung zwischen Zügen und Leitstellen dient und die Nachrichten des ETCS Level 2 überträgt.',
    ],
    'poste-d-aiguillage' => [
        'name' => 'Stellwerk',
        'definition' => 'Anlage, von der aus Bedienstete die Weichen und Signale eines Bereichs stellen.',
    ],
    'prci' => [
        'name' => 'PRCI',
        'full' => 'Poste à relais à commande informatique (Relaisstellwerk mit Rechnersteuerung)',
        'definition' => 'Stellwerk, in dem die Abhängigkeiten zwischen Weichen und Signalen durch Relais hergestellt werden und die Fahrstraßen von einem Rechner gestellt werden: Tastatur und Bildschirm ersetzen das Pult des Relaisstellwerks mit geografischer Verdrahtung, und der Fahrdienstleiter kann Fahrstraßenfolgen programmieren. Das erste ging 1984 in Frankreich in Betrieb.',
    ],
    'electrification' => [
        'name' => 'Elektrifizierung',
        'definition' => 'Ausrüstung einer Strecke mit elektrischer Energieversorgung, per Fahrleitung oder Stromschiene, für elektrisch angetriebene Züge.',
    ],
    'catenaire' => [
        'name' => 'Fahrleitung (Oberleitung)',
        'definition' => 'Gesamtheit der über dem Gleis aufgehängten Leitungen, die die Züge über ihren Stromabnehmer mit Strom versorgen.',
    ],
    'pantographe' => [
        'name' => 'Stromabnehmer',
        'definition' => 'Gelenkige Einrichtung auf dem Dach eines elektrischen Fahrzeugs, die an der Fahrleitung schleift und den Strom abnimmt.',
    ],
    'electrification-1500' => [
        'name' => 'Elektrifizierung mit 1,5 kV Gleichstrom',
        'definition' => 'Versorgung über die Fahrleitung mit Gleichstrom von 1.500 Volt. Sie rüstet insbesondere das ehemalige Netz der Midi-Gesellschaft und die großen Achsen von Paris nach Süden aus.',
    ],
    'electrification-25000' => [
        'name' => 'Elektrifizierung mit 25 kV 50 Hz',
        'definition' => 'Versorgung über die Fahrleitung mit Wechselstrom von 25.000 Volt bei 50 Hertz. Sie rüstet alle Hochgeschwindigkeitsstrecken und einen großen Teil der Nordhälfte des Landes aus; die Unterwerke können dabei weiter auseinanderliegen.',
    ],
    'troisieme-rail' => [
        'name' => 'Stromschiene (dritte Schiene)',
        'definition' => 'Technik der Stromabnahme von einer neben den Fahrschienen verlegten Stromschiene, die besonders bei U-Bahnen und Vorortbahnen verwendet wird.',
    ],
    'sous-station' => [
        'name' => 'Unterwerk',
        'definition' => 'Anlage, die Strom aus dem öffentlichen Netz aufnimmt, ihn umspannt (und bei Gleichstrom gleichrichtet) und in die Fahrleitung einspeist.',
    ],
    'traction-diesel' => [
        'name' => 'Dieseltraktion',
        'definition' => 'Antrieb durch einen Verbrennungsmotor: Triebwagen und Lokomotiven, insbesondere auf nicht elektrifizierten Strecken.',
    ],
    'traction-vapeur' => [
        'name' => 'Dampftraktion',
        'definition' => 'Antrieb durch Dampflokomotiven, von den Anfängen der Eisenbahn bis in die 1950er-Jahre verwendet und heute nur noch ganz örtlich bei touristischen und historischen Zügen.',
    ],
    'bimode' => [
        'name' => 'Zweikraftfahrzeug (bimodal)',
        'definition' => 'Fahrzeug, das sowohl unter Fahrleitung als auch autonom mit einem Verbrennungsmotor fahren kann und so elektrifizierte wie nicht elektrifizierte Abschnitte befährt.',
    ],
    'service-voyageurs' => [
        'name' => 'Personenverkehr',
        'definition' => 'Betrieb von Zügen, die der Öffentlichkeit zur Beförderung von Personen offenstehen, im Gegensatz zum Gütertransport.',
    ],
    'fret' => [
        'name' => 'Güterverkehr (Fret)',
        'definition' => 'Beförderung von Gütern per Bahn, in einzelnen Wagen oder in Ganzzügen.',
    ],
    'fret-seul' => [
        'name' => 'Reiner Güterverkehr',
        'definition' => 'Strecke oder Abschnitt, auf dem nur noch Güterzüge fahren, nachdem der Personenverkehr eingestellt wurde.',
    ],
    'trafic-mixte' => [
        'name' => 'Gemischter Verkehr',
        'definition' => 'Strecke, auf der sowohl Personen- als auch Güterzüge verkehren.',
    ],
    'fermeture-voyageurs' => [
        'name' => 'Einstellung des Personenverkehrs',
        'definition' => 'Ende der Reisezüge auf einer Strecke oder einem Abschnitt. Die Strecke kann weiter dem Güterverkehr dienen und ist damit nicht zwangsläufig entwidmet.',
    ],
    'fermeture-fret' => [
        'name' => 'Einstellung des Güterverkehrs',
        'definition' => 'Ende des Güterverkehrs auf einer Strecke oder einem Abschnitt. Die Strecke kann weiter von Reisezügen befahren werden; stillgelegt ist sie erst, wenn weder Personen- noch Güterverkehr stattfindet.',
    ],
    'fermeture' => [
        'name' => 'Stilllegung',
        'definition' => 'Ende des Betriebs einer Strecke, eines Abschnitts oder eines Bahnhofs. Im französischen Staatsnetz ist eine Strecke stillgelegt, wenn SNCF Réseau ihre Schließung nach Anhörung der Region ausgesprochen hat. Sie unterscheidet sich vom Gleisrückbau und von der Entwidmung.',
    ],
    'suspension-de-service' => [
        'name' => 'Betriebsunterbrechung',
        'definition' => 'Einstellung des Verkehrs auf einer Strecke oder einem Abschnitt, ohne dass eine Stilllegung beschlossen wäre: Bauarbeiten, schlechter Gleiszustand oder Sicherheitsgründe. Die Strecke bleibt im Netz und kann wieder öffnen; eine lange Unterbrechung kann der Stilllegung vorausgehen.',
    ],
    'reouverture' => [
        'name' => 'Wiedereröffnung (Reaktivierung)',
        'definition' => 'Wiederaufnahme des gewerblichen Verkehrs auf einer stillgelegten Strecke, einem Abschnitt oder einem Bahnhof.',
    ],
    'mise-en-service' => [
        'name' => 'Inbetriebnahme',
        'definition' => 'Datum, ab dem eine Strecke, ein Abschnitt oder ein Bahnhof den ersten gewerblichen Verkehr aufnimmt.',
    ],
    'vitesse-maximale' => [
        'name' => 'Nenngeschwindigkeit der Strecke (Höchstgeschwindigkeit)',
        'definition' => 'Die höchste der für Züge zugelassenen Geschwindigkeiten auf einem Streckenabschnitt, ohne vorübergehende Geschwindigkeitsbeschränkungen (Definition des Datensatzes von SNCF Réseau Open Data).',
    ],
    'sillon' => [
        'name' => 'Zugtrasse',
        'definition' => 'Zeitfenster, das der Infrastrukturbetreiber einem Zug auf seinem Laufweg reserviert, mit den Durchfahrtszeiten an jedem Punkt.',
    ],
    'gestionnaire-infrastructure' => [
        'name' => 'Infrastrukturbetreiber',
        'definition' => 'Stelle, die Gleise, Signal- und Energieversorgungsanlagen unterhält und betreibt und Trassen zuweist. Im französischen Staatsnetz ist das SNCF Réseau.',
    ],
    'entreprise-ferroviaire' => [
        'name' => 'Eisenbahnverkehrsunternehmen',
        'definition' => 'Unternehmen, das Personen- oder Güterzüge auf dem von einem Infrastrukturbetreiber verwalteten Netz fahren lässt.',
    ],
    'rfn' => [
        'name' => 'RFN',
        'full' => 'Réseau ferré national (französisches Staatsnetz)',
        'definition' => 'Gesamtheit der Bahnstrecken in Frankreich, die SNCF Réseau verwaltet. Strecken, die daraus ausscheiden, gelten als entwidmet.',
    ],
    'ter' => [
        'name' => 'TER',
        'full' => 'Transport express régional (Regionalverkehr)',
        'definition' => 'Marke von SNCF Voyageurs für Regionalverkehre, die im Rahmen von Verträgen mit den Regionen als Aufgabenträgern betrieben werden (außer in der Île-de-France, wo die Marke Transilien gilt).',
    ],
    'rer' => [
        'name' => 'RER',
        'full' => 'Réseau express régional (Regional-Express-Netz)',
        'definition' => 'Schnellbahnnetz auf Eisenbahngleisen für Paris und seinen Ballungsraum, bestehend aus fünf Linien (A bis E). Sein zentraler Teil, zwischen 1962 und 1977 gebaut, durchquert Paris unterirdisch.',
    ],
    'intercites' => [
        'name' => 'Intercités',
        'definition' => 'Marke der SNCF für ihre Fernverkehrszüge außer dem TGV. Aufgabenträger ist der Staat: Man nennt sie trains d’équilibre du territoire.',
    ],
    'uic' => [
        'name' => 'UIC',
        'full' => 'Union internationale des chemins de fer (Internationaler Eisenbahnverband)',
        'definition' => 'Internationale Eisenbahnorganisation, auf die das System der Netzcodes zurückgeht: Der der SNCF ist 87, mit dem die Kennnummern der französischen Bahnhöfe beginnen.',
    ],
    'chemin-de-fer-touristique' => [
        'name' => 'Museumsbahn (chemin de fer touristique)',
        'definition' => 'Eisenbahninfrastruktur, meist ohne gewerblichen Verkehr, die mit oft historischen Fahrzeugen befahren wird, um Eisenbahngeschichte oder die Umgebung der Strecke zu zeigen.',
    ],
    'velorail' => [
        'name' => 'Draisine (vélorail)',
        'definition' => 'Pedalbetriebenes Fahrzeug, das auf den Schienen einer nicht mehr von Zügen befahrenen Strecke zur Freizeitgestaltung rollt.',
    ],
    'concession' => [
        'name' => 'Konzession',
        'definition' => 'Vertrag, mit dem die öffentliche Hand einem Unternehmen den Bau und den Betrieb einer Strecke für einen festgelegten Zeitraum überträgt.',
    ],
    'grandes-compagnies' => [
        'name' => 'Große Gesellschaften (grandes compagnies)',
        'definition' => 'Die sechs großen Privatgesellschaften, auf die die Abkommen von 1859 die konzessionierten Strecken verteilten: Nord, Est, Ouest, Paris-Orléans, PLM und Midi. Der Staat kaufte die Ouest 1908; die übrigen wurden am 1. Januar 1938 mit den Netzen des Staates und von Elsass-Lothringen in der SNCF zusammengelegt.',
    ],
    'plm' => [
        'name' => 'PLM',
        'full' => 'Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée',
        'definition' => 'Privatgesellschaft von 1857 bis 1938 und eine der bedeutendsten Frankreichs; sie bediente den Südosten des Landes vom Pariser Gare de Lyon aus.',
    ],
    'po' => [
        'name' => 'PO',
        'full' => 'Compagnie du chemin de fer de Paris à Orléans',
        'definition' => 'Eine der fünf großen Privatgesellschaften, die 1938 in der SNCF aufgingen; sie bediente den Südwesten des Landes vom Pariser Gare d’Austerlitz aus.',
    ],
    'etat' => [
        'name' => 'Staatsbahnen (Chemins de fer de l’État)',
        'definition' => 'Verwaltung, die per Dekret vom 25. Mai 1878 geschaffen wurde, um die Konzessionen kleiner, in Schwierigkeiten geratener Gesellschaften zu übernehmen; im November 1908 kaufte sie die Ouest-Gesellschaft.',
    ],
    'sncf' => [
        'name' => 'SNCF',
        'full' => 'Société nationale des chemins de fer français (französische Staatsbahn)',
        'definition' => 'Öffentliches Eisenbahnunternehmen, am 1. Januar 1938 durch Vereinbarung zwischen dem Staat und den bestehenden Gesellschaften auf Grundlage des Dekretgesetzes vom 31. August 1937 gegründet. Zunächst gemischtwirtschaftliche Aktiengesellschaft, ab 1983 öffentliche Einrichtung und seit dem 1. Januar 2020 Aktiengesellschaft mit öffentlichem Kapital, ist sie die Muttergesellschaft eines Konzerns, zu dem SNCF Réseau gehört.',
    ],
    'sncf-reseau' => [
        'name' => 'SNCF Réseau',
        'definition' => 'Betreiber der französischen Eisenbahninfrastruktur, am 1. Januar 2015 durch die Zusammenlegung des Personals von Réseau ferré de France, SNCF Infra und der Direction de la circulation ferroviaire entstanden. Seit 2020 ist es eine der Gesellschaften des SNCF-Konzerns.',
    ],
    'rff' => [
        'name' => 'RFF',
        'full' => 'Réseau ferré de France',
        'definition' => 'Öffentliche Einrichtung, am 13. Februar 1997 durch Abspaltung von der SNCF geschaffen und Eigentümerin des Staatsnetzes. Sie wurde am 31. Dezember 2014 aufgelöst: SNCF Réseau trat am 1. Januar 2015 ihre Nachfolge an.',
    ],
    'plan-freycinet' => [
        'name' => 'Freycinet-Plan',
        'definition' => '1879 vom Minister Charles de Freycinet gestartetes Programm öffentlicher Bauten, das vor allem den Bau von Eisenbahnen vorsieht (das Gesetz vom 17. Juli 1879 fügt rund 8.800 km Neubaustrecken hinzu), daneben aber auch Kanäle und Hafenanlagen.',
    ],
    'cfil' => [
        'name' => 'CFIL',
        'full' => 'Chemin de fer d’intérêt local (Lokalbahn)',
        'definition' => 'Nebenbahn für Personen- und Güterverkehr innerhalb eines Départements, im Gegensatz zur Eisenbahn von allgemeinem Interesse. Das Gesetz vom 12. Juli 1865 erlaubte Départements und Gemeinden, solche Bahnen unmittelbar oder per Konzession unter Aufsicht des Staates zu bauen.',
    ],
    'declassement' => [
        'name' => 'Entwidmung (déclassement)',
        'definition' => 'Entscheidung von SNCF Réseau nach der Genehmigung zur Schließung ohne Erhalt des Gleises, mit der eine Strecke aus dem französischen Staatsnetz ausscheidet und vom öffentlichen in den privaten Bereich übergeht: Die Grundstücke können dann verkauft werden. Sie ist von der Stilllegung und vom Gleisrückbau zu unterscheiden.',
    ],
    'depose' => [
        'name' => 'Gleisrückbau',
        'definition' => 'Physischer Ausbau des Gleises (Schienen, Schwellen). Eine Strecke kann zurückgebaut werden, wenn sie stillgelegt oder entwidmet ist; ihre Trasse kann erhalten bleiben.',
    ],
    'desaffectation' => [
        'name' => 'Nutzungsaufgabe (désaffectation)',
        'definition' => 'Feststellung, dass ein Grundstück oder eine Anlage dem öffentlichen Eisenbahndienst nicht mehr dient. Sie geht der Entwidmung (déclassement) voraus, die das Gut anschließend aus dem öffentlichen Eigentum herauslöst; das Gleis wird dadurch nicht entfernt und kann liegen bleiben.',
    ],
    'monument-historique' => [
        'name' => 'Monument historique (Denkmalschutz)',
        'definition' => 'Schutz, den der französische Staat einem Gebäude oder Bauwerk von geschichtlichem oder künstlerischem Wert gewährt: die Einstufung („classé“) ist die höchste Stufe, die Eintragung („inscrit“) die darunterliegende. Bahnhöfe, Bahnhofshallen, Lokschuppen und Viadukte gehören dazu; das Label „Architecture contemporaine remarquable“ zeichnet jüngere Bauten aus.',
    ],
    'ere' => [
        'name' => 'Epoche (ère)',
        'definition' => 'Momentaufnahme des Netzes zu einem bestimmten Datum: Die historische Karte bietet eine je Jahrzehnt, von 1830 bis heute, während die Seiten die genauen Daten der Ereignisse bewahren.',
    ],
    'materiel-roulant' => [
        'name' => 'Schienenfahrzeuge (Rollmaterial)',
        'definition' => 'Gesamtheit der Eisenbahnfahrzeuge: Lokomotiven, Triebzüge, Triebwagen, Zugeinheiten, Reisezugwagen, Güterwagen, Straßenbahnen und Dienstfahrzeuge.',
    ],
    'serie' => [
        'name' => 'Baureihe',
        'definition' => 'Gruppe von Fahrzeugen gleicher Bauart mit fortlaufenden Nummern. Die Enzyklopädie stellt die Fahrzeuge Baureihe für Baureihe vor.',
    ],
    'famille-et-version' => [
        'name' => 'Familie und Version eines Fahrzeugs',
        'definition' => 'Mehrere Baureihen können zu einer Familie gehören, die eine gemeinsame Konstruktion und einen gemeinsamen Handelsnamen hat (Regio 2N); jede Version trägt ihre eigene Bezeichnung (Z 56600). Nennt eine Quelle eine Version, verknüpft VFF die Strecke mit dieser Version und nicht mit der ganzen Familie.',
    ],
    'materiel-preserve' => [
        'name' => 'Erhaltene Fahrzeuge',
        'definition' => 'Ein aus dem Dienst genommenes und erhaltenes Fahrzeug, das in einem Museum, bei einem Verein, auf einer Museumsbahn oder an einem Ort bewahrt wird. VFF nennt zu jedem Exemplar seine Nummer, seinen Zustand, einen etwaigen Denkmalschutz und die Quelle.',
    ],
    'locomotive' => [
        'name' => 'Lokomotive',
        'definition' => 'Schienenfahrzeug, das die Antriebsenergie eines Zuges liefert.',
    ],
    'automotrice' => [
        'name' => 'Elektrotriebwagen (automotrice)',
        'definition' => 'Schienenfahrzeug zur Beförderung von Reisenden, das sich selbst antreibt, ohne Lokomotive.',
    ],
    'autorail' => [
        'name' => 'Dieseltriebwagen (autorail)',
        'definition' => 'Fahrzeug mit Verbrennungsmotor, das selbst Reisende befördert und vor allem auf nicht elektrifizierten Strecken eingesetzt wird.',
    ],
    'element-automoteur' => [
        'name' => 'Triebzug (élément automoteur)',
        'definition' => 'Triebzug aus mehreren dauerhaft gekuppelten Wagenkästen, der mit eigenen Motoren ohne Lokomotive fährt. Die SNCF ordnet sie nach dem Antrieb ein: EAD (Diesel), EAE (elektrisch) oder EAB (bimodal). Einteilige Triebwagen und Schienenbusse bilden eine eigene Kategorie.',
    ],
    'rame-a-turbine-a-gaz' => [
        'name' => 'Gasturbinentriebzug',
        'definition' => 'Triebzug, dessen Antrieb aus Gasturbinen besteht und der in den 1970er-Jahren in Frankreich gebaut wurde: zuerst die Gasturbineneinheiten (ETG), vierzehn Züge, die von 1970 bis 1972 mit einer Turbine und einem Dieselmotor in Dienst gingen, dann die Gasturbinenzüge (RTG), 44 Stück, ab 1973 geliefert und mit Dieselkraftstoff betrieben. Der letzte Turbotrain beendete seinen Dienst in Frankreich im Dezember 2004.',
    ],
    'rame' => [
        'name' => 'Zugeinheit (Rame)',
        'definition' => 'Verband gekuppelter Fahrzeuge, der einen Zug bildet, zum Beispiel eine TGV-Einheit oder ein Triebzug.',
    ],
    'tgv' => [
        'name' => 'TGV',
        'full' => 'Train à grande vitesse (Hochgeschwindigkeitszug)',
        'definition' => 'Hochgeschwindigkeitszug der SNCF. Die ersten Einheiten verkehrten 1981 auf der LGV Sud-Est im Planbetrieb.',
    ],
    'voiture' => [
        'name' => 'Reisezugwagen (voiture)',
        'definition' => 'Gezogenes Fahrzeug für die Beförderung von Reisenden.',
    ],
    'wagon' => [
        'name' => 'Güterwagen (wagon)',
        'definition' => 'Gezogenes Fahrzeug für die Beförderung von Gütern.',
    ],
    'bogie' => [
        'name' => 'Drehgestell',
        'definition' => 'Fahrwerk unter einem Schienenfahrzeug, das die Achsen und damit die Räder trägt. Es ist gegenüber dem Fahrzeugrahmen beweglich und stellt sich in Kurven ein.',
    ],
    'formule-d-essieux' => [
        'name' => 'Achsfolge',
        'definition' => 'Genormte Schreibweise (UIC-Klassifikation) für die Anordnung der Triebachsen eines Fahrzeugs: BB steht für zwei Drehgestelle mit je zwei Triebachsen, CC für zwei Drehgestelle mit je drei.',
    ],
    'tramway' => [
        'name' => 'Straßenbahn',
        'definition' => 'Städtisches oder regionales öffentliches Verkehrsmittel mit Stahlrädern auf Schienen, auf eigenem Bahnkörper oder in die Straße eingelassen, sowie das dafür eingesetzte Fahrzeug.',
    ],
    'engin-de-service' => [
        'name' => 'Dienstfahrzeug',
        'definition' => 'Fahrzeug für Instandhaltung von Gleis und Fahrleitung, Hilfeleistung oder Bauarbeiten, nicht für den gewerblichen Transport.',
    ],
];
