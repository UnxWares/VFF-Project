<?php

namespace App\Services\Signalling;

use App\Services\WikimediaClient;
use Illuminate\Support\Sleep;

/**
 * Cache local des wikitextes d'articles de lignes (un fichier JSON par titre), pour relire les articles
 * sans les retélécharger. Le dossier porte un `.nobackup` : contenu régénérable, hors sauvegarde.
 *
 * Le téléchargement passe par WikimediaClient::api() (User-Agent du robot, maxlag, reprises), 50 titres par
 * requête, avec une pause entre deux lots. Un article est mémorisé absent (`missing`) seulement quand l'API le
 * dit (page `missing` ou `invalid`), pour ne pas être redemandé à chaque exécution. Une réponse d'erreur (par
 * exemple `readonly` pendant une maintenance, en HTTP 200) arrête le remplissage sans rien écrire pour le lot :
 * une panne passagère ne fait jamais passer un article pour absent.
 */
final class WikipediaArticleCache
{
    private const PAUSE_SECONDS = 2;

    public function __construct(private readonly WikimediaClient $wiki, private readonly string $directory) {}

    /** Dossier du cache d'une exécution : celui de la commande, ou un dossier temporaire sous test. */
    public static function defaultDirectory(): string
    {
        return app()->runningUnitTests()
            ? sys_get_temp_dir().'/vff-signalling-history-test'
            : storage_path('app/data/signalisation-historique/wikipedia');
    }

    /**
     * Article en cache, ou null s'il n'y est pas (ou s'il n'existe pas sur Wikipédia).
     *
     * @return array{title: string, revid: int, content: string, fetched_at: string}|null
     */
    public function get(string $title): ?array
    {
        $entry = $this->read($title);

        return $entry !== null && ! $entry['missing'] ? $entry : null;
    }

    public function has(string $title): bool
    {
        return $this->read($title) !== null;
    }

    /**
     * Télécharge les titres absents du cache (ou tous avec $refresh) et les range.
     *
     * @param  list<string>  $titles
     * @param  callable(int, int): void|null  $progress  appelée avec (lots faits, lots à faire)
     * @return int nombre d'articles demandés à Wikipédia
     */
    public function fill(array $titles, bool $refresh = false, ?callable $progress = null): int
    {
        $todo = array_values(array_filter(array_unique($titles), fn (string $t) => $refresh || ! $this->has($t)));
        if ($todo === []) {
            return 0;
        }
        $this->prepareDirectory();
        $chunks = array_chunk($todo, 50);
        foreach ($chunks as $n => $chunk) {
            if ($n > 0) {
                Sleep::sleep(self::PAUSE_SECONDS);
            }
            [$pages, $absent] = $this->fetch($chunk);
            foreach ($chunk as $title) {
                $page = $pages[$title] ?? null;
                if ($page === null && ! in_array($title, $absent, true)) {
                    // Ni lu ni déclaré absent par l'API (révision sans contenu) : rien n'est mémorisé.
                    continue;
                }
                file_put_contents($this->path($title), json_encode([
                    'requested' => $title,
                    'missing' => $page === null,
                    'title' => $page['title'] ?? $title,
                    'revid' => $page['revid'] ?? 0,
                    'content' => $page['content'] ?? '',
                    'fetched_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            if ($progress !== null) {
                $progress($n + 1, count($chunks));
            }
        }

        return count($todo);
    }

    /**
     * Wikitexte d'un lot de titres (redirections suivies) et titres que l'API déclare absents.
     *
     * @param  list<string>  $titles
     * @return array{0: array<string, array{title: string, revid: int, content: string}>, 1: list<string>}
     *
     * @throws \RuntimeException si l'API répond par une erreur
     */
    private function fetch(array $titles): array
    {
        $params = ['action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content|ids', 'rvslots' => 'main', 'redirects' => 1, 'titles' => implode('|', $titles)];
        $pages = [];
        $missing = [];
        $aliases = [];
        $continue = [];
        do {
            $json = $this->wiki->api($params + $continue);
            if (isset($json['error'])) {
                throw new \RuntimeException('Wikipédia a répondu par une erreur ('.($json['error']['code'] ?? '?').') : lot non mis en cache.');
            }
            foreach (['normalized', 'redirects'] as $k) {
                foreach ($json['query'][$k] ?? [] as $n) {
                    $aliases[$n['from']] = $n['to'];
                }
            }
            foreach ($json['query']['pages'] ?? [] as $p) {
                if (! empty($p['missing']) || ! empty($p['invalid'])) {
                    $missing[] = (string) $p['title'];
                } elseif (! empty($p['revisions'][0]['slots']['main']['content'])) {
                    $pages[$p['title']] = ['title' => $p['title'], 'revid' => (int) $p['revisions'][0]['revid'], 'content' => (string) $p['revisions'][0]['slots']['main']['content']];
                }
            }
            $continue = $json['continue'] ?? [];
        } while ($continue);

        $found = [];
        $absent = [];
        foreach ($titles as $t) {
            $final = $t;
            for ($i = 0; $i < 3 && isset($aliases[$final]); $i++) {
                $final = $aliases[$final];
            }
            if (isset($pages[$final])) {
                $found[$t] = $pages[$final];
            } elseif (in_array($final, $missing, true)) {
                $absent[] = $t;
            }
        }

        return [$found, $absent];
    }

    /** @return array{title: string, revid: int, content: string, fetched_at: string, missing: bool}|null */
    private function read(string $title): ?array
    {
        $path = $this->path($title);
        if (! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? [
            'title' => (string) ($json['title'] ?? $title),
            'revid' => (int) ($json['revid'] ?? 0),
            'content' => (string) ($json['content'] ?? ''),
            'fetched_at' => (string) ($json['fetched_at'] ?? ''),
            'missing' => (bool) ($json['missing'] ?? false) || ($json['content'] ?? '') === '',
        ] : null;
    }

    private function path(string $title): string
    {
        return rtrim($this->directory, '/').'/'.md5($title).'.json';
    }

    private function prepareDirectory(): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
        // `.nobackup` au niveau du sujet (dossier parent), pour la sauvegarde restic.
        $marker = dirname(rtrim($this->directory, '/')).'/.nobackup';
        if (! is_file($marker)) {
            file_put_contents($marker, '');
        }
    }
}
