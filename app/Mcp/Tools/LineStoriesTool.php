<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\LineStoryController;
use App\Http\Resources\LineEventResource;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Models\LineEvent;
use App\Services\Archives\LineArchives;
use App\Services\Stories\EventStories;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_stories')]
#[Title("Histoires d'événements d'une ligne (images de l'époque, archives)")]
#[Description(<<<'TXT'
Histoire d'un événement d'une ligne (ouverture, fermeture, électrification, dépose…) : l'événement avec sa source, les images publiées de la ligne datées à 5 ans au plus de son année, et les documents de Gallica de la même période. Même contenu que l'API REST GET /api/v1/lines/{line}/stories (sans `event` : liste des événements qui ont une histoire, avec le nombre d'images et de documents) et GET /api/v1/lines/{line}/stories/{event} (avec `event` : l'histoire complète).
Règles : événements concernés = ouvertures, réouvertures, fermetures, déclassements, déposes, reconversions, électrifications, modernisations, exploitation touristique, accidents (pas les concessions). Image retenue : toutes les années possibles de sa date à 5 ans au plus (date exacte, décennie, fourchette, ou année « vers » comptée à 2 ans près ; jamais une borne seule « avant » ou « après »), domaine public ou licence ouverte (pas les images montrées sur le site avec une simple autorisation) ; plus les images rattachées à l'événement. Seuil : 3 images et documents au moins, dont 2 images ; carrousel limité aux 24 images les plus proches de la date, dans l'ordre chronologique.
Chaque image et chaque document porte `gap_years` : écart signé avec l'année de l'événement (4 = quatre ans après, -2 = deux ans avant, 0 = la même année, null = image de l'événement sans date). Dites toujours cet écart : une photo de 1934 n'est pas une photo de l'ouverture de 1930. Quand `gap_exact` est faux, la date de l'image est approximative : `gap_years_min` et `gap_years_max` bornent l'écart (« environ trois ans avant »).
Droits : montrez chaque image avec son auteur, sa licence et sa source (`attribution`, `license`, `source_url`) ; citez la source de l'événement (`provenance`). Gallica : métadonnées de la BnF sous Licence Ouverte, jamais d'image ; renvoyez le lecteur vers `gallica_url`.
Exemples : {"line": "rfn-340000"} ; {"line": "340000", "event": 12793, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineStoriesTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'event' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-340000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "340000").'),
            'event' => $schema->integer()->min(1)
                ->description("Identifiant d'un événement de la ligne (`id` de get_line, line_events ou de la liste de cet outil). Sans lui : la liste des événements qui ont une histoire."),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $stories = app(EventStories::class);
        $lineData = ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name];

        if (! isset($input['event'])) {
            $available = $stories->available($line, true);
            $events = LineEvent::query()->whereIn('id', array_keys($available))->get()->keyBy('id');
            $items = [];
            foreach ($available as $id => $counts) {
                $event = $events->get($id);
                if ($event instanceof LineEvent) {
                    $items[] = ['event' => LineEventResource::make($event)->resolve()] + $counts
                        + ['vff_url' => self::url('/lines/'.$line->slug.'/stories/'.$id)];
                }
            }

            return $this->respond(
                ['line' => $lineData, 'rules' => LineStoryController::rules(), 'total' => count($items), 'stories' => $items],
                array_map(fn (array $i): ?string => $i['event']['provenance']['provider'] ?? null, $items),
                $lang,
                self::url('/lines/'.$line->slug.'#chronicle'),
                ['stories'],
            );
        }

        $event = LineEvent::query()->whereKey((int) $input['event'])->where('line_id', $line->id)->first();
        if ($event === null) {
            return $this->fail('event_not_on_line', ['event' => $input['event'], 'line' => $line->slug]);
        }
        $story = $stories->story($line, $event, true);
        if ($story === null) {
            return $this->fail('no_story', ['event' => $event->id], ['rules' => LineStoryController::rules()]);
        }

        return $this->respond(
            [
                'line' => $lineData,
                'event' => $story['event'],
                'window' => $story['window'],
                'images_total' => $story['images_total'],
                'images' => $story['images'],
                'documents' => $story['documents'],
                'stations' => $story['stations'],
                'rules' => LineStoryController::rules(),
                'archives_mention' => $story['documents'] === [] ? null : LineArchives::ATTRIBUTION,
            ],
            [
                $story['event']['provenance']['provider'] ?? null,
                ...array_map(fn (array $m): ?string => $this->attribution->providerFor('media', $m['provider'] ?? null), $story['images']),
                ...($story['documents'] === [] ? [] : ['gallica']),
            ],
            $lang,
            self::url('/lines/'.$line->slug.'/stories/'.$event->id),
            ['images', 'documents'],
        );
    }
}
