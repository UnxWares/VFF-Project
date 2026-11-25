<?php

namespace App\Console\Commands;

use App\Services\ManualStations;
use App\Services\StationCorrections;
use App\Services\StationMerger;
use App\Services\StationNames\ConsolidationCarryOver;
use App\Services\StationTimeline;
use App\Support\StationNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dédoublonne `station_sources` (SNCF, Wikidata, OSM, OHM) en un référentiel
 * unique `stations`, avec provenance attribut par attribut.
 *
 * Ordre de traitement = ordre de confiance des positions :
 *   SNCF (positions officielles, 1 gare = 1 UIC) → Wikidata → OSM → OHM.
 *
 * Rapprochement d'un objet avec une gare déjà constituée :
 *   1. identifiants : UIC (P722 Wikidata, uic_ref OSM ↔ code UIC SNCF, sur
 *      7 chiffres, clé de contrôle ignorée), Q-id (tag wikidata OSM/OHM) -
 *      seulement si < 5 km (garde-fou contre les tags erronés) ; au-delà et
 *      jusqu'à 15 km, UIC ET nom identiques : la position de la source est
 *      fausse (coordonnées Wikidata d'Aigueperse à 6,8 km de la gare) ;
 *   2. nom normalisé + distance : nom identique ≤ 1 500 m (coordonnées
 *      Wikidata/OHM souvent au centre du village ; ≤ 3 000 m pour une gare des
 *      listes par ligne, posée par son PK ou sa commune), nom inclus ≤ 600 m,
 *      nom proche (≥ 0,6) ≤ 250 m, nom quelconque ≤ 60 m ;
 *   3. contraintes : au plus une gare SNCF et un élément Wikidata par gare,
 *      UIC / Q-id différents interdits, et une ancienne gare (désaffectée,
 *      rasée…) à plus de 150 m d'une gare en service reste distincte (gare
 *      déplacée : l'ancien bâtiment n'est pas la gare actuelle).
 *
 * Attributs retenus : position SNCF > OSM > Wikidata > OHM ; nom SNCF > OSM >
 * Wikidata (sans « Gare de ») > OHM ; dates = meilleure précision puis
 * Wikidata > OHM > OSM. Écart de position > 100 m entre sources → signalé
 * (stations.position_flagged).
 *
 * Liens défaits (database/data/gares_corrections.php, action « unlink ») : une gare visée par son UIC
 * ou son objet OSM ne prend pas le Q-id désigné (tag wikidata d'un objet OHM posé sur une autre gare),
 * et l'élément Wikidata de ce Q-id ne la rejoint pas ; les objets qui portent le tag restent rattachés.
 *
 * Rattachements (action « link ») : la gare visée par son UIC, son objet OSM ou sa position et son nom, dont aucun objet
 * ne porte de Q-id, prend celui de la correction (élément Wikidata trouvé par l'UIC, le nom et la distance, mais non
 * importé ou non rattaché) ; un Q-id déjà porté par un objet de la gare, ou défait par « unlink », l'emporte.
 *
 * Fusions (action « merge ») : deux fiches qui désignent la même gare (une seule gare au schéma de la ligne)
 * mais que les règles gardent distinctes (ancien bâtiment à plus de 150 m de la gare SNCF) deviennent une
 * seule gare, après le rapprochement : la fiche gardée garde son id et son slug, prend les objets sources de
 * l'autre (UIC, Q-id, noms, dates), et la position de la fiche désignée par la correction (règle de
 * positionOf appliquée aux seuls objets de cette fiche) ; App\Services\StationMerger reprend médias,
 * rattachements, accidents et événements, et l'adresse de la fiche absorbée redirige (station_redirects).
 *
 * Idempotent : une gare garde son id et son slug d'une exécution à l'autre
 * (via station_sources.station_id). --fresh repart de zéro (efface les gares,
 * à n'utiliser qu'avant toute contribution / média rattaché).
 */
class ConsolidateStations extends Command
{
    protected $signature = 'vff:consolidate-stations
        {--fresh : Supprime et recrée toutes les gares (ids et slugs changent)}
        {--dry-run : Calcule le dédoublonnage sans rien écrire}';

    protected $description = 'Dédoublonne station_sources (SNCF/Wikidata/OSM/OHM) → stations + station_events (provenance, écarts > 100 m)';

    private const SOURCE_ORDER = ['sncf' => 0, 'sncf_voyageurs' => 1, 'wikidata' => 2, 'osm' => 3, 'ohm' => 4];

    private const CELL = 0.025; // ≈ 2,8 km × 1,8 km : couvre le rayon max de 1 500 m en 3×3 cellules

    private const CLOSED_STATES = ['disused', 'abandoned', 'razed', 'historic'];

    /** UIC et nom identiques : rapprochés jusqu'à cette distance (m), position de la source erronée. */
    private const UIC_NAME_MAX_M = 15000;

    /** Nom identique : distance maximale (m) pour une gare des listes par ligne (vff:import-station-sources --source=pk). */
    private const LISTED_SAME_NAME_M = 3000;

    /** Nom normalisé d'une gare de marchandises (« X-Marchandises »), distincte de la gare de voyageurs de X. */
    private const FREIGHT_NAME_RX = '/\bmarchandises\b/';

    /** @var array<int, object> */
    private array $recs = [];

    /** @var array<int, array<string, mixed>> */
    private array $clusters = [];

    /** @var array<string, array<int, array{0: int, 1: int}>> cellule → [clusterId, recIdx] */
    private array $grid = [];

    /** @var list<array{name: string, uic: ?string, osm: ?string, source: string, ref: string}> liens défaits (StationCorrections) */
    private array $unlinks = [];

    /** @var array<int, int> liens défaits : n° → objets sources qui désignent la gare */
    private array $unlinkHits = [];

    /** @var list<array{name: string, uic: ?string, osm: ?string, near: ?array{float, float, float}, source: string, ref: string}> rattachements (StationCorrections) */
    private array $links = [];

    /** @var array<int, int> rattachements : n° → objets sources qui désignent la gare */
    private array $linkHits = [];

    /** @var list<array{kept: array<string, string>, absorbed: array<string, string>, position: string, source_url: string}> fusions (StationCorrections) */
    private array $merges = [];

    public function handle(StationCorrections $corrections, StationMerger $merger): int
    {
        ini_set('memory_limit', '2G');
        // L'instance de la commande est réutilisée d'un appel à l'autre dans un même
        // processus (vff:stations-pipeline, tests) : sans remise à zéro, les objets et
        // gares du passage précédent s'ajoutaient (rattachements en double, erreur SQL).
        $this->recs = [];
        $this->clusters = [];
        $this->grid = [];
        $this->unlinks = $corrections->unlinks();
        $this->unlinkHits = array_fill(0, count($this->unlinks), 0);
        $this->links = $corrections->links();
        $this->linkHits = array_fill(0, count($this->links), 0);
        $this->merges = $corrections->merges();
        $this->loadRecords();
        $this->info(count($this->recs).' objets sources');

        // Par source (ordre de confiance), d'abord les rapprochements par
        // identifiant (UIC, Q-id), puis nom + distance pour le reste : un
        // élément qui porte le bon UIC passe avant un homonyme voisin.
        $bySource = [];
        $sources = array_values(array_unique(array_merge(array_keys(self::SOURCE_ORDER), array_map(fn ($r) => $r->source, $this->recs))));
        foreach ($sources as $src) {
            $rest = [];
            foreach ($this->recs as $i => $r) {
                if ($r->source !== $src) {
                    continue;
                }
                [$cid, $method, $dist] = $this->matchById($r);
                if ($cid === null) {
                    $rest[] = $i;

                    continue;
                }
                $this->attach($cid, $i, $method, $dist);
                $bySource["{$src}/{$method}"] = ($bySource["{$src}/{$method}"] ?? 0) + 1;
            }
            foreach ($rest as $i) {
                $r = $this->recs[$i];
                [$cid, $method, $dist] = $this->matchByName($r);
                if ($cid === null && $r->names === []) {
                    // Nom générique (« Gare », « Ancienne gare ») sans gare voisine : inexploitable.
                    $bySource["{$src}/ignored"] = ($bySource["{$src}/ignored"] ?? 0) + 1;

                    continue;
                }
                if ($cid === null) {
                    $cid = count($this->clusters);
                    $this->clusters[$cid] = ['members' => [], 'sources' => [], 'names' => [], 'uic' => null, 'qid' => null, 'active' => false, 'unlinked' => [], 'unlink_keys' => []];
                    [$method, $dist] = ['seed', 0];
                }
                $this->attach($cid, $i, $method, $dist);
                $bySource["{$src}/{$method}"] = ($bySource["{$src}/{$method}"] ?? 0) + 1;
            }
        }
        $this->applyMerges();
        $this->info(sprintf('%d gares après dédoublonnage', count($this->clusters)));
        ksort($bySource);
        foreach ($bySource as $k => $v) {
            $this->line("  {$k} : {$v}");
        }
        foreach ($this->unlinks as $k => $u) {
            $hit = array_values(array_filter($this->clusters, fn ($c) => isset($c['unlink_keys'][$k])));
            $line = sprintf('  lien défait %s / %s %s : %d gare(s)%s', $u['name'], $u['source'], $u['ref'], count($hit),
                $hit ? ' ('.implode(', ', array_map(fn ($c) => $this->recs[$c['members'][0]]->name.', Q-id retenu : '.($c['qid'] ?? 'aucun'), $hit)).')' : '');
            $this->unlinkHits[$k] === 0 ? $this->warn($line.' : gare introuvable dans les sources') : $this->line($line);
        }
        foreach ($this->links as $k => $l) {
            $line = sprintf('  rattachement %s / %s %s : %d objet(s)', $l['name'], $l['source'], $l['ref'], $this->linkHits[$k]);
            $this->linkHits[$k] === 0 ? $this->warn($line.' : gare introuvable dans les sources') : $this->line($line);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($merger) {
            if ($this->option('fresh')) {
                DB::table('station_sources')->update(['station_id' => null]);
                DB::table('stations')->delete();
            }
            $this->persist($merger);
        });

        $this->report();

        return self::SUCCESS;
    }

    private function loadRecords(): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT id, source, source_ref, station_id, name, name_norm, kind, lifecycle, uic_code, wikidata_id,
                   opened_on, opened_precision, closed_on, closed_precision, closed_scope, passenger, freight,
                   events, properties, ST_X(geom) AS lon, ST_Y(geom) AS lat
            FROM station_sources
            ORDER BY CASE source WHEN 'sncf' THEN 0 WHEN 'sncf_voyageurs' THEN 1 WHEN 'wikidata' THEN 2 WHEN 'osm' THEN 3 WHEN 'ohm' THEN 4 ELSE 5 END,
                     -- OSM : objets « railway=… » en service d'abord, bâtiments ensuite.
                     CASE lifecycle WHEN 'active' THEN 0 WHEN 'unknown' THEN 2 ELSE 1 END, id
        SQL);
        foreach ($rows as $r) {
            $r->props = $r->properties ? json_decode($r->properties, true) : [];
            $r->events = $r->events ? json_decode($r->events, true) : [];
            $r->uic7 = $r->uic_code && strlen($r->uic_code) >= 7 ? substr($r->uic_code, 0, 7) : null;
            // Q-id porté par un tag (OSM / OHM).
            $r->qid = $r->wikidata_id;
            $r->names = array_values(array_unique(array_filter(array_merge(
                [$r->name_norm],
                array_map([StationNames::class, 'normalize'], $r->props['aliases'] ?? []),
                array_map([StationNames::class, 'normalize'], array_filter([$r->props['old_name'] ?? null, $r->props['official_name'] ?? null, $r->props['uic_name'] ?? null])),
            ))));
            $r->unlink = $this->unlinksOf($r);
            $r->linked = false;
            if ($r->qid === null && $r->source !== 'wikidata') {
                $link = $this->linkOf($r);
                if ($link !== null) {
                    $r->qid = $link;
                    $r->linked = true;
                }
            }
            $this->recs[] = $r;
        }
    }

    /**
     * Q-id à ignorer pour la gare que désigne cet objet (action « unlink » : UIC sur 7 chiffres ou objet OSM,
     * et nom de la correction parmi les noms de l'objet).
     *
     * @return array<string, true>
     */
    private function unlinksOf(object $r): array
    {
        $out = [];
        $r->unlinkKeys = [];
        foreach ($this->unlinks as $k => $u) {
            $byId = ($u['uic'] !== null && $r->uic7 !== null && $r->uic7 === substr($u['uic'], 0, 7))
                || ($u['osm'] !== null && $r->source === 'osm' && $r->source_ref === $u['osm']);
            if ($byId && in_array(StationNames::normalize($u['name']), $r->names, true)) {
                $out[$u['ref']] = true;
                $r->unlinkKeys[$k] = true;
                $this->unlinkHits[$k]++;
            }
        }

        return $out;
    }

    /**
     * Q-id posé sur cet objet par une correction « link » : UIC sur 7 chiffres ou objet OSM, ou position (rayon de la
     * correction) ; dans tous les cas le nom de la correction est parmi les noms de l'objet, sauf pour l'UIC, qui désigne
     * la gare sans ambiguïté. Un Q-id défait pour cette gare (« unlink ») n'est pas posé.
     */
    private function linkOf(object $r): ?string
    {
        foreach ($this->links as $k => $l) {
            if (isset($r->unlink[$l['ref']])) {
                continue;
            }
            $nameOk = in_array(StationNames::normalize($l['name']), $r->names, true);
            $hit = ($l['uic'] !== null && $r->uic7 !== null && $r->uic7 === substr($l['uic'], 0, 7))
                || ($l['osm'] !== null && $r->source === 'osm' && $r->source_ref === $l['osm'] && $nameOk)
                || ($l['near'] !== null && $nameOk && $r->lon !== null && $this->haversine((float) $r->lat, (float) $r->lon, $l['near'][1], $l['near'][0]) <= $l['near'][2] * 1000);
            if ($hit) {
                $this->linkHits[$k]++;

                return $l['ref'];
            }
        }

        return null;
    }

    /**
     * Fusions de fiches (action « merge ») : la gare de la fiche absorbée rejoint celle de la fiche gardée.
     * Chaque fiche est la gare d'un objet source désigné par son identifiant (UIC sur 7 chiffres, objet OSM,
     * élément Wikidata) et dont les noms comprennent celui de la correction. Fiche introuvable, ou les deux
     * déjà réunies : signalé, rien n'est changé.
     */
    private function applyMerges(): void
    {
        foreach ($this->merges as $merge) {
            $label = "fusion {$merge['absorbed']['name']} dans {$merge['kept']['name']}";
            $kept = $this->clusterOf($merge['kept']);
            $absorbed = $this->clusterOf($merge['absorbed']);
            if ($kept === null || $absorbed === null) {
                $this->warn("  {$label} : ".($kept === null ? 'fiche gardée' : 'fiche absorbée').' introuvable dans les sources');

                continue;
            }
            [$kc, $keptRec] = $kept;
            [$ac] = $absorbed;
            if ($kc === $ac) {
                $this->line("  {$label} : déjà une seule gare");

                continue;
            }
            $a = $this->clusters[$ac];
            $c = &$this->clusters[$kc];
            // La fiche gardée : celle de l'objet qui la désigne (sinon l'id majoritaire des membres l'emporterait).
            $c['kept_id'] ??= $this->recs[$keptRec]->station_id;
            $c['absorbed_ids'] = array_values(array_unique(array_filter([...($c['absorbed_ids'] ?? []), ...($a['absorbed_ids'] ?? []),
                ...array_map(fn ($m) => $this->recs[$m]->station_id, $a['members'])], fn ($id) => $id !== null && $id !== $c['kept_id'])));
            if ($merge['position'] === 'absorbed') {
                $c['position_members'] = $a['position_members'] ?? $a['members'];
            }
            $c['members'] = [...$c['members'], ...$a['members']];
            $c['sources'] += $a['sources'];
            $c['names'] = array_values(array_unique([...$c['names'], ...$a['names']]));
            $c['uic'] ??= $a['uic'];
            $c['qid'] ??= $a['qid'];
            $c['unlinked'] = ($c['unlinked'] ?? []) + ($a['unlinked'] ?? []);
            $c['unlink_keys'] = ($c['unlink_keys'] ?? []) + ($a['unlink_keys'] ?? []);
            $c['active'] = $c['active'] || $a['active'];
            $c['merged'] = true;
            unset($c);
            unset($this->clusters[$ac]);
            $this->line("  {$label} : réunies (position de la fiche ".($merge['position'] === 'absorbed' ? 'absorbée' : 'gardée').')');
        }
    }

    /**
     * Gare (n° de groupe) et objet source désignés par une fiche d'une fusion ; null si aucun objet ne la désigne.
     *
     * @param  array<string, string>  $side  name + uic, wikidata ou osm
     * @return array{0: int, 1: int}|null
     */
    private function clusterOf(array $side): ?array
    {
        $name = StationNames::normalize($side['name']);
        foreach ($this->clusters as $cid => $c) {
            foreach ($c['members'] as $m) {
                $r = $this->recs[$m];
                $byId = (isset($side['uic']) && $r->uic7 !== null && $r->uic7 === substr($side['uic'], 0, 7))
                    || (isset($side['osm']) && $r->source === 'osm' && $r->source_ref === $side['osm'])
                    || (isset($side['wikidata']) && $r->source === 'wikidata' && $r->source_ref === $side['wikidata']);
                if ($byId && in_array($name, $r->names, true)) {
                    return [$cid, $m];
                }
            }
        }

        return null;
    }

    /**
     * Rapprochement par identifiant (UIC sur 7 chiffres, Q-id), < 5 km.
     *
     * @return array{0: ?int, 1: ?string, 2: ?int}
     */
    private function matchById(object $r): array
    {
        if ($r->source !== 'sncf' && $r->uic7) {
            foreach ($this->neighbours($r, 5000) as [$cid, $d]) {
                if (($this->clusters[$cid]['uic'] ?? null) === $r->uic7 && $this->compatible($cid, $r, $d, 1.0, true)) {
                    return [$cid, 'uic', $d];
                }
            }
            // UIC ET nom identiques, plus loin : c'est la POSITION de la source qui est fausse
            // (coordonnées Wikidata posées dans une autre commune, ex. Aigueperse à 6,8 km de la
            // gare), pas une autre gare. Deux identifiants concordants suffisent.
            foreach ($this->neighbours($r, self::UIC_NAME_MAX_M) as [$cid, $d]) {
                if ($d > 5000 && ($this->clusters[$cid]['uic'] ?? null) === $r->uic7
                    && array_intersect($r->names, $this->clusters[$cid]['names']) !== []
                    && $this->compatible($cid, $r, $d, 1.0, true)) {
                    return [$cid, 'uic_name', $d];
                }
            }
        }
        if ($r->qid && $r->source !== 'wikidata') {
            foreach ($this->neighbours($r, 5000) as [$cid, $d]) {
                if (($this->clusters[$cid]['qid'] ?? null) === $r->qid && $this->compatible($cid, $r, $d, 1.0, true)) {
                    return [$cid, 'wikidata', $d];
                }
            }
        }
        // Élément Wikidata d'une gare à laquelle une correction « link » l'a rattaché (gare de marchandises, nom ou UIC
        // différents) : il rejoint la gare qui porte son Q-id, ses déclarations (dates, coordonnées) entrent dans la gare.
        if ($r->source === 'wikidata' && $r->qid) {
            foreach ($this->neighbours($r, 5000) as [$cid, $d]) {
                if (($this->clusters[$cid]['qid'] ?? null) === $r->qid && $this->compatible($cid, $r, $d, 1.0, true)) {
                    return [$cid, 'wikidata', $d];
                }
            }
        }

        return [null, null, null];
    }

    /**
     * Rapprochement par nom normalisé + distance.
     *
     * @return array{0: ?int, 1: ?string, 2: ?int}
     */
    private function matchByName(object $r): array
    {
        $best = null;
        $bestScore = -INF;
        // Gare d'une liste par ligne posée par vff:import-station-sources --source=pk (PK calé, commune : précision
        // jusqu'à 2 ou 3 km ; gare SNCF homonyme : position de la liste des gares de SA ligne, qui diffère parfois
        // de plusieurs km d'une ligne à l'autre, Pouillenay) : le même nom exact jusqu'à LISTED_SAME_NAME_M est la
        // même gare (sinon doublon à 1,5 - 3 km).
        $sameName = isset($r->props['geocoding']) ? self::LISTED_SAME_NAME_M : 1500;
        foreach ($this->neighbours($r, $sameName) as [$cid, $d]) {
            $c = $this->clusters[$cid];
            $sim = 0.0;
            foreach ($r->names as $n) {
                foreach ($c['names'] as $m) {
                    $sim = max($sim, $this->nameSim($n, $m));
                }
            }
            if (! $this->compatible($cid, $r, $d, $sim)) {
                continue;
            }
            // ≤ 60 m quel que soit le nom, sauf deux gares OSM en service nommées
            // différemment (gares voisines distinctes) ; un bâtiment (« Ancienne
            // gare », « Bistrot de la gare »…) rejoint la gare qu'il abrite.
            $ok = ($sim >= 0.999 && $d <= $sameName) || ($sim >= 0.85 && $d <= 600) || ($sim >= 0.6 && $d <= 250)
                || ($d <= 60 && (! isset($c['sources'][$r->source]) || $r->source === 'ohm'
                    || $r->lifecycle !== 'active' || $r->names === []));
            if (! $ok) {
                continue;
            }
            $score = 2 * $sim - $d / 1500;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$cid, $sim >= 0.6 ? 'name_near' : 'near', $d];
            }
        }

        return $best ?? [null, null, null];
    }

    /**
     * Contraintes dures entre un objet et une gare candidate.
     */
    private function compatible(int $cid, object $r, int $d, float $sim, bool $byId = false): bool
    {
        $c = $this->clusters[$cid];
        // Gare saisie à la main (database/data/gares_manuelles.php) : une gare à part, jamais fondue dans une autre
        // par son nom ou sa proximité (la première gare de Boulogne-Ville ne rejoint pas la gare de 1962), et
        // aucun autre objet ne la rejoint.
        if ($r->source === ManualStations::SOURCE || isset($c['sources'][ManualStations::SOURCE])) {
            return false;
        }
        // Une seule gare SNCF par gare ; un seul élément Wikidata, sauf doublon
        // manifeste (même nom à ≤ 100 m : « gare de X » et « gare de X (ligne Y) »).
        if (in_array($r->source, ['sncf', 'sncf_voyageurs'], true) && isset($c['sources'][$r->source])) {
            return false;
        }
        if ($r->source === 'wikidata' && isset($c['sources']['wikidata']) && ! ($sim >= 0.85 && $d <= 100)) {
            return false;
        }
        // Lien défait (action « unlink ») : l'élément Wikidata est celui d'une autre gare.
        if ($r->source === 'wikidata' && isset($c['unlinked'][$r->source_ref])) {
            return false;
        }
        // UIC différents : gares distinctes, sauf homonymes à ≤ 500 m (code RATP
        // vs SNCF d'une même gare, erreur de saisie Wikidata…).
        if ($r->uic7 && $c['uic'] && $r->uic7 !== $c['uic'] && ! ($sim >= 0.9 && $d <= 500)) {
            return false;
        }
        if ($r->qid && $c['qid'] && $r->qid !== $c['qid'] && ! ($sim >= 0.9 && $d <= 300)) {
            return false;
        }
        if ($byId || ! in_array($r->source, ['osm', 'ohm'], true)) {
            return true;
        }
        // Objet OHM terminé d'un autre réseau (chemin de fer départemental, tramway, exploitant secondaire) :
        // gare distincte, jamais rattachée par son nom à la gare SNCF homonyme (« Sens (CFD) », « Morez PLM »
        // du NStCM, Lavaur des TVT : leur fin datée fermait la gare SNCF).
        if ($r->source === 'ohm' && $r->closed_on && (isset($c['sources']['sncf']) || isset($c['sources']['sncf_voyageurs']))
            && self::ohmOtherNetwork($r->props, $r->name)) {
            return false;
        }
        // Gare déplacée : l'ancien bâtiment (OSM/OHM désaffecté, rasé…) à
        // > 150 m de la gare en service est une gare distincte.
        if (in_array($r->lifecycle, self::CLOSED_STATES, true) && $c['active'] && $d > 150) {
            return false;
        }
        if ($r->lifecycle === 'active' && ($c['closed_only'] ?? false) && $d > 150) {
            return false;
        }

        return true;
    }

    /**
     * Objet OHM d'un autre réseau que le grand réseau : nom « (CFD) » ou « tram… », gabarit de tramway,
     * ou exploitant (operator) qui n'est pas une grande compagnie ni la SNCF (TVT, NStCM, CFSO, CFHF…).
     *
     * @param  array<string, mixed>  $props  propriétés de l'objet OHM (station_sources.properties)
     */
    public static function ohmOtherNetwork(array $props, ?string $name): bool
    {
        if (preg_match('/\bCFD\b|\btram/iu', (string) $name) || ($props['railway'] ?? null) === 'tram_stop' || isset($props['tram'])) {
            return true;
        }
        $operator = trim((string) ($props['operator'] ?? ''));

        // Grande compagnie, pas « Sud-Ouest » (CFSO). Train touristique (AGRIVAP, Chemin de fer du Haut-Forez,
        // du Tarn) : il roule sur la ligne et dans la gare du grand réseau (Ambert, La Chaise-Dieu), même gare.
        return $operator !== '' && ! preg_match('/(?<![-\w])(SNCF|PLM|PO|Midi|Nord|Est|Ouest|[EÉ]tat|RFF|AGRIVAP|CFHF|CFTT)\b/u', $operator)
            && ! preg_match('/touristique|d[ée]couverte/iu', $operator);
    }

    /**
     * Fermeture OHM retenue ? Quand la SNCF dit la gare ouverte aux voyageurs aujourd'hui, la fin datée de
     * tous ses objets OHM n'est une fermeture que si une réouverture d'une autre source la suit ; sinon c'est la fin d'un objet voisin (bâtiment remplacé à
     * Maubeuge et Lourches, première gare d'Andrézieux, halte du départemental à Montereau). Une gare fret
     * seul ou une gare d'un train touristique (OSM en service) garde sa fermeture OHM : c'est la fin des
     * voyageurs SNCF (Bavay, Courpière, Puy-Guillaume).
     *
     * @param  list<array<string, mixed>>  $events  événements déjà retenus (Wikidata : type, event_date)
     */
    public static function keepsOhmClosure(string $closedOn, bool $servedToday, array $events): bool
    {
        if (! $servedToday) {
            return true;
        }
        foreach ($events as $e) {
            if (($e['type'] ?? null) === 'reopening' && (string) $e['event_date'] > $closedOn) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gares ayant au moins un membre à moins de $radius mètres.
     *
     * @return array<int, array{0: int, 1: int}> [clusterId, distance min]
     */
    private function neighbours(object $r, int $radius): array
    {
        $cx = (int) floor($r->lon / self::CELL);
        $cy = (int) floor($r->lat / self::CELL);
        // Au-delà de 5 km (rapprochement UIC + nom) : le rayon rapporté à la largeur d'une
        // cellule (la plus étroite, en longitude).
        $span = match (true) {
            $radius > 5000 => max(2, (int) ceil($radius / (self::CELL * 111_320 * cos(deg2rad($r->lat))))),
            $radius > 2000 => 2,
            default => 1,
        };
        $out = [];
        for ($x = $cx - $span; $x <= $cx + $span; $x++) {
            for ($y = $cy - $span; $y <= $cy + $span; $y++) {
                foreach ($this->grid["{$x}:{$y}"] ?? [] as [$cid, $ri]) {
                    $m = $this->recs[$ri];
                    $d = $this->haversine($r->lat, $r->lon, $m->lat, $m->lon);
                    if ($d <= $radius && (! isset($out[$cid]) || $d < $out[$cid])) {
                        $out[$cid] = $d;
                    }
                }
            }
        }
        asort($out);

        return array_map(fn ($cid, $d) => [$cid, (int) round($d)], array_keys($out), $out);
    }

    private function attach(int $cid, int $i, string $method, int $dist): void
    {
        $r = $this->recs[$i];
        $r->method = $method;
        $r->dist = $dist;
        $c = &$this->clusters[$cid];
        $c['members'][] = $i;
        $c['sources'][$r->source] = true;
        foreach ($r->names as $n) {
            $c['names'][$n] = $n;
        }
        $c['names'] = array_values(array_unique($c['names']));
        $c['uic'] ??= $r->uic7;
        $c['unlinked'] = ($c['unlinked'] ?? []) + $r->unlink;
        $c['unlink_keys'] = ($c['unlink_keys'] ?? []) + $r->unlinkKeys;
        if ($r->qid !== null && ! isset($c['unlinked'][$r->qid])) {
            $c['qid'] ??= $r->qid;
        }
        if ($c['qid'] !== null && isset($c['unlinked'][$c['qid']])) {
            // Lien défait connu après coup (l'objet qui désigne la gare arrive après celui qui porte le tag).
            $c['qid'] = null;
            foreach ($c['members'] as $m) {
                $q = $this->recs[$m]->qid;
                if ($q !== null && ! isset($c['unlinked'][$q])) {
                    $c['qid'] = $q;
                    break;
                }
            }
        }
        $isActive = (in_array($r->source, ['sncf', 'sncf_voyageurs'], true) && ($r->passenger || $r->freight)) || ($r->source === 'osm' && $r->lifecycle === 'active');
        $c['active'] = $c['active'] || $isActive;
        $c['closed_only'] = ! $c['active'] && collect($c['members'])->every(fn ($m) => in_array($this->recs[$m]->lifecycle, self::CLOSED_STATES, true));
        $key = ((int) floor($r->lon / self::CELL)).':'.((int) floor($r->lat / self::CELL));
        $this->grid[$key][] = [$cid, $i];
    }

    private function nameSim(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        // Gare de marchandises (« X-Marchandises ») : une AUTRE installation que la gare de voyageurs
        // du même lieu (« Gif-sur-Yvette-Marchandises », gare SNCF désaffectée à 230 m de la
        // gare RER de Gif-sur-Yvette, qui prenait sinon son nom et son UIC).
        if (preg_match(self::FREIGHT_NAME_RX, $a) !== preg_match(self::FREIGHT_NAME_RX, $b)) {
            return 0.0;
        }
        if (str_contains(" {$b} ", " {$a} ") || str_contains(" {$a} ", " {$b} ")) {
            return 0.9;
        }
        // Mêmes mots dans un autre ordre (« Grignan - Chamaret » et « Chamaret-Grignan ») : la même gare à courte
        // distance (≤ 600 m), sans lever les garde-fous des UIC et Q-id différents (0,9).
        $ta = explode(' ', $a);
        $tb = explode(' ', $b);
        sort($ta);
        sort($tb);
        if ($ta === $tb) {
            return 0.85;
        }
        similar_text($a, $b, $pct);

        return $pct / 100;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $p = M_PI / 180;
        $a = 0.5 - cos(($lat2 - $lat1) * $p) / 2 + cos($lat1 * $p) * cos($lat2 * $p) * (1 - cos(($lon2 - $lon1) * $p)) / 2;

        return 12742000 * asin(sqrt($a));
    }

    // ── Écriture ────────────────────────────────────────────────────────────

    private function persist(StationMerger $merger): void
    {
        $now = now();
        $built = [];
        foreach ($this->clusters as $cid => $c) {
            $built[$cid] = $this->buildStation($c);
        }

        // Commune + département par géocodage inverse (contours Etalab).
        // ON COMMIT DROP ne suffit pas si la commande tourne deux fois dans une même
        // transaction englobante (tests, chaînes appelantes) : la table existerait encore.
        DB::statement('DROP TABLE IF EXISTS tmp_cluster_pos');
        DB::statement('CREATE TEMP TABLE tmp_cluster_pos (cid int PRIMARY KEY, geom geometry(Point, 4326)) ON COMMIT DROP');
        foreach (array_chunk($built, 1000, true) as $chunk) {
            $vals = [];
            $b = [];
            foreach ($chunk as $cid => $s) {
                $vals[] = '(?, ST_SetSRID(ST_MakePoint(?, ?), 4326))';
                array_push($b, $cid, $s['lon'], $s['lat']);
            }
            DB::insert('INSERT INTO tmp_cluster_pos VALUES '.implode(',', $vals), $b);
        }
        $geo = DB::select(<<<'SQL'
            SELECT p.cid, c.nom, c.departement
            FROM tmp_cluster_pos p
            JOIN LATERAL (SELECT nom, departement FROM commune_contours c
                          WHERE c.geom && ST_Expand(p.geom, 0.01)
                          ORDER BY ST_Distance(c.geom, p.geom) LIMIT 1) c ON TRUE
        SQL);
        foreach ($geo as $g) {
            $built[$g->cid]['city'] = $g->nom;
            $built[$g->cid]['department_code'] = $g->departement;
        }

        // Slugs déjà pris par les gares conservées.
        $existing = DB::table('stations')->pluck('slug', 'id')->all();
        $existingNames = DB::table('stations')->pluck('name', 'id')->all();
        // Ce que la consolidation ne produit pas (autres noms des autres sources, clés de provenance) est reporté.
        $ownedSources = array_values(array_unique(array_merge(array_keys(self::SOURCE_ORDER), array_map(fn ($r) => $r->source, $this->recs))));
        $currentAlt = [];
        $currentProv = [];
        foreach (DB::table('stations')->get(['id', 'alt_names', 'provenance']) as $row) {
            $alt = json_decode((string) $row->alt_names, true);
            $prov = json_decode((string) $row->provenance, true);
            $currentAlt[(int) $row->id] = is_array($alt) ? array_values($alt) : [];
            $currentProv[(int) $row->id] = is_array($prov) ? $prov : [];
        }
        $carriedAlt = [];
        $usedSlugs = array_flip($existing);
        $keptIds = [];

        foreach ($built as $cid => &$s) {
            // Gare existante : id majoritaire parmi les membres déjà rattachés.
            $ids = array_filter(array_map(fn ($m) => $this->recs[$m]->station_id, $this->clusters[$cid]['members']));
            $sid = null;
            $forced = $this->clusters[$cid]['kept_id'] ?? null;
            if ($forced !== null && isset($existing[$forced]) && ! isset($keptIds[$forced])) {
                // Fusion : la fiche gardée garde son id et son slug, quel que soit le nombre d'objets de l'autre.
                $sid = (int) $forced;
            } elseif ($ids) {
                $counts = array_count_values($ids);
                // À égalité, la fiche la plus ancienne (plus petit id) : un doublon créé à tort puis rapproché de la gare
                // existante disparaît, pas la gare existante (Grignan - Chamaret 24778 et Chamaret-Grignan 23325).
                uksort($counts, fn ($a, $b) => [$counts[$b], $a] <=> [$counts[$a], $b]);
                // Gare connue des seules listes par ligne : l'ancienne gare d'un membre n'est reprise que si son nom
                // ET son slug concordent (un placement corrigé ne donne pas à « Puisieux » l'id et le slug de
                // « faubourg-de-guise », gare créée sous ce nom par un placement faux puis renommée).
                $listedOnly = ! array_filter($this->clusters[$cid]['members'], fn ($m) => ! isset($this->recs[$m]->props['geocoding']));
                foreach (array_keys($counts) as $candidate) {
                    if ($listedOnly && isset($existing[$candidate], $existingNames[$candidate]) && (
                        ! str_starts_with((string) $existing[$candidate], Str::slug($s['name']) ?: 'gare')
                        || $this->nameSim((string) StationNames::normalize((string) $existingNames[$candidate]), (string) StationNames::normalize((string) $s['name'])) < 0.6)) {
                        continue;
                    }
                    if (isset($existing[$candidate]) && ! isset($keptIds[$candidate])) {
                        $sid = $candidate;
                        break;
                    }
                }
            }
            $s['id'] = $sid;
            if ($sid && ! (self::isGenericSlug((string) $existing[$sid]) && ! self::isGenericSlug(Str::slug($s['name']) ?: 'gare'))) {
                $keptIds[$sid] = true;
            } else {
                if ($sid) {
                    // Gare existante au slug générique qui a maintenant un vrai nom : nouveau slug, l'ancien redirige.
                    $keptIds[$sid] = true;
                    $s['old_slug'] = $existing[$sid];
                }
                $base = Str::slug($s['name']) ?: 'gare';
                $slug = $base;
                if (isset($usedSlugs[$slug])) {
                    $slug = $s['department_code'] ? "{$base}-{$s['department_code']}" : "{$base}-2";
                    $n = 2;
                    while (isset($usedSlugs[$slug])) {
                        $slug = ($s['department_code'] ? "{$base}-{$s['department_code']}" : $base).'-'.(++$n);
                    }
                }
                $usedSlugs[$slug] = true;
                $s['slug'] = $slug;
            }
        }
        unset($s);

        // Fusions : ce qui pointe vers la fiche absorbée passe à la fiche gardée, avant sa suppression (orpheline).
        foreach ($built as $cid => $s) {
            foreach ($s['id'] ? ($this->clusters[$cid]['absorbed_ids'] ?? []) : [] as $absorbed) {
                if (isset($existing[$absorbed]) && ! isset($keptIds[$absorbed])) {
                    $carriedAlt[(int) $s['id']] = [...($carriedAlt[(int) $s['id']] ?? []), ...ConsolidationCarryOver::foreignAltNames($currentAlt[(int) $absorbed] ?? [], $ownedSources)];
                    $moved = array_filter($merger->merge((int) $absorbed, (int) $s['id']));
                    $this->line(sprintf('  fiche %d (%s) fondue dans %d : %s', $absorbed, $existing[$absorbed], $s['id'],
                        $moved === [] ? 'rien à reprendre' : implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($moved), $moved))));
                }
            }
        }

        // Gares orphelines (plus aucune source) : supprimées.
        $orphans = array_diff(array_keys($existing), array_keys($keptIds));
        // Gare orpheline dont les objets sources ont rejoint une gare conservée (doublon rapproché) : fondue dans la gare
        // qui a reçu le plus de ses objets, comme une fusion (StationMerger) : médias, contributions, signalements,
        // événements hors sources, bibliographie, patrimoine, quais et anciennes adresses passent à cette gare, et son
        // adresse redirige vers elle. Supprimée sans cela, la fiche emportait ces données (clés étrangères en cascade,
        // médias et contributions sans clé laissés vers un id disparu).
        $this->mergeJoinedOrphans($merger, $built, $orphans, $existing);
        foreach (array_chunk($orphans, 1000) as $chunk) {
            DB::table('stations')->whereIn('id', $chunk)->delete();
        }

        $cols = ['name', 'alt_names', 'type', 'city', 'department_code', 'opened_at', 'closed_at', 'opened_precision',
            'closed_precision', 'status', 'source_status', 'lifecycle', 'uic_code', 'wikidata_id', 'osm_node_id', 'provenance',
            'position_source', 'position_spread_m', 'position_flagged', 'sources_count'];

        // osm_node_id est UNIQUE : on libère d'abord les valeurs pour éviter les collisions transitoires.
        DB::table('stations')->update(['osm_node_id' => null]);

        $bar = $this->output->createProgressBar(count($built));
        foreach ($built as $cid => $s) {
            $row = array_intersect_key($s, array_flip($cols));
            $prov = $s['provenance'];
            if ($s['id']) {
                $old = (int) $s['id'];
                $carried = [...ConsolidationCarryOver::foreignAltNames($currentAlt[$old] ?? [], $ownedSources), ...($carriedAlt[$old] ?? [])];
                $s['alt_names'] = ConsolidationCarryOver::withCarried($s['alt_names'], $carried);
                if (ConsolidationCarryOver::revertsToPlaceName($currentAlt[$old] ?? [], (string) ($existingNames[$old] ?? ''), (string) $s['name'])) {
                    // Nom ferroviaire posé par vff:import-station-names : la consolidation ne le rend pas au nom du lieu.
                    $row['name'] = $existingNames[$old];
                    $prov['name'] = $currentProv[$old]['name'] ?? $prov['name'];
                }
                $prov += ConsolidationCarryOver::foreignProvenance($currentProv[$old] ?? []);
            }
            $row['alt_names'] = json_encode($s['alt_names'], JSON_UNESCAPED_UNICODE);
            $row['provenance'] = json_encode($prov);
            $row['updated_at'] = $now;
            $geom = DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $s['lon'], $s['lat']));
            if ($s['id']) {
                DB::table('stations')->where('id', $s['id'])->update($row + ['geom' => $geom] + (isset($s['old_slug']) ? ['slug' => $s['slug']] : []));
                $sid = $s['id'];
                if (isset($s['old_slug'])) {
                    DB::table('station_redirects')->upsert([['old_slug' => $s['old_slug'], 'station_id' => $sid, 'created_at' => $now, 'updated_at' => $now]],
                        ['old_slug'], ['station_id', 'updated_at']);
                    $this->line(sprintf('  gare %d : slug générique %s remplacé par %s', $sid, $s['old_slug'], $s['slug']));
                }
            } else {
                $sid = DB::table('stations')->insertGetId($row + ['slug' => $s['slug'], 'geom' => $geom, 'created_at' => $now]);
            }
            $this->clusters[$cid]['station_id'] = $sid;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Rattachement des objets sources.
        // ON COMMIT DROP ne suffit pas si la commande tourne deux fois dans une même
        // transaction englobante (tests, chaînes appelantes) : la table existerait encore.
        DB::statement('DROP TABLE IF EXISTS tmp_src_match');
        DB::statement('CREATE TEMP TABLE tmp_src_match (id bigint PRIMARY KEY, station_id bigint, method varchar(24), dist int) ON COMMIT DROP');
        $vals = [];
        foreach ($this->clusters as $c) {
            foreach ($c['members'] as $m) {
                $r = $this->recs[$m];
                $vals[] = [$r->id, $c['station_id'], $r->method, $r->dist];
            }
        }
        foreach (array_chunk($vals, 2000) as $chunk) {
            DB::insert('INSERT INTO tmp_src_match VALUES '.implode(',', array_fill(0, count($chunk), '(?,?,?,?)')), array_merge(...$chunk));
        }
        DB::statement('UPDATE station_sources ss SET station_id = t.station_id, match_method = t.method, match_distance_m = t.dist
                       FROM tmp_src_match t WHERE t.id = ss.id');
        DB::statement('UPDATE station_sources ss SET station_id = NULL, match_method = NULL, match_distance_m = NULL
                       WHERE NOT EXISTS (SELECT 1 FROM tmp_src_match t WHERE t.id = ss.id) AND ss.station_id IS NOT NULL');

        // Événements issus des sources (les événements déduits sont gérés par vff:build-station-eras).
        // Les fermetures lues par vff:import-station-closures (articles Wikipédia,
        // qualificatifs Wikidata, notes OSM) sont conservées : elles ne viennent pas
        // de station_sources. Une gare supprimée (orpheline) les perd par cascade, sauf un doublon rejoint par ses
        // sources, fondu avant (mergeJoinedOrphans).
        // Corrections sourcées (gares_corrections.php) : gardées aussi, réappliquées par vff:build-station-eras.
        DB::table('station_events')->where('is_inferred', false)
            ->whereNotIn('source', [...ImportStationClosures::SOURCES, StationTimeline::CORRECTION])->delete();
        $events = [];
        foreach ($built as $cid => $s) {
            foreach ($s['events'] as $e) {
                $events[] = $e + ['station_id' => $this->clusters[$cid]['station_id'], 'is_inferred' => false, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($events, 1000) as $chunk) {
            DB::table('station_events')->insertOrIgnore($chunk);
        }
        $this->info(count($events).' événements datés issus des sources');
    }

    /**
     * Fusionne les membres d'une gare en un enregistrement `stations`.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function buildStation(array $c): array
    {
        $members = array_map(fn ($m) => $this->recs[$m], $c['members']);
        $by = fn (string $src) => array_values(array_filter($members, fn ($r) => $r->source === $src));
        $sncf = $by('sncf')[0] ?? null;
        $sncfV = $by('sncf_voyageurs')[0] ?? null;
        $osm = $by('osm');
        $osmActive = array_values(array_filter($osm, fn ($r) => $r->lifecycle === 'active'));
        // Élément Wikidata principal : celui qui porte l'UIC, sinon le premier
        // rattaché (les doublons éventuels restent des membres, sans leurs dates).
        $wds = $by('wikidata');
        usort($wds, fn ($a, $b) => [$b->uic_code !== null, $a->dist] <=> [$a->uic_code !== null, $b->dist]);
        $wd = $wds[0] ?? null;
        $ohm = $by('ohm');
        $manual = $by(ManualStations::SOURCE);
        $prov = [];

        // Position (fusion « position absorbed » : parmi les seuls objets de la fiche absorbée).
        $osmPos = $osmActive[0] ?? (array_values(array_filter($osm, fn ($r) => $r->lifecycle !== 'unknown'))[0] ?? ($osm[0] ?? null));
        $pos = self::positionOf(isset($c['position_members']) ? array_map(fn ($m) => $this->recs[$m], $c['position_members']) : $members);
        // Fusion : la position est celle que la correction a choisie, preuve à l'appui (pas « à vérifier »).
        $merged = $c['merged'] ?? false;
        $prov['geom'] = $pos->source;
        if (is_numeric($pos->props['precision_m'] ?? null)) {
            // Précision estimée de la position (m) et méthode : pk (PK calé sur le tracé) ou commune.
            $prov['geom_precision_m'] = (int) $pos->props['precision_m'];
            $prov['geom_method'] = $pos->props['geocoding'] ?? null;
        }
        $spread = 0;
        foreach ($members as $m) {
            $spread = max($spread, (int) round($this->haversine($pos->lat, $pos->lon, $m->lat, $m->lon)));
        }

        // Nom.
        // Un nom générique (« Ancienne gare », « Gare », « Halte » : nom normalisé vide) ne l'emporte jamais sur le
        // vrai nom d'une autre source (OSM w495279440 « Ancienne gare » et Wikidata Q106726094 « Saint-Geniès »).
        $nameRec = self::firstNamed([$sncf, $sncfV, ...$osmActive, $osmPos, $wd, ...$ohm, ...$members])
            ?? $sncf ?? $sncfV ?? ($osmActive[0] ?? $osmPos ?? $wd ?? ($ohm[0] ?? $members[0]));
        $name = $nameRec->name;
        if ($nameRec->source === 'wikidata' || ($nameRec->source !== 'sncf' && preg_match('/^(Ancienne )?(gare|halte)\b/iu', $name))) {
            $name = $this->stripPrefix($name);
        }
        $prov['name'] = $nameRec->source;
        $alt = [];
        foreach ($members as $m) {
            if ($m->source === 'osm' && in_array($m->lifecycle, ['unknown', 'historic'], true)) {
                continue; // nom du bâtiment (commerce, association…), pas de la gare
            }
            $n = $m->source === 'wikidata' || preg_match('/^(Ancienne )?(gare|halte)\b/iu', (string) $m->name) ? $this->stripPrefix((string) $m->name) : $m->name;
            if ($n && StationNames::normalize($n) !== null && StationNames::normalize($n) !== StationNames::normalize($name)) {
                $alt[StationNames::normalize($n)] ??= ['name' => $n, 'source' => $m->source]
                    + ($m->source === 'ohm' && ($m->opened_on || $m->closed_on) ? ['from' => substr((string) $m->opened_on, 0, 4) ?: null, 'to' => substr((string) $m->closed_on, 0, 4) ?: null] : []);
            }
        }

        // Type (halte / gare).
        $isHalt = ($osmActive && $osmActive[0]->kind === 'halte') || (! $osmActive && $wd?->kind === 'halte')
            || (! $osmActive && ! $wd && array_filter($osm, fn ($r) => $r->kind === 'halte'));
        $prov['type'] = $osmActive ? 'osm' : ($wd ? 'wikidata' : ($osm ? 'osm' : $members[0]->source));

        // Identifiants.
        $uic = $sncf?->uic_code ?? $sncfV?->uic_code ?? $wd?->uic_code ?? collect($osm)->pluck('uic_code')->filter()->first();
        if ($uic) {
            $prov['uic_code'] = $sncf ? 'sncf' : ($sncfV ? 'sncf_voyageurs' : ($wd?->uic_code ? 'wikidata' : 'osm'));
        }
        // Q-id d'un lien défait (action « unlink ») ignoré : tag posé sur l'objet d'une autre gare.
        $qid = $wd?->wikidata_id ?? collect($members)->pluck('qid')->filter(fn ($q) => $q && ! isset($c['unlinked'][$q]))->first();
        if ($qid) {
            $prov['wikidata_id'] = $wd ? 'wikidata' : (collect($members)->contains(fn ($r) => $r->linked && $r->qid === $qid) ? 'correction' : 'osm');
        }
        $osmNode = collect($osm)->first(fn ($r) => str_starts_with($r->source_ref, 'n'));

        // État actuel.
        [$status, $lifecycle, $statusSrc] = $this->currentState($sncf, $osm, $osmActive, $wd, $ohm, $members[0]->source, $sncfV, $manual);
        $prov['status'] = $statusSrc;

        // Événements par source + dates retenues.
        $events = [];
        $open = [];
        $close = [];
        $url = fn ($r) => match ($r->source) {
            'wikidata' => 'https://www.wikidata.org/wiki/'.$r->source_ref,
            'osm' => 'https://www.openstreetmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$r->source_ref[0]].'/'.substr($r->source_ref, 1),
            'ohm' => 'https://www.openhistoricalmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$r->source_ref[0]].'/'.substr($r->source_ref, 1),
            ManualStations::SOURCE => $r->props['source_url'] ?? null,
            default => null,
        };
        if ($wd) {
            $seenOpening = false;
            $lastWasClosure = false;
            foreach ($wd->events as $e) {
                if ((int) substr($e['date'], 0, 4) < 1827) {
                    continue;
                }
                $type = $e['type'];
                if ($type === 'opening') {
                    $type = $seenOpening ? 'reopening' : 'opening';
                    $seenOpening = true;
                }
                if ($type === 'reopening' && ! $seenOpening) {
                    $seenOpening = true;
                }
                $events[] = ['type' => $type, 'event_date' => $e['date'], 'date_precision' => $e['precision'], 'source' => 'wikidata',
                    'source_ref' => $wd->source_ref.'#'.$e['prop'], 'source_url' => $url($wd), 'note' => null];
                if ($type === 'opening') {
                    $open[] = [$e['date'], $e['precision'], 'wikidata'];
                }
            }
        }
        $ohmDated = array_values(array_filter($ohm, fn ($r) => $r->opened_on));
        if ($ohmDated) {
            usort($ohmDated, fn ($a, $b) => strcmp($a->opened_on, $b->opened_on));
            $first = $ohmDated[0];
            if ((int) substr($first->opened_on, 0, 4) >= 1827) {
                $events[] = ['type' => 'opening', 'event_date' => $first->opened_on, 'date_precision' => $first->opened_precision,
                    'source' => 'ohm', 'source_ref' => $first->source_ref, 'source_url' => $url($first), 'note' => null];
                $open[] = [$first->opened_on, $first->opened_precision, 'ohm'];
            }
            // Fermeture OHM : seulement si TOUS les objets OHM de la gare sont terminés
            // (une fin suivie d'un autre objet = changement de nom, pas une fermeture).
            if (collect($ohm)->every(fn ($r) => $r->closed_on)
                && self::keepsOhmClosure((string) collect($ohm)->max('closed_on'), $status === 'active' && in_array($statusSrc, ['sncf', 'sncf_voyageurs'], true), $events)) {
                $last = collect($ohm)->sortByDesc('closed_on')->first();
                $events[] = ['type' => 'closure', 'event_date' => $last->closed_on, 'date_precision' => $last->closed_precision,
                    'source' => 'ohm', 'source_ref' => $last->source_ref, 'source_url' => $url($last), 'note' => null];
                $close[] = [$last->closed_on, $last->closed_precision, 'ohm'];
            }
        }
        foreach ($osm as $r) {
            if ($r->opened_on && (int) substr($r->opened_on, 0, 4) >= 1827) {
                $events[] = ['type' => 'opening', 'event_date' => $r->opened_on, 'date_precision' => $r->opened_precision,
                    'source' => 'osm', 'source_ref' => $r->source_ref, 'source_url' => $url($r), 'note' => null];
                $open[] = [$r->opened_on, $r->opened_precision, 'osm'];
            }
            if ($r->closed_on && $r->lifecycle !== 'active') {
                $events[] = ['type' => 'closure', 'event_date' => $r->closed_on, 'date_precision' => $r->closed_precision,
                    'source' => 'osm', 'source_ref' => $r->source_ref, 'source_url' => $url($r), 'note' => null];
                $close[] = [$r->closed_on, $r->closed_precision, 'osm'];
            }
        }
        if ($wd && $wd->closed_on) {
            $close[] = [$wd->closed_on, $wd->closed_precision, 'wikidata'];
        }
        // Gare saisie à la main : dates et source citée (la citation et le motif restent dans station_sources).
        foreach ($manual as $r) {
            if ($r->opened_on) {
                $events[] = ['type' => 'opening', 'event_date' => $r->opened_on, 'date_precision' => $r->opened_precision,
                    'source' => ManualStations::SOURCE, 'source_ref' => $r->source_ref, 'source_url' => $url($r), 'note' => $r->props['quote'] ?? null];
                $open[] = [$r->opened_on, $r->opened_precision, ManualStations::SOURCE];
            }
            if ($r->closed_on) {
                $events[] = ['type' => 'closure', 'event_date' => $r->closed_on, 'date_precision' => $r->closed_precision,
                    'source' => ManualStations::SOURCE, 'source_ref' => $r->source_ref, 'source_url' => $url($r), 'note' => $r->props['quote'] ?? null];
                $close[] = [$r->closed_on, $r->closed_precision, ManualStations::SOURCE];
            }
        }

        $pick = function (array $cands) {
            $rank = ['day' => 3, 'month' => 2, 'year' => 1];
            $srcRank = [ManualStations::SOURCE => 4, 'wikidata' => 3, 'ohm' => 2, 'osm' => 1];
            usort($cands, fn ($a, $b) => [$rank[$b[1]] ?? 0, $srcRank[$b[2]] ?? 0] <=> [$rank[$a[1]] ?? 0, $srcRank[$a[2]] ?? 0]);

            return $cands[0] ?? null;
        };
        $o = $pick($open);
        // Fermeture DÉFINITIVE seulement si la gare n'existe plus aujourd'hui.
        $c = $status === 'gone' ? $pick($close) : null;
        if ($o) {
            $prov['opened_at'] = $o[2];
        }
        if ($c) {
            $prov['closed_at'] = $c[2];
        }

        return [
            'name' => mb_substr($name, 0, 255),
            'alt_names' => array_values($alt),
            'type' => $isHalt ? 'halte' : 'gare',
            'lon' => $pos->lon,
            'lat' => $pos->lat,
            'city' => null,
            'department_code' => null,
            'opened_at' => $o[0] ?? null,
            'opened_precision' => $o[1] ?? null,
            'closed_at' => $c[0] ?? null,
            'closed_precision' => $c[1] ?? null,
            // `status` affiché : plafonné ensuite par l'état des lignes (vff:build-station-eras).
            'status' => $status === 'gone' ? 'declassified' : $status,
            'source_status' => $status,
            'lifecycle' => $lifecycle,
            'uic_code' => $uic,
            'wikidata_id' => $qid,
            'osm_node_id' => $osmNode ? (int) substr($osmNode->source_ref, 1) : null,
            'provenance' => $prov,
            'position_source' => $pos->source,
            'position_spread_m' => $spread,
            'position_flagged' => $spread > 100 && ! $merged,
            'sources_count' => count($members),
            'events' => $events,
        ];
    }

    /**
     * Objet source qui donne la position d'une gare : SNCF > SNCF voyageurs > OSM (en service, puis désaffecté,
     * puis bâtiment) > Wikidata (celui qui porte l'UIC, puis le premier) > OHM > liste par ligne la plus précise
     * (lignes-oubliees, schémas Wikipédia, placées par vff:import-station-sources --source=pk) > premier objet.
     *
     * @param  non-empty-list<object>  $members
     */
    private static function positionOf(array $members): object
    {
        $by = fn (string $src) => array_values(array_filter($members, fn ($r) => $r->source === $src));
        $osm = $by('osm');
        $osmPos = array_values(array_filter($osm, fn ($r) => $r->lifecycle === 'active'))[0]
            ?? (array_values(array_filter($osm, fn ($r) => $r->lifecycle !== 'unknown'))[0] ?? ($osm[0] ?? null));
        $wds = $by('wikidata');
        usort($wds, fn ($a, $b) => [$b->uic_code !== null, $a->dist ?? 0] <=> [$a->uic_code !== null, $b->dist ?? 0]);
        $listed = array_values(array_filter($members, fn ($r) => is_numeric($r->props['precision_m'] ?? null)));
        usort($listed, fn ($a, $b) => (int) $a->props['precision_m'] <=> (int) $b->props['precision_m']);

        return $by('sncf')[0] ?? $by('sncf_voyageurs')[0] ?? $osmPos ?? $wds[0] ?? $by('ohm')[0] ?? $listed[0] ?? $members[0];
    }

    /**
     * État actuel : active | tourist | no_commercial | declassified (gare
     * existante mais fermée, ex. listée SNCF sans service) | gone (n'existe
     * plus en tant que gare).
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function currentState(?object $sncf, array $osm, array $osmActive, ?object $wd, array $ohm, string $fallbackSource, ?object $sncfV = null, array $manual = []): array
    {
        if ($sncfV && ! $sncf?->passenger) {
            // Gare de voyageurs SNCF (jeu gares-de-voyageurs) : ouverte aux voyageurs.
            return ['active', 'active', 'sncf_voyageurs'];
        }
        if ($sncf) {
            $tourist = $osmActive && $this->isTourist($osmActive[0]);
            if ($sncf->passenger) {
                return [$tourist ? 'tourist' : 'active', 'active', 'sncf'];
            }
            if ($sncf->freight) {
                return [$tourist ? 'tourist' : 'no_commercial', 'active', 'sncf'];
            }
            if ($tourist) {
                return ['tourist', 'active', 'osm'];
            }
            // Ni voyageurs ni fret SNCF, mais gare en service selon OSM, ou
            // gare RER en fonctionnement selon Wikidata : desservie par un autre
            // exploitant (RATP sur l'ex-ligne de Vincennes…). Le plafonnement par
            // les lignes (vff:build-station-eras) reste appliqué ensuite.
            if ($osmActive) {
                return ['active', 'active', 'osm'];
            }
            if ($wd && $wd->lifecycle === 'active' && in_array('Q1793804', $wd->props['p31'] ?? [], true)) {
                return ['active', 'active', 'wikidata'];
            }

            return ['declassified', $osm ? $this->worstOsm($osm) : 'disused', 'sncf'];
        }
        if ($osmActive) {
            return [$this->isTourist($osmActive[0]) ? 'tourist' : 'active', 'active', 'osm'];
        }
        if ($osm && array_filter($osm, fn ($r) => in_array($r->lifecycle, self::CLOSED_STATES, true))) {
            return ['gone', $this->worstOsm($osm), 'osm'];
        }
        if ($wd) {
            return match ($wd->lifecycle) {
                'active' => ['active', 'active', 'wikidata'],
                'unknown' => $wd->closed_on ? ['gone', 'disused', 'wikidata'] : ['active', 'unknown', 'wikidata'],
                'planned' => ['gone', 'planned', 'wikidata'],
                default => ['gone', $wd->lifecycle, 'wikidata'],
            };
        }
        if ($ohm && collect($ohm)->every(fn ($r) => $r->closed_on)) {
            return ['gone', 'historic', 'ohm'];
        }
        if ($fallbackSource === ManualStations::SOURCE) {
            // Gare saisie à la main : fermée si la source donne une fermeture, sinon en service.
            return array_filter($manual, fn ($r) => $r->closed_on) ? ['gone', 'historic', ManualStations::SOURCE] : ['active', 'active', ManualStations::SOURCE];
        }
        if ($osm) {
            // Bâtiment « train_station » seul, sans tag ferroviaire : ancienne gare probable.
            return ['gone', 'unknown', 'osm'];
        }

        // Gare connue seulement par une liste historique (lignes-oubliees…).
        return ['gone', 'unknown', $fallbackSource];
    }

    private function worstOsm(array $osm): string
    {
        foreach (['razed', 'abandoned', 'disused', 'historic'] as $s) {
            if (array_filter($osm, fn ($r) => $r->lifecycle === $s)) {
                return $s;
            }
        }

        return 'unknown';
    }

    private function isTourist(object $r): bool
    {
        $t = $r->props;

        return ($t['usage'] ?? null) === 'tourism' || ($t['tourism'] ?? null) === 'yes'
            || ($t['railway:preserved'] ?? null) === 'yes';
    }

    /**
     * Gares orphelines dont des objets sources ont rejoint une gare conservée (doublon rapproché, Grignan - Chamaret
     * 24778 et Chamaret-Grignan 23325) : chacune est fondue (StationMerger) dans la gare conservée qui a reçu le plus de
     * ses objets, la plus ancienne à égalité, avant sa suppression. Les fiches déjà fondues par l'action « merge »
     * (`absorbed_ids`) ne le sont pas deux fois.
     *
     * @param  array<int, array<string, mixed>>  $built  gare construite par groupe (`id` : fiche conservée, null : nouvelle)
     * @param  array<int, int>  $orphans  fiches existantes qu'aucun groupe ne garde
     * @param  array<int, string>  $existing  slug de chaque fiche existante
     */
    private function mergeJoinedOrphans(StationMerger $merger, array $built, array $orphans, array $existing): void
    {
        $orphanSet = array_flip($orphans);
        $absorbed = [];
        /** @var array<int, array<int, int>> $joined fiche orpheline => [fiche conservée => objets reçus] */
        $joined = [];
        foreach ($built as $cid => $s) {
            if (! $s['id']) {
                continue;
            }
            foreach ($this->clusters[$cid]['absorbed_ids'] ?? [] as $a) {
                $absorbed[(int) $a] = true;
            }
            foreach ($this->clusters[$cid]['members'] as $m) {
                $old = $this->recs[$m]->station_id;
                if ($old !== null && isset($orphanSet[(int) $old])) {
                    $joined[(int) $old][(int) $s['id']] = ($joined[(int) $old][(int) $s['id']] ?? 0) + 1;
                }
            }
        }
        foreach ($joined as $orphan => $targets) {
            if (isset($absorbed[$orphan])) {
                continue;
            }
            uksort($targets, fn (int $a, int $b): int => [$targets[$b], $a] <=> [$targets[$a], $b]);
            $kept = (int) array_key_first($targets);
            $moved = array_filter($merger->merge($orphan, $kept));
            $this->line(sprintf('  fiche %d (%s) rejointe par ses sources, fondue dans %d : %s', $orphan, $existing[$orphan] ?? '?', $kept,
                $moved === [] ? 'rien à reprendre' : implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($moved), $moved))));
        }
    }

    /**
     * Premier objet dont le nom n'est pas générique (nom normalisé non vide), dans l'ordre de priorité donné.
     *
     * @param  list<?object>  $candidates
     */
    private static function firstNamed(array $candidates): ?object
    {
        foreach ($candidates as $r) {
            if ($r !== null && StationNames::normalize((string) $r->name) !== null) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Slug tiré d'un nom générique (« ancienne-gare », « gare-24 », « halte-2 ») : la gare le quitte dès qu'elle a un
     * vrai nom, et l'ancienne adresse redirige.
     */
    public static function isGenericSlug(string $slug): bool
    {
        $base = preg_replace('/(-(\d{2,3}|2[ab]))?(-\d+)?$/', '', $slug);

        return $base !== '' && StationNames::normalize(str_replace('-', ' ', (string) $base)) === null;
    }

    private function stripPrefix(string $name): string
    {
        $n = preg_replace('/^(ancienne\s+)?(gare|halte)(\s+ferroviaire)?\s+(de\s+la\s+|de\s+l[\'’]|des\s+|du\s+|de\s+|d[\'’])/iu', '', trim($name));
        $n = preg_replace('/\s*\((gare|halte)[^)]*\)$/iu', '', $n);

        return Str::ucfirst(trim($n)) ?: $name;
    }

    private function report(): void
    {
        $r = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE sources_count > 1) AS multi,
                   COUNT(*) FILTER (WHERE position_flagged) AS flagged,
                   COUNT(opened_at) AS opened, COUNT(closed_at) AS closed,
                   COUNT(*) FILTER (WHERE status = 'active') AS active,
                   COUNT(*) FILTER (WHERE status = 'tourist') AS tourist,
                   COUNT(*) FILTER (WHERE status = 'no_commercial') AS freight,
                   COUNT(*) FILTER (WHERE status = 'declassified') AS declassified
            FROM stations
        SQL);
        $this->table(['gares', 'multi-sources', 'écart > 100 m', 'ouverture datée', 'fermeture datée', 'voyageurs', 'touristiques', 'fret seul', 'fermées / disparues'],
            [[(int) $r->total, (int) $r->multi, (int) $r->flagged, (int) $r->opened, (int) $r->closed, (int) $r->active, (int) $r->tourist, (int) $r->freight, (int) $r->declassified]]);
    }
}
