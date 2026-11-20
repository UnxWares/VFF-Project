<?php

namespace App\Services\TraceExport;

use App\Mcp\Support\Attribution;
use App\Services\Api\SourceRegistry;

/**
 * Légende d'un fichier de tracé (GPX, KML) : nom, résumé, mention de la base VFF et de chaque source
 * de géométrie utilisée avec sa licence, et ce qui n'a pas été exporté. Rédigée dans la langue
 * demandée (textes de `lang/{fr,en,de}/trace_export.php`, mentions des sources de `lang/{fr,en,de}/mcp.php`).
 */
final readonly class TraceCaption
{
    /**
     * @param  array<string, string>  $mentions  fournisseur de géométrie => mention (licence comprise)
     * @param  list<string>  $withheld  phrases sur les tronçons non exportés
     */
    public function __construct(
        public string $title,
        public string $summary,
        public string $url,
        public string $database,
        public string $licenseUrl,
        public string $notice,
        public array $mentions,
        public array $withheld,
        public string $lang,
    ) {}

    public static function make(LineTrace $trace, Attribution $attribution, string $lang): self
    {
        $line = $trace->line;
        $era = $trace->era->is_current
            ? trans('trace_export.era_current', [], $lang)
            : trans('trace_export.era_past', ['year' => $trace->era->year], $lang);
        $url = url('/lines/'.$line->slug);

        $withheld = [];
        foreach ($trace->withheld as $w) {
            $params = ['km' => self::km($w['length_m'], $lang), 'source' => $w['name'] ?? ''];
            $key = $w['reason'] === LineTrace::UNKNOWN_SOURCE ? 'withheld_unknown' : 'withheld_not_open';
            $withheld[] = trans_choice('trace_export.'.$key, $w['sections'], $params, $lang);
        }

        return new self(
            title: (string) trans('trace_export.title', ['line' => $line->name, 'era' => $era], $lang),
            summary: (string) trans('trace_export.summary', ['line' => $line->name, 'era' => $era, 'url' => $url], $lang),
            url: $url,
            database: $attribution->source('vff', $lang)['mention'],
            licenseUrl: SourceRegistry::DATABASE_LICENCE['url'],
            notice: (string) trans('mcp.attribution.notice', [], $lang),
            mentions: array_combine($trace->providers, array_map(fn (string $p): string => $attribution->source($p, $lang)['mention'], $trace->providers)),
            withheld: $withheld,
            lang: $lang,
        );
    }

    /** Étiquette d'un PK en mètres : 12345 => « 12+345 » (négatif : « -0+250 »). */
    public static function pkLabel(int $meters): string
    {
        $sign = $meters < 0 ? '-' : '';
        $abs = abs($meters);

        return sprintf('%s%d+%03d', $sign, intdiv($abs, 1000), $abs % 1000);
    }

    /** Longueur en kilomètres, une décimale, séparateur de la langue. */
    private static function km(int $meters, string $lang): string
    {
        return $lang === 'en'
            ? number_format($meters / 1000, 1, '.', ',')
            : number_format($meters / 1000, 1, ',', ' ');
    }

    /** @return list<string> mention de chaque source de géométrie utilisée */
    public function sources(): array
    {
        return array_values($this->mentions);
    }

    /** Deux-points de la langue (espace insécable avant en français). */
    private function colon(): string
    {
        return $this->lang === 'fr' ? "\u{a0}: " : ': ';
    }

    /** Description d'un tronçon (KML) : PK, état et source. */
    public function segmentText(TracePart $part): string
    {
        $range = $part->pkStartM !== null && $part->pkEndM !== null
            ? trans('trace_export.pk_range', ['from' => self::pkLabel($part->pkStartM), 'to' => self::pkLabel($part->pkEndM)], $this->lang)
            : trans('trace_export.pk_unknown', [], $this->lang);
        $items = [$range];
        if ($part->status !== null && trans()->has('maps.status.'.$part->status, $this->lang)) {
            $items[] = trans('trace_export.status', [], $this->lang).$this->colon().trans('maps.status.'.$part->status, [], $this->lang);
        }
        $items[] = trans('trace_export.source', [], $this->lang).$this->colon().($this->mentions[$part->provider] ?? $part->provider);

        return implode(' ; ', $items);
    }

    /** Texte long du fichier : résumé, base VFF, sources du tracé, tronçons non exportés. */
    public function description(): string
    {
        $parts = [$this->summary, $this->notice];
        $parts[] = trans('trace_export.sources_label', [], $this->lang).$this->colon().implode(' ; ', $this->sources()).'.';

        return implode("\n", [...$parts, ...$this->withheld]);
    }
}
