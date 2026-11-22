<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portions voyageurs d'une ligne à l'ère courante, pour vff:classify-lines
 * (App\Services\LineClassifier). Une ligne n'est plus « mixte » en entier dès
 * qu'une gare de son code a des voyageurs : le trafic se décide PORTION PAR
 * PORTION (Provins - Esternay, Rognac - Aix, Cheval-Blanc - Pertuis étaient
 * mixtes en 2030 alors qu'ils sont fret seul).
 *
 * Repère commun : « m », le PK interpolé sur le tronçon SNCF (sncf_line_geometries)
 * du même code le plus proche (<= SNAP_M). Le PK officiel des gares (pk_m) n'est
 * pas linéaire avec la longueur (écarts de plusieurs km) : gares ET tronçons sont
 * donc projetés de la même façon, ce qui garde l'ordre des gares (13 inversions
 * sur 5 654 paires de gares consécutives).
 *
 * Ordre de priorité des signaux (du plus fort au plus faible) :
 *   1. catégorie SNCF LGV (sncf_line_catlig) : voyageurs, toute la ligne (LineClassifier) ;
 *   2. type SNCF (sncf_line_types) : voie portuaire seule = fret ; raccordement /
 *      voie-mère seuls = hérite des voisins (LineClassifier) ;
 *   3. statut SNCF du tronçon (sncf_line_geometries.libelle) : une gare ou une
 *      portion hors tronçon « Exploitée » n'est pas desservie ;
 *   4. fermeture aux voyageurs datée récente (closure_passenger depuis 2010, ou
 *      closure de confiance haute depuis 2010, sans réouverture postérieure qui
 *      la couvre) : la gare ou la portion à l'intérieur n'est plus desservie ;
 *      de même une fermeture aux voyageurs ancienne suivie d'une réouverture
 *      LOCALISÉE ailleurs sur la ligne, aucune ne couvrant la gare (800000 : rive
 *      droite fermée en 1973, rouverte en 2022 de Pont-Saint-Esprit à Grezan seulement ;
 *      Saint-Péray et Le Teil, « voyageurs » dans liste-des-gares, sont des arrêts de car) ;
 *   5. drapeau voyageurs de liste-des-gares (sncf_gares.voyageurs) ;
 *   6. drapeau fret de liste-des-gares : fret seul hors des portions voyageurs ;
 *   7. trafic daté par le moteur (EraTimeline) : hors portion voyageurs sans gare fret.
 * Gares d'une ligne : celles de son code dans liste-des-gares, PLUS les gares du jeu « gares de
 * voyageurs » SNCF (station_sources « sncf_voyageurs ») posées sur son tracé en service de l'ère,
 * quel que soit leur code (adopt()) : liste-des-gares rattache Grasse au code 930000 (avec les
 * coordonnées de Marseille) et ignore Mouans-Sartoux, Ranguin, Le Bosquet ; 944000 Cannes-la
 * Bocca - Grasse, rouverte aux voyageurs le 26/03/2005, était donc « fret » en 2030.
 * Une réouverture aux voyageurs datée et sourcée (line_events), sans fermeture voyageurs après
 * (jusqu'à la date de l'ère), met aussi dans la portion voyageurs les tronçons qu'elle couvre.
 * Les fermetures « tout trafic » de Wikipédia ou d'OpenHistoricalMap (confiance
 * moyenne ou basse) ne comptent pas : sur un tronçon que le moteur garde en
 * service, elles sont presque toujours temporaires ou mal localisées (878000
 * fermée « entièrement » en 2017 alors que seul Saint-Claude - Oyonnax l'est).
 *
 * Portion voyageurs (zone) d'une ligne :
 *   - entre deux gares voyageurs desservies consécutives (ordre m), sauf si une
 *     gare voyageurs NON desservie du même code se trouve entre elles ;
 *   - prolongée jusqu'au bout de la ligne quand ce bout touche le réseau voyageurs
 *     (gare voyageurs desservie de tout code à moins de EXTREMITY_STATION_M, ou
 *     tronçon d'une autre ligne voyageurs à moins de JUNCTION_M), ou qu'il est à
 *     moins de TERMINUS_M (voies de la gare terminus) : les trains
 *     voyageurs traversent les haltes fermées jusqu'à la gare de jonction, rattachée
 *     à l'autre code (Culmont - Merrey, Châlons - Mourmelon, Longueville - Provins).
 *     Bout à l'étranger (hors des communes françaises, rails OSM au-delà) : branché
 *     aussi (Modane - Fréjus, Zoufftgen, Lauterbourg, ligne de Tende).
 *     Une gare terminus desservie en cul-de-sac (Fécamp, Lannion) se prolonge jusqu'à
 *     l'autre bout même si l'écart a une fermeture ancienne : les trains passent par là.
 *     Sauf si l'écart porte une gare voyageurs non desservie, ou si plus de la moitié
 *     de l'écart est fermée aux voyageurs (closure_passenger, ou closure de confiance haute, toute date)
 *     sans réouverture localisée qui la couvre (Provins - Esternay fermée en 1952,
 *     Givors - Saint-Péray fermée en 1973) ;
 *   - une gare isolée (seule gare desservie, voisines non desservies) se prolonge
 *     de même vers les bouts ; sinon elle ne rend mixte que le tronçon qui la porte
 *     en son intérieur, jamais un bout de ligne (Rognac, gare de bifurcation au bout
 *     de Rognac - Aix fermée aux voyageurs en 1939).
 * Hors zone, un tronçon sur une forme SNCF non « Exploitée » (neutralisée, déclassée,
 * cédée à la RATP…) garde le trafic du moteur : « fret seul » exige l'exploitation SNCF.
 * Les tronçons du moteur sont coupés aux gares qui bornent une zone (morceaux
 * d'au moins MIN_PIECE_M), pour qu'une même ligne puisse être mixte sur une
 * partie et fret seul sur l'autre.
 */
class PassengerPortions
{
    /** Distance maximale (m) d'une gare ou d'un point de tronçon à la forme SNCF de son code. */
    public const SNAP_M = 200;

    /** Une gare est sur un tronçon « Exploitée » si l'un d'eux passe à moins de (m). */
    public const STATION_EXPLOITED_M = 50;

    /** Idem pour le milieu d'un tronçon VFF (tracé précis, forme SNCF généralisée jusqu'à 165 m). */
    public const PIECE_EXPLOITED_M = 200;

    /** Une gare ou un milieu de tronçon est dans la portion d'une fermeture à moins de (m)… */
    public const CLOSURE_NEAR_M = 100;

    /** … et à plus de (m) de ses extrémités (la gare qui borne la fermeture reste desservie). */
    public const CLOSURE_END_M = 300;

    /** Fermetures aux voyageurs plus récentes que les drapeaux de liste-des-gares. */
    public const RECENT_CLOSURE_FROM = '2010-01-01';

    /** Longueur minimale (m) de chaque morceau d'un tronçon coupé à une gare. */
    public const MIN_PIECE_M = 150;

    /** Gare voyageurs au bout de la ligne : à moins de (m) de l'extrémité (bifurcation à 830 m de Lison). */
    public const EXTREMITY_STATION_M = 1000;

    /** Écart (m) entre la zone et le bout de ligne toujours comblé : voies de la gare terminus. */
    public const TERMINUS_M = 500;

    /** Tronçons non projetés à moins de (m) du bout projeté : gril de la gare de jonction (Sète). */
    public const END_AREA_M = 3000;

    /** Bout de ligne branché sur une autre ligne voyageurs : tronçon actif à moins de (m). */
    public const JUNCTION_M = 60;

    /**
     * Rayon (degrés) dans lequel une commune française doit exister pour qu'un bout de ligne hors
     * des communes soit « à l'étranger » (et pas hors des contours chargés). 0,2° ≈ 16 km en
     * longitude : la gare de Vintimille, terminus de 930000, est à 6 km de la frontière et à
     * 0,09° de Menton (l'ancien rayon de 0,07° la laissait « hors France inconnue », et
     * Menton-Garavan - Vintimille, 7,8 km, hors portion voyageurs).
     */
    public const ABROAD_NEAR_FRANCE_DEG = 0.2;

    /** Part (longueur) de l'écart fermée aux voyageurs au-delà de laquelle on ne prolonge pas. */
    public const EXTEND_MAX_CLOSED = 0.5;

    /**
     * Gare de voyageurs SNCF comptée pour une ligne d'un autre code : à moins de (m) de son tracé
     * en service. Les gares sont recalées sur les voies (98 % à moins de 24 m d'un tronçon en
     * service, toutes à moins de 100 m) : 100 m garde une gare dont le point est sur le
     * bâtiment voyageurs, sans atteindre une ligne voisine (entraxe des lignes parallèles).
     */
    public const ADOPT_M = 100;

    /**
     * … et plus proche de ce tracé que de toute autre ligne en service d'au moins (m) : une gare
     * de bifurcation (Cannes-la-Bocca, sur 930000 et 944000) ou d'une ligne parallèle n'est pas
     * adoptée (une ligne fret qui part d'une gare voyageurs resterait sinon « mixte »).
     */
    public const ADOPT_MARGIN_M = 25;

    /** Une gare de liste-des-gares voyageurs à moins de (m) : la gare est déjà comptée par son code. */
    public const ADOPT_LISTED_M = 300;

    /** @var array<string, list<array<string, mixed>>> code => gares (voyageurs ou fret) */
    private array $stations = [];

    /** @var list<array{lon: float, lat: float}> gares voyageurs desservies, tous codes */
    private array $served = [];

    private bool $loaded = false;

    /** Ère des gares adoptées (adopt()), null si aucune. */
    private ?int $adoptedEra = null;

    /** Codes qui ont une gare voyageurs desservie (tableau PostgreSQL), calculé à la demande. */
    private ?string $passengerCodes = null;

    // ─── SQL partagé (aussi utilisé par DataInvariants, en lecture seule) ───

    /** Point PostGIS d'une gare sncf_gares (NULL sans coordonnées). */
    public static function stationPoint(string $g): string
    {
        return "ST_SetSRID(ST_MakePoint({$g}.lon, {$g}.lat), 4326)";
    }

    /**
     * Le point est-il sur un tronçon SNCF « Exploitée » du code (ou loin de toute forme SNCF du code) ?
     */
    public static function exploitedSql(string $point, string $code, int $near = self::STATION_EXPLOITED_M): string
    {
        $snap = self::SNAP_M;

        return "(EXISTS (SELECT 1 FROM sncf_line_geometries xs WHERE xs.code_ligne = {$code} AND xs.libelle ILIKE 'Exploit%'
                    AND xs.geom && ST_Expand({$point}, 0.0035) AND ST_DWithin(xs.geom::geography, ({$point})::geography, {$near}))
                 OR NOT EXISTS (SELECT 1 FROM sncf_line_geometries xs WHERE xs.code_ligne = {$code}
                    AND xs.geom && ST_Expand({$point}, 0.0035) AND ST_DWithin(xs.geom::geography, ({$point})::geography, {$snap})))";
    }

    /**
     * Le point est-il à l'intérieur d'une fermeture aux voyageurs récente, non suivie d'une
     * réouverture qui le couvre ? $lineFilter : condition sur cl (lines) ou ce (line_events).
     */
    public static function recentlyClosedSql(string $point, string $lineFilter): string
    {
        $near = self::CLOSURE_NEAR_M;
        $from = self::RECENT_CLOSURE_FROM;

        return "EXISTS (SELECT 1 FROM line_events ce JOIN lines cl ON cl.id = ce.line_id
                 WHERE {$lineFilter}
                   AND (ce.type = 'closure_passenger' OR (ce.type = 'closure' AND ce.confidence = 'high'))
                   AND ce.event_date >= DATE '{$from}' AND ce.section_geom IS NOT NULL
                   AND ce.section_geom && ST_Expand({$point}, 0.002)
                   AND ST_DWithin(ce.section_geom::geography, ({$point})::geography, {$near})
                   AND NOT ".self::nearSectionEndSql('ce.section_geom', $point)."
                   AND NOT EXISTS (SELECT 1 FROM line_events re WHERE re.line_id = ce.line_id
                                   AND re.type IN ('reopening', 'reopening_passenger', 'opening', 'opening_passenger')
                                   AND re.event_date > ce.event_date
                                   AND (re.section_geom IS NULL OR ST_DWithin(re.section_geom::geography, ({$point})::geography, {$near}))))";
    }

    /**
     * Le milieu d'un tronçon (de ls) est-il fermé aux voyageurs, à n'importe quelle date, sans
     * réouverture LOCALISÉE qui le couvre ? Sert seulement à refuser un prolongement de zone.
     * Fermeture « tout trafic » : seulement de confiance haute (la fermeture du chemin de fer
     * Fell en 1871, lue comme « fermeture » de toute la ligne 900000, ne bloque pas Culoz).
     */
    private static function passengerClosedSql(string $point): string
    {
        $near = self::CLOSURE_NEAR_M;

        return "EXISTS (SELECT 1 FROM line_events pe WHERE pe.line_id = ls.line_id
                   AND (pe.type = 'closure_passenger' OR (pe.type = 'closure' AND pe.confidence = 'high'))
                   AND pe.section_geom IS NOT NULL
                   AND pe.section_geom && ST_Expand({$point}, 0.002)
                   AND ST_DWithin(pe.section_geom::geography, ({$point})::geography, {$near})
                   AND NOT EXISTS (SELECT 1 FROM line_events pr WHERE pr.line_id = pe.line_id
                                   AND pr.type IN ('reopening', 'reopening_passenger', 'opening', 'opening_passenger')
                                   AND pr.event_date > pe.event_date AND pr.section_geom IS NOT NULL
                                   AND ST_DWithin(pr.section_geom::geography, ({$point})::geography, {$near})))";
    }

    /**
     * Le point est-il à moins de CLOSURE_END_M d'un VRAI bout de la portion $sec (la gare qui
     * borne une fermeture reste desservie) ? Le bout d'une partie d'une portion en plusieurs
     * morceaux, à moins de 500 m d'un autre morceau, est un trou du tracé, pas un bout (Le Teil
     * dans la fermeture de toute la ligne 800000).
     */
    private static function nearSectionEndSql(string $sec, string $point): string
    {
        $end = self::CLOSURE_END_M;

        return "EXISTS (SELECT 1 FROM ST_Dump(ST_Boundary({$sec})) sb
                 WHERE ST_DWithin(sb.geom::geography, ({$point})::geography, {$end})
                   AND NOT EXISTS (SELECT 1 FROM ST_Dump({$sec}) sp
                                   WHERE NOT ST_DWithin(sp.geom, sb.geom, 0.0000001)
                                     AND ST_DWithin(sp.geom::geography, sb.geom::geography, 500)))";
    }

    /**
     * Le point est-il dans une fermeture aux voyageurs (toute date) de la ligne suivie d'une
     * réouverture LOCALISÉE ailleurs, sans aucune réouverture (localisée ou non) qui le couvre ?
     */
    public static function reopenedElsewhereSql(string $point, string $lineFilter): string
    {
        $near = self::CLOSURE_NEAR_M;

        return 'EXISTS (SELECT 1 FROM line_events oe JOIN lines ol ON ol.id = oe.line_id
                 WHERE '.str_replace(['cl.', 'ce.'], ['ol.', 'oe.'], $lineFilter)."
                   AND (oe.type = 'closure_passenger' OR (oe.type = 'closure' AND oe.confidence = 'high'))
                   AND oe.section_geom IS NOT NULL
                   AND oe.section_geom && ST_Expand({$point}, 0.002)
                   AND ST_DWithin(oe.section_geom::geography, ({$point})::geography, {$near})
                   AND NOT ".self::nearSectionEndSql('oe.section_geom', $point)."
                   AND EXISTS (SELECT 1 FROM line_events orr WHERE orr.line_id = oe.line_id
                               AND orr.type IN ('reopening', 'reopening_passenger', 'opening_passenger')
                               AND orr.event_date > oe.event_date AND orr.section_geom IS NOT NULL)
                   AND NOT EXISTS (SELECT 1 FROM line_events or2 WHERE or2.line_id = oe.line_id
                                   AND or2.type IN ('reopening', 'reopening_passenger', 'opening', 'opening_passenger')
                                   AND or2.event_date > oe.event_date
                                   AND (or2.section_geom IS NULL OR ST_DWithin(or2.section_geom::geography, ({$point})::geography, {$near}))))";
    }

    /** Gare $g de liste-des-gares ouverte aux voyageurs ET desservie (signaux 3 et 4). */
    public static function servedStationSql(string $g): string
    {
        $p = self::stationPoint($g);

        return "({$g}.voyageurs AND ({$g}.lat IS NULL OR (".self::exploitedSql($p, "{$g}.code_ligne")
            .' AND NOT '.self::recentlyClosedSql($p, "cl.code = {$g}.code_ligne")
            .' AND NOT '.self::reopenedElsewhereSql($p, "cl.code = {$g}.code_ligne").')))';
    }

    /** m (PK interpolé sur la forme SNCF du code la plus proche) d'un point. */
    private static function mSql(string $point, string $code): string
    {
        $snap = self::SNAP_M;

        return "(SELECT s.pk_debut_m + ST_LineLocatePoint(s.geom, {$point}) * (s.pk_fin_m - s.pk_debut_m)
                 FROM sncf_line_geometries s WHERE s.code_ligne = {$code} AND s.pk_debut_m IS NOT NULL AND s.pk_fin_m IS NOT NULL
                   AND s.geom && ST_Expand({$point}, 0.0035) AND ST_DWithin(s.geom::geography, ({$point})::geography, {$snap})
                 ORDER BY s.geom <-> {$point} LIMIT 1)";
    }

    /** Date de l'état d'une ère : aujourd'hui pour l'ère courante, 31/12 de l'année sinon. */
    public static function eraDateSql(string $eraId): string
    {
        return "(SELECT CASE WHEN er.is_current THEN CURRENT_DATE ELSE make_date(er.year, 12, 31) END FROM eras er WHERE er.id = {$eraId})";
    }

    /**
     * Le point est-il couvert par une réouverture aux voyageurs datée (jour ou mois), sourcée, de
     * confiance haute ou moyenne, à la portion résolue (section_geom ; « toute la ligne » est résolue
     * en tracé, une portion « unresolved » ne vaut pas toute la ligne : 205000, 1981), antérieure à la date de l'ère, sans fermeture aux voyageurs (ou
     * fermeture de confiance haute) postérieure qui le couvre avant cette date ?
     * $lineId, $eraId : expressions SQL.
     */
    public static function reopenedPassengerSql(string $point, string $lineId, string $eraId): string
    {
        $near = self::CLOSURE_NEAR_M;
        $date = self::eraDateSql($eraId);

        return "EXISTS (SELECT 1 FROM line_events ro WHERE ro.line_id = {$lineId} AND ro.type = 'reopening_passenger'
                   AND ro.confidence IN ('high', 'medium') AND ro.date_precision IN ('day', 'month')
                   AND (ro.source IS NOT NULL OR ro.source_url IS NOT NULL)
                   AND ro.event_date <= {$date}
                   AND ro.section_geom IS NOT NULL AND ro.section_geom && ST_Expand({$point}, 0.002)
                   AND ST_DWithin(ro.section_geom::geography, ({$point})::geography, {$near})
                   AND NOT EXISTS (SELECT 1 FROM line_events rc WHERE rc.line_id = ro.line_id
                                   AND (rc.type = 'closure_passenger' OR (rc.type = 'closure' AND rc.confidence = 'high'))
                                   AND rc.event_date > ro.event_date AND rc.event_date <= {$date}
                                   AND (rc.section_geom IS NULL OR ST_DWithin(rc.section_geom::geography, ({$point})::geography, {$near}))))";
    }

    /**
     * Ère passée : le point est-il fermé aux voyageurs à la date de l'ère, d'après une fermeture
     * (closure_passenger, ou closure de confiance haute) localisée antérieure à l'ère et une
     * réouverture qui le couvre POSTÉRIEURE à l'ère (aucune entre les deux) ? Les drapeaux de
     * liste-des-gares sont d'aujourd'hui : la rive droite du Rhône (800000), rouverte en 2022,
     * n'avait pas de voyageurs en 2020. Toujours faux pour l'ère courante.
     */
    public static function reopenedAfterEraSql(string $point, string $lineFilter, string $eraId): string
    {
        $near = self::CLOSURE_NEAR_M;
        $date = self::eraDateSql($eraId);
        $filter = str_replace(['cl.', 'ce.'], ['yl.', 'yc.'], $lineFilter);
        $covers = fn (string $e) => "({$e}.section_geom IS NULL OR ({$e}.section_geom && ST_Expand({$point}, 0.002)
                    AND ST_DWithin({$e}.section_geom::geography, ({$point})::geography, {$near})))";

        return "EXISTS (SELECT 1 FROM line_events yc JOIN lines yl ON yl.id = yc.line_id
                 WHERE {$filter}
                   AND (yc.type = 'closure_passenger' OR (yc.type = 'closure' AND yc.confidence = 'high'))
                   AND yc.event_date <= {$date} AND yc.section_geom IS NOT NULL
                   AND yc.section_geom && ST_Expand({$point}, 0.002)
                   AND ST_DWithin(yc.section_geom::geography, ({$point})::geography, {$near})
                   AND NOT EXISTS (SELECT 1 FROM line_events yb WHERE yb.line_id = yc.line_id
                                   AND yb.type IN ('reopening', 'reopening_passenger', 'opening', 'opening_passenger')
                                   AND yb.event_date > yc.event_date AND yb.event_date <= {$date} AND ".$covers('yb').")
                   AND EXISTS (SELECT 1 FROM line_events ya WHERE ya.line_id = yc.line_id
                               AND ya.type IN ('reopening', 'reopening_passenger')
                               AND ya.event_date > {$date} AND ya.event_date <= CURRENT_DATE
                               AND ya.section_geom IS NOT NULL AND ".$covers('ya').'))';
    }

    /**
     * Lignes de l'ère qui ont une réouverture aux voyageurs candidate (filtre grossier avant reopenedPassengerSql).
     *
     * @return list<int>
     */
    public function reopenedLines(int $eraId): array
    {
        return array_map(fn ($r) => (int) $r->line_id, DB::select("SELECT DISTINCT ro.line_id FROM line_events ro
            WHERE ro.type = 'reopening_passenger' AND ro.confidence IN ('high', 'medium') AND ro.date_precision IN ('day', 'month')
              AND ro.section_geom IS NOT NULL AND (ro.source IS NOT NULL OR ro.source_url IS NOT NULL) AND ro.event_date <= ".self::eraDateSql((string) $eraId)));
    }

    // ─── Gares ───────────────────────────────────────────────────────────

    /** Charge les gares de liste-des-gares (m, desserte, raison si non desservie). */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $p = self::stationPoint('g');
        $rows = DB::select('SELECT g.id, g.code_ligne, g.libelle, g.voyageurs, g.fret, g.pk_m, g.lon, g.lat,
                   CASE WHEN g.lat IS NOT NULL THEN '.self::mSql($p, 'g.code_ligne').' END AS m,
                   CASE WHEN g.voyageurs AND g.lat IS NOT NULL THEN NOT '.self::exploitedSql($p, 'g.code_ligne').' ELSE false END AS off_exploited,
                   CASE WHEN g.voyageurs AND g.lat IS NOT NULL THEN '.self::recentlyClosedSql($p, 'cl.code = g.code_ligne').' ELSE false END AS closed,
                   CASE WHEN g.voyageurs AND g.lat IS NOT NULL THEN '.self::reopenedElsewhereSql($p, 'cl.code = g.code_ligne').' ELSE false END AS elsewhere
            FROM sncf_gares g');

        foreach ($rows as $r) {
            $stale = $r->voyageurs && ($r->off_exploited || $r->closed || $r->elsewhere);
            $station = [
                'id' => (int) $r->id,
                'name' => (string) $r->libelle,
                'voyageurs' => (bool) $r->voyageurs,
                'fret' => (bool) $r->fret,
                'served' => $r->voyageurs && ! $stale,
                'stale' => $stale ? match (true) {
                    (bool) $r->off_exploited => 'tronçon SNCF non exploité',
                    (bool) $r->closed => 'fermée aux voyageurs depuis '.substr(self::RECENT_CLOSURE_FROM, 0, 4),
                    default => 'portion fermée aux voyageurs, rouverte ailleurs seulement',
                } : null,
                // Sans forme SNCF à moins de SNAP_M : PK officiel, moins précis mais dans le même ordre.
                'm' => $r->m !== null ? (float) $r->m : ($r->pk_m !== null ? (float) $r->pk_m : null),
                'lon' => $r->lon !== null ? (float) $r->lon : null,
                'lat' => $r->lat !== null ? (float) $r->lat : null,
            ];
            $this->stations[(string) $r->code_ligne][] = $station;
            if ($station['served'] && $station['lat'] !== null) {
                $this->served[] = ['lon' => $station['lon'], 'lat' => $station['lat']];
            }
        }
        $this->loaded = true;
    }

    /**
     * SQL des gares de voyageurs SNCF (sncf_voyageurs) posées sur le tracé en service de l'ère
     * d'une ligne d'un autre code que celui de liste-des-gares : à moins de ADOPT_M de la ligne,
     * plus proche d'elle que de toute autre ligne en service d'au moins ADOPT_MARGIN_M, et sans
     * gare voyageurs de liste-des-gares d'une ligne voisine (ou de la même) à moins de
     * ADOPT_LISTED_M. Colonnes : station_id, name, code, lon, lat, d. Paramètre lié : l'ère.
     */
    public static function adoptableSql(): string
    {
        $adopt = self::ADOPT_M;
        $margin = self::ADOPT_MARGIN_M;
        $reach = self::ADOPT_M + self::ADOPT_MARGIN_M;
        $listed = self::ADOPT_LISTED_M;
        $g = self::stationPoint('g');

        return "WITH v AS (
                SELECT DISTINCT s.id, s.name, s.geom FROM station_sources ss JOIN stations s ON s.id = ss.station_id
                WHERE ss.source = 'sncf_voyageurs' AND ss.passenger AND ss.lifecycle = 'active'
            ), near AS (
                SELECT v.id, l.code, MIN(ST_Distance(ls.geom::geography, v.geom::geography)) AS d
                FROM v JOIN line_segments ls ON ls.era_id = ? AND ls.status IN ('in_service', 'single_track')
                       AND ls.geom && ST_Expand(v.geom, 0.003) AND ST_DWithin(ls.geom::geography, v.geom::geography, {$reach})
                JOIN lines l ON l.id = ls.line_id
                GROUP BY v.id, l.code
            )
            SELECT v.id AS station_id, v.name, n.code, ST_X(v.geom) AS lon, ST_Y(v.geom) AS lat, n.d
            FROM near n JOIN v ON v.id = n.id
            WHERE n.d <= {$adopt} AND n.code IS NOT NULL AND n.code <> ''
              AND NOT EXISTS (SELECT 1 FROM near o WHERE o.id = n.id AND o.code IS DISTINCT FROM n.code AND o.d < n.d + {$margin})
              AND NOT EXISTS (SELECT 1 FROM sncf_gares g WHERE g.voyageurs AND g.lat IS NOT NULL
                              AND g.lat BETWEEN ST_Y(v.geom) - 0.004 AND ST_Y(v.geom) + 0.004
                              AND g.lon BETWEEN ST_X(v.geom) - 0.006 AND ST_X(v.geom) + 0.006
                              AND g.code_ligne IN (SELECT o.code FROM near o WHERE o.id = n.id)
                              AND ST_DWithin(({$g})::geography, v.geom::geography, {$listed}))";
    }

    /**
     * Ajoute aux gares de chaque code les gares de voyageurs SNCF posées sur son tracé en service
     * de l'ère (adoptableSql), avec les mêmes contrôles de desserte que liste-des-gares : forme
     * SNCF « Exploitée », pas de fermeture aux voyageurs récente, pas de réouverture ailleurs
     * seulement. Une gare adoptée non desservie borne la zone comme une gare de liste-des-gares.
     */
    public function adopt(int $eraId): int
    {
        $this->load();
        if ($this->adoptedEra === $eraId) {
            return $this->adoptedCount();
        }
        foreach ($this->stations as $code => $list) {
            $kept = [];
            foreach ($list as $s) {
                if (empty($s['adopted'])) {
                    if (isset($s['served0'])) {
                        [$s['served'], $s['stale']] = [$s['served0'], $s['stale0']];
                        unset($s['served0'], $s['stale0']);
                    }
                    $kept[] = $s;
                }
            }
            $this->stations[$code] = $kept;
        }
        $this->passengerCodes = null;

        // Ère passée : gares de liste-des-gares rouvertes aux voyageurs APRÈS l'ère (non desservies alors).
        $notYet = array_flip(array_map(fn ($r) => (int) $r->id, DB::select('SELECT g.id FROM sncf_gares g
            WHERE g.voyageurs AND g.lat IS NOT NULL AND '.self::reopenedAfterEraSql(self::stationPoint('g'), 'cl.code = g.code_ligne', (string) $eraId))));
        foreach ($this->stations as $code => $list) {
            foreach ($list as $i => $s) {
                if ($s['served'] && isset($notYet[$s['id']])) {
                    $this->stations[$code][$i] = ['served' => false, 'stale' => 'rouverte aux voyageurs après l\'ère',
                        'served0' => $s['served'], 'stale0' => $s['stale']] + $s;
                }
            }
        }

        $p = 'ST_SetSRID(ST_MakePoint(a.lon, a.lat), 4326)';
        $rows = DB::select('WITH a AS ('.self::adoptableSql().')
            SELECT a.*, '.self::mSql($p, 'a.code').' AS m,
                   NOT '.self::exploitedSql($p, 'a.code', self::PIECE_EXPLOITED_M).' AS off_exploited,
                   '.self::recentlyClosedSql($p, 'cl.code = a.code').' AS closed,
                   '.self::reopenedElsewhereSql($p, 'cl.code = a.code').' AS elsewhere,
                   '.self::reopenedAfterEraSql($p, 'cl.code = a.code', (string) $eraId).' AS not_yet
            FROM a', [$eraId]);
        foreach ($rows as $r) {
            $stale = match (true) {
                (bool) $r->off_exploited => 'tronçon SNCF non exploité',
                (bool) $r->closed => 'fermée aux voyageurs depuis '.substr(self::RECENT_CLOSURE_FROM, 0, 4),
                (bool) $r->elsewhere => 'portion fermée aux voyageurs, rouverte ailleurs seulement',
                (bool) $r->not_yet => 'rouverte aux voyageurs après l\'ère',
                default => null,
            };
            if ($r->m === null) {
                continue; // pas de repère sur la forme SNCF du code
            }
            $this->stations[(string) $r->code][] = [
                'id' => -(int) $r->station_id, // négatif : jamais confondu avec un id de sncf_gares
                'name' => (string) $r->name,
                'voyageurs' => true,
                'fret' => false,
                'served' => $stale === null,
                'stale' => $stale,
                'adopted' => true,
                'm' => (float) $r->m,
                'lon' => (float) $r->lon,
                'lat' => (float) $r->lat,
            ];
        }
        $this->adoptedEra = $eraId;

        return $this->adoptedCount();
    }

    /** Nombre de gares adoptées (desservies) pour l'ère chargée. */
    public function adoptedCount(): int
    {
        $n = 0;
        foreach ($this->stations as $list) {
            foreach ($list as $s) {
                if (! empty($s['adopted']) && $s['served']) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** @return list<string> gares adoptées desservies du code (pour line_sources) */
    public function adoptedStations(string $code): array
    {
        $out = [];
        foreach ($this->stations[$code] ?? [] as $s) {
            if (! empty($s['adopted']) && $s['served']) {
                $out[] = $s['name'];
            }
        }

        return $out;
    }

    /** Le code a-t-il au moins une gare voyageurs desservie ? */
    public function hasServedStation(string $code): bool
    {
        foreach ($this->stations[$code] ?? [] as $s) {
            if ($s['served']) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> gares voyageurs du code écartées (« nom (raison) ») */
    public function staleStations(string $code): array
    {
        $out = [];
        foreach ($this->stations[$code] ?? [] as $s) {
            if ($s['stale'] !== null) {
                $out[] = "{$s['name']} ({$s['stale']})";
            }
        }

        return array_values(array_unique($out));
    }

    public function hasFreightStation(string $code): bool
    {
        foreach ($this->stations[$code] ?? [] as $s) {
            if ($s['fret']) {
                return true;
            }
        }

        return false;
    }

    // ─── Tronçons ────────────────────────────────────────────────────────

    /**
     * Tronçons actifs des lignes, avec le m de leurs extrémités et de leur milieu.
     *
     * @param  list<int>  $lineIds
     * @return array<int, list<array<string, mixed>>> line_id => tronçons
     */
    public function pieces(int $eraId, array $lineIds): array
    {
        if (! $lineIds) {
            return [];
        }
        $start = 'ST_StartPoint(ls.geom)';
        $endP = 'ST_EndPoint(ls.geom)';
        $mid = 'ST_LineInterpolatePoint(ls.geom, 0.5)';
        $rows = DB::select("SELECT ls.id, ls.line_id, ls.traffic_type, ST_Length(ls.geom::geography) AS len,
                   ST_X({$start}) AS x1, ST_Y({$start}) AS y1, ST_X({$endP}) AS x2, ST_Y({$endP}) AS y2,
                   ".self::mSql($start, 'l.code').' AS m1, '.self::mSql($endP, 'l.code').' AS m2, '.self::mSql($mid, 'l.code').' AS mm,
                   '.self::exploitedSql($mid, 'l.code', self::PIECE_EXPLOITED_M).' AS exploited,
                   ('.self::recentlyClosedSql($mid, 'ce.line_id = ls.line_id').' OR '.self::reopenedAfterEraSql($mid, 'ce.line_id = ls.line_id', 'ls.era_id').') AS closed,
                   '.self::passengerClosedSql($mid).' AS pax_closed,
                   '.self::reopenedPassengerSql($mid, 'ls.line_id', 'ls.era_id')." AS reopened
            FROM line_segments ls JOIN lines l ON l.id = ls.line_id
            WHERE ls.era_id = ? AND ls.status IN ('in_service', 'single_track') AND ls.line_id = ANY(?::bigint[])
            ORDER BY ls.line_id, ls.id",
            [$eraId, '{'.implode(',', array_map('intval', $lineIds)).'}']);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id][] = [
                'id' => (int) $r->id,
                'traffic' => (string) $r->traffic_type,
                'len' => (float) $r->len,
                'x1' => (float) $r->x1, 'y1' => (float) $r->y1, 'x2' => (float) $r->x2, 'y2' => (float) $r->y2,
                'm1' => $r->m1 !== null ? (float) $r->m1 : null,
                'm2' => $r->m2 !== null ? (float) $r->m2 : null,
                'mm' => $r->mm !== null ? (float) $r->mm : null,
                'exploited' => (bool) $r->exploited,
                'closed' => (bool) $r->closed,
                'pax_closed' => (bool) $r->pax_closed,
                'reopened' => (bool) $r->reopened,
            ];
        }

        return $out;
    }

    // ─── Zone voyageurs ──────────────────────────────────────────────────

    /**
     * Zone voyageurs d'un code sur les tronçons d'une ligne. null : pas de repère
     * (aucun tronçon ni gare desservie projetables), la ligne se classe en entier.
     *
     * @param  list<array<string, mixed>>  $pieces
     * @return array{runs: list<array{lo: float, hi: float, from: ?array, to: ?array}>, isolated: list<array>}|null
     */
    public function plan(string $code, array $pieces, ?int $eraId = null, ?int $lineId = null): ?array
    {
        $stations = $this->stations[$code] ?? [];
        $served = array_values(array_filter($stations, fn ($s) => $s['served'] && $s['m'] !== null));
        $projected = array_values(array_filter($pieces, fn ($p) => $p['m1'] !== null && $p['m2'] !== null));
        if (! $this->hasServedStation($code)) {
            // Pas de gare desservie : seuls les tronçons rouverts aux voyageurs sont dans la zone (side()).
            return ['runs' => [], 'isolated' => []];
        }
        if (! $served || ! $projected) {
            return null;
        }
        usort($served, fn ($a, $b) => $a['m'] <=> $b['m']);
        $unserved = array_values(array_filter($stations, fn ($s) => $s['voyageurs'] && ! $s['served'] && $s['m'] !== null));

        // Bouts de la ligne (m extrêmes des tronçons projetés) et leurs points.
        $low = $high = null;
        foreach ($projected as $p) {
            foreach ([[$p['m1'], $p['x1'], $p['y1']], [$p['m2'], $p['x2'], $p['y2']]] as [$m, $x, $y]) {
                if ($low === null || $m < $low['m']) {
                    $low = ['m' => $m, 'lon' => $x, 'lat' => $y];
                }
                if ($high === null || $m > $high['m']) {
                    $high = ['m' => $m, 'lon' => $x, 'lat' => $y];
                }
            }
        }
        // Points testés pour le branchement : le bout projeté et les bouts des tronçons non projetés proches.
        foreach (['low', 'high'] as $k) {
            $$k['area'] = [['lon' => $$k['lon'], 'lat' => $$k['lat']]];
            foreach ($pieces as $p) {
                if ($p['m1'] !== null && $p['m2'] !== null) {
                    continue;
                }
                $ends = [['lon' => $p['x1'], 'lat' => $p['y1']], ['lon' => $p['x2'], 'lat' => $p['y2']]];
                if (min(self::metres($$k, $ends[0]), self::metres($$k, $ends[1])) <= self::END_AREA_M) {
                    array_push($$k['area'], ...$ends);
                }
            }
        }

        // Intervalles entre gares desservies consécutives, sauf gare non desservie entre elles.
        $runs = [];
        $inRun = [];
        for ($i = 0; $i < count($served) - 1; $i++) {
            [$a, $b] = [$served[$i], $served[$i + 1]];
            $blocked = false;
            foreach ($unserved as $u) {
                if ($u['m'] > $a['m'] + 1 && $u['m'] < $b['m'] - 1) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }
            $inRun[$i] = $inRun[$i + 1] = true;
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['to']['id'] === $a['id']) {
                $runs[$last]['hi'] = $b['m'];
                $runs[$last]['to'] = $b;
            } else {
                $runs[] = ['lo' => $a['m'], 'hi' => $b['m'], 'from' => $a, 'to' => $b];
            }
        }
        // Gares isolées (aucune voisine desservie) : candidates au prolongement vers un bout,
        // y compris une gare terminus au bout de la ligne (Lannion, Fécamp) ou une gare de
        // bifurcation (Is-sur-Tille) ; Rognac (Rognac - Aix fermée aux voyageurs en 1939) et
        // Pertuis (Cheval-Blanc - Pertuis, 1971) ne se prolongent pas, l'écart étant fermé.
        $isolated = [];
        foreach ($served as $i => $s) {
            if (! isset($inRun[$i])) {
                $isolated[] = $s;
            }
        }
        $candidates = $runs;
        foreach ($isolated as $s) {
            $candidates[] = ['lo' => $s['m'], 'hi' => $s['m'], 'from' => $s, 'to' => $s, 'isolated' => true];
        }
        if (! $candidates) {
            return ['runs' => [], 'isolated' => []];
        }

        // Prolongement jusqu'aux bouts de ligne branchés sur le réseau voyageurs.
        usort($candidates, fn ($a, $b) => $a['lo'] <=> $b['lo']);
        $first = 0;
        $lastIdx = count($candidates) - 1;
        // Zone prolongée : ouverte jusqu'au bout (un tronçon projeté un peu au-delà du bout reste dedans).
        // Gare terminus en cul-de-sac desservie (Fécamp, Lannion) : les trains viennent forcément
        // de l'autre bout, une fermeture ancienne de l'écart (rouverte sans portion connue) ne bloque pas.
        $terminusLow = $candidates[$first]['lo'] - $low['m'] <= self::TERMINUS_M && $this->deadEnd($low, $eraId, $lineId);
        $terminusHigh = $high['m'] - $candidates[$lastIdx]['hi'] <= self::TERMINUS_M && $this->deadEnd($high, $eraId, $lineId);
        if ($this->extends($low, $candidates[$first]['lo'], $unserved, $projected, $eraId, $lineId, $terminusHigh && $first === $lastIdx)) {
            $candidates[$first]['lo'] = -INF;
            $candidates[$first]['from'] = null;
            unset($candidates[$first]['isolated']);
        }
        if ($this->extends($high, $candidates[$lastIdx]['hi'], $unserved, $projected, $eraId, $lineId, $terminusLow && $first === $lastIdx)) {
            $candidates[$lastIdx]['hi'] = INF;
            $candidates[$lastIdx]['to'] = null;
            unset($candidates[$lastIdx]['isolated']);
        }

        $out = ['runs' => [], 'isolated' => []];
        foreach ($candidates as $c) {
            if (! empty($c['isolated'])) {
                $out['isolated'][] = $c['from'];
            } else {
                $out['runs'][] = ['lo' => $c['lo'], 'hi' => $c['hi'], 'from' => $c['from'], 'to' => $c['to']];
            }
        }

        return $out;
    }

    /**
     * Prolonger la zone de $edge (m de la gare desservie la plus proche du bout) jusqu'au bout $end ?
     */
    private function extends(array $end, float $edge, array $unserved, array $projected, ?int $eraId, ?int $lineId, bool $fromTerminus = false): bool
    {
        [$lo, $hi] = [min($end['m'], $edge), max($end['m'], $edge)];
        if ($hi - $lo <= self::TERMINUS_M) {
            return true; // voies de la gare terminus, ou la zone touche déjà le bout
        }
        foreach ($unserved as $u) {
            if ($u['m'] > $lo + 1 && $u['m'] < $hi - 1) {
                return false; // gare voyageurs non desservie dans l'écart
            }
        }
        $total = $closed = 0.0;
        foreach ($projected as $p) {
            $mid = $p['mm'] ?? ($p['m1'] + $p['m2']) / 2;
            if ($mid > $lo && $mid < $hi) {
                $total += $p['len'];
                if (! empty($p['pax_closed'])) {
                    $closed += $p['len'];
                }
            }
        }
        if (! $fromTerminus && $total > 0 && $closed > self::EXTEND_MAX_CLOSED * $total) {
            return false; // écart fermé aux voyageurs, jamais rouvert
        }
        foreach ($end['area'] as $point) {
            if ($this->servedNear($point) || $this->junctionToPassengerLine($point, $eraId, $lineId)) {
                return true;
            }
        }
        foreach ($end['area'] as $point) {
            if ($this->abroad($point)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Point hors des communes françaises, à moins de ABROAD_NEAR_FRANCE_DEG d'une commune
     * chargée : à plus de 300 m de toute commune (dans le pays voisin : Vintimille), ou avec des
     * rails OSM hors de France à moins de 3 km (Zoufftgen, Lauterbourg). Un bout de quai gagné sur la mer est à moins de 300 m de sa commune et
     * n'a pas de rails au-delà.
     */
    public function abroad(array $point): bool
    {
        return (bool) DB::scalar("WITH p AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS pt)
            SELECT EXISTS (SELECT 1 FROM commune_contours c, p WHERE c.geom && ST_Expand(p.pt, ?)) -- près de la France (communes chargées)
               AND NOT EXISTS (SELECT 1 FROM commune_contours c, p WHERE c.geom && p.pt AND ST_Contains(c.geom, p.pt))
               AND (NOT EXISTS (SELECT 1 FROM commune_contours c, p WHERE c.geom && ST_Expand(p.pt, 0.005)
                                AND ST_DWithin(c.geom::geography, p.pt::geography, 300))
                    OR EXISTS (SELECT 1 FROM present_osm_ways w, p WHERE w.kind = 'railway' AND w.geom && ST_Expand(p.pt, 0.04)
                           AND ST_DWithin(w.geom::geography, p.pt::geography, 3000)
                           AND NOT EXISTS (SELECT 1 FROM commune_contours c WHERE c.geom && ST_PointOnSurface(w.geom)
                                           AND ST_Contains(c.geom, ST_PointOnSurface(w.geom)))))
            FROM p", [$point['lon'], $point['lat'], self::ABROAD_NEAR_FRANCE_DEG]);
    }

    /**
     * Bout de ligne en cul-de-sac pour les voyageurs : aucune autre ligne voyageurs à moins de
     * JUNCTION_M (une voie de service ou une ligne fermée peut s'y brancher : Dieppe - Fécamp),
     * pas à l'étranger.
     */
    private function deadEnd(array $point, ?int $eraId, ?int $lineId): bool
    {
        return $eraId !== null && ! $this->junctionToPassengerLine($point, $eraId, $lineId) && ! $this->abroad($point);
    }

    /** Bout de ligne à moins de JUNCTION_M d'un tronçon d'une autre ligne voyageurs (gares desservies, ou LGV) ? */
    private function junctionToPassengerLine(array $point, ?int $eraId, ?int $lineId): bool
    {
        if ($eraId === null) {
            return false;
        }
        $this->passengerCodes ??= '{'.implode(',', array_map(fn ($c) => '"'.str_replace('"', '', $c).'"',
            array_keys(array_filter($this->stations, fn ($st) => (bool) array_filter($st, fn ($s) => $s['served']))))).'}';

        // Tout statut : c'est le code voyageurs de la ligne voisine qui fait la preuve.
        return (bool) DB::scalar('SELECT EXISTS (SELECT 1 FROM line_segments ls JOIN lines l ON l.id = ls.line_id
                WHERE ls.era_id = ? AND ls.line_id <> ?
                  AND ls.geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.001)
                  AND ST_DWithin(ls.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)
                  AND (l.code = ANY(?::text[]) OR l.is_lgv))',
            [$eraId, $lineId ?? 0, $point['lon'], $point['lat'], $point['lon'], $point['lat'], self::JUNCTION_M, $this->passengerCodes]);
    }

    /** Une gare voyageurs desservie (tous codes) à moins de EXTREMITY_STATION_M du point ? */
    private function servedNear(array $point): bool
    {
        foreach ($this->served as $s) {
            if (abs($s['lat'] - $point['lat']) < 0.02 && self::metres($s, $point) <= self::EXTREMITY_STATION_M) {
                return true;
            }
        }

        return false;
    }

    /** Distance approchée (m) entre deux points lon/lat proches. */
    private static function metres(array $a, array $b): float
    {
        $dx = ($a['lon'] - $b['lon']) * 111_320 * cos(deg2rad($a['lat']));
        $dy = ($a['lat'] - $b['lat']) * 110_540;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Coupes à faire : tronçon => gares bornes de zone qui tombent à son intérieur (en m).
     *
     * @return array<int, list<array>> segment_id => gares
     */
    public function cuts(array $plan, array $pieces): array
    {
        $bounds = [];
        foreach ($plan['runs'] as $r) {
            foreach (['from', 'to'] as $k) {
                if ($r[$k] !== null) {
                    $bounds[$r[$k]['id']] = $r[$k];
                }
            }
        }
        $cuts = [];
        foreach ($pieces as $p) {
            if ($p['m1'] === null || $p['m2'] === null) {
                continue;
            }
            [$a, $b] = [min($p['m1'], $p['m2']), max($p['m1'], $p['m2'])];
            foreach ($bounds as $s) {
                if ($s['lat'] !== null && $s['m'] > $a + self::MIN_PIECE_M && $s['m'] < $b - self::MIN_PIECE_M) {
                    $cuts[$p['id']][] = $s;
                }
            }
        }

        return $cuts;
    }

    /**
     * Coupe les tronçons aux gares (morceaux >= MIN_PIECE_M, gare à moins de SNAP_M du tracé).
     * Le tronçon d'origine garde son id pour le premier morceau ; les autres sont insérés
     * avec les mêmes attributs.
     *
     * @param  array<int, list<array>>  $cuts
     * @return int nombre de coupes faites
     */
    public function split(array $cuts): int
    {
        if (! $cuts) {
            return 0;
        }
        $keep = array_values(array_diff(Schema::getColumnListing('line_segments'),
            ['id', 'geom', 'length_meters', 'pk_start_m', 'pk_end_m', 'created_at', 'updated_at']));
        $cols = implode(', ', $keep);
        $done = 0;
        foreach ($cuts as $segmentId => $stations) {
            $fractions = [];
            foreach ($stations as $s) {
                $r = DB::selectOne('SELECT ST_LineLocatePoint(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)) AS f,
                        ST_Length(geom::geography) AS len,
                        ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?) AS near
                    FROM line_segments WHERE id = ?', [$s['lon'], $s['lat'], $s['lon'], $s['lat'], self::SNAP_M, $segmentId]);
                if ($r && $r->near && $r->f * $r->len >= self::MIN_PIECE_M && (1 - $r->f) * $r->len >= self::MIN_PIECE_M) {
                    $fractions[] = (float) $r->f;
                }
            }
            sort($fractions);
            // Coupes trop proches l'une de l'autre : une seule.
            $bounds = [0.0];
            $len = (float) DB::scalar('SELECT ST_Length(geom::geography) FROM line_segments WHERE id = ?', [$segmentId]);
            foreach ($fractions as $f) {
                if (($f - end($bounds)) * $len >= self::MIN_PIECE_M && (1 - $f) * $len >= self::MIN_PIECE_M) {
                    $bounds[] = $f;
                }
            }
            if (count($bounds) === 1) {
                continue;
            }
            $bounds[] = 1.0;
            for ($k = 1; $k < count($bounds) - 1; $k++) {
                [$a, $b] = [$bounds[$k], $bounds[$k + 1]];
                DB::insert("INSERT INTO line_segments ({$cols}, geom, length_meters, pk_start_m, pk_end_m, created_at, updated_at)
                    SELECT {$cols}, ST_LineSubstring(geom, ?::float8, ?::float8), ROUND(ST_Length(ST_LineSubstring(geom, ?::float8, ?::float8)::geography)),
                           CASE WHEN pk_start_m IS NOT NULL AND pk_end_m IS NOT NULL THEN ROUND(pk_start_m + ?::float8 * (pk_end_m - pk_start_m)) END,
                           CASE WHEN pk_start_m IS NOT NULL AND pk_end_m IS NOT NULL THEN ROUND(pk_start_m + ?::float8 * (pk_end_m - pk_start_m)) END,
                           NOW(), NOW()
                    FROM line_segments WHERE id = ?", [$a, $b, $a, $b, $a, $b, $segmentId]);
                $done++;
            }
            DB::update('UPDATE line_segments SET geom = ST_LineSubstring(geom, 0, ?::float8), length_meters = ROUND(ST_Length(ST_LineSubstring(geom, 0, ?::float8)::geography)),
                    pk_end_m = CASE WHEN pk_start_m IS NOT NULL AND pk_end_m IS NOT NULL THEN ROUND(pk_start_m + ?::float8 * (pk_end_m - pk_start_m)) END,
                    updated_at = NOW()
                WHERE id = ?', [$bounds[1], $bounds[1], $bounds[1], $segmentId]);
        }

        return $done;
    }

    /**
     * Tronçon dans la zone voyageurs ? 'in' | 'out' | 'keep' (forme SNCF non exploitée : trafic
     * du moteur) | null (non projeté : voir sides()).
     */
    public function side(array $plan, array $piece): ?string
    {
        if ($piece['m1'] === null || $piece['m2'] === null) {
            return ! empty($piece['reopened']) && $piece['exploited'] && ! $piece['closed'] ? 'in' : null;
        }
        if (! $piece['exploited']) {
            return 'keep';
        }
        if ($piece['closed']) {
            return 'out';
        }
        if (! empty($piece['reopened'])) {
            return 'in'; // réouverture aux voyageurs datée, sans fermeture après
        }
        [$a, $b] = [min($piece['m1'], $piece['m2']), max($piece['m1'], $piece['m2'])];
        $mid = $piece['mm'] ?? ($a + $b) / 2;
        foreach ($plan['runs'] as $r) {
            if ($mid >= $r['lo'] - 1 && $mid <= $r['hi'] + 1) {
                return 'in';
            }
        }
        foreach ($plan['isolated'] as $s) {
            if ($s['m'] > $a + self::MIN_PIECE_M && $s['m'] < $b - self::MIN_PIECE_M) {
                return 'in';
            }
        }

        return 'out';
    }

    /**
     * Côté de chaque tronçon ; un tronçon non projeté prend celui du tronçon projeté le plus proche.
     *
     * @return array<int, string> segment_id => 'in'|'out'|'keep'
     */
    public function sides(array $plan, array $pieces): array
    {
        $sides = [];
        $known = [];
        foreach ($pieces as $p) {
            $s = $this->side($plan, $p);
            if ($s !== null) {
                $sides[$p['id']] = $s;
                $known[] = $p;
            }
        }
        foreach ($pieces as $p) {
            if (isset($sides[$p['id']])) {
                continue;
            }
            $best = null;
            $bestD = INF;
            foreach ($known as $k) {
                foreach ([[$p['x1'], $p['y1']], [$p['x2'], $p['y2']]] as [$x, $y]) {
                    foreach ([[$k['x1'], $k['y1']], [$k['x2'], $k['y2']]] as [$kx, $ky]) {
                        $d = (($x - $kx) * cos(deg2rad($y))) ** 2 + ($y - $ky) ** 2;
                        if ($d < $bestD) {
                            $bestD = $d;
                            $best = $k['id'];
                        }
                    }
                }
            }
            // Aucun tronçon projeté : ligne entière dans la zone si elle a des gares desservies (plan
            // non vide), sinon hors zone (zone limitée aux tronçons rouverts, déjà classés).
            $sides[$p['id']] = $best !== null ? $sides[$best] : ($plan['runs'] || $plan['isolated'] ? 'in' : 'out');
        }

        return $sides;
    }
}
