<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\LineDossier;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_line')]
#[Title("Fiche d'une ligne")]
#[Description(<<<'TXT'
Fiche complète d'une ligne de chemin de fer française : identité (nom, code SNCF Réseau, réseau principal/secondaire/militaire/particulier, grande vitesse), état aujourd'hui (km par catégorie : grande vitesse, voyageurs et fret, fret seul, train touristique, fermée, voie déposée…), longueurs en km (tracé VFF à son plus grand développement, part en service aujourd'hui, formes SNCF), vitesse maximale (km/h), écartement (mm), électrification (état none/marginal/partial/full, km, %, tensions en volts avec leur longueur), signalisation (cantonnement : BAL, BAPR, TVM, ETCS…, contrôle de vitesse KVB, chacun avec ses km, source SNCF Réseau ; plus `history`, les mises en service datées lues dans l'article Wikipédia de la ligne, faits sûrs seulement, avec leur source, à ne pas confondre avec l'état actuel), faits encyclopédiques rapportés à leur source (Wikidata, SNCF, calcul VFF), phases datées (chaque date où une portion ouvre, ferme, change de trafic ou perd sa voie, avec la longueur concernée, l'événement qui la date et sa source ; « between » quand aucune source ne date le changement), état décennie par décennie (km en service), et chronologie datée : chaque événement avec date ISO et précision (day, month, year), portion concernée, texte, source, lien de la source et fournisseur.
Accepte le slug renvoyé par search (« rfn-420000 ») ou le code à 6 chiffres (« 420000 »). Réponse plafonnée en taille : réduisez max_events ou utilisez line_events pour une période.
Exemple : {"line": "rfn-610000", "max_events": 50}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineSheetTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'include_events' => ['sometimes', 'boolean'],
            'max_events' => ['sometimes', 'integer', 'min:1', 'max:300'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-420000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "420000").'),
            'include_events' => $schema->boolean()->default(true)
                ->description('Inclure la chronologie détaillée (événements datés et sourcés). false pour une fiche plus courte.'),
            'max_events' => $schema->integer()->min(1)->max(300)->default(100)
                ->description('Nombre maximal d\'événements de la chronologie, 1 à 300 (100 par défaut), dans l\'ordre chronologique ; `events_omitted` donne le reste.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $dossier = app(LineDossier::class)->build($line, $lang, (bool) ($input['include_events'] ?? true), (int) ($input['max_events'] ?? 100));

        return $this->respond(['line' => $dossier['line']], $dossier['providers'], $lang, self::url('/lines/'.$line->slug),
            ['line.events', 'line.phases', 'line.decades', 'line.facts']);
    }
}
