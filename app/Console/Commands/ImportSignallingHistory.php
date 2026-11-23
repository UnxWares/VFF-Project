<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingCorrectionsFile;
use App\Services\Signalling\SignallingHistoryExtractor;
use App\Services\Signalling\SignallingHistoryMap;
use App\Services\Signalling\SignallingSectionLocator;
use App\Services\Signalling\SignallingSupplementsFile;
use App\Services\Signalling\WikipediaArticleCache;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mises en service datées de signalisation (BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS,
 * KVB comme contrôle de vitesse) lues dans les articles Wikipédia des lignes, rangées dans
 * `line_signalling_events`. Historique par époque de la phase 13 : `line_signalling` (état actuel SNCF Réseau)
 * et la carte par ère ne sont pas touchés.
 *
 * Articles : `lines.wikipedia_title`, déjà vérifié par l'import des chronologies. Le wikitexte est mis en
 * cache dans storage/app/data/signalisation-historique/ (`.nobackup`) ; seul un article absent du cache est
 * demandé à Wikipédia (lecture seule, lots de 50, pause entre deux lots), sauf avec --offline.
 * Un article dont l'infobox annonce un autre code de ligne est écarté ; un article de plusieurs lignes ne donne
 * que des faits peu fiables (jamais montrés).
 *
 * Rejouable : un fait a une empreinte stable (ligne, système, date, portion) ; les faits d'une ligne lue qui ne
 * sont plus extraits sont retirés (cette table seulement, faits de Wikipédia seulement : ceux datés par d'autres
 * documents, `vff:import-signalling-sources`, ne sont jamais touchés).
 *
 * Corrections sourcées (`database/data/signalling_history_corrections.php`, SignallingCorrectionsFile) : un fait
 * de Wikipédia qu'une correction vise reste en base mais n'est plus montré (`confidence = 'hidden'`, colonne
 * `correction`) ; un fait visé introuvable est une erreur (la commande échoue après le bilan).
 *
 * Compléments relus (`database/data/signalling_history_wikipedia_supplements.php`, SignallingSupplementsFile) :
 * faits que l'extracteur laisse passer et qu'une personne a relus dans l'article, avec leur phrase. La phrase
 * est cherchée dans l'article lu (cache) : absente, la commande échoue, le fait n'est pas rangé. Le fait est
 * rangé comme les faits extraits (source Wikipédia, révision lue, origine `text`).
 *
 * Deuxième passe (soirée du 19/11/2026) : l'infobox et les tableaux sont lus aussi, la TVM d'une LGV est datée
 * de son ouverture (SignallingInfoboxReader), chaque portion nommée est placée sur la ligne par ses gares
 * d'extrémité et leurs PK calés (SignallingSectionLocator), puis la carte par ère est recalculée
 * (`line_signalling_history_map`, SignallingHistoryMap ; l'ère courante garde l'état de SNCF Réseau).
 *
 *   php artisan vff:import-signalling-history
 *   php artisan vff:import-signalling-history --line=956000 --dry-run -v
 *   php artisan vff:import-signalling-history --offline
 */
class ImportSignallingHistory extends Command
{
    protected $signature = 'vff:import-signalling-history
        {--line=* : limite à ces lignes (code, slug ou identifiant)}
        {--limit= : nombre maximal de lignes}
        {--offline : n\'appelle jamais Wikipédia, lit le cache seulement}
        {--refresh : redemande les articles même s\'ils sont en cache}
        {--cache-dir= : dossier du cache des articles}
        {--corrections= : fichier des corrections (défaut : database/data/signalling_history_corrections.php)}
        {--supplements= : fichier des compléments relus (défaut : database/data/signalling_history_wikipedia_supplements.php)}
        {--dry-run : n\'écrit rien en base, affiche le bilan (et les faits avec -v)}';

    protected $description = 'Range les mises en service de signalisation (BAL, BAPR, BM, CT, TVM, ETCS, KVB) datées dans les articles Wikipédia des lignes, les place sur le tracé et recalcule la carte par ère.';

    public function handle(WikimediaClient $wiki, SignallingHistoryExtractor $extractor, SignallingSectionLocator $locator, SignallingHistoryMap $map): int
    {
        $loaded = $this->corrections();
        if ($loaded === null) {
            return self::FAILURE;
        }
        $this->corrections = $loaded;
        $supplements = $this->supplements();
        if ($supplements === null) {
            return self::FAILURE;
        }
        $lines = $this->lines();
        if ($lines === []) {
            $this->error('Aucune ligne avec un article Wikipédia.');

            return self::FAILURE;
        }
        $this->info('Lignes avec un article : '.count($lines));

        $cache = new WikipediaArticleCache($wiki, $this->option('cache-dir') ?: WikipediaArticleCache::defaultDirectory());
        $titles = array_values(array_unique([...array_column($lines, 'wikipedia_title'), ...array_values(array_filter(array_column($supplements, 'article')))]));
        if (! $this->option('offline')) {
            $asked = $cache->fill($titles, (bool) $this->option('refresh'), fn (int $done, int $all) => $this->line("  Wikipédia : lot {$done}/{$all}"));
            $this->line("  Articles demandés à Wikipédia : {$asked} (le reste vient du cache)");
        }

        // Titres partagés comptés sur toutes les lignes, pas seulement celles choisies par --line ou --limit.
        $perTitle = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '!=', '')
            ->groupBy('wikipedia_title')->selectRaw('wikipedia_title, COUNT(*) AS n')->pluck('n', 'wikipedia_title')->map(fn ($n): int => (int) $n)->all();
        $readIds = [];
        $readRefs = [];
        $stats = ['quote_missing' => [], 'read' => 0, 'missing' => 0, 'rejected' => 0, 'with_facts' => 0, 'facts' => 0, 'removed' => 0, 'confidence' => [], 'system' => [], 'origin' => [], 'location' => []];
        $dry = (bool) $this->option('dry-run');

        foreach ($lines as $line) {
            $article = $cache->get($line['wikipedia_title']);
            if ($article === null) {
                $stats['missing']++;

                continue;
            }
            $codes = WikimediaClient::codesFromInfobox($article['content']);
            // Article d'une autre ligne (code d'infobox), ou titre partagé sans code pour trancher.
            if (($codes !== [] && ($line['code'] === null || ! in_array($line['code'], $codes, true)))
                || ($codes === [] && $perTitle[$line['wikipedia_title']] > 1)) {
                $stats['rejected']++;

                continue;
            }
            $stats['read']++;
            $readIds[] = $line['id'];
            array_push($readRefs, ...array_filter([$line['code'], $line['slug']]));
            $facts = $extractor->extract($article['content'], $article['title'], lgv: $line['is_lgv']);
            if (count($codes) > 1) {
                // Article de plusieurs lignes : on ne sait pas à laquelle le fait s'applique. Sauf la TVM d'une
                // LGV datée de son ouverture : elle équipe toutes les lignes de l'article.
                $facts = array_map(fn (array $f) => $f['origin'] === 'opening' ? $f : ['confidence' => 'low'] + $f, $facts);
            }
            // Les compléments relus de la ligne : phrase retrouvée dans l'article lu, sinon la commande échoue après le bilan.
            foreach ($supplements as $k => $s) {
                if (! in_array($s['line'], array_filter([$line['code'], $line['slug']]), true)) {
                    continue;
                }
                $source = $s['article'] === null ? $article : $cache->get($s['article']);
                if ($source === null || ! SignallingSupplementsFile::quoteIn($s['quote'], $extractor->plainText($source['content'], $source['title']))) {
                    $stats['quote_missing'][] = sprintf('%s %s %s : « %s »', $s['line'], $s['system'], $s['date'], mb_substr($s['quote'], 0, 60));

                    continue;
                }
                $this->supplemented[$k] = true;
                $facts[] = [
                    'system' => $s['system'], 'role' => $s['role'], 'date' => $s['date'], 'precision' => $s['precision'], 'scope' => $s['scope'],
                    'section_from' => $s['section_from'], 'section_to' => $s['section_to'], 'pk_start_m' => null, 'pk_end_m' => null,
                    'confidence' => $s['confidence'], 'origin' => 'text', 'excerpt' => $s['quote'],
                    'article' => ['title' => $source['title'], 'revid' => $source['revid']],
                ];
            }
            $rows = $this->rows($line, $article, $facts, $locator, ! $dry);
            if ($rows !== []) {
                $stats['with_facts']++;
            }
            foreach ($rows as $row) {
                $stats['facts']++;
                $stats['confidence'][$row['confidence']] = ($stats['confidence'][$row['confidence']] ?? 0) + 1;
                $stats['system'][$row['system']] = ($stats['system'][$row['system']] ?? 0) + 1;
                $stats['origin'][$row['origin']] = ($stats['origin'][$row['origin']] ?? 0) + 1;
                $location = $row['section_location'] ?? $row['scope'];
                $stats['location'][$location] = ($stats['location'][$location] ?? 0) + 1;
                if ($dry && $this->output->isVerbose()) {
                    $this->line(sprintf('  [%s] %-8s %s %-5s %-6s %s%s', $line['code'] ?? $line['slug'], $row['system'], $row['event_date'], $row['date_precision'], $row['confidence'],
                        $row['scope'] === 'section' ? trim(($row['section_from'] ?? '?').' -> '.($row['section_to'] ?? '?')).' ['.$row['section_location'].'] ' : $row['scope'].' ', $row['source_excerpt']));
                }
            }
            if (! $dry) {
                $stats['removed'] += $this->store($line['id'], $rows);
            }
        }

        // Import complet : les faits d'une ligne qui n'est plus lue (article absent, rejeté, titre retiré) disparaissent.
        if ($this->option('line') === [] && ! $this->option('limit')) {
            $orphans = DB::table('line_signalling_events')->where('source', 'wikipedia')->whereNotIn('line_id', $readIds ?: [0]);
            $stats['removed'] += $dry ? $orphans->count() : $orphans->delete();
        }

        // Une correction visant un fait de Wikipédia qui n'a pas été rencontré : l'article a changé, ou la ligne n'est plus lue.
        $unused = [];
        $whole = $this->option('line') === [] && ! $this->option('limit');
        $known = DB::table('lines')->whereIn('code', array_column($this->corrections, 'line'))->orWhereIn('slug', array_column($this->corrections, 'line'))->pluck('code', 'slug');
        foreach ($this->corrections as $i => $c) {
            // Une ligne absente de la base (copie partielle) n'est pas une erreur : il n'y a rien à corriger.
            $inBase = isset($known[$c['line']]) || $known->contains($c['line']);
            if ($c['target']['source'] === 'wikipedia' && ! isset($this->applied[$i]) && $inBase && ($whole || in_array($c['line'], $readRefs, true))) {
                $unused[] = SignallingCorrectionsFile::describe($c);
            }
        }

        $this->info(sprintf('%s%d lignes lues, %d avec des faits, %d faits (%d retirés : disparus de l\'article, ou ligne qui n\'est plus lue).',
            $dry ? '[simulation] ' : '', $stats['read'], $stats['with_facts'], $stats['facts'], $stats['removed']));
        $this->line(sprintf('  Sans article en cache : %d ; article d\'une autre ligne ou partagé : %d.', $stats['missing'], $stats['rejected']));
        ksort($stats['system']);
        $this->table(['Système', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['system']), $stats['system']));
        $this->table(['Fiabilité', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['confidence']), $stats['confidence']));
        ksort($stats['origin']);
        $this->table(['Origine', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['origin']), $stats['origin']));
        ksort($stats['location']);
        $this->table(['Portion', 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['location']), $stats['location']));

        if (! $dry) {
            $this->info('Carte par ère : '.$map->rebuild().' tracés (ères passées ; l\'ère courante garde l\'état de SNCF Réseau).');
        }

        foreach ($unused as $label) {
            $this->error("Correction sans fait visé : {$label} (l'article a changé ?)");
        }
        // Un complément dont la ligne a été lue mais dont la phrase n'est plus dans l'article, ou dont la ligne n'est pas lue du tout.
        $lost = $stats['quote_missing'];
        $inBaseRefs = DB::table('lines')->whereIn('code', array_column($supplements, 'line'))->orWhereIn('slug', array_column($supplements, 'line'))->get(['code', 'slug'])
            ->flatMap(fn ($l): array => [$l->code, $l->slug])->filter()->all();
        foreach ($supplements as $k => $s) {
            // Une ligne absente de la base (copie partielle) n'est pas une erreur : il n'y a rien à compléter.
            if (! isset($this->supplemented[$k]) && $whole && in_array($s['line'], $inBaseRefs, true) && ! in_array($s['line'], $readRefs, true)) {
                $lost[] = sprintf('%s %s %s : ligne non lue (article absent, partagé ou d\'une autre ligne)', $s['line'], $s['system'], $s['date']);
            }
        }
        foreach ($lost as $label) {
            $this->error("Complément sans phrase retrouvée : {$label} (l'article a changé ?)");
        }

        return $unused === [] && $lost === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @var list<array<string, mixed>> */
    private array $corrections = [];

    /** @var array<int, true> corrections appliquées pendant cette passe (indices) */
    private array $applied = [];

    /** @var array<int, true> compléments relus rangés pendant cette passe (indices) */
    private array $supplemented = [];

    /**
     * Compléments relus du fichier, ou null (erreur affichée) s'il est illisible ou contient un fait refusé.
     *
     * @return list<array<string, mixed>>|null
     */
    private function supplements(): ?array
    {
        $path = $this->option('supplements') ?: SignallingSupplementsFile::defaultPath();
        if (! is_file($path)) {
            $this->error("Fichier des compléments introuvable : {$path}");

            return null;
        }
        $entries = require $path;
        if (! is_array($entries)) {
            $this->error('Le fichier des compléments doit renvoyer une liste.');

            return null;
        }
        ['facts' => $facts, 'errors' => $errors] = SignallingSupplementsFile::parse(array_values($entries));
        foreach ($errors as $error) {
            $this->warn('  Refusé : '.$error);
        }
        if ($errors !== []) {
            $this->error(count($errors).' complément(s) refusé(s) : corrigez le fichier, rien n\'est écrit.');

            return null;
        }

        return $facts;
    }

    /**
     * Corrections du fichier, ou null (erreur affichée) s'il est illisible ou contient une correction refusée.
     *
     * @return list<array<string, mixed>>|null
     */
    private function corrections(): ?array
    {
        $path = $this->option('corrections') ?: SignallingCorrectionsFile::defaultPath();
        if (! is_file($path)) {
            $this->error("Fichier de corrections introuvable : {$path}");

            return null;
        }
        $entries = require $path;
        if (! is_array($entries)) {
            $this->error('Le fichier de corrections doit renvoyer une liste.');

            return null;
        }
        ['corrections' => $corrections, 'errors' => $errors] = SignallingCorrectionsFile::parse(array_values($entries));
        foreach ($errors as $error) {
            $this->warn('  Refusée : '.$error);
        }
        if ($errors !== []) {
            $this->error(count($errors).' correction(s) refusée(s) : corrigez le fichier, rien n\'est écrit.');

            return null;
        }

        return $corrections;
    }

    /** @return list<array{id: int, code: ?string, slug: string, wikipedia_title: string, is_lgv: bool}> */
    private function lines(): array
    {
        $query = DB::table('lines')->whereNotNull('wikipedia_title')->where('wikipedia_title', '!=', '')->orderBy('id');
        $refs = (array) $this->option('line');
        if ($refs !== []) {
            $query->where(fn ($q) => $q->whereIn('code', $refs)->orWhereIn('slug', $refs)
                ->orWhereIn('id', array_map('intval', array_filter($refs, fn ($r) => ctype_digit((string) $r) && strlen((string) $r) < 6))));
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query->get(['id', 'code', 'slug', 'wikipedia_title', 'is_lgv'])->map(fn ($l) => [
            'id' => (int) $l->id, 'code' => $l->code, 'slug' => $l->slug, 'wikipedia_title' => $l->wikipedia_title, 'is_lgv' => (bool) $l->is_lgv,
        ])->all();
    }

    /**
     * @param  array{id: int, code: ?string, slug: string}  $line
     * @param  array{title: string, revid: int}  $article
     * @param  list<array<string, mixed>>  $facts
     * @param  bool  $withGeometry  trace aussi la portion placée (pas en simulation)
     * @return list<array<string, mixed>>
     */
    private function rows(array $line, array $article, array $facts, SignallingSectionLocator $locator, bool $withGeometry): array
    {
        $now = now();
        $rows = [];
        foreach ($facts as $f) {
            // Un complément relu peut venir d'un autre article que celui de la ligne (article d'une gare, d'une LGV).
            $source = $f['article'] ?? $article;
            $url = WikimediaClient::permalink($source['title'], $source['revid']);
            $fingerprint = sha1(implode('|', [$line['id'], $f['system'], $f['date'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']]));
            $place = $locator->locate($line['id'], $f['scope'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']);
            $geometry = $withGeometry && $place['pk_start_m'] !== null ? $locator->geometry($line['code'], $place['pk_start_m'], (int) $place['pk_end_m']) : null;
            $correction = null;
            foreach ($this->corrections as $i => $c) {
                if (SignallingCorrectionsFile::matches($c, 'wikipedia', array_values(array_filter([$line['code'], $line['slug']])), $f['system'], $f['date'], $f['section_from'], $f['section_to'])) {
                    $correction = $c;
                    $this->applied[$i] = true;
                }
            }
            $rows[$fingerprint] = [
                'line_id' => $line['id'],
                'system' => $f['system'],
                'role' => $f['role'],
                'event_date' => $f['date'],
                'date_precision' => $f['precision'],
                'scope' => $f['scope'],
                'section_from' => $f['section_from'] !== null ? mb_substr($f['section_from'], 0, 255) : null,
                'section_to' => $f['section_to'] !== null ? mb_substr($f['section_to'], 0, 255) : null,
                'pk_start_m' => $f['pk_start_m'],
                'pk_end_m' => $f['pk_end_m'],
                'section_location' => $place['location'],
                'from_station_id' => $place['from_station_id'],
                'to_station_id' => $place['to_station_id'],
                'located_pk_start_m' => $place['pk_start_m'],
                'located_pk_end_m' => $place['pk_end_m'],
                'section_geom' => $geometry,
                'confidence' => $correction === null ? $f['confidence'] : 'hidden',
                'correction' => $correction === null ? null : SignallingCorrectionsFile::stored($correction),
                'origin' => $f['origin'],
                'source' => 'wikipedia',
                'source_title' => $source['title'],
                'source_revision' => $source['revid'],
                'source_url' => $url,
                'source_excerpt' => $f['excerpt'],
                'fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return array_values($rows);
    }

    /**
     * Écrit les faits d'une ligne et retire ceux qui ne sont plus extraits.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int nombre de faits retirés
     */
    private function store(int $lineId, array $rows): int
    {
        return DB::transaction(function () use ($lineId, $rows): int {
            if ($rows !== []) {
                $geometries = array_column($rows, 'section_geom', 'fingerprint');
                $rows = array_map(fn (array $r): array => array_diff_key($r, ['section_geom' => true]), $rows);
                DB::table('line_signalling_events')->upsert($rows, ['fingerprint'], [
                    'role', 'date_precision', 'scope', 'section_location', 'from_station_id', 'to_station_id', 'located_pk_start_m', 'located_pk_end_m',
                    'confidence', 'correction', 'origin', 'source_title', 'source_revision', 'source_url', 'source_excerpt', 'updated_at',
                ]);
                foreach ($geometries as $fingerprint => $geometry) {
                    DB::update("UPDATE line_signalling_events SET section_geom = CASE WHEN ?::text IS NULL THEN NULL ELSE ST_GeomFromEWKB(decode(?, 'hex')) END WHERE fingerprint = ?",
                        [$geometry, $geometry, $fingerprint]);
                }
            }

            return DB::table('line_signalling_events')->where('line_id', $lineId)->where('source', 'wikipedia')
                ->whereNotIn('fingerprint', array_column($rows, 'fingerprint') ?: [''])
                ->delete();
        });
    }
}
