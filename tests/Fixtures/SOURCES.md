# Sources et licences des fixtures de test

Ces fichiers sont de courts extraits, parfois modifiés pour les besoins des tests (dates
volontairement fausses, valeurs tronquées). Ils ne servent qu'aux tests automatisés.

| Dossier | Source | Licence |
|:-|:-|:-|
| `Sncf/` | Portail de données ouvertes de la SNCF (SNCF Réseau, SNCF Gares & Connexions), https://ressources.data.sncf.com | Open Database License (ODbL) 1.0 |
| `Sncf/archives-sncf-sardo-extrait.json` | 7 notices du jeu de données « Archives SNCF » (Service Archives Documentation du groupe SNCF), https://ressources.data.sncf.com/explore/dataset/archives-sncf-sardo/, lues le 22/11/2026 ; descriptions et noms de personnes retirés | Open Database License (ODbL) 1.0 |
| `Wikidata/` | Wikidata, https://www.wikidata.org | CC0 1.0 |
| `Gtfs/gannat-tronc-commun.json` | Extrait de VFF (ère 2030, lu le 22/11/2026) : tronçons en service des lignes 707000 et 790000 autour de Gannat, géométries issues de la BD TOPO de l'IGN et d'OpenStreetMap, coupées à un carré de 0,02° ; aucune géométrie archeoferroviaire | BD TOPO : Licence Ouverte 2.0 (IGN) ; OpenStreetMap : ODbL 1.0, © contributeurs OpenStreetMap |
| `Bnf/` | Réponses de l'API SRU du catalogue général de la Bibliothèque nationale de France (https://catalogue.bnf.fr/api/SRU), lues le 20/11/2026 ; `sru-paris-le-havre.xml` ne garde que 6 notices sur 41 | Licence Ouverte (Etalab), source : Bibliothèque nationale de France |
| `Wikipedia/ligne-de-montsecret-a-vassy.wikitext` | Extrait adapté de l'article Wikipédia « Ligne de Montsecret à Vassy », par les contributeurs de Wikipédia, https://fr.wikipedia.org/wiki/Ligne_de_Montsecret_%C3%A0_Vassy | CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/deed.fr), extrait modifié et redistribué sous la même licence |
| `Commons/` | Fichier synthétique de candidats (format de `vff:discover-commons-candidates`, une gare absente de la base, métadonnées d'exemple) pour l'essai à blanc de `vff:import-commons-candidates` | aucune donnée réelle |
