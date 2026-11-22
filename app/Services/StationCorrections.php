<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Corrections SOURCÉES des gares (database/data/gares_corrections.php), réappliquées
 * par vff:build-station-eras à chaque passage (avant la nature des points et les dates
 * déduites) : les imports (Wikidata, OSM, Wikipédia) réécrivent leurs données, la
 * correction survit aux reconstructions.
 *
 * Une correction ne remplace pas une règle : elle sert quand les sources structurées se
 * contredisent ou se trompent (jeu SNCF périmé, date Wikidata tirée d'un référentiel,
 * phrase mal lue) et qu'aucune règle générale ne permet de trancher. Toujours une source
 * vérifiable (permalien Wikipédia oldid, fiche Wikidata…).
 *
 * Gare visée (clé « station ») : « name » (obligatoire, contrôle) et une clé stable parmi
 * « uic » (stations.uic_code), « wikidata », « osm » (station_sources osm, ex. « w37889707 ») ;
 * « near » [lng, lat, rayon en km] départage deux fiches homonymes, ou désigne seule une gare
 * sans identifiant (halte lue dans un schéma Wikipédia : Charantonnay). Une correction dont la
 * gare est introuvable ou ambiguë n'est pas appliquée (signalée).
 *
 * Actions :
 *   - add        : événement (type, date AAAA-MM-JJ, precision day | month | year | before), source
 *                  « correction » (la plus sûre : StationTimeline::SOURCE_RANK) ;
 *   - neutralize : l'événement d'une source (match : source, type, date) n'est pas ce qu'il
 *                  dit (déclassement d'un tronçon lu comme fermeture de la gare, date d'un
 *                  référentiel) : retiré de la chronologie, à chaque passage ;
 *   - kind       : « excluded » (hors du réseau ferré : tramway, métro, funiculaire, fiche
 *                  erronée) ou « projected » (gare annoncée, jamais ouverte) ;
 *   - tourist    : train d'attraction ou touristique sans ligne VFF : statut « touristique » ;
 *   - current    : état du jour (ère courante seulement), « status » active (desservie aujourd'hui
 *                  par des trains de voyageurs publics : horaires publiés), tourist (seul un train
 *                  touristique s'y arrête : gare de départ d'un chemin de fer touristique), no_commercial = fret seul (fermée aux voyageurs, encore desservie par le fret : embranchement, installation terminale) ou declassified (plus
 *                  desservie aujourd'hui) ; il remplace le statut des sources (drapeau fret seul de
 *                  la liste SNCF sur une section RATP, gare dite touristique d'une ligne à service
 *                  public quotidien, halte dont l'arrêt est supprimé), le plafond par les lignes
 *                  (règle R2) s'applique ensuite ; « plus desservie » empêche aussi la réouverture
 *                  déduite d'une réouverture de la ligne (vff:build-station-eras, étape f) ;
 *   - position   : lng, lat (WGS 84) d'une meilleure source ;
 *   - pk         : PK de la gare sur une ligne (« line » : code SNCF, « pk_m » : mètres), d'après
 *                  un schéma ou un article (PK interpolé faux : Troyes sur Troyes - Brienne) ;
 *                  réécrit station_lines.pk_m (pk_source « correction ») avant les dates déduites
 *                  et les arrêts (line_stops), à chaque passage ;
 *   - unlink     : lien faux vers un élément Wikidata (« source » wikidata, « ref » Q-id) : l'objet porte
 *                  le Q-id d'une autre gare (tag wikidata d'un objet OHM ou OSM, élément Wikidata rattaché).
 *                  Lu par vff:consolidate-stations au rattachement : la gare ne prend pas ce Q-id et
 *                  l'élément Wikidata ne la rejoint pas ; les objets qui portent le tag restent rattachés
 *                  (seul le Q-id est ignoré). Gare visée par « uic » ou « osm » (lue sur les sources, avant
 *                  la consolidation), « name » en contrôle ; sans effet dans vff:build-station-eras.
 *
 * Champs communs : source_url, quote (citation courte), reason. Pas de tiret long.
 */
final class StationCorrections
{
    public const ACTIONS = ['add', 'neutralize', 'kind', 'tourist', 'current', 'position', 'pk', 'unlink'];

    /** Sources dont un lien peut être défait (action « unlink »). */
    public const UNLINK_SOURCES = ['wikidata'];

    /** États du jour admis par l'action « current ». */
    public const CURRENT_STATUSES = [StationTimeline::ACTIVE, StationTimeline::TOURIST, StationTimeline::FREIGHT, StationTimeline::CLOSED];

    public const EVENT_TYPES = ['opening', 'reopening', 'closure', 'closure_passenger', 'closure_freight', 'demolition'];

    /** @var list<array<string, mixed>> */
    private array $corrections;

    /** @param  list<array<string, mixed>>|null  $corrections  défaut : database/data/gares_corrections.php */
    public function __construct(?array $corrections = null)
    {
        $this->corrections = $corrections ?? (is_file($f = database_path('data/gares_corrections.php')) ? require $f : []);
    }

    /**
     * Erreurs de forme d'une correction (vide si valide).
     *
     * @param  array<string, mixed>  $c
     * @return list<string>
     */
    public static function validate(array $c): array
    {
        $errors = [];
        $action = $c['action'] ?? null;
        if (! in_array($action, self::ACTIONS, true)) {
            $errors[] = 'action inconnue';
        }
        $st = $c['station'] ?? [];
        if (! is_array($st) || ! isset($st['name']) || ! array_intersect_key($st, array_flip(['uic', 'wikidata', 'osm', 'near']))) {
            $errors[] = 'gare : name + uic, wikidata, osm ou near';
        }
        if (is_array($st) && isset($st['near']) && (! is_array($st['near']) || count($st['near']) !== 3
            || array_filter($st['near'], fn ($v) => ! is_numeric($v)) !== [] || $st['near'][2] <= 0 || $st['near'][2] > 5)) {
            $errors[] = 'gare : near [lng, lat, rayon en km, 5 au plus]';
        }
        if (! isset($c['source_url']) || ! str_starts_with((string) $c['source_url'], 'http')) {
            $errors[] = 'source_url manquante';
        }
        if (! isset($c['reason']) || trim((string) $c['reason']) === '') {
            $errors[] = 'reason manquante';
        }
        $date = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
        if ($action === 'add' && (! in_array($c['type'] ?? null, self::EVENT_TYPES, true) || ! $date($c['date'] ?? null)
            || ! in_array($c['precision'] ?? 'day', ['day', 'month', 'year', StationTimeline::BEFORE], true))) {
            $errors[] = 'add : type, date, precision';
        }
        if ($action === 'neutralize' && (! isset($c['match']['source'], $c['match']['type']) || ! $date($c['match']['date'] ?? null))) {
            $errors[] = 'neutralize : match source, type, date';
        }
        if ($action === 'kind' && ! in_array($c['kind'] ?? null, [StationTimeline::KIND_EXCLUDED, StationTimeline::KIND_PROJECTED], true)) {
            $errors[] = 'kind : excluded ou projected';
        }
        if ($action === 'current' && ! in_array($c['status'] ?? null, self::CURRENT_STATUSES, true)) {
            $errors[] = 'current : status active, tourist, no_commercial (fret seul) ou declassified';
        }
        if ($action === 'position' && (! is_numeric($c['lng'] ?? null) || ! is_numeric($c['lat'] ?? null))) {
            $errors[] = 'position : lng, lat';
        }
        if ($action === 'pk' && (preg_match('/^\d{6}$/', (string) ($c['line'] ?? '')) !== 1 || ! is_int($c['pk_m'] ?? null) || $c['pk_m'] < 0)) {
            $errors[] = 'pk : line (code SNCF), pk_m (entier, mètres)';
        }
        if ($action === 'unlink' && (! in_array($c['source'] ?? null, self::UNLINK_SOURCES, true)
            || preg_match('/^Q\d+$/', (string) ($c['ref'] ?? '')) !== 1 || ! is_array($st) || (! isset($st['uic']) && ! isset($st['osm'])))) {
            $errors[] = 'unlink : source wikidata, ref (Q-id), gare visée par uic ou osm';
        }
        foreach (['reason', 'quote'] as $k) {
            if (isset($c[$k]) && (str_contains((string) $c[$k], "\u{2014}") || str_contains((string) $c[$k], '--'))) {
                $errors[] = "{$k} : tiret long";
            }
        }

        return $errors;
    }

    /**
     * Fiche(s) visée(s) par une correction.
     *
     * @param  array<string, mixed>  $st
     * @return list<int>
     */
    public static function resolveStation(array $st): array
    {
        $q = DB::table('stations as s')->where('s.name', $st['name']);
        $q->where(function ($w) use ($st) {
            if (isset($st['uic'])) {
                $w->orWhere('s.uic_code', $st['uic']);
            }
            if (isset($st['wikidata'])) {
                $w->orWhere('s.wikidata_id', $st['wikidata'])
                    ->orWhereExists(fn ($x) => $x->from('station_sources as ss')->whereColumn('ss.station_id', 's.id')
                        ->where('ss.source', 'wikidata')->where('ss.source_ref', $st['wikidata']));
            }
            if (isset($st['osm'])) {
                $w->orWhereExists(fn ($x) => $x->from('station_sources as ss')->whereColumn('ss.station_id', 's.id')
                    ->where('ss.source', 'osm')->where('ss.source_ref', $st['osm']));
            }
        });
        if (isset($st['near'])) {
            [$lng, $lat, $km] = $st['near'];
            $q->whereRaw('ST_DWithin(s.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $km * 1000]);
        }

        return $q->pluck('s.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Liens défaits (action « unlink »), valides, pour vff:consolidate-stations.
     *
     * @return list<array{name: string, uic: ?string, osm: ?string, source: string, ref: string}>
     */
    public function unlinks(): array
    {
        $out = [];
        foreach ($this->corrections as $c) {
            if (($c['action'] ?? null) === 'unlink' && self::validate($c) === []) {
                $out[] = [
                    'name' => (string) $c['station']['name'],
                    'uic' => isset($c['station']['uic']) ? (string) $c['station']['uic'] : null,
                    'osm' => isset($c['station']['osm']) ? (string) $c['station']['osm'] : null,
                    'source' => (string) $c['source'],
                    'ref' => (string) $c['ref'],
                ];
            }
        }

        return $out;
    }

    /**
     * Gares déclarées touristiques (action « tourist ») : [id => true].
     *
     * @return array<int, true>
     */
    public function touristStations(): array
    {
        $out = [];
        foreach ($this->corrections as $c) {
            if (($c['action'] ?? null) === 'tourist' && self::validate($c) === [] && count($ids = self::resolveStation($c['station'])) === 1) {
                $out[$ids[0]] = true;
            }
        }

        return $out;
    }

    /**
     * État du jour imposé (action « current ») : [id => active | tourist | no_commercial | declassified].
     *
     * @return array<int, string>
     */
    public function currentStatuses(): array
    {
        $out = [];
        foreach ($this->corrections as $c) {
            if (($c['action'] ?? null) === 'current' && self::validate($c) === [] && count($ids = self::resolveStation($c['station'])) === 1) {
                $out[$ids[0]] = (string) $c['status'];
            }
        }

        return $out;
    }

    /**
     * Applique les corrections en base : événements (add, neutralize), positions et PK.
     * Renvoie les gares de nature imposée (kind), les gares touristiques (tourist) et l'état
     * du jour imposé (current), appliqués par vff:build-station-eras.
     *
     * @return array{kinds: array<int, string>, tourist: array<int, true>, current: array<int, string>, applied: int, skipped: list<string>}
     */
    public function apply(): array
    {
        $out = ['kinds' => [], 'tourist' => [], 'current' => [], 'applied' => 0, 'skipped' => []];
        DB::transaction(function () use (&$out) {
            DB::table('station_events')->where('source', StationTimeline::CORRECTION)->delete();
            foreach ($this->corrections as $i => $c) {
                $label = ($c['station']['name'] ?? '?').' ('.($c['action'] ?? '?').')';
                if (($errors = self::validate($c)) !== []) {
                    $out['skipped'][] = "#{$i} {$label} : ".implode(', ', $errors);

                    continue;
                }
                $ids = self::resolveStation($c['station']);
                if (count($ids) !== 1) {
                    $out['skipped'][] = "#{$i} {$label} : ".(count($ids) === 0 ? 'gare introuvable' : count($ids).' fiches');

                    continue;
                }
                $sid = $ids[0];
                $note = mb_strimwidth(trim(($c['reason'] ?? '').(isset($c['quote']) ? ' : « '.$c['quote'].' »' : '')), 0, 1000, '…');
                $done = match ($c['action']) {
                    'add' => DB::table('station_events')->insertOrIgnore([
                        'station_id' => $sid, 'type' => $c['type'], 'event_date' => $c['date'], 'date_precision' => $c['precision'] ?? 'day',
                        'source' => StationTimeline::CORRECTION, 'source_ref' => 'gares_corrections:'.substr(sha1(json_encode($c) ?: ''), 0, 12),
                        'source_url' => mb_substr((string) $c['source_url'], 0, 500), 'is_inferred' => false, 'note' => $note,
                        'created_at' => now(), 'updated_at' => now(),
                    ]) > 0,
                    'neutralize' => DB::table('station_events')->where('station_id', $sid)->where('is_inferred', false)
                        ->where('source', $c['match']['source'])->where('type', $c['match']['type'])->where('event_date', $c['match']['date'])->delete() >= 0,
                    'position' => DB::update(<<<'SQL'
                        UPDATE stations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), position_source = 'correction',
                               position_spread_m = 0, position_flagged = FALSE,
                               provenance = COALESCE(provenance, '{}'::jsonb) || jsonb_build_object('position', ?::text)
                        WHERE id = ? AND NOT (ST_X(geom) = ? AND ST_Y(geom) = ?)
                    SQL, [(float) $c['lng'], (float) $c['lat'], (string) $c['source_url'], $sid, (float) $c['lng'], (float) $c['lat']]) >= 0,
                    'kind' => (bool) ($out['kinds'][$sid] = $c['kind']),
                    'tourist' => $out['tourist'][$sid] = true,
                    'current' => (bool) ($out['current'][$sid] = (string) $c['status']),
                    // Appliquée au rattachement des sources (vff:consolidate-stations) : rien à écrire ici.
                    'unlink' => true,
                    'pk' => DB::update(<<<'SQL'
                        UPDATE station_lines SET pk_m = ?, pk_source = 'correction', updated_at = NOW()
                        WHERE station_id = ? AND line_id IN (SELECT id FROM lines WHERE code = ?)
                    SQL, [(int) $c['pk_m'], $sid, (string) $c['line']]) > 0,
                    default => false,
                };
                if ($done) {
                    $out['applied']++;
                } else {
                    $out['skipped'][] = "#{$i} {$label} : sans effet (événement visé absent ?)";
                }
            }
        });

        return $out;
    }
}
