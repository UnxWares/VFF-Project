<?php

namespace App\Services\Quiz;

use App\Enums\QuizLevel;
use App\Models\Material;
use App\Support\WebUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Jeu « Reconnaître le matériel » : la photo Commons d'une série, quatre séries du même type proposées.
 *
 * Vivier : séries dont l'image vient de Wikimedia Commons sous licence ouverte ou dans le domaine
 * public, avec son auteur ; reliées à au moins une ligne par un lien de fiabilité haute ; dont la
 * description courte vient de Wikidata et dit quelque chose (phrase montrée après la réponse).
 * Un type qui compte moins de quatre séries dans le vivier n'est pas proposé (quatre propositions
 * du même type).
 */
final class MaterialQuiz implements QuizSource
{
    /**
     * Longueur minimale de la description Wikidata montrée après la réponse : « locomotive » ou
     * « locomotive française » n'apprennent rien au joueur.
     */
    public const MIN_DESCRIPTION = 25;

    /** Constructeurs cités en indice, au plus. */
    private const MAX_BUILDERS = 3;

    private const WIKIDATA_LICENSE = 'CC0';

    private const WIKIDATA_LICENSE_URL = 'https://creativecommons.org/publicdomain/zero/1.0/';

    public function pool(): array
    {
        return Cache::remember('quiz:materials-pool:v2', now()->addHour(), function (): array {
            $rows = DB::select(<<<'SQL'
                SELECT m.id, m.slug, m.name, m.type, m.in_service_from, m.image
                FROM materials m
                WHERE m.image IS NOT NULL
                  AND m.image->>'provider' = 'commons'
                  AND m.image->>'rights_status' IN ('open_license', 'public_domain')
                  AND COALESCE(btrim(m.image->>'author'), '') <> ''
                  AND m.wikidata_id IS NOT NULL
                  AND char_length(btrim(m.description)) >= ?
                  AND m.provenance->'description'->>'source' = 'wikidata'
                  AND EXISTS (SELECT 1 FROM line_materials lm WHERE lm.material_id = m.id AND lm.confidence = 'high')
                ORDER BY m.id
            SQL, [self::MIN_DESCRIPTION]);

            $items = [];
            foreach ($rows as $r) {
                $image = json_decode((string) $r->image, true) ?: [];
                $photo = self::photo($image);
                if ($photo === null || ! Material::isReadableName($r->name)) {
                    continue;
                }
                $items[] = [
                    'id' => (int) $r->id,
                    'key' => (string) $r->slug,
                    'label' => (string) $r->name,
                    'type' => (string) $r->type,
                    'from' => $r->in_service_from !== null ? (int) $r->in_service_from : null,
                    'photo' => $photo,
                ];
            }
            // Quatre propositions du même type : les types trop peu fournis sont écartés.
            $perType = array_count_values(array_column($items, 'type'));

            return array_values(array_filter($items, fn (array $m): bool => $perType[$m['type']] >= QuizSeries::OPTIONS));
        });
    }

    /** Séries du même type, de la mise en service la plus proche à la plus lointaine (date inconnue en dernier). */
    public function candidates(array $subject, array $pool, QuizLevel $level): array
    {
        $others = array_values(array_filter($pool, fn (array $m): bool => $m['type'] === $subject['type'] && $m['id'] !== $subject['id']));
        $gap = fn (array $m): int => $m['from'] === null || $subject['from'] === null ? PHP_INT_MAX : abs($m['from'] - $subject['from']);
        usort($others, fn (array $a, array $b): int => [$gap($a), $a['id']] <=> [$gap($b), $b['id']]);

        return $others;
    }

    public function question(QuizPick $pick): array
    {
        $m = Material::query()->find($pick->subject['id']);
        $photo = $pick->subject['photo'];

        return [
            // Droits de l'image (auteur, licence) affichés avec elle ; l'adresse est ajoutée par QuizSeries.
            'photo' => [
                'width' => $photo['width'],
                'height' => $photo['height'],
                'author' => $photo['author'],
                'license' => $photo['license'],
                'license_url' => $photo['license_url'],
                'rights_status' => $photo['rights_status'],
            ],
            'hints' => [
                'type' => $pick->subject['type'],
                'from' => $m?->in_service_from,
                'to' => $m?->in_service_to,
                'builders' => array_slice($m?->builderNames() ?? [], 0, self::MAX_BUILDERS),
                'max_speed_kmh' => $m?->max_speed_kmh,
                'units_built' => $m?->units_built,
            ],
        ];
    }

    public function reveal(array $subject): array
    {
        $m = Material::query()->find($subject['id']);

        return [
            'answer' => [
                'key' => (string) $m?->slug,
                'label' => (string) $m?->name,
                'url' => '/materials/'.$m?->slug,
            ],
            'fact' => [
                'text' => trim((string) $m?->description),
                'lang' => 'fr',
                'source' => [
                    'name' => 'Wikidata',
                    'title' => $m?->wikidata_id,
                    'url' => $m?->wikidataUrl(),
                    'license' => self::WIKIDATA_LICENSE,
                    'license_url' => self::WIKIDATA_LICENSE_URL,
                ],
            ],
            // Page du fichier sur Commons : son adresse porte souvent le nom de la série, elle n'est
            // donnée qu'après la réponse.
            'photo_source_url' => $subject['photo']['source_url'],
        ];
    }

    /**
     * Adresse de l'image à afficher (redirection de QuizController::photo).
     *
     * @param  array<string, mixed>  $subject
     */
    public function photoUrl(array $subject): string
    {
        return (string) $subject['photo']['url'];
    }

    /**
     * Image utilisable : adresses en http(s), auteur, et lien vers la licence sauf pour le domaine public.
     *
     * @param  array<string, mixed>  $image
     * @return array{url: string, width: ?int, height: ?int, author: string, license: ?string, license_url: ?string, rights_status: string, source_url: string}|null
     */
    public static function photo(array $image): ?array
    {
        $url = WebUrl::safe($image['display_url'] ?? null) ?? WebUrl::safe($image['thumb_url'] ?? null);
        $source = WebUrl::safe($image['source_url'] ?? null);
        $licenseUrl = WebUrl::safe($image['license_url'] ?? null);
        $status = (string) ($image['rights_status'] ?? '');
        $author = trim((string) ($image['author'] ?? ''));
        if ($url === null || $source === null || $author === '' || ($licenseUrl === null && $status !== 'public_domain')) {
            return null;
        }

        return [
            'url' => $url,
            'width' => isset($image['width']) ? (int) $image['width'] : null,
            'height' => isset($image['height']) ? (int) $image['height'] : null,
            'author' => $author,
            'license' => isset($image['license']) ? (string) $image['license'] : null,
            'license_url' => $licenseUrl,
            'rights_status' => $status,
            'source_url' => $source,
        ];
    }
}
