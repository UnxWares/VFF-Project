<?php

namespace App\Services\Accidents\Context;

use App\Enums\AccidentContextSection;
use App\Models\Accident;
use App\Models\AccidentContextEntry;
use App\Models\AccidentImage;
use App\Models\AccidentReference;
use App\Services\Api\Provenance;
use App\Services\Media\CommonsMetadata;

/**
 * Contexte sourcé d'un accident tel que le servent l'API REST, GraphQL, la fiche du site et le serveur MCP
 * (même contenu partout ; le MCP retire les citations, décision Q22).
 *
 * Relations à charger : references, contextEntries, images. Sans elles (ou sans contexte), les listes sont vides.
 */
final class AccidentContextView
{
    /** Relations Eloquent à charger avant d'appeler for(). */
    public const RELATIONS = ['references', 'contextEntries', 'images'];

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * @return array{checked_on: string|null, sections: list<array{section: string, entries: list<array{text: array<string, string>, references: list<string>, quote: array{text: string, lang: string, reference: string}|null}>}>, references: list<array<string, mixed>>, images: list<array<string, mixed>>, corrections: list<array<string, mixed>>}
     */
    public function for(Accident $accident): array
    {
        $entries = $accident->relationLoaded('contextEntries') ? $accident->contextEntries : collect();
        $references = $accident->relationLoaded('references') ? $accident->references : collect();
        $images = $accident->relationLoaded('images') ? $accident->images : collect();

        $sections = [];
        foreach (AccidentContextSection::cases() as $section) {
            $list = $entries->filter(fn (AccidentContextEntry $e) => $e->section === $section)->sortBy('position')->values();
            if ($list->isEmpty()) {
                continue;
            }
            $sections[] = [
                'section' => $section->value,
                'entries' => $list->map(fn (AccidentContextEntry $e) => [
                    'text' => $e->text,
                    'references' => $e->reference_keys,
                    'quote' => $e->quote,
                ])->all(),
            ];
        }

        return [
            'checked_on' => $accident->context_checked_on?->toDateString(),
            'sections' => $sections,
            'references' => $references->sortBy('position')->values()->map(function (AccidentReference $r): array {
                $rights = $this->provenance->for('accident_references', $r->provider, $r->url);

                return [
                    'key' => $r->key,
                    'provider' => $r->provider,
                    'kind' => $r->kind->value,
                    'reliability' => $r->reliability->value,
                    'title' => $r->title,
                    'publisher' => $r->publisher,
                    'author' => $r->author,
                    'published' => $r->published,
                    'locator' => $r->locator,
                    'url' => $r->url,
                    'access' => $r->access->value,
                    'consulted_on' => $r->consulted_on->toDateString(),
                    'note' => $r->note,
                    'license' => $rights['license'],
                    'license_status' => $rights['license_status'],
                ];
            })->all(),
            'images' => $images->sortBy('position')->values()->map(fn (AccidentImage $i): array => [
                'file' => $i->file,
                'page_url' => $i->page_url,
                'image_url' => $i->image_url,
                'thumb_url' => $i->thumb_url,
                'width' => $i->width,
                'height' => $i->height,
                'author' => $i->author,
                'license' => $i->licence,
                'license_url' => $i->licence_url,
                'date' => $i->date,
                'caption' => $i->caption,
                'attribution' => implode(', ', [$i->author, $i->licence, 'via Wikimedia Commons']),
                'retrieved_at' => $i->retrieved_at->toIso8601String(),
            ])->all(),
            'corrections' => array_map(fn (array $c): array => [
                'field' => $c['field'],
                'before' => $c['before'],
                'value' => $c['value'],
                'references' => $c['references'],
                'reason' => $c['reason'],
            ], $accident->context_corrections ?? []),
        ];
    }

    /**
     * Version du serveur MCP : textes dans la seule langue demandée, sans citation (décision Q22 : aucune phrase
     * reprise d'une source dans les réponses MCP), légendes dans cette langue.
     *
     * @param  array<string, mixed>  $context  résultat de for()
     * @return array<string, mixed>
     */
    public static function forMcp(array $context, string $lang): array
    {
        $pick = fn (array $t): string => $t[$lang] ?? $t['fr'] ?? '';
        $context['sections'] = array_map(fn (array $s): array => [
            'section' => $s['section'],
            'entries' => array_map(fn (array $e): array => ['text' => $pick($e['text']), 'references' => $e['references']], $s['entries']),
        ], $context['sections']);
        $context['images'] = array_map(fn (array $i): array => ['caption' => $pick($i['caption'])] + $i, $context['images']);
        $context['corrections'] = array_map(fn (array $c): array => ['reason' => $pick($c['reason'])] + $c, $context['corrections']);

        return $context;
    }

    /** Ligne de crédit d'une image (règle de CommonsMetadata : auteur, licence, via Wikimedia Commons). */
    public static function credit(AccidentImage $image): string
    {
        return (string) CommonsMetadata::plainDashes(implode(', ', [$image->author, $image->licence, 'via Wikimedia Commons']));
    }
}
