<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Client minimal et poli pour l'API MediaWiki (fr.wikipedia.org) et le
 * service SPARQL de Wikidata.
 *
 * Règles d'usage Wikimedia respectées :
 *   - User-Agent identifiant le robot (« VFF-Bot/1.0 (+https://vff-project.org) ») ;
 *   - maxlag=5 sur toutes les requêtes API, avec attente + nouvel essai quand
 *     les serveurs sont en retard (erreur « maxlag » ou HTTP 429/503) ;
 *   - requêtes GROUPÉES (jusqu'à 50 titres par appel) plutôt qu'un appel par page ;
 *   - un seul appel SPARQL pour toute la table code ligne → article.
 */
class WikimediaClient
{
    public const FRWIKI_API = 'https://fr.wikipedia.org/w/api.php';

    public const WIKIDATA_SPARQL = 'https://query.wikidata.org/sparql';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    private const INFOBOX_TEMPLATE = 'Modèle:Infobox Ligne ferroviaire';

    /**
     * Appel API MediaWiki avec maxlag + reprises.
     */
    public function api(array $params, string $endpoint = self::FRWIKI_API): array
    {
        $params += ['format' => 'json', 'formatversion' => 2, 'maxlag' => 5];
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $res = Http::withUserAgent(self::USER_AGENT)->timeout(60)->get($endpoint, $params);
            if (in_array($res->status(), [429, 503], true)) {
                Sleep::sleep(min(60, (int) ($res->header('Retry-After') ?: 5 * $attempt)));

                continue;
            }
            $json = $res->json();
            if (($json['error']['code'] ?? null) === 'maxlag') {
                Sleep::sleep(min(60, (int) ($res->header('Retry-After') ?: 5)));

                continue;
            }
            if (! $res->ok() || ! is_array($json)) {
                if ($attempt < 6) {
                    Sleep::sleep(2 * $attempt);

                    continue;
                }
                throw new \RuntimeException("MediaWiki HTTP {$res->status()}");
            }

            return $json;
        }
        throw new \RuntimeException('MediaWiki : trop de reprises (maxlag).');
    }

    /**
     * Wikitexte + identifiant de révision (pour le permalien oldid) de plusieurs
     * pages, 50 par requête, redirections suivies.
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, content: string}> indexé par titre DEMANDÉ
     */
    public function fetchPages(array $titles, bool $leadOnly = false): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($titles)), 50) as $chunk) {
            $params = [
                'action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content|ids',
                'rvslots' => 'main', 'redirects' => 1, 'titles' => implode('|', $chunk),
            ];
            if ($leadOnly) {
                $params['rvsection'] = 0;
            }
            $pages = [];
            $aliases = [];
            $continue = [];
            do {
                $json = $this->api($params + $continue);
                foreach (['normalized', 'redirects'] as $k) {
                    foreach ($json['query'][$k] ?? [] as $n) {
                        $aliases[$n['from']] = $n['to'];
                    }
                }
                foreach ($json['query']['pages'] ?? [] as $p) {
                    if (! empty($p['missing']) || empty($p['revisions'][0]['slots']['main']['content'])) {
                        continue;
                    }
                    $pages[$p['title']] = [
                        'title' => $p['title'],
                        'revid' => (int) $p['revisions'][0]['revid'],
                        'content' => (string) $p['revisions'][0]['slots']['main']['content'],
                    ];
                }
                $continue = $json['continue'] ?? [];
            } while ($continue);

            foreach ($chunk as $t) {
                $final = $t;
                for ($i = 0; $i < 3 && isset($aliases[$final]); $i++) {
                    $final = $aliases[$final];
                }
                if (isset($pages[$final])) {
                    $out[$t] = $pages[$final];
                }
            }
        }

        return $out;
    }

    /**
     * Index « code ligne RFN → titres d'articles » construit en lisant
     * l'en-tête (infobox) de TOUS les articles qui incluent l'infobox ligne
     * ferroviaire (~80 requêtes groupées). Champ « numéro » : « 956 000 »,
     * « {{numéro|956000}} », plusieurs codes possibles. Mis en cache 7 jours.
     *
     * @return array{codes: array<string, list<string>>, titles: list<string>}
     */
    public function infoboxIndex(bool $refresh = false): array
    {
        $key = 'vff:wikipedia:infobox-index:v1';
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addDays(7), function (): array {
            $codes = [];
            $titles = [];
            $params = [
                'action' => 'query', 'generator' => 'embeddedin', 'geititle' => self::INFOBOX_TEMPLATE,
                'geinamespace' => 0, 'geilimit' => 50, 'prop' => 'revisions', 'rvprop' => 'content',
                'rvslots' => 'main', 'rvsection' => 0,
            ];
            $continue = [];
            do {
                $json = $this->api($params + $continue);
                foreach ($json['query']['pages'] ?? [] as $p) {
                    $titles[$p['title']] = true;
                    $content = $p['revisions'][0]['slots']['main']['content'] ?? null;
                    if (! $content) {
                        continue;
                    }
                    foreach (self::codesFromInfobox($content) as $code) {
                        $codes[$code][] = $p['title'];
                    }
                }
                $continue = $json['continue'] ?? [];
            } while ($continue);

            return ['codes' => array_map(fn ($t) => array_values(array_unique($t)), $codes), 'titles' => array_keys($titles)];
        });
    }

    /**
     * Codes RFN (6 chiffres) déclarés dans le champ « numéro » d'une infobox.
     *
     * @return list<string>
     */
    public static function codesFromInfobox(string $wikitext): array
    {
        if (! preg_match('/\|\s*numéro\s*=([^\n]*(?:\n(?!\s*\|)[^\n]*)*)/u', $wikitext, $m)) {
            return [];
        }
        $v = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>/su', ' ', $m[1]);
        preg_match_all('/(?<![\d.])(\d{3})[\s\x{00A0}\x{202F}.]?(\d{3})(?![\d.])/u', $v, $mm, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn ($x) => $x[1].$x[2], $mm)));
    }

    /**
     * Table Wikidata « code ligne (P1671, pays France) → article frwiki », un
     * seul appel SPARQL, mise en cache 1 jour.
     *
     * @return array<string, list<string>>
     */
    public function wikidataLineCodes(bool $refresh = false): array
    {
        $key = 'vff:wikidata:p1671-frwiki:v1';
        if ($refresh) {
            Cache::forget($key);
        }

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $query = 'SELECT ?code ?article WHERE { ?item wdt:P1671 ?code ; wdt:P17 wd:Q142 . '
            .'?article schema:about ?item ; schema:isPartOf <https://fr.wikipedia.org/> . }';
        $ok = false;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $res = Http::withUserAgent(self::USER_AGENT)->timeout(90)
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->asForm()->post(self::WIKIDATA_SPARQL, ['query' => $query]);
            if ($ok = ($res->ok() && is_array($res->json()))) {
                break;
            }
            if ($attempt < 4) {
                Sleep::sleep(5 * $attempt);
            }
        }
        // Service indisponible : table vide pour CET appel, sans la mettre en cache
        // (avant : la panne était mémorisée 24 h et privait les imports suivants des codes).
        if (! $ok) {
            return [];
        }
        $map = [];
        foreach ($res->json('results.bindings') ?? [] as $b) {
            $code = preg_replace('/[\s.]/u', '', (string) ($b['code']['value'] ?? ''));
            if (! preg_match('/^\d{6}$/', $code)) {
                continue;
            }
            $title = str_replace('_', ' ', rawurldecode(substr((string) $b['article']['value'], strlen('https://fr.wikipedia.org/wiki/'))));
            $map[$code][] = $title;
        }
        $map = array_map(fn ($t) => array_values(array_unique($t)), $map);
        Cache::put($key, $map, now()->addDay());

        return $map;
    }

    /**
     * Recherche plein texte frwiki (espace principal).
     *
     * @return list<string>
     */
    public function search(string $query, int $limit = 8): array
    {
        // Mise en cache 7 jours : la recherche par nom des ~1 700 lignes sans code est le poste le plus lent.
        return Cache::remember('vff:wikipedia:search:'.md5($query.'|'.$limit), now()->addDays(7), fn () => $this->searchUncached($query, $limit));
    }

    private function searchUncached(string $query, int $limit): array
    {
        $json = $this->api([
            'action' => 'query', 'list' => 'search', 'srsearch' => $query,
            'srnamespace' => 0, 'srlimit' => $limit, 'srprop' => '',
        ]);

        return array_map(fn ($r) => $r['title'], $json['query']['search'] ?? []);
    }

    public static function permalink(string $title, int $revid): string
    {
        return 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$revid;
    }
}
