<?php

namespace App\Mcp\Support;

use App\Services\CatalogSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Socle des outils MCP de VFF (lecture seule) :
 *   - validation stricte : arguments inconnus refusés, puis règles de l'outil (rules()) ;
 *   - langue (`lang` : fr par défaut, en, de) appliquée pendant l'appel puis rétablie ;
 *   - réponse JSON compacte avec le lien de la fiche VFF et le bloc d'attribution des licences,
 *     plafonnée en taille (ResponseBudget) ;
 *   - erreurs métier (objet introuvable…) : réponse d'erreur JSON avec un code stable, le message
 *     dans la langue demandée et, s'il y a lieu, des suggestions.
 */
abstract class VffTool extends Tool
{
    public const LANGS = ['fr', 'en', 'de'];

    public function __construct(protected readonly Attribution $attribution) {}

    /**
     * Règles de validation Laravel des arguments propres à l'outil (hors `lang`).
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Schéma JSON des arguments propres à l'outil (hors `lang`).
     *
     * @return array<string, Type>
     */
    abstract protected function arguments(JsonSchema $schema): array;

    /**
     * Réponse de l'outil, la langue de l'appel étant appliquée.
     *
     * @param  array<string, mixed>  $input  arguments validés
     */
    abstract protected function answer(array $input, string $lang): Response;

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return $this->arguments($schema) + [
            'lang' => $schema->string()->enum(self::LANGS)->default('fr')
                ->description('Langue des libellés et des textes générés : fr (par défaut), en ou de. Les noms propres, les citations de sources et les identifiants restent tels quels.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $all = $request->all();
        $lang = in_array($all['lang'] ?? null, self::LANGS, true) ? $all['lang'] : 'fr';
        $previous = App::getLocale();
        App::setLocale($lang);
        try {
            $unknown = array_diff(array_keys($all), [...array_keys($this->rules()), 'lang']);
            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'arguments' => trans('mcp.errors.unknown_arguments', ['names' => implode(', ', $unknown)]),
                ]);
            }
            $input = $request->validate($this->rules() + ['lang' => ['sometimes', 'string', 'in:'.implode(',', self::LANGS)]]);

            return $this->answer($input, $lang);
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Réponse JSON : données, lien de la fiche, attribution ; listes raccourcies si trop longue.
     *
     * @param  array<string, mixed>  $payload
     * @param  iterable<?string>  $providers  fournisseurs des données renvoyées
     * @param  list<string>  $lists  listes que l'on peut raccourcir (chemins à points)
     */
    protected function respond(array $payload, iterable $providers, string $lang, ?string $url = null, array $lists = []): Response
    {
        $payload = $this->withEnvelope($payload, $this->attribution->block($providers, $lang), $url);
        $payload = ResponseBudget::fit($payload, $lists, self::maxBytes());
        if (isset($payload['truncated'])) {
            $payload['truncated_note'] = trans('mcp.truncated', [], $lang);
        }

        return Response::json($payload);
    }

    /**
     * Réponse d'un outil de liste paginé par curseur. Si la page dépasse le plafond de taille, elle est
     * raccourcie à la frontière d'un élément et `next_cursor` désigne alors le premier élément non
     * renvoyé : en suivant les curseurs, on ne perd aucun élément. Le raccourcissement est signalé
     * (`truncated`, `truncated_note`), et `count` donne le nombre d'éléments réellement renvoyés.
     *
     * L'attribution est construite une fois, et chaque essai mesure la réponse complète telle
     * qu'elle sera envoyée : la dernière mesurée est renvoyée sans autre passage.
     *
     * @param  array<string, mixed>  $payload  données de la réponse, sans la liste
     * @param  CursorPaginator<array-key, mixed>  $page  page brute (porte les colonnes de tri)
     * @param  list<array<string, mixed>>  $items  éléments présentés, dans l'ordre de la page
     * @param  iterable<?string>  $providers  fournisseurs des données renvoyées
     */
    protected function respondPage(array $payload, string $listKey, CursorPaginator $page, array $items, iterable $providers, string $lang, ?string $url = null): Response
    {
        $max = self::maxBytes();
        $attribution = $this->attribution->block($providers, $lang);
        $raw = array_values($page->items());
        $build = function (int $n) use ($payload, $listKey, $items, $raw, $page, $lang, $attribution, $url): array {
            $shortened = $n < count($items);

            return $this->withEnvelope($payload + [$listKey => array_slice($items, 0, $n)] + [
                'count' => $n,
                'has_more' => $shortened || $page->hasMorePages(),
                'next_cursor' => $shortened ? $page->getCursorForItem($raw[$n - 1])->encode() : $page->nextCursor()?->encode(),
            ] + ($shortened ? ['truncated' => [$listKey => count($items) - $n], 'truncated_note' => trans('mcp.truncated_page', [], $lang)] : []), $attribution, $url);
        };
        $kept = count($items);
        $response = $build($kept);
        // Retrait d'un quart des éléments à la fois, jamais en dessous d'un élément.
        while ($kept > 1 && ResponseBudget::size($response) > $max) {
            $kept -= max(1, intdiv($kept, 4));
            $response = $build($kept);
        }

        return Response::json($response);
    }

    /**
     * Lien de la fiche VFF (s'il y en a un) puis bloc d'attribution, en fin de réponse.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $attribution
     * @return array<string, mixed>
     */
    private function withEnvelope(array $payload, array $attribution, ?string $url): array
    {
        if ($url !== null) {
            $payload['vff_url'] = $url;
        }
        $payload['attribution'] = $attribution;

        return $payload;
    }

    private static function maxBytes(): int
    {
        return (int) config('mcp.vff.max_response_bytes', 48_000);
    }

    /**
     * Erreur métier : code stable, message traduit, détails éventuels.
     *
     * @param  array<string, mixed>  $params  paramètres du message
     * @param  array<string, mixed>  $details
     */
    protected function fail(string $code, array $params = [], array $details = []): Response
    {
        return Response::error((string) json_encode(
            ['error' => $code, 'message' => trans('mcp.errors.'.$code, array_map('strval', array_filter($params, 'is_scalar')))] + $details,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * Suggestions de la recherche pour un identifiant introuvable.
     *
     * @return list<array{slug: string, name: string}>
     */
    protected function suggestions(string $query, string $kind): array
    {
        $found = app(CatalogSearch::class)->search($query, $kind === 'lines' ? 5 : 0, $kind === 'stations' ? 5 : 0, $kind === 'materials' ? 5 : 0);

        return array_map(fn (array $r) => ['slug' => $r['slug'], 'name' => $r['name']], $found[$kind]);
    }

    protected static function url(string $path): string
    {
        return config('mcp.vff.public_url').$path;
    }
}
