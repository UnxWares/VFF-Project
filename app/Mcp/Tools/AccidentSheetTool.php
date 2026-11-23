<?php

namespace App\Mcp\Tools;

use App\Enums\AccidentReviewStatus;
use App\Http\Resources\AccidentDetailResource;
use App\Mcp\Support\AccidentView;
use App\Mcp\Support\VffTool;
use App\Models\Accident;
use App\Services\Accidents\Context\AccidentContextView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_accident')]
#[Title("Fiche d'un accident ferroviaire")]
#[Description(<<<'TXT'
Fiche complète d'un accident ou événement grave du rail (même contenu que l'API REST GET /api/v1/accidents/{slug}) : tous les champs de la liste `accidents`, plus `lines` (lignes rattachées, la principale d'abord : `line`, `is_primary`, `era_year` = ère du tracé utilisé, `distance_m` du lieu au tracé, `pk_m` et `pk_label` = point kilométrique estimé en mètres et en « km+mmm », `section` = gares de part et d'autre du lieu, `method` = comment la ligne a été trouvée, `confidence` high/medium/low, `score` 0 à 1), `nearest_station` (slug, name, distance_m ; 10 km au plus), `trains` (nom de service, catégorie, numéro, matériel, exploitant, source), `toll_source_url` (lien qui appuie le bilan), `wikipedia` (articles par langue avec titre, adresse et révision lue), `sources` (chaque source lue : `provider`, `role`, `title`, `url`, `license`, `license_url`, `license_status`, `retrieved_at`, `facts` = faits lus dans cette source, pour vérifier le recoupement), `review` (`status` et `notes` : code stable, explication dans la langue demandée, valeurs en cause) et `provenance` (fournisseur de chaque fait retenu : date, nature, lieu, morts, blessés). Plus `context` (contexte sourcé, rédigé par VFF à partir de documents lus, dans la langue demandée) : `checked_on` (date de la recherche), `sections` (`circumstances` trains et horaires, `course` déroulement, `victims` bilan et secours, `causes` causes établies par l'enquête, `aftermath` procès et mesures de sécurité adoptées, `memory` monuments, `disagreements` contradictions entre sources ; chaque énoncé : `text` et `references` = clés des documents), `references` (documents : `key`, `provider`, `kind`, `reliability` official/scholarly/press/local_history/association/encyclopedia/forum, `title`, `publisher`, `published`, `locator`, `url`, `access` open/paywall/link_only, `consulted_on`, `license`), `images` (Commons : `image_url`, `author`, `license`, `caption`, `attribution`) et `corrections` (champ corrigé d'après ces documents, valeur d'avant, valeur retenue, raison). Aucune citation de source n'est servie ici ; listes vides si la fiche n'a pas de contexte.
Prudence : si `review.status` vaut "to_review" (à vérifier), au moins un point est incertain (bilans ou dates divergents, lieu connu à la commune près, ligne incertaine) : lisez `review.notes` et ne présentez pas la fiche comme certaine. La précision du lieu est dans `place.precision` ; avec "commune", la position est le centre de la commune et le lieu exact est inconnu. Un champ nul veut dire qu'aucune source consultée ne le donne : VFF n'estime aucune valeur. Aucune victime n'est nommée.
Sources et licences (bloc `attribution`) : Wikidata (CC0), Wikipédia (faits seulement, aucun texte repris ; toute citation de l'article est sous CC BY-SA 4.0, avec lien vers l'article), BEA-TT (Licence Ouverte 2.0 ; rapports d'enquête). Citez le fournisseur de chaque fait repris.
Identifiant : `accident` = slug renvoyé par accidents ou search (date puis commune, ex. "2013-07-12-bretigny-sur-orge"). Accident inconnu : erreur `accident_not_found`.
Exemple : {"accident": "2013-07-12-bretigny-sur-orge", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class AccidentSheetTool extends VffTool
{
    protected function rules(): array
    {
        return ['accident' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'accident' => $schema->string()->min(1)->max(255)->required()
                ->description('Slug de l\'accident (ex. "2013-07-12-bretigny-sur-orge"), tel que renvoyé par accidents.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        // Un doublon fusionné répond par la fiche retenue.
        $kept = Accident::redirectSlugFor($input['accident']);
        if ($kept !== null) {
            $this->noteRedirect('accident', $input['accident'], $kept);
        }
        $slug = $kept ?? $input['accident'];
        $accident = Accident::query()->withCoordinates()->where('slug', $slug)->first();
        if ($accident === null) {
            return $this->fail('accident_not_found', ['query' => $input['accident']]);
        }
        // Même chargement que la fiche REST (Api\AccidentController::show).
        $accident->load([
            'lineLinks' => fn ($q) => $q->with(['line:id,slug,code,name', 'sectionFrom:id,slug,name', 'sectionTo:id,slug,name']),
            'sources',
            'nearestStation:id,slug,name',
            ...AccidentContextView::RELATIONS,
        ]);
        $payload = ['accident' => AccidentView::present(AccidentDetailResource::make($accident)->resolve(), $lang)];
        if ($accident->review_status === AccidentReviewStatus::ToReview) {
            $payload['review_note'] = trans('mcp.accidents.review_note', [], $lang);
        }

        return $this->respond(
            $payload,
            AccidentView::providers([$accident->id]),
            $lang,
            self::url('/accidents/'.$accident->slug),
            ['accident.sources', 'accident.lines'],
        );
    }
}
