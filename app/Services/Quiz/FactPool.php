<?php

namespace App\Services\Quiz;

use App\Enums\QuizTopic;
use App\Services\Companies\CompanyFigures;
use App\Services\NetworkChronology;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vivier du jeu « Culture du rail » : un sujet par question possible, tiré des données déjà calculées ou
 * lues par le site, jamais écrit. Lecture seule, requêtes bornées.
 *
 *   - chronologie nationale (NetworkChronology, cache) : ouverture et électrification d'une ligne entière, jalons ;
 *   - compagnies (CompanyFigures, cache) : compagnie rattachée à une ligne à une date ronde ;
 *   - ouvrages d'art (`structures`) : longueur de tunnels et de viaducs nommés ;
 *   - patrimoine des gares (`station_heritage`) : architecte, année de protection ;
 *   - fréquentation (`station_attendances`) : voyageurs d'une année de la série 2015-2024 ;
 *   - matériel (`materials`) : année de mise en service d'une série.
 *
 * Règle commune : un fait n'est posé que s'il est sans équivoque. Une année n'est demandée que si le fait est
 * daté à l'année au moins et qu'aucune autre source ne donne une autre année pour la même chose ; une
 * proposition « la plus longue » ou « la plus fréquentée » a une marge sur les autres (FactQuiz::candidates).
 * Chaque sujet garde les sources publiables qui le portent (`sources`) : jamais une source à usage non
 * commercial (NetworkChronology::isPublishable).
 *
 * Forme d'un sujet : `id` (« sujet:référence »), `label` (la bonne réponse, telle qu'affichée), `topic`,
 * `group`, `difficulty`, `title` (ce dont parle la question : ligne, gare, jalon), `url` (fiche), `sources` et
 * `data` (champs propres au sujet).
 */
final class FactPool
{
    /** Première et dernière année proposées dans les questions sur une année (le reste n'a pas de sens pour le rail). */
    public const FIRST_YEAR = NetworkChronology::FIRST_YEAR;

    /** Années rondes auxquelles on demande la compagnie d'une ligne. */
    public const COMPANY_YEARS = [1860, 1870, 1880, 1890, 1900, 1910, 1920, 1930];

    /** Tunnels et viaducs plus courts que cela : écartés (un passage sous la voie n'est pas un tunnel à nommer). */
    public const MIN_STRUCTURE_M = 200;

    /** Année de la série de fréquentation utilisée (2015-2024 : même méthode pour toutes les gares). */
    public const ATTENDANCE_YEAR = 2024;

    /** Fréquentation minimale d'une gare du vivier (voyageurs par an). */
    public const MIN_PASSENGERS = 20000;

    /** Types de matériel dont l'année de mise en service est une vraie mise en service (pas une livraison). */
    private const SERVICE_BASES = ['mise en service', 'début du service commercial', 'début de la période de service'];

    /** Rôles de Mérimée tenus pour « architecte » (le seul rôle qui répond à la question). */
    private const ARCHITECT_ROLE = 'architecte';

    /** Mots qui montrent qu'un « architecte » est une institution ou un service, pas une personne. */
    private const NOT_A_PERSON = '/\b(compagnie|chemins? de fer|administration|soci[ée]t[ée]|service|bureau|ville|[ée]tat|sncf|ing[ée]nieur|ponts|architectes|agence|atelier|cabinet|entreprise)\b/iu';

    /**
     * Vivier complet.
     *
     * @return array{items: list<array<string, mixed>>, complete: bool} `complete` : faux si un cache (chronologie, compagnies) manquait
     */
    public function build(): array
    {
        $facts = $this->chronologyFacts();
        $companies = Cache::get(CompanyFigures::INDEX_KEY);
        $byLine = Cache::get(CompanyFigures::BY_LINE_KEY);
        $complete = $facts !== [] && is_array($companies) && is_array($byLine);

        $items = array_merge(
            $this->lineYears($facts),
            $this->milestones(),
            is_array($companies) && is_array($byLine) ? $this->companies($facts, $companies['companies'] ?? [], $byLine) : [],
            // Tables facultatives (imports pas encore passés) : leurs questions manquent, le vivier reste servi.
            $this->has('structures', 'line_structures') ? $this->structures('tunnel', QuizTopic::LongestTunnel, 'tunnel') : [],
            $this->has('structures', 'line_structures') ? $this->structures('viaduct', QuizTopic::LongestViaduct, 'viaduc') : [],
            $this->has('station_heritage') ? $this->architects() : [],
            $this->has('station_heritage') ? $this->heritageYears() : [],
            $this->has('station_attendances') ? $this->attendances() : [],
            $this->firstSeries(),
        );
        usort($items, fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return ['items' => $items, 'complete' => $complete];
    }

    /** Vrai quand toutes les tables existent (une lecture du catalogue, sans erreur SQL quand une table manque). */
    private function has(string ...$tables): bool
    {
        $missing = DB::scalar('SELECT COUNT(*) FROM unnest(?::text[]) t WHERE to_regclass(t) IS NULL', ['{'.implode(',', $tables).'}']);

        return (int) $missing === 0;
    }

    /**
     * Faits de la chronologie nationale, lus dans le cache sans le calculer (vide s'il n'a pas été calculé).
     *
     * @return list<array<string, mixed>>
     */
    private function chronologyFacts(): array
    {
        if (! is_array(Cache::get(NetworkChronology::CACHE_KEY.':meta'))) {
            return [];
        }
        $facts = [];
        for ($decade = intdiv(NetworkChronology::FIRST_YEAR, 10) * 10; $decade <= intdiv((int) now()->year, 10) * 10; $decade += 10) {
            $list = Cache::get(NetworkChronology::CACHE_KEY.':decade:'.$decade);
            if (is_array($list)) {
                array_push($facts, ...$list);
            }
        }

        return $facts;
    }

    /**
     * Ouverture ou électrification d'une ligne entière : une ligne n'a qu'un seul fait de ce type dans la
     * chronologie, et aucun événement de sa chronologie (toutes sources, sauf les estimations du réseau) ne
     * donne une autre année.
     *
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, mixed>>
     */
    private function lineYears(array $facts): array
    {
        $byLine = [];
        foreach ($facts as $f) {
            if (in_array($f['kind'], ['opening', 'electrification'], true) && isset($f['line']['slug'])) {
                $byLine[$f['line']['slug']][$f['kind']][] = $f;
            }
        }
        $conflicts = [];
        foreach (DB::select(<<<'SQL'
            SELECT l.slug, CASE WHEN e.type = 'electrification' THEN 'electrification' ELSE 'opening' END AS kind
            FROM line_events e JOIN lines l ON l.id = e.line_id
            WHERE (e.type LIKE 'opening%' OR e.type = 'electrification') AND COALESCE(e.source, '') <> 'estimation-reseau'
            GROUP BY 1, 2 HAVING COUNT(DISTINCT EXTRACT(YEAR FROM e.event_date)) > 1
        SQL) as $r) {
            $conflicts[$r->slug.'|'.$r->kind] = true;
        }

        $items = [];
        foreach ($byLine as $slug => $kinds) {
            foreach ($kinds as $kind => $list) {
                $f = $list[0];
                if (count($list) !== 1 || $f['sections'] !== [] || ($f['sources'] ?? []) === [] || isset($conflicts[$slug.'|'.$kind])) {
                    continue;
                }
                $topic = $kind === 'opening' ? QuizTopic::OpeningYear : QuizTopic::ElectrificationYear;
                $items[] = $this->item($topic, $slug, (string) $f['year'], (string) $f['line']['name'], '/lines/'.$slug, $f['sources'], ['year' => (int) $f['year'], 'precision' => (string) $f['precision'], 'line' => (string) $slug]);
            }
        }

        return $items;
    }

    /**
     * Jalons nationaux (lois, création des entreprises du réseau) : date vérifiée dans l'article cité.
     *
     * @return list<array<string, mixed>>
     */
    private function milestones(): array
    {
        $items = [];
        foreach (NetworkChronology::MILESTONES as $m) {
            $year = (int) substr($m['date'], 0, 4);
            if ($year > (int) now()->year) {
                continue;
            }
            $items[] = $this->item(QuizTopic::Milestone, $m['key'], (string) $year, $m['key'], '/chronology', [['provider' => 'wikipedia', 'url' => $m['url']]], ['year' => $year, 'milestone' => $m['key']]);
        }

        return $items;
    }

    /**
     * Compagnie d'une ligne à une année ronde : une seule compagnie rattachée à cette date (au début et à la fin
     * de son intervalle, l'année du changement n'est pas retenue), la ligne déjà ouverte et pas encore fermée.
     * Les lignes dont une fin de rattachement est inconnue sont écartées.
     *
     * @param  list<array<string, mixed>>  $facts
     * @param  list<array<string, mixed>>  $companies
     * @param  array<int|string, list<array<string, mixed>>>  $byLine
     * @return list<array<string, mixed>>
     */
    private function companies(array $facts, array $companies, array $byLine): array
    {
        $firstOpening = $firstClosure = [];
        foreach ($facts as $f) {
            if (! isset($f['line']['slug'])) {
                continue;
            }
            if ($f['kind'] === 'opening') {
                $firstOpening[$f['line']['slug']] = min($firstOpening[$f['line']['slug']] ?? PHP_INT_MAX, $f['year']);
            } elseif ($f['kind'] === 'closure') {
                $firstClosure[$f['line']['slug']] = min($firstClosure[$f['line']['slug']] ?? PHP_INT_MAX, $f['year']);
            }
        }
        $names = array_column($companies, null, 'slug');
        $ids = array_map('intval', array_keys($byLine));
        if ($ids === []) {
            return [];
        }
        $array = '{'.implode(',', $ids).'}';
        $lines = DB::select('SELECT id, slug, name FROM lines WHERE id = ANY(?::bigint[])', [$array]);
        // Article Wikipédia dont l'infobox donne le concessionnaire : la source de la réponse.
        $articles = [];
        foreach (DB::select(<<<'SQL'
            SELECT DISTINCT ON (line_id) line_id, source_url FROM line_events
            WHERE type IN ('concession', 'concession_end') AND source = 'wikipedia' AND source_url IS NOT NULL AND line_id = ANY(?::bigint[])
            ORDER BY line_id, event_date, id
        SQL, [$array]) as $r) {
            $articles[(int) $r->line_id] = (string) $r->source_url;
        }

        $items = [];
        foreach ($lines as $line) {
            $intervals = $byLine[(int) $line->id] ?? $byLine[(string) $line->id] ?? [];
            if ($intervals === [] || in_array('unknown', array_column($intervals, 'to_basis'), true) || ! isset($firstOpening[$line->slug], $articles[(int) $line->id])) {
                continue;
            }
            $eligible = [];
            foreach (self::COMPANY_YEARS as $year) {
                $covering = array_values(array_filter($intervals, fn (array $v): bool => $v['from_year'] < $year && ($v['to_year'] === null ? $v['to_basis'] === 'open' : $year < $v['to_year'])));
                if (count($covering) === 1 && $year > $firstOpening[$line->slug] && $year < ($firstClosure[$line->slug] ?? PHP_INT_MAX) && isset($names[$covering[0]['slug']])) {
                    $eligible[$year] = $covering[0];
                }
            }
            if ($eligible === []) {
                continue;
            }
            // Une année par ligne, choisie par hachage du nom : le tirage ne bouge pas d'une série à l'autre.
            $years = array_keys($eligible);
            usort($years, fn (int $a, int $b): int => strcmp(hash('xxh128', "{$line->slug}|{$a}"), hash('xxh128', "{$line->slug}|{$b}")));
            $year = $years[0];
            $c = $eligible[$year];
            $company = $names[$c['slug']];
            $others = [];
            foreach ($companies as $o) {
                $founded = (int) substr((string) ($o['founded']['date'] ?? '9999'), 0, 4);
                $ended = isset($o['ended']['date']) ? (int) substr((string) $o['ended']['date'], 0, 4) : null;
                $everOnLine = in_array($o['slug'], array_column($intervals, 'slug'), true);
                if (! $everOnLine && in_array($o['kind'], ['grande-compagnie', 'etat'], true) && $founded < $year && ($ended === null || $ended > $year)) {
                    $others[] = ['label' => (string) $o['short'], 'kind' => (string) $o['kind']];
                }
            }
            $items[] = $this->item(QuizTopic::Company, "{$line->slug}@{$year}", (string) $company['short'], (string) $line->name, '/lines/'.$line->slug, [['provider' => 'wikipedia', 'url' => $articles[(int) $line->id]]], [
                'year' => $year, 'line' => (string) $line->slug, 'line_id' => (int) $line->id, 'company' => (string) $company['short'],
                'company_kind' => (string) $company['kind'], 'from' => (int) $c['from_year'], 'to' => $c['to_year'] === null ? null : (int) $c['to_year'],
                'others' => $others,
            ]);
        }

        return $items;
    }

    /**
     * Tunnels ou viaducs nommés, reliés à une ligne, de longueur connue. Un nom porté par plusieurs ouvrages (de n'importe quelle sorte) est écarté.
     *
     * @return list<array<string, mixed>>
     */
    private function structures(string $kind, QuizTopic $topic, string $nameStart): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.id, s.name, s.length_m, s.length_source, s.wikidata_id, l.slug AS line_slug, l.name AS line_name
            FROM structures s
            JOIN LATERAL (
                SELECT li.id, li.slug, li.name FROM line_structures ls JOIN lines li ON li.id = ls.line_id
                WHERE ls.structure_id = s.id ORDER BY li.id LIMIT 1
            ) l ON TRUE
            WHERE s.kind = ? AND s.name IS NOT NULL AND s.length_m >= ? AND s.length_source IN ('bdtopo', 'wikidata')
              AND lower(s.name) LIKE ?
              AND NOT EXISTS (SELECT 1 FROM structures x WHERE x.id <> s.id AND lower(btrim(x.name)) = lower(btrim(s.name)))
            ORDER BY s.id
        SQL, [$kind, self::MIN_STRUCTURE_M, $nameStart.'%']);
        $count = array_count_values(array_map(fn (object $r): string => mb_strtolower(trim((string) $r->name)), $rows));

        $items = [];
        foreach ($rows as $r) {
            $name = trim((string) $r->name);
            if ($count[mb_strtolower($name)] > 1) {
                continue;
            }
            $sources = $r->length_source === 'bdtopo'
                ? [['provider' => 'ign', 'url' => 'https://geoservices.ign.fr/bdtopo']]
                : [['provider' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/'.$r->wikidata_id]];
            $items[] = $this->item($topic, (string) $r->id, ucfirst($name), ucfirst($name), '/lines/'.$r->line_slug, $sources, ['length_m' => (int) $r->length_m, 'line' => (string) $r->line_name]);
        }

        return $items;
    }

    /**
     * Gares dont le bâtiment protégé a un seul architecte (rôle « architecte », une personne), d'après la base Mérimée
     * ou Wikidata ; un seul bâtiment protégé par gare.
     *
     * @return list<array<string, mixed>>
     */
    private function architects(): array
    {
        $items = [];
        foreach ($this->heritageRows() as $r) {
            $people = json_decode((string) $r->architects, true);
            if (! is_array($people) || count($people) !== 1 || mb_strtolower((string) ($people[0]['role'] ?? '')) !== self::ARCHITECT_ROLE) {
                continue;
            }
            $name = self::personName((string) ($people[0]['name'] ?? ''));
            if ($name === null) {
                continue;
            }
            $items[] = $this->item(QuizTopic::Architect, (string) $r->slug, $name, (string) $r->name, '/stations/'.$r->slug, [self::heritageSource($r)], [
                'city' => $r->city, 'built' => $r->built_from !== null ? (int) $r->built_from : null, 'station' => (string) $r->name,
            ]);
        }

        return $items;
    }

    /**
     * Année de protection au titre des monuments historiques (base Mérimée, date au jour), pour les gares qui n'ont
     * qu'une seule protection datée (ni inscription suivie d'un classement, ni deux dates).
     *
     * @return list<array<string, mixed>>
     */
    private function heritageYears(): array
    {
        $items = [];
        foreach ($this->heritageRows() as $r) {
            if ($r->protection_date === null || ! in_array($r->protection, ['registered', 'classified'], true) || $r->ref_kind !== 'PA' || $r->source !== 'merimee' || (int) $r->dated_protections !== 1) {
                continue;
            }
            $year = (int) substr((string) $r->protection_date, 0, 4);
            $items[] = $this->item(QuizTopic::HeritageYear, (string) $r->slug, (string) $year, (string) $r->name, '/stations/'.$r->slug, [self::heritageSource($r)], [
                'year' => $year, 'protection' => (string) $r->protection, 'city' => $r->city, 'station' => (string) $r->name,
            ]);
        }

        return $items;
    }

    /**
     * Gares qui n'ont qu'un seul bâtiment protégé ou inventorié, avec leur fiche.
     *
     * @return list<object>
     */
    private function heritageRows(): array
    {
        return DB::select(<<<'SQL'
            SELECT s.slug, s.name, s.city, h.architects, h.built_from, h.protection, h.protection_date, h.ref, h.ref_kind, h.ref_url, h.source, h.wikidata_id,
                   (SELECT COUNT(*) FROM jsonb_array_elements(COALESCE(h.designations, '[]'::jsonb)) d WHERE d->>'kind' IN ('registered', 'classified') AND d->>'date' IS NOT NULL) AS dated_protections
            FROM station_heritage h JOIN stations s ON s.id = h.station_id
            WHERE h.element = 'building' AND h.ref_kind IN ('PA', 'IA', 'EA') AND h.match_method = 'wikidata'
              AND (h.denomination IS NULL OR h.denomination NOT LIKE '%;%')
              AND (SELECT COUNT(*) FROM station_heritage x WHERE x.station_id = h.station_id AND x.protection IN ('registered', 'classified')) <= 1
              AND (SELECT COUNT(*) FROM station_heritage x WHERE x.station_id = h.station_id AND x.element = 'building' AND x.ref_kind IN ('PA', 'IA', 'EA')) = 1
            ORDER BY s.id
        SQL);
    }

    /**
     * Source d'une ligne du patrimoine : la notice de la base Mérimée si la donnée vient d'elle, sinon Wikidata.
     *
     * @return array{provider: string, url: string|null}
     */
    private static function heritageSource(object $r): array
    {
        if ($r->source === 'wikidata' && $r->wikidata_id !== null) {
            return ['provider' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/'.$r->wikidata_id];
        }

        return ['provider' => 'pop', 'url' => $r->ref_url !== null ? (string) $r->ref_url : 'https://www.pop.culture.gouv.fr/notice/merimee/'.$r->ref];
    }

    /**
     * Nom d'une personne tel qu'on l'écrit (« LISCH » devient « Lisch »), ou null si ce n'est pas un nom de personne
     * (une compagnie, un nom seul).
     */
    public static function personName(string $raw): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if (preg_match('/^\p{Lu}[\p{L}\'’.-]*( [\p{L}\'’.-]+)+$/u', $name) !== 1 || preg_match(self::NOT_A_PERSON, $name) === 1) {
            return null;
        }
        // Un nom suivi ou précédé d'une initiale (« Houssin F. ») s'écrit de plusieurs façons : écarté.
        foreach (explode(' ', $name) as $word) {
            if (mb_strlen(rtrim($word, '.')) < 2) {
                return null;
            }
        }

        return implode(' ', array_map(fn (string $w): string => preg_match('/^\p{Lu}{2,}$/u', $w) === 1 ? mb_convert_case($w, MB_CASE_TITLE) : $w, explode(' ', $name)));
    }

    /**
     * Gares les plus fréquentées : voyageurs de l'année de la série 2015-2024 (rattachement par code UIC), noms
     * portés par une seule gare du vivier.
     *
     * @return list<array<string, mixed>>
     */
    private function attendances(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.slug, s.name, s.city, a.passengers
            FROM station_attendances a JOIN stations s ON s.id = a.station_id
            WHERE a.year = ? AND a.series = '2015-2024' AND a.match_method = 'uic' AND a.passengers >= ?
            ORDER BY s.id
        SQL, [self::ATTENDANCE_YEAR, self::MIN_PASSENGERS]);
        $count = array_count_values(array_map(fn (object $r): string => mb_strtolower(trim((string) $r->name)), $rows));

        $items = [];
        foreach ($rows as $r) {
            if ($count[mb_strtolower(trim((string) $r->name))] > 1) {
                continue;
            }
            $items[] = $this->item(QuizTopic::Attendance, (string) $r->slug, (string) $r->name, (string) $r->name, '/stations/'.$r->slug, [['provider' => 'sncf', 'url' => 'https://data.sncf.com/explore/dataset/frequentation-gares-archive/']], [
                'passengers' => (int) $r->passengers, 'year' => self::ATTENDANCE_YEAR,
            ]);
        }

        return $items;
    }

    /**
     * Séries de matériel dont l'année de mise en service (et non de livraison) vient de Wikidata ou d'une phrase de
     * Wikipédia qui la dit. Les types qui comptent moins de quatre séries sont écartés.
     *
     * @return list<array<string, mixed>>
     */
    private function firstSeries(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT m.id, m.slug, m.name, m.type, m.in_service_from, m.wikidata_id, m.provenance->'in_service_from' AS prov
            FROM materials m
            WHERE m.in_service_from IS NOT NULL AND m.scope = 'france'
              AND (m.provenance->'in_service_from'->>'source' = 'wikidata'
                   OR (m.provenance->'in_service_from'->>'source' = 'wikipedia' AND m.provenance->'in_service_from'->>'basis' = ANY(?::text[])))
            ORDER BY m.id
        SQL, ['{"'.implode('","', self::SERVICE_BASES).'"}']);

        $items = [];
        foreach ($rows as $r) {
            if (trim((string) $r->name) === '' || preg_match('/^Q\d+$/', trim((string) $r->name)) === 1) {
                continue;
            }
            $prov = json_decode((string) $r->prov, true) ?: [];
            $source = ($prov['source'] ?? '') === 'wikipedia' && isset($prov['revid'])
                ? ['provider' => 'wikipedia', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid='.(int) $prov['revid']]
                : ['provider' => 'wikidata', 'url' => $r->wikidata_id !== null ? 'https://www.wikidata.org/wiki/'.$r->wikidata_id : null];
            $items[] = $this->item(QuizTopic::FirstSeries, (string) $r->slug, (string) $r->name, (string) $r->name, '/materials/'.$r->slug, [$source], [
                'type' => (string) $r->type, 'from' => (int) $r->in_service_from,
            ]);
        }
        $perType = array_count_values(array_map(fn (array $i): string => $i['data']['type'], $items));

        return array_values(array_filter($items, fn (array $i): bool => $perType[$i['data']['type']] >= QuizSeries::OPTIONS));
    }

    /**
     * Un sujet du vivier.
     *
     * @param  list<array{provider: string, url: string|null}>  $sources
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function item(QuizTopic $topic, string $ref, string $label, string $title, ?string $url, array $sources, array $data): array
    {
        $id = $topic->value.':'.$ref;

        return [
            'id' => $id,
            'key' => FactQuiz::optionKey($id, $label),
            'label' => $label,
            'topic' => $topic->value,
            'group' => $topic->value,
            'difficulty' => $topic->difficulty(),
            'title' => $title,
            'url' => $url,
            'sources' => $sources,
            'data' => $data,
        ];
    }
}
