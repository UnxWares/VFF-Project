<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Fusion d'une ligne en double dans la ligne qui la représente vraiment, et
 * déplacement d'événements mal rattachés, SANS créer de doublon d'événement.
 *
 * Utilisé par vff:merge-split-lines (sections et doublons) et vff:link-traces
 * (ligne créée lors d'une exécution précédente, dont tous les tracés
 * appartiennent désormais à une ligne existante).
 *
 * Événements : un événement de la ligne fusionnée qui existe déjà sur la ligne
 * cible (même source, type, date, précision, portion) est SUPPRIMÉ ; les autres
 * sont déplacés. Wikipédia : l'empreinte (fingerprint) est recalculée pour la
 * ligne cible, comme le ferait vff:import-wikipedia-chronology, et la portion
 * est remise à résoudre (elle avait été résolue sur le mauvais tracé). Les
 * événements posés sur un tracé (source_trace_id : OHM, noms archeo…) gardent
 * leur géométrie, qui suit le tracé.
 *
 * Les voies vertes rattachées (line_greenways) suivent, sauf celles que la
 * ligne cible a déjà. Les tables dérivées (tronçons par ère, tracé de référence,
 * arrêts, arêtes…) partent avec la ligne supprimée (ON DELETE CASCADE) et sont
 * reconstruites pour la ligne cible par la chaîne (build-line-references,
 * build-era-segments…).
 */
class LineMerger
{
    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * Fusionne $from dans $into puis supprime $from.
     *
     * @param  string|null  $traceMethod  match_method imposé aux tracés déplacés (null : inchangé) ; une
     *                                    méthode hors LinkTraces::METHODS rend le rattachement définitif
     * @return array{traces: int, events_moved: int, events_dropped: int}
     */
    public function merge(int $from, int $into, ?string $traceMethod, string $reason): array
    {
        $old = DB::table('lines')->where('id', $from)->first(['id', 'name', 'slug', 'code', 'wikipedia_title']);
        if (! $old || $from === $into || ! DB::table('lines')->where('id', $into)->exists()) {
            return ['traces' => 0, 'events_moved' => 0, 'events_dropped' => 0];
        }

        // Les tracés d'autres sources rattachés par recouvrement (trace_overlap) gardent leur méthode :
        // leur passe vff:link-traces les recalcule et les suivra sur la ligne cible.
        $traces = $traceMethod
            ? DB::update("UPDATE source_traces SET line_id = ?, updated_at = NOW(),
                    match_method = CASE WHEN match_method = 'trace_overlap' THEN match_method ELSE ? END WHERE line_id = ?", [$into, $traceMethod, $from])
            : DB::update('UPDATE source_traces SET line_id = ?, updated_at = NOW() WHERE line_id = ?', [$into, $from]);
        DB::delete('DELETE FROM source_trace_lines WHERE line_id = ? AND source_trace_id IN (SELECT source_trace_id FROM source_trace_lines WHERE line_id = ?)', [$from, $into]);
        DB::update('UPDATE source_trace_lines SET line_id = ?, updated_at = NOW() WHERE line_id = ?', [$into, $from]);

        $ev = $this->moveEvents(DB::table('line_events')->where('line_id', $from)->pluck('id')->all(), $into);

        // Références externes : elles désignent la même ligne.
        DB::update('UPDATE wikidata_lines SET line_id = ?, updated_at = NOW() WHERE line_id = ?', [$into, $from]);
        DB::update('UPDATE source_stations SET line_id = ?, updated_at = NOW() WHERE line_id = ?', [$into, $from]);
        DB::update('UPDATE archeo_lines SET matched_line_id = ?, updated_at = NOW() WHERE matched_line_id = ?', [$into, $from]);
        DB::update('UPDATE line_sources s SET line_id = ?, updated_at = NOW() WHERE line_id = ?
            AND NOT EXISTS (SELECT 1 FROM line_sources t WHERE t.line_id = ? AND t.source_type = s.source_type)', [$into, $from, $into]);
        $type = 'App\\Models\\Line';
        DB::update('UPDATE media m SET mediable_id = ?, updated_at = NOW() WHERE mediable_type = ? AND mediable_id = ?
            AND NOT EXISTS (SELECT 1 FROM media o WHERE o.mediable_type = m.mediable_type AND o.mediable_id = ?
                            AND o.provider = m.provider AND o.external_id = m.external_id)', [$into, $type, $from, $into]);
        DB::delete('DELETE FROM media WHERE mediable_type = ? AND mediable_id = ?', [$type, $from]);
        // Voies vertes qui reprennent l'emprise (vff:link-greenways) : la même emprise, donc la ligne cible. Sans cela,
        // elles partaient avec la ligne supprimée (88 voies sur les trois sections de la nuit du 22/11/2026).
        DB::update('UPDATE line_greenways g SET line_id = ?, updated_at = NOW() WHERE line_id = ?
            AND NOT EXISTS (SELECT 1 FROM line_greenways o WHERE o.line_id = ? AND o.osm_way_id = g.osm_way_id)', [$into, $from, $into]);

        // Mémoire de la fusion (nom, slug, raison) : alias de recherche et redirection éventuelle.
        DB::update(
            "UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb)
                || jsonb_build_object('merged', COALESCE(alt_names->'merged', '[]'::jsonb)
                    || jsonb_build_array(jsonb_build_object('name', ?::text, 'slug', ?::text, 'reason', ?::text))),
                updated_at = NOW()
             WHERE id = ?",
            [$old->name, $old->slug, $reason, $into],
        );
        DB::delete('DELETE FROM lines WHERE id = ?', [$from]);

        return ['traces' => $traces] + $ev;
    }

    /**
     * Déplace des événements vers $into ; supprime ceux qui y existent déjà.
     *
     * @param  list<int>  $ids
     * @return array{events_moved: int, events_dropped: int}
     */
    public function moveEvents(array $ids, int $into): array
    {
        $moved = 0;
        $dropped = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('line_events')->whereIn('id', $chunk)->get() as $e) {
                if ((int) $e->line_id === $into) {
                    continue;
                }
                if ($e->source === 'wikipedia') {
                    $fp = $this->wikipediaFingerprint($into, $e);
                    $dup = DB::table('line_events')->where('fingerprint', $fp)->exists()
                        || $this->equivalentExists($e, $into);
                    if ($dup) {
                        DB::table('line_events')->where('id', $e->id)->delete();
                        $dropped++;

                        continue;
                    }
                    DB::table('line_events')->where('id', $e->id)->update([
                        'line_id' => $into, 'fingerprint' => $fp, 'updated_at' => now(),
                        'section_resolution' => $e->section_resolution === 'unresolved' ? 'unresolved' : 'pending',
                        'section_geom' => null, 'section_length_m' => null,
                        'section_resolution_note' => $e->section_resolution === 'unresolved' ? $e->section_resolution_note : null,
                    ]);
                    $moved++;

                    continue;
                }
                if ($this->equivalentExists($e, $into)) {
                    DB::table('line_events')->where('id', $e->id)->delete();
                    $dropped++;

                    continue;
                }
                $update = ['line_id' => $into, 'updated_at' => now()];
                if ($e->source_trace_id === null) {
                    // Portion résolue sur l'ancienne ligne : à recalculer sur le tracé de la ligne cible.
                    $update += ['section_resolution' => $e->section_resolution === 'unresolved' ? 'unresolved' : 'pending',
                        'section_geom' => null, 'section_length_m' => null];
                }
                DB::table('line_events')->where('id', $e->id)->update($update);
                $moved++;
            }
        }

        return ['events_moved' => $moved, 'events_dropped' => $dropped];
    }

    /** Même fait déjà présent sur la ligne cible (source, type, date, précision, portion). */
    private function equivalentExists(object $e, int $into): bool
    {
        $norm = fn ($s) => $this->parser->norm((string) $s);
        foreach (DB::table('line_events')->where('line_id', $into)->where('source', $e->source)
            ->where('type', $e->type)->where('event_date', $e->event_date)->where('date_precision', $e->date_precision)
            ->get(['section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'source_trace_id']) as $o) {
            if ($norm($o->section_from) === $norm($e->section_from) && $norm($o->section_to) === $norm($e->section_to)
                && $o->section_pk_start_m == $e->section_pk_start_m && $o->section_pk_end_m == $e->section_pk_end_m
                && ($e->source_trace_id === null || $o->source_trace_id == $e->source_trace_id)) {
                return true;
            }
        }

        return false;
    }

    /** Empreinte de vff:import-wikipedia-chronology (ligne + type + date + portion). */
    public function wikipediaFingerprint(int $lineId, object $e): string
    {
        return 'wikipedia:'.sha1(implode('|', [
            $lineId, $e->type, substr((string) $e->event_date, 0, 10), $e->date_precision,
            $this->parser->norm((string) $e->section_from), $this->parser->norm((string) $e->section_to),
            $e->section_pk_start_m, $e->section_pk_end_m,
        ]));
    }

    /** Titre d'article frwiki d'une URL (permalien index.php?title=…&oldid=… ou /wiki/…). */
    public static function wikipediaTitle(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('/[?&]title=([^&#]+)/', $url, $m) || preg_match('~/wiki/([^?#]+)~', $url, $m)) {
            return str_replace('_', ' ', rawurldecode($m[1]));
        }

        return null;
    }
}
