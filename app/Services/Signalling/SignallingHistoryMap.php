<?php

namespace App\Services\Signalling;

use App\Enums\SignallingFamily;
use App\Support\MapTiles;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Calque « Signalisation » de /maps aux ères passées : les mises en service datées (historique lu dans
 * Wikipédia, faits sûrs seulement) en vigueur à la fin de chaque ère (SignallingHistoryState), dessinées
 * dans `line_signalling_history_map`. L'ère courante n'est jamais remplie : elle garde l'état de SNCF Réseau
 * (SignallingMap, `line_signalling_map`), que l'historique ne remplace pas.
 *
 * Tracé d'un fait à une ère :
 *   - portion placée (`section_geom`, formes SNCF Réseau calées par PK) : la partie de cette portion qui suit
 *     le tracé en service de la ligne à cette ère (à 60 m près), `location = section` ; rien si la portion
 *     n'était pas en service ;
 *   - toute la ligne d'après l'article : le tracé en service de la ligne à cette ère, `location = line` ;
 *   - portion inconnue, ou nommée mais non placée : le même tracé, `location = unknown` (dessiné plus pâle,
 *     la bulle dit « ligne entière, section inconnue »).
 * Seuls le cantonnement et la signalisation en cabine sont dessinés ; le KVB (contrôle de vitesse) ne l'est pas.
 */
final class SignallingHistoryMap
{
    /** Statuts d'un tronçon en service (même lecture que le réseau de la carte). */
    private const IN_SERVICE = ['in_service', 'single_track', 'service_track'];

    /** Marge (m) autour du tracé de l'ère pour garder une portion placée. */
    private const CLIP_M = 60;

    private const TTL = 86_400;

    private const CACHED_MAX_ZOOM = 10;

    public function __construct(private readonly LineSignallingHistory $history) {}

    /**
     * Recalcule toute la table (après un import de l'historique) et vide le cache des tuiles.
     *
     * @return int nombre de tracés écrits
     */
    public function rebuild(): int
    {
        $eras = DB::table('eras')->where('is_current', false)->orderBy('year')->get(['id', 'year']);
        $lineIds = DB::table('line_signalling_events')->whereIn('confidence', LineSignallingHistory::SHOWN)
            ->whereIn('role', ['block', 'cab'])->distinct()->orderBy('line_id')->pluck('line_id')->map(fn ($id): int => (int) $id)->all();

        $written = DB::transaction(function () use ($eras, $lineIds): int {
            DB::table('line_signalling_history_map')->delete();
            $written = 0;
            foreach ($lineIds as $lineId) {
                $facts = array_values(array_filter($this->history->factsOf($lineId), fn (array $f): bool => in_array($f['role'], ['block', 'cab'], true)));
                foreach ($eras as $era) {
                    foreach (SignallingHistoryState::inForce($facts, SignallingHistoryState::until((int) $era->year, false)) as $fact) {
                        $written += $this->draw((int) $era->id, $lineId, $fact);
                    }
                }
            }

            return $written;
        });
        Cache::increment('signalling-history-map:version');

        return $written;
    }

    /**
     * Écrit le tracé d'un fait à une ère ; 0 si rien n'est dessiné (ligne absente ou fermée à cette ère).
     *
     * @param  array<string, mixed>  $fact
     */
    private function draw(int $eraId, int $lineId, array $fact): int
    {
        $family = SignallingFamily::fromCode((string) $fact['system'])?->value;
        if ($family === null) {
            return 0;
        }
        $placed = $fact['scope'] === 'section' && ($fact['section']['pk_start_m'] ?? null) !== null
            && DB::table('line_signalling_events')->where('id', $fact['id'])->whereNotNull('section_geom')->exists();
        $location = $placed ? 'section' : ($fact['scope'] === 'whole_line' ? 'line' : 'unknown');
        // Les faits les plus récents passent dessus ; une portion placée passe sur un tracé de ligne entière.
        $sort = ($placed ? 100_000_000 : 0) + (int) str_replace('-', '', (string) $fact['date']);
        $statuses = "'".implode("','", self::IN_SERVICE)."'";
        $geometry = $placed
            ? 'ST_Multi(ST_CollectionExtract(ST_Intersection(e.section_geom, ST_Transform(ST_Buffer(ST_Transform(t.g, 2154), '.self::CLIP_M.'), 4326)), 2))'
            : 'ST_Multi(t.g)';

        return DB::affectingStatement(<<<SQL
            INSERT INTO line_signalling_history_map (era_id, line_id, event_id, family, location, sort, geom, created_at, updated_at)
            SELECT :era, :line, e.id, :family, :location, :sort, {$geometry}, now(), now()
            FROM line_signalling_events e
            CROSS JOIN (
                SELECT ST_CollectionExtract(ST_Collect(geom), 2) AS g FROM line_segments
                WHERE line_id = :line2 AND era_id = :era2 AND status IN ({$statuses})
            ) t
            WHERE e.id = :event AND t.g IS NOT NULL AND NOT ST_IsEmpty(t.g)
              AND NOT ST_IsEmpty({$geometry})
            SQL, [
            'era' => $eraId, 'line' => $lineId, 'family' => $family, 'location' => $location, 'sort' => $sort,
            'line2' => $lineId, 'era2' => $eraId, 'event' => $fact['id'],
        ]);
    }

    public function tile(int $eraId, int $z, int $x, int $y): string
    {
        if ($z > self::CACHED_MAX_ZOOM) {
            return $this->compute($eraId, $z, $x, $y);
        }
        $version = (int) Cache::get('signalling-history-map:version', 0);
        $format = MapTiles::FORMAT;

        return Cache::remember("signalling-history-map:{$format}:{$version}:{$eraId}:{$z}:{$x}:{$y}", self::TTL, fn (): string => $this->compute($eraId, $z, $x, $y));
    }

    private function compute(int $eraId, int $z, int $x, int $y): string
    {
        $geom = MapTiles::displaySql('h.geom', $z);
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z2, :x2, :y2), 4326) AS env_4326
            ),
            mvt_layer AS (
                SELECT true AS history,
                       e.system AS signalling,
                       h.family,
                       e.role,
                       e.event_date::text AS date,
                       e.date_precision,
                       e.scope,
                       h.location,
                       e.section_from,
                       e.section_to,
                       e.located_pk_start_m AS pk_start_m,
                       e.located_pk_end_m AS pk_end_m,
                       e.origin,
                       e.source_title,
                       e.source_url,
                       h.sort,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       l.code AS line_code,
                       ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 256, true) AS geom
                FROM line_signalling_history_map h
                CROSS JOIN bounds
                JOIN line_signalling_events e ON e.id = h.event_id
                JOIN lines l ON l.id = h.line_id
                WHERE h.era_id = :era AND h.geom && bounds.env_4326
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-signalling') AS tile FROM mvt_layer WHERE geom IS NOT NULL
            SQL, ['z' => $z, 'x' => $x, 'y' => $y, 'z2' => $z, 'x2' => $x, 'y2' => $y, 'era' => $eraId]);

        return MvtBytes::from($result->tile ?? '');
    }
}
