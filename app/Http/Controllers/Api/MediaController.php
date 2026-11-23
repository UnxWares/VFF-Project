<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Services\Api\MediaQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

#[Group('Médias', "Photos, cartes postales, plans, cartes et documents d'archive liés aux lignes, aux gares et aux séries de matériel, avec leur auteur, leur licence et le lien vers leur source.", weight: 6)]
class MediaController extends Controller
{
    /**
     * Médias.
     *
     * Photos, cartes postales, plans, cartes et documents d'archive, toutes
     * lignes, gares et séries de matériel confondues, filtrables et paginés par curseur, dans
     * l'ordre croissant de leur identifiant (`order=desc` pour l'inverse).
     * Pour les médias d'une seule ligne avec compteurs par filtre, voir
     * `/lines/{line}/media`.
     *
     * **Pagination par curseur** : la réponse contient `links.next` (adresse
     * de la page suivante, filtres conservés, `null` à la dernière page) et
     * `meta.next_cursor`. Suivez `links.next` tant qu'elle n'est pas `null` :
     * pas de numéro de page ni de total, le curseur reste valable si des
     * médias sont ajoutés entre deux appels. C'est une chaîne opaque, à
     * reprendre telle quelle ; un curseur illisible ou venu d'une autre liste
     * donne HTTP 422.
     *
     * **Droits** : les médias externes (Wikimedia Commons…) ne sont pas
     * hébergés par VFF ; `thumb_url` (vignette d'environ 500 px) et
     * `display_url` (grande taille) pointent chez le fournisseur, et
     * `source_url` vers la page d'origine (la page Wikimedia Commons du
     * fichier, pour un média Commons). Une image déposée par un contributeur
     * (`provider` = `upload`) est hébergée par VFF : `thumb_url` est sa
     * miniature WebP de 320 px de large et `display_url` celle de 1 280 px
     * (jamais agrandies, sans métadonnées), ou le fichier d'origine tant
     * qu'elles n'existent pas. Chaque média porte ses propres droits,
     * à afficher avec l'image : `attribution` (ligne de crédit prête à
     * l'emploi), `author`, `license` (telle que déclarée par la source,
     * `null` si inconnue : aucune licence n'est présumée), `license_url`,
     * `rights_status` (`public_domain` domaine public, `open_license` licence
     * libre, `unknown` inconnu), `commercial_ok`
     * (usage commercial permis, `null` si inconnu), `share_alike` (partage
     * dans les mêmes conditions exigé), `attribution_required` (mention de
     * l'auteur obligatoire), `rights_uncertain` (la licence du fonds est
     * « incertaine » : à signaler avec l'image). Une image que le site montre avec une simple
     * autorisation de son auteur (`permission`) n'est jamais diffusée par
     * l'API : elle n'est ni dans la liste ni dans les compteurs, et sa fiche
     * répond 404 (même règle que GraphQL, l'outil MCP `media` et les
     * histoires d'événements).
     *
     * **Datation** : `depicted_year` est l'année représentée par le média (pas
     * celle de sa mise en ligne), `depicted_year_to` la fin d'une fourchette et
     * `date_precision` dit ce qu'on en sait : `day`, `month`, `year`, `decade`
     * (décennie), `circa` (vers), `range` (fourchette), `before` / `after`
     * (avant, après). `captured_at` : date de prise de vue (AAAA-MM-JJ) quand
     * elle est connue ; `location` : lieu de prise de vue en WGS 84 (degrés
     * décimaux), `null` si inconnu.
     *
     * **Rattachement** : `subject` dit à quoi le média est lié : `type`
     * (`line`, `station`, `material` ou `event`) avec `slug` et `name` (ou
     * `id` pour un événement).
     *
     * **Contributions** : `contribution_id` donne le numéro de la contribution
     * validée qui a publié le média (fichier de Wikimedia Commons ou fichier
     * déposé, `provider` = `upload`), `null` pour un média importé par VFF.
     * Une image proposée n'entre dans la liste qu'après sa validation : celles
     * qui attendent encore n'y sont jamais.
     *
     * **Mis en avant** : `featured=true` donne les images que l'accueil peut
     * montrer (champ `featured`), `featured=false` les autres.
     *
     * **Compteurs** : `facets=true` ajoute `meta.facets`, les chiffres de la
     * page [/credits/media](/credits/media) pour la recherche en cours :
     * `total`, `types` (nombre de médias par nature), `licenses` (par licence
     * déclarée, `null` = inconnue, les plus fréquentes d'abord) et `authors`
     * (les 30 auteurs les plus représentés).
     */
    #[QueryParameter('line', "Identifiant (slug) d'une ligne : ses médias seulement. Exclusif avec `station`, `material` et `event`. Une ligne inconnue donne HTTP 422.", type: 'string', example: 'rfn-447000')]
    #[QueryParameter('station', "Identifiant (slug) d'une gare : ses médias seulement. Exclusif avec `line`, `material` et `event`. Une gare inconnue donne HTTP 422.", type: 'string', example: 'brest')]
    #[QueryParameter('material', "Identifiant (slug) d'une série de matériel roulant : ses médias seulement (images proposées pour sa fiche et publiées). Exclusif avec `line`, `station` et `event`. Une série inconnue donne HTTP 422.", type: 'string', example: 'x-73500')]
    #[QueryParameter('event', "Identifiant d'un événement daté d'une ligne (`id` de `/events`) : ses images seulement (proposées pour cet événement et publiées). Exclusif avec `line`, `station` et `material`. Un événement inconnu donne HTTP 422.", type: 'int', example: 120394)]
    #[QueryParameter('type', 'Type de média : `photo`, `postcard` (carte postale), `map` (carte), `plan`, `document`.', type: 'string', example: 'postcard')]
    #[QueryParameter('provider', 'Fournisseur du média, 20 caractères au plus : `commons` (Wikimedia Commons), `upload` (hébergé par VFF), `argentanwebferro`…', type: 'string', example: 'commons')]
    #[QueryParameter('license', 'Licence telle que déclarée par la source, sans tenir compte de la casse, correspondance exacte (ex. `CC BY-SA 4.0`, `Public domain`). 191 caractères au plus.', type: 'string', example: 'CC BY-SA 4.0')]
    #[QueryParameter('rights_status', 'Statut des droits : `public_domain` (domaine public), `open_license` (licence libre), `unknown` (inconnu). `permission` (simple autorisation de l\'auteur, images jamais diffusées par l\'API) donne HTTP 422.', type: 'string', example: 'public_domain')]
    #[QueryParameter('commercial_ok', "`true` : seulement les médias dont l'usage commercial est permis ; `false` : ceux dont il ne l'est pas. Les médias dont on l'ignore ne sont dans aucun des deux. Valeurs acceptées : `true`, `false`, `1`, `0`.", type: 'boolean', example: true)]
    #[QueryParameter('period', 'Époque représentée : `pre1900` (jusqu\'à 1899), `1900_1944`, `1945_1969`, `1970_1999`, `since2000` (depuis 2000), `undated` (année inconnue). Bornes incluses.', type: 'string', example: '1900_1944')]
    #[QueryParameter('from_year', 'Année représentée minimale, de 1 à 2100, incluse. Exclut les médias non datés.', type: 'int', example: 1900)]
    #[QueryParameter('to_year', 'Année représentée maximale, de 1 à 2100, incluse, au moins égale à `from_year` (sinon HTTP 422). Exclut les médias non datés.', type: 'int', example: 1944)]
    #[QueryParameter('featured', '`true` : seulement les médias mis en avant (images de l\'accueil) ; `false` : les autres.', type: 'boolean', example: true)]
    #[QueryParameter('facets', 'Ajouter les compteurs de la recherche dans `meta.facets` (total, par nature, par licence, 30 premiers auteurs) : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: true)]
    #[QueryParameter('order', 'Ordre des identifiants : `asc` (par défaut) ou `desc`.', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('per_page', 'Nombre de médias par page, de 1 à 60.', type: 'int', default: 24, example: 24)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer. Sans curseur : première page.', type: 'string', example: 'eyJtZWRpYS5pZCI6MjkzMCwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ')]
    #[ApiResponse(200, 'Une page de médias (ici un média de la ligne de Morlaix à Roscoff, `line=rfn-447000&per_page=1&facets=true`, compteurs raccourcis).', examples: [[
        'data' => [[
            'id' => 2930, 'provider' => 'commons', 'provider_label' => 'Wikimedia Commons', 'type' => 'photo',
            'title' => 'Sainte-Sève ligne Morlaix - Roscoff',
            'caption' => 'La ligne Morlaix - Roscoff au lieu-dit "Les Quatre Vents" sur la commune de Sainte-Sève après l\'orage du 3 juin 2018.',
            'thumb_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a0/Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg/500px-Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
            'display_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a0/Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg/1280px-Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
            'width' => 5152, 'height' => 3864, 'depicted_year' => 2018, 'depicted_year_to' => null, 'date_precision' => 'day',
            'captured_at' => '2018-06-07', 'author' => 'Spendeau', 'attribution' => 'Spendeau, CC BY-SA 4.0, via Wikimedia Commons',
            'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'rights_status' => 'open_license',
            'commercial_ok' => true, 'share_alike' => true, 'attribution_required' => true, 'featured' => false, 'contribution_id' => null,
            'location' => ['lat' => 48.561166, 'lng' => -3.866779],
            'subject' => ['type' => 'line', 'slug' => 'rfn-447000', 'name' => 'Ligne de Morlaix à Roscoff'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => 'https://vff-project.org/api/v1/media?line=rfn-447000&per_page=1&facets=true&cursor=eyJtZWRpYS5pZCI6MjkzMCwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ'],
        'meta' => ['path' => 'https://vff-project.org/api/v1/media', 'per_page' => 1, 'next_cursor' => 'eyJtZWRpYS5pZCI6MjkzMCwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', 'prev_cursor' => null,
            'facets' => [
                'total' => 51, 'types' => ['photo' => 44, 'postcard' => 7],
                'licenses' => [['license' => 'CC BY-SA 4.0', 'count' => 20], ['license' => 'CC BY-SA 3.0', 'count' => 14], ['license' => 'Public domain', 'count' => 10]],
                'authors' => [['author' => 'Spendeau', 'count' => 9], ['author' => 'Bruno Corpet (Quoique)', 'count' => 5]],
            ],
        ],
    ]])]
    #[ApiResponse(422, 'Filtre ou curseur invalide : type inconnu, deux filtres parmi `line`, `station`, `material` et `event` ensemble, ligne, gare, série ou événement inconnu, `to_year` avant `from_year`, `per_page` hors de 1 à 60. Le corps donne `message` et, par paramètre, la liste des erreurs dans `errors`.', examples: [ErrorExamples::MEDIA_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListMediaRequest $request, MediaQuery $query): JsonResponse
    {
        $media = $query->apply($this->withSubject(Media::query()), $request->filters())
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        $facets = null;
        if ($request->wantsFacets()) {
            $facets = $query->facets($request->filters());
            // Objet JSON même vide (`{}`), comme les autres dictionnaires de compteurs.
            $facets['types'] = (object) $facets['types'];
        }

        return MediaResource::collection($media)
            ->additional($facets !== null ? ['meta' => ['facets' => $facets]] : [])
            ->response()->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fiche d'un média.
     *
     * Un média avec toutes ses informations de droits (auteur, licence,
     * attribution, lien vers la source), son datage, sa position et son
     * rattachement. Mêmes champs, mêmes unités et mêmes valeurs possibles que
     * dans la liste : voir `/media` pour leur sens. Affichez toujours
     * `attribution` avec l'image et gardez le lien `source_url`.
     */
    #[PathParameter('media', 'Identifiant numérique du média, tel que renvoyé par la liste (`id`), 1 à 18 chiffres.', example: 2930)]
    #[ApiResponse(200, 'Fiche du média.', examples: [['data' => [
        'id' => 2930, 'provider' => 'commons', 'provider_label' => 'Wikimedia Commons', 'type' => 'photo',
        'title' => 'Sainte-Sève ligne Morlaix - Roscoff',
        'caption' => 'La ligne Morlaix - Roscoff au lieu-dit "Les Quatre Vents" sur la commune de Sainte-Sève après l\'orage du 3 juin 2018.',
        'thumb_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a0/Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg/500px-Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
        'display_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a0/Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg/1280px-Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
        'source_url' => 'https://commons.wikimedia.org/wiki/File:Sainte-S%C3%A8ve_ligne_Morlaix_-_Roscoff.jpg',
        'width' => 5152, 'height' => 3864, 'depicted_year' => 2018, 'depicted_year_to' => null, 'date_precision' => 'day',
        'captured_at' => '2018-06-07', 'author' => 'Spendeau', 'attribution' => 'Spendeau, CC BY-SA 4.0, via Wikimedia Commons',
        'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'rights_status' => 'open_license',
        'commercial_ok' => true, 'share_alike' => true, 'attribution_required' => true, 'featured' => false, 'contribution_id' => null,
        'location' => ['lat' => 48.561166, 'lng' => -3.866779],
        'subject' => ['type' => 'line', 'slug' => 'rfn-447000', 'name' => 'Ligne de Morlaix à Roscoff'],
    ]]])]
    #[ApiResponse(404, 'Média inconnu, image que l\'API ne diffuse pas (droits `permission`), ou identifiant qui n\'est pas un entier de 1 à 18 chiffres.', examples: [ErrorExamples::MEDIA_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(int $media, MediaQuery $query): JsonResponse
    {
        $item = $query->apply($this->withSubject(Media::query()), [])->whereKey($media)->firstOrFail();

        return MediaResource::make($item)->response()->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Charge l'entité de rattachement avec les seules colonnes utiles (les lignes portent de longs textes).
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    private function withSubject(Builder $query): Builder
    {
        return $query->with(['mediable' => fn (MorphTo $morph) => $morph->constrain([
            Line::class => fn ($q) => $q->select(['id', 'slug', 'name']),
            Station::class => fn ($q) => $q->select(['id', 'slug', 'name']),
            Material::class => fn ($q) => $q->select(['id', 'slug', 'name']),
            LineEvent::class => fn ($q) => $q->select(['id']),
        ])]);
    }
}
