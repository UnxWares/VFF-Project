<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingDating;
use App\Services\Signalling\SignallingHistoryState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('signalling_history')]
#[Title("Historique de la signalisation d'une ligne")]
#[Description(<<<'TXT'
Mises en service datées de signalisation d'une ligne (BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS, KVB, commande centralisée), relevées dans l'article Wikipédia de la ligne (texte, listes, tableaux, infobox) ou dans d'autres documents datés relevés par VFF (communiqués de SNCF Réseau, rapports du BEA-TT et de l'EPSF, Journal officiel, presse). Mêmes faits que l'API REST GET /api/v1/lines/{line}/signalling-history, sans les phrases de l'article : les liens vers la révision lue suffisent. C'est un historique à part : l'état actuel (SNCF Réseau) est dans get_line (`signalling.block_systems_km`), que ces faits ne corrigent pas. Faits sûrs seulement ; un fait absent ne veut pas dire qu'aucun système n'a été mis en service.
Chaque fait : `system` (BAL, BAPR, BM, CT, CC, TVM-300, TVM-430, TVM, ETCS_L1, ETCS_L2, ETCS, KVB), `role` (block : cantonnement ; cab : signalisation en cabine TVM et ETCS, cantonnement et contrôle à la fois, une LGV n'a pas de BAL ; speed_control : contrôle de vitesse, le KVB n'est jamais un cantonnement ; remote_control : commande centralisée CC, CCVU, CCR ou PCD, commande à distance des postes qui se combine avec un cantonnement sans le remplacer), `date` et `date_precision` (day, month, year : jour et mois manquants valent 01), `reliability` (high, ou medium : année seule ou date déduite de l'ouverture), `origin` (text, list, table, infobox, opening : TVM d'une LGV, en service dès l'ouverture de la ligne ; document : daté hors de l'article), `scope` (whole_line, section, unspecified), `section` (lieux tels qu'écrits `from` et `to` ; `location` : stations, placée par ses gares d'extrémité ; landmarks, une extrémité au moins est un point remarquable de la ligne (bifurcation, raccordement, origine, fin, frontière) ; article_pk, PK de l'article ; unlocated, non placée ; `pk_start_m`, `pk_end_m`, `from_station`, `to_station`), `source` (`provider` : wikipedia ou manual ; `title`, `url` permalien de la révision ou lien du premier document, `revision`, nul pour un document ; `verification` : official ou two_sources pour un document ; `documents` : publisher, title, url, published).
`dating` : ce que VFF sait de la date de mise en service du cantonnement de la ligne, calculé : `status` (`dated` : au moins une mise en service de cantonnement datée et sûre ; `undated` : ligne classique en service dont aucun document libre consulté ne date la signalisation ; `not_applicable` : ligne à grande vitesse, fermée ou touristique seule), `checked_at` (date du dernier passage sur les documents libres), `messages` (la mention pour `undated`, en fr, en et de ; nul sinon).
`corrections` : faits relevés que des documents contredisent, jamais dans `history` ni `in_force` (`system`, `date`, `date_precision`, `section`, `original` : provider, title, url ; `action` : neutralize ou replace ; `reason`, `quote`, `sources` : publisher, title, url, published) ; liste vide sinon.
Avec `year` (n'importe quelle année, ramenée à l'ère de la carte), la réponse ajoute `era` et `in_force` : les faits en vigueur à la fin de l'ère, ceux que dessine le calque « Signalisation » de la carte à cette époque. Un fait plus récent du même groupe (cantonnement, TVM, ETCS, KVB) qui porte sur toute la ligne ou recouvre sa portion le remplace.
Licence : texte de Wikipédia sous CC BY-SA 4.0 (bloc `attribution`) ; seuls des faits sont repris. Faits datés par des documents : relevé de VFF, avec le lien de chaque document.
Exemples : {"line": "rfn-974000"} ; {"line": "905000", "year": 1990, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SignallingHistoryTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-974000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "974000").'),
            'year' => $schema->integer()->min(1800)->max(2100)
                ->description('Année quelconque de 1800 à 2100 (ex. 1990), ramenée à l\'ère de la carte : ajoute les faits en vigueur à la fin de cette ère. Facultatif.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $history = app(LineSignallingHistory::class);
        // Sans la phrase source : aucun extrait de Wikipédia dans le MCP (décision Q22), les liens suffisent.
        $facts = $history->forSheet($line);
        $payload = [
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'history' => $facts,
            // Faits que des documents contredisent : jamais dans `history`, comme l'API REST (clé `corrections`).
            'corrections' => $history->corrections($line),
            // Date connue de la mise en service du cantonnement, calculée : `status` (dated, undated, not_applicable), `checked_at`, `messages` (fr, en, de).
            'dating' => app(SignallingDating::class)->forLine($line),
        ];
        if (isset($input['year'])) {
            $era = Lookup::era((int) $input['year']);
            if ($era === null) {
                return $this->fail('era_not_found', ['year' => $input['year']], ['available_eras' => Lookup::eraYears()]);
            }
            $payload['era'] = ['year' => (int) $era->year, 'is_current' => (bool) $era->is_current];
            $payload['in_force'] = SignallingHistoryState::inForce($facts, SignallingHistoryState::until((int) $era->year, (bool) $era->is_current));
        }
        $payload['provenance'] = $history->provenance() + ['mention' => LineSignallingHistory::ATTRIBUTION];

        return $this->respond($payload, ['wikipedia'], $lang, self::url('/lines/'.$line->slug.'#tech'), ['history', 'in_force']);
    }
}
