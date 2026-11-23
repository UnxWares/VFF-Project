<?php

namespace App\Services;

use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use App\Support\EventText;
use App\Support\SourceRights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Ce jour-là » (page /on-this-day, `GET /api/v1/on-this-day`, outil MCP `on_this_day`) : pour un jour du
 * calendrier, ce qui s'est passé ce jour-là les années précédentes.
 *
 * Seuls des faits dont la date est connue AU JOUR sont servis : jamais une date à l'année ou au mois présentée
 * comme un jour. Chaque fait porte au moins une source publiable (licence ouverte établie, ou source dont les
 * faits se citent avec un lien : mêmes règles que la chronologie, NetworkChronology::isPublishable ; jamais une
 * source à usage non commercial). Faits rassemblés :
 *   - ouvertures, réouvertures, fermetures, électrifications et mises en service de signalisation des lignes :
 *     les faits de la chronologie (NetworkChronology, cache de `vff:compute-network-chronology`), au jour ;
 *   - ouvertures, réouvertures, fermetures et démolitions de gares : événements de gare (station_events) lus
 *     dans une source qui parle de la gare, jamais déduits d'une ligne ; pour l'ouverture et la fermeture, la
 *     date doit être celle que retient la fiche (au jour), pour les autres types aucune autre date au jour ne
 *     doit les contredire ;
 *   - accidents publiés et relus (la fiche « à vérifier » n'est pas servie), de date au jour, avec une source
 *     publiable : nom, lieu et bilan, sans récit ;
 *   - jalons nationaux (lois, création des entreprises du réseau) de la chronologie.
 *
 * Lecture seule en base. Le calcul d'un jour passe par un index des 366 jours, construit d'un coup et gardé
 * en cache jusqu'à la fin de la journée (la clé porte la date du jour : un fait daté d'aujourd'hui ne
 * s'annonce qu'à partir de ce jour-là).
 */
final class OnThisDay
{
    public const CACHE_PREFIX = 'on_this_day:v1:';

    /** Types de faits, dans l'ordre d'affichage d'une même année. */
    public const KINDS = ['milestone', 'opening', 'reopening', 'closure', 'electrification', 'signalling', 'demolition', 'accident'];

    /** Sujets d'un fait : ce que le fait concerne. */
    public const SUBJECTS = ['line', 'station', 'accident', 'milestone'];

    /** Types d'événements de gare servis → [type de fait, trafic]. */
    private const STATION_TYPES = [
        'opening' => ['opening', null],
        'reopening' => ['reopening', null],
        'closure' => ['closure', 'total'],
        'closure_passenger' => ['closure', 'passenger'],
        'closure_freight' => ['closure', 'freight'],
        'demolition' => ['demolition', null],
    ];

    /** Faits de la chronologie repris (le matériel n'est jamais daté au jour ; les accidents viennent de leur table). */
    private const CHRONOLOGY_KINDS = ['milestone', 'opening', 'reopening', 'closure', 'electrification', 'signalling'];

    public function __construct(private readonly NetworkChronology $chronology, private readonly SourceRights $rights) {}

    /**
     * Jour « MM-JJ » valide (29 février compris) : [mois, jour], ou null.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function parse(string $monthDay): ?array
    {
        if (! preg_match('/^(\d{2})-(\d{2})$/', $monthDay, $m)) {
            return null;
        }

        return checkdate((int) $m[1], (int) $m[2], 2024) ? [(int) $m[1], (int) $m[2]] : null;
    }

    public static function key(int $month, int $day): string
    {
        return sprintf('%02d-%02d', $month, $day);
    }

    /** Les 366 jours de l'année, dans l'ordre du calendrier. */
    public static function allDays(): array
    {
        $days = [];
        for ($m = 1; $m <= 12; $m++) {
            for ($d = 1; $d <= (int) date('t', (int) mktime(0, 0, 0, $m, 1, 2024)); $d++) {
                $days[] = self::key($m, $d);
            }
        }

        return $days;
    }

    /**
     * Faits d'un jour, triés par année, dans la langue.
     *
     * @param  list<string>  $types  types retenus (vide : tous)
     * @return array{day: string, count: int, facts: list<array<string, mixed>>}
     */
    public function day(int $month, int $day, string $locale, array $types = []): array
    {
        $key = self::key($month, $day);
        $raw = $this->rawDay($key);
        $out = [];
        foreach ($raw as $f) {
            if ($types !== [] && ! in_array($f['kind'], $types, true)) {
                continue;
            }
            $out[] = $this->present($f, $locale);
        }

        return ['day' => $key, 'count' => count($out), 'facts' => $out];
    }

    /**
     * Couverture de l'année : nombre de faits par jour, jours sans fait, jour le plus fourni.
     *
     * @return array{days: int, days_with_facts: int, days_without_facts: int, facts: int, by_day: array<string, int>, empty_days: list<string>, richest: array{day: string, count: int}}
     */
    public function coverage(): array
    {
        $this->ensureIndex();
        $coverage = Cache::get($this->prefix().'coverage');

        return is_array($coverage) ? $coverage : $this->buildIndex()['coverage'];
    }

    /**
     * Jour le plus proche avec au moins un fait, avant ou après (circulaire, 29 février compris) ; null si aucun.
     *
     * @return array{previous: ?string, next: ?string}
     */
    public function neighbours(string $key): array
    {
        $by = $this->coverage()['by_day'];
        $days = self::allDays();
        $i = array_search($key, $days, true);
        $n = count($days);
        $previous = $next = null;
        if ($i !== false) {
            for ($step = 1; $step < $n; $step++) {
                $next ??= ($by[$days[($i + $step) % $n]] ?? 0) > 0 ? $days[($i + $step) % $n] : null;
                $previous ??= ($by[$days[($i - $step + $n) % $n]] ?? 0) > 0 ? $days[($i - $step + $n) % $n] : null;
                if ($next !== null && $previous !== null) {
                    break;
                }
            }
        }

        return ['previous' => $previous, 'next' => $next];
    }

    /** Date du jour, au format de l'index (clé de cache incluse). */
    private function prefix(): string
    {
        return self::CACHE_PREFIX.now()->toDateString().':';
    }

    /** @return list<array<string, mixed>> */
    private function rawDay(string $key): array
    {
        $this->ensureIndex();
        $facts = Cache::get($this->prefix().'day:'.$key);

        return is_array($facts) ? $facts : ($this->buildIndex()['days'][$key] ?? []);
    }

    private function ensureIndex(): void
    {
        if (! Cache::has($this->prefix().'built')) {
            $this->buildIndex();
        }
    }

    /**
     * Construit l'index complet et le range jusqu'à la fin de la journée.
     *
     * @return array{days: array<string, list<array<string, mixed>>>, coverage: array<string, mixed>}
     */
    public function buildIndex(): array
    {
        $today = now()->toDateString();
        $days = array_fill_keys(self::allDays(), []);
        foreach ([...$this->lineFacts($today), ...$this->stationFacts($today), ...$this->accidentFacts($today)] as $f) {
            $days[substr($f['date'], 5)][] = $f;
        }
        $order = array_flip(self::KINDS);
        $by = [];
        $total = 0;
        foreach ($days as $key => &$list) {
            usort($list, fn (array $a, array $b): int => [$a['year'], $order[$a['kind']], $a['sort'], $a['id']] <=> [$b['year'], $order[$b['kind']], $b['sort'], $b['id']]);
            $by[$key] = count($list);
            $total += count($list);
        }
        unset($list);
        $withFacts = count(array_filter($by));
        arsort($by);
        $richest = (string) array_key_first($by);
        $by = array_replace(array_fill_keys(self::allDays(), 0), $by);
        $coverage = [
            'days' => count($by),
            'days_with_facts' => $withFacts,
            'days_without_facts' => count($by) - $withFacts,
            'facts' => $total,
            'by_day' => $by,
            'empty_days' => array_keys(array_filter($by, fn (int $c): bool => $c === 0)),
            'richest' => ['day' => $richest, 'count' => $by[$richest] ?? 0],
        ];

        $ttl = now()->endOfDay()->addSecond();
        foreach ($days as $key => $list) {
            Cache::put($this->prefix().'day:'.$key, $list, $ttl);
        }
        Cache::put($this->prefix().'coverage', $coverage, $ttl);
        Cache::put($this->prefix().'built', true, $ttl);

        return ['days' => $days, 'coverage' => $coverage];
    }

    /**
     * Faits des lignes (et jalons) datés au jour, repris de la chronologie.
     *
     * @return list<array<string, mixed>>
     */
    private function lineFacts(string $today): array
    {
        $meta = $this->chronology->meta();
        [$first, $last] = NetworkChronology::decadeBounds((int) $meta['last_year']);
        $out = [];
        for ($decade = $first; $decade <= $last; $decade += 10) {
            foreach ($this->chronology->decadeFacts($decade) as $f) {
                if ($f['precision'] !== 'day' || $f['date'] > $today || ! in_array($f['kind'], self::CHRONOLOGY_KINDS, true) || $f['sources'] === []) {
                    continue;
                }
                $out[] = ['id' => $f['id'], 'subject' => $f['kind'] === 'milestone' ? 'milestone' : 'line', 'kind' => $f['kind'],
                    'year' => $f['year'], 'date' => $f['date'], 'sort' => (string) ($f['line']['name'] ?? $f['key'] ?? ''), 'chronology' => $f];
            }
        }

        return $out;
    }

    /**
     * Faits des gares datés au jour (station_events), avec les règles de l'en-tête de classe.
     *
     * @return list<array<string, mixed>>
     */
    private function stationFacts(string $today): array
    {
        $rows = DB::table('station_events as e')->join('stations as s', 's.id', '=', 'e.station_id')
            ->whereIn('e.type', array_keys(self::STATION_TYPES))
            ->where('e.is_inferred', false)->where('e.date_precision', 'day')
            ->where('e.event_date', '<=', $today)
            ->orderBy('e.id')
            ->get(['e.id', 'e.station_id', 'e.type', 'e.event_date', 'e.source', 'e.source_url',
                's.slug', 's.name', 's.city', 's.wikidata_id', 's.opened_at', 's.opened_precision', 's.closed_at', 's.closed_precision']);

        // Dates distinctes (au jour, hors année seule d'OpenHistoricalMap) de chaque type d'événement d'une gare.
        $groups = [];
        foreach ($rows as $r) {
            $date = substr((string) $r->event_date, 0, 10);
            if (NetworkChronology::precision('day', $date, $r->source) !== 'day') {
                continue;
            }
            $groups[$r->station_id.'|'.$r->type][$date][] = $r;
        }

        $facts = [];
        foreach ($groups as $byDate) {
            $first = reset($byDate)[0];
            $type = (string) $first->type;
            // Ouverture et fermeture : la date retenue par la fiche, au jour. Autres types : une seule date au jour.
            if ($type === 'opening' || $type === 'closure') {
                $prefix = $type === 'opening' ? 'opened' : 'closed';
                $kept = ($first->{$prefix.'_precision'} ?? null) === 'day' ? substr((string) $first->{$prefix.'_at'}, 0, 10) : null;
                if ($kept === null || ! isset($byDate[$kept])) {
                    continue;
                }
                $byDate = [$kept => $byDate[$kept]];
            } elseif (count($byDate) !== 1) {
                continue;
            }
            $date = (string) array_key_first($byDate);
            $members = [];
            foreach ($byDate[$date] as $r) {
                $provider = $this->rights->providerFor('station_events', $r->source, $r->source_url);
                $url = $r->source_url ?? ($provider === 'wikidata' && $r->wikidata_id !== null ? 'https://www.wikidata.org/wiki/'.$r->wikidata_id : null);
                $members[] = ['provider' => $provider, 'url' => $url];
            }
            $sources = self::sources(array_values(array_filter($members, fn (array $m): bool => $this->chronology->isPublishable($m['provider']))));
            if ($sources === []) {
                continue;
            }
            [$kind, $traffic] = self::STATION_TYPES[$type];
            $facts[] = ['id' => 'g'.$byDate[$date][0]->id, 'subject' => 'station', 'kind' => $kind, 'traffic' => $traffic,
                'year' => (int) substr($date, 0, 4), 'date' => $date, 'sort' => (string) $first->name,
                'station' => ['slug' => (string) $first->slug, 'name' => (string) $first->name, 'city' => $first->city !== null ? (string) $first->city : null],
                'sources' => $sources];
        }

        return $facts;
    }

    /**
     * Accidents publiés, relus et datés au jour, avec une source publiable.
     *
     * @return list<array<string, mixed>>
     */
    private function accidentFacts(string $today): array
    {
        $accidents = Accident::query()->where('date_precision', 'day')->where('review_status', AccidentReviewStatus::Ok->value)
            ->whereDate('occurred_on', '<=', $today)
            ->toBase()->get(['id', 'slug', 'occurred_on', 'name', 'commune_name', 'deaths']);
        $sources = [];
        foreach (DB::table('accident_sources')->whereIn('accident_id', $accidents->pluck('id'))
            ->orderByRaw("CASE role WHEN 'article' THEN 0 WHEN 'report' THEN 1 ELSE 2 END")->orderBy('id')
            ->get(['accident_id', 'provider', 'url']) as $s) {
            $sources[(int) $s->accident_id][] = ['provider' => (string) $s->provider, 'url' => $s->url];
        }
        $facts = [];
        foreach ($accidents as $a) {
            $kept = self::sources(array_values(array_filter($sources[(int) $a->id] ?? [], fn (array $s): bool => $this->chronology->isPublishable($s['provider']))));
            if ($kept === []) {
                continue;
            }
            $date = substr((string) $a->occurred_on, 0, 10);
            $names = array_filter((array) json_decode((string) $a->name, true), 'is_string');
            $facts[] = ['id' => 'a'.$a->id, 'subject' => 'accident', 'kind' => 'accident', 'year' => (int) substr($date, 0, 4), 'date' => $date,
                'sort' => (string) ($names['fr'] ?? reset($names) ?: $a->slug), 'names' => $names, 'slug' => (string) $a->slug,
                'commune' => $a->commune_name !== null ? (string) $a->commune_name : null,
                'deaths' => $a->deaths === null ? null : (int) $a->deaths, 'sources' => $kept];
        }

        return $facts;
    }

    /**
     * Fait rédigé dans la langue.
     *
     * @param  array<string, mixed>  $f  fait de l'index
     * @return array<string, mixed>
     */
    private function present(array $f, string $locale): array
    {
        $base = ['id' => $f['id'], 'subject' => $f['subject'], 'kind' => $f['kind'], 'traffic' => null, 'year' => $f['year'],
            'years_ago' => max(0, (int) now()->year - $f['year']), 'date' => $f['date'],
            'when' => EventText::isoDate($f['date'], 'day', $locale),
            'title' => null, 'system' => null, 'detail' => null, 'text' => null, 'deaths' => null, 'path' => null, 'line' => null, 'station' => null,
            'sources' => []];

        if ($f['subject'] === 'station') {
            return array_replace($base, ['traffic' => $f['traffic'], 'title' => $f['station']['name'], 'detail' => $f['station']['city'],
                'path' => '/stations/'.$f['station']['slug'], 'station' => $f['station'] + ['path' => '/stations/'.$f['station']['slug']],
                'sources' => $this->named($f['sources'], $locale)]);
        }
        if ($f['subject'] === 'accident') {
            return array_replace($base, ['title' => $f['names'][$locale] ?? $f['names']['fr'] ?? $f['slug'], 'detail' => $f['commune'],
                'deaths' => $f['deaths'], 'path' => '/accidents/'.$f['slug'], 'sources' => $this->named($f['sources'], $locale)]);
        }
        $p = $this->chronology->present([$f['chronology']], [], null, $locale)[0];
        $sections = array_map(fn (array $s): string => $s['text'], $p['sections']);

        return array_replace($base, [
            'traffic' => $p['traffic'],
            'title' => $f['subject'] === 'milestone' ? $p['title'] : $p['line']['name'],
            'system' => $f['kind'] === 'signalling' ? $p['title'] : null,
            'detail' => $sections === [] ? null : implode(', ', $sections),
            'text' => $p['text'],
            'path' => $p['path'],
            'line' => $p['line'],
            'sources' => array_map(fn (array $s): array => ['provider' => $s['provider'], 'name' => $s['name'], 'url' => $s['url']], $p['sources']),
        ]);
    }

    /**
     * @param  list<array{provider: string, url: ?string}>  $sources
     * @return list<array{provider: string, name: string, url: ?string}>
     */
    private function named(array $sources, string $locale): array
    {
        return array_map(fn (array $s): array => ['provider' => $s['provider'], 'name' => $this->chronology->sourceName($s['provider'], $locale), 'url' => $s['url']], $sources);
    }

    /**
     * Une source par fournisseur, avec le premier lien cité.
     *
     * @param  list<array{provider: ?string, url: ?string}>  $members
     * @return list<array{provider: string, url: ?string}>
     */
    private static function sources(array $members): array
    {
        $out = [];
        foreach ($members as $m) {
            $p = (string) $m['provider'];
            if (! isset($out[$p]) || ($out[$p]['url'] === null && ($m['url'] ?? null) !== null)) {
                $out[$p] = ['provider' => $p, 'url' => $m['url'] ?? null];
            }
        }

        return array_values($out);
    }
}
