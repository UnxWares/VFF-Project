<?php

namespace App\Mcp\Tools;

use App\Enums\AccidentKind;
use App\Enums\AccidentReviewStatus;
use App\Http\Resources\AccidentResource;
use App\Mcp\Support\AccidentView;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Rules\PaginationCursor;
use App\Services\Api\AccidentQuery;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('accidents')]
#[Title('Accidents et événements graves du rail')]
#[Description(<<<'TXT'
Accidents et événements graves du rail français (collisions, déraillements, passages à niveau, incendies, attentats, dérives, incidents graves), du plus ancien (1842) à aujourd'hui, filtrables par ligne, période, nature, département et bilan. Mêmes données et mêmes filtres que l'API REST GET /api/v1/accidents ; fiche complète avec sources, lignes rattachées et points à vérifier : get_accident.
Chaque accident : `slug` (pour get_accident), `name` et `summary` (résumé rédigé par VFF, jamais recopié d'une source) dans la langue demandée, `kind` et `kind_label` (collision, derailment, level_crossing, fire, explosion, structure_failure, attack, war_action, person_struck, runaway, incident, other), `date` ISO AAAA-MM-JJ à lire avec `date_precision` (day = date exacte ; month = mois connu, jour à 01 ; year = année seule) et `when` (date rédigée), `time` (HH:MM, rare), `place` (`commune`, `commune_insee`, `department_code`, `department`, `lat` et `lng` en WGS 84, `precision` de la position : exact, level_crossing, station, commune = lieu exact inconnu), `line` (ligne VFF retenue : slug, code, name, `confidence` high, medium ou low du rattachement), `deaths` et `injuries` (nombres, null = aucune source consultée ne les donne ; 0 = aucun), `toll_source` (bea-tt, wikidata, wikipedia ou manual : fiche rédigée à la main), `review_status`, `url`. Aucune victime n'est nommée.
Prudence : `review_status` = "to_review" (à vérifier) signale un point incertain (une seule source pour la date ou le lieu, bilans ou dates qui diffèrent selon les sources, lieu connu à la commune près, ligne incertaine) ; présentez-le comme tel, jamais comme sûr, et renvoyez à get_accident pour le détail (`review.notes`). Le bilan retenu est celui de l'enquête officielle du BEA-TT, sinon de Wikidata, sinon de Wikipédia.
Sources et licences (bloc `attribution`, un fournisseur par source lue) : Wikidata (CC0), Wikipédia (faits seulement, aucun texte repris : toute citation de l'article est sous CC BY-SA 4.0, avec lien vers l'article), BEA-TT (Licence Ouverte 2.0, mention du BEA-TT et de la date de mise à jour du bilan). Les accidents urbains (métro, tramway) ne sont pas importés.
Filtres : `q` (nom ou commune, sans accents), `from`/`to` (années, 1800 à 2100, incluses), `line` (slug, ex. "rfn-570000"), `department` (code INSEE : "91", "2A", "974"), `region` (code INSEE de la région : "11"), `station` (slug d'une gare : accidents dont elle est la gare la plus proche du lieu), `kind`, `review` (ok ou to_review), `min_deaths` (bilan connu d'au moins ce nombre de morts), `has_line` (true : rattachés à une ligne VFF). Tri `sort` : date (défaut), -date, deaths, -deaths ; bilan inconnu en dernier.
Pagination par curseur, comme les autres outils de liste : `limit` accidents par appel (1 à 50, 20 par défaut), puis `next_cursor` dans `cursor` avec les mêmes filtres et le même tri tant que `has_more` vaut true ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend au premier accident non renvoyé.
Exemples : {"line": "rfn-570000", "from": 1950, "to": 2020} ; {"kind": "derailment", "min_deaths": 10, "sort": "-deaths", "limit": 5} ; {"department": "29", "review": "ok"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class AccidentsTool extends VffTool
{
    /** Ancien slug dans `line` ou `station` : filtre sous le slug actuel, dit dans `redirected`. */
    protected const SLUG_FILTERS = true;

    protected function rules(): array
    {
        return AccidentQuery::rules() + [
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(AccidentQuery::CURSOR_COLUMNS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->max(100)->description('Texte cherché dans le nom de l\'accident (toutes langues) et la commune, sans accents ni capitales (ex. "bretigny").'),
            'from' => $schema->integer()->min(1800)->max(2100)->description('Accidents survenus à partir de cette année, incluse (ex. 1950).'),
            'to' => $schema->integer()->min(1800)->max(2100)->description('Accidents survenus jusqu\'à cette année, incluse, au moins égale à from (ex. 2020).'),
            'line' => $schema->string()->max(255)->description('Slug d\'une ligne (ex. "rfn-570000"), tel que renvoyé par search : accidents rattachés à cette ligne, principale ou secondaire. Ligne inconnue : erreur.'),
            'department' => $schema->string()->max(3)->description('Code INSEE du département actuel du lieu, en majuscules ("91", "2A", "974").'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())->description('Code INSEE de la région du lieu ("11" Île-de-France ; liste avec les noms : outil list_departments).'),
            'station' => $schema->string()->max(255)->description('Slug d\'une gare (ex. "bretigny"), tel que renvoyé par search : accidents dont elle est la gare la plus proche du lieu (10 km au plus). Gare inconnue : erreur.'),
            'kind' => $schema->string()->enum(array_map(fn (AccidentKind $k) => $k->value, AccidentKind::cases()))->description('Nature de l\'accident.'),
            'review' => $schema->string()->enum(array_map(fn (AccidentReviewStatus $s) => $s->value, AccidentReviewStatus::cases()))->description('ok : date et lieu confirmés (enquête du BEA-TT ou deux sources indépendantes) ; to_review : fiches « à vérifier ».'),
            'min_deaths' => $schema->integer()->min(0)->description('Bilan d\'au moins ce nombre de morts ; exclut les bilans inconnus.'),
            'has_line' => $schema->boolean()->description('true : accidents rattachés à une ligne VFF ; false : sans ligne (lieu inconnu, réseau urbain).'),
            'sort' => $schema->string()->enum(array_keys(AccidentQuery::SORTS))->default('date')->description('Tri : date (du plus ancien), -date, deaths (bilan croissant), -deaths ; bilan inconnu en dernier.'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres et même tri).'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('Nombre d\'accidents par page, 1 à 50 (20 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = array_diff_key($input, array_flip(['cursor', 'limit', 'lang']));
        $page = app(AccidentQuery::class)->cursorQuery($filters)
            ->with(['lineLinks' => fn ($q) => $q->where('is_primary', true)->with('line:id,slug,code,name')])
            ->cursorPaginate((int) ($input['limit'] ?? 20), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $accidents = array_map(fn (array $a): array => AccidentView::present($a, $lang), AccidentResource::collection($page->items())->resolve());
        $payload = ['filters' => $filters];
        if (in_array(AccidentReviewStatus::ToReview->value, array_column($accidents, 'review_status'), true)) {
            $payload['review_note'] = trans('mcp.accidents.review_note', [], $lang);
        }

        return $this->respondPage(
            $payload, 'accidents', $page, $accidents,
            AccidentView::providers(array_map(fn ($a) => (int) $a->id, $page->items())),
            $lang,
            self::url('/accidents'),
        );
    }
}
