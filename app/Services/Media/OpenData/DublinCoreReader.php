<?php

namespace App\Services\Media\OpenData;

use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Lit les images d'une bibliothèque numérique de marque blanche Gallica dont le plan du site n'est pas public (Rosalis à
 * Toulouse, Pireneas à Pau) : les identifiants ark viennent de la recherche (images seulement, une requête par page de 50
 * résultats, réponse en HTML), puis une notice Dublin Core par ark (`/services/OAIRecord?ark=`), qui porte la licence propre à
 * chaque image (`dc:rights`), les sujets (lieux, « Gare (Foix) »), l'établissement et la cote (`dc:source`).
 * Les adresses d'image sont composées (service IIIF 1.1, `/iiif/ark:/12148/<ark>/f1/full/<largeur>,/0/native.jpg`),
 * jamais téléchargées.
 *
 * Le cache `<jeu>.records.jsonl` est écrit ligne à ligne : une lecture interrompue reprend où elle s'est arrêtée (les arks déjà
 * lus, y compris en erreur, ne sont pas redemandés ; pour repartir de zéro, supprimer le fichier ; `--retry-errors` redemande les erreurs).
 */
final class DublinCoreReader
{
    public function __construct(private readonly OpenDataFetcher $http, private readonly string $key) {}

    public static function recordCache(string $key): string
    {
        return OpenDataFetcher::cacheDir().'/'.$key.'.records.jsonl';
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    public function read(array $dataset, ?int $limit = null): array
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        $done = [];
        foreach (self::cachedRecords($this->key) as $line) {
            if (! isset($line['error']) || ! $this->http->retryErrors) {
                $done[$line['ark']] = true;
            }
        }
        $arks = (new IiifManifestReader($this->http, $this->key))->searchArks($dataset, []);
        $handle = fopen(self::recordCache($this->key), 'ab');
        $read = 0;
        foreach ($arks as $ark) {
            if (isset($done[$ark])) {
                continue;
            }
            if ($limit !== null && $read >= $limit) {
                break;
            }
            $read++;
            try {
                $xml = $this->http->getText(str_replace('{ark}', $ark, $dataset['record']), ['accept' => 'application/xml', 'timeout' => 30, 'retry' => false]);
                $line = ['ark' => $ark] + self::row($xml, $ark, $dataset);
            } catch (RuntimeException|ConnectionException $e) {
                $line = ['ark' => $ark, 'error' => mb_substr($e->getMessage(), 0, 200)];
            }
            fwrite($handle, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            fflush($handle);
        }
        fclose($handle);

        return self::rowsFromCache($this->key, $limit);
    }

    /**
     * Lignes plates des notices déjà lues (les arks en erreur sont ignorés, chaque ark une seule fois).
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsFromCache(string $key, ?int $limit = null): array
    {
        $rows = [];
        foreach (self::cachedRecords($key) as $line) {
            if (isset($line['error']) || isset($rows[$line['ark']])) {
                continue;
            }
            $rows[$line['ark']] = $line;
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return array_values($rows);
    }

    /** @return iterable<array<string, mixed>> */
    public static function cachedRecords(string $key): iterable
    {
        $path = self::recordCache($key);
        if (! is_file($path)) {
            return;
        }
        $handle = fopen($path, 'rb');
        try {
            while (($raw = fgets($handle)) !== false) {
                $line = json_decode($raw, true);
                if (is_array($line) && isset($line['ark'])) {
                    yield $line;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Ligne plate d'une réponse `OAIRecord` (Dublin Core).
     *
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public static function row(string $xml, string $ark, array $dataset): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = trim($xml) !== '' && $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException("Notice illisible : {$ark}");
        }
        $dc = static function (string $name) use ($doc): array {
            $out = [];
            foreach ($doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', $name) as $node) {
                // Chaque valeur existe en plusieurs langues (fre, eng) : la première de chaque langue ne se répète pas.
                $out[] = trim($node->textContent);
            }

            return array_values(array_unique(array_filter($out, fn (string $v): bool => $v !== '')));
        };
        $title = $dc('title')[0] ?? null;
        if ($title === null) {
            throw new RuntimeException("Notice sans titre : {$ark}");
        }
        $source = $dc('source')[0] ?? null;
        $site = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', (string) ($dataset['record'] ?? '')), '/');
        $sizes = $dataset['image_sizes'] ?? [1600, 400];
        $image = $site.'/iiif/ark:/12148/'.$ark.'/f1/full/';
        $identifier = null;
        foreach ($dc('identifier') as $id) {
            if (str_starts_with($id, 'http')) {
                $identifier = $id;
                break;
            }
        }
        $subjects = $dc('subject');

        return [
            'title' => $title,
            'author' => self::author($dc('creator')),
            'creators' => $dc('creator'),
            'date' => $dc('date')[0] ?? null,
            'subjects' => implode(' | ', $subjects),
            'commune' => self::commune($subjects),
            'source' => $source,
            'inventory' => self::shelfmark($source),
            'repository' => self::repository($source),
            'rights' => implode(' | ', $dc('rights')),
            'type' => implode(' | ', $dc('type')),
            'description' => implode(' | ', $dc('description')),
            'link' => $identifier,
            'image' => $image.$sizes[0].',/0/native.jpg',
            'thumb' => $image.$sizes[1].',/0/native.jpg',
        ];
    }

    /**
     * Le photographe ou l'auteur, au format « Prénom Nom » (« Trutat, Eugène (1840-1910). Photographe » donne « Eugène Trutat »).
     * Les éditeurs, imprimeurs et collectivités ne sont pas des auteurs.
     *
     * @param  list<string>  $creators
     */
    public static function author(array $creators): ?string
    {
        foreach ($creators as $c) {
            if (preg_match('/^([^,(]+),\s*([^(]+?)\s*\(([^)]*)\)\.?\s*(.*)$/u', $c, $m) !== 1) {
                continue;
            }
            $role = mb_strtolower(trim($m[4]));
            if (preg_match('/[ée]diteur|imprimeur|libraire|collectivit|organisme/iu', $role) === 1) {
                continue;
            }

            return trim($m[2]).' '.trim($m[1]);
        }

        return null;
    }

    /**
     * Commune des sujets : « Gare (Foix) » (sujet de gare, le plus sûr), sinon « Foix (Ariège, France) » ou
     * « Tramways, Pau (Pyrénées-Atlantiques), 20e siècle, Cartes postales » (rubriques séparées par un double tiret dans la notice).
     *
     * @param  list<string>  $subjects
     */
    public static function commune(array $subjects): ?string
    {
        foreach ($subjects as $s) {
            if (preg_match('/^Gare\s*\(([^()]+)\)$/iu', $s, $m) === 1) {
                return trim($m[1]);
            }
        }
        // « Foix (Ariège, France) » : commune, département, pays ; « Pyrénées (France) » (région) n'a pas de département.
        $departments = 'Pyrénées-Atlantiques|Hautes-Pyrénées|Basses-Pyrénées|Ariège|Haute-Garonne|Gers|Landes|Lot|Lot-et-Garonne|Tarn|Tarn-et-Garonne|Aude|Aveyron|Pyrénées-Orientales|Gironde';
        foreach ($subjects as $s) {
            foreach (preg_split('/\s--\s/u', $s) ?: [] as $part) {
                if ((preg_match('/^([^()]+?)\s*\([^()]+,\s*France\)$/u', $part, $m) === 1 || preg_match('/^([^()]+?)\s*\((?:'.$departments.')\)$/u', $part, $m) === 1)
                    && preg_match('/\b(?:vall[ée]e|massif|pays|r[ée]gion|canton|d[ée]partement|pic|lac|gave)\b/iu', $m[1]) !== 1) {
                    return trim($m[1]);
                }
            }
        }

        return null;
    }

    /** Cote : le dernier élément de `dc:source` s'il en a l'allure (« Bibliothèque patrimoniale de Pau, 8-060-3 »), sinon rien. */
    public static function shelfmark(?string $source): ?string
    {
        if ($source === null || ! str_contains($source, ', ')) {
            return null;
        }
        $last = trim(substr($source, (int) strrpos($source, ', ') + 2));

        return preg_match('/\d/', $last) === 1 && mb_strlen($last) <= 30 && preg_match('/^(?:Mus[ée]e|Archives|Biblioth)/iu', $last) !== 1 ? $last : null;
    }

    /** Établissement : `dc:source` sans la cote. */
    public static function repository(?string $source): ?string
    {
        $cote = self::shelfmark($source);

        return $source === null ? null : ($cote === null ? $source : trim(substr($source, 0, strlen($source) - strlen($cote) - 2)));
    }
}
