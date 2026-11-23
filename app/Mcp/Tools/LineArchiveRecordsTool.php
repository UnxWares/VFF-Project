<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Models\LineArchiveRecord;
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

#[Name('line_archive_records')]
#[Title("Notices des fonds d'archives d'une ligne (Archives SNCF)")]
#[Description(<<<'TXT'
Notices d'inventaire des fonds d'archives dont le titre désigne une ligne. Seule source à ce jour : le jeu de données « Archives SNCF » du Service Archives Documentation du groupe SNCF (dossiers d'archives, photographies de la Médiathèque SNCF, films), consultables sur archives.sncf.com. Même contenu que l'API REST GET /api/v1/lines/{line}/archive-records, du plus ancien au plus récent (sans date en dernier).
Rattachement par le même score que les documents de Gallica (outil `line_archives`), calculé sur le titre, ou sur la description quand le titre ne nomme aucune ligne (raison `read_in_description`, 0 point : une seule ligne décrite en voie ferrée « de X à Y » dès le début du texte, sans les mentions en passant « par », « via », « sur la ligne de ») : `rail_route` 4, `route` 3 ou `two_places` 1 (plus `only_line_places` 1), `ends` 2, `rail_word` 2 ou `rail_hint` 1, `date_in_life` 2, `date_project` 1, `date_after_closure` 1, `date_unknown` 0, `doc_type` 1 ; seuil 9 sur 11. Un nom de compagnie (« Compagnie du chemin de fer de Paris à Orléans ») ne compte pas comme une ligne. Les notices sur des personnes (cheminots blessés et tués, contentieux, personnel) ne sont jamais rattachées. Une liste vide ne veut pas dire que les archives de la SNCF n'ont rien sur la ligne : seule une partie du fonds est numérisée et décrite dans ce jeu de données.
Réponse : `records[]` avec `title`, `type` (document : dossier d'archives ; image : photographie, affiche ; video : film), `reference` (numéro du dossier), `theme`, `sub_theme`, `holder` (service versant), `box`, `rights`, `date` (`label`, `year`, `year_to`, `exact`, `precision` : day, month, year, range, unknown), `url` (lien à donner au lecteur), `match` (score, raisons, lieux reconnus) et `fetched_at`.
Droits : jeu de données sous ODbL, mention « SNCF, Service Archives Documentation ». VFF ne sert ni description, ni image, ni document : renvoyez le lecteur vers `url`.
Exemples : {"line": "rfn-430000"} ; {"line": "830000", "type": "image", "lang": "de"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineArchiveRecordsTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(LineArchiveRecord::TYPES)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-430000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "430000").'),
            'type' => $schema->string()->enum(LineArchiveRecord::TYPES)
                ->description('Ne garder qu\'un type de notice : document (dossier d\'archives), image (photographie, affiche) ou video (film). Tous par défaut.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $archives = app(LineArchives::class);
        $records = $archives->records($line, isset($input['type']) && $input['type'] !== '' ? (string) $input['type'] : null);

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total' => $records->count(),
                'records' => $records->map(fn (LineArchiveRecord $r): array => $archives->recordToApi($r))->values()->all(),
                'provenance' => $archives->recordsProvenance() + ['mention' => LineArchives::RECORDS_ATTRIBUTION],
                'method' => ['threshold' => ArchiveMatcher::THRESHOLD, 'max_score' => ArchiveMatcher::MAX_SCORE, 'points' => ArchiveMatcher::POINTS],
            ],
            ['sncf-archives'],
            $lang,
            self::url('/lines/'.$line->slug.'#archives'),
            ['records'],
        );
    }
}
