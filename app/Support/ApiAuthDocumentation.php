<?php

namespace App\Support;

use App\Enums\ApiAccessProfile;
use App\Http\Controllers\Api\ErrorExamples;
use App\Http\Middleware\AuthenticateApiToken;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Section « Authentification et quotas » de la spécification OpenAPI (Scalar /docs/api, Swagger UI) :
 *
 *   - schéma de sécurité `bearer` (jeton personnel Sanctum) FACULTATIF : la sécurité globale est
 *     `[{}, {"bearer": []}]`, un appel sans jeton reste valable ;
 *   - en-têtes `X-RateLimit-Limit` et `X-RateLimit-Remaining` sur chaque réponse 2xx, `Retry-After`
 *     en plus sur la réponse 429, ajoutée aux opérations qui ne la décrivent pas encore ;
 *   - réponses 401 (jeton invalide ou révoqué) et 403 (compte suspendu) sur chaque opération, avec
 *     leur corps d'exemple (AuthenticateApiToken).
 *
 * Les limites affichées sont celles de config/api.php (ApiAccessProfile).
 */
final class ApiAuthDocumentation implements DocumentTransformer
{
    public const SCHEME = 'bearer';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        // Limites de l'introduction (config/scramble.php, « Authentification et quotas »).
        $document->info->description = strtr($document->info->description, [
            '{limit.anonymous}' => self::number(ApiAccessProfile::Anonymous->perMinute()),
            '{limit.member}' => self::number(ApiAccessProfile::Member->perMinute()),
            '{limit.partner}' => self::number(ApiAccessProfile::Partner->perMinute()),
        ]);
        $document->components->addSecurityScheme(self::SCHEME, SecurityScheme::http('bearer', '<numéro>|vff_…')
            ->as(self::SCHEME)
            ->setDescription(self::schemeDescription()));
        // Sécurité facultative : l'exigence vide `{}` admet l'appel anonyme.
        $document->security = [new SecurityRequirement([]), new SecurityRequirement([self::SCHEME => []])];

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $responses = $operation->responses ?? [];
                foreach ($responses as $response) {
                    if (! $response instanceof Response) {
                        continue;
                    }
                    $code = (int) $response->code;
                    if ($code >= 200 && $code < 300) {
                        self::addLimitHeaders($response);
                    } elseif ($code === 429) {
                        self::addLimitHeaders($response);
                        self::addRetryAfter($response);
                    }
                }

                $codes = array_map(fn ($r): int => $r instanceof Response ? (int) $r->code : 0, $responses);
                if (! in_array(429, $codes, true)) {
                    $tooMany = self::error(429, ErrorExamples::TOO_MANY_REQUESTS, null, ErrorExamples::TOO_MANY_REQUESTS_BODY['message']);
                    self::addLimitHeaders($tooMany);
                    self::addRetryAfter($tooMany);
                    $operation->addResponse($tooMany);
                }
                if (! in_array(401, $codes, true)) {
                    $operation->addResponse(self::error(
                        401,
                        'Jeton invalide, révoqué ou expiré (en-tête `Authorization: Bearer …`). L\'appel n\'est jamais compté comme anonyme : retirez l\'en-tête pour utiliser l\'API sans jeton.',
                        'invalid_token',
                        AuthenticateApiToken::INVALID,
                    ));
                }
                if (! in_array(403, $codes, true)) {
                    $operation->addResponse(self::error(
                        403,
                        'Jeton d\'un compte suspendu (`account_suspended`, avec `suspended_until`, null si la suspension n\'a pas de fin) : refusé jusqu\'à la fin de la suspension.',
                        'account_suspended',
                        AuthenticateApiToken::SUSPENDED,
                    ));
                }
            }
        }
    }

    /** Nombre écrit à la française (« 1 200 »). */
    private static function number(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }

    private static function schemeDescription(): string
    {
        return sprintf(
            "Jeton personnel **facultatif**, créé depuis le profil d'un compte (/profile, section « Jetons d'accès à l'API »), en lecture seule. Sans jeton : %s requêtes par minute et par adresse IP. Avec un jeton : %s par minute pour le compte (%s pour un compte partenaire), tous ses jetons partageant le même compteur, commun à REST, GraphQL et MCP. Envoi : `Authorization: Bearer <jeton>`.",
            self::number(ApiAccessProfile::Anonymous->perMinute()),
            self::number(ApiAccessProfile::Member->perMinute()),
            self::number(ApiAccessProfile::Partner->perMinute()),
        );
    }

    private static function addLimitHeaders(Response $response): void
    {
        $response->addHeader('X-RateLimit-Limit', new Header(
            description: sprintf(
                'Requêtes permises par minute pour l\'appelant : %s sans jeton, %s avec un jeton, %s avec le jeton d\'un compte partenaire.',
                self::number(ApiAccessProfile::Anonymous->perMinute()),
                self::number(ApiAccessProfile::Member->perMinute()),
                self::number(ApiAccessProfile::Partner->perMinute()),
            ),
            schema: Schema::fromType(new IntegerType),
            example: ApiAccessProfile::Anonymous->perMinute(),
        ));
        $response->addHeader('X-RateLimit-Remaining', new Header(
            description: 'Requêtes restantes dans la minute en cours (compteur commun à REST, GraphQL et MCP).',
            schema: Schema::fromType(new IntegerType),
            example: ApiAccessProfile::Anonymous->perMinute() - 1,
        ));
    }

    private static function addRetryAfter(Response $response): void
    {
        $response->addHeader('Retry-After', new Header(
            description: 'Attente avant la prochaine requête acceptée, en secondes.',
            schema: Schema::fromType(new IntegerType),
            example: 42,
        ));
    }

    /** Corps d'erreur `{"message": …, "error": …}` ; sans `error` pour la réponse 429 de ThrottleRequests. */
    private static function error(int $status, string $description, ?string $code, string $message): Response
    {
        $body = new ObjectType;
        $body->addProperty('message', (new StringType)->setDescription('Message en anglais.')->example($message));
        $required = ['message'];
        if ($code !== null) {
            $body->addProperty('error', (new StringType)->setDescription('Code stable de l\'erreur.')->example($code));
            $required[] = 'error';
        }
        $body->setRequired($required);
        // Corps complet en exemple, comme les autres réponses d'erreur (ErrorExamples).
        $body->examples([$code === null ? ['message' => $message] : ['message' => $message, 'error' => $code]]);

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($body));
    }
}
