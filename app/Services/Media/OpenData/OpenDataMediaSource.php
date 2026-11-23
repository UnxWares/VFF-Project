<?php

namespace App\Services\Media\OpenData;

use App\Enums\MediaType;
use App\Models\Line;
use App\Models\Station;
use App\Services\Media\Sources\MediaRecord;
use App\Services\Media\Sources\MediaSource;

/**
 * Source de médias pour un jeu ouvert d'images (database/data/open_data_media.php) : lit le cache local
 * (OpenDataFetcher), ne garde que les vues ferroviaires dont la licence est lue, les rattache à une gare ou une
 * ligne (OpenDataMatcher) et rend des MediaRecord pour MediaWriter. Ne fait aucune requête réseau ni écriture.
 *
 * Compteurs publics, lus par la commande après le passage : lignes lues, refus par motif, retenues, rattachées par
 * méthode, non rattachées par motif, et exemples rattachés pour la relecture à la main.
 */
final class OpenDataMediaSource implements MediaSource
{
    public int $read = 0;

    public int $retained = 0;

    /** @var array<string, int> */
    public array $rejected = [];

    /** @var array<string, int> */
    public array $attached = [];

    /** @var array<string, int> */
    public array $unattached = [];

    /** @var list<array<string, mixed>> vues ferroviaires sans rattachement, pour le fichier de relecture */
    public array $review = [];

    /** @var list<array{candidate: array<string, mixed>, match: array<string, mixed>}> */
    public array $examples = [];

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<int, true> */
    public array $stations = [];

    /** @var array<int, true> */
    public array $lines = [];

    /** @param  array<string, mixed>  $dataset */
    public function __construct(
        private readonly string $key,
        private readonly array $dataset,
        private readonly OpenDataMatcher $matcher,
    ) {}

    public function provider(): string
    {
        return $this->dataset['provider'];
    }

    public function records(?int $limit = null): iterable
    {
        foreach (OpenDataFetcher::cached($this->key) as $row) {
            if ($limit !== null && $this->read >= $limit) {
                return;
            }
            $this->read++;
            $n = OpenDataNormalizer::normalize($this->dataset, $row);
            if (! $n['ok']) {
                $this->rejected[$n['reason']] = ($this->rejected[$n['reason']] ?? 0) + 1;

                continue;
            }
            $c = $n['candidate'];
            // Mêmes planches reliées dans deux albums (Getty) : une seule image par titre, auteur et date.
            if (! empty($this->dataset['dedupe'])) {
                $signature = OpenDataText::fold($c['title'].' '.$c['author'].' '.$c['date_raw']);
                if (isset($this->seen[$signature])) {
                    $this->rejected['doublon'] = ($this->rejected['doublon'] ?? 0) + 1;

                    continue;
                }
                $this->seen[$signature] = true;
            }
            $this->retained++;
            $match = $this->matcher->match($c, $this->dataset);
            if ($match['kind'] === null) {
                $this->unattached[$match['reason']] = ($this->unattached[$match['reason']] ?? 0) + 1;
                $this->review[] = ['id' => $c['id'], 'title' => $c['title'], 'commune' => $c['commune'], 'reason' => $match['reason'], 'image' => $c['image']];

                continue;
            }
            $this->attached[$match['method']] = ($this->attached[$match['method']] ?? 0) + 1;
            $this->examples[] = ['candidate' => $c, 'match' => $match];

            $targets = [[$match['kind'], $match['id']]];
            foreach ($match['also'] ?? [] as $lineId) {
                $targets[] = ['line', (int) $lineId];
            }
            foreach ($targets as [$kind, $id]) {
                $kind === 'station' ? $this->stations[$id] = true : $this->lines[$id] = true;
                yield $this->record($c, $match, $kind === 'station' ? Station::class : Line::class, (int) $id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $match
     * @param  class-string  $type
     */
    private function record(array $c, array $match, string $type, int $id): MediaRecord
    {
        $r = $c['rights'];
        $title = $c['title'] ?? $c['title_alt'];
        $caption = $c['title_alt'] !== null && $c['title_alt'] !== $title ? $c['title_alt'] : ($c['caption'] ?? null);

        return new MediaRecord(
            provider: $this->dataset['provider'],
            externalId: (string) $c['id'],
            mediableType: $type,
            mediableId: $id,
            rightsStatus: $r['rights_status'],
            license: $r['license'],
            licenseUrl: $r['license_url'],
            commercialOk: $r['commercial_ok'],
            derivativesOk: $r['derivatives_ok'],
            shareAlike: $r['share_alike'],
            attribution: $this->attribution($c, $r),
            author: $c['author'],
            sourceUrl: $c['source'] ?? str_replace('{id}', rawurlencode((string) $c['id']), $this->dataset['source_url']),
            thumbUrl: $c['thumb'],
            displayUrl: $c['image'],
            type: MediaType::from($this->dataset['type']),
            title: $title,
            caption: $caption,
            capturedAt: $c['captured_at'],
            datePrecision: $c['date_precision'],
            depictedYear: $c['year'],
            lat: $c['lat'],
            lng: $c['lng'],
            meta: [
                'source' => $this->key,
                'rule' => 'open-data-v1',
                'method' => $match['method'],
                'confidence' => $match['confidence'],
                'distance_m' => $match['distance_m'] ?? null,
                'commune' => $c['commune'],
                'place' => $c['place'],
                'inventory' => $c['inventory'],
                'date_raw' => $c['date_raw'],
                'year_to' => $c['year_to'],
                'process' => $this->dataset['process'] ?? null,
                'declared_license' => $r['declared'] ?? null,
            ],
        );
    }

    /**
     * Crédit prêt à afficher : auteur (opérateur ou producteur), date, producteur du fonds, numéro d'inventaire ou
     * cote, licence. Ex. « Auguste Léon, 5 décembre 1918, musée départemental Albert-Kahn, Archives de la Planète
     * (Département des Hauts-de-Seine), A 15 049, CC0 1.0 ».
     *
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $rights
     */
    private function attribution(array $c, array $rights): string
    {
        $date = $this->dateLabel($c);
        $author = $c['author'];
        $cote = $c['inventory'] ?? $c['id'];
        $credit = $c['credit'] ?? $this->dataset['credit'];

        // Avec un auteur : auteur, date, producteur, cote. Sans auteur (cartes postales) : producteur, cote, date.
        $parts = $author !== null && $author !== ''
            ? [$author, $date, $credit, $cote, $rights['license']]
            : [$credit, $cote, $date, $rights['license']];

        return implode(', ', array_filter($parts, fn ($v) => $v !== null && $v !== ''));
    }

    /** @param  array<string, mixed>  $c */
    private function dateLabel(array $c): ?string
    {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $year = $c['year'];
        if ($year === null) {
            return null;
        }
        if ($c['captured_at'] !== null) {
            [$y, $m, $d] = array_map('intval', explode('-', (string) $c['captured_at']));
            $label = match ($c['date_precision']) {
                'day' => ($d === 1 ? '1er' : (string) $d).' '.$months[$m].' '.$y,
                'circa' => ($d === 1 ? '' : $d.' ').$months[$m].' '.$y,
                default => $months[$m].' '.$y,
            };

            return $c['date_precision'] === 'circa' ? 'vers '.$label : $label;
        }

        return match ($c['date_precision']) {
            'circa' => 'vers '.$year,
            'range' => $year.'-'.$c['year_to'],
            'before' => 'avant '.$year,
            'after' => 'après '.$year,
            default => (string) $year,
        };
    }
}
