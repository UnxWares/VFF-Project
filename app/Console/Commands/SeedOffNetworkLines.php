<?php

namespace App\Console\Commands;

use App\Services\WikipediaLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lignes EXPLOITÉES aujourd'hui qu'aucune source de tracés de lignes ne décrit
 * (ni forme SNCF, ni archeoferroviaire, ni OHM) : partie RATP du RER (tunnel
 * central et branche de Marne-la-Vallée de la ligne A, ligne B entre Luxembourg
 * et Châtelet), tunnel sous la Manche, antenne CFL de Volmerange-les-Mines,
 * trains touristiques sur voie propre (baie de Somme, Saint-Trojan, Artouste…).
 * Sans elles, leurs gares restaient « incertaines » sur la carte de 2030
 * (gare ouverte selon ses sources, aucune ligne VFF à proximité).
 *
 * Déclarées dans database/data/lignes_hors_rfn.php, chacune avec ses sources
 * (permalien Wikipédia, QID Wikidata, citation). Rejouable : vff:rebuild-history
 * l'appelle en premier, avant le rattachement des tracés.
 *
 * Pour chaque entrée :
 *   1. LIGNE (lines, upsert par slug, alt_names.created_by = vff:seed-off-network-lines) :
 *      nom, catégorie (lines.network), écartement, code RFN d'une ancienne ligne
 *      SNCF déclassée, article Wikipédia, QID
 *      Wikidata (alt_names.wikidata_qid, repris par vff:import-wikidata-lines).
 *      Une entrée peut aussi viser une ligne EXISTANTE (clé « line » = son slug) :
 *      seuls sa forme déclarée et ses événements sont alors ajoutés ;
 *   2. FORME DÉCLARÉE (source_traces, source = 'declared', une par entrée) :
 *      union des tracés OSM et BD TOPO désignés par l'entrée (emprise + nom,
 *      exploitant, toponyme, ou identifiants de ways). Elle joue pour
 *      vff:match-current-network le rôle d'une forme SNCF (pseudo-code
 *      « L<id> ») : les tronçons BD TOPO et OSM de la ligne lui sont rattachés
 *      avec les mêmes seuils et contrôles (vote au plus proche : une voie RER A
 *      qui longe la ligne D va à la forme la plus proche). vff:build-line-references
 *      en tire le tracé de référence ; la forme elle-même n'y entre pas ;
 *   3. ÉVÉNEMENTS DATÉS (line_events, source = 'manual', fingerprint
 *      « hors-rfn:… », confiance haute) : ouvertures par section, fermetures,
 *      exploitation touristique (heritage_start + heritage_kind), avec citation
 *      exacte et permalien. Sans portion (from / to), l'événement vaut pour toute
 *      la ligne (section_resolution = whole_line, déclaré, jamais réinterprété
 *      d'après la citation) ; avec portion, il attend le résolveur
 *      (vff:resolve-event-sections), sauf portion DÉCLARÉE par une emprise
 *      (clé portion : tracés de la forme découpés à l'emprise, résolution
 *      « trace ») quand une extrémité n'est pas géocodable. Un événement retiré
 *      du fichier est supprimé.
 * Une ligne créée par cette commande et retirée du fichier est supprimée.
 *
 * Gares : rien à faire ici, vff:attach-stations les rattache par proximité du
 * tracé (puis vff:build-station-eras).
 *
 *   php artisan vff:seed-off-network-lines --dry-run
 *   php artisan vff:seed-off-network-lines --entry=hors-rfn-rer-a-nanterre-vincennes
 */
class SeedOffNetworkLines extends Command
{
    protected $signature = 'vff:seed-off-network-lines
        {--entry=* : slug d\'une entrée (répétable) : ne traite que ces entrées, sans nettoyage}
        {--file= : autre fichier de déclarations (tests ; défaut : database/data/lignes_hors_rfn.php)}
        {--dry-run : affiche ce qui serait écrit, sans rien écrire}';

    protected $description = 'Lignes exploitées hors RFN absentes des sources de tracés (RER RATP, tunnel sous la Manche, CFL, trains touristiques) : lignes, formes déclarées, dates sourcées.';

    public const CREATED_BY = 'vff:seed-off-network-lines';

    /** source_traces.source de la forme déclarée d'une ligne. */
    public const FORM_SOURCE = 'declared';

    public const EVENT_SOURCE = 'manual';

    public const FP_PREFIX = 'hors-rfn:';

    public const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier'];

    private const HERITAGE_KINDS = ['tourist_train', 'velorail', 'museum'];

    private const HERITAGE_LABELS = [
        'heritage_start' => 'Début de l\'exploitation touristique',
        'heritage_end' => 'Fin de l\'exploitation touristique',
    ];

    /** Couches OSM retenues par défaut pour une forme (voies, pas les voies de service). */
    private const OSM_LAYERS = ['rail', 'narrow_gauge', 'light_rail', 'preserved', 'funicular', 'disused'];

    private bool $dry = false;

    public function handle(): int
    {
        $entries = require $this->option('file') ?: database_path('data/lignes_hors_rfn.php');
        $this->dry = (bool) $this->option('dry-run');
        $only = array_values(array_filter((array) $this->option('entry')));
        $types = array_merge(array_keys(WikipediaLineParser::TYPE_LABELS), array_keys(self::HERITAGE_LABELS));

        $errors = 0;
        $seen = [];
        foreach ($entries as $i => $e) {
            if ($problem = $this->invalid($e, $types, $seen)) {
                $this->error("Entrée n° {$i} (".($e['slug'] ?? $e['line'] ?? '?')."): {$problem}");
                $errors++;
            }
            $seen[$this->key($e)] = true;
        }
        if ($errors) {
            return self::FAILURE;
        }

        $rows = [];
        $fps = [];
        foreach ($entries as $e) {
            $key = $this->key($e);
            foreach ($e['events'] ?? [] as $ev) {
                $fps[] = $this->fingerprint($key, $ev);
            }
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            $lineId = isset($e['line']) ? $this->existingLine($e['line']) : $this->upsertLine($e);
            if ($lineId === null) {
                $this->warn("  {$key} : ligne existante introuvable (slug {$e['line']}), entrée ignorée.");
                $rows[] = [$key, '-', 'introuvable', '', '', ''];

                continue;
            }
            [$members, $km] = $this->upsertForm($e, $key, $lineId);
            [$added, $updated] = $this->upsertEvents($e, $key, $lineId);
            $rows[] = [$key, $lineId ?: '(nouvelle)', $e['network'] ?? '(existante)', $members, $km, "{$added} ajouté(s), {$updated} à jour"];
        }

        if (! $only) {
            $this->cleanup($entries, $fps);
        }

        $this->table(['entrée', 'ligne', 'catégorie', 'tracés de la forme', 'km (forme)', 'événements'], $rows);
        $this->info(($this->dry ? '[dry-run] ' : '').count($rows).' entrée(s) traitée(s).');

        return self::SUCCESS;
    }

    /** Contrôle d'une entrée : message d'erreur ou null. */
    private function invalid(array $e, array $types, array $seen): ?string
    {
        $key = $this->key($e);
        if ($key === '') {
            return 'slug (ligne créée) ou line (ligne existante) obligatoire';
        }
        if (isset($seen[$key])) {
            return 'entrée en double';
        }
        if (! isset($e['line'])) {
            // Ligne du RFN sans forme SNCF (code connu, ex. 433000 Couterne - La Ferté-Macé) : slug « rfn-<code> »,
            // comme les lignes importées des formes SNCF.
            if (! preg_match('/^hors-rfn-[a-z0-9-]+$/', $e['slug']) && ! (isset($e['code']) && $e['slug'] === 'rfn-'.$e['code'])) {
                return 'slug attendu « hors-rfn-… » (minuscules, chiffres, tirets) ou « rfn-<code> » (ligne du RFN sans forme SNCF)';
            }
            if (empty($e['name']) || ! in_array($e['network'] ?? null, self::NETWORKS, true)) {
                return 'name et network ('.implode(', ', self::NETWORKS).') obligatoires';
            }
            if (empty($e['sources'])) {
                return 'au moins une source (sources) obligatoire';
            }
            if (isset($e['code']) && (! preg_match('/^\d{6}$/', $e['code'])
                || DB::table('lines')->where('code', $e['code'])->where('slug', '<>', $e['slug'])->exists())) {
                return "code {$e['code']} invalide ou déjà porté par une autre ligne";
            }
        }
        if (isset($e['trace']) && (count($e['trace']['bbox'] ?? []) !== 4)) {
            return 'trace.bbox = [lon min, lat min, lon max, lat max] obligatoire';
        }
        foreach ([$e['name'] ?? '', $e['description'] ?? ''] as $text) {
            if (preg_match('/[\x{2014}\x{2013}]|--/u', $text)) {
                return 'tiret long interdit dans les textes affichés';
            }
        }
        foreach ($e['events'] ?? [] as $n => $ev) {
            $date = $ev['date'] ?? '';
            if (! in_array($ev['type'] ?? null, $types, true)) {
                return "événement {$n} : type inconnu « ".($ev['type'] ?? '?').' »';
            }
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return "événement {$n} : date invalide « {$date} »";
            }
            if (! in_array($ev['precision'] ?? 'day', ['day', 'month', 'year'], true)) {
                return "événement {$n} : précision day | month | year";
            }
            if (empty($ev['source_url']) || empty($ev['quote'])) {
                return "événement {$n} : source_url et quote obligatoires";
            }
            if (str_starts_with($ev['type'], 'heritage') && ! in_array($ev['heritage_kind'] ?? 'tourist_train', self::HERITAGE_KINDS, true)) {
                return "événement {$n} : heritage_kind ".implode(' | ', self::HERITAGE_KINDS);
            }
            if (isset($ev['from']) !== isset($ev['to'])) {
                return "événement {$n} : from et to vont ensemble";
            }
            if (isset($ev['portion']) && (count($ev['portion']) !== 4 || ! isset($ev['from'], $e['trace']))) {
                return "événement {$n} : portion = [lon min, lat min, lon max, lat max], avec from / to et une forme (trace)";
            }
        }

        return null;
    }

    private function key(array $e): string
    {
        return (string) ($e['slug'] ?? $e['line'] ?? '');
    }

    private function existingLine(string $slug): ?int
    {
        $id = DB::table('lines')->where('slug', $slug)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Ligne créée (upsert par slug) : retourne son id (0 en --dry-run pour une ligne à créer). */
    private function upsertLine(array $e): int
    {
        $cur = DB::table('lines')->where('slug', $e['slug'])->first(['id', 'alt_names']);
        $alt = $cur ? (json_decode((string) $cur->alt_names, true) ?: []) : [];
        $alt = array_merge($alt, array_filter([
            'created_by' => self::CREATED_BY,
            'source' => self::CREATED_BY,
            'wikidata_qid' => $e['wikidata'] ?? null,
            'operator' => $e['operator'] ?? null,
            'labels' => $e['aliases'] ?? null,
            'sources' => $e['sources'],
        ], fn ($v) => $v !== null));
        $data = [
            'name' => $e['name'],
            // Code de ligne RFN d'une ancienne ligne SNCF déclassée (ex. 324000 Noyelles - Saint-Valery).
            'code' => $e['code'] ?? null,
            'network' => $e['network'],
            'gauge_mm_default' => $e['gauge_mm'] ?? null,
            'historical_operator' => $e['historical_operator'] ?? null,
            'wikipedia_title' => $e['wikipedia'] ?? null,
            'description' => $e['description'] ?? null,
            'alt_names' => json_encode($alt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];
        if ($this->dry) {
            return $cur ? (int) $cur->id : 0;
        }
        if ($cur) {
            DB::table('lines')->where('id', $cur->id)->update($data);

            return (int) $cur->id;
        }

        return (int) DB::table('lines')->insertGetId($data + ['slug' => $e['slug'], 'created_at' => now()]);
    }

    /**
     * Forme déclarée : union des tracés OSM / BD TOPO désignés par l'entrée.
     *
     * @return array{0: int, 1: float} nombre de tracés, longueur (km)
     */
    private function upsertForm(array $e, string $key, int $lineId): array
    {
        $t = $e['trace'] ?? null;
        if ($t === null) {
            return [0, 0.0];
        }
        [$where, $bind] = $this->formFilter($t);
        $from = "FROM source_traces t WHERE t.source IN ('osm', 'bdtopo') AND t.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326) AND ({$where})";
        $bind = array_merge(array_map('floatval', $t['bbox']), $bind);
        $row = DB::selectOne("SELECT COUNT(*) AS n, COALESCE(ST_Length(ST_Union(t.geom)::geography), 0) AS len,
                   jsonb_agg(t.source || ':' || t.source_ref ORDER BY t.source, t.source_ref) AS refs {$from}", $bind);
        $n = (int) $row->n;
        if ($n === 0) {
            $this->warn("  {$key} : aucun tracé OSM / BD TOPO ne correspond à la forme déclarée.");

            return [0, 0.0];
        }
        if (! $this->dry && $lineId > 0) {
            $props = json_encode(['created_by' => self::CREATED_BY, 'criteria' => $t, 'members' => json_decode((string) $row->refs, true)],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $name = $e['name'] ?? DB::table('lines')->where('id', $lineId)->value('name');
            DB::statement(<<<SQL
                INSERT INTO source_traces (source, source_ref, layer, name, properties, length_m, line_id, match_method, match_score,
                                           seen_at, created_at, updated_at, geom)
                SELECT ?, ?, 'exploitee', ?, CAST(? AS jsonb), ?, ?, 'declared', 1, NOW(), NOW(), NOW(), ST_Multi(ST_LineMerge(ST_Union(t.geom)))
                {$from}
                ON CONFLICT (source, source_ref) DO UPDATE SET
                    name = EXCLUDED.name, properties = EXCLUDED.properties, length_m = EXCLUDED.length_m,
                    line_id = EXCLUDED.line_id, match_method = 'declared', match_score = 1,
                    seen_at = NOW(), updated_at = NOW(), geom = EXCLUDED.geom
                SQL, array_merge([self::FORM_SOURCE, $key, $name, $props, (int) round((float) $row->len), $lineId], $bind));
        }

        return [$n, round((float) $row->len / 1000, 1)];
    }

    /**
     * Filtre SQL des tracés de la forme : ways OSM (couches, nom, exploitant, identifiants)
     * et tronçons BD TOPO (toponyme), sans les voies de service sauf demande.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function formFilter(array $t): array
    {
        $or = [];
        $bind = [];
        if (isset($t['osm'])) {
            $o = $t['osm'];
            $conds = ["t.source = 'osm'", 't.layer = ANY (CAST(? AS text[]))'];
            $bind[] = '{'.implode(',', $o['layers'] ?? self::OSM_LAYERS).'}';
            if (! ($o['service'] ?? false)) {
                $conds[] = "NOT jsonb_exists(t.properties, 'service')";
            }
            foreach (['name' => "COALESCE(t.properties->>'name', '')", 'operator' => "COALESCE(t.properties->>'operator', '')"] as $k => $col) {
                if (isset($o[$k])) {
                    $conds[] = "{$col} ~* ?";
                    $bind[] = $o[$k];
                }
            }
            if (isset($o['not_name'])) {
                $conds[] = "COALESCE(t.properties->>'name', '') !~* ?";
                $bind[] = $o['not_name'];
            }
            $or[] = '('.implode(' AND ', $conds).')';
        }
        if (isset($t['bdtopo'])) {
            $b = $t['bdtopo'];
            $conds = ["t.source = 'bdtopo'"];
            if (isset($b['layers'])) {
                $conds[] = 't.layer = ANY (CAST(? AS text[]))';
                $bind[] = '{'.implode(',', array_map(fn ($l) => '"'.$l.'"', $b['layers'])).'}';
            }
            if (isset($b['toponyme'])) {
                $conds[] = "COALESCE(t.properties->>'cpx_toponyme', t.name, '') ~* ?";
                $bind[] = $b['toponyme'];
            }
            $or[] = '('.implode(' AND ', $conds).')';
        }
        if (! empty($t['osm_ways'])) {
            $or[] = "(t.source = 'osm' AND t.source_ref = ANY (CAST(? AS text[])))";
            $bind[] = '{'.implode(',', array_map('intval', $t['osm_ways'])).'}';
        }
        $where = $or ? '('.implode(' OR ', $or).')' : 'FALSE';
        if (! empty($t['exclude_osm_ways'])) {
            $where .= " AND NOT (t.source = 'osm' AND t.source_ref = ANY (CAST(? AS text[])))";
            $bind[] = '{'.implode(',', array_map('intval', $t['exclude_osm_ways'])).'}';
        }

        return [$where, $bind];
    }

    /**
     * Événements datés de l'entrée (source manual, fingerprint hors-rfn:…).
     *
     * @return array{0: int, 1: int} ajoutés, mis à jour
     */
    private function upsertEvents(array $e, string $key, int $lineId): array
    {
        [$added, $updated] = [0, 0];
        foreach ($e['events'] ?? [] as $ev) {
            $fp = $this->fingerprint($key, $ev);
            $from = $ev['from'] ?? null;
            $to = $ev['to'] ?? null;
            $label = self::HERITAGE_LABELS[$ev['type']] ?? WikipediaLineParser::TYPE_LABELS[$ev['type']];
            $where = $from !== null ? (preg_match('/^[AEIOUYÉÈÊÂÎÔ]/u', $from) ? " d'" : ' de ')."{$from} à {$to}" : '';
            $data = [
                'line_id' => $lineId,
                'type' => $ev['type'],
                'event_date' => $ev['date'],
                'date_precision' => $ev['precision'] ?? 'day',
                'description' => $this->text($label.$where.(isset($ev['reason']) ? ' ('.$ev['reason'].')' : '')),
                'source' => self::EVENT_SOURCE,
                'source_url' => $ev['source_url'],
                'source_quote' => $this->text(mb_strimwidth($ev['quote'], 0, 300, '…')),
                'confidence' => $ev['confidence'] ?? 'high',
                'section_from' => $from,
                'section_to' => $to,
                'heritage_kind' => str_starts_with($ev['type'], 'heritage') ? ($ev['heritage_kind'] ?? 'tourist_train') : null,
                'updated_at' => now(),
            ];
            $cur = DB::table('line_events')->where('fingerprint', $fp)->first(['id', 'line_id', 'section_from', 'section_to', 'section_resolution']);
            // Sans portion : toute la ligne, déclaré (pas de lecture de la citation par le résolveur).
            $resolution = $from === null ? ['section_resolution' => 'whole_line', 'section_geom' => null, 'section_length_m' => null,
                'section_resolution_note' => 'Toute la ligne (événement déclaré dans database/data/lignes_hors_rfn.php)'] : null;
            if ($this->dry || $lineId === 0) {
                $cur ? $updated++ : $added++;

                continue;
            }
            if ($cur === null) {
                $id = DB::table('line_events')->insertGetId($data + ($resolution ?? ['section_resolution' => 'pending']) + ['fingerprint' => $fp, 'created_at' => now()]);
                $added++;
            } else {
                $id = (int) $cur->id;
                $moved = (int) $cur->line_id !== $lineId || $cur->section_from !== $from || $cur->section_to !== $to;
                if ($resolution !== null) {
                    $data += $resolution;
                } elseif (isset($ev['portion'])) {
                    // Portion déclarée : recalculée ci-dessous à chaque passage.
                } elseif ($moved || in_array($cur->section_resolution, ['whole_line', 'trace'], true)) {
                    $data += ['section_resolution' => 'pending', 'section_geom' => null, 'section_length_m' => null, 'source_trace_id' => null];
                }
                DB::table('line_events')->where('id', $id)->update($data);
                $updated++;
            }
            if (isset($ev['portion'])) {
                $this->declarePortion($e, $key, $id, $ev['portion']);
            }
        }

        return [$added, $updated];
    }

    /**
     * Portion DÉCLARÉE d'un événement (clé portion = emprise [lon min, lat min, lon max, lat max]) :
     * les tracés de la forme de l'entrée découpés à cette emprise. Sert quand une extrémité de la
     * section n'est pas géocodable par le résolveur (gare dont les sources se contredisent sur la
     * position, ex. Noisy-le-Grand - Mont d'Est), au lieu d'une gare voisine approchée. Résolution
     * « trace » (forme déclarée de la ligne en source_trace_id), que le résolveur ne recalcule pas.
     */
    private function declarePortion(array $e, string $key, int $eventId, array $bbox): void
    {
        [$where, $bind] = $this->formFilter($e['trace']);
        $env = 'ST_MakeEnvelope(?, ?, ?, ?, 4326)';
        $formId = DB::table('source_traces')->where('source', self::FORM_SOURCE)->where('source_ref', $key)->value('id');
        DB::update(<<<SQL
            UPDATE line_events le SET section_geom = p.g, section_length_m = ROUND(ST_Length(p.g::geography)),
                   section_resolution = 'trace', source_trace_id = ?,
                   section_resolution_note = 'Portion déclarée dans database/data/lignes_hors_rfn.php (tracés de la forme dans l''emprise '||?||')'
            FROM (
                SELECT ST_Multi(ST_LineMerge(ST_CollectionExtract(ST_Intersection(ST_Union(t.geom), {$env}), 2))) AS g
                FROM source_traces t
                WHERE t.source IN ('osm', 'bdtopo') AND t.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326) AND t.geom && {$env} AND ({$where})
            ) p
            WHERE le.id = ? AND p.g IS NOT NULL AND NOT ST_IsEmpty(p.g)
            SQL, array_merge([$formId, implode(', ', $bbox)], array_map('floatval', $bbox), array_map('floatval', $e['trace']['bbox']),
            array_map('floatval', $bbox), $bind, [$eventId]));
    }

    /** Lignes, formes et événements retirés du fichier. */
    private function cleanup(array $entries, array $fps): void
    {
        $keys = array_map(fn ($e) => $this->key($e), $entries);
        $created = array_values(array_filter(array_map(fn ($e) => $e['slug'] ?? null, $entries)));
        $staleLines = DB::table('lines')->whereRaw("alt_names->>'created_by' = ?", [self::CREATED_BY])
            ->when($created, fn ($q) => $q->whereNotIn('slug', $created))->pluck('slug', 'id');
        $staleForms = DB::table('source_traces')->where('source', self::FORM_SOURCE)
            ->when($keys, fn ($q) => $q->whereNotIn('source_ref', $keys));
        $staleEvents = DB::table('line_events')->where('source', self::EVENT_SOURCE)->where('fingerprint', 'like', self::FP_PREFIX.'%')
            ->when($fps, fn ($q) => $q->whereNotIn('fingerprint', $fps));
        $counts = [$staleLines->count(), $staleForms->count(), $staleEvents->count()];
        if (array_sum($counts) === 0) {
            return;
        }
        $this->line(sprintf('  Retirés du fichier : %d ligne(s) (%s), %d forme(s), %d événement(s)%s.', $counts[0],
            $staleLines->implode(', ') ?: '-', $counts[1], $counts[2], $this->dry ? ' [non supprimés : --dry-run]' : ''));
        if ($this->dry) {
            return;
        }
        $staleEvents->delete();
        $staleForms->delete();
        DB::table('lines')->whereIn('id', $staleLines->keys())->delete();
    }

    private function fingerprint(string $key, array $ev): string
    {
        return self::FP_PREFIX.sha1(implode('|', [$key, $ev['type'], $ev['date'], $ev['from'] ?? '', $ev['to'] ?? '']));
    }

    /** Pas de tiret long (ni demi-cadratin) dans les textes affichés. */
    private function text(string $s): string
    {
        return str_replace(['—', '–', '--'], '-', $s);
    }
}
