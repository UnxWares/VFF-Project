<?php

namespace App\Services\Materials;

use App\Models\LineMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liens matériel ↔ ligne (`line_materials`), créés seulement quand une source le dit :
 *
 *   1. Wikidata : déclaration « véhicule habituellement utilisé » (P3438) d'une ligne reliée à
 *      VFF (table wikidata_lines), vers une série importée ;
 *   2. article Wikipédia d'une LIGNE (lines.wikipedia_title) : lien vers l'article d'une série
 *      dans une section « Matériel roulant », « Exploitation », « Desserte »… ;
 *   3. article Wikipédia d'une SÉRIE (materials.wikipedia_fr) : lien vers l'article d'une ligne
 *      dans une section « Utilisation », « Services », « Carrière »… ;
 *   4. phrase relue à la main que la lecture automatique ne prend pas (LineMaterialSupplements,
 *      database/data/line_material_supplements.php).
 *
 * Une phrase qui nomme une version d'une famille de séries revient à cette version, même quand son lien
 * pointe vers l'article commun (« [[Regio 2N|Omneo Premium]] (Z 56600) » : Z 56600, MaterialFamilies).
 *
 * Chaque lien garde ses preuves (`evidence` : article, révision, section, phrase citée,
 * permalien) ; un seul lien par couple ligne × série, toutes preuves réunies. Aucune période
 * n'est déduite du texte : `from_date` / `to_date` restent vides.
 *
 * Confiance : `high` pour Wikidata ou une section « Matériel » d'un article de ligne, `medium`
 * pour les autres sections et pour une phrase qui parle du remplacement d'un autre matériel
 * (« Ce train a été remplacé par le TGV » : la série citée n'est pas le sujet de la phrase),
 * `low` quand l'article décrit plusieurs lignes de VFF à la fois (le matériel n'a peut-être
 * parcouru que l'une d'elles) ou quand la preuve porte un doute (`doubt` : commande, essai,
 * circulation exceptionnelle, présentation, préservation, possibilité, acheminement vers un
 * atelier, détournement, restriction à une autre portion, pièce reprise, note de bas de page,
 * section d'essais ou de records ; voir WikipediaMaterialSections::doubt()). La preuve la plus
 * forte décide : une seule preuve sans doute suffit à garder `high` ou `medium`.
 *
 * Écartés, dans cet ordre (les liens écartés restent lisibles par rejected() pour le bilan de l'import) :
 *   1. les faux positifs relus à la main, phrase par phrase ou couple entier (LineMaterialExclusions,
 *      database/data/line_material_exclusions.php) ;
 *   2. une série rattachée à une ligne d'un autre écartement (autorail à voie normale sur une ligne
 *      métrique), sauf source explicite (MaterialGaugeRule) ;
 *   3. une série électrique rattachée à une ligne jamais électrifiée dans un de ses systèmes pendant
 *      son service (locomotive à 1 500 V continu seul sur une ligne en 25 kV), sauf source explicite
 *      (MaterialTractionRule).
 */
final class LineMaterialLinker
{
    public const KIND_WIKIDATA = 'wikidata';

    public const KIND_LINE_ARTICLE = 'wikipedia_line_article';

    public const KIND_MATERIAL_ARTICLE = 'wikipedia_material_article';

    private const MAX_EVIDENCE = 5;

    /** Vitesse à partir de laquelle une série citée par l'article d'une LGV est reliée à celle-ci. */
    private const LGV_MIN_SPEED_KMH = 220;

    /** Titre d'une section consacrée au matériel : preuve la plus directe dans un article de ligne. */
    private const MATERIAL_HEADING = '/^mat[ée]riels?\b/iu';

    /** @var array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>, reason: string}> */
    private array $rejected = [];

    /** @var list<string> exclusions et exceptions sans effet à la dernière collecte */
    private array $unresolved = [];

    public function __construct(
        private readonly WikidataMaterialSource $wikidata,
        private readonly WikipediaArticleSource $wikipedia,
        private readonly ?MaterialGaugeRule $gauges = null,
        private readonly ?MaterialTractionRule $traction = null,
        private readonly ?LineMaterialExclusions $exclusions = null,
        private readonly ?MaterialRelations $relations = null,
        private readonly bool $plannedSections = true,
        private readonly ?LineMaterialSupplements $supplements = null,
    ) {}

    /**
     * Bilan de lecture des relations et itinéraires (MaterialRelations), vide sans eux.
     *
     * @return array<string, int>
     */
    public function relationStats(): array
    {
        return $this->relations?->stats() ?? [];
    }

    /**
     * Liens attestés, par couple « ligne:série ».
     *
     * @return array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>}>
     */
    public function collect(bool $refresh = false): array
    {
        $links = [];
        $add = function (int $lineId, int $materialId, array $evidence) use (&$links): void {
            $key = "{$lineId}:{$materialId}";
            $links[$key] ??= ['line_id' => $lineId, 'material_id' => $materialId, 'evidence' => []];
            foreach ($links[$key]['evidence'] as $known) {
                if ($known == $evidence) {
                    return;
                }
            }
            $links[$key]['evidence'][] = $evidence;
        };

        $materialsByQid = DB::table('materials')->whereNotNull('wikidata_id')->pluck('id', 'wikidata_id')->all();
        $linesByQid = [];
        foreach (DB::table('wikidata_lines')->whereNotNull('line_id')->get(['qid', 'line_id']) as $row) {
            $linesByQid[$row->qid][] = (int) $row->line_id;
        }
        foreach ($this->wikidata->vehicleUsage($refresh) as $usage) {
            foreach ($linesByQid[$usage['subject']] ?? [] as $lineId) {
                if (isset($materialsByQid[$usage['material']])) {
                    $add($lineId, (int) $materialsByQid[$usage['material']], [
                        'kind' => self::KIND_WIKIDATA,
                        'property' => 'P3438',
                        'subject' => $usage['subject'],
                        'from_year' => $usage['from'] !== null ? (int) substr($usage['from'], 0, 4) : null,
                        'to_year' => $usage['to'] !== null ? (int) substr($usage['to'], 0, 4) : null,
                        'url' => 'https://www.wikidata.org/wiki/'.$usage['subject'].'#P3438',
                    ]);
                }
            }
        }

        $this->fromLineArticles($add, $refresh);
        $this->fromMaterialArticles($add, $refresh);
        // Phrases relues à la main que la lecture automatique ne prend pas (database/data/line_material_supplements.php).
        $supplements = $this->supplements ?? LineMaterialSupplements::fromDatabase();
        foreach ($supplements->evidence() as $supplement) {
            $add($supplement['line_id'], $supplement['material_id'], $supplement['evidence']);
        }

        // Exclusion relue, écartement ou électrification contraires sans source explicite : le lien est
        // écarté (et gardé pour le bilan).
        $exclusions = $this->exclusions ?? LineMaterialExclusions::fromDatabase();
        $gauges = $this->gauges ?? MaterialGaugeRule::fromDatabase();
        $traction = $this->traction ?? MaterialTractionRule::fromDatabase();
        $this->rejected = [];
        $this->unresolved = [...$exclusions->unresolvedEntries(), ...$gauges->unresolvedEntries(), ...$traction->unresolvedEntries(), ...$supplements->unresolvedEntries()];
        foreach ($links as $key => $link) {
            $evidence = $link['evidence'];
            $reason = $exclusions->apply($link['line_id'], $link['material_id'], $evidence);
            $links[$key]['evidence'] = $evidence;
            $reason ??= $gauges->conflict($link['line_id'], $link['material_id'], $evidence)
                ?? $traction->conflict($link['line_id'], $link['material_id'], $evidence);
            if ($reason !== null) {
                $this->rejected[$key] = $link + ['reason' => $reason];
                unset($links[$key]);
            }
        }

        return $links;
    }

    /**
     * Liens que la dernière collecte a écartés (exclusion relue, écartement, électrification), par couple
     * « ligne:série », avec le motif.
     *
     * @return array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>, reason: string}>
     */
    public function rejected(): array
    {
        return $this->rejected;
    }

    /**
     * Exclusions et exceptions relues sans effet à la dernière collecte (ligne ou série introuvable, source
     * vide), une phrase par entrée : l'import les signale.
     *
     * @return list<string>
     */
    public function unresolvedEntries(): array
    {
        return $this->unresolved;
    }

    /**
     * Ce que write() changerait : couples « ligne:série » à créer, à mettre à jour (preuves, fiabilité)
     * et liens importés à retirer.
     *
     * @param  array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>}>  $links
     * @param  list<int>|null  $materialIds  séries seules concernées (null : toutes)
     * @return array{created: list<string>, updated: list<string>, removed: list<string>}
     */
    public static function diff(array $links, ?array $materialIds = null): array
    {
        $existing = self::imported($materialIds)->keyBy(fn (LineMaterial $l): string => "{$l->line_id}:{$l->material_id}");
        $out = ['created' => [], 'updated' => [], 'removed' => []];
        foreach (self::only($links, $materialIds) as $key => $link) {
            $row = $existing->pull((string) $key);
            if ($row === null) {
                $out['created'][] = (string) $key;
            } elseif (self::changed($row, self::attributes($link['evidence']))) {
                $out['updated'][] = (string) $key;
            }
        }
        $out['removed'] = array_map('strval', $existing->keys()->all());

        return $out;
    }

    /**
     * Liens importés en base, de ces séries seulement si elles sont données.
     *
     * @param  list<int>|null  $materialIds
     * @return Collection<int, LineMaterial>
     */
    private static function imported(?array $materialIds): Collection
    {
        return LineMaterial::query()->whereIn('source', LineMaterial::IMPORTED_SOURCES)
            ->when($materialIds !== null, fn ($q) => $q->whereIn('material_id', $materialIds ?? []))->get();
    }

    /**
     * Liens de ces séries seulement si elles sont données.
     *
     * @param  array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>}>  $links
     * @param  list<int>|null  $materialIds
     * @return array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>}>
     */
    private static function only(array $links, ?array $materialIds): array
    {
        return $materialIds === null ? $links : array_filter($links, fn (array $l): bool => in_array($l['material_id'], $materialIds, true));
    }

    /**
     * Le lien enregistré diffère-t-il de ces colonnes ?
     *
     * @param  array{source: string, source_url: ?string, confidence: string, evidence: list<array<string, mixed>>, fetched_at: mixed}  $values
     */
    private static function changed(LineMaterial $row, array $values): bool
    {
        return $row->source !== $values['source'] || $row->source_url !== $values['source_url']
            || $row->confidence !== $values['confidence'] || ! JsonEquality::same($row->evidence, $values['evidence']);
    }

    /**
     * Écrit les liens : un lien importé absent de `$links` est retiré, les liens saisis dans VFF
     * (source nulle ou autre) ne sont jamais touchés.
     *
     * Avec `$materialIds`, seuls les liens de ces séries sont créés, mis à jour ou retirés : les autres restent tels quels.
     *
     * @param  array<string, array{line_id: int, material_id: int, evidence: list<array<string, mixed>>}>  $links
     * @param  list<int>|null  $materialIds  séries seules concernées (null : toutes)
     * @return array{created: int, updated: int, removed: int}
     */
    public function write(array $links, ?array $materialIds = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'removed' => 0];

        DB::transaction(function () use ($links, $materialIds, &$stats): void {
            $existing = self::imported($materialIds)->keyBy(fn (LineMaterial $l): string => "{$l->line_id}:{$l->material_id}");

            foreach (self::only($links, $materialIds) as $key => $link) {
                $values = self::attributes($link['evidence']);
                $row = $existing->pull($key);
                if ($row === null) {
                    LineMaterial::query()->create(['line_id' => $link['line_id'], 'material_id' => $link['material_id']] + $values);
                    $stats['created']++;

                    continue;
                }
                if (self::changed($row, $values)) {
                    $row->fill($values)->save();
                    $stats['updated']++;
                }
            }

            // Liens importés que les sources ne portent plus.
            $stats['removed'] = $existing->isEmpty() ? 0 : LineMaterial::query()->whereIn('id', $existing->pluck('id'))->delete();
        });

        return $stats;
    }

    /**
     * Colonnes d'un lien d'après ses preuves.
     *
     * @param  list<array<string, mixed>>  $evidence
     * @return array{source: string, source_url: ?string, confidence: string, evidence: list<array<string, mixed>>, fetched_at: mixed}
     */
    public static function attributes(array $evidence): array
    {
        usort($evidence, fn (array $a, array $b): int => self::rank($b) <=> self::rank($a));
        // Cinq preuves suffisent : les plus fortes d'abord.
        $evidence = array_slice($evidence, 0, self::MAX_EVIDENCE);
        $hasWikidata = in_array(self::KIND_WIKIDATA, array_column($evidence, 'kind'), true);
        $best = self::rank($evidence[0]);

        return [
            'source' => $hasWikidata ? 'wikidata' : 'wikipedia',
            'source_url' => $evidence[0]['url'] ?? null,
            'confidence' => match (true) {
                $best >= 3 => 'high',
                $best === 2 => 'medium',
                default => 'low',
            },
            'evidence' => $evidence,
            'fetched_at' => now(),
        ];
    }

    /**
     * Force d'une preuve : 3 Wikidata ou section « Matériel » d'un article de ligne, 2 autre section
     * (ou phrase qui reprend un autre matériel pour dire qu'il est remplacé), 1 article partagé ou
     * preuve qui porte un doute (`doubt`).
     */
    private static function rank(array $evidence): int
    {
        if (($evidence['shared_article'] ?? false) === true || is_string($evidence['doubt'] ?? null)) {
            return 1;
        }

        return match (true) {
            ($evidence['refers_back'] ?? false) === true => 2,
            $evidence['kind'] === self::KIND_WIKIDATA => 3,
            $evidence['kind'] === self::KIND_LINE_ARTICLE && self::isMaterialSection((string) ($evidence['section'] ?? '')) => 3,
            default => 2,
        };
    }

    /** Section (ou sous-section, « Trafic > Matériel roulant ») consacrée au matériel. */
    private static function isMaterialSection(string $path): bool
    {
        foreach (explode(' > ', $path) as $title) {
            if (preg_match(self::MATERIAL_HEADING, trim($title)) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param callable(int, int, array<string, mixed>): void $add */
    private function fromLineArticles(callable $add, bool $refresh): void
    {
        $linesByTitle = [];
        foreach (DB::table('lines')->whereNotNull('wikipedia_title')->get(['id', 'wikipedia_title']) as $row) {
            $linesByTitle[WikipediaMaterialSections::normalizeTitle((string) $row->wikipedia_title)][] = (int) $row->id;
        }
        $materialsByTitle = $this->materialTitles($refresh);
        $pages = $this->wikipedia->sections('wikipedia_lines', array_keys($linesByTitle), WikipediaMaterialSections::LINE_SECTIONS, $refresh);

        foreach ($pages as $requested => $page) {
            if ($page === null) {
                continue;
            }
            $lineIds = $linesByTitle[$requested] ?? [];
            foreach ($page['sections'] as $section) {
                if (WikipediaMaterialSections::isDoubtfulSection($section['title'])) {
                    continue;
                }
                foreach (WikipediaMaterialSections::links($section['text']) as $link) {
                    // « [[Regio 2N|Omneo Premium]] (Z 56600) » : la phrase nomme une version, le lien vaut pour elle.
                    foreach ($this->families($refresh)->attribute(array_values(array_unique($materialsByTitle[$link['target']] ?? [])), $link['quote']) as $materialId) {
                        foreach ($lineIds as $lineId) {
                            $add($lineId, $materialId, self::wikipediaEvidence(self::KIND_LINE_ARTICLE, $page, $section['title'], $link, count($lineIds) > 1));
                        }
                    }
                }
            }
        }
    }

    /** @param callable(int, int, array<string, mixed>): void $add */
    private function fromMaterialArticles(callable $add, bool $refresh): void
    {
        $materials = DB::table('materials')->whereNotNull('wikipedia_fr')->pluck('id', 'wikipedia_fr')->all();
        $this->fromRelations($add, $materials, $refresh);
        // Une série lente citée dans le contexte d'une LGV (« la mise en service de la LGV Est libère les
        // BB 16000 ») n'y a pas circulé : sur une LGV, seuls les engins de grande vitesse sont reliés.
        $fast = DB::table('materials')->where(fn ($q) => $q->where('type', 'rame_tgv')->orWhere('max_speed_kmh', '>=', self::LGV_MIN_SPEED_KMH))->pluck('id')->flip()->all();
        $lgv = DB::table('lines')->where('is_lgv', true)->pluck('id')->flip()->all();
        $linesByTitle = $this->lineTitles($refresh);
        $pages = $this->wikipedia->sections('wikipedia_materials', array_map('strval', array_keys($materials)), WikipediaMaterialSections::MATERIAL_SECTIONS, $refresh);

        foreach ($pages as $requested => $page) {
            if ($page === null || ! isset($materials[$requested])) {
                continue;
            }
            foreach ($page['sections'] as $section) {
                $planned = WikipediaMaterialSections::isDoubtfulSection($section['title']);
                // « Dessertes réalisées ou prévues » : lue lien par lien, derrière isPlannedSectionService().
                if ($planned && ! ($this->plannedSections && WikipediaMaterialSections::isRealisedSection($section['title']))) {
                    continue;
                }
                foreach (WikipediaMaterialSections::links($section['text']) as $link) {
                    if (! $this->speaksOf((int) $materials[$requested], (string) $requested, $page, $link['quote'])
                        || ($planned && ! WikipediaMaterialSections::isPlannedSectionService($link))) {
                        continue;
                    }
                    $lineIds = array_values(array_unique($linesByTitle[$link['target']] ?? []));
                    foreach ($lineIds as $lineId) {
                        if (isset($lgv[$lineId]) && ! isset($fast[(int) $materials[$requested]])) {
                            continue;
                        }
                        $add($lineId, (int) $materials[$requested], self::wikipediaEvidence(self::KIND_MATERIAL_ARTICLE, $page, $section['title'], $link, count($lineIds) > 1));
                    }
                }
            }
        }
    }

    /** @var array<string, string>|null titre de l'article demandé → nom de la série */
    private ?array $seriesNames = null;

    private ?MaterialFamilies $familyIndex = null;

    /**
     * La phrase vaut-elle pour la série dont l'article a été demandé ? Oui si l'article est le sien (titre
     * demandé = titre final), sauf quand elle nomme l'une de ses versions (« les Z 56600 assurent… » dans
     * l'article Regio 2N revient aux Z 56600). Une série dont le titre renvoie vers l'article d'une autre
     * (Coradia Liner vers Régiolis, Z 56500 vers Regio 2N) ne reçoit que les phrases qui la nomment
     * (« Z 56500 », « Omneo Premium ») : le reste de l'article décrit l'autre série. Règle partagée avec
     * MaterialPeriodCollector (MaterialFamilies).
     *
     * @param  array{title: string}  $page
     */
    private function speaksOf(int $materialId, string $requested, array $page, string $quote): bool
    {
        $this->seriesNames ??= DB::table('materials')->whereNotNull('wikipedia_fr')->pluck('name', 'wikipedia_fr')->map(fn ($n): string => (string) $n)->all();

        return $this->families(false)->speaksOf($materialId, $requested, $page['title'], $this->seriesNames[$requested] ?? $requested, $quote);
    }

    /** Familles de séries d'après les articles des séries (MaterialFamilies), lues une fois. */
    private function families(bool $refresh): MaterialFamilies
    {
        if ($this->familyIndex === null) {
            $titles = DB::table('materials')->whereNotNull('wikipedia_fr')->pluck('wikipedia_fr')->map(fn ($t): string => (string) $t)->all();
            $this->familyIndex = MaterialFamilies::fromDatabase($this->wikipedia->sections('wikipedia_materials', $titles, WikipediaMaterialSections::MATERIAL_SECTIONS, $refresh));
        }

        return $this->familyIndex;
    }

    /**
     * Relations et itinéraires cités par les articles des séries (MaterialRelations), quand ils sont fournis.
     * Les articles dont aucune section ne répond à MATERIAL_SECTIONS sont relus pour leurs sections
     * « Relations… » (RELATION_SECTIONS, cache à part).
     *
     * @param  callable(int, int, array<string, mixed>): void  $add
     * @param  array<string, int|string>  $materials  titre de l'article → série
     */
    private function fromRelations(callable $add, array $materials, bool $refresh): void
    {
        if ($this->relations === null) {
            return;
        }
        $titles = array_map('strval', array_keys($materials));
        $pages = $this->wikipedia->sections('wikipedia_materials', $titles, WikipediaMaterialSections::MATERIAL_SECTIONS, $refresh);
        $bare = array_values(array_filter($titles, fn (string $t): bool => ($pages[$t] ?? null) !== null && $pages[$t]['sections'] === []));
        $more = $bare === [] ? [] : $this->wikipedia->sections('wikipedia_material_relations', $bare, WikipediaMaterialSections::RELATION_SECTIONS, $refresh);
        $info = DB::table('materials')->whereIn('id', array_values($materials))->get(['id', 'type', 'max_speed_kmh', 'in_service_from', 'in_service_to', 'electrification'])->keyBy('id');
        $lgv = DB::table('lines')->where('is_lgv', true)->pluck('id')->flip()->all();
        $titles = null;
        foreach ([$pages, $more] as $set) {
            foreach ($set as $requested => $page) {
                $row = $info[(int) ($materials[$requested] ?? 0)] ?? null;
                if ($page === null || $row === null) {
                    continue;
                }
                $material = [
                    'fast' => $row->type === 'rame_tgv' || (int) $row->max_speed_kmh >= self::LGV_MIN_SPEED_KMH,
                    'from' => $row->in_service_from !== null ? (int) $row->in_service_from : null,
                    'to' => $row->in_service_to !== null ? (int) $row->in_service_to : null,
                    'electric' => self::isElectricOnly((string) $row->type, $row->electrification),
                ];
                foreach ($this->relations->links($page, $material) as $link) {
                    if (! $this->speaksOf((int) $row->id, (string) $requested, $page, (string) ($link['evidence']['quote'] ?? ''))) {
                        continue;
                    }
                    // Série qui remplace celle de l'article sur un itinéraire : le lien est pour elle, si son
                    // article désigne une seule série importée (pas sur une LGV : itinéraires classiques).
                    if (isset($link['successor'])) {
                        $titles ??= $this->materialTitles($refresh);
                        $ids = array_values(array_unique($titles[$link['successor']] ?? []));
                        if (count($ids) === 1 && $ids[0] !== (int) $row->id && ! isset($lgv[$link['line_id']])) {
                            $add($link['line_id'], $ids[0], $link['evidence']);
                        }

                        continue;
                    }
                    if (! isset($lgv[$link['line_id']]) || $material['fast']) {
                        $add($link['line_id'], (int) $row->id, $link['evidence']);
                    }
                }
            }
        }
    }

    /**
     * Série qui ne roule que sous caténaire, pour le tracé des relations (RelationRouter) : rame TGV,
     * locomotive électrique, ou automotrice dont Wikidata donne les systèmes d'alimentation (la catégorie
     * « automotrice » range aussi des engins thermiques). Une bimode n'en est pas une.
     */
    public static function isElectricOnly(string $type, mixed $electrification): bool
    {
        $systems = is_string($electrification) ? json_decode($electrification, true) : $electrification;

        return match ($type) {
            'rame_tgv', 'locomotive_electrique' => true,
            'automotrice' => is_array($systems) && $systems !== [],
            default => false,
        };
    }

    /**
     * Titres d'articles (et redirections) qui désignent chaque série importée.
     *
     * @return array<string, list<int>>
     */
    private function materialTitles(bool $refresh): array
    {
        $byTitle = [];
        $titles = DB::table('materials')->whereNotNull('wikipedia_fr')->pluck('id', 'wikipedia_fr')->all();
        foreach ($titles as $title => $id) {
            $byTitle[WikipediaMaterialSections::normalizeTitle((string) $title)][] = (int) $id;
        }
        foreach ($this->wikipedia->redirects('redirects_materials', array_map('strval', array_keys($titles)), $refresh) as $title => $aliases) {
            foreach ($aliases ?? [] as $alias) {
                $byTitle[WikipediaMaterialSections::normalizeTitle($alias)][] = (int) $titles[$title];
            }
        }

        return $byTitle;
    }

    /**
     * Titres d'articles (titre enregistré, titre final, redirections) qui désignent chaque ligne.
     *
     * @return array<string, list<int>>
     */
    private function lineTitles(bool $refresh): array
    {
        $byTitle = [];
        foreach (DB::table('lines')->whereNotNull('wikipedia_title')->get(['id', 'wikipedia_title']) as $row) {
            $byTitle[WikipediaMaterialSections::normalizeTitle((string) $row->wikipedia_title)][] = (int) $row->id;
        }
        // Titre final de chaque article (le titre enregistré peut être une redirection).
        $finals = [];
        foreach ($this->wikipedia->sections('wikipedia_lines', array_keys($byTitle), WikipediaMaterialSections::LINE_SECTIONS, $refresh) as $requested => $page) {
            if ($page !== null && $page['title'] !== $requested) {
                $byTitle[$page['title']] = $byTitle[$requested];
            }
            if ($page !== null) {
                $finals[$page['title']] = $byTitle[$requested];
            }
        }
        foreach ($this->wikipedia->redirects('redirects_lines', array_keys($finals), $refresh) as $title => $aliases) {
            foreach ($aliases ?? [] as $alias) {
                $byTitle[WikipediaMaterialSections::normalizeTitle($alias)] = $finals[$title];
            }
        }

        return array_map(fn (array $ids): array => array_values(array_unique($ids)), $byTitle);
    }

    /**
     * Preuve tirée d'une phrase. `doubt` : famille de doute de la mention, sinon celle du titre de section
     * (« Trains d'essai », « Records »…), omise quand rien ne met la circulation en doute.
     *
     * @param  array{title: string, revid: int}  $page
     * @param  array{target: string, quote: string, refers_back: bool, doubt?: ?string}  $link
     * @return array<string, mixed>
     */
    private static function wikipediaEvidence(string $kind, array $page, string $section, array $link, bool $shared): array
    {
        return array_filter([
            'kind' => $kind,
            'article' => $page['title'],
            'revid' => $page['revid'],
            'section' => $section,
            'quote' => $link['quote'],
            'url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $page['title'])).'&oldid='.$page['revid'],
            'shared_article' => $shared ?: null,
            'refers_back' => $link['refers_back'] ?: null,
            'doubt' => $link['doubt'] ?? WikipediaMaterialSections::uncertainSection($section),
        ], fn ($v): bool => $v !== null);
    }
}
