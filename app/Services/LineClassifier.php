<?php

namespace App\Services;

use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Classification déterministe du trafic (voyageurs/fret/mixte) par ligne et
 * PAR PORTION, à partir des sources SNCF officielles. Remplace l'heuristique
 * de proximité de gares de DeriveTrafficType (qui classait à tort 163 km de
 * LGV et 474 km de raccordements en « freight »).
 *
 * Sources (toutes locales après vff:import-sncf-refs) :
 *   - sncf_gares (fret O/N, voyageurs O/N par code_ligne) → LE discriminant
 *   - sncf_line_types (Rac/Vmère/Vport) → règle dure « jamais freight par défaut »
 *   - sncf_line_catlig (LGV) → règle dure « LGV = voyageurs »
 *   - sncf_line_geometries (statut des tronçons, PK) → portions exploitées
 *   - line_events récents (fermetures aux voyageurs depuis 2010)
 *   - line_segments.max_speed_kmh / lines.name → renforts LGV
 *
 * Règles dures (cf. docs/METHODE-CLASSIFICATION.md) :
 *   - LGV ⇒ passenger (jamais freight)
 *   - raccordement/Vmère ⇒ hérite des lignes voisines, jamais freight par défaut
 *   - Vport (voie portuaire) ⇒ freight (desserte portuaire assumée)
 *   - aucune source ⇒ 'unknown' (JAMAIS freight par défaut)
 *
 * Trafic par portion (2026-10-19) : voyageurs implique mixte, mais seulement sur
 * la portion desservie (entre deux gares voyageurs desservies, ordre des PK) ;
 * au-delà de la dernière gare voyageurs, fret seul si le code a des gares fret,
 * sinon le trafic daté par le moteur. Les drapeaux voyageurs périmés (gare sur un
 * tronçon neutralisé, fermeture aux voyageurs récente) sont écartés. Ordre de
 * priorité des signaux et détail : App\Services\PassengerPortions.
 *
 * Exploitation associative (2026-10-19) : un tronçon `is_heritage` (train touristique,
 * vélorail, musée) n'a pas de trafic commercial selon le moteur (EraTimeline ne pose
 * l'exploitation associative que là où voyageurs et fret ont cessé). Le classement SNCF
 * ne le requalifie JAMAIS (ni « mixte » d'une ligne hors RFN en service, ni portion
 * voyageurs, ni héritage d'un raccordement) : il garde le trafic du moteur. Une ligne dont
 * tous les tronçons en service sont associatifs n'est pas classée (lines.type = NULL).
 * Ex. 131000 Cernay - Sewen (Train Thur Doller Alsace), 795000 Bonson - Sembadel (Chemin
 * de fer du Haut-Forez), classés « mixte » jusque-là. Une exploitation qui n'est pas
 * associative (service saisonnier d'un exploitant commercial) se corrige à la source
 * (database/data/line_event_corrections.php, action neutralize).
 */
class LineClassifier
{
    private const ACTIVE = ['in_service', 'single_track'];

    public function __construct(private ?PassengerPortions $portions = null)
    {
        $this->portions ??= new PassengerPortions;
    }

    /**
     * @return array{lines:int,passenger:int,freight:int,mixed:int,unknown:int,raccordements:int,lgv:int,report:array<int,array<string,mixed>>,portions:array<string,mixed>}
     */
    public function classifyEra(int $eraId, bool $dryRun = false): array
    {
        // Signaux SNCF indexés par code_ligne
        $voyFret = [];
        foreach (DB::select('SELECT code_ligne, BOOL_OR(voyageurs) AS voy, BOOL_OR(fret) AS fret FROM sncf_gares GROUP BY code_ligne') as $r) {
            $voyFret[$r->code_ligne] = ['voy' => (bool) $r->voy, 'fret' => (bool) $r->fret];
        }
        $this->portions->load();
        // Gares de voyageurs SNCF posées sur le tracé d'une ligne d'un autre code (Grasse, 944000).
        $this->portions->adopt($eraId);
        $reopened = array_flip($this->portions->reopenedLines($eraId));
        // Ère passée : lignes fermées depuis la date de l'ère. Un code que SNCF Réseau n'exploite plus
        // aujourd'hui parce qu'il a fermé après l'ère (694000 Paulhan - Montpellier, 2024) n'est pas
        // « exploité par un autre opérateur » (vide pour l'ère courante).
        $closedSince = array_flip(array_map(fn ($r) => (int) $r->line_id, DB::select("SELECT DISTINCT e.line_id FROM line_events e
            WHERE e.type IN ('closure', 'closure_passenger') AND e.confidence IN ('high', 'medium')
              AND e.event_date > ".PassengerPortions::eraDateSql((string) $eraId).' AND e.event_date <= CURRENT_DATE')));

        $lgvCodes = array_flip(array_map(
            fn ($r) => $r->code_ligne,
            DB::select('SELECT DISTINCT code_ligne FROM sncf_line_catlig WHERE catlig ILIKE ?', [Lgv::CATLIG_PATTERN]),
        ));

        $typeSets = [];
        foreach (DB::select('SELECT code_ligne, ARRAY_AGG(DISTINCT type_ligne) AS types FROM sncf_line_types GROUP BY code_ligne') as $r) {
            // pg array "{Ligne,Rac}" → array PHP
            $typeSets[$r->code_ligne] = array_filter(explode(',', trim((string) $r->types, '{}')));
        }
        // Codes encore EXPLOITÉS par SNCF Réseau sur au moins un tronçon. Une ligne
        // déclassée du RFN mais en service aujourd'hui (RER A cédé à la RATP en 1969,
        // Corse, Provence…) est exploitée par un autre opérateur : ses gares ne sont pas
        // dans liste-des-gares SNCF, ce qui ne prouve PAS qu'elle soit « fret seul ».
        $sncfExploited = array_flip(array_map(
            fn ($r) => $r->code_ligne,
            DB::select("SELECT DISTINCT code_ligne FROM sncf_line_geometries WHERE libelle ILIKE 'Exploit%'"),
        ));

        // Codes qui sont une vraie « Ligne » RFN (pas seulement Rac/Vmère/Vport).
        $ligneCodes = array_flip(array_map(
            fn ($r) => $r->code_ligne,
            DB::select("SELECT DISTINCT code_ligne FROM sncf_line_types WHERE type_ligne = 'Ligne'"),
        ));

        // Lignes de l'ère avec leurs agrégats (vitesse/électrification/tension)
        $lines = DB::select(
            'SELECT l.id, l.code, l.name,
                    MAX(ls.max_speed_kmh) AS vmax,
                    BOOL_OR(ls.electrified) AS elec,
                    MODE() WITHIN GROUP (ORDER BY ls.voltage_v) FILTER (WHERE ls.electrified AND ls.voltage_v IS NOT NULL) AS volt,
                    COALESCE(BOOL_AND(ls.is_heritage) FILTER (WHERE ls.status IN (\'in_service\', \'single_track\')), false) AS heritage_only
             FROM lines l JOIN line_segments ls ON ls.line_id = l.id AND ls.era_id = ?
             GROUP BY l.id, l.code, l.name',
            [$eraId],
        );

        // Nature de chaque ligne (LGV, raccordement, voie portuaire, voie-mère).
        $nature = [];
        foreach ($lines as $l) {
            $code = (string) $l->code;
            if ($code === '') {
                continue;
            }
            $name = (string) $l->name;
            $types = $typeSets[$code] ?? [];
            $nature[$l->id] = [
                // Même définition que les tuiles de la carte (App\Support\Lgv).
                'lgv' => isset($lgvCodes[$code]) || Lgv::nameIsLgv($name) || (int) $l->vmax >= Lgv::MIN_SPEED_KMH,
                'rac' => (bool) preg_match('/^(raccordement|raccord|bifurcation)/i', $name)
                    || (! in_array('Ligne', $types, true) && (in_array('Rac', $types, true) || in_array('Vmère', $types, true))),
                'vport' => in_array('Vport', $types, true) && ! in_array('Ligne', $types, true),
                // Voie-mère = embranchement industriel : jamais LGV, même branchée près d'une LGV.
                'voie_mere' => preg_match('/^voie[- ]m[eè]re/iu', $name) || (in_array('Vmère', $types, true) && ! array_intersect(['Ligne', 'Rac'], $types)),
            ];
        }

        // Passe 0 : portions voyageurs des lignes qui ont des gares voyageurs desservies.
        $candidates = [];
        foreach ($lines as $l) {
            $n = $nature[$l->id] ?? null;
            if ($n && ! $n['lgv'] && ! $n['vport'] && ($this->portions->hasServedStation((string) $l->code) || isset($reopened[$l->id]))) {
                $candidates[] = (int) $l->id;
            }
        }
        $pieces = $this->portions->pieces($eraId, $candidates);
        $plans = [];        // line_id => plan (null : ligne entière)
        $estimates = [];    // line_id => [km in, km out]
        foreach ($lines as $l) {
            if (! in_array((int) $l->id, $candidates, true)) {
                continue;
            }
            $plan = $this->portions->plan((string) $l->code, $pieces[$l->id] ?? [], $eraId, (int) $l->id);
            $est = $plan === null ? [INF, 0.0] : $this->estimate($plan, $pieces[$l->id] ?? []);
            if ($est[0] > 0) {
                $plans[$l->id] = $plan;
                $estimates[$l->id] = $est;
            }
        }

        $aggAssign = [];   // line_id => [vmax, elec, volt]
        $assign = [];          // line_id => traffic_type final (ligne entière)
        $lineTypeAssign = [];   // line_id => lines.type (passengers|freight|mixed|null)
        $lgvAssign = [];        // line_id => bool is_lgv
        $confAssign = [];       // line_id => [confidence, signal, extra]
        $deferred = [];         // line_id => true (raccordements à hériter en passe 2)
        $voieMere = [];         // line_id => true (voies-mères : jamais LGV)
        $portionLines = [];     // line_id => plan (trafic écrit tronçon par tronçon)
        $heritageOnly = [];     // line_id : tous les tronçons en service sont associatifs (non classée)
        $stats = ['passenger' => 0, 'freight' => 0, 'mixed' => 0, 'unknown' => 0, 'raccordements' => 0, 'lgv' => 0];
        $report = [];
        $portionReport = [];

        foreach ($lines as $l) {
            $code = (string) $l->code;
            $name = (string) $l->name;
            if ($code === '') {
                // Ligne sans code SNCF (ligne disparue, réseau secondaire, associatif…) :
                // aucune donnée SNCF à appliquer, on garde le trafic daté par le moteur.
                continue;
            }
            if ($l->heritage_only) {
                // Exploitation associative seule : aucun trafic commercial à classer (cf. docblock).
                $heritageOnly[] = (int) $l->id;

                continue;
            }
            $vf = $voyFret[$code] ?? null;
            ['lgv' => $isLgv, 'rac' => $isRaccordement, 'vport' => $isVport, 'voie_mere' => $isVoieMere] = $nature[$l->id];
            if ($isVoieMere) {
                $voieMere[$l->id] = true;
            }
            if ($isLgv) {
                $stats['lgv']++;
            }
            $lgvAssign[$l->id] = $isLgv;
            $aggAssign[$l->id] = [$l->vmax, (bool) $l->elec, $l->volt];
            $stale = $this->portions->staleStations($code);
            $extra = $stale ? ['stale_stations' => array_slice($stale, 0, 20)] : [];

            // Décision (+ confiance + signal source pour audit/curation)
            $class = null;
            $conf = 'high';
            $signal = 'sncf';
            if ($isLgv) {
                $class = 'passenger';
                $signal = 'sncf-catlig-lgv';
            } elseif ($isVport) {
                $class = 'freight'; // voie portuaire = desserte fret assumée
                $signal = 'sncf-vport';
            } elseif (array_key_exists($l->id, $plans)) {
                // Règle métier : « si y'a du passager, y'a du fret » → mixte, sur la portion desservie.
                // Seules les LGV (cas ci-dessus) sont « voyageurs seuls ».
                $class = 'mixed';
                $signal = 'sncf-liste-des-gares';
                if (! $this->portions->hasServedStation($code)) {
                    $signal = 'reouverture-voyageurs-datee'; // aucune gare desservie : tronçons rouverts seulement
                }
                if ($adopted = $this->portions->adoptedStations($code)) {
                    $extra += ['gares_sur_trace' => array_slice($adopted, 0, 20)];
                }
                [$in, $out] = $estimates[$l->id];
                if ($plans[$l->id] !== null && (! $isRaccordement || $signal === 'reouverture-voyageurs-datee')) {
                    $portionLines[$l->id] = $plans[$l->id];
                    if ($out > 0) {
                        $signal .= '-portions';
                        $extra += ['mixed_km' => round($in / 1000, 1), 'outside_km' => round($out / 1000, 1)];
                        $portionReport[] = ['line_id' => $l->id, 'code' => $code, 'name' => $name,
                            'mixed_km' => round($in / 1000, 1), 'outside_km' => round($out / 1000, 1), 'stale' => $stale];
                    }
                }
            } elseif ($isRaccordement) {
                $deferred[$l->id] = true; // hérite en passe 2, jamais freight par défaut
                $stats['raccordements']++;
                $confAssign[$l->id] = ['medium', 'inherit-voisins', $extra];

                continue;
            } elseif (! isset($sncfExploited[$code]) && ! isset($closedSince[$l->id])) {
                // Hors RFN exploité mais en service (cf. $sncfExploited) : autre opérateur,
                // presque toujours des voyageurs (RER, Corse, Provence) → mixte, confiance basse.
                $class = 'mixed';
                $conf = 'low';
                $signal = 'hors-rfn-en-service';
                $report[] = ['line_id' => $l->id, 'code' => $code, 'name' => $name, 'reason' => 'Déclassée du RFN mais en service (autre opérateur) → mixte (confiance faible)'];
            } elseif ($vf && $vf['fret']) {
                $class = 'freight'; // fret-only avéré (gares fret, aucune portion voyageurs desservie)
                $signal = $vf['voy'] ? 'sncf-liste-des-gares-fret-voyageurs-hors-ligne' : 'sncf-liste-des-gares-fret';
            } elseif (isset($ligneCodes[$code])) {
                // Vraie « Ligne » RFN exploitée, non-LGV, non-raccordement, SANS
                // aucune gare voyageurs desservie → ligne de fret (cf. méthode).
                $class = 'freight';
                $conf = 'low';
                $signal = 'fallback-ligne-sans-gare';
                $report[] = ['line_id' => $l->id, 'code' => $code, 'name' => $name, 'reason' => 'Ligne RFN sans gare voyageurs desservie → fret (confiance faible)'];
            } else {
                $class = 'unknown'; // aucune source ni type → JAMAIS freight par défaut
                $conf = 'insufficient';
                $signal = 'aucun';
                $report[] = ['line_id' => $l->id, 'code' => $code, 'name' => $name, 'reason' => 'aucun signal SNCF (à curer)'];
            }

            $confAssign[$l->id] = [$conf, $signal, $extra];
            $assign[$l->id] = $class;
            $stats[$class]++;
            $lineTypeAssign[$l->id] = match ($class) {
                'passenger' => 'passenger',
                'freight' => 'freight',
                'mixed' => 'mixed',
                default => null,
            };
        }

        usort($portionReport, fn ($a, $b) => $b['outside_km'] <=> $a['outside_km']);
        $stats['portions'] = [
            'lines' => count($portionReport),
            'outside_km' => round(array_sum(array_column($portionReport, 'outside_km')), 1),
            'stale_stations' => array_sum(array_map(fn ($c) => count($this->portions->staleStations($c)),
                array_unique(array_map(fn ($l) => (string) $l->code, $lines)))),
            'split' => 0,
            'report' => $portionReport,
        ];

        if ($dryRun) {
            $stats['lines'] = count($lines);
            $stats['report'] = $report;
            $stats['deferred'] = count($deferred);

            return $stats;
        }

        $split = 0;
        DB::transaction(function () use ($eraId, $assign, $lineTypeAssign, $lgvAssign, $aggAssign, $confAssign, $portionLines, $pieces, $lines, $heritageOnly, &$split) {
            if ($heritageOnly) {
                DB::update('UPDATE lines SET type = NULL WHERE id = ANY(?::bigint[])', ['{'.implode(',', $heritageOnly).'}']);
            }

            // Drapeau LGV + agrégats ligne (vitesse/électrification/tension).
            // Les agrégats ne remplacent une valeur existante (Wikidata, saisie…)
            // que si les segments en apportent une : segments muets ≠ « non électrifiée ».
            foreach ($lgvAssign as $lineId => $isLgv) {
                [$vmax, $elec, $volt] = $aggAssign[$lineId] ?? [null, false, null];
                DB::update('UPDATE lines SET is_lgv=?, max_speed_kmh=COALESCE(?, max_speed_kmh),
                                   electrified=(electrified OR ?), voltage_v=COALESCE(?, voltage_v) WHERE id=?',
                    [$isLgv, $vmax, $elec, $volt, $lineId]);
            }

            // Audit : source 'sncf-classification' + confiance par ligne (curation).
            // On NE touche PAS aux sources curées manuellement (source_type='manual').
            foreach ($confAssign as $lineId => [$conf, $signal, $extra]) {
                DB::table('line_sources')->updateOrInsert(
                    ['line_id' => $lineId, 'source_type' => 'sncf-classification'],
                    ['source_url' => 'https://ressources.data.sncf.com/ (liste-des-gares, lignes-par-type, lignes-lgv, lignes-par-statut)',
                        'fields_provided' => json_encode(['traffic_type', 'is_lgv', 'signal' => $signal] + $extra, JSON_UNESCAPED_UNICODE),
                        'confidence' => $conf, 'fetched_at' => now(), 'updated_at' => now(), 'created_at' => now()],
                );
            }

            // Tronçons associatifs → trafic du moteur ('unknown' : pas de trafic commercial), même
            // si une classification antérieure les avait requalifiés (cf. docblock).
            DB::update("UPDATE line_segments SET traffic_type='unknown', updated_at=NOW()
                        WHERE era_id=? AND is_heritage AND traffic_type<>'unknown'", [$eraId]);

            // Segments inactifs → traffic_type 'unknown' (un segment fermé n'a pas de trafic)
            DB::update(
                "UPDATE line_segments SET traffic_type='unknown', updated_at=NOW()
                 WHERE era_id=? AND status NOT IN ('in_service','single_track') AND traffic_type<>'unknown'",
                [$eraId],
            );

            // Passe 1 : écrire le trafic des lignes décidées en entier (segments actifs)
            foreach ($assign as $lineId => $class) {
                if (! isset($portionLines[$lineId])) {
                    DB::update(
                        "UPDATE line_segments SET traffic_type=?, updated_at=NOW()
                         WHERE line_id=? AND era_id=? AND status IN ('in_service','single_track') AND NOT is_heritage",
                        [$class, $lineId, $eraId],
                    );
                }
                if (array_key_exists($lineId, $lineTypeAssign)) {
                    DB::update('UPDATE lines SET type=? WHERE id=?', [$lineTypeAssign[$lineId], $lineId]);
                }
            }

            // Passe 1 bis : lignes classées portion par portion. Coupe des tronçons aux gares
            // qui bornent une portion voyageurs, puis mixte dans la portion, hors portion fret
            // seul si le code a des gares fret, sinon trafic daté par le moteur.
            if ($portionLines) {
                // Tronçons de la passe 0 : rien ne les a modifiés depuis (géométrie, statut).
                $cuts = [];
                foreach ($portionLines as $lineId => $plan) {
                    $cuts += $this->portions->cuts($plan, $pieces[$lineId] ?? []);
                }
                $split = $this->portions->split($cuts);
                $after = $split ? $this->portions->pieces($eraId, array_keys($portionLines)) : $pieces;
                $codes = array_column(array_map(fn ($l) => ['id' => $l->id, 'code' => (string) $l->code], $lines), 'code', 'id');
                foreach ($portionLines as $lineId => $plan) {
                    $freight = $this->portions->hasFreightStation($codes[$lineId]);
                    $byClass = [];
                    foreach ($this->portions->sides($plan, $after[$lineId] ?? []) as $segmentId => $side) {
                        if ($side === 'in') {
                            $byClass['mixed'][] = $segmentId;
                        } elseif ($side === 'out' && $freight) {
                            $byClass['freight'][] = $segmentId;
                        }
                    }
                    foreach ($byClass as $class => $ids) {
                        DB::update('UPDATE line_segments SET traffic_type=?, updated_at=NOW() WHERE id = ANY(?::bigint[]) AND NOT is_heritage',
                            [$class, '{'.implode(',', $ids).'}']);
                    }
                    if (! isset($byClass['mixed']) && isset($byClass['freight'])) {
                        // Estimation trop optimiste (morceau fermé aux voyageurs) : aucune portion voyageurs.
                        DB::update("UPDATE lines SET type='freight' WHERE id=?", [$lineId]);
                    }
                }
            }
        });
        $stats['portions']['split'] = $split;

        // Passe 2 : héritage des raccordements depuis les voisins déjà classés (<60 m)
        $rstats = $this->resolveRaccordements($eraId, array_keys($deferred), array_keys(array_filter($lgvAssign)), $voieMere);
        $stats['passenger'] += $rstats['passenger'];
        $stats['freight'] += $rstats['freight'];
        $stats['mixed'] += $rstats['mixed'];
        $stats['unknown'] += $rstats['unknown'];

        $stats['lines'] = count($lines);
        $stats['report'] = $report;

        return $stats;
    }

    /**
     * Km dans / hors de la portion voyageurs, tronçons coupés aux gares bornes (estimation
     * sur le PK : sert à décider avant d'écrire, et au --dry-run).
     *
     * @return array{0: float, 1: float}
     */
    private function estimate(array $plan, array $pieces): array
    {
        $cuts = $this->portions->cuts($plan, $pieces);
        $virtual = [];
        foreach ($pieces as $p) {
            if (! isset($cuts[$p['id']])) {
                $virtual[] = $p;

                continue;
            }
            [$a, $b] = [min($p['m1'], $p['m2']), max($p['m1'], $p['m2'])];
            $bounds = array_merge([$a], array_map(fn ($s) => $s['m'], $cuts[$p['id']]), [$b]);
            sort($bounds);
            // Morceaux virtuels : la fermeture récente du milieu du tronçon entier ne vaut pas pour
            // chaque morceau (recalculée sur les vrais morceaux après la coupe).
            for ($k = 0; $k < count($bounds) - 1; $k++) {
                $virtual[] = ['id' => $p['id'] * 100 + $k, 'len' => $p['len'] * ($bounds[$k + 1] - $bounds[$k]) / max(1, $b - $a),
                    'm1' => $bounds[$k], 'm2' => $bounds[$k + 1], 'mm' => null, 'closed' => false] + $p;
            }
        }
        $in = $out = 0.0;
        $len = array_column($virtual, 'len', 'id');
        foreach ($this->portions->sides($plan, $virtual) as $id => $side) {
            match ($side) {
                'in' => $in += $len[$id],
                'out' => $out += $len[$id],
                default => null, // 'keep' : trafic du moteur
            };
        }

        return [$in, $out];
    }

    /**
     * Passe 2 : chaque raccordement hérite du trafic de ses voisins (segments
     * actifs d'autres lignes à <60 m). Jamais freight par défaut.
     *
     * Raccordement LGV (violet, voyageurs) : une EXTRÉMITÉ du raccordement est
     * branchée (<60 m) sur une ligne LGV de la passe 1, et cette LGV est la
     * ligne la plus proche de cette extrémité. Un raccordement qui CROISE une
     * LGV (pont, saut-de-mouton) ou longe une LGV n'en est pas un. On se fonde
     * sur les LGV de la passe 1 uniquement (pas d'effet domino d'un
     * raccordement à l'autre), et une voie-mère n'est jamais LGV.
     *
     * @param  array<int>  $lineIds
     * @param  array<int>  $lgvLineIds  lignes LGV de la passe 1
     * @param  array<int,bool>  $voieMere  line_id => voie-mère
     * @return array{passenger:int,freight:int,mixed:int,unknown:int}
     */
    private function resolveRaccordements(int $eraId, array $lineIds, array $lgvLineIds, array $voieMere): array
    {
        $out = ['passenger' => 0, 'freight' => 0, 'mixed' => 0, 'unknown' => 0];
        $lgv = array_flip($lgvLineIds);

        foreach ($lineIds as $lineId) {
            $tt = array_map(fn ($r) => $r->tt, DB::select(
                "SELECT DISTINCT ls2.traffic_type AS tt
                 FROM line_segments ls1
                 JOIN line_segments ls2
                   ON ls2.line_id <> ls1.line_id AND ls2.era_id = ls1.era_id
                  AND ls2.status IN ('in_service','single_track')
                  AND ls2.geom && ST_Expand(ls1.geom, 0.001)
                  AND ST_DWithin(ls1.geom::geography, ls2.geom::geography, 60)
                 WHERE ls1.line_id = ? AND ls1.era_id = ?",
                [$lineId, $eraId],
            ));

            // Ligne la plus proche de chaque extrémité du raccordement (bornes du tracé fusionné).
            $touchesLgv = false;
            if (! isset($voieMere[$lineId]) && $lgv) {
                $ends = DB::select(
                    "WITH ends AS (
                        SELECT (ST_Dump(ST_Boundary(ST_LineMerge(ST_Collect(geom))))).geom AS p
                        FROM line_segments WHERE line_id = ? AND era_id = ?
                     )
                     SELECT (SELECT ls2.line_id FROM line_segments ls2
                             WHERE ls2.line_id <> ? AND ls2.era_id = ?
                               AND ls2.status IN ('in_service','single_track')
                               AND ls2.geom && ST_Expand(ends.p, 0.001)
                               AND ST_DWithin(ends.p::geography, ls2.geom::geography, 60)
                             ORDER BY ST_Distance(ends.p::geography, ls2.geom::geography) LIMIT 1) AS nearest
                     FROM ends",
                    [$lineId, $eraId, $lineId, $eraId],
                );
                $touchesLgv = (bool) array_filter($ends, fn ($r) => $r->nearest !== null && isset($lgv[(int) $r->nearest]));
            }

            // « Voyageurs seuls » n'existe que pour la grande vitesse : un voisin
            // classique voyageurs (sans fret connu) donne quand même « mixte ».
            $class = match (true) {
                $touchesLgv => 'passenger',
                in_array('mixed', $tt, true), in_array('passenger', $tt, true) => 'mixed',
                in_array('freight', $tt, true) => 'freight',
                default => 'unknown',
            };

            DB::update(
                "UPDATE line_segments SET traffic_type=?, updated_at=NOW()
                 WHERE line_id=? AND era_id=? AND status IN ('in_service','single_track') AND NOT is_heritage",
                [$class, $lineId, $eraId],
            );
            DB::update('UPDATE lines SET type=?, is_lgv = (is_lgv OR ?) WHERE id=?', [
                match ($class) {
                    'passenger' => 'passenger', 'freight' => 'freight', 'mixed' => 'mixed', default => null
                },
                $touchesLgv,
                $lineId,
            ]);
            $out[$class]++;
        }

        return $out;
    }
}
