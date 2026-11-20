<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Profiles\LineProfiles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_profile')]
#[Title("Profil en long d'une ligne")]
#[Description(<<<'TXT'
Profil en long d'une ligne (altitude le long du tracé, comme dans un livret de ligne), même contenu que l'API REST GET /api/v1/lines/{line}/profile, allégé par défaut à 200 points.
Parcours : tracé de l'ère courante assemblé le long de l'artère principale, de la première gare (plus petit PK) vers la dernière. `points` = [distance_m, altitude_m] : distance le long du parcours depuis son origine (pas le PK officiel, donné pour chaque gare dans `stations[].pk_m`), altitude en mètres (NGF-IGN69), null sans valeur (hors de France, lacune).
`summary` : min_altitude_m, max_altitude_m, ascent_m et descent_m (dénivelés cumulés dans le sens du parcours, variations de moins de 3 m ignorées), max_grade_permil (déclivité la plus forte en ‰, pente moyenne sur 1 km) entre max_grade_from_m et max_grade_to_m, max_grade_direction (up = rampe dans le sens du parcours, down = pente). Valeurs indicatives : quelques ‰ d'écart avec la déclivité officielle sont courants.
`structures` (tunnel, bridge), `gaps` (lacunes du tracé), `stations` (slug, name, distance_m, altitude_m, pk_m), `altitude_sources` (nombre de points par origine : bdtopo = altitude de la voie, rge_alti = terrain, interpolated, missing).
Source et licence (bloc `attribution`) : IGN, BD TOPO et RGE ALTI, Licence Ouverte 2.0 ; citez « IGN ». Profil pas encore calculé pour la ligne : erreur `profile_not_computed`.
Exemple : {"line": "rfn-896000", "max_points": 100}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineProfileTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'max_points' => ['sometimes', 'integer', 'min:10', 'max:2000'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-896000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "896000").'),
            'max_points' => $schema->integer()->min(10)->max(2000)->default(200)
                ->description('Nombre de points au plus, 10 à 2 000 (200 par défaut) ; les deux bouts, le point le plus bas et le plus haut sont toujours gardés.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $profile = app(LineProfiles::class)->forLine($line, (int) ($input['max_points'] ?? 200));
        if ($profile === null) {
            return $this->fail('profile_not_computed', ['line' => $line->name]);
        }
        unset($profile['provenance']);

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], 'profile' => $profile],
            ['ign'],
            $lang,
            self::url('/lines/'.$line->slug.'#profile'),
            ['profile.structures'],
        );
    }
}
