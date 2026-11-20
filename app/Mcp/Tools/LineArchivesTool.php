<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Models\LineArchiveDocument;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\LineArchives;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_archives')]
#[Title("Documents d'archives d'une ligne (Gallica)")]
#[Description(<<<'TXT'
Documents numérisés en accès libre dans Gallica (Bibliothèque nationale de France) dont le titre parle d'une ligne : projets, plans et cartes du tracé, mémoires, guides, photographies. Même contenu que l'API REST GET /api/v1/lines/{line}/archives, du plus ancien au plus récent (sans date en dernier).
Le rattachement se fait sur le seul titre de la notice, par un score : `rail_route` 4 (« chemin de fer de X à Y » entre deux lieux de la ligne), `route` 3 (trajet « de X à Y ») ou `two_places` 1 (deux lieux nommés, plus `only_line_places` 1 si le titre ne nomme aucun lieu hors de la ligne), `ends` 2 (ce sont ses extrémités ; `secondary_mention` 0 si la ligne n'est citée que comme raccordement d'une autre), `rail_word` 2 ou `rail_hint` 1 (mot du rail), date `date_in_life` 2, `date_project` 1, `date_after_closure` 1, `date_unknown` 0 ou `date_doubtful` 0 (année imprimée dans le titre à plus de 40 ans de la date de la notice), `doc_type` 1. Seuil : 9 sur 11. Une ligne sans document retenu renvoie une liste vide, pas une erreur : cela ne veut pas dire que Gallica n'a rien sur elle.
Réponse : `documents[]` avec `title`, `type` (monograph, map, image, manuscript), `authors`, `date` (`label` imprimé dans la notice, `year`, `year_to`, `exact`, `precision` : day, month, year, circa, range, unknown), `shelfmark` (cote à la BnF), `gallica_url` (lien à donner au lecteur), `catalogue_url`, `match` (score, raisons, lieux reconnus) et `fetched_at`.
Droits : métadonnées des notices de la BnF sous Licence Ouverte, mention « Source : gallica.bnf.fr / BnF » avec la date de récupération. VFF ne sert aucune image ni aucun texte numérisé : renvoyez le lecteur vers `gallica_url`.
Exemples : {"line": "rfn-610000"} ; {"line": "340000", "type": "map", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineArchivesTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(LineArchiveDocument::TYPES)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-610000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "610000").'),
            'type' => $schema->string()->enum(LineArchiveDocument::TYPES)
                ->description('Ne garder qu\'un type de document : monograph (texte imprimé), map (carte, plan), image (photographie, estampe, affiche) ou manuscript. Tous par défaut.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $archives = app(LineArchives::class);
        $documents = $archives->documents($line, isset($input['type']) && $input['type'] !== '' ? (string) $input['type'] : null);

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total' => $documents->count(),
                'documents' => $documents->map(fn (LineArchiveDocument $d): array => $archives->toApi($d))->values()->all(),
                'provenance' => $archives->provenance() + ['mention' => LineArchives::ATTRIBUTION],
                'method' => ['threshold' => ArchiveMatcher::THRESHOLD, 'max_score' => ArchiveMatcher::MAX_SCORE, 'points' => ArchiveMatcher::POINTS],
            ],
            ['gallica'],
            $lang,
            self::url('/lines/'.$line->slug.'#archives'),
            ['documents'],
        );
    }
}
