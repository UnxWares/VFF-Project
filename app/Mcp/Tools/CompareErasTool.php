<?php

namespace App\Mcp\Tools;

use App\Enums\EraChange;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\EraComparison;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('compare_eras')]
#[Title('Comparer le réseau à deux époques')]
#[Description(<<<'TXT'
Ce qui a changé sur le réseau ferré français entre deux époques de la carte VFF, en kilomètres et en nombre de lignes : lignes ouvertes ou rouvertes, fermées (voie en place), déposées ou disparues, restées en service. Même résumé que l'API REST GET /api/v1/eras/{from}/compare/{to} et que la vue « Comparer » de la carte ; répond à « combien de kilomètres ont fermé entre 1930 et 1980 ? ».
Chaque époque est un instantané au 31/12 d'une décennie (1830 à 2020), et l'ère courante décrit le réseau d'aujourd'hui. Chaque année demandée est ramenée à l'ère qui la contient ou la précède (1937 donne 1930), comme network_at_era ; `from` et `to` donnent les ères réellement comparées, `requested_years` les années demandées, dans l'ordre où vous les avez données. Les deux années peuvent être données dans n'importe quel ordre : la comparaison va toujours de l'ère la plus ancienne (`from`) à la plus récente (`to`). Deux années qui tombent dans la même ère : erreur `same_era`.
Renvoie, dans `changes`, quatre rubriques `{km, lines}` : `opened` (en service à `to`, pas à `from`), `closed` (fermée à `to` voie en place, y compris voie de service, alors qu'elle ne l'était pas à `from`), `removed` (en service ou fermée à `from`, déposée ou disparue à `to`) et `kept` (en service aux deux dates) ; `km` = somme des tronçons concernés (voies parallèles d'une même ligne comprises), arrondie au kilomètre ; `lines` = nombre de lignes dont au moins un tronçon est concerné (une ligne fermée en partie compte dans `closed` et dans `kept`). `open_km` donne la longueur en service à chacune des deux ères (`from`, `to`). `labels` explique chaque rubrique dans la langue demandée.
Limites : les longueurs sont celles des tronçons de la carte, un ordre de grandeur et non le kilométrage officiel ; les tronçons sont appariés d'une ère à l'autre par ligne et par géométrie identique ; le résumé est en cache dix minutes. Pas de détail par ligne : pour une ligne précise, utilisez get_line (décennies, phases) ou line_events. `links.map` ouvre la comparaison sur la carte, `links.tiles` donne les tuiles vectorielles de l'API REST.
Exemples : {"from": 1930, "to": 1980} ; {"from": 1980, "to": 1937, "lang": "en"} (années dans le désordre, 1937 devient 1930).
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class CompareErasTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'from' => ['required', 'integer', 'min:1800', 'max:2100'],
            'to' => ['required', 'integer', 'min:1800', 'max:2100'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'from' => $schema->integer()->min(1800)->max(2100)->required()
                ->description('Première année comparée, de 1800 à 2100 (ex. 1930), ramenée à l\'ère de la carte qui la contient ou la précède.'),
            'to' => $schema->integer()->min(1800)->max(2100)->required()
                ->description('Seconde année comparée, de 1800 à 2100 (ex. 1980, ou l\'année en cours pour le réseau d\'aujourd\'hui). Peut précéder `from` : les deux sont remises dans l\'ordre chronologique.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $requested = [(int) $input['from'], (int) $input['to']];
        $eras = [];
        foreach ($requested as $year) {
            $era = Lookup::era($year);
            if ($era === null) {
                return $this->fail('era_not_found', ['year' => $year], ['available_eras' => Lookup::eraYears()]);
            }
            $eras[] = $era;
        }
        usort($eras, fn (object $a, object $b): int => $a->year <=> $b->year);
        [$from, $to] = $eras;
        if ($from->id === $to->id) {
            return $this->fail('same_era', ['year' => $from->year], ['available_eras' => Lookup::eraYears()]);
        }

        $comparison = app(EraComparison::class);
        $report = $comparison->compare($from->id, $from->year, $to->id, $to->year, (string) config('mcp.vff.public_url'));
        $providers = array_map(
            fn (?string $source): ?string => $this->attribution->providerFor('line_segments', $source),
            $comparison->sources($from->id, $to->id),
        );
        $labels = [];
        foreach (EraChange::cases() as $change) {
            $labels[$change->value] = (string) trans('mcp.compare.'.$change->value, [], $lang);
        }

        return $this->respond([
            'requested_years' => $requested,
            'from' => $report['from'],
            'to' => $report['to'],
            'changes' => $report['changes'],
            'open_km' => $report['open_km'],
            'labels' => $labels,
            // Liens de la carte et des tuiles seulement : l'API REST est décrite à part.
            'links' => ['map' => $report['links']['map'], 'tiles' => $report['links']['tiles']],
            'available_eras' => Lookup::eraYears(),
        ], $providers, $lang, $report['links']['map']);
    }
}
