<?php

namespace App\Mcp\Tools;

use App\Enums\MediaType;
use App\Http\Resources\MediaResource;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Models\Media;
use App\Rules\PaginationCursor;
use App\Services\Api\MediaQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('media')]
#[Title('Photos, cartes postales, plans et documents')]
#[Description(<<<'TXT'
Médias (photos, cartes postales, plans, cartes, documents d'archive) rattachés aux lignes, aux gares et aux séries de matériel, avec leurs droits. Mêmes données et mêmes filtres que l'API REST GET /api/v1/media.
Chaque média : `id`, `type` (photo, postcard, plan, map, document), `title`, `caption`, `thumb_url` et `display_url` (image, souvent hébergée chez le fournisseur, ex. Wikimedia Commons), `source_url` (page d'origine), `width`/`height` en pixels, `depicted_year` (année représentée, `depicted_year_to` pour une fourchette, `date_precision`), `author`, `license`, `license_url`, `attribution` (texte prêt à afficher), `rights_status` (public_domain, open_license, permission, unknown), `commercial_ok`, `share_alike`, `location` (lat, lng WGS 84) et `subject` (ligne, gare ou série rattachée).
Obligation : montrez toujours l'`attribution` (auteur et licence) et le lien `source_url` avec l'image ; une licence share_alike impose la même licence à l'œuvre dérivée.
Filtres : `line`, `station` ou `material` (slug, un seul des trois), `type`, `provider` (commons, upload, argentanwebferro…), `license` (texte exact, ex. "CC BY-SA 4.0"), `rights_status`, `commercial_ok`, `period` (pre1900, 1900_1944, 1945_1969, 1970_1999, since2000, undated), `from_year`/`to_year` (année représentée), `order` (asc, desc : par identifiant).
Pagination : `limit` médias par appel (1 à 60, 20 par défaut), puis `next_cursor` dans `cursor` tant que `has_more` vaut true  ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend au premier média non renvoyé.
Exemple : {"station": "brest", "limit": 5} ; {"line": "rfn-610000", "period": "pre1900", "commercial_ok": true}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class MediaTool extends VffTool
{
    protected function rules(): array
    {
        return MediaQuery::rules() + [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(['media.id'])],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->max(255)->description('Slug d\'une ligne (ex. "rfn-610000"). Exclusif avec `station` et `material`.'),
            'station' => $schema->string()->max(255)->description('Slug d\'une gare (ex. "brest"). Exclusif avec `line` et `material`.'),
            'material' => $schema->string()->max(255)->description('Slug d\'une série de matériel roulant (ex. "x-73500") : images proposées pour sa fiche. Exclusif avec `line` et `station`.'),
            'type' => $schema->string()->enum(array_map(fn (MediaType $t) => $t->value, MediaType::cases()))->description('Nature du média. Par défaut : toutes.'),
            'provider' => $schema->string()->max(20)->description('Fournisseur : commons (Wikimedia Commons), upload (envoi sur VFF), argentanwebferro… Par défaut : tous.'),
            'license' => $schema->string()->max(191)->description('Licence exacte, casse ignorée (ex. "CC BY-SA 4.0", "Public domain").'),
            'rights_status' => $schema->string()->enum(MediaQuery::RIGHTS)->description('État des droits : public_domain, open_license, permission (autorisation particulière), unknown.'),
            'commercial_ok' => $schema->boolean()->description('true : réutilisation commerciale permise ; false : interdite ou inconnue.'),
            'period' => $schema->string()->enum([...array_keys(MediaQuery::PERIODS), 'undated'])->description('Tranche d\'époque représentée ; undated = année inconnue.'),
            'from_year' => $schema->integer()->min(1)->max(2100)->description('Année représentée minimale, incluse.'),
            'to_year' => $schema->integer()->min(1)->max(2100)->description('Année représentée maximale, incluse (au moins from_year).'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('Ordre des identifiants : asc (par défaut) ou desc (les plus récemment ajoutés d\'abord).'),
            'limit' => $schema->integer()->min(1)->max(60)->default(20)->description('Nombre de médias par appel, 1 à 60 (20 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = MediaQuery::normalize(array_diff_key($input, array_flip(['limit', 'cursor', 'lang'])));
        $page = app(MediaQuery::class)->apply(Media::query()->with('mediable'), $filters)
            ->cursorPaginate((int) ($input['limit'] ?? 20), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $media = MediaResource::collection($page->items())->resolve();

        return $this->respondPage(
            ['filters' => $filters], 'media', $page, $media,
            array_map(fn (array $m) => $this->attribution->providerFor('media', $m['provider'] ?? null), $media),
            $lang,
            self::url(match (true) {
                isset($filters['line']) => '/lines/'.$filters['line'],
                isset($filters['station']) => '/stations/'.$filters['station'],
                isset($filters['material']) => '/materials/'.$filters['material'],
                default => '/credits/media',
            }),
        );
    }
}
