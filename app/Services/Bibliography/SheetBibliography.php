<?php

namespace App\Services\Bibliography;

use App\Models\Line;
use App\Models\Station;
use App\Services\Api\Provenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliographie d'une ligne ou d'une gare, telle que la section « Pour aller plus loin » des fiches et l'API REST
 * (`/api/v1/lines/{slug}/bibliography`, `/api/v1/stations/{slug}/bibliography`) la servent : les références les plus
 * récentes d'abord, sans date en dernier. Lecture seule ; avant l'application de la migration (ou sans import), la
 * liste est vide : les fiches n'échouent jamais faute de table.
 *
 * Seules les données de la notice sont servies (auteurs, titre, éditeur, année, ISBN) avec le lien vers la notice
 * de la BnF ou vers la référence d'origine ; jamais le contenu de l'ouvrage.
 */
final class SheetBibliography
{
    /** Mention de source des notices de la BnF (Licence Ouverte : source et date de récupération). */
    public const ATTRIBUTION = 'Notices : catalogue général de la Bibliothèque nationale de France (catalogue.bnf.fr), Licence Ouverte (Etalab), avec la date de récupération ; références d\'articles de Wikipédia (CC BY-SA 4.0) et éléments de Wikidata (CC0).';

    /** Références servies au plus par fiche. */
    public const MAX_ITEMS = 60;

    public function __construct(private readonly Provenance $provenance) {}

    /** La table existe-t-elle (constat gardé cinq minutes : une lecture de moins par fiche) ? */
    public function available(): bool
    {
        return Cache::remember('vff:bibliography:table', 300, fn (): bool => Schema::hasTable('bibliography_links'));
    }

    /**
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    public function forLine(Line $line): array
    {
        return $this->read('line_id', $line->id);
    }

    /**
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    public function forStation(Station $station): array
    {
        return $this->read('station_id', $station->id);
    }

    /** @return array{total: int, items: list<array<string, mixed>>} */
    private function read(string $column, int $id): array
    {
        if (! $this->available()) {
            return ['total' => 0, 'items' => []];
        }
        // Une seule lecture : références les plus récentes d'abord, sans date en dernier, MAX_ITEMS au plus, avec le total.
        $rows = DB::table('bibliography_links as l')
            ->join('bibliography_entries as e', 'e.id', '=', 'l.entry_id')
            ->where("l.{$column}", $id)
            ->orderByRaw('e.year DESC NULLS LAST, e.title, l.id')
            ->limit(self::MAX_ITEMS)
            ->get(['e.kind', 'e.title', 'e.authors', 'e.publisher', 'e.place', 'e.year', 'e.isbn', 'e.journal', 'e.url', 'e.provider as source',
                'e.fetched_at', 'l.basis', 'l.provider as match_provider', 'l.score', 'l.reasons', 'l.origin_url',
                DB::raw('count(*) OVER () AS total')]);

        return ['total' => (int) ($rows->first()->total ?? 0), 'items' => $rows->map(fn (object $r): array => $this->item($r))->all()];
    }

    /** @return array<string, mixed> */
    private function item(object $r): array
    {
        return [
            'kind' => $r->kind,
            'title' => $r->title,
            'authors' => $this->decode($r->authors),
            'publisher' => $r->publisher,
            'place' => $r->place,
            'year' => $r->year === null ? null : (int) $r->year,
            'isbn' => $r->isbn,
            'journal' => $r->journal,
            'url' => $r->url,
            'source' => $r->source,
            'match' => [
                'basis' => $r->basis,
                'provider' => $r->match_provider,
                'score' => $r->score === null ? null : (int) $r->score,
                'reasons' => $this->decode($r->reasons),
                'origin_url' => $r->origin_url,
            ],
            'fetched_on' => $r->fetched_at === null ? null : substr((string) $r->fetched_at, 0, 10),
            'provenance' => $this->provenance((string) $r->source),
        ];
    }

    /** @return array<mixed> */
    private function decode(mixed $json): array
    {
        $value = is_string($json) ? json_decode($json, true) : $json;

        return is_array($value) ? $value : [];
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(string $source): array
    {
        return $this->provenance->for('bibliography_entries', $source);
    }
}
