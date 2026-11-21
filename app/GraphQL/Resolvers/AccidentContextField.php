<?php

namespace App\GraphQL\Resolvers;

use App\Models\Accident;
use App\Services\Accidents\Context\AccidentContextView;

/**
 * Champs du contexte sourcé d'un accident en GraphQL (`Accident.context` et ses types) : même contenu que
 * l'API REST (`context` de la fiche), textes dans la langue demandée.
 */
final class AccidentContextField
{
    private const LOCALES = ['fr', 'en', 'de'];

    public function __construct(private readonly AccidentContextView $view) {}

    /** @return array<string, mixed> */
    public function context(Accident $accident): array
    {
        $accident->loadMissing(AccidentContextView::RELATIONS);

        return $this->view->for($accident);
    }

    /**
     * Texte d'un énoncé, légende d'une image ou raison d'une correction, dans la langue demandée.
     *
     * @param  array<string, mixed>  $root
     * @param  array{lang?: string}  $args
     */
    public function localized(array $root, array $args, mixed $context, mixed $info): string
    {
        $field = $info->fieldName === 'text' ? 'text' : ($info->fieldName === 'caption' ? 'caption' : 'reason');
        $texts = (array) ($root[$field] ?? []);
        $lang = in_array($args['lang'] ?? 'fr', self::LOCALES, true) ? ($args['lang'] ?? 'fr') : 'fr';

        return (string) ($texts[$lang] ?? $texts['fr'] ?? '');
    }

    /**
     * Valeur d'une correction (avant ou après) en texte : date `AAAA-MM-JJ`, heure `HH:MM` ou nombre.
     *
     * @param  array<string, mixed>  $root
     */
    public function correctionValue(array $root, array $args, mixed $context, mixed $info): ?string
    {
        $v = $root[$info->fieldName] ?? null;

        return $v !== null ? (string) $v : null;
    }
}
