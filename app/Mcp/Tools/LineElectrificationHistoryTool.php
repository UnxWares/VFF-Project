<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_electrification_history')]
#[Title("Histoire de l'électrification d'une ligne")]
#[Description(<<<'TXT'
Histoire de l'électrification d'une ligne : tranches de PK électrifiées aujourd'hui avec leur tension, et mises sous tension, conversions de tension et désélectrifications datées. Mêmes données que l'API REST GET /api/v1/lines/{line}/electrification-history, sans les phrases de l'article : les liens vers la révision lue suffisent.
`ranges` : tranches de PK (pk_start_m, pk_end_m en mètres dans la numérotation de SNCF Réseau, pk_start_label, pk_end_label en km+mmm, length_m, voltage_v en volts, system : dc continu, ac alternatif, third_rail troisième rail, unknown), dans l'ordre des PK, tranches voisines de même tension réunies. Source SNCF Réseau Open Data, jeu « liste-des-lignes-electrifiees » (ODbL, mention SNCF Réseau) : réseau au jour même. Liste vide pour une ligne hors du réseau de SNCF Réseau.
`summary` : line_electrified, line_voltage_v (état de la ligne dans VFF), electrified_m, by_voltage (voltage_v, system, length_m, la plus longue d'abord).
`history` : faits datés relevés dans les chronologies des articles Wikipédia de la ligne et dans Wikidata (kind : electrification mise sous tension d'une portion, conversion ré-électrification ou changement de tension, deelectrification dépose de la caténaire ; voltage_v et system seulement si la phrase les cite, jamais déduits ; date et date_precision day, month, year ; reliability high ou medium ; scope whole_line, section, unspecified ; section from, to, pk_start_m, pk_end_m ; source provider, url permalien). Faits sûrs seulement : travaux, projets, fourchettes de dates et phrases ambiguës sont écartés ; un fait absent ne veut pas dire que la portion n'a pas été électrifiée à une autre date.
Exemples : {"line": "rfn-830000"} ; {"line": "420000", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineElectrificationHistoryTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-830000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "830000").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        // Sans la phrase source : aucun extrait de Wikipédia dans le MCP, les liens suffisent.
        $data = app(LineElectrificationHistory::class)->forLine($line, withExcerpt: false);
        array_walk($data['history'], function (array &$e): void {
            unset($e['source']['excerpt']);
        });

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], ...$data],
            ['sncf', 'wikipedia', 'wikidata'],
            $lang,
            self::url('/lines/'.$line->slug.'#electrification'),
            ['ranges', 'history'],
        );
    }
}
