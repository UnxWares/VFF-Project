<?php

namespace App\Mcp\Support;

use App\Services\Accidents\Context\AccidentContextView;
use App\Support\EventText;
use App\Support\SourceRights;
use Illuminate\Support\Facades\DB;

/**
 * Présentation d'un accident pour les outils MCP : le tableau de la ressource REST
 * (AccidentResource, AccidentDetailResource) avec le nom, le résumé et les libellés dans la seule
 * langue demandée (la ressource REST donne les trois), la date rédigée (`when`), l'adresse de la fiche sur le site public (`url`, pas celle du serveur local) et les points à
 * vérifier expliqués dans cette langue. Rien n'est ajouté ni estimé : mêmes champs, mêmes valeurs.
 */
final class AccidentView
{
    /**
     * @param  array<string, mixed>  $data  tableau résolu de la ressource REST
     * @return array<string, mixed>
     */
    public static function present(array $data, string $lang): array
    {
        $name = (array) ($data['name'] ?? []);
        $summary = (array) ($data['summary'] ?? []);
        $data['name'] = $name[$lang] ?? $name['fr'] ?? $data['slug'];
        $data['summary'] = $summary[$lang] ?? $summary['fr'] ?? null;
        $data['kind_label'] = (string) trans('accidents.kinds.'.$data['kind'], [], $lang);
        $data['url'] = config('mcp.vff.public_url').'/accidents/'.$data['slug'];
        $data['when'] = EventText::isoDate((string) $data['date'], (string) $data['date_precision'], $lang);
        if (isset($data['review']['notes'])) {
            $data['review']['notes'] = array_map(fn (array $n): array => [
                'label' => (string) trans('accidents.review.'.$n['code'], [], $lang),
            ] + $n, $data['review']['notes']);
        }

        // Contexte sourcé dans la seule langue demandée, sans citation (décision Q22).
        if (isset($data['context']) && is_array($data['context'])) {
            $data['context'] = AccidentContextView::forMcp($data['context'], $lang);
        }

        return $data;
    }

    /**
     * Fournisseurs des sources lues pour ces accidents (Wikidata, Wikipédia, BEA-TT) et des documents et images de leur contexte, pour l'attribution.
     *
     * @param  list<int>  $accidentIds
     * @return list<string>
     */
    public static function providers(array $accidentIds): array
    {
        $rights = new SourceRights;
        $context = DB::table('accident_references')->whereIn('accident_id', $accidentIds)->distinct()->pluck('provider')
            ->map(fn ($p) => $rights->providerFor('accident_references', (string) $p))
            ->merge(DB::table('accident_images')->whereIn('accident_id', $accidentIds)->exists() ? ['commons'] : []);

        return DB::table('accident_sources')->whereIn('accident_id', $accidentIds)
            ->distinct()->orderBy('provider')->pluck('provider')->map(fn ($p) => (string) $p)
            ->merge($context)->filter()->unique()->values()->all();
    }
}
