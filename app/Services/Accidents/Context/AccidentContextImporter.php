<?php

namespace App\Services\Accidents\Context;

use App\Enums\AccidentContextSection;
use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentContextEntry;
use App\Models\AccidentImage;
use App\Models\AccidentReference;
use App\Services\Accidents\AccidentSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Enregistre le contexte sourcé des accidents à partir des fichiers database/data/accident_contexts/<slug>.php
 * (vff:enrich-accidents). Le fichier est la seule source : la base n'est jamais modifiée à la main.
 *
 *   - chaque fichier est contrôlé (AccidentContextValidator) ; un fichier en erreur n'écrit rien ;
 *   - pour chaque fiche, documents, énoncés et images sont remplacés en entier (rejouable, idempotent) ;
 *   - images : auteur et licence relus sur Commons (AccidentImageResolver), fichier non libre écarté ;
 *   - corrections de date, d'heure ou de bilan : appliquées à la fiche, valeur d'avant gardée dans
 *     accidents.context_corrections ; une correction retirée du fichier rend la valeur d'avant ; le résumé
 *     construit est réécrit avec les faits corrigés ;
 *   - passage complet (sans --only) : une fiche dont le fichier a disparu perd son contexte et ses corrections.
 *
 * vff:import-accidents réécrit date et bilan depuis ses sources : rejouer cette commande après un import.
 */
final class AccidentContextImporter
{
    public function __construct(
        private readonly AccidentContextValidator $validator,
        private readonly AccidentSummary $summary,
    ) {}

    public static function defaultDirectory(): string
    {
        return database_path('data/accident_contexts');
    }

    /**
     * @param  list<string>  $only  slugs à traiter (vide : tous les fichiers)
     */
    public function run(string $directory, array $only, AccidentImageResolver $images, bool $dryRun = false, bool $checkOnly = false): AccidentContextResult
    {
        $result = new AccidentContextResult;
        $files = $this->files($directory, $only, $result);
        $valid = [];
        foreach ($files as $slug => $path) {
            $data = self::load($path);
            $errors = $this->validator->errors($data, $slug);
            $accident = Accident::withoutGlobalScopes()->where('slug', $slug)->first();
            if ($accident === null) {
                $errors[] = "aucune fiche « {$slug} » dans la table accidents";
            } elseif ($accident->alias_of_id !== null) {
                $errors[] = "la fiche « {$slug} » est un alias : écrire le contexte sur la fiche retenue";
            } elseif (in_array($accident->kind->value, AccidentKind::HIDDEN, true)) {
                $errors[] = "la fiche « {$slug} » n'est pas publiée (nature masquée)";
            }
            if ($errors !== []) {
                $result->invalid[$slug] = $errors;

                continue;
            }
            /** @var array<string, mixed> $data */
            /** @var Accident $accident */
            $valid[$slug] = [$accident, $data];
        }
        $result->checked = count($files);
        if ($checkOnly) {
            return $result;
        }

        DB::beginTransaction();
        try {
            foreach ($valid as $slug => [$accident, $data]) {
                $this->write($accident, $data, $images, $result);
                $result->written[] = $slug;
            }
            if ($only === []) {
                $this->removeOrphans(array_keys($files), $result);
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $result->imageRequests = $images->requests;

        return $result;
    }

    /**
     * Typographie des textes affichés : apostrophe courbe, espace insécable dans les nombres (« 1 000 ») et entre
     * un nombre et son unité (« 350 m », « 40 km/h », « 23 h 15 »), pour qu'ils ne soient jamais coupés en fin de
     * ligne.
     */
    public static function typography(string $text): string
    {
        $t = trim(str_replace("'", "\u{2019}", $text));
        $t = preg_replace('/\b(\d{1,2}) h (\d{2})\b/u', "$1\u{00A0}h\u{00A0}$2", $t) ?? $t;
        $t = preg_replace('/(\d) (?=\d{3}(?!\d))/u', "$1\u{00A0}", $t) ?? $t;

        return preg_replace('/(\d) (?=(?:km\/h|km|m|h|t|Uhr|%|‰)(?!\p{L}))/u', "$1\u{00A0}", $t) ?? $t;
    }

    /** Lit un fichier de données dans une portée vide (aucune variable de la commande visible). */
    public static function load(string $path): mixed
    {
        return (static fn (string $__file): mixed => require $__file)($path);
    }

    /**
     * @param  list<string>  $only
     * @return array<string, string> slug => chemin
     */
    private function files(string $directory, array $only, AccidentContextResult $result): array
    {
        if (! File::isDirectory($directory)) {
            throw new RuntimeException("Dossier introuvable : {$directory}");
        }
        $files = [];
        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $files[$file->getBasename('.php')] = $file->getPathname();
            }
        }
        ksort($files);
        if ($only !== []) {
            foreach (array_diff($only, array_keys($files)) as $missing) {
                $result->invalid[$missing] = ["aucun fichier {$missing}.php"];
            }
            $files = array_intersect_key($files, array_flip($only));
        }

        return $files;
    }

    /** @param  array<string, mixed>  $data */
    private function write(Accident $accident, array $data, AccidentImageResolver $images, AccidentContextResult $result): void
    {
        $now = Carbon::now();
        AccidentReference::query()->where('accident_id', $accident->id)->delete();
        AccidentContextEntry::query()->where('accident_id', $accident->id)->delete();
        AccidentImage::query()->where('accident_id', $accident->id)->delete();

        $urls = [];
        $position = 0;
        foreach ($data['sources'] as $key => $s) {
            $urls[$key] = $s['url'];
            AccidentReference::query()->create([
                'accident_id' => $accident->id,
                'key' => $key,
                'position' => $position++,
                'provider' => $s['provider'],
                'kind' => $s['kind'],
                'reliability' => $s['reliability'],
                'title' => trim($s['title']),
                'publisher' => $s['publisher'] ?? null,
                'author' => $s['author'] ?? null,
                'published' => $s['published'] ?? null,
                'locator' => $s['locator'] ?? null,
                'url' => $s['url'],
                'access' => $s['access'],
                'consulted_on' => $s['consulted_on'],
                'note' => $s['note'] ?? null,
            ]);
            $result->references++;
        }

        $position = 0;
        foreach (AccidentContextSection::cases() as $section) {
            foreach ($data['sections'][$section->value] ?? [] as $entry) {
                $quote = isset($entry['quote']) ? [
                    'text' => $entry['quote']['text'],
                    'lang' => $entry['quote']['lang'],
                    'reference' => $entry['quote']['source'],
                ] : null;
                AccidentContextEntry::query()->create([
                    'accident_id' => $accident->id,
                    'section' => $section->value,
                    'position' => $position++,
                    'text' => ['fr' => self::typography($entry['fr']), 'en' => self::typography($entry['en']), 'de' => self::typography($entry['de'])],
                    'reference_keys' => array_values($entry['sources']),
                    'quote' => $quote,
                ]);
                $result->entries++;
            }
        }

        $position = 0;
        foreach ($data['images'] ?? [] as $image) {
            $found = $images->resolve($image['file']);
            if (! $found['ok']) {
                $result->imagesRejected[] = "{$accident->slug} : {$image['file']} ({$found['reason']})";

                continue;
            }
            AccidentImage::query()->create([
                ...$found['image'],
                'accident_id' => $accident->id,
                'position' => $position++,
                'caption' => ['fr' => self::typography($image['caption']['fr']), 'en' => self::typography($image['caption']['en']), 'de' => self::typography($image['caption']['de'])],
                'retrieved_at' => $now,
            ]);
            $result->images++;
        }

        $this->applyCorrections($accident, $data['corrections'] ?? [], $urls, $result);
        $accident->context_checked_on = Carbon::parse($data['checked_on']);
        $accident->save();
    }

    /**
     * @param  list<array<string, mixed>>  $corrections
     * @param  array<string, string>  $urls  clé de document => lien
     */
    private function applyCorrections(Accident $accident, array $corrections, array $urls, AccidentContextResult $result): void
    {
        $previous = collect($accident->context_corrections ?? [])->keyBy('field')->all();
        $applied = [];
        $changed = false;
        foreach ($corrections as $c) {
            $field = $c['field'];
            $current = $this->value($accident, $field);
            $before = array_key_exists($field, $previous) ? $previous[$field]['before'] : $current;
            $beforeMeta = $previous[$field]['before_meta'] ?? $this->meta($accident, $field);
            if ($current !== $c['value']) {
                $this->set($accident, $field, $c['value']);
                $changed = true;
            }
            if (in_array($field, ['deaths', 'injuries'], true)) {
                $accident->toll_source = 'context';
                $accident->toll_source_url = $urls[$c['sources'][0]];
                $accident->provenance = [...($accident->provenance ?? []), $field => 'context'];
            }
            $applied[] = [
                'field' => $field,
                'before' => $before,
                'before_meta' => $beforeMeta,
                'value' => $c['value'],
                'references' => array_values($c['sources']),
                'reason' => ['fr' => self::typography($c['fr']), 'en' => self::typography($c['en']), 'de' => self::typography($c['de'])],
            ];
            $result->corrections[] = "{$accident->slug} : {$field} ".json_encode($before).' -> '.json_encode($c['value']);
        }
        // Correction retirée du fichier : la valeur d'avant revient, si personne ne l'a changée depuis.
        foreach (array_diff_key($previous, array_flip(array_column($corrections, 'field'))) as $field => $old) {
            if ($this->value($accident, $field) === $old['value']) {
                $this->set($accident, $field, $old['before']);
                $this->restoreMeta($accident, $field, $old['before_meta'] ?? []);
                $changed = true;
            }
        }
        $accident->context_corrections = $applied !== [] ? $applied : null;
        if ($changed) {
            $this->rewriteSummary($accident, array_column($applied, 'field'));
        }
    }

    private function value(Accident $a, string $field): int|string|null
    {
        return match ($field) {
            'occurred_on' => $a->occurred_on->toDateString(),
            'occurred_at_time' => $a->occurred_at_time !== null ? substr($a->occurred_at_time, 0, 5) : null,
            'deaths' => $a->deaths,
            'injuries' => $a->injuries,
            default => throw new RuntimeException("champ {$field}"),
        };
    }

    private function set(Accident $a, string $field, int|string|null $value): void
    {
        match ($field) {
            'occurred_on' => $a->occurred_on = Carbon::parse((string) $value),
            'occurred_at_time' => $a->occurred_at_time = $value !== null ? $value.':00' : null,
            'deaths' => $a->deaths = $value !== null ? (int) $value : null,
            'injuries' => $a->injuries = $value !== null ? (int) $value : null,
            default => throw new RuntimeException("champ {$field}"),
        };
    }

    /** @return array<string, mixed> */
    private function meta(Accident $a, string $field): array
    {
        return in_array($field, ['deaths', 'injuries'], true) ? [
            'toll_source' => $a->toll_source,
            'toll_source_url' => $a->toll_source_url,
            'provenance' => $a->provenance[$field] ?? null,
        ] : [];
    }

    /** @param  array<string, mixed>  $meta */
    private function restoreMeta(Accident $a, string $field, array $meta): void
    {
        if ($meta === []) {
            return;
        }
        $a->toll_source = $meta['toll_source'];
        $a->toll_source_url = $meta['toll_source_url'];
        $provenance = $a->provenance ?? [];
        if ($meta['provenance'] !== null) {
            $provenance[$field] = $meta['provenance'];
        } else {
            unset($provenance[$field]);
        }
        $a->provenance = $provenance;
    }

    /** @param  list<string>  $correctedFields */
    private function rewriteSummary(Accident $a, array $correctedFields): void
    {
        $tollCorrected = array_intersect($correctedFields, ['deaths', 'injuries']) !== [];
        $mismatch = collect($a->review_notes ?? [])->contains(fn (array $n) => $n['code'] === 'toll_mismatch');
        $a->summary = $this->summary->write([
            'date' => $a->occurred_on->toDateString(),
            'precision' => $a->date_precision,
            'kind' => $a->kind,
            'commune' => $a->commune_name,
            'department' => $a->department_code,
            'trains' => array_map(fn (array $t) => [
                'label' => $t['label'] ?? null, 'category' => $t['category'] ?? null, 'number' => $t['number'] ?? null,
                'material' => $t['material'] ?? null, 'operator' => $t['operator'] ?? null, 'source' => $t['source'] ?? null,
            ], $a->trains ?? []),
            'deaths' => $a->deaths,
            'injuries' => $a->injuries,
            'toll_disputed' => $mismatch && ! $tollCorrected,
        ]);
    }

    /** @param  list<string>  $present  slugs des fichiers présents */
    private function removeOrphans(array $present, AccidentContextResult $result): void
    {
        $ids = AccidentReference::query()->distinct()->pluck('accident_id')
            ->merge(AccidentContextEntry::query()->distinct()->pluck('accident_id'))
            ->merge(Accident::withoutGlobalScopes()->where(fn ($q) => $q->whereNotNull('context_checked_on')->orWhereNotNull('context_corrections'))->pluck('id'))
            ->unique()->all();
        $orphans = Accident::withoutGlobalScopes()->whereIn('id', $ids)->whereNotIn('slug', $present)->get();
        foreach ($orphans as $accident) {
            AccidentReference::query()->where('accident_id', $accident->id)->delete();
            AccidentContextEntry::query()->where('accident_id', $accident->id)->delete();
            AccidentImage::query()->where('accident_id', $accident->id)->delete();
            $this->applyCorrections($accident, [], [], $result);
            $accident->context_checked_on = null;
            $accident->save();
            $result->removed[] = $accident->slug;
        }
    }
}
