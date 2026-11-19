<?php

namespace App\Mcp\Support;

/**
 * Décision Q22 : aucun extrait de Wikipédia (CC BY-SA 4.0) dans les réponses du serveur MCP ; le permalien
 * de la révision lue suffit pour citer la source.
 *
 * Les ressources partagées avec l'API REST gardent leurs phrases citées : `quote` des preuves d'un lien
 * entre une ligne et une série de matériel, `excerpt` d'un fait de signalisation, `source_quote` et
 * `source_excerpt` des faits datés. VffTool retire ces clés de chaque réponse, à toute profondeur, avant
 * de l'envoyer : un nouvel outil qui réutilise une ressource REST ne peut pas les laisser passer.
 */
final class WikipediaExcerpts
{
    /** Clés qui portent une phrase recopiée d'une source. */
    public const KEYS = ['quote', 'excerpt', 'source_quote', 'source_excerpt'];

    /**
     * Données sans les clés KEYS, à toute profondeur. Les objets sérialisables (ressources d'API
     * imbriquées, collections) sont d'abord mis sous la forme que json_encode leur donnerait ; les listes
     * restent des listes et les objets simples des objets.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function strip(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (! self::isExcerpt($key)) {
                $out[$key] = self::clean($value);
            }
        }

        return $out;
    }

    private static function clean(mixed $value): mixed
    {
        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if (is_array($value)) {
            return self::strip($value);
        }
        if ($value instanceof \stdClass) {
            $out = new \stdClass;
            foreach (get_object_vars($value) as $key => $item) {
                if (! self::isExcerpt($key)) {
                    $out->{$key} = self::clean($item);
                }
            }

            return $out;
        }

        return $value;
    }

    private static function isExcerpt(int|string $key): bool
    {
        return is_string($key) && in_array($key, self::KEYS, true);
    }
}
