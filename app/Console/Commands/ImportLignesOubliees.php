<?php

namespace App\Console\Commands;

use App\Services\LineMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * lignes-oubliees.com (Loïc Dagallier) → événements datés PAR SECTION de ligne.
 *
 * Pourquoi : pour dessiner la carte décennie par décennie, il faut savoir QUELLE
 * PORTION d'une ligne ferme, et QUAND, et pour QUEL trafic. Les fiches de
 * lignes-oubliees donnent exactement ça : dates jj/mm/aaaa par section bornée
 * par des gares et souvent par des PK SNCF (ouverture, fermeture voyageurs,
 * fermeture marchandises, dépose, déclassement…), le numéro de ligne RFN à
 * 6 chiffres et la liste des gares avec le PK de leur bâtiment voyageurs.
 * Deux PDF complètent : fermetures (décisions RFF / SNCF Réseau depuis 1999)
 * et déclassements / retranchements (JO + BO RFF, 1941-1951 et 1975-2012).
 *
 * Droits : la page « À propos » autorise la reproduction des INFORMATIONS à
 * condition d'en indiquer l'origine (source_url sur chaque événement ; cf.
 * database/data/source_rights.php). Les photos, profils et
 * textes rédigés ne sont PAS repris : seules les dates, sections, PK et gares.
 *
 * Étapes :
 *   1. crawl POLI (1 requête / 5 s, User-Agent VFF-Bot, cache local dans
 *      storage/app/sources/lignes-oubliees, reprise : ce qui est en cache n'est
 *      jamais re-téléchargé) - inventaire, 187 fiches, 2 PDF ;
 *   2. lignes : rattachement par code RFN (« 476 000 » → '476000') ; un code
 *      absent de `lines` (ligne fermée avant le RFN actuel) crée la ligne
 *      (slug lo-<code>, network = principal, alt_names.name_source) ;
 *   3. line_events : un événement par (section, type, date), PK en mètres,
 *      bornes nommées, section_resolution = 'pending' (le résolveur de sections
 *      les projettera sur le tracé), fingerprint stable → rejouable ;
 *   4. source_stations : gares + PK de chaque fiche ;
 *   5. PDF (texte via pdftotext -layout, mis en cache) : événements
 *      supplémentaires, sans doublonner ceux des fiches ; numéro de ligne
 *      mal recopié (un chiffre, absent du référentiel SNCF) corrigé d'après
 *      les autres lignes du document (cf. fixPdfCodes()).
 *
 * Rejouable : upsert par fingerprint, puis suppression des seuls événements
 * / gares source='lignes-oubliees' qui ne sont plus produits.
 */
class ImportLignesOubliees extends Command
{
    protected $signature = 'vff:import-lignes-oubliees
        {--from-cache : N\'utilise que le cache local, aucune requête réseau}
        {--fetch-only : Remplit le cache (crawl poli) sans rien importer}
        {--page=* : Limite aux fiches données (ex. --page=concarneau), sans les PDF}
        {--no-pdf : Ignore les PDF fermetures / déclassements}
        {--dry-run : Analyse et affiche, sans écrire en base}';

    protected $description = 'lignes-oubliees.com → line_events par section (PK), source_stations, lignes fermées hors RFN';

    public const SOURCE = 'lignes-oubliees';

    private const BASE = 'https://www.lignes-oubliees.com/';

    private const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Délai minimal entre deux requêtes (politesse). */
    private const DELAY_S = 5;

    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    /** Saisons : date ramenée à l'année, confiance basse. */
    private const SEASONS = ['printemps', 'ete', 'automne', 'hiver'];

    private float $lastRequestAt = 0.0;

    /** @var array<string, int> libellés de rubrique non reconnus → nb */
    private array $unknownLabels = [];

    /** @var list<string> */
    private array $skipped = [];

    public function handle(): int
    {
        // Une instance de commande sert à tous les appels du processus : état remis à zéro.
        [$this->unknownLabels, $this->skipped, $this->codeCorrections, $this->nameIndex, $this->ficheKeys] = [[], [], [], [], []];
        $this->stats = ['lines_matched' => 0, 'lines_created' => 0, 'lines_existing_lo' => 0, 'events' => [], 'pdf' => [], 'stations' => 0];
        @mkdir($this->cacheDir().'/fiches', 0775, true);

        // ── 1. Inventaire ───────────────────────────────────────────────────
        $invHtml = $this->cached('inventaire.html', 'index.php?page=inventaire&debut=0&limite=376');
        if ($invHtml === null) {
            $this->error('Inventaire indisponible.');

            return self::FAILURE;
        }
        $inventory = $this->parseInventory($invHtml);
        $this->info(count($inventory).' ligne(s) à l\'inventaire, '
            .count(array_filter($inventory, fn ($r) => $r['page'] !== null)).' avec fiche détaillée');

        $only = array_values(array_filter((array) $this->option('page')));
        $partial = $only !== [];

        // ── 2. Fiches (crawl poli + cache) ──────────────────────────────────
        $fiches = [];
        $missing = 0;
        foreach ($inventory as $row) {
            if ($row['page'] === null || ($partial && ! in_array($row['page'], $only, true))) {
                continue;
            }
            $html = $this->cached("fiches/{$row['page']}.html", "index.php?page={$row['page']}");
            if ($html === null) {
                $missing++;

                continue;
            }
            if ($this->option('fetch-only')) {
                continue;
            }
            $fiche = $this->parseFiche($html, $row);
            $fiches[$row['page']] = $fiche;
        }

        // ── PDF ─────────────────────────────────────────────────────────────
        $pdfText = [];
        if (! $this->option('no-pdf') && ! $partial) {
            foreach (['fermetures', 'declassements'] as $pdf) {
                $this->cached("{$pdf}.pdf", "fichiers/{$pdf}.pdf");
                $pdfText[$pdf] = $this->pdfText($pdf);
            }
        }

        if ($this->option('fetch-only')) {
            $this->info('Cache : '.(count(array_filter($inventory, fn ($r) => $r['page'] !== null)) - $missing).' fiche(s) disponibles, '
                ."{$missing} manquante(s) (relancer pour reprendre).");

            return $missing === 0 ? self::SUCCESS : self::FAILURE;
        }
        if ($missing > 0) {
            $this->warn("{$missing} fiche(s) absente(s) du cache : relancer sans --from-cache pour les récupérer.");
        }

        $nbEvents = array_sum(array_map(fn ($f) => count($f['events']), $fiches));
        $nbStations = array_sum(array_map(fn ($f) => count($f['stations']), $fiches));
        $this->info(count($fiches)." fiche(s) analysée(s) : {$nbEvents} événement(s), {$nbStations} gare(s)");

        // ── PDF → lignes de tableau ─────────────────────────────────────────
        $pdfRows = [];
        if (isset($pdfText['fermetures'])) {
            $pdfRows = array_merge($pdfRows, $this->parseFermeturesPdf($pdfText['fermetures']));
        }
        if (isset($pdfText['declassements'])) {
            $pdfRows = array_merge($pdfRows, $this->parseDeclassementsPdf($pdfText['declassements']));
        }
        if ($pdfRows !== []) {
            $this->info(count($pdfRows).' ligne(s) de tableau extraites des PDF');
            $pdfRows = $this->fixPdfCodes($pdfRows);
            foreach ($this->codeCorrections as $code => $list) {
                foreach ($list as $c) {
                    $this->line("  code corrigé : {$c['cited']} → {$code} ({$c['file']}.pdf, {$c['ligne']}, {$c['section']}) : {$c['proof']}");
                }
            }
        }

        $this->buildNameIndex($inventory, $fiches, $pdfRows);

        if ($this->option('dry-run')) {
            $this->dumpDry($fiches, $pdfRows);

            return self::SUCCESS;
        }

        // ── 3. Écriture ─────────────────────────────────────────────────────
        DB::transaction(function () use ($inventory, $fiches, $pdfRows, $partial): void {
            $lineIds = $this->resolveLines($inventory, $fiches, $pdfRows, $partial);
            if (! $partial && $pdfRows !== []) {
                $this->applyCodeCorrections($lineIds);
            }
            $fps = $this->writeFicheEvents($fiches, $lineIds);
            $stationRefs = $this->writeStations($fiches, $lineIds);
            if ($pdfRows !== []) {
                $fps = array_merge($fps, $this->writePdfEvents($pdfRows, $lineIds));
            }

            // Nettoyage limité aux documents effectivement relus : une fiche absente
            // du cache ou un PDF ignoré (--no-pdf) ne perd pas ses événements.
            $urls = array_values(array_map(fn ($f) => $f['url'], $fiches));
            foreach (array_unique(array_column($pdfRows, 'file')) as $file) {
                $urls[] = self::BASE.'fichiers/'.$file.'.pdf';
            }
            $stale = DB::table('line_events')->where('source', self::SOURCE)->whereIn('source_url', $urls)
                ->whereNotIn('fingerprint', $fps)->delete();
            $staleSt = DB::table('source_stations')->where('source', self::SOURCE)
                ->whereIn(DB::raw("split_part(source_ref, ':', 1)"), array_keys($fiches))
                ->whereNotIn('source_ref', $stationRefs)->delete();
            $this->line("  nettoyage : {$stale} événement(s) et {$staleSt} gare(s) obsolètes supprimés");
        });

        $this->report();

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════
    //  Crawl poli + cache
    // ════════════════════════════════════════════════════════════════════════

    private function cacheDir(): string
    {
        return storage_path('app/sources/lignes-oubliees');
    }

    /** Contenu en cache, sinon téléchargé (sauf --from-cache) puis mis en cache. */
    private function cached(string $rel, string $urlPath): ?string
    {
        $path = $this->cacheDir().'/'.$rel;
        if (is_file($path) && filesize($path) > 0) {
            return file_get_contents($path);
        }
        if ($this->option('from-cache')) {
            $this->warn("  absent du cache : {$rel}");

            return null;
        }
        $body = $this->fetch(self::BASE.$urlPath);
        if ($body === null) {
            return null;
        }
        // Écriture atomique : un crawl interrompu ne laisse jamais de fichier tronqué.
        file_put_contents($path.'.part', $body);
        rename($path.'.part', $path);

        return $body;
    }

    /** GET poli : ≥ 5 s entre requêtes, backoff croissant sur 429/5xx. */
    private function fetch(string $url): ?string
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $wait = self::DELAY_S * $attempt - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                Sleep::usleep((int) ($wait * 1e6));
            }
            $this->lastRequestAt = microtime(true);
            try {
                $resp = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'fr-FR,fr;q=0.9',
                ])->timeout(60)->get($url);
            } catch (\Throwable $e) {
                $this->warn("  erreur réseau ({$attempt}) {$url} : ".$e->getMessage());

                continue;
            }
            if ($resp->ok() && strlen($resp->body()) > 0) {
                $this->line("  ← {$url} (".strlen($resp->body()).' o)');

                return $resp->body();
            }
            $this->warn("  HTTP {$resp->status()} ({$attempt}) {$url}");
            if (! in_array($resp->status(), [429, 500, 502, 503, 504], true)) {
                return null;
            }
        }

        return null;
    }

    /**
     * Texte « mis en page » d'un PDF : cache <pdf>.txt, sinon pdftotext -layout
     * s'il est installé (absent de l'image PHP : le .txt est alors produit sur
     * l'hôte par `pdftotext -layout fermetures.pdf fermetures.txt`).
     */
    private function pdfText(string $pdf): ?string
    {
        $txt = $this->cacheDir()."/{$pdf}.txt";
        $src = $this->cacheDir()."/{$pdf}.pdf";
        if (is_file($src) && (! is_file($txt) || filemtime($txt) < filemtime($src))) {
            $bin = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
            if ($bin !== '') {
                exec(escapeshellarg($bin).' -layout '.escapeshellarg($src).' '.escapeshellarg($txt));
            }
        }
        if (! is_file($txt)) {
            $this->warn("  {$pdf}.txt absent et pdftotext indisponible : PDF ignoré "
                ."(lancer « pdftotext -layout {$pdf}.pdf {$pdf}.txt » dans le dossier de cache)");

            return null;
        }

        return file_get_contents($txt);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  Analyse HTML
    // ════════════════════════════════════════════════════════════════════════

    /** @return list<array{name: string, code: ?string, length_m: ?int, regions: list<string>, page: ?string}> */
    private function parseInventory(string $html): array
    {
        $rows = [];
        preg_match_all('#<tr>(.*?)</tr>#s', $html, $trs);
        foreach ($trs[1] as $tr) {
            preg_match_all('#<td[^>]*>(.*?)</td>#s', $tr, $tds);
            if (count($tds[1]) < 4) {
                continue;
            }
            $page = preg_match('#href="index\.php\?page=([^"&]+)"#', $tds[1][0], $m) ? $m[1] : null;
            $len = $this->text($tds[1][2]);
            $rows[] = [
                'name' => $this->text($tds[1][0]),
                'code' => $this->normCode($this->text($tds[1][1])),
                'length_m' => preg_match('/^\d+(,\d+)?$/', $len) ? (int) round((float) str_replace(',', '.', $len) * 1000) : null,
                'regions' => array_values(array_filter(array_map('trim', explode('/', $this->text($tds[1][3]))))),
                'page' => $page,
            ];
        }

        return $rows;
    }

    /**
     * Fiche détaillée → caractéristiques, événements par section, gares.
     *
     * @param  array{name: string, code: ?string, page: ?string}  $row
     */
    private function parseFiche(string $html, array $row): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $url = self::BASE.'index.php?page='.$row['page'];

        $title = $this->text($xp->query('//div[@id="contenu"]/h1')->item(0)?->textContent ?? '');

        // Caractéristiques (dt → liste de dd)
        $carac = [];
        foreach ($this->dlPairs($xp, 'caracteristiques') as [$label, $dd]) {
            $carac[trim(rtrim($this->norm($label), ' :'))][] = $this->text($dd->textContent);
        }
        // Code RFN : celui de l'inventaire (tableau trié par numéro) prime ; la fiche
        // a des coquilles (vic : « 095 000 » = Longuyon - Pagny, inventaire « 098 000 »).
        $ficheCode = $this->normCode($carac['numero de la ligne'][0] ?? '');
        $code = $row['code'] ?? $ficheCode;
        if ($ficheCode !== null && $row['code'] !== null && $ficheCode !== $row['code']) {
            $this->warn("  {$row['page']} : numéro {$ficheCode} sur la fiche ≠ {$row['code']} à l'inventaire → {$row['code']} retenu");
        }
        $length = null;
        if (preg_match('/([\d\s]+(?:,\d+)?)\s*km/u', $carac['longueur'][0] ?? '', $m)) {
            $length = (int) round((float) str_replace([' ', ','], ['', '.'], $m[1]) * 1000);
        }

        // Gares + PK (paragraphes entre <h2>Les gares</h2> et le <h2> suivant)
        $stations = $this->parseStations($xp);

        // Historique
        $raw = [];
        foreach ($this->dlPairs($xp, 'historique') as [$label, $dd]) {
            $spanNode = $xp->query('./span', $dd)->item(0);
            $span = $spanNode ? $this->text($spanNode->textContent) : '';
            $full = $this->text($dd->textContent);
            $rest = $span !== '' && str_starts_with($full, $span) ? trim(substr($full, strlen($span))) : $full;
            $raw[] = ['label' => $this->text($label), 'span' => $span, 'rest' => ltrim($rest, " :\t"), 'full' => $full];
        }

        // PK appris : « Lieu (671,700) » cités explicitement dans une borne de section.
        $learned = [];
        foreach ($raw as $r) {
            foreach ($this->parseSection($r['span'])['bounds'] as $b) {
                if ($b['pk'] !== null) {
                    $k = $this->norm($b['name']);
                    $learned[$k] = isset($learned[$k]) && $learned[$k] !== $b['pk'] ? false : $b['pk'];
                }
            }
        }
        $learned = array_filter($learned, fn ($v) => $v !== false);
        // Plages explicites par section : « Annecy ZI - Saint-Jorioz (2,360 - 10,500) »
        // sert aussi à « Annecy ZI - Saint-Jorioz » cité sans PK dans une autre rubrique.
        $ranges = [];
        foreach ($raw as $r) {
            $sec = $this->parseSection($r['span']);
            if ($sec['range'] !== null && $sec['from'] !== null && $sec['from'] !== $sec['to']) {
                $k = $this->norm($sec['from']).'|'.$this->norm($sec['to']);
                $ranges[$k] = isset($ranges[$k]) && $ranges[$k] !== $sec['range'] ? false : $sec['range'];
            }
        }
        $learned['@ranges'] = array_filter($ranges, fn ($v) => $v !== false);

        $events = [];
        foreach ($raw as $r) {
            $type = $this->mapLabel($r['label']);
            if ($type === null) {
                continue;
            }
            $dates = $this->parseDates($r['rest']);
            $quote = rtrim($r['label'], ' :').' : '.($r['span'] !== '' ? rtrim($r['span'], ' :').' : ' : '').$r['rest'];
            if ($dates === []) {
                $this->skipped[] = "{$row['page']} - {$quote}";

                continue;
            }
            $sec = $this->parseSection($r['span']);
            [$pkStart, $pkEnd, $pkOrigin] = $this->sectionPk($sec, $stations, $learned);
            foreach ($dates as $d) {
                $events[] = [
                    'type' => $type,
                    'label' => rtrim($r['label'], ' :'),
                    'date' => $d['date'],
                    'precision' => $d['precision'],
                    'confidence' => $d['confidence'],
                    'from' => $sec['from'],
                    'to' => $sec['to'],
                    'pk_start' => $pkStart,
                    'pk_end' => $pkEnd,
                    'pk_origin' => $pkOrigin,
                    'section' => rtrim($r['span'], ' :'),
                    'rest' => $r['rest'],
                    'quote' => $quote,
                ];
            }
        }

        return [
            'page' => $row['page'],
            'url' => $url,
            'title' => $title,
            'inventory_name' => $row['name'],
            'code' => $code,
            'length_m' => $length ?? ($row['length_m'] ?? null),
            'regions' => $row['regions'] ?? [],
            'gauge' => $carac['ecartement'][0] ?? null,
            'tracks' => $carac['nombre de voies'][0] ?? null,
            'events' => $events,
            'stations' => $stations,
        ];
    }

    /**
     * Paires (libellé dt, nœud dd) d'un <dl class="…">.
     *
     * @return list<array{0: string, 1: \DOMElement}>
     */
    private function dlPairs(\DOMXPath $xp, string $class): array
    {
        $out = [];
        foreach ($xp->query("//dl[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]") as $dl) {
            $label = '';
            foreach ($dl->childNodes as $n) {
                if (! $n instanceof \DOMElement) {
                    continue;
                }
                if ($n->tagName === 'dt') {
                    $label = $n->textContent;
                } elseif ($n->tagName === 'dd') {
                    $out[] = [$label, $n];
                }
            }
        }

        return $out;
    }

    /**
     * « Rosporden (664,604) > La Boissière (673,424) > Concarneau (679,724) ».
     *
     * @return list<array{name: string, pk_m: ?int, pk_raw: ?string, order: int, group: int}>
     */
    private function parseStations(\DOMXPath $xp): array
    {
        $h2 = null;
        foreach ($xp->query('//div[@id="contenu"]/h2') as $h) {
            if ($this->norm($h->textContent) === 'les gares') {
                $h2 = $h;
                break;
            }
        }
        if ($h2 === null) {
            return [];
        }
        $out = [];
        $group = 0;
        for ($n = $h2->nextSibling; $n !== null; $n = $n->nextSibling) {
            if (! $n instanceof \DOMElement) {
                continue;
            }
            if (in_array($n->tagName, ['h2', 'h1'], true)) {
                break;
            }
            $txt = $this->text($n->textContent);
            if (! str_contains($txt, '>') && ! preg_match('/\(\s*-?\d+[,+][\dx]{1,3}\s*\)/i', $txt)) {
                continue;
            }
            $group++;
            foreach (preg_split('/\s*>\s*/u', $txt) as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $pkRaw = null;
                if (preg_match('/^(.*?)\s*\(\s*(-?\d+[,+.][\dx]{1,3})\s*\)\s*(.*)$/iu', $item, $m)) {
                    $name = trim($m[1].' '.$m[3]);
                    $pkRaw = $m[2];
                } elseif (preg_match('/^(.*?)\s*\(\s*((?:-?\d+[,+.][\dx]{1,3}\s*;\s*)+(-?\d+[,+.][\dx]{1,3}))\s*\)\s*$/iu', $item, $m)) {
                    // Bifurcation « (303,7xx ; 0,000) » : PK sur la ligne mère ; PK propre à la ligne (le dernier).
                    $name = trim($m[1]);
                    $pkRaw = $m[3];
                } else {
                    $name = trim(preg_replace('/\s*\(\s*\?\s*\)\s*/u', ' ', $item));
                }
                $out[] = ['name' => $name, 'pk_m' => $pkRaw !== null ? $this->pk($pkRaw) : null,
                    'pk_raw' => $pkRaw, 'pk_approx' => $pkRaw !== null && (bool) preg_match('/x|[,.]\d{1,2}$/i', $pkRaw),
                    'order' => count($out) + 1, 'group' => $group];
            }
        }

        return $out;
    }

    /**
     * Libellé de section → bornes nommées + PK explicites.
     *   « Rosporden - La Boissière (671,700) »
     *   « Saint-Jorioz - Lathuille (10,500 - 15,271) »
     *   « Section à Annecy (1,699 - 2,360) », « Gare de Concarneau »,
     *   « Section entre A et B », « Totalité de la ligne ».
     *
     * @return array{from: ?string, to: ?string, bounds: list<array{name: string, pk: ?int}>, range: ?array{0: int, 1: int}, whole: bool}
     */
    private function parseSection(string $span): array
    {
        $s = trim(rtrim(trim($span), ':'));
        $res = ['from' => null, 'to' => null, 'bounds' => [], 'range' => null, 'whole' => false];
        if ($s === '') {
            $res['whole'] = true;

            return $res;
        }
        $pkRe = '-?\d+[,+]\d{1,3}';
        if (preg_match("/^(.*?)\s*\(\s*(?:pk\s*)?({$pkRe})\s*(?:[-–]|à|au)\s*(?:pk\s*)?({$pkRe})\s*\)\s*$/iu", $s, $m)) {
            $s = trim($m[1]);
            $res['range'] = [$this->pk($m[2]), $this->pk($m[3])];
        }
        if (preg_match('/^(totalit[ée]|ensemble) de la ligne$/iu', $s)) {
            $res['whole'] = true;

            return $res;
        }
        if (preg_match('/^section (?:comprise )?entre (.+?) et (.+)$/iu', $s, $m)) {
            $parts = [$m[1], $m[2]];
        } elseif (preg_match('/^(?:section|gare|emprises?|voies?)\b.*?\b(?:à|a|au|aux|en gare d[e\'’]|de|du|d[\'’])\s*(.+)$/iu', $s, $m)
            && ! preg_match('/\s[-–]\s/u', $s)) {
            $place = trim($m[1]);
            $res['from'] = $res['to'] = $this->stripPk($place)[0];
            $res['bounds'][] = ['name' => $res['from'], 'pk' => $this->stripPk($place)[1]];

            return $res;
        } else {
            $parts = preg_split('/\s+[-–]\s+/u', $s);
            // « Lapeyrouse à Volvic » (fiche 709000) : « à » entre deux noms de lieux, sans tiret.
            if (count($parts) === 1 && ! str_contains($s, '(')
                && preg_match('/^(?:d[e\'’]\s*)?(\p{Lu}[^()]*?)\s+à\s+(\p{Lu}[^()]*)$/u', $s, $m)
                && ! preg_match('/^(raccordement|embranchement|section|voies?|gare|emprises?)\b/iu', $m[1])) {
                $parts = [$m[1], $m[2]];
            }
        }
        if (count($parts) === 1) {
            [$name, $pk] = $this->stripPk($parts[0]);
            $res['from'] = $res['to'] = $name;
            $res['bounds'][] = ['name' => $name, 'pk' => $pk];

            return $res;
        }
        foreach ([reset($parts), end($parts)] as $p) {
            [$name, $pk] = $this->stripPk($p);
            $res['bounds'][] = ['name' => $name, 'pk' => $pk];
        }
        $res['from'] = $res['bounds'][0]['name'];
        $res['to'] = $res['bounds'][1]['name'];

        return $res;
    }

    /** « La Boissière (671+700) » → ['La Boissière', 671700]. */
    private function stripPk(string $s): array
    {
        $s = trim($s);
        if (preg_match('/^(.*?)\s*\(\s*(?:pk\s*)?(-?\d+[,+]\d{1,3})\s*\)\s*$/iu', $s, $m)) {
            return [trim($m[1]), $this->pk($m[2])];
        }

        return [$s, null];
    }

    /**
     * PK de la section : explicite dans le libellé > PK cité ailleurs sur la
     * fiche pour ce lieu > PK du bâtiment voyageurs (liste des gares). Les deux
     * bornes doivent être connues, sinon NULL (le résolveur partira des noms).
     *
     * @return array{0: ?int, 1: ?int, 2: ?string}
     */
    private function sectionPk(array $sec, array $stations, array $learned): array
    {
        if ($sec['range'] !== null) {
            return [$sec['range'][0], $sec['range'][1], 'explicit'];
        }
        if ($sec['whole'] || count($sec['bounds']) !== 2) {
            return [null, null, null];
        }
        // Dernier recours (une borne inconnue, ex. « Annecy ZI ») : la plage explicite
        // donnée ailleurs sur la fiche pour exactement la même section.
        $sameSection = function () use ($sec, $learned): array {
            $k = $this->norm($sec['from']).'|'.$this->norm($sec['to']);
            if (isset($learned['@ranges'][$k]) && $sec['bounds'][0]['pk'] === null && $sec['bounds'][1]['pk'] === null) {
                return [$learned['@ranges'][$k][0], $learned['@ranges'][$k][1], 'page'];
            }

            return [null, null, null];
        };
        $pks = [];
        $origins = [];
        foreach ($sec['bounds'] as $b) {
            if ($b['pk'] !== null) {
                $pks[] = $b['pk'];
                $origins[] = 'explicit';

                continue;
            }
            $k = $this->norm($b['name']);
            if (isset($learned[$k])) {
                $pks[] = $learned[$k];
                $origins[] = 'page';

                continue;
            }
            $st = $this->findStation($b['name'], $stations);
            if ($st === null || $st['pk_m'] === null) {
                return $sameSection();
            }
            $pks[] = $st['pk_m'];
            $origins[] = 'stations';
        }
        $origin = count(array_unique($origins)) === 1 ? $origins[0] : 'mixed';

        return [$pks[0], $pks[1], $origin];
    }

    /** Gare de la fiche par nom exact, sinon nom de section = début unique du nom de gare (« Épinac » → « Épinac-les-Mines »). */
    private function findStation(string $name, array $stations): ?array
    {
        $k = $this->norm($name);
        $prefix = [];
        foreach ($stations as $st) {
            $sk = $this->norm($st['name']);
            if ($sk === $k) {
                return $st;
            }
            if (str_starts_with($sk, $k.' ') || str_starts_with($sk, $k.'-')) {
                $prefix[] = $st;
            }
        }

        return count($prefix) === 1 ? $prefix[0] : null;
    }

    /** Intitulé de rubrique historique → LineEventType (null = pas un événement). */
    private function mapLabel(string $label): ?string
    {
        $l = preg_replace('/\s*\(\d+\)\s*/', ' ', $this->norm(rtrim($label, ' :')));
        $l = trim($l);
        $svc = fn (string $base) => match (true) {
            str_contains($l, 'voyageurs') && ! str_contains($l, 'marchandises') => $base.'_passenger',
            str_contains($l, 'marchandises') && ! str_contains($l, 'voyageurs') => $base.'_freight',
            default => $base,
        };

        return match (true) {
            str_starts_with($l, 'etat actuel') => null,
            str_starts_with($l, 'declaration d') => 'other',
            str_starts_with($l, 'concession') => 'concession',
            str_starts_with($l, 'reouverture') || str_starts_with($l, 'remise en service') => $svc('reopening'),
            str_starts_with($l, 'ouverture') || str_starts_with($l, 'mise en service') => $svc('opening'),
            str_starts_with($l, 'fermeture') => $svc('closure'),
            // Dépose de la caténaire / désélectrification : la voie reste, pas de type dédié.
            str_contains($l, 'catenaire') || str_starts_with($l, 'deselectrification') => 'other',
            str_starts_with($l, 'arret des travaux') => 'other',
            str_starts_with($l, 'depose') => 'dismantling',
            str_starts_with($l, 'declassement') || str_starts_with($l, 'retranchement') => 'declassification',
            str_starts_with($l, 'electrification') => 'electrification',
            default => $this->unknownLabel($label),
        };
    }

    private function unknownLabel(string $label): string
    {
        $k = rtrim($this->text($label), ' :');
        $this->unknownLabels[$k] = ($this->unknownLabels[$k] ?? 0) + 1;

        return 'other';
    }

    /**
     * Date(s) d'une entrée : « 30/06/1883 », « mars 2020 », « 1988 », « vers 1999 »,
     * « Fin des années 1960 », « automne 2015 », « 15/04/2016 et 12/09/2017 ».
     * Seule la PREMIÈRE date compte (« 14/09/1998 (officielle : 06/06/2013) »,
     * « 28/06/1883, approuvée le 20/11/1883 ») sauf énumération « A et B ».
     *
     * @return list<array{date: string, precision: string, confidence: string}>
     */
    private function parseDates(string $rest): array
    {
        $t = $this->norm($rest);
        $mon = implode('|', array_keys(self::MONTHS));
        $day = '(\d{1,2})\/(\d{1,2})\/(\d{4})';
        $out = [];

        // Énumération de dates complètes en tête : « 15/04/2016 et 12/09/2017 ».
        if (preg_match("/^(?:le\s+)?{$day}((?:\s*(?:,|et)\s*{$day})+)/", $t, $m)) {
            preg_match_all("/{$day}/", $m[0], $all, PREG_SET_ORDER);
            foreach ($all as $a) {
                if ($d = $this->mkDate((int) $a[3], (int) $a[2], (int) $a[1])) {
                    $out[] = ['date' => $d, 'precision' => 'day', 'confidence' => 'high'];
                }
            }

            return $out;
        }

        $approx = (bool) preg_match('/^(vers|env\.?|environ|avant|apres|courant|circa|probablement|c\.)\b/', $t);
        $candidates = [];
        if (preg_match("/{$day}/", $t, $m, PREG_OFFSET_CAPTURE)) {
            $candidates[] = [$m[0][1], $this->mkDate((int) $m[3][0], (int) $m[2][0], (int) $m[1][0]), 'day', $approx ? 'medium' : 'high'];
        }
        if (preg_match("/(\d{1,2}|1er)\s+({$mon})\s+(\d{4})/", $t, $m, PREG_OFFSET_CAPTURE)) {
            $candidates[] = [$m[0][1], $this->mkDate((int) $m[3][0], self::MONTHS[$m[2][0]], (int) $m[1][0]), 'day', $approx ? 'medium' : 'high'];
        }
        if (preg_match("/(debut|fin|mi-?|courant)?\s*({$mon})\s+(\d{4})/", $t, $m, PREG_OFFSET_CAPTURE)) {
            $candidates[] = [$m[0][1], $this->mkDate((int) $m[3][0], self::MONTHS[$m[2][0]], 1), 'month',
                ($approx || ($m[1][0] ?? '') !== '') ? 'low' : 'medium'];
        }
        if (preg_match('/(debut|milieu|fin)?\s*(?:des\s+)?annees\s+((?:18|19|20)\d0)/', $t, $m, PREG_OFFSET_CAPTURE)) {
            $y = (int) $m[2][0] + match ($m[1][0] ?? '') {
                'debut' => 1, 'fin' => 8, default => 5
            };
            $candidates[] = [$m[0][1], $this->mkDate($y, 1, 1), 'year', 'low'];
        }
        if (preg_match('/(?<![\d\/])((?:18|19|20)\d{2})(?!\d)/', $t, $m, PREG_OFFSET_CAPTURE)) {
            $before = substr($t, 0, $m[1][1]);
            $fuzzy = $approx || preg_match('/(debut|fin|mi-?|milieu|'.implode('|', self::SEASONS).')\s*$/', $before)
                || preg_match('/^\s*\/\s*\d{2,4}/', substr($t, $m[1][1] + 4));
            $candidates[] = [$m[1][1], $this->mkDate((int) $m[1][0], 1, 1), 'year', $fuzzy ? 'low' : 'medium'];
        }
        $candidates = array_filter($candidates, fn ($c) => $c[1] !== null);
        if ($candidates === []) {
            return [];
        }
        // Le motif le plus à gauche (« vers 1989 (le 14/09/2006 par RFF) » → 1989) ;
        // à position égale, le plus précis (« mars 2020 » plutôt que « 2020 »).
        usort($candidates, fn ($a, $b) => [$a[0], -$this->rank($a[2])] <=> [$b[0], -$this->rank($b[2])]);
        $best = $candidates[0];

        return [['date' => $best[1], 'precision' => $best[2], 'confidence' => $best[3]]];
    }

    private function rank(string $precision): int
    {
        return ['year' => 0, 'month' => 1, 'day' => 2][$precision];
    }

    private function mkDate(int $y, int $m, int $d): ?string
    {
        return ($y >= 1800 && $y <= 2100 && checkdate($m, $d, $y)) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    // ════════════════════════════════════════════════════════════════════════
    //  Analyse PDF (texte pdftotext -layout)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * fermetures.pdf : LIGNE | SECTION | PK DÉBUT | PK FIN | LONG | IG/IL | Numéro | DATE | OBSERVATIONS
     * (décisions de fermeture RFF / SNCF Réseau → closure, tout trafic).
     */
    private function parseFermeturesPdf(string $txt): array
    {
        $pk = '-?\d{1,3}(?:\s?\d{3})*,\d{1,3}|-|\?';
        $re = "/^\s*(?<ligne>\S.*?)\s{2,}(?<section>\S.*?)\s{2,}(?<pkd>{$pk})\s+(?<pkf>{$pk})\s+(?<long>{$pk})\s+(?<int>IG|IL|\?)\s+(?:(?<num>\d{3}\s\d{3})\s+)?(?<date>\d{2}\/\d{2}\/\d{4})(?:\s+(?<obs>.*))?$/u";

        return $this->parsePdfRows($txt, $re, 'fermetures', fn () => 'closure');
    }

    /**
     * declassements.pdf : LIGNE | SECTION | NUM | PK DÉBUT | PK FIN | LONG | IG | DECL/RETR | DATE | OBSERVATIONS
     * (retranchement = perte de la vocation ferroviaire, déclassement = sortie
     * du domaine public : les deux → declassification).
     */
    private function parseDeclassementsPdf(string $txt): array
    {
        $pk = '-?\d{1,3}(?:\s?\d{3})*,\d{1,3}|-|\?';
        $re = "/^\s*(?<ligne>\S.*?)\s{2,}(?<section>\S.*?)\s{2,}(?:(?<num>\d{3}\s\d{3})\s+)?(?<pkd>{$pk})\s+(?<pkf>{$pk})\s+(?<long>{$pk})\s+(?<int>IG|IL|\?)\s+(?<kind>DECL|RETR)\s+(?<date>\d{2}\/\d{2}\/\d{4})(?:\s+(?<obs>.*))?$/u";

        return $this->parsePdfRows($txt, $re, 'declassements', fn () => 'declassification');
    }

    private function parsePdfRows(string $txt, string $re, string $file, callable $type): array
    {
        $rows = [];
        foreach (preg_split('/\R/u', $txt) as $line) {
            if (! preg_match($re, $line, $m)) {
                continue;
            }
            $obs = trim($m['obs'] ?? '');
            $date = $this->mkDate((int) substr($m['date'], 6, 4), (int) substr($m['date'], 3, 2), (int) substr($m['date'], 0, 2));
            if ($date === null) {
                continue;
            }
            $rows[] = [
                'file' => $file,
                'type' => $type($m),
                'kind' => $m['kind'] ?? null,
                'ligne' => $this->text($m['ligne']),
                'section' => $this->text($m['section']),
                'code' => isset($m['num']) && $m['num'] !== '' ? $this->normCode($m['num']) : null,
                'pk_start' => ! in_array($m['pkd'], ['-', '?'], true) ? $this->pk($m['pkd']) : null,
                'pk_end' => ! in_array($m['pkf'], ['-', '?'], true) ? $this->pk($m['pkf']) : null,
                'date' => $date,
                'obs' => $obs,
                'quote' => preg_replace('/\s{2,}/u', ' | ', trim($line)),
            ];
        }

        return $rows;
    }

    /** @var array<string, list<array{cited: string, file: string, ligne: string, section: string, proof: string}>> code corrigé → corrections */
    private array $codeCorrections = [];

    /**
     * Numéros de ligne mal recopiés dans les PDF (2026-10-19). fermetures.pdf,
     * « Voie du port de Saint-Malo » : deux sections contiguës, PK 453,940 à
     * 454,505 numérotée 422 500 et PK 454,505 à 454,914 numérotée 442 500.
     * 442500 est au référentiel SNCF (type « Vport », PK 453,324 à 454,914) ;
     * 422500 n'existe nulle part (SNCF, Wikidata, lignes VFF) : coquille d'un
     * chiffre, qui créait une seconde « Voie du port de Saint-Malo » (lo-422500).
     *
     * Règle : un code cité absent du référentiel SNCF (types, catégories,
     * formes, gares, électrification), de Wikidata et des lignes VFF (hors
     * lignes créées par cet import) est remplacé par le code d'une autre ligne
     * du MÊME document et du MÊME libellé si ce code (1) est au référentiel
     * SNCF, (2) ne diffère que d'un seul chiffre et (3) a une plage de PK SNCF
     * qui couvre la section (± 100 m). Un seul candidat, sinon rien. La preuve
     * est notée dans lines.alt_names.code_corrections ; la ligne créée sous le
     * code erroné est fusionnée dans la bonne (applyCodeCorrections()).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function fixPdfCodes(array $rows): array
    {
        $this->codeCorrections = [];
        $ref = [];
        foreach (DB::select(<<<'SQL'
            SELECT code_ligne, MIN(LEAST(a, b)) AS a, MAX(GREATEST(a, b)) AS b FROM (
                SELECT code_ligne, pkd_m AS a, pkf_m AS b FROM sncf_line_types
                UNION ALL SELECT code_ligne, pkd_m, pkf_m FROM sncf_line_catlig
                UNION ALL SELECT code_ligne, pk_debut_m, pk_fin_m FROM sncf_line_geometries
                UNION ALL SELECT code_ligne, pk_m, pk_m FROM sncf_gares
                UNION ALL SELECT code_ligne, pkd_m, pkf_m FROM sncf_line_electrification
            ) s GROUP BY code_ligne
        SQL) as $r) {
            $ref[$r->code_ligne] = [$r->a !== null ? (int) $r->a : null, $r->b !== null ? (int) $r->b : null];
        }
        $known = array_flip(DB::table('lines')->whereNotNull('code')->where('slug', 'not like', 'lo-%')->pluck('code')->all());
        foreach (DB::select("SELECT DISTINCT c->>'code' AS code FROM wikidata_lines, jsonb_array_elements(COALESCE(codes, '[]'::jsonb)) c WHERE c->>'code' IS NOT NULL") as $r) {
            $known[$r->code] = true;
        }

        foreach ($rows as $i => $r) {
            $cited = $r['code'];
            if ($cited === null || isset($ref[$cited]) || isset($known[$cited]) || $r['pk_start'] === null || $r['pk_end'] === null) {
                continue;
            }
            [$a, $b] = [min($r['pk_start'], $r['pk_end']), max($r['pk_start'], $r['pk_end'])];
            $cands = [];
            foreach ($rows as $j => $o) {
                $code = $o['code'];
                if ($j === $i || $code === null || $code === $cited || $o['file'] !== $r['file']
                    || $this->norm($o['ligne']) !== $this->norm($r['ligne']) || ! isset($ref[$code])) {
                    continue;
                }
                [$ra, $rb] = $ref[$code];
                $diff = 0;
                for ($k = 0; $k < 6; $k++) {
                    $diff += $code[$k] !== $cited[$k] ? 1 : 0;
                }
                if ($diff === 1 && $ra !== null && $rb !== null && $a >= $ra - 100 && $b <= $rb + 100) {
                    $cands[$code] = $o;
                }
            }
            if (count($cands) !== 1) {
                continue;
            }
            $code = (string) array_key_first($cands);
            $o = $cands[$code];
            $km = fn (?int $m) => $m === null ? '?' : number_format($m / 1000, 3, ',', '');
            $proof = "{$cited} (PK {$km($a)} à {$km($b)}) absent du référentiel SNCF, de Wikidata et des lignes VFF ; "
                ."même ligne « {$r['ligne']} » numérotée {$code} dans le même document (PK {$km($o['pk_start'])} à {$km($o['pk_end'])}) ; "
                ."{$code} au référentiel SNCF (PK {$km($ref[$code][0])} à {$km($ref[$code][1])}), qui couvre la section : coquille d'un chiffre";
            $this->codeCorrections[$code][] = ['cited' => $cited, 'file' => $r['file'], 'ligne' => $r['ligne'], 'section' => $r['section'], 'proof' => $proof];
            $rows[$i]['code'] = $code;
            $rows[$i]['code_cited'] = $cited;
        }

        return $rows;
    }

    /**
     * Preuves des corrections de code (alt_names.code_corrections, recalculé à
     * chaque import) et fusion de la ligne créée sous le code erroné (lo-<code>)
     * dans la ligne du code corrigé (App\Services\LineMerger : événements,
     * tracés, références ; mémoire dans alt_names.merged).
     *
     * @param  array<string, int>  $lineIds  code → line_id
     */
    public function applyCodeCorrections(array $lineIds): void
    {
        $fixed = [];
        foreach ($this->codeCorrections as $code => $list) {
            $into = $lineIds[$code] ?? null;
            if ($into === null) {
                continue;
            }
            $fixed[] = $into;
            DB::update("UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb) || jsonb_build_object('code_corrections', ?::jsonb), updated_at = NOW() WHERE id = ?",
                [json_encode(array_map(fn ($c) => ['cited' => $c['cited'], 'source' => self::BASE.'fichiers/'.$c['file'].'.pdf',
                    'section' => $c['section'], 'proof' => $c['proof']], $list), JSON_UNESCAPED_UNICODE), $into]);
            foreach (array_unique(array_column($list, 'cited')) as $cited) {
                $from = DB::table('lines')->where('slug', 'lo-'.$cited)->where('code', $cited)->value('id');
                if ($from && (int) $from !== $into) {
                    $res = app(LineMerger::class)->merge((int) $from, $into, null,
                        "code {$cited} cité par lignes-oubliees = coquille de {$code} (cf. code_corrections)");
                    $this->output?->writeln("  fusion : ligne {$from} (lo-{$cited}) → {$into} ({$code}) : {$res['events_moved']} événement(s) déplacé(s), {$res['events_dropped']} doublon(s)");
                }
            }
        }
        // Corrections qui ne s'appliquent plus : preuve retirée.
        DB::table('lines')->whereRaw("jsonb_exists(alt_names, 'code_corrections')")->whereNotIn('id', $fixed ?: [0])
            ->update(['alt_names' => DB::raw("alt_names - 'code_corrections'"), 'updated_at' => now()]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  Écriture en base
    // ════════════════════════════════════════════════════════════════════════

    private array $stats = ['lines_matched' => 0, 'lines_created' => 0, 'lines_existing_lo' => 0, 'events' => [], 'pdf' => [], 'stations' => 0];

    /** @var array<string, string> nom de ligne normalisé → code */
    private array $nameIndex = [];

    /**
     * Codes RFN → lines.id ; crée les lignes absentes (fermées avant le RFN actuel).
     *
     * @return array<string, int> code → line_id
     */
    private function resolveLines(array $inventory, array $fiches, array $pdfRows, bool $partial): array
    {
        $wanted = [];   // code → [name, length, regions, page, url, inventory_name]
        foreach ($inventory as $row) {
            if ($row['code'] === null || ($partial && ! isset($fiches[$row['page'] ?? '']))) {
                continue;
            }
            $f = $row['page'] !== null ? ($fiches[$row['page']] ?? null) : null;
            $wanted[$row['code']] = [
                'name' => $f['title'] ?? $this->lineName($row['name']),
                'inventory_name' => $row['name'],
                'length_m' => $f['length_m'] ?? $row['length_m'],
                'regions' => $row['regions'],
                'url' => $f['url'] ?? self::BASE.'index.php?page=inventaire&debut=0&limite=376',
                'gauge' => $f['gauge'] ?? null,
                'tracks' => $f['tracks'] ?? null,
            ];
        }
        foreach ($fiches as $f) {
            if ($f['code'] !== null && ! isset($wanted[$f['code']])) {
                $wanted[$f['code']] = ['name' => $f['title'], 'inventory_name' => $f['inventory_name'], 'length_m' => $f['length_m'],
                    'regions' => $f['regions'], 'url' => $f['url'], 'gauge' => $f['gauge'], 'tracks' => $f['tracks']];
            }
        }
        // Lignes citées par code dans les PDF : index des noms pour rattacher les
        // lignes de tableau sans numéro, et création si la ligne est inconnue.
        foreach ($pdfRows as $r) {
            if ($r['code'] !== null) {
                if (! isset($wanted[$r['code']]) && ! $this->skipPdfRow($r)) {
                    $wanted[$r['code']] = ['name' => $this->lineName($r['ligne']), 'inventory_name' => $r['ligne'], 'length_m' => null,
                        'regions' => [], 'url' => self::BASE.'fichiers/'.$r['file'].'.pdf', 'gauge' => null, 'tracks' => null];
                }
            }
        }

        $existing = DB::table('lines')->whereIn('code', array_keys($wanted))
            ->where('slug', 'not like', 'lo-%')->pluck('id', 'code')->all();
        $ids = [];
        foreach ($wanted as $code => $w) {
            if (isset($existing[$code])) {
                $ids[$code] = $existing[$code];
                $this->stats['lines_matched']++;

                continue;
            }
            $slug = 'lo-'.$code;
            $data = [
                'name' => Str::limit($w['name'], 250, ''),
                'code' => $code,
                'network' => 'principal',
                'alt_names' => json_encode([
                    'name_source' => self::SOURCE,
                    'lignes_oubliees' => ['name' => $w['inventory_name'], 'url' => $w['url']],
                ], JSON_UNESCAPED_UNICODE),
                'length_official_m' => $w['length_m'],
                'regions' => $w['regions'] !== [] ? json_encode($w['regions'], JSON_UNESCAPED_UNICODE) : null,
                'gauge_mm_default' => $this->gauge($w['gauge']),
                'nb_voies_default' => $this->tracks($w['tracks']),
                'updated_at' => now(),
            ];
            $id = DB::table('lines')->where('slug', $slug)->value('id');
            if ($id) {
                // Ligne créée par un import précédent : on rafraîchit nos champs sans
                // écraser ce que d'autres traitements ont pu y ajouter (network, alt_names).
                $alt = $data['alt_names'];
                unset($data['network'], $data['alt_names']);
                DB::table('lines')->where('id', $id)->update($data + [
                    'alt_names' => DB::raw("COALESCE(alt_names, '{}'::jsonb) || ".DB::getPdo()->quote($alt).'::jsonb'),
                ]);
                $this->stats['lines_existing_lo']++;
            } else {
                $id = DB::table('lines')->insertGetId($data + ['slug' => $slug, 'created_at' => now()]);
            }
            $ids[$code] = $id;
            $this->stats['lines_created']++;
        }

        return $ids;
    }

    /**
     * Index nom de ligne → code, pour rattacher les lignes des PDF sans numéro :
     * inventaire et fiches d'abord, puis lignes des PDF portant un numéro, puis
     * noms des lignes déjà en base (sans écraser). Un nom ambigu vaut « ? ».
     */
    private function buildNameIndex(array $inventory, array $fiches, array $pdfRows): void
    {
        $this->nameIndex = [];
        foreach ($inventory as $row) {
            if ($row['code'] !== null) {
                $this->indexName($row['name'], $row['code']);
                if ($row['page'] !== null && isset($fiches[$row['page']])) {
                    $this->indexName($fiches[$row['page']]['title'], $row['code']);
                }
            }
        }
        foreach ($pdfRows as $r) {
            if ($r['code'] !== null) {
                $this->indexName($r['ligne'], $r['code']);
            }
        }
        foreach (DB::table('lines')->whereNotNull('code')->get(['code', 'name']) as $l) {
            $this->indexName($l->name, $l->code, false);
        }
    }

    private function indexName(string $name, string $code, bool $override = true): void
    {
        $k = $this->lineKey($name);
        if ($k === '') {
            return;
        }
        if (! isset($this->nameIndex[$k])) {
            $this->nameIndex[$k] = $code;
        } elseif ($this->nameIndex[$k] !== $code && $override) {
            $this->nameIndex[$k] = $this->nameIndex[$k] === $code ? $code : '?';
        }
    }

    /** « Ligne de Rosporden à Concarneau » / « Rosporden - Concarneau » → « concarneau|rosporden ». */
    private function lineKey(string $name): string
    {
        $t = $this->text($name);
        $t = preg_replace('/\s*\((via|par)\b[^)]*\)|\s+(via|par)\s+.*$/iu', '', $t);
        if (preg_match('/^ligne (?:de |d[\'’]\s*|du |des )(.+?) (?:à |au |aux )(.+)$/iu', $t, $m)) {
            $ends = [$m[1], $m[2]];
        } else {
            $ends = preg_split('/\s+[-–‒—]\s+/u', $t);
            if (count($ends) !== 2) {
                return '';
            }
        }
        // Toponyme : sans article initial, espaces / apostrophes / tirets unifiés, « st » = « saint ».
        $ends = array_map(function ($e) {
            $e = preg_replace('/^(?:(?:le|la|les)\s+|l\'\s*)/', '', $this->norm($e));
            $e = preg_replace('/\bste?\b-?/', 'saint-', $e);

            return trim(preg_replace('/[\s\'-]+/', '-', $e), '-');
        }, $ends);
        sort($ends);

        return implode('|', $ends);
    }

    private function writeFicheEvents(array $fiches, array $lineIds): array
    {
        $fps = [];
        $this->ficheKeys = [];
        foreach ($fiches as $f) {
            $lineId = $f['code'] !== null ? ($lineIds[$f['code']] ?? null) : null;
            if ($lineId === null) {
                $this->skipped[] = "{$f['page']} - sans code de ligne, fiche ignorée";

                continue;
            }
            foreach ($f['events'] as $e) {
                // Empreinte = la ligne de la source (fiche, rubrique → type, libellé de section, date),
                // indépendante des PK déduits : améliorer la déduction ne change pas l'identité.
                $fp = hash('sha256', implode('|', [self::SOURCE, 'fiche', $f['page'], $e['type'], $e['date'],
                    $this->norm($e['section'])]));
                if (isset($fps[$fp])) {
                    continue;
                }
                $fps[$fp] = $fp;
                $desc = $e['label'].($e['section'] !== '' ? ' ('.$e['section'].')' : ' (toute la ligne)')
                    .' : '.$e['rest'];
                if ($e['pk_origin'] === 'stations' || $e['pk_origin'] === 'mixed' || $e['pk_origin'] === 'page') {
                    $desc .= ' - PK déduits '.($e['pk_origin'] === 'page' ? 'des autres mentions de la fiche' : 'de la liste des gares de la fiche');
                }
                $this->upsertEvent($fp, [
                    'line_id' => $lineId,
                    'type' => $e['type'],
                    'event_date' => $e['date'],
                    'date_precision' => $e['precision'],
                    'description' => $desc,
                    'section_from' => $e['from'] !== null ? Str::limit($e['from'], 250, '') : null,
                    'section_to' => $e['to'] !== null ? Str::limit($e['to'], 250, '') : null,
                    'section_pk_start_m' => $e['pk_start'],
                    'section_pk_end_m' => $e['pk_end'],
                    'source_url' => $f['url'],
                    'source_quote' => $e['quote'],
                    'confidence' => $e['confidence'],
                ]);
                $this->ficheKeys[$lineId][] = [$e['type'], $e['date'], $e['pk_start'], $e['pk_end']];
                $this->stats['events'][$e['type']] = ($this->stats['events'][$e['type']] ?? 0) + 1;
            }
        }

        return array_values($fps);
    }

    /** @var array<int, list<array{0: string, 1: string, 2: ?int, 3: ?int}>> */
    private array $ficheKeys = [];

    private function writePdfEvents(array $rows, array $lineIds): array
    {
        $fps = [];
        foreach ($rows as $r) {
            $key = $r['file'];
            if ($this->skipPdfRow($r)) {
                $this->stats['pdf'][$key.' ignorées (annulées / non construites)'] = ($this->stats['pdf'][$key.' ignorées (annulées / non construites)'] ?? 0) + 1;

                continue;
            }
            $code = $r['code'] ?? $this->codeByName($r['ligne']);
            $lineId = $code !== null ? ($lineIds[$code] ?? DB::table('lines')->where('code', $code)->value('id')) : null;
            if ($lineId === null) {
                $this->stats['pdf'][$key.' non rattachées'] = ($this->stats['pdf'][$key.' non rattachées'] ?? 0) + 1;
                $this->skipped[] = "{$key}.pdf - ligne non rattachée : {$r['quote']}";

                continue;
            }
            if ($this->isDuplicateOfFiche($lineId, $r)) {
                $this->stats['pdf'][$key.' doublons de fiche'] = ($this->stats['pdf'][$key.' doublons de fiche'] ?? 0) + 1;

                continue;
            }
            $fp = hash('sha256', implode('|', [self::SOURCE, $r['file'], $this->norm($r['ligne']), $this->norm($r['section']),
                $r['date'], $r['pk_start'], $r['pk_end']]));
            $sec = $this->parseSection(preg_replace('/\s*\(.*\)\s*$/u', '', $r['section']));
            if (preg_match('/d[ée]terminer/iu', $r['section'])) {
                // « Section à déterminer » : seule la plage de PK (si connue) localise la portion.
                if ($r['pk_start'] === null || $r['pk_end'] === null) {
                    $this->stats['pdf'][$key.' ignorées (section indéterminée)'] = ($this->stats['pdf'][$key.' ignorées (section indéterminée)'] ?? 0) + 1;

                    continue;
                }
                $sec = ['from' => null, 'to' => null, 'whole' => true];
            }
            if (isset($fps[$fp])) {
                continue;
            }
            $fps[$fp] = $fp;
            $label = $r['file'] === 'fermetures' ? 'Fermeture de ligne (décision RFF / SNCF Réseau)'
                : ($r['kind'] === 'RETR' ? 'Retranchement' : 'Déclassement');
            $this->upsertEvent($fp, [
                'line_id' => $lineId,
                'type' => $r['type'],
                'event_date' => $r['date'],
                'date_precision' => 'day',
                'description' => $label.' ('.$r['section'].')'.($r['obs'] !== '' ? ' - '.$r['obs'] : ''),
                'section_from' => $sec['whole'] ? null : $sec['from'],
                'section_to' => $sec['whole'] ? null : $sec['to'],
                'section_pk_start_m' => $r['pk_start'],
                'section_pk_end_m' => $r['pk_end'],
                'source_url' => self::BASE.'fichiers/'.$r['file'].'.pdf',
                'source_quote' => $r['quote'],
                'confidence' => $r['code'] !== null ? 'high' : 'medium',
            ]);
            $this->stats['pdf'][$key.' importées'] = ($this->stats['pdf'][$key.' importées'] ?? 0) + 1;
            $this->stats['events'][$r['type']] = ($this->stats['events'][$r['type']] ?? 0) + 1;
        }

        return array_values($fps);
    }

    /** Décision annulée / abrogée, ou ligne jamais construite : pas un événement sur le terrain. */
    private function skipPdfRow(array $r): bool
    {
        return (bool) preg_match('/annul|abrog|non construite/iu', $r['obs']);
    }

    /** Même ligne, même type, même date, et même portion (PK à 100 m près, ou PK inconnus d'un côté). */
    private function isDuplicateOfFiche(int $lineId, array $r): bool
    {
        foreach ($this->ficheKeys[$lineId] ?? [] as [$type, $date, $a, $b]) {
            if ($type !== $r['type'] || $date !== $r['date']) {
                continue;
            }
            if ($a === null || $b === null || $r['pk_start'] === null || $r['pk_end'] === null) {
                return true;
            }
            [$a, $b] = [min($a, $b), max($a, $b)];
            [$c, $d] = [min($r['pk_start'], $r['pk_end']), max($r['pk_start'], $r['pk_end'])];
            if (abs($a - $c) <= 100 && abs($b - $d) <= 100) {
                return true;
            }
            $overlap = min($b, $d) - max($a, $c);
            if ($overlap > 0 && $overlap >= 0.8 * max(1, min($b - $a, $d - $c))) {
                return true;
            }
        }

        return false;
    }

    private function codeByName(string $ligne): ?string
    {
        $key = $this->lineKey($ligne);
        if ($key === '') {
            return null;
        }
        if (isset($this->nameIndex[$key])) {
            return $this->nameIndex[$key] === '?' ? null : $this->nameIndex[$key];
        }
        // Variante abrégée : « Sablé - Montoir-de-Bretagne » ↔ « Sablé-sur-Sarthe - Montoir-de-Bretagne ».
        [$a, $b] = explode('|', $key);
        $codes = [];
        foreach ($this->nameIndex as $k => $code) {
            [$c, $d] = explode('|', $k);
            if (($this->endMatch($a, $c) && $this->endMatch($b, $d)) || ($this->endMatch($a, $d) && $this->endMatch($b, $c))) {
                $codes[$code] = true;
            }
        }

        return count($codes) === 1 && array_key_first($codes) !== '?' ? (string) array_key_first($codes) : null;
    }

    /** Même toponyme, ou l'un est le début de l'autre jusqu'à un tiret / espace. */
    private function endMatch(string $x, string $y): bool
    {
        if ($x === $y) {
            return true;
        }
        [$short, $long] = strlen($x) < strlen($y) ? [$x, $y] : [$y, $x];
        if (strlen($short) >= 3 && str_starts_with($long, $short.'-')) {
            return true;
        }
        // Coquilles fréquentes des tableaux (« Badonvillier », « Viescamps », « Autueil »).
        $tol = strlen($short) >= 10 ? 2 : (strlen($short) >= 6 ? 1 : 0);

        return $tol > 0 && levenshtein($x, $y) <= $tol;
    }

    private function upsertEvent(string $fp, array $data): void
    {
        $data += ['source' => self::SOURCE, 'section_resolution' => 'pending', 'updated_at' => now()];
        $id = DB::table('line_events')->where('fingerprint', $fp)->value('id');
        if ($id) {
            // Garder la résolution déjà faite par le résolveur si la portion n'a pas changé ;
            // sinon la remettre en attente (géométrie à recalculer).
            $cur = DB::table('line_events')->where('id', $id)->first();
            $same = true;
            foreach (['line_id', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m'] as $col) {
                $same = $same && (string) $cur->{$col} === (string) $data[$col];
            }
            if ($same && $cur->section_resolution !== null && $cur->section_resolution !== 'pending') {
                unset($data['section_resolution']);
            } elseif (! $same) {
                $data['section_geom'] = null;
            }
            DB::table('line_events')->where('id', $id)->update($data);
        } else {
            DB::table('line_events')->insert($data + ['fingerprint' => $fp, 'created_at' => now()]);
        }
    }

    private function writeStations(array $fiches, array $lineIds): array
    {
        $refs = [];
        foreach ($fiches as $f) {
            $lineId = $f['code'] !== null ? ($lineIds[$f['code']] ?? null) : null;
            foreach ($f['stations'] as $st) {
                $ref = $f['page'].':'.Str::slug($st['name']);
                if (isset($refs[$ref])) {
                    $ref .= ':'.($st['pk_m'] ?? $st['order']);
                }
                $refs[$ref] = $ref;
                // Date de création gardée d'un import à l'autre (seule updated_at bouge).
                DB::table('source_stations')->updateOrInsert(
                    ['source' => self::SOURCE, 'source_ref' => $ref],
                    fn (bool $exists) => [
                        'line_id' => $lineId,
                        'name' => Str::limit($st['name'], 250, ''),
                        'pk_m' => $st['pk_m'],
                        'properties' => json_encode([
                            'line_code' => $f['code'],
                            'page' => $f['page'],
                            'order' => $st['order'],
                            'branch' => $st['group'],
                            'pk_raw' => $st['pk_raw'],
                            'pk_approx' => $st['pk_approx'],
                            'source_url' => $f['url'],
                        ], JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ] + ($exists ? [] : ['created_at' => now()]),
                );
                $this->stats['stations']++;
            }
        }

        return array_values($refs);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  Utilitaires
    // ════════════════════════════════════════════════════════════════════════

    private function report(): void
    {
        $this->newLine();
        $this->info("Lignes : {$this->stats['lines_matched']} rattachée(s) par code à une ligne existante, "
            ."{$this->stats['lines_created']} créée(s) par cet import (slug lo-*, dont {$this->stats['lines_existing_lo']} déjà présente(s) d'un passage précédent)");
        ksort($this->stats['events']);
        $this->table(['type', 'événements'], array_map(null, array_keys($this->stats['events']), array_values($this->stats['events'])));
        foreach ($this->stats['pdf'] as $k => $v) {
            $this->line("  PDF {$k} : {$v}");
        }
        $this->info("Gares (source_stations) : {$this->stats['stations']}");
        if ($this->unknownLabels !== []) {
            $this->warn('Rubriques non reconnues (importées en « other ») : '.json_encode($this->unknownLabels, JSON_UNESCAPED_UNICODE));
        }
        if ($this->skipped !== []) {
            $this->line('Entrées ignorées : '.count($this->skipped).' (-v pour le détail)');
            if ($this->output->isVerbose()) {
                foreach ($this->skipped as $s) {
                    $this->line('  · '.$s);
                }
            }
        }
    }

    private function dumpDry(array $fiches, array $pdfRows): void
    {
        foreach ($fiches as $f) {
            $this->line("<info>{$f['page']}</info> [{$f['code']}] {$f['title']}");
            foreach ($f['events'] as $e) {
                $this->line(sprintf('  %-18s %s %-5s %-6s %s → %s  [%s → %s] (%s)', $e['type'], $e['date'], $e['precision'], $e['confidence'],
                    $e['from'] ?? '∅', $e['to'] ?? '∅', $e['pk_start'] ?? '∅', $e['pk_end'] ?? '∅', $e['pk_origin'] ?? '-'));
            }
            foreach ($f['stations'] as $s) {
                $this->line("    gare {$s['order']}/{$s['group']} {$s['name']} ".($s['pk_m'] ?? '∅'));
            }
        }
        if ($this->unknownLabels !== []) {
            $this->warn('Rubriques non reconnues : '.json_encode($this->unknownLabels, JSON_UNESCAPED_UNICODE));
        }
        foreach ($this->skipped as $s) {
            $this->line('  · ignoré : '.$s);
        }
        $noNum = array_filter($pdfRows, fn ($r) => $r['code'] === null && ! $this->skipPdfRow($r));
        $unmatched = array_filter($noNum, fn ($r) => $this->codeByName($r['ligne']) === null);
        $this->line(count($pdfRows).' ligne(s) PDF ; sans numéro (hors annulées) : '.count($noNum).', dont non rattachables : '.count($unmatched));
        foreach ($unmatched as $r) {
            $this->line("  · PDF non rattachée : {$r['file']} - {$r['ligne']} / {$r['section']} / {$r['date']}");
        }
        $codeNames = [];
        foreach ($this->nameIndex as $k => $c) {
            $codeNames[$c][] = $k;
        }
        foreach ($noNum as $r) {
            $k = $this->lineKey($r['ligne']);
            if (! isset($this->nameIndex[$k]) && ($c = $this->codeByName($r['ligne'])) !== null) {
                $this->line("  · PDF rattachée par nom approché : {$r['ligne']} → {$c} (".implode(', ', $codeNames[$c]).')');
            }
        }
    }

    /** Texte brut : entités décodées, espaces insécables et blancs normalisés. */
    private function text(string $s): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $s));
    }

    /** Clé de comparaison : minuscules, sans accents, apostrophes droites, tirets longs → « - ». */
    private function norm(string $s): string
    {
        $s = mb_strtolower($this->text($s));
        $s = str_replace(['’', '‘', '`', '–', '—', '‒', 'œ', 'æ'], ["'", "'", "'", '-', '-', '-', 'oe', 'ae'], $s);
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s);

        $s = preg_replace('/\s*-\s*/', '-', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** « 476 000 » / « 476000 » → '476000' ; autre chose → null. */
    private function normCode(?string $s): ?string
    {
        $d = preg_replace('/\D/', '', (string) $s);

        return strlen($d) === 6 ? $d : null;
    }

    /** « 671,700 » / « 671+700 » / « -0,078 » / « 1 234,5 » → mètres. */
    private function pk(string $s): int
    {
        $s = str_ireplace([' ', "\u{00A0}", 'x'], ['', '', '0'], trim($s));
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$km, $m] = array_pad(preg_split('/[,+.]/', $s, 2), 2, '0');
        $m = str_pad(substr($m, 0, 3), 3, '0');
        $v = (int) $km * 1000 + (int) $m;

        return $neg ? -$v : $v;
    }

    private function gauge(?string $s): ?int
    {
        $n = $this->norm((string) $s);

        return match (true) {
            str_contains($n, 'normale') => 1435,
            str_contains($n, 'metrique') => 1000,
            default => null,
        };
    }

    private function tracks(?string $s): ?int
    {
        $n = $this->norm((string) $s);

        return match (true) {
            str_contains($n, 'unique') => 1,
            str_contains($n, 'double') => 2,
            default => null,
        };
    }

    /** « Gretz-Armainvilliers - Sézanne » → « Ligne de Gretz-Armainvilliers à Sézanne » (élisions françaises). */
    private function lineName(string $pair): string
    {
        $parts = preg_split('/\s+[-–]\s+/u', trim($pair));
        if (count($parts) !== 2 || preg_match('/^(voie|raccordement|embranchement|ligne|antenne|section|ceinture|grande|petite)/iu', $parts[0])) {
            return $pair;
        }
        $de = function (string $p): string {
            return match (true) {
                (bool) preg_match('/^Le\s/u', $p) => 'du '.substr($p, 3),
                (bool) preg_match('/^Les\s/u', $p) => 'des '.substr($p, 4),
                (bool) preg_match('/^[AEIOUYÉÈÊÂÎÔH]/u', $p) => 'd’'.$p,
                default => 'de '.$p,
            };
        };
        $a = function (string $p): string {
            return match (true) {
                (bool) preg_match('/^Le\s/u', $p) => 'au '.substr($p, 3),
                (bool) preg_match('/^Les\s/u', $p) => 'aux '.substr($p, 4),
                default => 'à '.$p,
            };
        };

        return 'Ligne '.$de($parts[0]).' '.$a($parts[1]);
    }
}
