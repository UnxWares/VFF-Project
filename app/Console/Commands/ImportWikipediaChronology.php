<?php

namespace App\Console\Commands;

use App\Services\LineMerger;
use App\Services\RouteDiagramStations;
use App\Services\WikimediaClient;
use App\Services\WikipediaLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chronologies Wikipédia → line_events PAR PORTION (source = 'wikipedia').
 *
 * Pourquoi : la carte par décennie doit savoir quelle portion d'une ligne
 * ouvre, ferme aux voyageurs, au fret, est déclassée ou déposée, et quand.
 * Les articles frwiki « Ligne de X à Y » le disent dans leurs listes
 * « Chronologie », « Dates de déclassement », leurs tableaux et leur texte.
 *
 * Article de chaque ligne, dans l'ordre :
 *   1. lines.wikipedia_title (déjà connu) ;
 *   2. Wikidata : code ligne P1671 (pays France) → article frwiki ;
 *   3. index des infobox frwiki (champ « numéro » de tous les articles qui
 *      incluent {{Infobox Ligne ferroviaire}}) ;
 *   4. recherche par nom, avec garde-fous : titre de ligne contenant les
 *      deux extrémités (mots entiers : « Quimper » ≠ « Quimperlé »), et
 *      numéro d'infobox compatible avec le code.
 * Le titre trouvé est enregistré dans lines.wikipedia_title s'il manquait.
 *
 * Homonymes (2026-10-19) : une ligne SANS code (réseau secondaire, ligne
 * créée depuis un tracé) n'accepte pas l'article d'une autre ligne VFF :
 * infobox annonçant le code d'une ligne VFF, ou titre déjà porté par une
 * ligne à code (« Ligne de Besançon à Vesoul », voie métrique CFV, n'est pas
 * la ligne 856000 « Besançon-Viotte à Vesoul »). Si le titre enregistré est
 * ainsi écarté, il est retiré et les événements tirés de cet article sur
 * cette ligne sont supprimés (ils sont sur la ligne propriétaire).
 * Correction des données existantes : vff:merge-split-lines --only=homonymes.
 *
 * Événements : cf. App\Services\WikipediaLineParser (fermeture aux voyageurs
 * = closure_passenger, etc.). section_from / section_to = lieux tels
 * qu'écrits, PK si l'article les donne, section_resolution = 'pending'
 * (puis vff:resolve-event-sections). Portion explicitement inconnue
 * (infobox « partiel ») → 'unresolved' d'emblée.
 *
 * Portion d'un événement de référence (2026-10-19) : « Il est mis en service
 * le 16 mai 1993, un mois avant la mise sous tension du tronçon d'Hazebrouck
 * à Calais-Ville » ne rattache PAS Hazebrouck - Calais à la ligne de l'article
 * (cf. withoutReferenceSections()).
 *
 * Licence : Wikipédia est sous CC BY-SA → source_url = permalien (oldid) de
 * la révision lue, source_quote = courte citation (≤ 300 caractères).
 *
 * Idempotente : fingerprint stable (ligne + type + date + portion) ; les
 * événements wikipedia d'une ligne traitée qui ne sont plus extraits sont
 * supprimés (uniquement source = 'wikipedia').
 *
 *   php artisan vff:import-wikipedia-chronology
 *   php artisan vff:import-wikipedia-chronology --line=956000 --dry-run
 */
class ImportWikipediaChronology extends Command
{
    protected $signature = 'vff:import-wikipedia-chronology
        {--line= : Code SNCF, slug ou id d\'une ligne}
        {--limit= : Nombre maximal de lignes}
        {--only-missing : Seulement les lignes sans événement wikipedia}
        {--dry-run : N\'écrit rien, affiche les événements}
        {--refresh-index : Reconstruit l\'index des infobox et la table Wikidata (sinon cache)}';

    protected $description = 'Importe les chronologies Wikipédia (événements datés par portion) dans line_events.';

    public function __construct(private WikimediaClient $wiki, private WikipediaLineParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $q = DB::table('lines')->select('id', 'code', 'name', 'slug', 'wikipedia_title')->orderBy('id');
        if ($ref = $this->option('line')) {
            $q->where(fn ($w) => $w->where('code', $ref)->orWhere('slug', $ref)->orWhere('id', ctype_digit($ref) && strlen($ref) < 6 ? (int) $ref : 0));
        }
        if ($this->option('only-missing')) {
            $q->whereNotExists(fn ($s) => $s->from('line_events')->whereColumn('line_events.line_id', 'lines.id')->where('source', 'wikipedia'));
        }
        if ($limit = $this->option('limit')) {
            $q->limit((int) $limit);
        }
        $lines = $q->get();
        if ($lines->isEmpty()) {
            $this->error('Aucune ligne.');

            return self::FAILURE;
        }
        $this->info("Lignes à traiter : {$lines->count()}");

        // 1-3. Titres par code / titre connu
        $refresh = (bool) $this->option('refresh-index');
        $wd = $this->wiki->wikidataLineCodes($refresh);
        $this->line('  Wikidata : '.count($wd).' codes RFN avec article frwiki');
        $index = null;
        if ($lines->count() > 20 || $refresh) {
            $index = $this->wiki->infoboxIndex($refresh);
            $this->line('  Index des infobox : '.count($index['codes']).' codes, '.count($index['titles']).' articles');
        }

        $candidates = [];   // line_id => [[title, via], …]
        foreach ($lines as $l) {
            if ($l->wikipedia_title) {
                $candidates[$l->id][] = [$l->wikipedia_title, 'wikipedia_title'];
            }
            if ($l->code) {
                foreach ($wd[$l->code] ?? [] as $t) {
                    $candidates[$l->id][] = [$t, 'wikidata'];
                }
                foreach ($index['codes'][$l->code] ?? [] as $t) {
                    $candidates[$l->id][] = [$t, 'infobox'];
                }
                if (! $index && empty($candidates[$l->id])) {
                    foreach ($this->wiki->search('hastemplate:"Infobox Ligne ferroviaire" insource:"'.substr($l->code, 0, 3).' '.substr($l->code, 3).'"', 5) as $t) {
                        $candidates[$l->id][] = [$t, 'infobox'];
                    }
                }
            }
        }

        // Homonymes : codes et titres déjà portés par les lignes VFF à code.
        $lineCodes = [];
        $codedTitles = [];
        foreach (DB::table('lines')->whereNotNull('code')->get(['id', 'code', 'wikipedia_title']) as $c) {
            $lineCodes[$c->code] = (int) $c->id;
            if ($c->wikipedia_title) {
                $codedTitles[$this->parser->norm($c->wikipedia_title)] = (int) $c->id;
            }
        }

        // 4. Recherche par nom pour les autres
        foreach ($lines as $l) {
            if (! empty($candidates[$l->id])) {
                continue;
            }
            foreach ($this->searchByName($l) as $t) {
                $candidates[$l->id][] = [$t, 'recherche'];
            }
        }

        // Téléchargement groupé des articles candidats
        $titles = [];
        foreach ($candidates as $list) {
            foreach ($list as [$t]) {
                $titles[$t] = true;
            }
        }
        $pages = $this->wiki->fetchPages(array_keys($titles));
        $this->line('  Articles téléchargés : '.count($pages));

        $stats = ['lines_with_article' => 0, 'events' => 0, 'deleted' => 0, 'by_type' => [], 'by_via' => [], 'titles_saved' => 0];
        $bar = $this->option('dry-run') ? null : $this->output->createProgressBar($lines->count());
        foreach ($lines as $l) {
            $bar?->advance();
            $chosen = null;
            foreach ($candidates[$l->id] ?? [] as [$t, $via]) {
                $page = $pages[$t] ?? null;
                if (! $page) {
                    continue;
                }
                $codes = WikimediaClient::codesFromInfobox($page['content']);
                // Garde-fou : l'infobox annonce d'autres codes → mauvais article.
                // (y compris pour un wikipedia_title déjà présent : d'anciens outils
                // le remplissaient par recherche floue).
                if ($l->code && $codes && ! in_array($l->code, $codes, true)) {
                    $stats['rejected'][$via] = ($stats['rejected'][$via] ?? 0) + 1;

                    continue;
                }
                // Garde-fou homonymes : ligne sans code + article d'une autre ligne VFF (code d'infobox ou titre).
                if (! $l->code && (array_intersect_key($lineCodes, array_flip($codes))
                        || (($codedTitles[$this->parser->norm($page['title'])] ?? $l->id) !== $l->id))) {
                    $stats['rejected']['homonyme'] = ($stats['rejected']['homonyme'] ?? 0) + 1;
                    $homonym = $t;

                    continue;
                }
                $chosen = [$page, $via];
                break;
            }
            if (! $chosen && isset($homonym) && $l->wikipedia_title === $homonym && ! $this->option('dry-run')) {
                // Titre homonyme enregistré par erreur : retiré, avec les événements tirés de cet article.
                DB::table('lines')->where('id', $l->id)->update(['wikipedia_title' => null, 'updated_at' => now()]);
                $ids = DB::table('line_events')->where('line_id', $l->id)->where('source', 'wikipedia')->get(['id', 'source_url'])
                    ->filter(fn ($e) => LineMerger::wikipediaTitle($e->source_url) === $homonym)->pluck('id')->all();
                $stats['deleted'] += DB::table('line_events')->whereIn('id', $ids ?: [0])->delete();
            }
            unset($homonym);
            if (! $chosen) {
                continue;
            }
            [$page, $via] = $chosen;
            $stats['lines_with_article']++;
            $stats['by_via'][$via] = ($stats['by_via'][$via] ?? 0) + 1;

            $events = $this->parser->parseChronology($page['content'], $page['title']);
            $before = count($events);
            $events = $this->withoutReferenceSections($events, $stats);
            $stats['reference_dropped'] = ($stats['reference_dropped'] ?? 0) + $before - count($events);
            $url = WikimediaClient::permalink($page['title'], $page['revid']);

            if ($this->option('dry-run')) {
                $this->newLine();
                $this->info("[{$l->code}] {$l->name} → {$page['title']} ({$via}) · ".count($events).' événements');
                foreach ($events as $e) {
                    $this->line(sprintf('  %s %-5s %-18s %-7s %s%s%s', $e['date'], $e['precision'], $e['type'], $e['origin'],
                        $e['section_from'] || $e['section_to'] ? ($e['section_from'] ?? '?').' → '.($e['section_to'] ?? '?') : 'toute la ligne',
                        $e['pk_start_m'] !== null ? " [PK {$e['pk_start_m']}-{$e['pk_end_m']}]" : '',
                        $e['portion_unknown'] ? ' [portion inconnue]' : ''));
                    if ($this->output->isVerbose()) {
                        $this->line('      « '.mb_substr($e['source_quote'], 0, 160).' »');
                    }
                }
                foreach ($events as $e) {
                    $stats['by_type'][$e['type']] = ($stats['by_type'][$e['type']] ?? 0) + 1;
                }
                $stats['events'] += count($events);

                continue;
            }

            DB::transaction(function () use ($l, $page, $events, $url, &$stats) {
                if (! $l->wikipedia_title) {
                    DB::table('lines')->where('id', $l->id)->update(['wikipedia_title' => $page['title'], 'updated_at' => now()]);
                    $stats['titles_saved']++;
                }
                $fps = [];
                foreach ($events as $e) {
                    $fp = $this->fingerprint($l->id, $e);
                    if (isset($fps[$fp])) {
                        continue;
                    }
                    $fps[$fp] = true;
                    $unresolved = $e['portion_unknown'];
                    DB::statement(
                        'INSERT INTO line_events (line_id, type, event_date, date_precision, description, source, source_url,
                            source_quote, confidence, section_from, section_to, section_pk_start_m, section_pk_end_m,
                            section_resolution, section_resolution_note, fingerprint, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, \'wikipedia\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                         ON CONFLICT (fingerprint) DO UPDATE SET description = EXCLUDED.description,
                            source_url = EXCLUDED.source_url, source_quote = EXCLUDED.source_quote,
                            confidence = EXCLUDED.confidence, updated_at = NOW(),
                            -- portion devenue « inconnue » (règles durcies) : on retire la géométrie
                            section_resolution = CASE WHEN EXCLUDED.section_resolution = \'unresolved\' THEN \'unresolved\'
                                WHEN line_events.section_resolution_note LIKE \'Portion non précisée%\' THEN \'pending\'
                                ELSE line_events.section_resolution END,
                            section_resolution_note = CASE WHEN EXCLUDED.section_resolution = \'unresolved\' THEN EXCLUDED.section_resolution_note ELSE line_events.section_resolution_note END,
                            section_geom = CASE WHEN EXCLUDED.section_resolution = \'unresolved\' THEN NULL ELSE line_events.section_geom END,
                            section_length_m = CASE WHEN EXCLUDED.section_resolution = \'unresolved\' THEN NULL ELSE line_events.section_length_m END',
                        [$l->id, $e['type'], $e['date'], $e['precision'], $e['description'], $url,
                            $e['source_quote'], $e['confidence'], $e['section_from'], $e['section_to'],
                            $e['pk_start_m'], $e['pk_end_m'], $unresolved ? 'unresolved' : 'pending',
                            $unresolved ? 'Portion non précisée par la source ('.match (true) {
                                $e['origin'] === 'infobox' => 'infobox',
                                $e['section_to'] !== null => 'seule l\'extrémité d\'arrivée est citée',
                                str_starts_with($e['type'], 'reopening') => 'réouverture sans portion explicite',
                                default => 'portion citée dans la phrase mais non identifiée',
                            }.')' : null,
                            $fp],
                    );
                    $stats['by_type'][$e['type']] = ($stats['by_type'][$e['type']] ?? 0) + 1;
                    $stats['events']++;
                }
                // Gares du schéma de ligne (nom ↔ PK) : points de calage du résolveur.
                if (Schema::hasTable('wikipedia_line_stations')) {
                    DB::table('wikipedia_line_stations')->where('line_id', $l->id)->delete();
                    $rows = [];
                    // Article de plusieurs lignes (infobox « numéro » par code) : les seules gares de CETTE ligne.
                    foreach (RouteDiagramStations::forCode($this->parser->parseRouteDiagram($page['content']), $page['content'], $l->code, $this->parser) as $st) {
                        $rows[$st['name'].'|'.$st['pk_m']] = ['line_id' => $l->id, 'name' => mb_substr($st['name'], 0, 255), 'pk_m' => $st['pk_m'],
                            'icon' => $st['icon'], 'source_url' => $url, 'created_at' => now(), 'updated_at' => now()];
                    }
                    if ($rows) {
                        DB::table('wikipedia_line_stations')->insert(array_values($rows));
                    }
                    $stats['diagram_stations'] = ($stats['diagram_stations'] ?? 0) + count($rows);
                }
                // Nettoyage : événements wikipedia de CETTE ligne qui ne sont plus extraits.
                $stats['deleted'] += DB::table('line_events')->where('source', 'wikipedia')->where('line_id', $l->id)
                    ->when($fps, fn ($q) => $q->whereNotIn('fingerprint', array_keys($fps)))->delete();
            });
        }
        $bar?->finish();
        $this->newLine(2);

        arsort($stats['by_type']);
        $this->info("✓ {$stats['lines_with_article']}/{$lines->count()} lignes avec article · {$stats['events']} événements"
            .($this->option('dry-run') ? ' (dry-run)' : " · {$stats['deleted']} obsolètes supprimés · {$stats['titles_saved']} titres enregistrés"));
        $this->line('  Article trouvé via : '.json_encode($stats['by_via'], JSON_UNESCAPED_UNICODE)
            .(! empty($stats['rejected']) ? ' · candidats écartés (numéro d\'infobox ≠ code) : '.json_encode($stats['rejected'], JSON_UNESCAPED_UNICODE) : '')
            .(isset($stats['diagram_stations']) ? " · {$stats['diagram_stations']} gares à PK tirées des schémas de ligne" : ''));
        if (! empty($stats['reference_section'])) {
            $this->line("  Portions d'un événement de référence (« un mois avant le … du tronçon de X à Y ») : "
                ."{$stats['reference_section']} retirée(s), dont {$stats['reference_dropped']} événement(s) écarté(s) faute de sujet identifiable");
        }
        $this->table(['type', 'événements'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['by_type']), $stats['by_type']));

        return self::SUCCESS;
    }

    /**
     * Référence temporelle relative (« un mois avant », « deux ans après »,
     * « peu après », « la veille de »…) suivie du groupe nominal de l'événement
     * de référence (« la mise sous tension du tronçon de X à Y »).
     */
    private const TIME_REFERENCE = '/(?:,\s*(?:soit\s+)?|\s)(?:(?:un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|quinze|vingt|trente|quelques|plusieurs|\d+)\s+(?:jours?|semaines?|mois|ans|années|décennies?)\s+(?:avant|après)|(?:peu|juste|bien|longtemps|quelque\s+temps)\s+(?:avant|après)|(?:la|à\s+la)\s+veille|(?:le|au)\s+lendemain)\s+(?:d[eu]s?\s+|d[’\']\s*)?(?=(?:la|le|les|l[’\']|sa|son|ses|leur|leurs|celle|celui|cette|ce|cet)\b)/iu';

    /** Proposition dont le sujet est la ligne (même règle que le texte libre de WikipediaLineParser). */
    private const LINE_SUBJECT = '/\b(?:la|cette|toute la|l.ensemble de la) ligne\b|\bligne (?:est|fut|a été)\b|\bses (?:voies|rails)\b|(?:^|,\s*)elle\s/u';

    /**
     * Portion citée dans une RÉFÉRENCE TEMPORELLE (2026-10-19). Article « Ligne
     * de Boulogne-Ville à Calais-Maritime » : « Il est mis en service le 16 mai
     * 1993, un mois avant la mise sous tension du tronçon d'Hazebrouck à
     * Calais-Ville ». Le sujet est un poste d'aiguillage ; la portion est celle
     * de l'événement de référence (électrification de la ligne d'Hazebrouck à
     * Calais) : rattachée à la ligne, elle donnait une « ouverture d'Hazebrouck
     * à Calais-Ville » sur Boulogne - Calais.
     *
     * Règle : texte libre ou liste, portion nommée (sans PK) citée seulement
     * APRÈS un marqueur relatif (TIME_REFERENCE) qui suit la date de
     * l'événement, dans le même groupe (jusqu'à la virgule suivante). La
     * portion appartient à l'événement de référence : elle est retirée.
     * L'événement n'est gardé que si le reste de la proposition désigne la
     * ligne (LINE_SUBJECT), avec une portion inconnue (la phrase cite une
     * portion, ce n'est donc pas d'office toute la ligne) ; sinon il est écarté,
     * comme tout texte libre sans sujet identifiable.
     *
     * @param  list<array<string, mixed>>  $events  sortie de WikipediaLineParser::parseChronology()
     * @return list<array<string, mixed>>
     */
    public function withoutReferenceSections(array $events, array &$stats = []): array
    {
        $out = [];
        foreach ($events as $e) {
            $cut = $this->referenceCut($e);
            if ($cut === null) {
                $out[] = $e;

                continue;
            }
            $stats['reference_section'] = ($stats['reference_section'] ?? 0) + 1;
            $main = mb_strtolower(mb_substr((string) $e['source_quote'], 0, $cut));
            if (! preg_match(self::LINE_SUBJECT, $main)) {
                continue; // « Il est mis en service le … » : sujet inconnu (ici un poste d'aiguillage)
            }
            $label = WikipediaLineParser::TYPE_LABELS[$e['type']] ?? 'Événement';
            $heading = (string) ($e['heading'] ?? '');
            $out[] = ['section_from' => null, 'section_to' => null, 'portion_unknown' => true, 'confidence' => 'low',
                'description' => str_replace('—', '-', $label.' (Wikipédia, '.$e['origin']
                    .($heading !== '' ? ', « '.mb_substr($heading, 0, 80).' »' : ', introduction').')'),
            ] + $e;
        }

        return $out;
    }

    /**
     * Position (caractères) du marqueur de référence temporelle qui introduit
     * la portion de l'événement, ou null si la portion n'est pas dans une telle
     * référence.
     */
    private function referenceCut(array $e): ?int
    {
        $quote = (string) ($e['source_quote'] ?? '');
        if (! in_array($e['origin'] ?? null, ['texte', 'liste'], true) || $e['pk_start_m'] !== null
            || ($e['section_from'] === null && $e['section_to'] === null)
            || ! preg_match(self::TIME_REFERENCE, $quote, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $cut = mb_strlen(substr($quote, 0, $m[0][1]));
        $start = $cut + mb_strlen($m[0][0]);
        // Fin du groupe de référence : virgule, point-virgule, deux-points ou parenthèse suivants.
        $end = preg_match('/[,;:(]/u', mb_substr($quote, $start), $p, PREG_OFFSET_CAPTURE)
            ? $start + mb_strlen(substr(mb_substr($quote, $start), 0, $p[0][1])) : mb_strlen($quote);

        // La date de l'événement est dans la proposition principale, avant le marqueur.
        $datePos = null;
        foreach ($this->parser->findDates($quote) as $d) {
            if ($d['date'] === $e['date'] || ($e['precision'] === 'year' && $d['year'] === (int) substr((string) $e['date'], 0, 4))) {
                $datePos = $d['pos'];
                break;
            }
        }
        if ($datePos === null || $datePos >= $cut) {
            return null;
        }

        // Portion citée dans la référence, et pas avant elle.
        $lc = mb_strtolower($quote);
        $inside = false;
        foreach ([$e['section_from'], $e['section_to']] as $place) {
            if ($place === null) {
                continue;
            }
            $name = mb_strtolower(preg_replace('/^(?:Le|La|Les|L[’\'])\s*/u', '', (string) $place));
            $pos = $name !== '' ? mb_strpos($lc, $name) : false;
            if ($pos === false) {
                continue;
            }
            if ($pos < $cut) {
                return null;
            }
            $inside = $inside || $pos < $end;
        }

        return $inside ? $cut : null;
    }

    /**
     * Empreinte stable : ne dépend pas de la révision de l'article, seulement
     * du fait (ligne, type, date, portion).
     */
    private function fingerprint(int $lineId, array $e): string
    {
        return 'wikipedia:'.sha1(implode('|', [
            $lineId, $e['type'], $e['date'], $e['precision'],
            $this->parser->norm((string) $e['section_from']), $this->parser->norm((string) $e['section_to']),
            $e['pk_start_m'], $e['pk_end_m'],
        ]));
    }

    /**
     * Recherche par nom avec garde-fous : le titre doit être un article de
     * ligne et contenir les deux extrémités du nom de la ligne.
     *
     * @return list<string>
     */
    private function searchByName(object $line): array
    {
        $name = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $line->name));
        if ($name === '' || preg_match('/^Ligne \d+$/u', $name)) {
            return [];
        }
        $ends = $this->parser->lineEnds($name);
        if (count($ends) < 2) {
            $ends = [];
        }
        $query = $ends ? 'Ligne de '.$ends[0].' à '.$ends[1] : $name;
        $out = [];
        foreach ($this->wiki->search($query, 6) as $t) {
            if (! preg_match('/^(Ligne|LGV|Chemin de fer|Tramway|Raccordement|Voie ferrée)\b/iu', $t) || str_contains($t, 'homonymie')) {
                continue;
            }
            $nt = $this->parser->norm($t);
            if ($ends) {
                $ok = true;
                foreach ($ends as $e) {
                    $ne = $this->parser->norm(preg_replace('/^(Le|La|Les|L\')\s*/u', '', $e));
                    // Mots entiers : « Quimper » ne doit pas trouver « Quimperlé ».
                    if ($ne === '' || ! str_contains(' '.$nt.' ', ' '.$ne.' ')) {
                        $ok = false;
                    }
                }
                if ($ok) {
                    $out[] = $t;
                }
            } elseif ($nt === $this->parser->norm($name)) {
                $out[] = $t;
            }
        }

        return array_slice($out, 0, 2);
    }
}
