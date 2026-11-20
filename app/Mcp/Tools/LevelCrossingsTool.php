<?php

namespace App\Mcp\Tools;

use App\Enums\LevelCrossingGroup;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Models\LevelCrossing;
use App\Models\Line;
use App\Rules\PaginationCursor;
use App\Services\LevelCrossings\LevelCrossingQuery;
use App\Services\LevelCrossings\LineLevelCrossings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('level_crossings')]
#[Title('Passages à niveau')]
#[Description(<<<'TXT'
Passages à niveau (PN) du réseau ferré national publiés par SNCF Réseau (environ 16 800), filtrables par ligne, groupe, classe, commune ou emprise. Mêmes données et mêmes filtres que l'API REST GET /api/v1/level-crossings. État actuel seulement : aucune date d'ouverture ou de suppression n'est connue, et un PN supprimé avant la publication du jeu n'y figure pas.
Chaque PN : `id` (identifiant SNCF Réseau), `label` (« PN 32 »), `class` (classe SNCF Réseau) et `class_label` (sa définition dans la langue demandée, null pour une classe hors de la liste officielle), `group` et `group_label`, `pk_m` et `pk_label` (point kilométrique officiel, en mètres et en notation km+mmm), `commune`, `crossed_way` (voie franchie, libellé brut : VC voie communale, CD 106-0 route départementale, CR chemin rural), `line` (slug, code, name), `lat` et `lng` en WGS 84.
Groupes : automatic (barrières automatiques, classes 17 à 19), guarded (gardés, 10 à 16), no_barrier (sans barrières, 20 et 21), pedestrian (piétons, 31 et 32), private (privés, 41 à 46), secondary (classe 0 : passage situé sur plusieurs lignes, compté sur une autre ; à exclure pour compter des passages).
Avec `line`, la réponse ajoute `summary` : nombre de PN de la ligne par groupe.
Licence : ODbL 1.0 (bloc `attribution`) ; citez « SNCF Réseau » et partagez sous la même licence une base dérivée publiquement utilisée.
Filtres : `line` (slug, ex. "rfn-70000"), `group`, `class` (0, 10 à 21, 31, 32, 41 à 46), `commune` (sans accents), `bbox` ("ouest,sud,est,nord" en degrés). Ordre : ligne puis PK. Pagination par curseur : `limit` (1 à 100, 50 par défaut), puis `next_cursor` dans `cursor` avec les mêmes filtres tant que `has_more` vaut true.
Exemples : {"line": "rfn-70000"} ; {"commune": "courthiezy"} ; {"group": "guarded", "bbox": "1.0,47.0,2.0,48.0", "limit": 20}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LevelCrossingsTool extends VffTool
{
    protected function rules(): array
    {
        return LevelCrossingQuery::rules() + [
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(LevelCrossingQuery::CURSOR_COLUMNS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->max(255)->description('Slug d\'une ligne (ex. "rfn-70000"), tel que renvoyé par search. Ligne inconnue : erreur.'),
            'group' => $schema->string()->enum(LevelCrossingGroup::ORDER)->description('Groupe de classes (voir la description de l\'outil).'),
            'class' => $schema->integer()->enum(LevelCrossingGroup::CLASSES)->description('Classe SNCF Réseau exacte.'),
            'commune' => $schema->string()->min(2)->max(100)->description('Partie du nom de la commune, sans accents ni capitales (ex. "courthiezy").'),
            'bbox' => $schema->string()->max(80)->description('Emprise "ouest,sud,est,nord" en degrés WGS 84 (ex. "3.5,49.0,3.7,49.1").'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres).'),
            'limit' => $schema->integer()->min(1)->max(100)->default(50)->description('Nombre de PN par page, 1 à 100 (50 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = array_diff_key($input, array_flip(['cursor', 'limit', 'lang']));
        $page = app(LevelCrossingQuery::class)->query($filters)
            ->cursorPaginate((int) ($input['limit'] ?? 50), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $items = array_map(fn (LevelCrossing $c): array => self::present($c, $lang), array_values($page->items()));
        $payload = ['filters' => $filters];
        $url = null;
        if (filled($filters['line'] ?? null)) {
            $line = Line::where('slug', $filters['line'])->firstOrFail();
            $crossings = app(LineLevelCrossings::class);
            $payload['summary'] = array_map(fn (array $s): array => $s + [
                'label' => trans("level_crossings.groups.{$s['group']}", [], $lang),
            ], $crossings->summary($crossings->list($line)));
            $url = self::url('/lines/'.$line->slug.'#level-crossings');
        }

        return $this->respondPage($payload, 'level_crossings', $page, $items, ['sncf'], $lang, $url);
    }

    /** @return array<string, mixed> */
    private static function present(LevelCrossing $c, string $lang): array
    {
        $group = $c->group;

        return [
            'id' => $c->id_if,
            'label' => $c->libelle,
            'class' => $c->class,
            'class_label' => $c->classLabel($lang),
            'group' => $group?->value,
            'group_label' => $group === null ? null : trans("level_crossings.groups.{$group->value}", [], $lang),
            'pk_m' => $c->pk_m,
            'pk_label' => LevelCrossing::pkLabel($c->pk_m),
            'commune' => $c->commune,
            'crossed_way' => $c->obstacle,
            'line' => $c->line ? ['slug' => $c->line->slug, 'code' => $c->line->code, 'name' => $c->line->name] : ['code' => $c->code_ligne],
            'lat' => round($c->lat, 6),
            'lng' => round($c->lon, 6),
        ];
    }
}
