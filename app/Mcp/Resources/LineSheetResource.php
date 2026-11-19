<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\LineDossier;
use App\Mcp\Support\Lookup;
use Illuminate\Support\Facades\App;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('line-sheet')]
#[Title("Fiche d'une ligne (Markdown)")]
#[Description('Fiche d\'une ligne en Markdown, prête à citer : identité, longueurs, électrification, signalisation, phases datées et chronologie sourcée (50 événements au plus), lien de la fiche VFF et attributions. URI : vff://{lang}/lines/{slug}, lang = fr, en ou de, slug renvoyé par l\'outil search (ex. vff://fr/lines/rfn-420000).')]
#[MimeType('text/markdown')]
class LineSheetResource extends Resource implements HasUriTemplate
{
    /** Événements de la chronologie repris dans la fiche Markdown. */
    public const MAX_EVENTS = 50;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('vff://{lang}/lines/{slug}');
    }

    public function handle(Request $request, LineDossier $dossier, Attribution $attribution): Response
    {
        $lang = (string) $request->get('lang');
        $slug = (string) $request->get('slug');
        if (! in_array($lang, ['fr', 'en', 'de'], true) || preg_match('/^[a-z0-9-]{1,100}$/', $slug) !== 1) {
            return Response::error((string) trans('mcp.errors.invalid_uri'));
        }
        $previous = App::getLocale();
        App::setLocale($lang);
        try {
            $line = Lookup::line($slug);
            if ($line === null) {
                return Response::error((string) trans('mcp.errors.line_not_found', ['query' => $slug]));
            }
            $built = $dossier->build($line, $lang, true, self::MAX_EVENTS);
            $md = self::markdown($built['line'], $attribution->block($built['providers'], $lang), config('mcp.vff.public_url').'/lines/'.$line->slug);
            $max = (int) config('mcp.vff.max_response_bytes', 48_000);

            return Response::text(strlen($md) > $max ? mb_strcut($md, 0, $max)."\n\n".trans('mcp.truncated') : $md);
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * @param  array<string, mixed>  $l  fiche (LineDossier)
     * @param  array{notice: string, sources: list<array{mention: string}>}  $attribution
     */
    public static function markdown(array $l, array $attribution, string $url): string
    {
        $t = fn (string $k, array $p = []) => (string) trans('mcp.markdown.'.$k, $p);
        $km = fn ($v) => $v === null ? '?' : rtrim(rtrim(number_format((float) $v, 1, ',', "\u{202f}"), '0'), ',').' km';
        $out = ['# '.$l['name'], ''];
        $out[] = '- '.$t('code').' : '.($l['code'] ?? '-');
        $out[] = '- '.$t('length').' : '.$km($l['length']['trace_km']).' ('.$t('in_service_today').' : '.$km($l['length']['in_service_today_km']).')';
        if ($l['max_speed_kmh'] !== null) {
            $out[] = '- '.$t('max_speed').' : '.$l['max_speed_kmh'].' km/h';
        }
        if ($l['electrification'] !== null) {
            $volts = array_map(fn ($v) => $v['voltage_v'].' V ('.$km($v['km']).')', $l['electrification']['voltages'] ?? []);
            $out[] = '- '.$t('electrification').' : '.$l['electrification']['state_label'].($volts ? ', '.implode(', ', $volts) : '');
        }
        if ($l['signalling']['block_systems_km'] !== []) {
            $out[] = '- '.$t('signalling').' : '.implode(', ', array_map(fn ($s) => $s['code'].' ('.$km($s['km']).')', $l['signalling']['block_systems_km']));
        }
        foreach ($l['facts'] as $f) {
            $value = $f['km'] ?? $f['kmh'] ?? $f['mm'] ?? $f['n'] ?? $f['year'] ?? null;
            if (isset($f['list'])) {
                $value = implode(', ', array_map(fn ($x) => is_array($x) ? $x['name'] : $x, $f['list']));
            }
            if ($value !== null && in_array($f['key'], ['tracks', 'concession', 'regions', 'departements'], true)) {
                $out[] = '- '.$f['label'].' : '.$value.' ('.$f['note'].')';
            }
        }
        if ($l['phases'] !== []) {
            $out[] = '';
            $out[] = '## '.$t('phases');
            foreach ($l['phases'] as $ph) {
                $changes = array_map(fn ($c) => $c['from']['label'].' → '.$c['to']['label'].' ('.$km($c['length_km']).')'
                    .($c['source_url'] ? ' ['.$c['source'].']('.$c['source_url'].')' : ''), $ph['changes']);
                $out[] = '- **'.$ph['when'].'** : '.implode(' ; ', $changes);
            }
        }
        if (! empty($l['events'])) {
            $out[] = '';
            $out[] = '## '.$t('chronology');
            foreach ($l['events'] as $e) {
                $src = $e['source_url'] ? ' ['.($e['source'] ?? $t('source')).']('.$e['source_url'].')' : ($e['source'] ? ' ('.$e['source'].')' : '');
                $out[] = '- **'.$e['when'].'** : '.($e['text'] ?? $e['label']).$src;
            }
            if (isset($l['events_omitted'])) {
                $out[] = '- '.$t('more_events', ['n' => $l['events_omitted']]);
            }
        }
        $out[] = '';
        $out[] = '## '.$t('sources');
        $out[] = $attribution['notice'];
        foreach ($attribution['sources'] as $s) {
            $out[] = '- '.$s['mention'];
        }
        $out[] = '';
        $out[] = $t('sheet').' : '.$url;

        return implode("\n", $out)."\n";
    }
}
