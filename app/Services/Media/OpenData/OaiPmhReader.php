<?php

namespace App\Services\Media\OpenData;

use DOMDocument;
use RuntimeException;

/**
 * Lit un entrepôt OAI-PMH en Dublin Core (`verb=ListRecords&metadataPrefix=oai_dc&set=<ensemble>`, jeton de reprise) : L'Empreinte,
 * bibliothèque numérique des médiathèques de Valence Romans Agglo (ensemble `theme:gares-et-chemins-de-fer`, 68 notices, quatre
 * requêtes). Aucune clé, pas de robots.txt.
 *
 * L'entrepôt ne donne pas l'adresse de l'image : elle se compose avec l'identifiant de la notice (`image_template` et
 * `thumb_template`, `{id}` remplacé), schéma lu sur la page de la notice. Les textes de L'Empreinte sont de l'UTF-8 encodé deux fois
 * (« MÃ©diathÃ¨que ») : ils sont réparés ici. Les images ne sont jamais téléchargées.
 */
final class OaiPmhReader
{
    private const DC = 'http://purl.org/dc/elements/1.1/';

    private const OAI = 'http://www.openarchives.org/OAI/2.0/';

    public function __construct(private readonly OpenDataFetcher $http) {}

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    public function read(array $dataset, ?int $limit = null): array
    {
        $rows = [];
        $url = $dataset['resource'];
        $query = ['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc', 'set' => $dataset['set']];
        for ($page = 0; $page < 100; $page++) {
            $xml = $this->http->getText($url, ['accept' => 'application/xml', 'query' => $query]);
            [$pageRows, $token] = self::parse($xml, $dataset);
            foreach ($pageRows as $row) {
                $rows[$row['id']] ??= $row;
            }
            if ($token === null || ($limit !== null && count($rows) >= $limit)) {
                break;
            }
            $query = ['verb' => 'ListRecords', 'resumptionToken' => $token];
        }

        return array_values($rows);
    }

    /**
     * Lignes plates d'une page `ListRecords` et jeton de reprise (null à la dernière page).
     *
     * @param  array<string, mixed>  $dataset
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    public static function parse(string $xml, array $dataset): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = trim($xml) !== '' && $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('Réponse OAI-PMH illisible.');
        }
        if ($doc->getElementsByTagNameNS(self::OAI, 'error')->length > 0) {
            // « noRecordsMatch » : un ensemble vide n'est pas une erreur.
            $code = $doc->getElementsByTagNameNS(self::OAI, 'error')->item(0)?->getAttribute('code');
            if ($code === 'noRecordsMatch') {
                return [[], null];
            }
            throw new RuntimeException("OAI-PMH : {$code}");
        }
        $rows = [];
        foreach ($doc->getElementsByTagNameNS(self::OAI, 'record') as $record) {
            $id = trim((string) $record->getElementsByTagNameNS(self::OAI, 'identifier')->item(0)?->textContent);
            if ($id === '' || $record->getElementsByTagNameNS(self::DC, 'title')->length === 0) {
                continue;
            }
            $dc = static function (string $name) use ($record): array {
                $out = [];
                foreach ($record->getElementsByTagNameNS(self::DC, $name) as $node) {
                    $out[] = self::repair(trim($node->textContent));
                }

                return array_values(array_unique(array_filter($out, fn (string $v): bool => $v !== '')));
            };
            $page = '';
            foreach ($dc('identifier') as $candidate) {
                if (str_starts_with($candidate, 'http')) {
                    $page = (string) preg_replace('#^http://#', 'https://', $candidate);
                    break;
                }
            }
            $subjects = $dc('subject');
            $rows[] = [
                'id' => $id,
                'title' => $dc('title')[0],
                'date' => $dc('date')[0] ?? null,
                'author' => null,
                'subjects' => implode(' | ', $subjects),
                'commune' => $dc('coverage')[0] ?? null,
                'repository' => $dc('source')[0] ?? null,
                'inventory' => $id,
                'rights' => implode(' | ', $dc('rights')),
                'type' => implode(' | ', $dc('type')),
                'description' => implode(' | ', $dc('description')),
                'link' => $page !== '' ? $page : null,
                'image' => str_replace('{id}', $id, (string) ($dataset['image_template'] ?? '')),
                'thumb' => str_replace('{id}', $id, (string) ($dataset['thumb_template'] ?? '')),
            ];
        }
        $token = trim((string) $doc->getElementsByTagNameNS(self::OAI, 'resumptionToken')->item(0)?->textContent);

        return [$rows, $token === '' ? null : $token];
    }

    /** UTF-8 encodé deux fois (« MÃ©diathÃ¨que ») : relu en ISO-8859-1 puis en UTF-8, seulement si le résultat est de l'UTF-8 valide. */
    public static function repair(string $text): string
    {
        if (preg_match('/Ã[\x{0080}-\x{00BF}]/u', $text) !== 1) {
            return $text;
        }
        $bytes = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        return mb_check_encoding($bytes, 'UTF-8') ? $bytes : $text;
    }
}
