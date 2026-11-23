<?php

namespace App\Services\Quiz;

use App\Enums\QuizTopic;
use Illuminate\Support\Facades\DB;

/**
 * Sujets du jeu « Culture du rail » tirés des données les plus fiables et les plus récemment corrigées : dates au
 * jour des gares, systèmes de signalisation en cabine des LGV, matériel d'une ligne, accidents relus. Lecture
 * seule, requêtes bornées, sujets de même forme que ceux de FactPool.
 *
 *   - station_opening_year : grande gare (au moins 1 million de voyageurs en 2024) dont l'ouverture a été corrigée
 *     au jour d'après l'article de la gare, sans autre année dans ses événements, d'après un article « Gare… » (pas une station de tramway) ;
 *   - cab_signalling : LGV dont tous les événements de signalisation en cabine (toute confiance) nomment un seul
 *     système précis (TVM 300, TVM 430, ETCS niveau 1 ou 2), confirmé par un document officiel daté au jour (jamais un raccordement) ;
 *   - line_material : série citée comme assurant la ligne (confiance haute ou moyenne, aucune preuve douteuse),
 *     mauvaises réponses : séries du même type sans aucun lien avec la ligne ;
 *   - accident_year : accident relu (statut « ok »), publié, de plus de 25 ans, sans autre accident de même nature
 *     dans la même commune ; jamais de victimes, jamais de nom.
 */
final class DailyFactPool
{
    /** Voyageurs minimaux (2024) d'une « grande gare ». */
    public const BIG_STATION_PASSENGERS = 1_000_000;

    /** Systèmes de signalisation en cabine posés en question : valeur en base, clé de traduction (quiz.facts.cab.*). */
    public const CAB_SYSTEMS = ['TVM-300' => 'tvm_300', 'TVM-430' => 'tvm_430', 'ETCS_L1' => 'etcs_l1', 'ETCS_L2' => 'etcs_l2'];

    /** Années depuis lesquelles un accident est trop récent pour une question de jeu. */
    public const ACCIDENT_MIN_AGE = 25;

    /** Natures d'accident posées en question (les autres demandent plus de précautions que ce jeu n'en offre). */
    public const ACCIDENT_KINDS = ['collision', 'derailment', 'level_crossing', 'fire', 'structure_failure', 'runaway'];

    /** Fournisseurs dont les sources d'un accident sont citées dans la réponse. */
    private const ACCIDENT_PROVIDERS = ['wikipedia', 'wikipedia-en', 'wikipedia-de', 'wikidata', 'bea-tt'];

    /** Mauvaises réponses gardées par sujet de matériel (le niveau facile en tire parmi les douze premières). */
    private const MATERIAL_OTHERS = 14;

    /**
     * Les tables lues sont facultatives : si l'une manque, ses questions manquent seulement.
     *
     * @return list<array<string, mixed>>
     */
    public function build(bool $stations, bool $signalling, bool $materials, bool $accidents): array
    {
        return array_merge(
            $stations ? $this->stationOpenings() : [],
            $signalling ? $this->cabSignalling() : [],
            $materials ? $this->lineMaterials() : [],
            $accidents ? $this->accidents() : [],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stationOpenings(): array
    {
        // Les homonymes (gare dont le nom, sans casse ni espaces au bord, en désigne une autre) sont comptés une fois
        // pour toutes les gares : compter par gare candidate relisait les 9 400 noms deux cents fois (1,1 s à froid).
        $rows = DB::select(<<<'SQL'
            SELECT s.slug, s.name, s.city, e.event_date, e.source_url
            FROM station_events e
            JOIN stations s ON s.id = e.station_id
            JOIN station_attendances a ON a.station_id = s.id AND a.year = ? AND a.series = '2015-2024' AND a.match_method = 'uic' AND a.passengers >= ?
            JOIN (SELECT id, COUNT(*) OVER (PARTITION BY lower(btrim(name))) AS homonyms FROM stations) h ON h.id = s.id
            WHERE e.type = 'opening' AND e.source = 'correction' AND e.date_precision = 'day' AND e.source_url LIKE 'https://%.wikipedia.org/%' AND e.source_url ~ '(title=|/wiki/)Gare_'
              AND (SELECT COUNT(*) FROM station_events x WHERE x.station_id = s.id AND x.type = 'opening' AND x.source = 'correction') = 1
              AND NOT EXISTS (SELECT 1 FROM station_events x WHERE x.station_id = s.id AND x.type = 'opening' AND EXTRACT(YEAR FROM x.event_date) <> EXTRACT(YEAR FROM e.event_date))
              AND h.homonyms = 1
            ORDER BY s.id
        SQL, [FactPool::ATTENDANCE_YEAR, self::BIG_STATION_PASSENGERS]);

        $items = [];
        foreach ($rows as $r) {
            $date = (string) $r->event_date;
            $year = (int) substr($date, 0, 4);
            $items[] = FactPool::item(QuizTopic::StationOpeningYear, (string) $r->slug, (string) $year, (string) $r->name, '/stations/'.$r->slug, [['provider' => 'wikipedia', 'url' => (string) $r->source_url]], [
                'year' => $year, 'date' => $date, 'city' => $r->city, 'station' => (string) $r->name,
            ]);
        }

        return $items;
    }

    /**
     * LGV à un seul système de cabine : « quel système équipe cette ligne ? ». Un système générique (« TVM » ou
     * « ETCS » sans version) ou deux systèmes sur la même ligne écartent la ligne.
     *
     * @return list<array<string, mixed>>
     */
    private function cabSignalling(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT l.slug, l.name, s.system, s.event_date, s.date_precision, s.verification, s.source_url, s.source_title
            FROM line_signalling_events s JOIN lines l ON l.id = s.line_id
            WHERE s.role = 'cab' AND l.is_lgv AND l.name NOT ILIKE 'Raccordement%'
            ORDER BY l.slug, s.event_date, s.id
        SQL);
        $byLine = [];
        foreach ($rows as $r) {
            $byLine[$r->slug][] = $r;
        }

        $items = [];
        foreach ($byLine as $slug => $events) {
            $systems = array_values(array_unique(array_map(fn (object $e): string => (string) $e->system, $events)));
            if (count($systems) !== 1 || ! isset(self::CAB_SYSTEMS[$systems[0]])) {
                continue;
            }
            $official = array_values(array_filter($events, fn (object $e): bool => $e->verification === 'official' && $e->date_precision === 'day'));
            if ($official === []) {
                continue;
            }
            $first = $official[0];
            $key = self::CAB_SYSTEMS[$systems[0]];
            // Documents officiels (bilans LOTI, rapports de l'EPSF) : cités par leur titre, sans licence propre.
            $documents = [];
            foreach ($official as $e) {
                $documents[(string) $e->source_url] = ['provider' => 'document', 'name' => (string) $e->source_title, 'url' => (string) $e->source_url];
            }
            $item = FactPool::item(QuizTopic::CabSignalling, (string) $slug, $systems[0], (string) $events[0]->name, '/lines/'.$slug, array_slice(array_values($documents), 0, 3), [
                'system' => $systems[0], 'since' => (string) $first->event_date, 'line' => (string) $slug,
            ]);
            $item['trans'] = 'quiz.facts.cab.'.$key;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Une série citée par ligne (la même d'une série de questions à l'autre), avec les séries du même type qui n'ont
     * aucun lien avec la ligne : leur choix dépend de la proximité de leur mise en service.
     *
     * @return list<array<string, mixed>>
     */
    private function lineMaterials(): array
    {
        $links = DB::select(<<<'SQL'
            SELECT lm.line_id, l.slug AS line_slug, l.name AS line_name, m.id AS material_id, m.slug, m.name, m.type, m.in_service_from, lm.source, lm.source_url
            FROM line_materials lm
            JOIN lines l ON l.id = lm.line_id
            JOIN materials m ON m.id = lm.material_id
            WHERE lm.confidence IN ('high', 'medium') AND m.name !~ '^Q[0-9]+$' AND btrim(m.name) <> ''
              AND lm.source IN ('wikipedia', 'wikidata') AND lm.source_url IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(lm.evidence, '[]'::jsonb)) ev WHERE jsonb_exists(ev, 'doubt'))
            ORDER BY lm.line_id, m.id
        SQL);
        if ($links === []) {
            return [];
        }
        $linkedAny = [];
        foreach (DB::select('SELECT line_id, material_id FROM line_materials') as $r) {
            $linkedAny[(int) $r->line_id][(int) $r->material_id] = true;
        }
        $series = DB::select("SELECT id, name, type, in_service_from FROM materials WHERE name !~ '^Q[0-9]+\$' AND btrim(name) <> '' ORDER BY id");
        $byType = [];
        foreach ($series as $m) {
            $byType[$m->type][] = $m;
        }
        $byLine = [];
        foreach ($links as $l) {
            $byLine[(int) $l->line_id][] = $l;
        }

        $items = [];
        foreach ($byLine as $lineId => $list) {
            usort($list, fn (object $a, object $b): int => strcmp(hash('xxh128', $a->line_slug.'|'.$a->slug), hash('xxh128', $b->line_slug.'|'.$b->slug)));
            $l = $list[0];
            $taken = [mb_strtolower(trim((string) $l->name)) => true];
            foreach ($list as $x) {
                $taken[mb_strtolower(trim((string) $x->name))] = true;
            }
            $others = [];
            foreach ($byType[$l->type] ?? [] as $m) {
                $name = mb_strtolower(trim((string) $m->name));
                if (isset($linkedAny[$lineId][(int) $m->id]) || isset($taken[$name])) {
                    continue;
                }
                $taken[$name] = true;
                $others[] = ['label' => trim((string) $m->name), 'gap' => $l->in_service_from === null || $m->in_service_from === null ? 999 : abs((int) $m->in_service_from - (int) $l->in_service_from)];
            }
            usort($others, fn (array $a, array $b): int => [$a['gap'], $a['label']] <=> [$b['gap'], $b['label']]);
            $others = array_map(fn (array $o): string => $o['label'], array_slice($others, 0, self::MATERIAL_OTHERS));
            if (count($others) < QuizSeries::OPTIONS - 1) {
                continue;
            }
            $provider = $l->source === 'wikidata' ? 'wikidata' : 'wikipedia';
            $items[] = FactPool::item(QuizTopic::LineMaterial, $l->line_slug, trim((string) $l->name), (string) $l->line_name, '/materials/'.$l->slug, [['provider' => $provider, 'url' => (string) $l->source_url]], [
                'line' => (string) $l->line_slug, 'line_id' => $lineId, 'type' => (string) $l->type, 'others' => $others,
            ]);
        }

        return $items;
    }

    /**
     * Accidents relus et publiables d'au moins ACCIDENT_MIN_AGE ans, date au jour, seuls de leur nature dans la
     * commune. La question ne donne ni nom ni bilan : la nature et la commune seulement.
     *
     * @return list<array<string, mixed>>
     */
    private function accidents(): array
    {
        $kinds = '{'.implode(',', self::ACCIDENT_KINDS).'}';
        $rows = DB::select(<<<'SQL'
            SELECT a.id, a.slug, a.occurred_on, a.kind, a.commune_name, a.department_code
            FROM accidents a
            WHERE a.review_status = 'ok' AND a.alias_of_id IS NULL AND a.date_precision = 'day' AND a.commune_name IS NOT NULL
              AND a.kind = ANY(?::text[]) AND a.occurred_on <= ?::date
              AND (SELECT COUNT(*) FROM accidents b WHERE b.alias_of_id IS NULL AND b.commune_name = a.commune_name AND b.kind = a.kind) = 1
              AND EXISTS (SELECT 1 FROM accident_sources s WHERE s.accident_id = a.id AND s.provider = ANY(?::text[]))
            ORDER BY a.id
        SQL, [$kinds, now()->subYears(self::ACCIDENT_MIN_AGE)->toDateString(), '{'.implode(',', self::ACCIDENT_PROVIDERS).'}']);
        if ($rows === []) {
            return [];
        }
        $sources = [];
        foreach (DB::select(
            'SELECT accident_id, provider, url FROM accident_sources WHERE accident_id = ANY(?::bigint[]) AND provider = ANY(?::text[]) ORDER BY accident_id, role, id',
            ['{'.implode(',', array_map(fn (object $r): int => (int) $r->id, $rows)).'}', '{'.implode(',', self::ACCIDENT_PROVIDERS).'}'],
        ) as $s) {
            $sources[(int) $s->accident_id][] = ['provider' => (string) $s->provider, 'url' => (string) $s->url];
        }

        $items = [];
        foreach ($rows as $r) {
            $year = (int) substr((string) $r->occurred_on, 0, 4);
            $items[] = FactPool::item(QuizTopic::AccidentYear, (string) $r->slug, (string) $year, (string) $r->commune_name, '/accidents/'.$r->slug, array_slice($sources[(int) $r->id] ?? [], 0, 3), [
                'year' => $year, 'date' => (string) $r->occurred_on, 'kind' => (string) $r->kind, 'commune' => (string) $r->commune_name,
            ]);
        }

        return $items;
    }
}
