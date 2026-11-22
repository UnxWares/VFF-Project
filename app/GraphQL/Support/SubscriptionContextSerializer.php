<?php

namespace App\GraphQL\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

/**
 * Contexte d'un abonné GraphQL tel qu'il est gardé dans Valkey (un jour, config/lighthouse.php),
 * à la place du sérialiseur de Lighthouse, qui y écrivait toute la requête : en-tête Cookie (cookie
 * de session, donc de quoi prendre la session), adresse IP, navigateur, et les attributs de la
 * requête, dont le compte du jeton d'API avec toutes ses colonnes (courriel, empreinte du mot de
 * passe, jeton).
 *
 * Seuls restent l'hôte et le schéma de l'appel (pour les adresses absolues) et le numéro du compte.
 * À la diffusion, le compte est relu dans la base : un compte supprimé entre-temps donne un abonné
 * anonyme, au lieu d'une erreur qui arrêtait la diffusion pour tous les abonnés du même sujet.
 */
final readonly class SubscriptionContextSerializer implements SerializesContext
{
    /** Variables de serveur conservées : rien qui désigne la personne ni sa session. */
    private const SERVER_KEYS = ['HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'HTTPS', 'REQUEST_METHOD'];

    public function __construct(private CreatesContext $createsContext) {}

    public function serialize(GraphQLContext $context): string
    {
        $request = $context->request();
        $user = $context->user();

        return serialize([
            'server' => $request === null ? null : array_intersect_key($request->server->all(), array_flip(self::SERVER_KEYS)),
            'user' => $user instanceof Model ? ['class' => $user::class, 'id' => $user->getKey()] : null,
        ]);
    }

    public function unserialize(string $context): GraphQLContext
    {
        $data = unserialize($context, ['allowed_classes' => false]);
        $server = is_array($data) && is_array($data['server'] ?? null) ? $data['server'] : null;
        $user = is_array($data) && is_array($data['user'] ?? null) ? $data['user'] : null;

        if ($server === null) {
            return $this->createsContext->generate(null);
        }
        $request = new Request(server: array_intersect_key($server, array_flip(self::SERVER_KEYS)));
        $account = self::restore($user);
        $request->setUserResolver(fn (): ?Authenticatable => $account);

        return $this->createsContext->generate($request);
    }

    /** @param  array<mixed>|null  $user */
    private static function restore(?array $user): ?Authenticatable
    {
        $class = $user['class'] ?? null;
        $id = $user['id'] ?? null;
        if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Authenticatable::class)
            || (! is_int($id) && ! is_string($id))) {
            return null;
        }
        $model = $class::query()->find($id);

        return $model instanceof Authenticatable ? $model : null;
    }
}
