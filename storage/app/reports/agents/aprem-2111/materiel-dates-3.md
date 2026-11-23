# Dates, retraits et effectifs du matériel, troisième passe : articles d'autres langues (agent materiel-dates-3, 23/11/2026)

## Résultat
Sur les 176 séries sans mise en service qui ne sont pas déjà dans `material_date_supplements.php` (218 au départ moins 42 traitées
par la deuxième passe ou déjà complétées), **32 séries reçoivent 40 valeurs sourcées** dans l'article d'une autre édition de Wikipédia
(16 en anglais, 20 en allemand, 4 en néerlandais) : 20 mises en service ou livraisons, 12 retraits, 8 nombres construits. Objectif de
60 séries non atteint : seules 63 séries ont un article en anglais, allemand, italien ou néerlandais lié par Wikidata (101 articles
lus), 113 n'en ont aucun, et la plupart des articles liés ne donnent que des dates de construction. Une recherche plein texte
(de, en) pour les 87 séries sans lien n'a rien donné d'exploitable (articles de familles, listes ou faux positifs).
Séries datées après import de la passe 3 : +20.

## Format étendu (sans casser l'existant)
Chaque ligne de `database/data/material_date_supplements.php` accepte une clé optionnelle `lang` (`en`, `de`, `it`, `nl`) ; absente =
français. Le mapper la recopie dans la provenance du champ (`provenance.<champ>.lang`) ; `MaterialDetailResource::sourceUrl` (et
`MaterialResource`) construisent alors le permalien sur `<lang>.wikipedia.org/w/index.php?title=…&oldid=<revid>`. Une langue inconnue
retombe sur le français (jamais d'hôte arbitraire). Les 101 lignes françaises existantes sont inchangées. Nouveau test
`MaterialDataFilesTest` (langue d'une ligne dans la liste, valeurs relevées, permalien par langue) et
`WikidataMaterialMapperTest` (la provenance garde la langue ; une valeur française n'en porte pas).

## Méthode
- Série sans date : lecture de `materials` (`statement_timeout` 30 s, aucune écriture), moins les séries déjà dans le fichier.
- Liens de langue : Wikidata (`wbgetentities`, sitelinks enwiki, dewiki, itwiki, nlwiki, lots de 50) puis colonne `wikipedia_en`.
- Articles : API Wikipédia (`prop=revisions`, lots de 20, 5 s entre deux requêtes, User-Agent `VFF-Bot/1.0 (+https://vff-project.org)`),
  101 articles ; extraction automatique des phrases datées et des champs d'infobox (Ausmusterung, indienst, service, numberbuilt...),
  relecture à la main de chacun.
- Chaque citation est vérifiée mécaniquement dans le texte de la révision citée (liens, modèles de notes et espaces insécables
  normalisés) : 40 sur 40. Chaque ligne porte la révision (`revid`) de la langue citée.
- Règles gardées : jamais de date de construction pour une mise en service (« built » écarté), pas de sous-série, pas d'estimation ;
  livraison de la première unité ou retrait de la dernière dits dans le champ `basis`.

## Valeurs ajoutées
- **Mise en service ou livraison (20)** : Est 0.250 (1859, en), Est 613 (1881, en), 241 A PLM (1925, de), G8.1 AL (1913, réception des
  premières, en), Voiture à étage État (1933, livraison, en), Y 7020 (1934, livraison, de), XD 2000-2600 (1935, de), CC 14100 (1954,
  livraison, de), Élément automoteur double (1963, livraison des Caravelle, de), Série 16 SNCB (1966, de), Série 18 SNCB (1973, en),
  Z 2N (1982, infobox, en), Eurotram (1994, Strasbourg, de), Breda VLC de Lille (1994, livraison, de), MI 2N (6 juin 1997, infobox, en),
  SJ X11 (1999, infobox, nl), Bombardier TVR (février 2001, en), Siemens S70/S700 et Avanto (1er janvier 2004, première série S70,
  en), Stadler Flirt (2004, première livraison, en), Rhônexpress Tango (2010, infobox, nl).
- **Retraits (12)** : Est 0.1 (1928), Est 0.250 (1937), S&NJ 5-14 et 17-26 (1923), Y 7020 (1979), H et Hh ex-Wurtemberg (1935), Midi
  51 à 95 (1929), Bavarian P 3/5 N (1938), Prussian G 12 CFOA (1957), G8.1 AL (1966), 230 État 3701 (1960), BB Jacquemin (2013,
  dernières locomotives de la famille), Ф soviétique (1987, infobox).
- **Effectifs (8)** : MI 84 (73), Thalys PBKA (17), 2D2 5546 à 5550 (5), XD 2000-2600 (44), Voiture à étage État (50), Tango
  Rhônexpress (6), SJ X11 (49, rames transformées), G8.1 AL (137).
- Séries déjà datées ou terminées en base : lignes de retrait pour 2D2 5546 (1980), Voiture à étage État (1982) et CC 14100 (1997) non
  ajoutées (valeur déjà présente).

## Écartés, par prudence
Famille ou exploitation partielle (CAF Urbos, Traxx, Talgo Avril), valeurs contradictoires entre langues ou dans l'article (S 10,
G 12 AL, G 5.1, T 15, Voiture TEE PBA 1963 / 1964, Série 16 SNCB retrait 2007 / 2010, MI 84 1984 / 1985, XD retrait 1974 / 1976),
dates de construction seule (Est 0.1, G5 AL, S 7), transformations (230 Est 3103, BB 88500), listes de locomotives (en, de) sans
phrase datée par série, séries sans article (87 : VTU, tramways ELRT, Y 2100 à 6400...).

## Tests
`MaterialDataFilesTest` + `WikidataMaterialMapperTest` + `tests/Unit/Materials` : 291 verts ; `MaterialImporterTest` 6 verts ;
`TypographicDashTest` 18 verts ; Pint vert ; PHPStan sans erreur sur les fichiers touchés (ajout de `lang` à la forme de
`Material::$provenance`). Aucune écriture dans `vff`.

## Application (coordinateur, après la file en cours)
Les 32 séries ont toutes les champs concernés vides en base ; l'import n'écrase jamais une valeur existante.
```
podman exec vff-db pg_dump -U vff -d vff -t materials -Fc > database-dumps/materials-avant-dates-3-2311.dump
bin/php artisan vff:import-materials --no-links --dry-run --materials=Q104579653,Q105356580,Q105415368,Q107500638,Q114400656,Q125904212,Q1294846,Q131294466,Q132346,Q13479762,Q139678896,Q142081720,Q1648680,Q1861367,Q2044360,Q2109198,Q2460934,Q2516634,Q2647945,Q2806515,Q2815032,Q2876891,Q2931158,Q3273006,Q3588094,Q41499495,Q490139,Q668935,Q739478,Q832767,Q843063,Q899787
bin/php artisan vff:import-materials --no-links --materials=Q104579653,Q105356580,Q105415368,Q107500638,Q114400656,Q125904212,Q1294846,Q131294466,Q132346,Q13479762,Q139678896,Q142081720,Q1648680,Q1861367,Q2044360,Q2109198,Q2460934,Q2516634,Q2647945,Q2806515,Q2815032,Q2876891,Q2931158,Q3273006,Q3588094,Q41499495,Q490139,Q668935,Q739478,Q832767,Q843063,Q899787
```
Effet attendu : 32 fiches mises à jour, `in_service_from` +20 (séries datées : 707 après la passe 2, puis 727),
`in_service_to` +12, `units_built` +8 ; la provenance de ces champs porte `lang` (en, de, nl) et le permalien de la fiche pointe sur
l'édition citée. Restauration éventuelle : archive entière du dump ciblé, `--single-transaction`, jamais `--clean -t`.
