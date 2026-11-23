<?php

namespace App\Mcp\Tools;

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
Parcours : tracé de référence de la ligne entière, toutes époques (lignes fermées et déposées comprises), assemblé le long de l'artère principale, du plus petit PK officiel vers le plus grand ; les morceaux séparés par un trou sont gardés (`gaps` : la courbe s'y interrompt), les embranchements suivent bout à bout (`branches`). `points` = [distance_m, altitude_m, pk_m] : distance le long du parcours depuis son origine, trous compris ; altitude en mètres (NGF-IGN69), null sans valeur (hors de France, trou) ; PK estimé en mètres, calé sur les PK officiels des gares (null sans calage ou sur un embranchement).
Avec `pk_m` : `altitude_at_pk` donne l'altitude interpolée à ce PK (altitude_m, distance_m, interpolated, sources, structure, lat, lng), null si elle est inconnue ; lat et lng sont null, avec le motif dans position_withheld (licence_not_open, unknown_source), quand le tracé à cet endroit vient d'une source non redistribuable.
`summary` : min_altitude_m, max_altitude_m, ascent_m et descent_m (dénivelés cumulés dans le sens du parcours : altitudes du terrain moyennées sur 1 km, puis variations de moins de 3 m ignorées ; méthode dans climb_method), max_grade_permil (déclivité la plus forte en ‰, pente moyenne sur 1 km mesurée sur la voie) entre max_grade_from_m et max_grade_to_m, max_grade_direction (up = rampe dans le sens du parcours, down = pente) ; ces quatre valeurs sont null quand la déclivité n'est pas publiée, avec le motif dans max_grade_withheld (terrain_only = altitude du terrain seulement, tunnel, interpolated, rack = ligne à crémaillère ; motif nul aussi quand aucune portion de 1 km n'est exploitable). `rack` : null pour une ligne à adhérence ; sinon system (strub, abt, riggenbach), extent (full, partial, null si la source ne le dit pas), max_grade_permil (déclivité la plus forte publiée par la source, en ‰), braking_only (crémaillère utilisée pour le freinage seulement) et source (Wikipédia, permalien de la révision lue, CC BY-SA 4.0). `sourced_grade` : quand la déclivité mesurée n'est pas publiée (terrain_only, tunnel, interpolated) et qu'une source écrite la donne, max_grade_permil (déclivité la plus forte selon la source, en ‰, sans position), covered_share (part de la ligne décrite par la source, 0 à 1, null pour toute la ligne) et source (provider, title, url, license, attribution) : `wikipedia` (infobox ou texte de l'article, permalien de la révision lue, CC BY-SA 4.0, attribution Wikipédia) ou `sncf` (segment le plus raide du jeu « Caractéristique des voies et déclivité », ligne couverte à 10 % au moins, ODbL 1.0, attribution SNCF Réseau à citer avec la valeur) ; null sinon, et jamais à la place d'une mesure publiée. `sourced_grade_compared` : quand la déclivité mesurée est publiée et qu'une source écrite en donne une, même forme que `sourced_grade` plus difference_permil (mesure moins source, en ‰ ; positif : la mesure est plus forte), servie à côté de la mesure, jamais à sa place ; null sinon. `trace_holes` : vrais trous du tracé de référence (sans les trous conventionnels devant les embranchements) : count, length_m (à vol d'oiseau entre les bords, la voie disparue était au moins aussi longue), share (part des trous dans le parcours, 0 à 1) et ranges (bornes en distances) ; les chiffres de summary ne portent que sur la partie connue. `terrain_ranges` : parties où l'altitude est celle du terrain (RGE ALTI). `track_estimate_ranges` : parties à l'altitude de la voie estimée d'après le terrain (plateforme repérée sur une coupe en travers du RGE ALTI, lissée le long de la ligne) ; `track_estimate` : méthode et erreur mesurée contre le MNT LiDAR HD de l'IGN selon l'écart entre le tracé et la voie (accuracy : trace_offset_m 5, 10 ou 20, median_m, p90_m, p99_m, under_1m_share, under_2m_share, grade_1km_p90_permil, grade_1km_within_1_permil_share ; bridges), null sans point estimé. Valeurs indicatives : quelques ‰ d'écart avec la déclivité officielle sont courants.
`structures` (tunnel, bridge), `gaps` (trous du tracé), `stations` (toutes les gares de la ligne, fermées comprises : slug, name, distance_m, altitude_m, pk_m, status, offset_m), `altitude_sources` (nombre de points par origine : bdtopo = altitude de la voie, rge_alti = terrain, rge_track = voie estimée d'après le terrain, interpolated, missing).
Source et licence (bloc `attribution`) : IGN, BD TOPO et RGE ALTI, Licence Ouverte 2.0 ; citez « IGN ». Profil pas encore calculé pour la ligne : erreur `profile_not_computed`.
Avec `pk_from_m` et/ou `pk_to_m` (bornes incluses, en mètres) : `samples` donne le référentiel point par point entre ces PK, comme l'API REST GET /api/v1/lines/{line}/profile/points (seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, branch, lat, lng, position_withheld), allégé par `max_points` ; les points sans PK sont exclus.
Exemples : {"line": "rfn-896000", "max_points": 100} ; {"line": "rfn-405000", "pk_m": 45764} ; {"line": "rfn-405000", "pk_from_m": 45000, "pk_to_m": 46000, "max_points": 50}.
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
            'pk_m' => ['sometimes', 'integer', 'min:-100000', 'max:5000000'],
            'pk_from_m' => ['sometimes', 'integer', 'min:-100000', 'max:5000000'],
            'pk_to_m' => ['sometimes', 'integer', 'min:-100000', 'max:5000000', 'gte:pk_from_m'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-896000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "896000").'),
            'max_points' => $schema->integer()->min(10)->max(2000)->default(200)
                ->description('Nombre de points au plus, 10 à 2 000 (200 par défaut) ; les deux bouts, le point le plus bas et le plus haut sont toujours gardés.'),
            'pk_m' => $schema->integer()->min(-100000)->max(5000000)
                ->description('PK en mètres (facultatif) : ajoute `altitude_at_pk`, l\'altitude de la ligne à ce PK.'),
            'pk_from_m' => $schema->integer()->min(-100000)->max(5000000)
                ->description('PK de début en mètres, borne incluse (facultatif) : ajoute `samples`, le référentiel point par point entre `pk_from_m` et `pk_to_m`, allégé à `max_points`.'),
            'pk_to_m' => $schema->integer()->min(-100000)->max(5000000)
                ->description('PK de fin en mètres, borne incluse (au moins `pk_from_m`) : voir `pk_from_m`.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $profiles = app(LineProfiles::class);
        $model = $profiles->find($line);
        if ($model === null) {
            return $this->fail('profile_not_computed', ['line' => $line->name]);
        }
        $profile = $profiles->present($model, (int) ($input['max_points'] ?? 200));
        unset($profile['provenance']);
        if (isset($input['pk_m'])) {
            $profile['altitude_at_pk'] = $profiles->altitudeAtPk($model, (int) $input['pk_m']);
        }

        if (isset($input['pk_from_m']) || isset($input['pk_to_m'])) {
            $profile['samples'] = $profiles->detailedPoints($model, $input['pk_from_m'] ?? null, $input['pk_to_m'] ?? null, (int) ($input['max_points'] ?? 200));
        }

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], 'profile' => $profile],
            self::providers($profile),
            $lang,
            self::url('/lines/'.$line->slug.'#profile'),
            ['profile.structures', 'profile.samples'],
        );
    }

    /**
     * Fournisseurs à citer : l'IGN pour les altitudes, plus la source de la fiche de crémaillère
     * (Wikipédia) et celle de la déclivité sourcée (Wikipédia ou SNCF Réseau).
     *
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    private static function providers(array $profile): array
    {
        $providers = ['ign'];
        if ($profile['rack'] !== null) {
            $providers[] = 'wikipedia';
        }
        foreach (['sourced_grade', 'sourced_grade_compared'] as $key) {
            if ($profile[$key] !== null) {
                $providers[] = $profile[$key]['source']['provider'];
            }
        }

        return array_values(array_unique($providers));
    }
}
