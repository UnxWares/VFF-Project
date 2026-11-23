<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Sncf\LineInfrastructure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_infrastructure')]
#[Title("Voies et radio sol-train d'une ligne")]
#[Description(<<<'TXT'
Nombre de voies et liaison radio sol-train (GSM-R…) d'une ligne du réseau ferré national aujourd'hui, par portion de points kilométriques, d'après SNCF Réseau ; même contenu que l'API REST GET /api/v1/lines/{line}/infrastructure. Réseau d'aujourd'hui seulement : ni ère, ni date de mise en service.
Réponse : `tracks` (jeu « regime-dexploitation-des-lignes ») : `summary` (nul sans portion au nombre établi) avec `tracks` (nombre de voies dominant en longueur : 1 voie unique ou navette, 2 double voie), `uniform` (vrai si ce nombre couvre 90 % de la longueur décrite ou plus, voie banalisée comprise ; faux pour une ligne qui mêle voie unique et double voie ou dont une part notable est banalisée : dire alors les longueurs de `length_m_by_tracks` et `banalised_length_m`), `dominant_share`, `banalised_length_m` ; `portions[]` (pk_start_label, pk_end_label, length_m, tracks, regime : libellé SNCF tel quel). La « voie banalisée » (chaque voie parcourue dans les deux sens) ne dit pas le nombre de voies : `tracks` nul, ne jamais en déduire 2. `radio` (jeu « lignes-equipees-de-liaison-radio-sol-train ») : `systems` (longueur par système), `gsm_r`, `gsm_r_length_m`, `portions[]` avec `system` : `gsm-r` (GSM-R), `gsm-gfu` (GSM d'opérateur public en groupe fermé d'utilisateurs), `rst` (radio sol-train analogique sans données), `rst-data` (analogique avec données), `rst-id` (analogique avec identification). Une ligne absente des jeux (fermée, hors réseau ferré national) renvoie des listes vides, pas une erreur.
Source et licence (champ `provenance` et bloc `attribution`) : SNCF Réseau, ODbL 1.0 ; la mention « SNCF Réseau » est à reproduire et toute base dérivée diffusée se partage sous la même licence.
Exemples : {"line": "rfn-579000"} ; {"line": "420000", "include_portions": false, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineInfrastructureTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'include_portions' => ['sometimes', 'boolean'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-579000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "579000").'),
            'include_portions' => $schema->boolean()->default(true)
                ->description('Garde le détail par portion de PK (vrai par défaut) ; faux : résumés seulement.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $infrastructure = app(LineInfrastructure::class);
        $block = $infrastructure->forLine((int) $line->id);
        if (! ($input['include_portions'] ?? true)) {
            unset($block['tracks']['portions'], $block['radio']['portions']);
        }

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                ...$block,
                'provenance' => $infrastructure->provenance() + ['mention' => LineInfrastructure::ATTRIBUTION],
            ],
            ['sncf'],
            $lang,
            self::url('/lines/'.$line->slug),
            ['tracks.portions', 'radio.portions'],
        );
    }
}
