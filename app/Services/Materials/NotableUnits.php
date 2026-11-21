<?php

namespace App\Services\Materials;

use App\Support\SourceRights;

/**
 * Rames et engins remarquables d'une série (database/data/material_notable_units.php) : numéro, surnom et faits
 * datés, chacun avec sa source, sa citation courte et la licence de la source. Partagé par la fiche, l'API REST,
 * GraphQL, l'outil MCP get_material et la recherche (surnoms).
 */
final class NotableUnits
{
    /** Langues des textes ; le français sert de repli. */
    public const LANGS = ['fr', 'en', 'de'];

    /** @var array{sources: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>} */
    private readonly array $data;

    /** @param array{sources: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>}|null $data */
    public function __construct(private readonly SourceRights $rights, ?array $data = null)
    {
        /** @var array{sources: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>} $loaded */
        $loaded = $data ?? require database_path('data/material_notable_units.php');
        $this->data = $loaded;
    }

    /**
     * Rames remarquables d'une série, textes dans la langue demandée (français à défaut).
     *
     * @return list<array{number: string, nickname: string|null, facts: list<array<string, mixed>>, unknown: string|null}>
     */
    public function forMaterial(string $slug, string $lang = 'fr'): array
    {
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'fr';

        return array_map(fn (array $unit): array => [
            'number' => (string) $unit['number'],
            'nickname' => $unit['nickname'] ?? null,
            'facts' => array_map(fn (array $fact): array => $this->fact($fact, $lang), $unit['facts']),
            'unknown' => isset($unit['unknown']) ? ($unit['unknown'][$lang] ?? $unit['unknown']['fr']) : null,
        ], $this->data['units'][$slug] ?? []);
    }

    /**
     * Séries dont une rame remarquable répond à la recherche (surnom, « TGV <surnom> », « rame <numéro> <surnom> ») :
     * slug de la série → numéro et surnom de la rame. Accents et casse ignorés ; trois caractères au moins pour
     * un début de surnom.
     *
     * @return array<string, array{number: string, nickname: string|null}>
     */
    public function matching(string $query): array
    {
        $q = self::fold($query);
        if ($q === '') {
            return [];
        }
        $out = [];
        foreach ($this->data['units'] as $slug => $units) {
            foreach ($units as $unit) {
                $nickname = $unit['nickname'] ?? null;
                if ($nickname === null || isset($out[$slug])) {
                    continue;
                }
                $nick = self::fold($nickname);
                $hit = $q === $nick
                    || (mb_strlen($q) >= 3 && str_starts_with($nick, $q))
                    || preg_match('/(^|\s)'.preg_quote($nick, '/').'($|\s)/u', $q) === 1;
                if ($hit) {
                    $out[$slug] = ['number' => (string) $unit['number'], 'nickname' => $nickname];
                }
            }
        }

        return $out;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function all(): array
    {
        return $this->data['units'];
    }

    /** @return array<string, array<string, mixed>> */
    public function sources(): array
    {
        return $this->data['sources'];
    }

    /**
     * @param  array<string, mixed>  $fact
     * @return array<string, mixed>
     */
    private function fact(array $fact, string $lang): array
    {
        $source = $this->data['sources'][$fact['source']];
        $provider = $this->rights->provider($source['provider']);

        return [
            'kind' => $fact['kind'],
            'date' => $fact['date'],
            'date_to' => $fact['date_to'] ?? null,
            'precision' => $fact['precision'],
            'text' => $fact['text'][$lang] ?? $fact['text']['fr'],
            'quote' => $fact['quote'] ?? null,
            'source' => [
                'id' => $fact['source'],
                'publisher' => $source['publisher'],
                'reported_by' => $source['reported_by'] ?? null,
                'date' => $source['date'] ?? null,
                'url' => $source['url'],
                'provider' => $source['provider'],
                'license' => $provider['licence'] ?? null,
                'license_status' => match ($provider['statut'] ?? null) {
                    SourceRights::SAFE => 'verified',
                    SourceRights::UNCERTAIN => 'uncertain',
                    SourceRights::FORBIDDEN => 'forbidden',
                    default => null,
                },
            ],
        ];
    }

    /** Minuscules sans accents, espaces réduits. */
    private static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $ascii = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $text);

        return (string) preg_replace('/\s+/u', ' ', is_string($ascii) ? $ascii : $text);
    }
}
