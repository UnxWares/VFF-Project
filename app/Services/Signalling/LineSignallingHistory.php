<?php

namespace App\Services\Signalling;

use App\Models\Era;
use App\Models\Line;
use App\Services\Api\Provenance;
use Illuminate\Support\Facades\DB;

/**
 * Mises en service de signalisation d'une ligne, datées, lues dans Wikipédia
 * (`vff:import-signalling-history`) ou dans d'autres documents relevés à la main (SNCF Réseau, BEA-TT, EPSF,
 * Journal officiel, presse : `vff:import-signalling-sources`, `source.provider = manual`) : frise de la fiche ligne, `GET /api/v1/lines/{line}/signalling-history`,
 * champ GraphQL `Line.signalling_history`, fiche ligne de l'API et du MCP. Lecture seule.
 *
 * Seuls les faits sûrs sont montrés (`high` : jour ou mois précis ; `medium` : année). Les faits `low`
 * (date approximative, plusieurs dates, période) restent en base et ne sont jamais exposés.
 * Ces faits sont un historique : l'état actuel est `line_signalling` (SNCF Réseau), qu'ils ne remplacent pas.
 */
final class LineSignallingHistory
{
    /** Fiabilités montrées. */
    public const SHOWN = ['high', 'medium'];

    /** Mention à reproduire (CC BY-SA 4.0, section 3 : attribution, lien vers la licence). */
    public const ATTRIBUTION = 'Faits relevés dans des articles de Wikipédia (auteurs de l\'article cité), CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/deed.fr) ; '
        .'faits datés par d\'autres documents (source.provider = manual) : relevé de VFF, documents cités dans source.documents';

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * Faits de la ligne, du plus ancien au plus récent.
     *
     * `section` (portion nommée seulement) : lieux tels qu'écrits (`from`, `to`), `location` (`stations` :
     * placée par les gares d'extrémité trouvées sur la ligne ; `article_pk` : PK donnés par l'article ;
     * `unlocated` : lieux introuvables ou ambigus), PK de la portion placée (`pk_start_m`, `pk_end_m`, null
     * sinon) et gares retenues (`from_station`, `to_station`, avec leur PK sur la ligne).
     *
     * @return list<array{
     *     system: string, role: string, date: string, date_precision: string, reliability: string, scope: string, origin: string,
     *     section: array{from: ?string, to: ?string, location: string, pk_start_m: ?int, pk_end_m: ?int,
     *         from_station: array{slug: string, name: string, pk_m: ?int}|null, to_station: array{slug: string, name: string, pk_m: ?int}|null}|null,
     *     source: array{provider: string, title: string, url: string, revision: ?int, excerpt: string, verification: ?string,
     *         documents: list<array{publisher: string, title: string, url: string, published: ?string}>}
     * }>
     */
    public function facts(Line $line): array
    {
        return array_map(fn (array $f): array => array_diff_key($f, ['id' => true]), $this->factsOf($line->id));
    }

    /**
     * Mêmes faits, pour un identifiant de ligne, avec l'identifiant interne de chaque fait (`id`, pour la
     * carte par ère ; jamais exposé).
     *
     * @return list<array<string, mixed>>
     */
    public function factsOf(int $lineId): array
    {
        return $this->factsOfLines([$lineId])[$lineId] ?? [];
    }

    /**
     * Faits de plusieurs lignes en une requête (GraphQL, liste de lignes), sans l'identifiant interne.
     *
     * @param  list<int>  $lineIds
     * @return array<int, list<array<string, mixed>>> ligne → faits
     */
    public function publicFactsOfLines(array $lineIds): array
    {
        return array_map(fn (array $facts): array => array_map(fn (array $f): array => array_diff_key($f, ['id' => true]), $facts), $this->factsOfLines($lineIds));
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, list<array<string, mixed>>> ligne → faits (avec `id`)
     */
    private function factsOfLines(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }
        $station = fn (?string $slug, ?string $name, mixed $pk): ?array => $slug === null ? null
            : ['slug' => $slug, 'name' => (string) $name, 'pk_m' => $pk === null ? null : (int) $pk];

        return DB::table('line_signalling_events as e')
            ->leftJoin('stations as sf', 'sf.id', '=', 'e.from_station_id')
            ->leftJoin('stations as st', 'st.id', '=', 'e.to_station_id')
            ->leftJoin('station_lines as lf', fn ($j) => $j->on('lf.station_id', '=', 'e.from_station_id')->on('lf.line_id', '=', 'e.line_id'))
            ->leftJoin('station_lines as lt', fn ($j) => $j->on('lt.station_id', '=', 'e.to_station_id')->on('lt.line_id', '=', 'e.line_id'))
            ->whereIn('e.line_id', $lineIds)->whereIn('e.confidence', self::SHOWN)
            ->orderBy('e.event_date')->orderBy('e.role')->orderBy('e.system')->orderBy('e.id')
            ->get(['e.id', 'e.line_id', 'e.system', 'e.role', 'e.event_date', 'e.date_precision', 'e.confidence', 'e.scope', 'e.origin',
                'e.section_from', 'e.section_to', 'e.pk_start_m', 'e.pk_end_m', 'e.section_location', 'e.located_pk_start_m', 'e.located_pk_end_m',
                'sf.slug as from_slug', 'sf.name as from_name', 'lf.pk_m as from_pk', 'st.slug as to_slug', 'st.name as to_name', 'lt.pk_m as to_pk',
                'e.source', 'e.source_title', 'e.source_url', 'e.source_revision', 'e.source_excerpt', 'e.documents', 'e.verification'])
            ->groupBy('line_id')
            ->map(fn ($rows) => $rows->map(function (object $r) use ($station): array {
                $start = $r->located_pk_start_m ?? $r->pk_start_m;
                $end = $r->located_pk_end_m ?? $r->pk_end_m;

                return [
                    'id' => (int) $r->id,
                    'system' => $r->system,
                    'role' => $r->role,
                    'date' => (string) $r->event_date,
                    'date_precision' => $r->date_precision,
                    'reliability' => $r->confidence,
                    'scope' => $r->scope,
                    'origin' => $r->origin,
                    'section' => $r->scope === 'section' ? [
                        'from' => $r->section_from,
                        'to' => $r->section_to,
                        'location' => $r->section_location ?? ($start !== null ? 'article_pk' : 'unlocated'),
                        'pk_start_m' => $start === null ? null : (int) $start,
                        'pk_end_m' => $end === null ? null : (int) $end,
                        'from_station' => $station($r->from_slug, $r->from_name, $r->from_pk),
                        'to_station' => $station($r->to_slug, $r->to_name, $r->to_pk),
                    ] : null,
                    'source' => [
                        'provider' => $r->source,
                        'title' => $r->source_title,
                        'url' => $r->source_url,
                        'revision' => $r->source_revision === null ? null : (int) $r->source_revision,
                        'excerpt' => $r->source_excerpt,
                        'verification' => $r->verification,
                        'documents' => self::documents($r->documents),
                    ],
                ];
            })->values()->all())
            ->map(fn (array $facts): array => SignallingHistoryState::withoutRestatements($facts))->all();
    }

    /**
     * Documents d'un fait relevé à la main (colonne JSON), sans la marque « officiel » interne ; liste vide pour
     * un fait de Wikipédia.
     *
     * @return list<array{publisher: string, title: string, url: string, published: ?string}>
     */
    private static function documents(?string $json): array
    {
        $documents = $json === null ? [] : json_decode($json, true);

        return is_array($documents) ? array_values(array_map(fn (array $d): array => [
            'publisher' => (string) ($d['publisher'] ?? ''),
            'title' => (string) ($d['title'] ?? ''),
            'url' => (string) ($d['url'] ?? ''),
            'published' => isset($d['published']) ? (string) $d['published'] : null,
        ], array_filter($documents, 'is_array'))) : [];
    }

    /**
     * Faits en vigueur à la fin d'une ère (aujourd'hui pour l'ère courante) : ce que dessine la carte par ère
     * (SignallingHistoryState).
     *
     * @param  list<array<string, mixed>>|null  $facts  faits déjà lus (sinon lus ici)
     * @return list<array<string, mixed>>
     */
    public function inForce(Line $line, Era $era, ?array $facts = null): array
    {
        return SignallingHistoryState::inForce($facts ?? $this->facts($line), SignallingHistoryState::until((int) $era->year, (bool) $era->is_current));
    }

    /**
     * Frise de la fiche ligne : même contenu que l'API, sans la phrase source (les liens suffisent).
     *
     * @return list<array<string, mixed>>
     */
    public function forSheet(Line $line): array
    {
        return array_map(function (array $f): array {
            $f['source'] = array_diff_key($f['source'], ['excerpt' => true]);

            return $f;
        }, $this->facts($line));
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('line_signalling_events', 'wikipedia');
    }
}
