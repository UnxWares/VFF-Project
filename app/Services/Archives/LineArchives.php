<?php

namespace App\Services\Archives;

use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Services\Api\Provenance;
use Illuminate\Support\Collection;

/**
 * Documents d'archives d'une ligne, tels que la fiche (section « Dans les archives »), l'API REST
 * (GET /api/v1/lines/{slug}/archives), GraphQL (`Line.archives`) et le serveur MCP (`line_archives`) les
 * servent : du plus ancien au plus récent, sans date en dernier. Lecture seule.
 *
 * Seules les métadonnées des notices de la BnF sont servies, avec le lien vers Gallica ; jamais d'image
 * ni de vignette (conditions de réutilisation : database/data/source_rights.php, fournisseur `gallica`).
 */
final class LineArchives
{
    /** Mention de source demandée par la BnF, à reproduire avec la date de récupération. */
    public const ATTRIBUTION = 'Source : gallica.bnf.fr / BnF. Métadonnées des notices du catalogue général de la Bibliothèque nationale de France, Licence Ouverte (Etalab).';

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * @param  ?string  $type  monograph, map, image ou manuscript ; null pour tous
     * @return Collection<int, LineArchiveDocument>
     */
    public function documents(Line $line, ?string $type = null): Collection
    {
        return $line->archiveDocuments()
            ->when($type !== null, fn ($q) => $q->where('doc_type', $type))
            ->get();
    }

    /**
     * Section de la fiche : documents regroupés quand plusieurs notices portent le même titre, la même
     * date et le même type (cinq photographies d'un même déraillement), avec toutes leurs cotes (deux
     * exemplaires d'une même ode, YE-54393 et YE-54394) ; date de récupération la plus ancienne.
     *
     * @return array{total: int, fetched_on: ?string, items: list<array{title: string, type: string, date: ?string,
     *     year: ?int, year_to: ?int, exact: ?string, date_precision: string, authors: list<string>,
     *     shelfmarks: list<string>, url: string, more_urls: list<string>}>}
     */
    public function forSheet(Line $line): array
    {
        $documents = $this->documents($line);
        $items = [];
        foreach ($documents as $d) {
            $key = mb_strtolower($d->title).'|'.$d->date_label.'|'.$d->doc_type;
            if (isset($items[$key])) {
                $items[$key]['more_urls'][] = $d->gallica_url;
                if ($d->shelfmark !== null && ! in_array($d->shelfmark, $items[$key]['shelfmarks'], true)) {
                    $items[$key]['shelfmarks'][] = $d->shelfmark;
                }

                continue;
            }
            $items[$key] = [
                'title' => $d->title,
                'type' => $d->doc_type,
                'date' => $d->date_label ?? ($d->year === null ? null : (string) $d->year),
                'year' => $d->year,
                'year_to' => $d->year_to,
                'exact' => $d->exact_date?->toDateString(),
                'date_precision' => $d->date_precision,
                'authors' => $d->authors,
                'shelfmarks' => $d->shelfmark === null ? [] : [$d->shelfmark],
                'url' => $d->gallica_url,
                'more_urls' => [],
            ];
        }

        return [
            'total' => $documents->count(),
            'fetched_on' => $documents->sortBy('fetched_at')->first()?->fetched_at->toDateString(),
            'items' => array_values($items),
        ];
    }

    /**
     * Un document dans l'API REST (et l'outil MCP) : métadonnées, liens, date et précision, raisons du
     * rattachement.
     *
     * @return array<string, mixed>
     */
    public function toApi(LineArchiveDocument $d): array
    {
        return [
            'gallica_ark' => $d->gallica_ark,
            'gallica_url' => $d->gallica_url,
            'catalogue_ark' => $d->catalogue_ark,
            'catalogue_url' => $d->catalogue_url,
            'title' => $d->title,
            'type' => $d->doc_type,
            'authors' => $d->authors,
            'date' => [
                'label' => $d->date_label,
                'year' => $d->year,
                'year_to' => $d->year_to,
                'exact' => $d->exact_date?->toDateString(),
                'precision' => $d->date_precision,
            ],
            'shelfmark' => $d->shelfmark,
            'match' => [
                'score' => $d->score,
                'threshold' => ArchiveMatcher::THRESHOLD,
                'max_score' => ArchiveMatcher::MAX_SCORE,
                'reasons' => $d->score_reasons,
                'places' => $d->matched_places,
            ],
            'record_updated_on' => $d->record_updated_on?->toDateString(),
            'fetched_at' => $d->fetched_at->toIso8601String(),
        ];
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('line_archive_documents', GallicaImporter::SOURCE);
    }
}
