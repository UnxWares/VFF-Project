<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Speed\LineSpeeds;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_speeds')]
#[Title("Vitesses maximales d'une ligne")]
#[Description(<<<'TXT'
Vitesses maximales d'une ligne par points kilométriques et leurs changements datés. Mêmes données que l'API REST GET /api/v1/lines/{line}/speeds, sans les phrases de l'article : les liens vers la révision lue suffisent.
`ranges` : vitesse maximale nominale par tranche de PK (pk_start_m, pk_end_m en mètres dans la numérotation de SNCF Réseau, pk_start_label, pk_end_label en km+mmm, length_m, max_speed_kmh), dans l'ordre des PK, tranches voisines de même vitesse réunies. Source SNCF Réseau Open Data, jeu « vitesse-maximale-nominale-sur-ligne » (ODbL, mention SNCF Réseau) : réseau au jour même, seule l'ère courante (2030) est renseignée ; vitesse nominale, sans limitation temporaire ni vitesse propre à une catégorie de trains. Liste vide pour une ligne hors du réseau de SNCF Réseau (RATP, chemins de fer de la Corse, lignes touristiques).
`summary` : max_speed_kmh, min_speed_kmh, covered_m, by_speed (max_speed_kmh, length_m, la plus haute d'abord).
`history` : changements datés relevés dans l'article Wikipédia de la ligne (kind : raise relèvement, lower abaissement, opening vitesse à l'ouverture, state vitesse autorisée à cette date ; speed_kmh, previous_speed_kmh quand l'article la donne ; date et date_precision day, month, year ; reliability high ou medium ; scope whole_line, section, unspecified ; section from, to, pk_start_m, pk_end_m ; source provider, title, url permalien, revision). Faits sûrs seulement ; un fait absent ne veut pas dire que la vitesse n'a jamais changé.
Exemples : {"line": "rfn-536000"} ; {"line": "752000", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineSpeedsTool extends VffTool
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
                ->description('Ligne : slug (ex. "rfn-536000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "536000").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $era = Lookup::era(null);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => null], ['available_eras' => Lookup::eraYears()]);
        }
        // Sans la phrase source : aucun extrait de Wikipédia dans le MCP, les liens suffisent.
        $speeds = app(LineSpeeds::class)->forLine((int) $line->id, (int) $era->id, (int) $era->year, withExcerpt: false);
        array_walk($speeds['history'], function (array &$e): void {
            unset($e['source']['excerpt']);
        });

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], ...$speeds],
            ['sncf', 'wikipedia'],
            $lang,
            self::url('/lines/'.$line->slug.'#speeds'),
            ['ranges', 'history'],
        );
    }
}
