<?php

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

/**
 * `myContributionChanged` : l'état des contributions du compte connecté (proposée, approuvée,
 * publiée, refusée). Le sujet de l'abonnement porte le numéro du compte : un abonné ne reçoit que les
 * siennes.
 */
final class MyContributionChanged extends ContributionSubscription
{
    private const TOPIC = 'MY_CONTRIBUTION_CHANGED';

    protected function permits(Subscriber $subscriber): bool
    {
        return $this->account($subscriber) !== null;
    }

    public function encodeTopic(Subscriber $subscriber, string $fieldName): string
    {
        return self::TOPIC.':'.($this->account($subscriber)->id ?? 0);
    }

    public function decodeTopic(string $fieldName, mixed $root): string
    {
        $authorId = is_array($root) ? ($root['author_id'] ?? null) : null;

        return self::TOPIC.':'.(is_int($authorId) ? $authorId : 0);
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        // Le sujet a déjà trié les abonnés ; un compte supprimé (auteur nul) n'a plus d'abonné.
        return is_array($root) && is_int($root['author_id'] ?? null);
    }
}
