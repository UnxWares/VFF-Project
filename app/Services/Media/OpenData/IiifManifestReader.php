<?php

namespace App\Services\Media\OpenData;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Lit les images d'une bibliothèque numérique de marque blanche Gallica (École des ponts et chaussées, Numistral) par son
 * plan du site et ses manifestes IIIF, jamais par la recherche (interdite par le robots.txt de heritage.ecoledesponts.fr).
 *
 * - le plan du site (`sitemap.txt`) donne les identifiants ark ; on garde ceux dont l'identifiant commence par `ark_prefix`
 *   (btv = images, bpt = imprimés) ;
 * - un manifeste IIIF (Presentation 2) par ark, à 2 s d'écart : titre, date, créateurs, cote, dépôt, service d'images.
 *
 * Le cache `<jeu>.manifests.jsonl` est écrit ligne à ligne : une lecture interrompue reprend où elle s'est arrêtée (les
 * arks déjà lus, y compris ceux en erreur, ne sont pas redemandés ; `--refresh` repart de zéro). Aucune image n'est
 * téléchargée : les adresses IIIF de vignette et d'affichage sont seulement composées.
 */
final class IiifManifestReader
{
    public function __construct(private readonly OpenDataFetcher $http, private readonly string $key) {}

    public static function manifestCache(string $key): string
    {
        return OpenDataFetcher::cacheDir().'/'.$key.'.manifests.jsonl';
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    public function read(array $dataset, ?int $limit = null): array
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        $options = $this->options($dataset);
        $done = [];
        foreach (self::cachedManifests($this->key) as $line) {
            // Avec `--retry-errors`, un ark dont la lecture a échoué est redemandé (la ligne en erreur reste, la nouvelle la suit).
            if (! isset($line['error']) || ! $this->http->retryErrors) {
                $done[$line['ark']] = true;
            }
        }
        $arks = isset($dataset['searches'])
            ? $this->searchArks($dataset, $options)
            : self::arks($this->http->getText($dataset['sitemap'], ['accept' => 'text/plain'] + $options), $dataset['ark_prefix']);
        $handle = fopen(self::manifestCache($this->key), 'ab');
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
                // Les manifestes de gros documents (journaux de mission, livres) n'aboutissent parfois pas : 20 s, une seule tentative,
                // l'erreur est gardée et l'ark n'est pas redemandé.
                $manifest = $this->http->getJson(str_replace('{ark}', $ark, $dataset['manifest']), ['timeout' => 20, 'retry' => false] + $options);
                $line = ['ark' => $ark] + self::row($manifest, $ark, $dataset);
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
     * Identifiants ark des pages de résultats d'une recherche (jeux dont le robots.txt ne l'interdit pas : Numistral, 404 sur
     * numistral.bnf.fr), 50 résultats par page, jusqu'au nombre de résultats annoncé.
     *
     * @param  array<string, mixed>  $dataset
     * @param  array{verify?: string}  $options
     * @return list<string>
     */
    public function searchArks(array $dataset, array $options): array
    {
        $out = [];
        foreach ($dataset['searches'] as $query) {
            $total = null;
            for ($start = 0; $total === null || $start < $total; $start += 50) {
                $html = $this->http->getText($dataset['search_url'], ['accept' => '*/*', 'query' => [
                    'operation' => 'searchRetrieve', 'version' => '1.2', 'query' => $query,
                    'maximumRecords' => '50', 'startRecord' => (string) $start, 'page' => (string) ($start / 50 + 1),
                ]] + $options);
                $total ??= preg_match('/(\d[\d\s\x{202f}\x{a0}]*)\s+r[ée]sultats/u', $html, $m) === 1 ? (int) preg_replace('/\D/', '', $m[1]) : 0;
                foreach (self::arks(preg_replace('#(/ark:/12148/[a-z0-9]+)#i', "\n$1\n", $html) ?? '', $dataset['ark_prefix']) as $ark) {
                    $out[$ark] = true;
                }
            }
        }

        return array_keys($out);
    }

    /**
     * Lignes plates des manifestes déjà lus (les arks en erreur sont ignorés, chaque ark une seule fois).
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsFromCache(string $key, ?int $limit = null): array
    {
        // Un ark peut figurer deux fois (reprise après une erreur, lectures qui se sont croisées) : la première lecture réussie compte.
        $rows = [];
        foreach (self::cachedManifests($key) as $line) {
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
    public static function cachedManifests(string $key): iterable
    {
        $path = self::manifestCache($key);
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
     * Identifiants ark du plan du site, dans l'ordre du plan, sans doublon.
     *
     * @return list<string>
     */
    public static function arks(string $sitemap, string $prefix): array
    {
        $out = [];
        foreach (preg_split('/\R/', $sitemap) ?: [] as $line) {
            if (preg_match('#/ark:/12148/('.preg_quote($prefix, '#').'[a-z0-9]+)\s*$#i', trim($line), $m) === 1) {
                $out[$m[1]] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array{verify?: string}
     */
    private function options(array $dataset): array
    {
        if (empty($dataset['ca_chain'])) {
            return [];
        }
        // Le serveur de l'École n'envoie pas son certificat intermédiaire (HARICA GEANT TLS R1, publié par son émetteur) :
        // les autorités du système plus cet intermédiaire, réunis dans le cache.
        $bundle = OpenDataFetcher::cacheDir().'/ca-bundle-'.$this->key.'.pem';
        if (! is_file($bundle)) {
            $system = (string) (openssl_get_cert_locations()['default_cert_file'] ?? '');
            file_put_contents($bundle, (is_file($system) ? (string) file_get_contents($system)."\n" : '').(string) file_get_contents(base_path($dataset['ca_chain'])));
        }

        return ['verify' => $bundle];
    }

    /**
     * Ligne plate d'un manifeste Presentation 2.
     *
     * @param  array<string, mixed>  $m
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public static function row(array $m, string $ark, array $dataset): array
    {
        $meta = [];
        foreach ((array) ($m['metadata'] ?? []) as $item) {
            $value = $item['value'] ?? null;
            if (is_array($value)) {
                $value = array_map(fn ($v): string => is_array($v) ? (string) ($v['@value'] ?? '') : (string) $v, $value);
            }
            $meta[(string) ($item['label'] ?? '')][] = $value;
        }
        $first = static function (string $label) use ($meta): ?string {
            $v = $meta[$label][0] ?? null;
            $v = is_array($v) ? ($v[0] ?? null) : $v;

            return $v === null || trim((string) $v) === '' ? null : trim((string) $v);
        };
        $statement = $first('Title') ?? (string) ($m['description'] ?? '');
        $title = trim(explode(' / ', $statement)[0]);

        $creators = [];
        foreach ($meta['Creator'] ?? [] as $v) {
            foreach ((array) $v as $c) {
                $creators[] = (string) $c;
            }
        }

        $canvas = $m['sequences'][0]['canvases'][0] ?? [];
        $service = $canvas['images'][0]['resource']['service']['@id'] ?? null;
        $sizes = $dataset['image_sizes'] ?? [1600, 400];
        $quality = $dataset['iiif_quality'] ?? 'native.jpg';

        return [
            'title' => $title === '' ? null : $title,
            'statement' => $statement === '' ? null : $statement,
            'date' => $first('Date'),
            'author' => self::author($creators),
            'shelfmark' => $first('Shelfmark'),
            // Cote sans le nom du dépôt qui la précède (« Ecole nationale des ponts et chaussées, PH 125(10) A.8 »).
            'inventory' => ($shelf = $first('Shelfmark')) !== null && ($repo = $first('Repository')) !== null && str_starts_with($shelf, $repo.', ') ? substr($shelf, strlen($repo) + 2) : $first('Shelfmark'),
            'repository' => $first('Repository'),
            'digitised_by' => $first('Digitised by'),
            'type' => $first('Type'),
            'width' => $canvas['width'] ?? null,
            'height' => $canvas['height'] ?? null,
            'image' => $service === null ? null : $service.'/full/'.$sizes[0].',/0/'.$quality,
            'thumb' => $service === null ? null : $service.'/full/'.$sizes[1].',/0/'.$quality,
            'source' => $m['related'] ?? null,
        ];
    }

    /**
     * Le photographe, au format « Prénom Nom » (« Berthaud, Michel (1845-1912 ; photographe). Auteur du texte » donne
     * « Michel Berthaud »), à défaut aucun auteur : les collectivités (« France. Ministère des travaux publics ») ne sont pas des auteurs.
     *
     * @param  list<string>  $creators
     */
    public static function author(array $creators): ?string
    {
        foreach ($creators as $c) {
            if (preg_match('/^([^,(]+),\s*([^(]+?)\s*\(([^)]*)\)/u', $c, $m) === 1 && preg_match('/photographe|photographer/iu', $m[3].' '.$c) === 1) {
                return trim($m[2]).' '.trim($m[1]);
            }
        }

        return null;
    }
}
