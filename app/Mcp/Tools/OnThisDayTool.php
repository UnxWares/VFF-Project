<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\OnThisDay;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('on_this_day')]
#[Title('Ce jour-là')]
#[Description(<<<'TXT'
Ce qui s'est passé un jour du calendrier les années précédentes, trié par année : ouvertures, réouvertures et fermetures de lignes et de gares, électrifications, mises en service de signalisation, démolitions de gares, accidents et jalons nationaux. Mêmes données que l'API REST GET /api/v1/on-this-day et la page /on-this-day.
Seuls des faits dont la date est connue AU JOUR sont servis (jamais une date à l'année ou au mois présentée comme un jour), avec au moins une source publiable ; les accidents « à vérifier » ne sont pas servis. Reprenez les accidents avec sobriété et respect pour les victimes.
Chaque fait : `id`, `subject` (line, station, accident, milestone), `kind` (milestone, opening, reopening, closure, electrification, signalling, demolition, accident), `traffic` (fermeture : passenger, freight, total), `year`, `years_ago`, `date` ISO (toujours au jour), `when` (date rédigée dans la langue demandée), `title` (ligne, gare, accident ou jalon), `system`, `detail` (portions, commune ou ville), `deaths` (accident : bilan connu), `path`, `line` ou `station` (slug, name) et `sources` (provider, name, url). `coverage` : nombre de jours de l'année qui ont au moins un fait.
Sans argument : aujourd'hui. Exemple : {"month": 11, "day": 23} ; {"month": 7, "day": 1, "types": ["closure"]}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class OnThisDayTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'types' => ['sometimes', 'array', 'max:10'],
            'types.*' => ['string', 'in:'.implode(',', OnThisDay::KINDS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'month' => $schema->integer()->min(1)->max(12)->description('Mois (1 à 12). Par défaut : aujourd\'hui (avec `day`).'),
            'day' => $schema->integer()->min(1)->max(31)->description('Jour du mois (29 février accepté). Par défaut : aujourd\'hui (avec `month`).'),
            'types' => $schema->array()->items($schema->string()->enum(OnThisDay::KINDS))->description('Types de faits retenus. Par défaut : tous.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $today = now();
        if (isset($input['month']) !== isset($input['day'])) {
            return $this->fail('invalid_day');
        }
        $month = (int) ($input['month'] ?? $today->month);
        $day = (int) ($input['day'] ?? $today->day);
        if (! checkdate($month, $day, 2024)) {
            return $this->fail('invalid_day');
        }
        $types = array_values(array_intersect(OnThisDay::KINDS, (array) ($input['types'] ?? [])));
        $service = app(OnThisDay::class);
        $data = $service->day($month, $day, $lang, $types);
        $coverage = $service->coverage();

        return $this->respond(
            ['day' => $data['day'], 'count' => $data['count'], 'filters' => ['types' => $types], 'facts' => $data['facts'],
                'coverage' => ['days' => $coverage['days'], 'days_with_facts' => $coverage['days_with_facts'], 'facts' => $coverage['facts']]],
            array_merge([], ...array_map(fn (array $f): array => array_column($f['sources'], 'provider'), $data['facts'])),
            $lang,
            self::url('/on-this-day/'.$data['day']),
            ['facts'],
        );
    }
}
