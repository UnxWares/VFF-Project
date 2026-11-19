<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\ContributionApiAccess;
use App\GraphQL\Support\ContributionPresenter;
use App\Models\Era;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\TraceContributionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Mutation `submitContribution` : la même proposition que sur le site (modification d'une portion
 * par PK, ou tracé dessiné), mêmes règles de saisie, même enregistrement (ContributionSubmissions)
 * et même annonce aux validateurs. Erreurs de saisie en anglais, comme le reste de l'API, avec le
 * chemin du champ (`input.segment.pk_end_m`) dans `extensions.validation`.
 */
final readonly class SubmitContribution
{
    use Localizable;

    public function __construct(
        private ContributionApiAccess $access,
        private ContributionSubmissions $submissions,
        private ContributionPresenter $presenter,
    ) {}

    /**
     * @param  array{input: array{segment?: array<string, mixed>|null, trace?: array<string, mixed>|null}}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $author = $this->access->contributor($context->request());

        return $this->withLocale('en', function () use ($author, $args): array {
            $input = array_filter($args['input'], fn ($value) => $value !== null);
            if (count($input) !== 1) {
                throw ValidationException::withMessages([
                    'input' => 'Provide exactly one of "segment" or "trace".',
                ]);
            }

            $kind = array_key_first($input);
            $prefix = "input.$kind.";
            try {
                $contribution = $kind === 'segment'
                    ? $this->submissions->submitSegment($author, Validator::make(
                        $input['segment'],
                        ContributionSubmissions::segmentRules(),
                        ContributionSubmissions::segmentMessages(),
                    )->validate())
                    : $this->submissions->submitTrace($author, Validator::make(
                        $input['trace'],
                        TraceContributionService::rules(),
                        TraceContributionService::messages(),
                    )->validate());
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(self::prefixed($e->errors(), $prefix));
            } catch (ModelNotFoundException $e) {
                // Ligne ou ère inconnue (le site répond 404) : une erreur de saisie dans l'API.
                throw ValidationException::withMessages($e->getModel() === Era::class
                    ? [$prefix.'era_year' => 'Unknown era: use a decade from 1830 to 2030.']
                    : [$prefix.($kind === 'segment' ? 'line_code' : 'line_slug') => 'Unknown line.']);
            }

            return $this->presenter->present($contribution);
        });
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private static function prefixed(array $errors, string $prefix): array
    {
        $out = [];
        foreach ($errors as $field => $messages) {
            $out[$prefix.$field] = $messages;
        }

        return $out;
    }
}
