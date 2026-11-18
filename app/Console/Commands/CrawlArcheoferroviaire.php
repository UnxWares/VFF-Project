<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Crawler archeoferroviaire.free.fr (tâche #118) - signal NÉGATIF de fermeture.
 *
 * archeoferroviaire.free.fr/v31/ recense exclusivement des lignes DISPARUES.
 * Le « plan d'accueil » (spip.php) liste, sous des rubriques régionales
 * (FRANCE NORD/EST/OUEST/SUD-OUEST/SUD-EST), un article par ligne disparue,
 * nommé par sa paire de villes-extrémités (ex « Mézidon Dives »). L'existence
 * d'un tel article catégorisé ⇒ la ligne est fermée/déposée, jamais ré-exploitée.
 *
 * Cette commande :
 *   1. récupère le plan d'accueil (en-têtes NAVIGATEUR obligatoires, sinon 503) ;
 *   2. parse les <a href="spip.php?articleNN">Paire de villes</a> sous les
 *      rubriques régionales françaises - EXCLUT « Carte générale » et suffixe '°' ;
 *   3. stocke chaque article dans archeo_lines (idempotent par article_id) ;
 *   4. matche name → lines.name via les villes-extrémités → matched_line_id.
 *
 * Elle NE déclasse PAS les lignes VFF matchées : ça reste à une étape de
 * curation ultérieure. Ici on se contente de peupler archeo_lines.
 */
class CrawlArcheoferroviaire extends Command
{
    protected $signature = 'vff:crawl-archeo
        {--limit= : Nombre max d\'articles à traiter (pour ne pas marteler le serveur)}';

    protected $description = 'Crawle archeoferroviaire (lignes disparues) → archeo_lines + matched_line_id';

    private const BASE = 'http://archeoferroviaire.free.fr/v31/';

    /** Rubriques régionales françaises retenues (le reste = cartes / outre-mer / etc.). */
    private const REGIONS = ['FRANCE NORD', 'FRANCE EST', 'FRANCE OUEST', 'FRANCE SUD-OUEST', 'FRANCE SUD-EST'];

    /** Mots vides ignorés lors de la comparaison de toponymes. */
    private const STOPWORDS = [
        'de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'a', 'au', 'aux', 'et',
        'sur', 'sous', 'en', 'lez', 'les', 'saint', 'sainte', 'st', 'ste',
        'ligne', 'raccordement', 'embranchement', 'gare', 'ville', 'maritime',
        'via', 'sa', 'son', 'son', 'militaire',
    ];

    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $this->info('Récupération du plan d\'accueil archeoferroviaire…');
        $html = $this->fetch(self::BASE.'spip.php');
        if ($html === null) {
            $this->error('Plan d\'accueil inaccessible (HTTP).');

            return self::FAILURE;
        }
        $this->line('  → '.number_format(strlen($html)).' octets reçus');

        $articles = $this->parseArticles($html);
        $this->line('  → '.count($articles).' article(s) régionaux retenus (hors « Carte générale » / suffixe °)');

        if ($limit !== null && count($articles) > $limit) {
            $articles = array_slice($articles, 0, $limit);
            $this->line("  → limité à {$limit} article(s) (--limit)");
        }

        // Index des lignes VFF : id => set de toponymes normalisés extraits du nom.
        $lineIndex = $this->buildLineIndex();
        $this->line('  → '.count($lineIndex).' ligne(s) VFF indexées pour le matching');

        $stored = 0;
        $matched = 0;

        foreach ($articles as $a) {
            $matchId = $this->matchLine($a['name'], $lineIndex);

            // created_at conservé au ré-import (updateOrInsert l'écrasait à chaque passage).
            DB::table('archeo_lines')->upsert([[
                'article_id' => $a['article_id'],
                'name' => $a['name'],
                'rubrique' => $a['rubrique'],
                'url' => self::BASE.'spip.php?article'.$a['article_id'],
                'matched_line_id' => $matchId,
                'updated_at' => now(),
                'created_at' => now(),
            ]], ['article_id'], ['name', 'rubrique', 'url', 'matched_line_id', 'updated_at']);

            $stored++;
            if ($matchId !== null) {
                $matched++;
                $this->line(sprintf('  ✓ #%-5d %-45s → line #%d', $a['article_id'], mb_strimwidth($a['name'], 0, 45), $matchId));
            }
        }

        $this->newLine();
        $this->info("OK - {$stored} article(s) en base, {$matched} matché(s) à une ligne VFF.");

        return self::SUCCESS;
    }

    /**
     * GET avec en-têtes NAVIGATEUR (sinon HTTP 503), backoff 4-5 s sur 503.
     * Le corps est en UTF-8 valide malgré le charset iso-8859-1 déclaré → pas d'iconv.
     */
    private function fetch(string $url): ?string
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $resp = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9',
                'Accept-Encoding' => 'gzip, deflate',
            ])->timeout(30)->get($url);

            if ($resp->status() === 503) {
                $wait = 4 + $attempt; // 5,6,7,8 s
                $this->warn("  HTTP 503 (tentative {$attempt}) - nouvel essai dans {$wait} s…");
                Sleep::sleep($wait);

                continue;
            }
            if (! $resp->ok()) {
                $this->warn("  HTTP {$resp->status()} sur {$url}");

                return null;
            }

            return $resp->body();
        }

        $this->warn("  Abandon après 503 répétés sur {$url}");

        return null;
    }

    /**
     * Extrait les articles régionaux du plan d'accueil.
     *
     * Le plan est un menu déroulant : les 6 ancres « FRANCE … » (CARTES
     * GENERALES, NORD, EST, OUEST, SUD-OUEST, SUD-EST, D'OUTRE-MER) sont en
     * ordre positionnel. On affecte chaque article à la région dont l'ancre
     * précède immédiatement sa position dans le HTML, et on ne garde que les
     * régions métropolitaines (self::REGIONS).
     *
     * @return list<array{article_id:int,name:string,rubrique:string}>
     */
    private function parseArticles(string $html): array
    {
        // Positions des ancres régionales (texte affiché en MAJUSCULES).
        $regionAt = []; // pos => libellé région
        foreach (['FRANCE CARTES GENERALES', 'FRANCE NORD', 'FRANCE EST', 'FRANCE OUEST', 'FRANCE SUD-OUEST', 'FRANCE SUD-EST', 'FRANCE D'] as $label) {
            $pos = mb_stripos($html, $label, 0, 'UTF-8');
            // mb_stripos renvoie un index en caractères ; on veut un offset octets cohérent
            $bpos = stripos($html, $label);
            if ($bpos !== false) {
                $regionAt[$bpos] = $label === 'FRANCE D' ? 'FRANCE OUTRE-MER' : $label;
            }
        }
        ksort($regionAt);
        $bounds = array_keys($regionAt); // positions triées

        $resolveRegion = function (int $offset) use ($regionAt, $bounds): ?string {
            $current = null;
            foreach ($bounds as $p) {
                if ($p <= $offset) {
                    $current = $regionAt[$p];
                } else {
                    break;
                }
            }

            return $current;
        };

        preg_match_all('#<a href="spip\.php\?article(\d+)"[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $out = [];
        $seen = [];
        foreach ($m as $match) {
            $articleId = (int) $match[1][0];
            $offset = $match[0][1];
            $name = $this->cleanText($match[2][0]);

            if ($name === '' || isset($seen[$articleId])) {
                continue;
            }

            $region = $resolveRegion($offset);
            if ($region === null || ! in_array($region, self::REGIONS, true)) {
                continue; // cartes générales / outre-mer / hors zone
            }

            // EXCLUSIONS : articles « Carte générale » et suffixe '°'.
            if (str_contains($name, '°') || mb_stripos($name, 'carte g', 0, 'UTF-8') !== false) {
                continue;
            }

            $seen[$articleId] = true;
            $out[] = [
                'article_id' => $articleId,
                'name' => $name,
                'rubrique' => $region,
            ];
        }

        return $out;
    }

    /** Nettoie le texte visible d'une ancre (strip tags, entités, espaces). */
    private function cleanText(string $s): string
    {
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // apostrophes typographiques / nbsp
        $s = str_replace(["\u{2019}", "\u{00A0}", "\u{202F}"], ["'", ' ', ' '], $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Construit l'index VFF : line_id => liste de toponymes normalisés extraits
     * du nom « Ligne de X à Y », et un set unifié pour le matching.
     *
     * @return array<int,array{name:string,tokens:array<string,bool>,ends:list<string>}>
     */
    private function buildLineIndex(): array
    {
        $index = [];
        foreach (DB::table('lines')->whereNotNull('name')->get(['id', 'name']) as $l) {
            $tokens = $this->toponymTokens($l->name);
            if (empty($tokens)) {
                continue;
            }
            $index[$l->id] = [
                'name' => $l->name,
                'tokens' => array_flip($tokens),
                'ends' => $this->endpointTokens($l->name),
            ];
        }

        return $index;
    }

    /**
     * Matche un nom archeo (« Mézidon Dives ») à une ligne VFF.
     *
     * Principe : on extrait les toponymes des deux côtés. On exige que les DEUX
     * extrémités de la paire archeo soient présentes parmi les toponymes du nom
     * VFF (rapprochement « confiant »). À défaut, on accepte un match si l'unique
     * meilleure ligne couvre les deux extrémités sans ambiguïté.
     */
    private function matchLine(string $archeoName, array $lineIndex): ?int
    {
        $ends = $this->endpointTokens($archeoName);
        // Toponymes significatifs de la paire archeo (≥ 3 lettres après normalisation).
        $needles = array_values(array_filter($this->toponymTokens($archeoName), fn ($t) => mb_strlen($t) >= 3));
        if (count($needles) < 2) {
            return null; // pas assez d'info pour un rapprochement confiant
        }

        $candidates = [];
        foreach ($lineIndex as $lineId => $data) {
            $hits = 0;
            foreach ($needles as $t) {
                if (isset($data['tokens'][$t])) {
                    $hits++;
                }
            }
            if ($hits === 0) {
                continue;
            }

            // Bonus fort si les DEUX extrémités archeo sont couvertes.
            $endsCovered = 0;
            foreach ($ends as $e) {
                if ($e !== '' && isset($data['tokens'][$e])) {
                    $endsCovered++;
                }
            }

            // Score : couverture extrémités prioritaire, puis ratio de toponymes,
            // pénalisé par la longueur (préfère le nom VFF le plus spécifique).
            $coverage = $hits / count($needles);
            $score = ($endsCovered * 10) + ($coverage * 5) - (count($data['tokens']) * 0.05);
            $candidates[] = ['id' => $lineId, 'score' => $score, 'ends' => $endsCovered, 'coverage' => $coverage];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $candidates[0];

        // Confiance : on n'accepte que si les 2 extrémités sont couvertes,
        // OU si toutes les toponymes archeo sont présents (coverage == 1) ET
        // le meilleur candidat domine nettement le suivant.
        $confidentByEnds = ($best['ends'] >= 2);
        $confidentByCoverage = ($best['coverage'] >= 1.0)
            && (count($candidates) === 1 || ($candidates[0]['score'] - ($candidates[1]['score'] ?? -INF)) >= 2.0);

        if ($confidentByEnds || $confidentByCoverage) {
            return $best['id'];
        }

        return null;
    }

    /**
     * Les deux toponymes-extrémités d'un nom de ligne.
     *
     * - VFF « Ligne de X à Y » → [premier mot fort de X, premier mot fort de Y]
     * - archeo « Ville-A Ville-B » → [premier, dernier groupe fort]
     *
     * @return list<string> 0..2 entrées
     */
    private function endpointTokens(string $name): array
    {
        $s = $name;
        // Coupe le préfixe « Ligne … de/du/d' » ou « Raccordement … de ».
        $s = preg_replace('/^(ligne|raccordement|embranchement)\b.*?\b(de|du|d\x27|des)\s+/iu', '', $s) ?? $s;
        $s = preg_replace('/\([^)]*\)/u', ' ', $s) ?? $s; // (via …)

        // Séparateur d'extrémités : « à » (VFF) ou simple espace (archeo).
        if (preg_match('/\s+à\s+/u', $s)) {
            $parts = preg_split('/\s+à\s+/u', $s, 2);
        } else {
            // archeo : « Ville-A Ville-B » → on coupe au milieu sur l'espace
            $words = preg_split('/\s+/u', trim($s)) ?: [];
            if (count($words) <= 1) {
                $parts = [$s];
            } else {
                $mid = (int) floor(count($words) / 2);
                $parts = [implode(' ', array_slice($words, 0, $mid)), implode(' ', array_slice($words, $mid))];
            }
        }

        $ends = [];
        foreach ($parts as $p) {
            $tok = $this->toponymTokens($p);
            // toponyme représentatif = le plus long de l'extrémité
            usort($tok, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            if (! empty($tok)) {
                $ends[] = $tok[0];
            }
        }

        return array_slice(array_values(array_filter($ends)), 0, 2);
    }

    /**
     * Normalise un libellé en jeu de toponymes comparables : minuscule, accents
     * repliés, séparateurs (-, /, espaces) éclatés, mots vides retirés.
     *
     * @return list<string>
     */
    private function toponymTokens(string $s): array
    {
        $s = mb_strtolower($s, 'UTF-8');
        // accents → ASCII
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'û' => 'u', 'ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n',
            'œ' => 'oe', 'æ' => 'ae',
        ]);
        // tout séparateur → espace
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
        $words = array_filter(explode(' ', $s), fn ($w) => $w !== '');

        $out = [];
        foreach ($words as $w) {
            if (in_array($w, self::STOPWORDS, true)) {
                continue;
            }
            if (mb_strlen($w) < 2) {
                continue;
            }
            $out[] = $w;
        }

        return array_values(array_unique($out));
    }
}
