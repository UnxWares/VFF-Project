<?php

namespace App\Mcp\Support;

use App\Services\LineChronicle;
use App\Support\EventText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Événements datés d'une ligne (line_events) pour le serveur MCP : lecture brute (un type ajouté
 * en base avant l'énumération PHP ne fait pas échouer l'outil, comme l'API REST) et mise en forme
 * compacte, chaque fait avec sa source, son lien et son fournisseur (licence).
 */
final class LineEvents
{
    public function __construct(private readonly Attribution $attribution) {}

    /**
     * @param  list<string>  $types
     */
    public function query(int $lineId, ?int $fromYear = null, ?int $toYear = null, array $types = []): Builder
    {
        return DB::table('line_events')
            ->where('line_id', $lineId)
            ->when($fromYear !== null, fn (Builder $q) => $q->where('event_date', '>=', sprintf('%04d-01-01', $fromYear)))
            ->when($toYear !== null, fn (Builder $q) => $q->where('event_date', '<=', sprintf('%04d-12-31', $toYear)))
            ->when($types !== [], fn (Builder $q) => $q->whereIn('type', $types))
            ->orderBy('event_date')->orderBy('id')
            ->select(['id', 'type', 'event_date', 'date_precision', 'description', 'section_from', 'section_to', 'source', 'source_url']);
    }

    /**
     * @return array{id: int, date: string, precision: string, when: string, type: string, label: string, section: ?string,
     *     text: ?string, text_lang?: string, source: ?string, source_url: ?string, provider: ?string}
     */
    public function format(object $e, string $lang): array
    {
        $date = substr((string) $e->event_date, 0, 10);
        $precision = in_array($e->date_precision, ['day', 'month', 'year'], true) ? $e->date_precision : 'day';
        $text = EventText::describe($e->source, $e->description === null ? null : LineChronicle::dashes($e->description), $lang,
            $e->section_from, $e->section_to);
        $labelKey = 'lines.event_types.'.$e->type;
        $section = $e->section_from !== null || $e->section_to !== null
            ? trim(($e->section_from ?? '?').' - '.($e->section_to ?? '?'))
            : null;

        return [
            'id' => (int) $e->id,
            'date' => $date,
            'precision' => $precision,
            'when' => EventText::isoDate($date, $precision, $lang),
            'type' => (string) $e->type,
            'label' => trans()->has($labelKey, $lang) ? (string) trans($labelKey, [], $lang) : (string) $e->type,
            'section' => $section,
            'text' => $text['text'],
        ] + ($text['lang'] !== null && $text['lang'] !== $lang ? ['text_lang' => $text['lang']] : []) + [
            'source' => $e->source,
            'source_url' => $e->source_url,
            'provider' => $this->attribution->providerFor('line_events', $e->source, $e->source_url),
        ];
    }
}
