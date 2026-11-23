<?php

namespace App\Models\Scopes;

use App\Enums\AccidentKind;
use App\Models\Accident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Accidents que VFF publie (pages, API REST, GraphQL, serveur MCP, carte, fiches de ligne).
 *
 * Les heurts de personnes (AccidentKind::SOURCED_ONLY) ne sont montrés que s'ils reposent sur une enquête
 * officielle (BEA-TT) ou sur un contexte sourcé relu (décision du 22/11/2026) ; les autres restent en base pour
 * le recoupement des sources. L'import les relit sans ce filtre (`withoutGlobalScopes()`).
 *
 * Une fiche alias (`alias_of_id`) décrit le même accident qu'une autre, déjà publiée : elle est gardée
 * avec ses sources (vff:verify-accidents) mais n'est jamais montrée.
 *
 * @implements Scope<Accident>
 */
final class PublicAccidents implements Scope
{
    /**
     * @param  Builder<covariant Accident>  $builder
     * @param  Accident  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereRaw(AccidentKind::publishableSql($model->getTable()))
            ->whereNull($model->qualifyColumn('alias_of_id'));
    }
}
