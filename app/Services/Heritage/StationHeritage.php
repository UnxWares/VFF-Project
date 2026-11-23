<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use App\Services\Api\Provenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Patrimoine d'une gare (bâtiment voyageurs et ouvrages de gare protégés, labellisés ou recensés), table
 * `station_heritage` (vff:import-station-heritage). Même contenu pour la fiche de la gare, l'API REST
 * (`GET /api/v1/stations/{station}/heritage`), GraphQL (`Station.heritage`) et le serveur MCP (`station_heritage`).
 *
 * Tant que la migration n'est pas appliquée, tout est vide (aucune erreur).
 */
final class StationHeritage
{
    public const ATTRIBUTION = 'Base Mérimée, Plateforme ouverte du patrimoine (ministère de la Culture), sous Licence Ouverte 2.0 : '
        .'mention « Ministère de la Culture, base Mérimée (POP) » ; Wikidata (CC0) ; images de Wikimedia Commons sous la licence de chaque fichier.';

    /** Sources citées par la colonne `source`. */
    public const SOURCES = ['merimee', 'wikidata'];

    private const READY_CACHE = 'station-heritage:table-ready';

    private const ELEMENT_ORDER = ['building' => 0, 'works' => 1];

    private const STRENGTH = ['classified' => 0, 'registered' => 1, 'acr' => 2, 'inventory' => 3];

    private static ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /** La table existe (migration appliquée). Un constat positif est gardé en cache, un négatif est refait. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            if (Cache::get(self::READY_CACHE) === true) {
                return self::$ready = true;
            }
            $ready = (bool) DB::scalar("SELECT to_regclass('station_heritage') IS NOT NULL");
            if ($ready) {
                Cache::put(self::READY_CACHE, true, now()->addDay());
                self::$ready = true;
            }

            return $ready;
        } catch (\Throwable) {
            // Lecture protégée : un cache ou une base qui répondent mal ne doivent jamais casser une fiche.
            return false;
        }
    }

    /**
     * Patrimoine d'une gare, ou null quand rien n'est connu.
     *
     * @return array<string, mixed>|null
     */
    public function forStation(int $stationId): ?array
    {
        return $this->forStations([$stationId])[$stationId] ?? null;
    }

    /**
     * Blocs de plusieurs gares en une requête (GraphQL). Un bloc :
     *
     * - `items[]` : bâtiment voyageurs d'abord, puis ouvrages, du plus protégé au moins protégé. Un objet : `element`
     *   (`building`, `works`), `protection` (`classified`, `registered`, `acr`, `inventory`), `protection_date`, `scope`
     *   (`partial` quand seules des parties sont protégées), `designations[]` (`kind`, `date`), `title`, `denomination`,
     *   `architects[]` (`name`, `role`), `built` (`from`, `to`, `period`), `style`, `reference` (`id`, `kind`, `url`),
     *   `wikidata_id`, `image` (nul sans image libre), `source`, `source_updated`, `match_method`, `provenance` ;
     * - `protected` : vrai quand un bâtiment ou un ouvrage est classé ou inscrit aux monuments historiques ;
     * - `attribution`.
     *
     * @param  list<int>  $stationIds
     * @return array<int, array<string, mixed>> seules les gares qui ont du patrimoine
     */
    public function forStations(array $stationIds): array
    {
        if ($stationIds === [] || ! self::ready()) {
            return [];
        }
        $byStation = [];
        try {
            $rows = DB::table('station_heritage')->whereIn('station_id', $stationIds)->orderBy('station_id')->orderBy('id')->get();
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $r) {
            $byStation[(int) $r->station_id][] = $this->item($r);
        }

        $out = [];
        foreach ($byStation as $id => $items) {
            usort($items, fn (array $a, array $b): int => [self::ELEMENT_ORDER[$a['element']] ?? 9, self::STRENGTH[$a['protection']] ?? 9]
                <=> [self::ELEMENT_ORDER[$b['element']] ?? 9, self::STRENGTH[$b['protection']] ?? 9]);
            $out[$id] = [
                'items' => $items,
                'protected' => (bool) array_filter($items, fn (array $i): bool => in_array($i['protection'], ['classified', 'registered'], true)),
                'attribution' => self::ATTRIBUTION,
            ];
        }

        return $out;
    }

    /**
     * Clés des fournisseurs du registre des droits dont un bloc reprend des données : Mérimée (`pop`), Wikidata, et
     * Commons quand une image est donnée.
     *
     * @param  array<string, mixed>  $block
     * @return list<string>
     */
    public static function providers(array $block): array
    {
        $out = ['wikidata'];
        foreach ($block['items'] ?? [] as $item) {
            if ($item['source'] === 'merimee') {
                $out[] = 'pop';
            }
            if ($item['image'] !== null) {
                $out[] = 'commons';
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array<string, mixed> */
    private function item(object $r): array
    {
        $designations = json_decode((string) $r->designations, true);
        $architects = json_decode((string) $r->architects, true);

        return [
            'element' => (string) $r->element,
            'protection' => (string) $r->protection,
            'protection_date' => $r->protection_date !== null ? substr((string) $r->protection_date, 0, 10) : null,
            'scope' => $r->scope,
            'designations' => is_array($designations) ? $designations : [],
            'title' => $r->title,
            'denomination' => $r->denomination,
            'architects' => is_array($architects) ? $architects : [],
            'built' => ['from' => $r->built_from !== null ? (int) $r->built_from : null, 'to' => $r->built_to !== null ? (int) $r->built_to : null, 'period' => $r->built_period],
            'style' => $r->style,
            'reference' => ['id' => (string) $r->ref, 'kind' => (string) $r->ref_kind, 'url' => $r->ref_url],
            'wikidata_id' => $r->wikidata_id,
            'image' => $r->image_url !== null ? [
                'url' => $r->image_url, 'source_url' => $r->image_source_url, 'file' => $r->image_file, 'license' => $r->image_license, 'author' => $r->image_author,
            ] : null,
            'source' => (string) $r->source,
            'source_updated' => $r->source_updated !== null ? substr((string) $r->source_updated, 0, 10) : null,
            'match_method' => (string) $r->match_method,
            'provenance' => $this->provenance->for('station_heritage', (string) $r->source),
        ];
    }
}
