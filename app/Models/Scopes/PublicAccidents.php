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
 * Les natures de AccidentKind::HIDDEN restent en base pour le recoupement des sources mais ne
 * sont jamais montrées : les heurts de personnes sont un sujet délicat, sans intérêt
 * encyclopédique pour les lignes (décision du 19/11/2026). L'import les relit sans ce filtre
 * (`withoutGlobalScopes()`).
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
        $builder->whereNotIn($model->qualifyColumn('kind'), AccidentKind::HIDDEN);
    }
}
