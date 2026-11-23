<?php

namespace App\Services\Archives;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Client poli de l'API SRU du catalogue général de la BnF (https://catalogue.bnf.fr/api/SRU), décrite sur
 * https://api.bnf.fr : notices UNIMARC, 50 par page, une requête par seconde au plus, User-Agent générique
 * de VFF (aucune donnée personnelle), trois essais en cas d'erreur du serveur (Retry-After respecté).
 *
 * Chaque page de réponse est gardée sur disque (storage/app/data/gallica/sru/, dossier marqué `.nobackup`) :
 * une relance relit le cache sans requête, ce qui permet de reprendre un import interrompu. `offline` :
 * cache seulement (une page absente est une erreur : la requête n'a pas de réponse) ; `refresh` : le cache est
 * ignoré et réécrit.
 *
 * Pourquoi le catalogue général et pas l'API SRU de Gallica (gallica.bnf.fr/SRU) : le 20/11/2026, gallica.bnf.fr
 * refusait toutes les requêtes de la machine de développement (blocage Cloudflare). Le catalogue décrit les
 * mêmes documents numérisés de la BnF (critère bib.digitized = freeAccess), avec des métadonnées sous Licence
 * Ouverte, et renvoie le lien vers Gallica de chaque document.
 */
class CatalogueSruClient
{
    public const ENDPOINT = 'https://catalogue.bnf.fr/api/SRU';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const PAGE_SIZE = 50;

    /** Pages lues au plus par requête (200 notices) : au-delà, la requête est trop large pour être utile. */
    public const MAX_PAGES = 4;

    /** Intervalle minimal entre deux requêtes au serveur, en millisecondes. */
    public const MIN_INTERVAL_MS = 1000;

    private const ATTEMPTS = 3;

    private ?float $lastRequestAt = null;

    public int $networkRequests = 0;

    public int $cacheHits = 0;

    public int $cacheMisses = 0;

    public function __construct(
        private readonly string $cacheDir,
        private readonly CatalogueSruParser $parser = new CatalogueSruParser,
        private readonly bool $offline = false,
        private readonly bool $refresh = false,
        private readonly int $intervalMs = self::MIN_INTERVAL_MS,
    ) {}

    public static function default(bool $offline = false, bool $refresh = false, ?string $cacheDir = null, int $intervalMs = self::MIN_INTERVAL_MS): self
    {
        return new self($cacheDir ?? storage_path('app/data/gallica'), new CatalogueSruParser, $offline, $refresh, $intervalMs);
    }

    /**
     * Notices d'une requête CQL, toutes pages lues (MAX_PAGES au plus).
     *
     * `fetched_at` : date de récupération des notices (lecture du serveur, ou date du fichier en cache), à
     * citer avec la source selon les conditions de la BnF ; null si aucune page n'a été lue.
     *
     * @return array{total: int, records: list<ArchiveRecord>, truncated: bool, fetched_at: ?int}
     *
     * @throws RuntimeException serveur injoignable ou réponse refusée après trois essais, réponse qui n'est pas du
     *                          SRU, ou page absente du cache hors ligne
     */
    public function search(string $cql): array
    {
        /** @var array{total: int, records: list<ArchiveRecord>, truncated: bool, fetched_at: ?int} $result */
        $result = $this->searchWith($cql, $this->parser->parse(...));

        return $result;
    }

    /**
     * Comme search(), avec un autre lecteur des pages de réponse (la bibliographie lit des livres, pas des documents
     * de Gallica) ; même cache, mêmes essais, même cadence. La page est gardée en cache seulement si `$parse` l'accepte.
     *
     * @param  callable(string): array{total: int, records: list<mixed>}  $parse
     * @return array{total: int, records: list<mixed>, truncated: bool, fetched_at: ?int}
     *
     * @throws RuntimeException voir search()
     */
    public function searchWith(string $cql, callable $parse): array
    {
        $records = [];
        $total = 0;
        $fetchedAt = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            [$xml, $time] = $this->page($cql, $page * self::PAGE_SIZE + 1, $parse);
            $fetchedAt = $fetchedAt === null ? $time : min($fetchedAt, $time);
            $parsed = $parse($xml);
            $total = $parsed['total'];
            $records = [...$records, ...$parsed['records']];
            if ($total <= ($page + 1) * self::PAGE_SIZE) {
                break;
            }
        }

        return ['total' => $total, 'records' => $records, 'truncated' => $total > self::MAX_PAGES * self::PAGE_SIZE, 'fetched_at' => $fetchedAt];
    }

    /**
     * Une page de réponse (XML) et sa date de lecture (horodatage Unix), du cache ou du serveur.
     *
     * @param  callable(string): mixed  $parse
     * @return array{0: string, 1: int}
     *
     * @throws RuntimeException hors ligne, page absente du cache
     */
    private function page(string $cql, int $start, callable $parse): array
    {
        $path = $this->cacheDir.'/sru/'.sha1($cql.'#'.$start).'.xml';
        if (! $this->refresh && is_file($path)) {
            $this->cacheHits++;

            return [(string) file_get_contents($path), (int) filemtime($path)];
        }
        if ($this->offline) {
            $this->cacheMisses++;

            // Une page absente ne vaut pas une réponse vide : la ligne garderait sinon zéro document.
            throw new RuntimeException('page absente du cache local (--offline)');
        }
        $xml = $this->fetch($cql, $start);
        // Une réponse refusée (diagnostic SRU) lève une exception ici et n'est jamais mise en cache.
        $parse($xml);
        File::ensureDirectoryExists(dirname($path));
        if (! is_file($this->cacheDir.'/.nobackup')) {
            File::put($this->cacheDir.'/.nobackup', '');
        }
        File::put($path, $xml);

        return [$xml, time()];
    }

    private function fetch(string $cql, int $start): string
    {
        $error = 'aucun essai';
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $this->throttle();
            $this->networkRequests++;
            try {
                $response = Http::withUserAgent(self::USER_AGENT)->timeout(60)->get(self::ENDPOINT, [
                    'version' => '1.2',
                    'operation' => 'searchRetrieve',
                    'query' => $cql,
                    'recordSchema' => 'unimarcXchange',
                    'maximumRecords' => self::PAGE_SIZE,
                    'startRecord' => $start,
                ]);
            } catch (ConnectionException $e) {
                $error = $e->getMessage();
                Sleep::sleep(5 * $attempt);

                continue;
            }
            if ($response->successful()) {
                return $response->body();
            }
            $error = 'HTTP '.$response->status();
            if ($response->status() !== 429 && $response->status() < 500) {
                break;
            }
            $retryAfter = (int) $response->header('Retry-After');
            Sleep::sleep($retryAfter > 0 ? min($retryAfter, 120) : 5 * $attempt);
        }

        throw new RuntimeException("API SRU du catalogue de la BnF : {$error}");
    }

    /** Une requête par seconde au plus. */
    private function throttle(): void
    {
        $now = microtime(true);
        if ($this->lastRequestAt !== null) {
            $wait = (int) round($this->intervalMs * 1000 - ($now - $this->lastRequestAt) * 1_000_000);
            if ($wait > 0) {
                Sleep::usleep($wait);
            }
        }
        $this->lastRequestAt = microtime(true);
    }
}
