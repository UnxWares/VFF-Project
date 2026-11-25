<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Map\WhatWasHere;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('what_was_here')]
#[Title('Ce qui passait ici en telle année')]
#[Description(<<<'TXT'
« Qu'est-ce qui passait ici en 1930 ? » : autour d'un point (latitude, longitude WGS 84) et pour une année, les lignes de chemin de fer présentes à l'époque avec leur état et leurs caractéristiques à cet endroit, les gares proches avec leur état, la signalisation en vigueur, les séries de matériel roulant datées sur ces lignes cette année-là, les événements datés de l'année et les accidents survenus à proximité. Tout est sourcé.
L'année est ramenée à l'ère de la carte qui la contient ou la précède (1937 donne 1930) pour l'état des lignes et des gares (`era`) ; les événements, le matériel, la signalisation et les accidents utilisent l'année exacte demandée (`year`). Sans `year` : aujourd'hui.
`lines` (la plus proche d'abord, au plus `max_lines`) : slug, name, code, `distance_m` (du point au tracé), `nearest_station` (gare de la ligne la plus proche du point à cette ère : slug, name, distance_m), `pk_m` (point kilométrique estimé au droit du point, null sans PK), `status` à l'ère (in_service, single_track, service_track, construction, abandoned = fermée voie en place, dismantled = déposée, razed), `traffic` (mixed, passenger, freight, unknown), `heritage` (train touristique, vélorail), `electrified` et `voltage_v`, `max_speed_kmh`, `gauge_mm` (nul quand seule une source à usage non commercial le donne), `geometry_withheld` (vrai quand le tracé à cet endroit vient d'une source non redistribuable : il est alors cherché autour du centre de la maille de 0,001 degré du point, dans 200 m au moins, `distance_m` est null et `pk_m` arrondi à 100 m, juste à 150 m près environ), `signalling_in_force` (mises en service datées en vigueur au 31/12 de l'année, faits sûrs seulement : system, role, date, date_precision, scope, section, source ; une liste vide ne prouve pas l'absence de signalisation), `materials` (séries dont une période de circulation datée par une source couvre l'année : slug, name, type, `period` = start_date, start_precision, end_date, end_precision, section ; `certainty` = dated_period (début et fin datés), open_end (fin non donnée par la source, bornée par le retrait de la série s'il est connu : la série n'y circulait pas forcément encore) ou open_start (début non donné, borné par la mise en service de la série) ; source, source_url), `events` (événements datés de l'année sur cette ligne, au plus 5 : id, date, precision, when, type, label, section, text, source, source_url, provider), `url`.
`stations` (au plus 10, la plus proche d'abord) : slug, name, city, `distance_m`, `status` à l'ère et `status_label`, `uncertain`, `url`. `accidents` (accidents publiés de l'année dans le rayon) : slug, name, date, when, kind, kind_label, deaths, injuries, review_status (to_review = à vérifier, ne pas présenter comme sûr), `distance_m`, url.
Les faits d'une source à usage non commercial ne sont jamais servis. Sources et licences : bloc `attribution`.
Exemples : {"lat": 45.6484, "lng": 0.1562, "year": 1930} ; {"lat": 48.39, "lng": -4.49, "year": 1975, "radius_m": 2000, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class WhatWasHereTool extends VffTool
{
    public function __construct(Attribution $attribution, private readonly WhatWasHere $here)
    {
        parent::__construct($attribution);
    }

    protected function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'radius_m' => ['sometimes', 'integer', 'min:50', 'max:5000'],
            'max_lines' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'lat' => $schema->number()->min(-90)->max(90)->required()->description('Latitude WGS 84 en degrés décimaux (ex. 45.6484).'),
            'lng' => $schema->number()->min(-180)->max(180)->required()->description('Longitude WGS 84 en degrés décimaux (ex. 0.1562).'),
            'year' => $schema->integer()->min(1800)->max(2100)->description('Année de 1800 à 2100 (ex. 1930) ; par défaut l\'année en cours.'),
            'radius_m' => $schema->integer()->min(50)->max(5000)->default(1000)->description('Rayon de recherche en mètres, 50 à 5 000 (1 000 par défaut).'),
            'max_lines' => $schema->integer()->min(1)->max(20)->default(8)->description('Nombre de lignes renvoyées au plus, 1 à 20 (8 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $result = $this->here->around($input, $lang);
        if ($result === null) {
            return $this->fail('era_not_found', ['year' => (int) ($input['year'] ?? date('Y'))], ['available_eras' => Lookup::eraYears()]);
        }

        return $this->respond($result['payload'], $result['providers'], $lang, $result['map'], ['lines', 'stations', 'accidents']);
    }
}
