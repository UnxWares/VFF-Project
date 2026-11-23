<?php

namespace App\Services\OpenData;

/**
 * Dictionnaire des champs des fichiers de la deuxième vague de jeux ouverts (OpenDataSuiteExporter), en français, en
 * anglais et en allemand : pour chaque champ, sa description, sa provenance et sa licence. Écrit dans chaque jeu
 * (dictionnaire.csv) et résumé dans le LISEZMOI.
 *
 * Provenance d'un champ :
 *   - « column:source » : la clé du fournisseur est dans la colonne `source` de la même ligne (sa licence dans `licence`) ;
 *   - une clé du registre des droits (« sncf », « ign », « vff »…) : tout le fichier vient de ce fournisseur ;
 *   - « column:sources » : liste de clés séparées par « | » dans la colonne `sources` ;
 *   - « derived » : calculé par VFF à partir de champs déjà libres.
 * Aucun texte rédigé n'est repris : ces fichiers ne portent que des faits (valeurs, dates, identifiants, liens).
 */
final class OpenDataDictionary
{
    public const COLUMN = 'column:source';

    public const COLUMN_LIST = 'column:sources';

    public const DERIVED = 'derived';

    /** @return array<string, list<array{0: string, 1: string, 2: string, 3: string, 4: string}>> fichier => [champ, fr, en, de, provenance] */
    public static function all(): array
    {
        $c = self::COLUMN;

        return [
            'signalisation-evenements.csv' => [
                ['event_id', 'identifiant du fait', 'identifier of the fact', 'Kennung der Angabe', 'vff'],
                ['line_id', 'identifiant de la ligne', 'line identifier', 'Kennung der Strecke', 'vff'],
                ['line_slug', 'adresse de la fiche de la ligne', 'slug of the line page', 'Adresse der Streckenseite', 'vff'],
                ['line_code', 'code de la ligne (RFN)', 'line code (RFN)', 'Streckennummer (RFN)', 'vff'],
                ['system', 'système (BAL, BAPR, BM, CT, TVM-300, TVM-430, ETCS_L1, ETCS_L2, KVB, CC)', 'system (BAL, BAPR, BM, CT, TVM-300, TVM-430, ETCS_L1, ETCS_L2, KVB, CC)', 'System (BAL, BAPR, BM, CT, TVM-300, TVM-430, ETCS_L1, ETCS_L2, KVB, CC)', $c],
                ['family', 'famille (cantonnement automatique, manuel, téléphonique, cabine, commande centralisée)', 'family (automatic, manual, telephone block, cab signalling, centralised control)', 'Familie (selbsttätiger, manueller, telefonischer Blockbetrieb, Führerraumsignalisierung, zentrale Steuerung)', self::DERIVED],
                ['role', 'rôle : block (cantonnement), cab (cabine), remote_control (commande à distance), speed_control (contrôle de vitesse)', 'role: block, cab, remote_control, speed_control', 'Rolle: block (Blockbetrieb), cab (Führerraum), remote_control (Fernsteuerung), speed_control (Geschwindigkeitsüberwachung)', $c],
                ['event_date', 'date de mise en service (AAAA-MM-JJ)', 'date of entry into service (YYYY-MM-DD)', 'Datum der Inbetriebnahme (JJJJ-MM-TT)', $c],
                ['date_precision', 'précision de la date : day, month ou year', 'date precision: day, month or year', 'Genauigkeit des Datums: day, month oder year', $c],
                ['scope', 'portée : whole_line, section ou unspecified', 'scope: whole_line, section or unspecified', 'Geltungsbereich: whole_line, section oder unspecified', $c],
                ['section_from, section_to', 'lieux d\'extrémité de la portion, tels que la source les nomme', 'end places of the section, as the source names them', 'Endorte des Abschnitts, wie die Quelle sie nennt', $c],
                ['section_pk_start_m, section_pk_end_m', 'points kilométriques de la portion placée, en mètres', 'kilometre posts of the located section, in metres', 'Kilometrierung des verorteten Abschnitts in Metern', self::DERIVED],
                ['section_location', 'comment la portion a été placée (stations, landmarks, article_pk, unlocated)', 'how the section was located (stations, landmarks, article_pk, unlocated)', 'Wie der Abschnitt verortet wurde (stations, landmarks, article_pk, unlocated)', self::DERIVED],
                ['confidence', 'fiabilité du fait : high (jour ou mois sûr) ou medium (année)', 'reliability: high (day or month known) or medium (year)', 'Verlässlichkeit: high (Tag oder Monat bekannt) oder medium (Jahr)', 'vff'],
                ['origin', 'où le fait a été lu : text, list, opening, document', 'where the fact was read: text, list, opening, document', 'Wo die Angabe gelesen wurde: text, list, opening, document', 'vff'],
                ['verification', 'niveau de vérification (official : document officiel lu)', 'verification level (official: official document read)', 'Prüfstufe (official: amtliches Dokument gelesen)', 'vff'],
                ['source', 'clé du fournisseur de la donnée (registre des droits)', 'provider key of the value (rights register)', 'Schlüssel des Datenlieferanten (Rechteregister)', $c],
                ['licence', 'licence du fournisseur', 'provider licence', 'Lizenz des Lieferanten', $c],
                ['source_title, source_revision, source_url', 'titre de l\'article, numéro de révision lue et lien vers cette révision (articles seulement)', 'article title, revision read and link to that revision (articles only)', 'Titel des Artikels, gelesene Version und Link zu dieser Version (nur Artikel)', $c],
            ],
            'signalisation-par-epoque.csv' => [
                ['era', 'décennie de la carte ; 2030 désigne l\'état au jour de l\'export', 'map decade; 2030 is the state on the export day', 'Jahrzehnt der Karte; 2030 steht für den Stand am Exporttag', self::DERIVED],
                ['in_force_on', 'jour auquel l\'état est lu : 31 décembre de la décennie, ou le jour de l\'export', 'day the state is read at: 31 December of the decade, or the export day', 'Tag, an dem der Stand gelesen wird: 31. Dezember des Jahrzehnts oder Exporttag', self::DERIVED],
                ['line_slug, line_code', 'ligne (adresse de la fiche et code RFN)', 'line (page slug and RFN code)', 'Strecke (Seitenadresse und RFN-Nummer)', 'vff'],
                ['event_id', 'fait de signalisation en vigueur (voir signalisation-evenements.csv)', 'signalling fact in force (see signalisation-evenements.csv)', 'geltende Signalisierungsangabe (siehe signalisation-evenements.csv)', self::DERIVED],
                ['system, family, role, event_date, scope, section_from, section_to', 'mêmes champs que dans signalisation-evenements.csv', 'same fields as in signalisation-evenements.csv', 'dieselben Felder wie in signalisation-evenements.csv', $c],
                ['source, licence', 'fournisseur du fait et sa licence', 'provider of the fact and its licence', 'Lieferant der Angabe und seine Lizenz', $c],
            ],
            'materiel-preserve-exemplaires.csv' => [
                ['material_slug', 'série de matériel (adresse de la fiche) ; vide pour une pièce protégée sans série', 'rolling stock series (page slug); empty for a protected piece with no series', 'Fahrzeugreihe (Seitenadresse); leer bei einem geschützten Stück ohne Reihe', 'vff'],
                ['number', 'numéro de l\'exemplaire tel que la source l\'écrit', 'unit number as the source writes it', 'Nummer des Fahrzeugs, wie die Quelle sie schreibt', self::COLUMN_LIST],
                ['designation', 'désignation de la notice Palissy (pièces sans série seulement)', 'Palissy record designation (pieces with no series only)', 'Bezeichnung des Palissy-Eintrags (nur Stücke ohne Reihe)', 'palissy'],
                ['venue_slug', 'lieu de conservation (voir materiel-preserve-lieux.csv)', 'place of preservation (see materiel-preserve-lieux.csv)', 'Aufbewahrungsort (siehe materiel-preserve-lieux.csv)', self::COLUMN_LIST],
                ['place_said', 'lieu tel que la source le dit, quand il n\'est pas dans la liste des lieux', 'place as the source says it, when it is not in the venue list', 'Ort, wie die Quelle ihn nennt, wenn er nicht in der Ortsliste steht', self::COLUMN_LIST],
                ['state', 'état dit par la source : running, exposed, restoration, stored', 'state said by the source: running, exposed, restoration, stored', 'Zustand laut Quelle: running, exposed, restoration, stored', self::COLUMN_LIST],
                ['protection_kind, protection_date, protection_year, protection_ref', 'protection au titre des monuments historiques : nature (classified, listed, declassified), date, année et référence de la notice', 'listed-heritage protection: kind, date, year and record reference', 'Denkmalschutz: Art, Datum, Jahr und Kennung des Eintrags', 'palissy'],
                ['sources', 'clés des fournisseurs des faits, séparées par |', 'provider keys of the facts, separated by |', 'Schlüssel der Lieferanten, durch | getrennt', self::COLUMN_LIST],
                ['source_urls', 'liens vers les révisions et notices lues, séparés par un espace', 'links to the revisions and records read, separated by a space', 'Links zu den gelesenen Versionen und Einträgen, durch Leerzeichen getrennt', self::COLUMN_LIST],
            ],
            'materiel-preserve-lieux.csv' => [
                ['venue_slug', 'identifiant du lieu de conservation', 'place of preservation identifier', 'Kennung des Aufbewahrungsorts', 'vff'],
                ['name, type, commune, tourist_railway', 'nom, type (museum, association, tourist_railway, site), commune et exploitation touristique du lieu', 'name, type (museum, association, tourist_railway, site), commune and tourist railway of the place', 'Name, Art (museum, association, tourist_railway, site), Gemeinde und Museumsbahn des Ortes', self::COLUMN_LIST],
                ['pieces', 'nombre d\'exemplaires du lieu dans le fichier des exemplaires', 'number of units of the place in the units file', 'Anzahl der Fahrzeuge des Ortes in der Fahrzeugdatei', self::DERIVED],
                ['sources', 'fournisseurs des exemplaires qui désignent ce lieu', 'providers of the units that name this place', 'Lieferanten der Fahrzeuge, die diesen Ort nennen', self::COLUMN_LIST],
            ],
            'projets.csv' => [
                ['project_id, name, kind', 'identifiant, nom et type du projet (reopening, new_line, lgv, rer, electrification)', 'project identifier, name and kind (reopening, new_line, lgv, rer, electrification)', 'Kennung, Name und Art des Projekts (reopening, new_line, lgv, rer, electrification)', 'vff'],
                ['regions, line_slugs', 'régions concernées et fiches de lignes liées, séparées par |', 'regions concerned and linked line pages, separated by |', 'betroffene Regionen und verknüpfte Streckenseiten, durch | getrennt', 'vff'],
                ['suspended', 'true si le projet est suspendu, reporté sans date ou abandonné', 'true if the project is suspended, postponed with no date or abandoned', 'true, wenn das Projekt ausgesetzt, ohne Termin verschoben oder aufgegeben ist', 'vff'],
                ['checked_on', 'jour de la dernière vérification', 'day of the last check', 'Tag der letzten Prüfung', 'vff'],
                ['source, licence', 'relevé de VFF et sa licence', 'VFF survey and its licence', 'Erhebung von VFF und ihre Lizenz', 'vff'],
            ],
            'projets-etapes.csv' => [
                ['project_id', 'projet (voir projets.csv)', 'project (see projets.csv)', 'Projekt (siehe projets.csv)', 'vff'],
                ['stage', 'étape : study, debate, dup, funding, works, service, commissioning', 'stage: study, debate, dup, funding, works, service, commissioning', 'Etappe: study, debate, dup, funding, works, service, commissioning', $c],
                ['stage_date, date_precision', 'date de l\'étape (AAAA, AAAA-MM ou AAAA-MM-JJ) et sa précision', 'date of the stage (YYYY, YYYY-MM or YYYY-MM-DD) and its precision', 'Datum der Etappe (JJJJ, JJJJ-MM oder JJJJ-MM-TT) und seine Genauigkeit', $c],
                ['done', 'true si l\'étape est franchie ; false : date prévue, jamais certaine', 'true if the stage is passed; false: planned date, never certain', 'true, wenn die Etappe erreicht ist; false: geplantes Datum, nie sicher', $c],
                ['source, licence, source_url, source_published', 'fournisseur du fait, sa licence, lien et date de publication de la page lue', 'provider of the fact, its licence, link and publication date of the page read', 'Lieferant der Angabe, seine Lizenz, Link und Veröffentlichungsdatum der gelesenen Seite', $c],
                ['link_role', 'source : le fait vient du fournisseur libre du lien ; citation : étape relevée et rédigée par VFF (source vff), le lien (presse, ministère, collectivité) est seulement cité', 'source: the fact comes from the open provider of the link; citation: stage surveyed and written by VFF (source vff), the link (press, ministry, local authority) is only cited', 'source: die Angabe stammt vom freien Lieferanten des Links; citation: von VFF erhobene und formulierte Etappe (Quelle vff), der Link (Presse, Ministerium, Gebietskörperschaft) wird nur zitiert', self::DERIVED],
            ],
            'voies.csv' => [
                ['line_slug, line_code', 'ligne (adresse de la fiche et code RFN)', 'line (page slug and RFN code)', 'Strecke (Seitenadresse und RFN-Nummer)', 'sncf'],
                ['section_rank', 'rang du tronçon dans la ligne selon SNCF Réseau', 'section rank in the line according to SNCF Réseau', 'Rang des Abschnitts in der Strecke laut SNCF Réseau', 'sncf'],
                ['pk_start_m, pk_end_m', 'points kilométriques en mètres', 'kilometre posts in metres', 'Kilometrierung in Metern', 'sncf'],
                ['regime, tracks', 'régime d\'exploitation (voie unique, double voie…) et nombre de voies', 'operating regime (single track, double track…) and number of tracks', 'Betriebsart (eingleisig, zweigleisig…) und Anzahl der Gleise', 'sncf'],
                ['source, licence, dataset_modified_on', 'fournisseur (SNCF Réseau), licence ODbL 1.0 et date de modification du jeu', 'provider (SNCF Réseau), ODbL 1.0 licence and dataset modification date', 'Lieferant (SNCF Réseau), Lizenz ODbL 1.0 und Änderungsdatum des Datensatzes', $c],
            ],
            'radio.csv' => [
                ['line_slug, line_code', 'ligne (adresse de la fiche et code RFN)', 'line (page slug and RFN code)', 'Strecke (Seitenadresse und RFN-Nummer)', 'sncf'],
                ['section_rank, pk_start_m, pk_end_m', 'rang du tronçon et points kilométriques en mètres', 'section rank and kilometre posts in metres', 'Rang des Abschnitts und Kilometrierung in Metern', 'sncf'],
                ['system, label', 'système radio sol-train (gsm-r, gsm-gfu, rst…) et son libellé', 'ground-train radio system (gsm-r, gsm-gfu, rst…) and its label', 'Zugfunksystem (gsm-r, gsm-gfu, rst…) und seine Bezeichnung', 'sncf'],
                ['source, licence, dataset_modified_on', 'fournisseur (SNCF Réseau), licence ODbL 1.0 et date de modification du jeu', 'provider (SNCF Réseau), ODbL 1.0 licence and dataset modification date', 'Lieferant (SNCF Réseau), Lizenz ODbL 1.0 und Änderungsdatum des Datensatzes', $c],
            ],
            'quais.csv' => [
                ['station_slug', 'gare de VFF rattachée au quai (vide si non rattaché)', 'VFF station linked to the platform (empty if unlinked)', 'der Bahnhof von VFF, dem der Bahnsteig zugeordnet ist (leer, wenn nicht zugeordnet)', self::DERIVED],
                ['sncf_station_name, line_code, track_code, section_rank', 'nom de la gare chez SNCF, code de la ligne et de la voie, rang du tronçon', 'station name at SNCF, line and track codes, section rank', 'Bahnhofsname bei SNCF, Strecken- und Gleiscode, Rang des Abschnitts', 'sncf'],
                ['label, length_m', 'libellé du quai et longueur en mètres', 'platform label and length in metres', 'Bezeichnung des Bahnsteigs und Länge in Metern', 'sncf'],
                ['pk_start_m, pk_end_m, lat, lon', 'points kilométriques et position (WGS 84)', 'kilometre posts and position (WGS 84)', 'Kilometrierung und Position (WGS 84)', 'sncf'],
                ['match_method', 'comment le quai a été rattaché à une gare (uic, site, name)', 'how the platform was linked to a station (uic, site, name)', 'Wie der Bahnsteig einem Bahnhof zugeordnet wurde (uic, site, name)', self::DERIVED],
                ['source, licence, dataset_modified_on', 'fournisseur (SNCF Réseau), licence ODbL 1.0 et date de modification du jeu', 'provider (SNCF Réseau), ODbL 1.0 licence and dataset modification date', 'Lieferant (SNCF Réseau), Lizenz ODbL 1.0 und Änderungsdatum des Datensatzes', $c],
            ],
            'cantonnement.csv' => [
                ['line_slug, line_code', 'ligne (adresse de la fiche et code RFN)', 'line (page slug and RFN code)', 'Strecke (Seitenadresse und RFN-Nummer)', 'sncf'],
                ['pk_start_m, pk_end_m', 'points kilométriques de la portion, en mètres', 'kilometre posts of the portion, in metres', 'Kilometrierung des Abschnitts in Metern', 'sncf'],
                ['signalling, signalling_systems, block_label', 'code de cantonnement de VFF, systèmes et libellé de SNCF Réseau', 'VFF block code, systems and SNCF Réseau label', 'Blockcode von VFF, Systeme und Bezeichnung von SNCF Réseau', 'sncf'],
                ['train_protection, kvb_equipped', 'contrôles de vitesse (KVB…) et équipement KVB', 'train protection (KVB…) and KVB equipment', 'Zugsicherung (KVB…) und KVB-Ausrüstung', 'sncf'],
                ['source, licence', 'fournisseur (SNCF Réseau) et licence ODbL 1.0', 'provider (SNCF Réseau) and ODbL 1.0 licence', 'Lieferant (SNCF Réseau) und Lizenz ODbL 1.0', $c],
            ],
            'accidents-sources.csv' => [
                ['accident_id, accident_slug', 'accident publié (voir accidents.csv)', 'published accident (see accidents.csv)', 'veröffentlichter Unfall (siehe accidents.csv)', 'vff'],
                ['context_checked_on', 'jour où VFF a relu les documents de l\'accident', 'day VFF proofread the accident documents', 'Tag, an dem VFF die Dokumente des Unfalls nachgelesen hat', 'vff'],
                ['position', 'rang de la référence dans la fiche', 'rank of the reference in the page', 'Rang der Quelle in der Seite', 'vff'],
                ['provider_family, kind, reliability, access', 'famille de la source : fournisseur déclaré, nature (official, report, press, encyclopedia, book…), fiabilité et accès (open, link_only, paywall)', 'source family: declared provider, kind (official, report, press, encyclopedia, book…), reliability and access (open, link_only, paywall)', 'Quellenfamilie: angegebener Lieferant, Art (official, report, press, encyclopedia, book…), Verlässlichkeit und Zugang (open, link_only, paywall)', 'vff'],
                ['published', 'date de publication de la source, telle qu\'elle est donnée', 'publication date of the source, as given', 'Veröffentlichungsdatum der Quelle, wie angegeben', 'vff'],
                ['source, licence, source_url', 'fournisseur retenu (registre des droits), sa licence et le lien', 'provider retained (rights register), its licence and the link', 'Lieferant laut Rechteregister, seine Lizenz und der Link', $c],
            ],
            'redirections-gares.csv' => [
                ['old_slug, station_slug', 'ancienne adresse de la fiche gare et adresse qui la remplace (redirection 301)', 'former station page slug and the slug that replaces it (301 redirect)', 'frühere Adresse der Bahnhofsseite und die Adresse, die sie ersetzt (301-Weiterleitung)', 'vff'],
                ['source, licence', 'relevé de VFF et sa licence', 'VFF record and its licence', 'Erfassung von VFF und ihre Lizenz', 'vff'],
            ],
            'redirections-lignes.csv' => [
                ['old_slug, line_slug', 'ancienne adresse de la fiche ligne et adresse qui la remplace (redirection 301)', 'former line page slug and the slug that replaces it (301 redirect)', 'frühere Adresse der Streckenseite und die Adresse, die sie ersetzt (301-Weiterleitung)', 'vff'],
                ['source, licence', 'relevé de VFF et sa licence', 'VFF record and its licence', 'Erfassung von VFF und ihre Lizenz', 'vff'],
            ],
            'profils-lignes.csv' => [
                ['line_slug, line_code', 'ligne (adresse de la fiche et code RFN)', 'line (page slug and RFN code)', 'Strecke (Seitenadresse und RFN-Nummer)', 'vff'],
                ['status', 'published, ou withheld quand le profil a été mesuré sur un tracé non libre (mesures vides)', 'published, or withheld when the profile was measured on a non-open trace (measures empty)', 'published, oder withheld, wenn das Profil auf einer nicht freien Trasse gemessen wurde (Messwerte leer)', self::DERIVED],
                ['step_m, length_m, trace_length_m, covered_m, off_path_m', 'pas d\'échantillonnage, longueur du profil, longueur du tracé, longueur couverte et longueur hors tracé, en mètres', 'sampling step, profile length, trace length, covered length and off-path length, in metres', 'Abtastschritt, Länge des Profils, Länge der Trasse, abgedeckte Länge und Länge abseits der Trasse in Metern', 'ign'],
                ['min_altitude_m, max_altitude_m, ascent_m, descent_m', 'altitudes extrêmes, cumul des montées et des descentes, en mètres (RGE ALTI et BD TOPO de l\'IGN)', 'extreme altitudes, total ascent and descent, in metres (IGN RGE ALTI and BD TOPO)', 'Höhenextreme, Summe der Anstiege und Abstiege in Metern (RGE ALTI und BD TOPO des IGN)', 'ign'],
                ['max_grade_permil, max_grade_from_m, max_grade_to_m, max_grade_direction', 'déclivité maximale mesurée en pour mille, portion (distance en mètres) et sens (up, down)', 'measured maximum gradient in permil, portion (distance in metres) and direction (up, down)', 'gemessene maximale Neigung in Promille, Abschnitt (Entfernung in Metern) und Richtung (up, down)', 'ign'],
                ['grade_status', 'raison pour laquelle la déclivité mesurée n\'est pas publiée (terrain_only, tunnel, interpolated…)', 'reason the measured gradient is not published (terrain_only, tunnel, interpolated…)', 'Grund, warum die gemessene Neigung nicht veröffentlicht wird (terrain_only, tunnel, interpolated…)', 'vff'],
                ['source, licence', 'fournisseur des altitudes (IGN) et licence Licence Ouverte 2.0', 'provider of the altitudes (IGN) and Licence Ouverte 2.0 licence', 'Lieferant der Höhen (IGN) und Lizenz Licence Ouverte 2.0', $c],
            ],
            'profils-points.csv' => [
                ['line_slug', 'ligne (adresse de la fiche)', 'line (page slug)', 'Strecke (Seitenadresse)', 'vff'],
                ['seq, distance_m, pk_m', 'rang du point, distance le long du parcours et point kilométrique, en mètres', 'point rank, distance along the route and kilometre post, in metres', 'Rang des Punkts, Entfernung entlang der Strecke und Kilometrierung in Metern', self::DERIVED],
                ['altitude_m', 'altitude du point en mètres', 'altitude of the point in metres', 'Höhe des Punktes in Metern', 'ign'],
                ['altitude_source, trace_source', 'fournisseur de l\'altitude et du tracé sur lequel le point est placé (clés du registre des droits)', 'provider of the altitude and of the trace the point sits on (rights register keys)', 'Lieferant der Höhe und der Trasse, auf der der Punkt liegt (Schlüssel des Rechteregisters)', 'column:altitude_source, trace_source'],
                ['interpolated, structure, branch', 'point interpolé (true), ouvrage (tunnel, bridge) et branche du parcours', 'interpolated point (true), structure (tunnel, bridge) and route branch', 'interpolierter Punkt (true), Bauwerk (tunnel, bridge) und Ast der Strecke', self::DERIVED],
            ],
            'declivites-sourcees.csv' => [
                ['line_slug', 'ligne (adresse de la fiche)', 'line (page slug)', 'Strecke (Seitenadresse)', 'vff'],
                ['max_grade_permil', 'déclivité maximale publiée par la source, en pour mille', 'maximum gradient published by the source, in permil', 'von der Quelle veröffentlichte maximale Neigung in Promille', $c],
                ['covered_share', 'part de la ligne couverte par les données de la source (0 à 1 ; SNCF Réseau seulement)', 'share of the line covered by the source data (0 to 1; SNCF Réseau only)', 'Anteil der Strecke, den die Daten der Quelle abdecken (0 bis 1; nur SNCF Réseau)', $c],
                ['source, licence, source_title, source_url', 'fournisseur (SNCF Réseau ou Wikipédia), sa licence, titre du jeu ou de l\'article et lien vers la révision ou le jeu lu', 'provider (SNCF Réseau or Wikipedia), its licence, dataset or article title and link to the revision or dataset read', 'Lieferant (SNCF Réseau oder Wikipedia), seine Lizenz, Titel des Datensatzes oder Artikels und Link zur gelesenen Version', $c],
            ],
        ];
    }

    /**
     * Lignes du dictionnaire des fichiers d'un jeu : [fichier, champ, fr, en, de, provenance, licence].
     *
     * @param  list<string>  $files  fichiers écrits dans le jeu
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public static function rows(array $files, OpenDataRights $rights): array
    {
        $all = self::all();
        $out = [];
        foreach ($files as $file) {
            foreach ($all[$file] ?? [] as [$field, $fr, $en, $de, $origin]) {
                $licence = 'par ligne (colonne licence) / per row (licence column)';
                if (! str_starts_with($origin, 'column:') && $origin !== self::DERIVED) {
                    $info = $rights->registry()->provider($origin);
                    $licence = $info !== null ? OpenDataRights::shortLicence($origin, (string) ($info['licence'] ?? '')) : '';
                } elseif ($origin === self::DERIVED) {
                    $licence = 'ODbL 1.0';
                }
                $out[] = [$file, $field, $fr, $en, $de, $origin, $licence];
            }
        }

        return $out;
    }

    /** Fournisseurs fixes cités par le dictionnaire de ces fichiers (pour le contrôle des droits et les crédits). */
    public static function fixedProviders(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            foreach (self::all()[$file] ?? [] as $entry) {
                if (! str_starts_with($entry[4], 'column:') && $entry[4] !== self::DERIVED) {
                    $out[$entry[4]] = true;
                }
            }
        }

        return array_keys($out);
    }
}
