<?php

namespace App\Services\Signalling;

use App\Models\Line;
use App\Services\Api\Provenance;
use Illuminate\Support\Facades\DB;

/**
 * Mises en service de signalisation d'une ligne, datées, lues dans Wikipédia
 * (`vff:import-signalling-history`) : frise de la fiche ligne, `GET /api/v1/lines/{line}/signalling-history`,
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
    public const ATTRIBUTION = 'Faits relevés dans des articles de Wikipédia (auteurs de l\'article cité), CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/deed.fr)';

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * Faits de la ligne, du plus ancien au plus récent.
     *
     * @return list<array{
     *     system: string, role: string, date: string, date_precision: string, reliability: string, scope: string,
     *     section: array{from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int}|null,
     *     source: array{provider: string, title: string, url: string, revision: int, excerpt: string}
     * }>
     */
    public function facts(Line $line): array
    {
        return DB::table('line_signalling_events')->where('line_id', $line->id)->whereIn('confidence', self::SHOWN)
            ->orderBy('event_date')->orderBy('role')->orderBy('system')->orderBy('id')
            ->get(['system', 'role', 'event_date', 'date_precision', 'confidence', 'scope', 'section_from', 'section_to', 'pk_start_m', 'pk_end_m',
                'source', 'source_title', 'source_url', 'source_revision', 'source_excerpt'])
            ->map(fn (object $r): array => [
                'system' => $r->system,
                'role' => $r->role,
                'date' => (string) $r->event_date,
                'date_precision' => $r->date_precision,
                'reliability' => $r->confidence,
                'scope' => $r->scope,
                'section' => $r->scope === 'section' ? [
                    'from' => $r->section_from,
                    'to' => $r->section_to,
                    'pk_start_m' => $r->pk_start_m === null ? null : (int) $r->pk_start_m,
                    'pk_end_m' => $r->pk_end_m === null ? null : (int) $r->pk_end_m,
                ] : null,
                'source' => [
                    'provider' => $r->source,
                    'title' => $r->source_title,
                    'url' => $r->source_url,
                    'revision' => (int) $r->source_revision,
                    'excerpt' => $r->source_excerpt,
                ],
            ])->all();
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
