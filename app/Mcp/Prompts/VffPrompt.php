<?php

namespace App\Mcp\Prompts;

use App\Support\FrenchDepartments;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchQuery;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\Completable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * Socle des invites (prompts) du serveur MCP : un message prêt à envoyer, qui dit à l'assistant
 * quels outils appeler et comment citer les sources. Le message est rédigé dans la langue `lang`
 * (lang/{fr,en,de}/mcp.php, clé `prompts`) ; il ne contient aucune donnée de source (aucun extrait
 * de Wikipédia : décision Q22), seulement l'identifiant de l'objet trouvé.
 *
 * Complétion des arguments (`completion/complete`) : `lang`, `region` (codes INSEE et noms), `line`
 * et `station` (identifiants dont le début ou le nom correspond à la saisie, 20 au plus).
 */
abstract class VffPrompt extends Prompt implements Completable
{
    /** Langues des messages. */
    public const LANGS = ['fr', 'en', 'de'];

    /** Suggestions données pour une ligne ou une gare. */
    private const SUGGESTIONS = 20;

    protected static function langArgument(): Argument
    {
        return new Argument('lang', 'Langue du message et de la réponse attendue : fr (par défaut), en ou de.');
    }

    /** @return list<string> */
    protected static function langRule(): array
    {
        return ['nullable', 'string', 'in:'.implode(',', self::LANGS)];
    }

    /** Nom de la langue dans cette langue (« français », « English », « Deutsch »). */
    protected static function languageName(string $lang): string
    {
        return (string) trans('mcp.prompts.language', [], $lang);
    }

    /** @param  array<string, mixed>  $context */
    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return match ($argument) {
            'lang' => CompletionResponse::match(self::LANGS),
            'region' => CompletionResponse::match([...FrenchDepartments::regionCodes(), ...array_values(FrenchDepartments::REGIONS)]),
            'line' => CompletionResponse::result(self::slugsLike('lines', $value)),
            'station' => CompletionResponse::result(self::slugsLike('stations', $value)),
            default => CompletionResponse::empty(),
        };
    }

    /**
     * Identifiants dont le slug commence par la saisie, ou dont le nom la contient (accents et
     * capitales ignorés), les plus courts d'abord.
     *
     * @return list<string>
     */
    private static function slugsLike(string $table, string $value): array
    {
        $value = trim($value);
        if (mb_strlen($value) < 2) {
            return [];
        }
        $escaped = addcslashes($value, '\\%_');
        // Nom : recherche commune (App\Support\Search), tirets, accents et abréviations indifférents.
        [$match, $bindings] = SearchQuery::parse($value)->matchSql(SearchFields::keys($table === 'lines' ? 'line' : 'station', "{$table}.")[0], anywhere: true);

        return DB::table($table)
            ->where(fn ($q) => $q->where('slug', 'like', $escaped.'%')
                ->orWhereRaw("({$match})", $bindings))
            ->orderByRaw('length(slug), slug')
            ->limit(self::SUGGESTIONS)
            ->pluck('slug')
            ->map(fn ($slug): string => (string) $slug)
            ->all();
    }
}
