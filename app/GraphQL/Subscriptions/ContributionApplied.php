<?php

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

/**
 * `contributionApplied` : publications de contributions, ouvert à tous (même sans compte), filtrable
 * par fiche visée (`resource`, `slug`). Même contenu que le canal public `contributions`.
 */
final class ContributionApplied extends ContributionSubscription
{
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        $target = self::payload($root)['target'] ?? null;
        $resource = $subscriber->args['resource'] ?? null;
        $slug = $subscriber->args['slug'] ?? null;
        if ($resource === null && $slug === null) {
            return true;
        }
        if (! is_array($target)) {
            return false;
        }

        return ($resource === null || $target['resource'] === $resource)
            && ($slug === null || $target['slug'] === $slug);
    }
}
