<?php

namespace App\Services\Materials;

use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Périodes de circulation des séries sur les lignes (`line_material_periods`), rattachées aux liens
 * ligne × matériel importés par `vff:import-materials` :
 *
 *   1. articles Wikipédia des LIGNES : phrases des sections « Matériel roulant », « Exploitation »… qui
 *      citent une série par un lien (MaterialPeriodExtractor) ;
 *   2. articles Wikipédia des SÉRIES : phrases des sections « Utilisation », « Services »… qui citent une
 *      ligne par un lien ;
 *   3. Wikidata : qualificatifs de début (P580) et de fin (P582) des déclarations P3438, avec leur précision.
 *
 * Les articles sont ceux que l'import du matériel a mis en cache (SourceCache, storage/app/data/materiel/,
 * mêmes révisions que les preuves des liens) : aucune requête vers Wikipédia ici. Wikidata : une requête,
 * gardée en cache, sauf hors ligne.
 *
 * Une période n'est gardée que pour un lien attesté. Fiabilité abaissée à `low` (jamais montrée) quand le
 * fait sort de la vie de la série (début avant sa mise en service, fin après son retrait), quand l'article
 * décrit plusieurs lignes de VFF à la fois ou quand la section parle d'essais, de records, de festivités,
 * d'entretien ou de préservation (WikipediaMaterialSections::uncertainSection()).
 */
final class MaterialPeriodCollector
{
    /** Titre de section qui nomme une portion (« Section de Saint-Just-en-Chaussée à Cambrai »). */
    private const SECTION_HEADING = '/^(?:section|tronçon|portion|partie)\s+(?:de\s+la\s+|de\s+l[’\']|du\s+|des\s+|de\s+|d[’\'])(.+?)\s+(?:à|au|aux)\s+(?:la\s+|l[’\'])?(.+)$|^(?:section|tronçon|portion|partie)\s+entre\s+(.+?)\s+et\s+(.+)$/iu';

    /** Vitesse à partir de laquelle une série citée par l'article d'une LGV y est rattachée (comme LineMaterialLinker). */
    private const LGV_MIN_SPEED_KMH = 220;

    private const RANK = ['high' => 3, 'medium' => 2, 'low' => 1];

    /** Précision Wikidata (wikibase:timePrecision) → précision VFF. */
    private const WIKIDATA_PRECISION = [11 => 'day', 10 => 'month', 9 => 'year'];

    public function __construct(
        private readonly SourceCache $cache,
        private readonly MaterialPeriodExtractor $extractor,
        private readonly ?WikidataMaterialSource $wikidata = null,
    ) {}

    /**
     * Périodes trouvées, par empreinte, et bilan de lecture.
     *
     * @return array{periods: array<string, array<string, mixed>>, stats: array<string, int>}
     */
    public function collect(bool $offline = false, ?\DateTimeInterface $today = null): array
    {
        $links = [];
        foreach (LineMaterial::query()->whereIn('source', LineMaterial::IMPORTED_SOURCES)->orderBy('id')->get(['id', 'line_id', 'material_id']) as $link) {
            $links["{$link->line_id}:{$link->material_id}"] ??= (int) $link->id;
        }
        $materials = DB::table('materials')->get(['id', 'wikidata_id', 'wikipedia_fr', 'type', 'max_speed_kmh', 'in_service_from', 'in_service_to'])->keyBy('id');
        $lines = DB::table('lines')->whereNotNull('wikipedia_title')->get(['id', 'wikipedia_title', 'is_lgv'])->keyBy('id');

        $materialsByTitle = $this->materialTitles($materials->pluck('wikipedia_fr', 'id')->filter()->all());
        $linesByTitle = $this->lineTitles($lines->pluck('wikipedia_title', 'id')->all());
        // Lignes qui partagent un article avec une autre ligne de VFF : le fait peut ne concerner que l'autre.
        $shared = [];
        foreach ($lines->groupBy(fn ($l): string => WikipediaMaterialSections::normalizeTitle((string) $l->wikipedia_title)) as $group) {
            if ($group->count() > 1) {
                foreach ($group as $l) {
                    $shared[(int) $l->id] = true;
                }
            }
        }

        $candidates = [];
        $stats = ['line_articles' => 0, 'material_articles' => 0, 'facts' => 0, 'unlinked' => 0, 'wikidata' => 0];

        // 1. Articles des lignes : les liens désignent des séries.
        foreach ($this->cache->load('wikipedia_lines') as $requested => $page) {
            $lineIds = $linesByTitle[(string) $requested] ?? [];
            if (! is_array($page) || $lineIds === []) {
                continue;
            }
            $stats['line_articles']++;
            foreach ($page['sections'] ?? [] as $section) {
                if (WikipediaMaterialSections::isDoubtfulSection($section['title'])) {
                    continue;
                }
                $facts = $this->extractor->extract(
                    $section['text'],
                    fn (string $target): array => array_values(array_unique($materialsByTitle[$target] ?? [])),
                    fn (string $target): bool => array_diff($linesByTitle[$target] ?? [], $lineIds) !== [],
                    MaterialPeriodExtractor::MODE_LINE_ARTICLE,
                    $today,
                );
                foreach ($facts as $fact) {
                    foreach ($lineIds as $lineId) {
                        $candidates[] = ['line_id' => $lineId, 'material_id' => $fact['id'], 'origin' => MaterialPeriodExtractor::MODE_LINE_ARTICLE, 'page' => $page, 'heading' => $section['title']] + $fact;
                    }
                }
            }
        }

        // 2. Articles des séries : les liens désignent des lignes.
        $fast = $materials->filter(fn ($m): bool => $m->type === 'rame_tgv' || (int) $m->max_speed_kmh >= self::LGV_MIN_SPEED_KMH)->keys()->flip()->all();
        $byArticle = $materials->filter(fn ($m): bool => $m->wikipedia_fr !== null)->pluck('id', 'wikipedia_fr')->all();
        foreach ($this->cache->load('wikipedia_materials') as $requested => $page) {
            $materialId = $byArticle[(string) $requested] ?? null;
            if (! is_array($page) || $materialId === null) {
                continue;
            }
            $stats['material_articles']++;
            foreach ($page['sections'] ?? [] as $section) {
                if (WikipediaMaterialSections::isDoubtfulSection($section['title'])) {
                    continue;
                }
                $facts = $this->extractor->extract(
                    $section['text'],
                    fn (string $target): array => array_values(array_unique($linesByTitle[$target] ?? [])),
                    fn (string $target): bool => array_diff($materialsByTitle[$target] ?? [], [(int) $materialId]) !== [],
                    MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE,
                    $today,
                );
                foreach ($facts as $fact) {
                    // Comme les liens : sur une LGV, seuls les engins de grande vitesse.
                    if ($lines->has($fact['id']) && (bool) $lines[$fact['id']]->is_lgv && ! isset($fast[(int) $materialId])) {
                        continue;
                    }
                    $candidates[] = ['line_id' => $fact['id'], 'material_id' => (int) $materialId, 'origin' => MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE, 'page' => $page, 'heading' => $section['title']] + $fact;
                }
            }
        }

        // 3. Wikidata : P3438 daté.
        if ($this->wikidata !== null) {
            $materialsByQid = $materials->filter(fn ($m): bool => $m->wikidata_id !== null)->pluck('id', 'wikidata_id')->all();
            $linesByQid = [];
            foreach (DB::table('wikidata_lines')->whereNotNull('line_id')->get(['qid', 'line_id']) as $row) {
                $linesByQid[$row->qid][] = (int) $row->line_id;
            }
            foreach ($this->wikidata->vehicleUsagePeriods(offline: $offline) as $usage) {
                $fact = self::wikidataFact($usage);
                if ($fact === null || ! isset($materialsByQid[$usage['material']])) {
                    continue;
                }
                foreach ($linesByQid[$usage['subject']] ?? [] as $lineId) {
                    $candidates[] = ['line_id' => $lineId, 'material_id' => (int) $materialsByQid[$usage['material']], 'origin' => 'wikidata', 'subject' => $usage['subject']] + $fact;
                    $stats['wikidata']++;
                }
            }
        }

        $periods = [];
        foreach ($candidates as $c) {
            $linkId = $links["{$c['line_id']}:{$c['material_id']}"] ?? null;
            if ($linkId === null) {
                $stats['unlinked']++;

                continue;
            }
            $row = $this->row($linkId, $c, $materials[$c['material_id']] ?? null, isset($shared[$c['line_id']]));
            $key = $row['fingerprint'];
            if (! isset($periods[$key]) || self::RANK[$row['confidence']] > self::RANK[$periods[$key]['confidence']]) {
                $periods[$key] = $row;
            }
        }
        $stats['facts'] = count($periods);

        return ['periods' => $periods, 'stats' => $stats];
    }

    /**
     * Remplace les périodes importées en base par celles-ci (table `line_material_periods` seulement) ; une
     * période dont l'empreinte n'a pas changé est gardée telle quelle. Les périodes d'une autre source (une
     * contribution validée, source `contrib`) ne sont jamais touchées.
     *
     * @param  array<string, array<string, mixed>>  $periods
     * @return array{created: int, updated: int, removed: int}
     */
    public function write(array $periods): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'removed' => 0];
        DB::transaction(function () use ($periods, &$stats): void {
            $existing = LineMaterialPeriod::query()->whereIn('source', LineMaterial::IMPORTED_SOURCES)->get()->keyBy('fingerprint');
            foreach ($periods as $fingerprint => $values) {
                $row = $existing->pull($fingerprint);
                if ($row === null) {
                    LineMaterialPeriod::query()->create($values);
                    $stats['created']++;

                    continue;
                }
                $row->fill($values);
                if ($row->isDirty()) {
                    $row->save();
                    $stats['updated']++;
                }
            }
            $stats['removed'] = $existing->isEmpty() ? 0 : LineMaterialPeriod::query()->whereIn('id', $existing->pluck('id'))->delete();
        });

        return $stats;
    }

    /**
     * Ce que write() changerait : empreintes nouvelles et périodes en base que les sources ne portent plus.
     *
     * @param  array<string, array<string, mixed>>  $periods
     * @return array{created: list<string>, removed: list<int>}
     */
    public static function diff(array $periods): array
    {
        $existing = LineMaterialPeriod::query()->whereIn('source', LineMaterial::IMPORTED_SOURCES)->pluck('id', 'fingerprint')->all();

        return [
            'created' => array_values(array_diff(array_map('strval', array_keys($periods)), array_map('strval', array_keys($existing)))),
            'removed' => array_values(array_map('intval', array_diff_key($existing, $periods))),
        ];
    }

    /**
     * Ligne de la table pour un fait.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function row(int $linkId, array $c, ?object $material, bool $sharedArticle): array
    {
        $confidence = $c['confidence'];
        // Article commun à plusieurs lignes, ou section d'essais, de records, de festivités (comme les liens).
        if ($c['origin'] !== 'wikidata' && ($sharedArticle || WikipediaMaterialSections::uncertainSection((string) $c['heading']) !== null)) {
            $confidence = 'low';
        }
        // Hors de la vie de la série : erreur de lecture probable (date d'une autre série, d'une autre action).
        $from = $material?->in_service_from !== null ? (int) $material->in_service_from : null;
        $to = $material?->in_service_to !== null ? (int) $material->in_service_to : null;
        $startYear = $c['from'] !== null ? (int) substr($c['from']['date'], 0, 4) : null;
        $endYear = $c['to'] !== null ? (int) substr($c['to']['date'], 0, 4) : null;
        if (($from !== null && $startYear !== null && $startYear < $from) || ($from !== null && $endYear !== null && $endYear < $from)
            || ($to !== null && $startYear !== null && $startYear > $to) || ($to !== null && $endYear !== null && $endYear > $to + 1)) {
            $confidence = 'low';
        }
        // Article de série : « mise en service en 1966 au dépôt de La Chapelle… » date souvent la série, pas sa venue sur la ligne.
        if ($c['origin'] === MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE && $c['to'] === null && $startYear !== null && $startYear === $from) {
            $confidence = 'low';
        }
        [$sectionFrom, $sectionTo] = $c['origin'] === MaterialPeriodExtractor::MODE_LINE_ARTICLE ? self::sectionOf((string) $c['heading']) : [null, null];

        $values = [
            'line_material_id' => $linkId,
            'start_date' => $c['from']['date'] ?? null,
            'start_precision' => $c['from']['precision'] ?? null,
            'end_date' => $c['to']['date'] ?? null,
            'end_precision' => $c['to']['precision'] ?? null,
            'section_from' => $sectionFrom,
            'section_to' => $sectionTo,
            'confidence' => $confidence,
            'origin' => $c['origin'],
        ];
        if ($c['origin'] === 'wikidata') {
            $values += [
                'source' => 'wikidata',
                'source_title' => $c['subject'],
                'source_revision' => null,
                'source_url' => 'https://www.wikidata.org/wiki/'.$c['subject'].'#P3438',
                'source_excerpt' => null,
            ];
        } else {
            $page = $c['page'];
            $values += [
                'source' => 'wikipedia',
                'source_title' => (string) $page['title'],
                'source_revision' => (int) $page['revid'],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', (string) $page['title'])).'&oldid='.(int) $page['revid'],
                'source_excerpt' => $c['excerpt'],
            ];
        }
        $values['fingerprint'] = sha1(implode('|', [$linkId, $values['start_date'], $values['end_date'], $sectionFrom, $sectionTo]));

        return $values;
    }

    /**
     * Fait tiré d'une déclaration Wikidata datée, ou null si aucune date n'est assez précise.
     *
     * @param  array{from: ?string, from_precision: ?int, to: ?string, to_precision: ?int}  $usage
     * @return array{from: ?array{date: string, precision: string}, to: ?array{date: string, precision: string}, confidence: string}|null
     */
    public static function wikidataFact(array $usage): ?array
    {
        $date = function (?string $value, ?int $precision): ?array {
            if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }
            $kind = self::WIKIDATA_PRECISION[$precision ?? 0] ?? 'year';

            return ['date' => match ($kind) {
                'year' => substr($value, 0, 4).'-01-01',
                'month' => substr($value, 0, 7).'-01',
                default => $value,
            }, 'precision' => $kind, 'vague' => ($precision ?? 0) < 9];
        };
        $from = $date($usage['from'], $usage['from_precision']);
        $to = $date($usage['to'], $usage['to_precision']);
        if (($from === null && $to === null) || ($from !== null && $to !== null && $from['date'] > $to['date'])) {
            return null;
        }
        $used = array_filter([$from, $to]);
        $confidence = match (true) {
            array_filter($used, fn (array $d): bool => $d['vague']) !== [] => 'low',
            array_filter($used, fn (array $d): bool => $d['precision'] === 'year') !== [] => 'medium',
            default => 'high',
        };
        $strip = fn (?array $d): ?array => $d === null ? null : ['date' => $d['date'], 'precision' => $d['precision']];

        return ['from' => $strip($from), 'to' => $strip($to), 'confidence' => $confidence];
    }

    /**
     * Portion nommée par un titre de section (« Matériel roulant > Section de X à Y »), sinon rien.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function sectionOf(string $heading): array
    {
        foreach (array_reverse(explode(' > ', $heading)) as $title) {
            if (preg_match(self::SECTION_HEADING, trim($title), $m)) {
                // Premier motif : groupes 1 et 2 ; second (« entre X et Y ») : groupes 3 et 4.
                $a = $m[1] !== '' ? $m[1] : $m[3];
                $b = $m[2] !== '' ? $m[2] : $m[4];

                return [trim($a), trim($b)];
            }
        }

        return [null, null];
    }

    /**
     * Titres d'articles (et redirections en cache) qui désignent chaque série.
     *
     * @param  array<int, string>  $titles  identifiant → titre de l'article
     * @return array<string, list<int>>
     */
    private function materialTitles(array $titles): array
    {
        $byTitle = [];
        foreach ($titles as $id => $title) {
            $byTitle[WikipediaMaterialSections::normalizeTitle($title)][] = (int) $id;
        }
        $ids = array_flip(array_map('strval', $titles));
        foreach ($this->cache->load('redirects_materials') as $title => $aliases) {
            foreach (is_array($aliases) && isset($ids[$title]) ? $aliases : [] as $alias) {
                $byTitle[WikipediaMaterialSections::normalizeTitle((string) $alias)][] = (int) $ids[$title];
            }
        }

        return $byTitle;
    }

    /**
     * Titres d'articles (titre enregistré, titre final, redirections en cache) qui désignent chaque ligne.
     *
     * @param  array<int, string>  $titles  identifiant → titre de l'article
     * @return array<string, list<int>>
     */
    private function lineTitles(array $titles): array
    {
        $byTitle = [];
        foreach ($titles as $id => $title) {
            $byTitle[WikipediaMaterialSections::normalizeTitle($title)][] = (int) $id;
        }
        $finals = [];
        foreach ($this->cache->load('wikipedia_lines') as $requested => $page) {
            if (is_array($page) && isset($byTitle[$requested])) {
                $byTitle[(string) $page['title']] = array_merge($byTitle[(string) $page['title']] ?? [], $byTitle[$requested]);
                $finals[(string) $page['title']] = $byTitle[$requested];
            }
        }
        foreach ($this->cache->load('redirects_lines') as $title => $aliases) {
            foreach (is_array($aliases) && isset($finals[$title]) ? $aliases : [] as $alias) {
                $byTitle[WikipediaMaterialSections::normalizeTitle((string) $alias)] = $finals[$title];
            }
        }

        return array_map(fn (array $ids): array => array_values(array_unique($ids)), $byTitle);
    }
}
