<?php

namespace App\Console\Commands;

use App\Enums\LineEventType;
use App\Models\Line;
use App\Models\LineEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importe les métadonnées + événements datés d'une ligne ferroviaire
 * depuis un article Wikipédia FR (API parse → wikitext).
 *
 * Ne touche PAS aux line_segments existants : on enrichit Line + on crée
 * des LineEvent. Idempotent via updateOrCreate(line_id + event_date + type).
 */
class ImportWikipediaLine extends Command
{
    protected $signature = 'vff:import-wikipedia-line
        {url : URL complète de l\'article Wikipédia FR (ex: https://fr.wikipedia.org/wiki/Ligne_de_...)}
        {--dry-run : N\'écrit rien en base, affiche uniquement ce qui serait fait}';

    protected $description = 'Enrichit une Line + crée ses LineEvent à partir d\'un article Wikipédia FR';

    /** Mois FR → numéro (1..12) */
    private const MONTHS_FR = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3,
        'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10,
        'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $dryRun = (bool) $this->option('dry-run');

        if (! preg_match('#^https?://fr\.wikipedia\.org/wiki/(.+)$#u', $url, $m)) {
            $this->error('URL invalide. Attendu: https://fr.wikipedia.org/wiki/<Titre>');

            return self::FAILURE;
        }

        $title = rawurldecode($m[1]);
        $this->info("Article : {$title}");

        // 1. Récupération du wikitext
        $wikitext = $this->fetchWikitext($title);
        if ($wikitext === null) {
            $this->error('Impossible de récupérer le wikitext.');

            return self::FAILURE;
        }
        $this->line('  → '.mb_strlen($wikitext).' caractères de wikitext récupérés');

        // 2. Parsing infobox + texte
        $meta = $this->parseInfobox($wikitext);
        $body = $this->parseBody($wikitext);

        $code = $meta['code'] ?? null;
        $name = $meta['name'] ?? $this->humanizeTitle($title);
        $operator = $meta['operator'] ?? null;
        $description = $body['intro'] ?? null;

        $this->line("  → Nom détecté      : {$name}");
        $this->line('  → Code RFN détecté : '.($code ?? '(absent)'));
        $this->line('  → Opérateur        : '.($operator ?? '(absent)'));

        // 3. Trouver / créer la Line
        $line = $this->findOrCreateLine($name, $code, $operator, $description, $dryRun);

        if ($line) {
            $this->info("Ligne #{$line->id}, slug={$line->slug}");
        } elseif (! $dryRun) {
            $this->error('Ligne introuvable et non créable.');

            return self::FAILURE;
        } else {
            $this->warn('  → [dry-run] Pas de Line persistée : événements affichés sans persistance.');
        }

        // 4. Extraction des événements
        $events = $this->extractEvents($wikitext, $meta);
        $this->line('  → '.count($events).' événement(s) candidat(s)');

        // 5. Persistance
        $persisted = 0;
        $skipped = 0;
        foreach ($events as $ev) {
            $this->line(sprintf(
                '     • %s  %s  %s',
                str_pad($ev['type']->value, 16),
                $ev['event_date']->format('Y-m-d'),
                Str::limit($ev['description'], 70)
            ));

            if ($dryRun || ! $line) {
                $skipped++;

                continue;
            }

            LineEvent::updateOrCreate(
                [
                    'line_id' => $line->id,
                    'type' => $ev['type']->value,
                    'event_date' => $ev['event_date']->format('Y-m-d'),
                ],
                [
                    'description' => $ev['description'],
                    // Provenance normalisée (comme les autres importeurs Wikipédia), URL à part.
                    'source' => 'wikipedia',
                    'source_url' => $url,
                    'date_precision' => $ev['precision'],
                ]
            );
            $persisted++;
        }

        $this->newLine();
        $this->info(sprintf(
            'OK : %d événement(s) %s%s',
            $dryRun ? $skipped : $persisted,
            $dryRun ? 'à persister (dry-run)' : 'persisté(s)',
            $line ? ', ligne '.($line->wasRecentlyCreated ? 'créée' : 'mise à jour') : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Appelle l'API parse de Wikipédia FR et renvoie le wikitext brut.
     */
    private function fetchWikitext(string $title): ?string
    {
        $resp = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org; contact: contact@vff-project.org)')
            ->timeout(30)
            ->acceptJson()
            ->get('https://fr.wikipedia.org/w/api.php', [
                'action' => 'parse',
                'format' => 'json',
                'formatversion' => 2,
                'page' => $title,
                'prop' => 'wikitext',
                'redirects' => 1,
            ]);

        if (! $resp->ok()) {
            $this->warn("HTTP {$resp->status()} en récupérant {$title}");

            return null;
        }

        $data = $resp->json();
        if (isset($data['error'])) {
            $this->warn('Wikipedia: '.($data['error']['info'] ?? 'erreur inconnue'));

            return null;
        }

        // formatversion=2 : wikitext est une chaîne. Toute autre forme (réponse
        // inattendue, ancien format { "*": … }) est une erreur propre, pas un TypeError.
        $wikitext = $data['parse']['wikitext'] ?? null;

        return is_string($wikitext) ? $wikitext : null;
    }

    /**
     * Parse les champs principaux de l'infobox `{{Infobox Ligne ferroviaire ...}}`.
     *
     * @return array{name?: string, code?: string, operator?: string, opening?: int, closure?: int}
     */
    private function parseInfobox(string $wikitext): array
    {
        $out = [];

        // Nom complet : `nomligne = Montsecret - Vassy aux Maures` + surtitre = "Ligne de"
        if (preg_match('/\|\s*nomligne\s*=\s*((?:\[\[[^\]]*\]\]|[^\r\n|}])+)/u', $wikitext, $m)) {
            $nom = trim($m[1]);
            $surtitre = '';
            if (preg_match('/\|\s*surtitre\s*=\s*((?:\[\[[^\]]*\]\]|[^\r\n|}])+)/u', $wikitext, $sm)) {
                $surtitre = trim($sm[1]);
            }
            $full = trim($surtitre.' '.$nom);
            if ($full !== '') {
                $out['name'] = $this->cleanWiki($full);
            }
        }

        // Code RFN : essai sur plusieurs intitulés
        $codePatterns = [
            '/N°\s*ligne\s+RFN\s*=\s*([^\r\n|}]+)/iu',
            '/Numéro\s+(?:officiel|de\s+ligne)\s*=\s*([^\r\n|}]+)/iu',
            '/\|\s*numéro\s*=\s*([^\r\n|}]+)/iu',
        ];
        foreach ($codePatterns as $rx) {
            if (preg_match($rx, $wikitext, $m)) {
                $raw = $this->cleanWiki($m[1]);
                // Normaliser : "420 000" ou "420000" → garder la version texte
                if (preg_match('/(\d{3}[\s\x{202F}\x{00A0}]?\d{3}|\d{6}|\d{3})/u', $raw, $cm)) {
                    $out['code'] = preg_replace('/[\s\x{202F}\x{00A0}]+/u', ' ', trim($cm[1]));
                    break;
                }
            }
        }

        // Opérateur historique : on prend le dernier `concessionnaireN` non vide
        // (ou exploitants, ou propriétaire). Valeur = suite de liens [[…|…]] complets
        // ou de caractères hors « | » : un lien à libellé ([[Administration…|État]])
        // n'est plus coupé à son « | ».
        $operator = null;
        if (preg_match_all('/\|\s*concessionnaire(\d*)\s*=\s*((?:\[\[[^\]]*\]\]|[^\r\n|}])+)/u', $wikitext, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $val = $this->cleanWiki($c[2]);
                if ($val !== '') {
                    $operator = $val; // on garde le dernier rencontré (le plus récent)
                }
            }
        }
        if (! $operator && preg_match('/\|\s*exploitants?\s*=\s*((?:\[\[[^\]]*\]\]|[^\r\n|}])+)/u', $wikitext, $em)) {
            $operator = $this->cleanWiki($em[1]);
        }
        if ($operator) {
            $out['operator'] = $operator;
        }

        // Année de mise en service / fermeture (depuis l'infobox, en complément du body)
        if (preg_match('/\|\s*mise en service\s*=\s*(\d{4})/u', $wikitext, $m)) {
            $out['opening'] = (int) $m[1];
        }
        if (preg_match('/\|\s*fermeture\s*=\s*(\d{4})/u', $wikitext, $m)) {
            $out['closure'] = (int) $m[1];
        }

        return $out;
    }

    /**
     * Parse les sections narratives (Histoire, Chronologie) - renvoie l'intro propre
     * et la concaténation de tout le corps utile pour les regex d'événements.
     *
     * @return array{intro: ?string, body: string}
     */
    private function parseBody(string $wikitext): array
    {
        // Retire l'infobox `{{Infobox Ligne ferroviaire ...}}` (matching parenthèse simple)
        $text = preg_replace('/\{\{Infobox Ligne ferroviaire.*?^\}\}/smu', '', $wikitext) ?? $wikitext;

        // Premier paragraphe : intro après la dernière `}}` avant `==` ou jusqu'à `==`
        $intro = null;
        if (preg_match('/^(.{50,800}?)(?=\n==|\n\{\{)/su', ltrim($text), $m)) {
            $intro = $this->cleanWiki($m[1]);
        }

        return ['intro' => $intro, 'body' => $text];
    }

    /**
     * Cherche la ligne en base par code, sinon par nom approchant.
     * Si non trouvée → création (sauf dry-run).
     */
    private function findOrCreateLine(string $name, ?string $code, ?string $operator, ?string $description, bool $dryRun): ?Line
    {
        // 1. par code exact (et variantes 6 chiffres / espacé)
        $line = null;
        if ($code) {
            $variants = array_unique([
                $code,
                str_replace(' ', '', $code),
                preg_replace('/(\d{3})(\d{3})/', '$1 $2', str_replace(' ', '', $code)),
            ]);
            $line = Line::whereIn('code', $variants)->first();
        }

        // 2. par nom (similarité large)
        if (! $line) {
            $needle = trim(preg_replace('/^Ligne (?:de|du|d\')\s+/iu', '', $name));
            if ($needle !== '') {
                $line = Line::where('name', 'ilike', '%'.$needle.'%')
                    ->orderByRaw('length(name) asc')
                    ->first();
            }
        }

        if ($line) {
            $payload = array_filter([
                'description' => $description ?: null,
                'historical_operator' => $operator ?: null,
            ], fn ($v) => $v !== null && $v !== '');
            // On n'écrase pas un champ déjà rempli
            foreach ($payload as $k => $v) {
                if (! empty($line->{$k})) {
                    unset($payload[$k]);
                }
            }
            if (! empty($payload) && ! $dryRun) {
                $line->fill($payload)->save();
                $this->line('  → Line existante enrichie ('.implode(', ', array_keys($payload)).')');
            } elseif (! empty($payload)) {
                $this->line('  → [dry-run] Line existante à enrichir ('.implode(', ', array_keys($payload)).')');
            } else {
                $this->line('  → Line existante déjà complète');
            }

            return $line;
        }

        // Création
        $slug = Str::slug($name);
        $i = 1;
        $finalSlug = $slug;
        while (Line::where('slug', $finalSlug)->exists()) {
            $finalSlug = $slug.'-'.++$i;
        }

        $payload = [
            'slug' => $finalSlug,
            'name' => $name,
            'code' => $code,
            'historical_operator' => $operator,
            'description' => $description,
        ];

        if ($dryRun) {
            $this->line('  → [dry-run] Création Line : '.json_encode($payload, JSON_UNESCAPED_UNICODE));

            return null;
        }

        $line = Line::create($payload);
        $this->line("  → Line créée #{$line->id} (slug={$line->slug})");

        return $line;
    }

    /**
     * Extrait les LineEvent depuis le wikitext.
     *
     * @return list<array{type: LineEventType, event_date: Carbon, description: string, precision: ?string}>
     */
    private function extractEvents(string $wikitext, array $meta): array
    {
        $events = [];

        // Patterns : [type LineEvent, regex case-insensitive, label humain pour description]
        $patterns = [
            [LineEventType::Opening,        '/mis(?:e|es)?\s+en\s+service\s+(?:[^.]*?)(?:le\s+|en\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                              'Mise en service'],
            [LineEventType::Opening,        '/mise\s+en\s+service\s+(?:de\s+la\s+ligne\s+)?(?:le\s+)?\{\{date\|(\d{1,2}\s+\w+\s+\d{4})/iu',                          'Mise en service'],
            [LineEventType::Electrification, '/électrifi[ée]+e?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                          'Électrification'],
            [LineEventType::Electrification, '/électrification\s+(?:de\s+la\s+ligne\s+)?(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                    'Électrification'],
            // Fermeture à UN trafic : type dédié (avant : « closure », tout trafic, et les
            // motifs nus « voyageurs en 1880 » prenaient une OUVERTURE aux voyageurs pour
            // une fermeture). La fermeture générique exclut ces phrases.
            [LineEventType::ClosurePassenger, '/ferm(?:é|ée|és|ées|eture)\s+(?:[^.]*?)(?:aux?\s+voyageurs?[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',     'Fermeture aux voyageurs'],
            [LineEventType::ClosureFreight, '/ferm(?:é|ée|és|ées|eture)\s+(?:[^.]*?)(?:aux?\s+marchandises?[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',     'Fermeture aux marchandises'],
            [LineEventType::Closure,        '/ferm(?:é|ée|és|ées|eture)\s+(?![^.]*?aux?\s+(?:voyageurs?|marchandises?))(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu', 'Fermeture'],
            [LineEventType::Dismantling,    '/dépos[ée]+e?s?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                            'Dépose des voies'],
            [LineEventType::Dismantling,    '/dépose\s+(?:des\s+voies\s+)?(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                 'Dépose des voies'],
            [LineEventType::Dismantling,    '/démantel[éée]+e?s?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                        'Démantèlement'],
            [LineEventType::Modernization,  '/modernis[éée]+e?s?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                        'Modernisation'],
            [LineEventType::Reopening,      '/rouvert(?:e|s|es)?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                        'Réouverture'],
            [LineEventType::Reopening,      '/réouvert(?:e|ure)?\s+(?:[^.]*?)(?:en\s+|le\s+)(\d{1,2}\s+\w+\s+\d{4}|\d{4})/iu',                                        'Réouverture'],
        ];

        foreach ($patterns as [$type, $rx, $label]) {
            if (preg_match_all($rx, $wikitext, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // L'extrait peut contenir un {{date|...}} : on parse l'intérieur
                    $rawDate = $match[1];
                    if (preg_match('/\{\{date-?\|([^|}]+)/u', $rawDate, $dm)) {
                        $rawDate = $dm[1];
                    }
                    [$date, $precision] = $this->parseFrDate($rawDate) ?? [null, null];
                    if (! $date) {
                        continue;
                    }
                    $excerpt = $this->cleanWiki($match[0]);
                    $events[] = [
                        'type' => $type,
                        'event_date' => $date,
                        'precision' => $precision,
                        'description' => $label.' - '.Str::limit($excerpt, 250),
                    ];
                }
            }
        }

        // Fallback : si rien trouvé pour mise en service mais infobox en a une
        $hasOpening = collect($events)->contains(fn ($e) => $e['type'] === LineEventType::Opening);
        if (! $hasOpening && ! empty($meta['opening'])) {
            $events[] = [
                'type' => LineEventType::Opening,
                'event_date' => Carbon::create($meta['opening'], 1, 1),
                'precision' => 'year',
                'description' => 'Mise en service (infobox)',
            ];
        }
        // L'année « fermeture » de l'infobox ne dit pas quel trafic : elle ne sert que si le
        // texte n'a AUCUNE fermeture (sinon une fermeture aux voyageurs devenait totale).
        $hasClosure = collect($events)->contains(fn ($e) => in_array($e['type'], [LineEventType::Closure, LineEventType::ClosurePassenger, LineEventType::ClosureFreight], true));
        if (! $hasClosure && ! empty($meta['closure'])) {
            $events[] = [
                'type' => LineEventType::Closure,
                'event_date' => Carbon::create($meta['closure'], 1, 1),
                'precision' => 'year',
                'description' => 'Fermeture (infobox)',
            ];
        }

        // Dédoublonnage exact (même type + même date)
        $seen = [];
        $out = [];
        foreach ($events as $ev) {
            $key = $ev['type']->value.'|'.$ev['event_date']->format('Y-m-d');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $ev;
        }

        // Tri chronologique
        usort($out, fn ($a, $b) => $a['event_date']->getTimestamp() <=> $b['event_date']->getTimestamp());

        return $out;
    }

    /**
     * Parse une date FR : "13 février 1883" → [date, 'day'] ; "1883" → [1er janvier,
     * 'year'] ; jour impossible (« 31 février 1883 ») → [1er janvier, 'year'] (avant :
     * Carbon débordait au 3 mars, présenté comme une date exacte).
     *
     * @return array{0: Carbon, 1: string}|null
     */
    private function parseFrDate(string $raw): ?array
    {
        $raw = trim(mb_strtolower($raw));

        // "13 février 1883"
        if (preg_match('/^(\d{1,2})\s+([a-zéûôîùà]+)\s+(\d{4})$/u', $raw, $m)) {
            $day = (int) $m[1];
            $month = self::MONTHS_FR[$m[2]] ?? null;
            $year = (int) $m[3];
            if ($month && $year >= 1700 && $year <= 2100) {
                return checkdate($month, $day, $year)
                    ? [Carbon::create($year, $month, $day), 'day']
                    : [Carbon::create($year, 1, 1), 'year'];
            }
        }

        // "1883"
        if (preg_match('/^(\d{4})$/', $raw, $m)) {
            $year = (int) $m[1];
            if ($year >= 1700 && $year <= 2100) {
                return [Carbon::create($year, 1, 1), 'year'];
            }
        }

        return null;
    }

    /**
     * Nettoie les marqueurs wikitext basiques pour produire du texte lisible.
     */
    private function cleanWiki(string $s): string
    {
        // {{date|13 février 1883|...}} → 13 février 1883
        $s = preg_replace('/\{\{date-?\|([^|}]+)(?:\|[^}]*)?\}\}/u', '$1', $s) ?? $s;
        // Template ouvert non fermé (regex tronqué) : "{{date|13 février 1883" → "13 février 1883"
        $s = preg_replace('/\{\{date-?\|([^|}]+)/u', '$1', $s) ?? $s;
        // {{citation|x}} → x
        $s = preg_replace('/\{\{citation\|([^}]+)\}\}/iu', '$1', $s) ?? $s;
        // {{Unité|1.435|m}} → 1.435 m
        $s = preg_replace('/\{\{Unité\|([^|}]+)\|?([^|}]*)\}\}/iu', '$1 $2', $s) ?? $s;
        // Autres templates simples → vide
        $s = preg_replace('/\{\{[^{}]*\}\}/u', '', $s) ?? $s;
        // [[lien|texte]] → texte ; [[lien]] → lien
        $s = preg_replace('/\[\[([^\]|]+)\|([^\]]+)\]\]/u', '$2', $s) ?? $s;
        $s = preg_replace('/\[\[([^\]]+)\]\]/u', '$1', $s) ?? $s;
        // <ref>...</ref>
        $s = preg_replace('/<ref[^>]*>.*?<\/ref>/su', '', $s) ?? $s;
        $s = preg_replace('#<ref[^/]*/>#u', '', $s) ?? $s;
        // '''gras''' / ''italique''
        $s = preg_replace("/'''([^']+)'''/u", '$1', $s) ?? $s;
        $s = preg_replace("/''([^']+)''/u", '$1', $s) ?? $s;
        // Espaces multiples
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function humanizeTitle(string $title): string
    {
        $name = str_replace('_', ' ', $title);

        return ucfirst($name);
    }
}
