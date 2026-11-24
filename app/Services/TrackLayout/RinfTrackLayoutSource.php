<?php

namespace App\Services\TrackLayout;

use App\Models\Line;
use App\Services\Rinf\RinfDataset;
use Illuminate\Support\Facades\DB;

/**
 * Source `rinf` du schéma des voies : le RINF de l'ERA importé par vff:import-rinf (tables rinf_*).
 *
 * - Sections de la ligne (rinf_sections dont `line_code` = lines.code, liaisons de longueur nulle écartées) :
 *   leurs voies (voie 1, voie 2, voie unique, troisième voie…) de PK à PK, position exacte ; la vitesse de la
 *   bande est la plus forte vitesse déclarée sur les voies de la section ; les déclivités viennent du profil
 *   déclaré de la voie qui suit le sens de la section (voir RinfGradients).
 * - Points d'exploitation aux extrémités des sections, PK exact ; leurs voies (voies de gare, voies de service,
 *   liaisons) n'ont pas de PK dans le RINF français : centrées sur le PK du point, sur leur longueur déclarée
 *   (quai, voie de service), position `approximate`.
 * - Aiguillages et signaux : SNCF Réseau n'en déclare pas au RINF (aucun era:Switch ni era:Signal dans son
 *   graphe, mesure du 24/11/2026) ; les familles `switches` et `signals` ne sont donc jamais couvertes ici.
 */
final class RinfTrackLayoutSource implements TrackLayoutSource
{
    /** Longueur (m) donnée à une voie de point d'exploitation sans longueur déclarée. */
    public const DEFAULT_OP_TRACK_M = 300;

    /** Longueur (m) donnée à une liaison (crossover), dessinée autour du PK du point. */
    public const CROSSOVER_M = 60;

    public function key(): string
    {
        return 'rinf';
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        $code = (string) ($line->code ?? '');
        if ($code === '') {
            return TrackLayoutPart::empty();
        }
        $sections = DB::table('rinf_sections')->where('line_code', $code)->where('nature', 'regular')
            ->whereNotNull('pk_start_m')->whereNotNull('pk_end_m')
            ->orderBy('national_line')->orderBy('pk_start_m')->get();
        if ($sections->isEmpty()) {
            return TrackLayoutPart::empty();
        }

        // PK des points d'exploitation : extrémités des sections (première ligne nationale d'abord).
        $opPk = [];
        foreach ($sections as $s) {
            $opPk[$s->op_start_uopid] ??= (int) $s->pk_start_m;
            $opPk[$s->op_end_uopid] ??= (int) $s->pk_end_m;
        }
        $ops = DB::table('rinf_operational_points as o')->leftJoin('stations as s', 's.id', '=', 'o.station_id')
            ->whereIn('o.uopid', array_keys($opPk))->get(['o.uopid', 'o.name', 'o.kind', 'o.op_type', 's.slug as station_slug']);
        $points = [];
        $opKinds = [];
        foreach ($ops as $o) {
            $opKinds[$o->uopid] = (int) $o->op_type;
            $points[] = [
                'id' => 'rinf:op:'.$o->uopid, 'name' => $o->name, 'kind' => $o->kind, 'pk_m' => $opPk[$o->uopid],
                'uopid' => $o->uopid, 'station_slug' => $o->station_slug, 'rinf_type' => $o->op_type === null ? null : (int) $o->op_type,
                'source' => 'rinf', 'precision' => 'exact',
            ];
        }

        $tracks = [];
        $speeds = [];
        $gradients = [];
        $covered = [];
        $sectionTracks = DB::table('rinf_tracks')->whereIn('section_uri', $sections->pluck('uri'))->get()->groupBy('section_uri');
        foreach ($sections as $s) {
            $from = min((int) $s->pk_start_m, (int) $s->pk_end_m);
            $to = max((int) $s->pk_start_m, (int) $s->pk_end_m);
            if ($to <= $from) {
                continue;
            }
            $covered[] = [$from, $to];
            $increasing = (int) $s->pk_end_m >= (int) $s->pk_start_m;
            $max = null;
            $profile = null;
            foreach ($sectionTracks[$s->uri] ?? [] as $t) {
                if (strtoupper((string) $t->ref) === 'VLINK') {
                    continue;
                }
                $tracks[] = [
                    'id' => 'rinf:t:'.substr(md5((string) $t->uri), 0, 12), 'ref' => RinfDataset::shortTrackRef((string) $t->ref),
                    'rinf_ref' => $t->ref, 'kind' => 'running', 'pk_from_m' => $from, 'pk_to_m' => $to, 'op_id' => null,
                    'length_m' => $to - $from, 'electrified' => $t->electrified === null ? null : (bool) $t->electrified, 'platform' => false,
                    'direction' => RinfDataset::direction($t->direction, $increasing),
                    'max_speed_kmh' => $t->max_speed_kmh === null ? null : (int) $t->max_speed_kmh,
                    'source' => 'rinf', 'precision' => 'exact',
                ];
                if ($t->max_speed_kmh !== null) {
                    $max = max($max ?? 0, (int) $t->max_speed_kmh);
                }
                // Profil de la voie qui suit le sens de la section (sinon celle des deux sens).
                if ($t->gradient_profile !== null && ($profile === null || $t->direction === 'N')) {
                    $profile = (array) json_decode((string) $t->gradient_profile, true);
                }
            }
            if ($max !== null) {
                $speeds[] = ['pk_from_m' => $from, 'pk_to_m' => $to, 'kmh' => $max, 'source' => 'rinf'];
            }
            foreach ($profile ?? [] as $g) {
                $a = max($from, (int) $g['pk_from_m']);
                $b = min($to, (int) $g['pk_to_m']);
                if ($b > $a) {
                    $gradients[] = ['pk_from_m' => $a, 'pk_to_m' => $b, 'permille' => (float) $g['permille'], 'source' => 'rinf', 'precision' => 'derived'];
                }
            }
        }

        // Voies des points d'exploitation : centrées sur le PK du point, position approchée.
        $platforms = DB::table('rinf_platforms')->whereIn('op_uopid', array_keys($opPk))->get()->groupBy('track_uri');
        foreach (DB::table('rinf_tracks')->whereIn('op_uopid', array_keys($opPk))->orderBy('uri')->get() as $t) {
            $pk = $opPk[$t->op_uopid];
            $kind = RinfDataset::opTrackKind((string) $t->rinf_class, (string) $t->ref, $opKinds[$t->op_uopid] ?? null);
            $own = $platforms[$t->uri] ?? collect();
            $length = $kind === 'crossover' ? self::CROSSOVER_M
                : (int) ($t->length_m ?: ($own->max('length_m') ?: self::DEFAULT_OP_TRACK_M));
            $tracks[] = [
                'id' => 'rinf:t:'.substr(md5((string) $t->uri), 0, 12), 'ref' => RinfDataset::shortTrackRef((string) $t->ref),
                'rinf_ref' => $t->ref, 'kind' => $kind, 'pk_from_m' => $pk - intdiv($length, 2), 'pk_to_m' => $pk + $length - intdiv($length, 2),
                'op_id' => 'rinf:op:'.$t->op_uopid, 'length_m' => $t->length_m === null ? null : (int) $t->length_m,
                'electrified' => $t->electrified === null ? null : (bool) $t->electrified, 'platform' => $own->isNotEmpty(),
                'direction' => null, 'max_speed_kmh' => $t->max_speed_kmh === null ? null : (int) $t->max_speed_kmh,
                'source' => 'rinf', 'precision' => 'approximate',
            ];
        }

        $covered = TrackLayout::union($covered);

        return new TrackLayoutPart(
            source: RinfDataset::sourceMeta(
                (string) $sections->pluck('dataset')->unique()->implode(' ; '),
                ($fetched = $sections->max('fetched_at')) === null ? null : substr((string) $fetched, 0, 10),
            ),
            operationalPoints: $points,
            tracks: $tracks,
            speeds: $speeds,
            gradients: $gradients,
            covered: ['tracks' => $covered, 'operational_points' => $covered, 'crossings' => []],
        );
    }
}
