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
Médias (photos, cartes postales, plans, cartes, documents d'archive) rattachés aux lignes, aux gares, aux séries de matériel et aux événements datés des lignes, avec leurs droits. Mêmes données et mêmes filtres que l'API REST GET /api/v1/media.
Chaque média : `id`, `type` (photo, postcard, plan, map, document), `title`, `caption`, `captions`, `thumb_url` et `display_url` (image, souvent hébergée chez le fournisseur, ex. Wikimedia Commons), `source_url` (page d'origine), `width`/`height` en pixels, `depicted_year` (année représentée, `depicted_year_to` pour une fourchette, `date_precision`), `author`, `license`, `license_url`, `attribution` (texte prêt à afficher), `rights_status` (public_domain, open_license, unknown ; les images montrées sur le site avec une simple autorisation de leur auteur ne sont jamais servies), `commercial_ok`, `share_alike`, `location` (lat, lng WGS 84), `subject` (ligne, gare, série ou événement rattaché) et `contribution_id` (numéro de la contribution validée qui a publié l'image, null pour un import ; une image encore en attente de validation n'est jamais renvoyée).
`captions` donne la légende par langue (`fr`, `en`, `de`) quand les contributeurs en ont écrit plusieurs, sinon null : montrez celle de la langue de l'utilisateur, à défaut `caption` (pour une image Commons, `caption` est la légende de Commons et `captions` ne porte que les ajouts en anglais et en allemand).
Obligation : montrez toujours l'`attribution` (auteur et licence) et le lien `source_url` avec l'image ; une licence share_alike impose la même licence à l'œuvre dérivée.
Filtres : `line`, `station` ou `material` (slug), ou `event` (identifiant d'un événement de get_line ou line_events), un seul des quatre, `type`, `provider` (commons, upload, argentanwebferro…), `license` (texte exact, ex. "CC BY-SA 4.0"), `rights_status`, `commercial_ok`, `period` (pre1900, 1900_1944, 1945_1969, 1970_1999, since2000, undated), `from_year`/`to_year` (année représentée), `featured` (true : images que l'accueil peut montrer, false : les autres), `order` (asc, desc : par identifiant). Avec `facets` à true, `facets` donne les compteurs de la recherche (total, `types` par nature, `licenses`, 30 premiers `authors`), ceux de `GET /api/v1/media?facets=true`.
Pagination : `limit` médias par appel (1 à 60, 20 par défaut), puis `next_cursor` dans `cursor` tant que `has_more` vaut true  ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend au premier média non renvoyé.
Exemple : {"station": "brest", "limit": 5} ; {"line": "rfn-610000", "period": "pre1900", "commercial_ok": true}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class MediaTool extends VffTool
{
    /** Ancien slug dans `line` ou `station` : filtre sous le slug actuel, dit dans `redirected`. */
    protected const SLUG_FILTERS = true;

    protected function rules(): array
    {
        return MediaQuery::rules() + [
            'facets' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(['media.id'])],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->max(255)->description('Slug d\'une ligne (ex. "rfn-610000"). Exclusif avec `station`, `material` et `event`.'),
            'station' => $schema->string()->max(255)->description('Slug d\'une gare (ex. "brest"). Exclusif avec `line`, `material` et `event`.'),
            'material' => $schema->string()->max(255)->description('Slug d\'une série de matériel roulant (ex. "x-73500") : images proposées pour sa fiche. Exclusif avec `line`, `station` et `event`.'),
            'event' => $schema->integer()->min(1)->description('Identifiant d\'un événement daté d\'une ligne (champ `id` des événements de get_line ou line_events) : ses images seulement. Exclusif avec `line`, `station` et `material`.'),
            'type' => $schema->string()->enum(array_map(fn (MediaType $t) => $t->value, MediaType::cases()))->description('Nature du média. Par défaut : toutes.'),
            'provider' => $schema->string()->max(20)->description('Fournisseur : commons (Wikimedia Commons), upload (envoi sur VFF), argentanwebferro… Par défaut : tous.'),
            'license' => $schema->string()->max(191)->description('Licence exacte, casse ignorée (ex. "CC BY-SA 4.0", "Public domain").'),
            'rights_status' => $schema->string()->enum(MediaQuery::RIGHTS)->description('État des droits : public_domain, open_license, unknown. Les images montrées sur le site avec une simple autorisation de leur auteur (permission) ne sont jamais servies.'),
            'commercial_ok' => $schema->boolean()->description('true : réutilisation commerciale permise ; false : interdite ou inconnue.'),
            'period' => $schema->string()->enum([...array_keys(MediaQuery::PERIODS), 'undated'])->description('Tranche d\'époque représentée ; undated = année inconnue.'),
            'from_year' => $schema->integer()->min(1)->max(2100)->description('Année représentée minimale, incluse.'),
            'to_year' => $schema->integer()->min(1)->max(2100)->description('Année représentée maximale, incluse (au moins from_year).'),
            'featured' => $schema->boolean()->description('true : seulement les médias mis en avant (images que l\'accueil peut montrer) ; false : les autres.'),
            'facets' => $schema->boolean()->default(false)->description('true : ajoute `facets`, les compteurs de la recherche (total, nombre par nature, par licence, 30 premiers auteurs), sans tenir compte de la pagination.'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('Ordre des identifiants : asc (par défaut) ou desc (les plus récemment ajoutés d\'abord).'),
            'limit' => $schema->integer()->min(1)->max(60)->default(20)->description('Nombre de médias par appel, 1 à 60 (20 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = MediaQuery::normalize(array_diff_key($input, array_flip(['limit', 'cursor', 'lang', 'facets'])));
        $page = app(MediaQuery::class)->apply(Media::query()->with('mediable'), $filters)
            ->cursorPaginate((int) ($input['limit'] ?? 20), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $media = MediaResource::collection($page->items())->resolve();

        $payload = ['filters' => $filters];
        if (filter_var($input['facets'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $payload['facets'] = app(MediaQuery::class)->facets($filters);
        }

        return $this->respondPage(
            $payload, 'media', $page, $media,
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
