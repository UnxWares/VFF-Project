<?php

declare(strict_types=1);

namespace App\GraphQL\Support;

use GraphQL\Error\Error;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ErrorHandler;

/**
 * Refus d'une requête trop coûteuse ou trop profonde (`lighthouse.security`) : remplace le message
 * brut de graphql-php par un message qui dit quoi changer, avec un code stable et les deux nombres
 * dans `extensions` :
 *   - `query_too_complex` : `extensions.complexity` (coût calculé), `extensions.max_complexity` ;
 *   - `query_too_deep` : `extensions.depth` (profondeur sous la racine), `extensions.max_depth`.
 *
 * Langue : anglais, comme les autres erreurs de l'API (ApiLocale), ou français et allemand quand
 * l'en-tête Accept-Language les demande. Textes dans lang/<langue>/graphql.php.
 */
final readonly class QueryLimitErrorHandler implements ErrorHandler
{
    /** Langues des messages, la première par défaut. */
    private const array LOCALES = ['en', 'fr', 'de'];

    public function __construct(private Request $request) {}

    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null || $error->getPrevious() !== null) {
            return $next($error);
        }

        $message = $error->getMessage();
        foreach ($this->limits() as $code => [$pattern, $valueKey, $maxKey]) {
            if (preg_match($pattern, $message, $match) !== 1) {
                continue;
            }
            [$max, $value] = [(int) $match[1], (int) $match[2]];
            $locale = $this->request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0];

            return $next(new Error(
                __("graphql.limits.{$code}", ['value' => $value, 'max' => $max], $locale),
                $error->getNodes(),
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                null,
                ['code' => $code, $valueKey => $value, $maxKey => $max],
            ));
        }

        return $next($error);
    }

    /**
     * Motifs tirés des messages de graphql-php eux-mêmes : ils suivent la bibliothèque si son texte change.
     *
     * @return array<string, array{string, string, string}>
     */
    private function limits(): array
    {
        $pattern = static fn (string $sample): string => '/^'.str_replace(['111111', '222222'], '(\d+)', preg_quote($sample, '/')).'$/';

        return [
            'query_too_complex' => [$pattern(QueryComplexity::maxQueryComplexityErrorMessage(111111, 222222)), 'complexity', 'max_complexity'],
            'query_too_deep' => [$pattern(QueryDepth::maxQueryDepthErrorMessage(111111, 222222)), 'depth', 'max_depth'],
        ];
    }
}
