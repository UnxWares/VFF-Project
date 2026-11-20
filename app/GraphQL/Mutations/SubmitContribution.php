<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Exceptions\ContributionApiError;
use App\GraphQL\Support\ContributionApiAccess;
use App\GraphQL\Support\ContributionPresenter;
use App\Models\Era;
use App\Models\Material;
use App\Models\Station;
use App\Services\Contributions\ContributionLimits;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\EventContributions;
use App\Services\Contributions\LineMaterialContributions;
use App\Services\Contributions\MaterialSeriesContributions;
use App\Services\Contributions\MediaContributions;
use App\Services\TraceContributionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Mutation `submitContribution` : la même proposition que sur le site (modification d'une portion
 * par PK, tracé dessiné, événement daté, image, matériel sur une ligne, série de matériel), mêmes
 * règles de saisie, même enregistrement
 * (ContributionSubmissions) et même annonce aux validateurs. Erreurs de saisie en anglais, comme le
 * reste de l'API, avec le chemin du champ (`input.segment.pk_end_m`) dans `extensions.validation`.
 * Une image déposée arrive en requête multipart (scalaire `Upload`, UploadedFile). Une série compte
 * aussi dans la limite des séries du site (ContributionLimits::SERIES, 10 par minute et par compte).
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
     * @param  array{input: array<string, array<string, mixed>|null>}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $author = $this->access->contributor($context->request());

        return $this->withLocale('en', function () use ($author, $args): array {
            $input = array_filter($args['input'], fn ($value) => $value !== null);
            if (count($input) !== 1) {
                throw ValidationException::withMessages([
                    'input' => 'Provide exactly one of "segment", "trace", "event", "media", "line_material" or "series".',
                ]);
            }

            $kind = array_key_first($input);
            $prefix = "input.$kind.";
            // Une série fait appeler Wikidata par le serveur : même limite que le formulaire du site, même compteur.
            if ($kind === 'series' && ($retryAfter = ContributionLimits::hit(ContributionLimits::SERIES, $author)) !== null) {
                throw ContributionApiError::tooManySeries($retryAfter);
            }
            try {
                $data = $input[$kind];
                $contribution = match ($kind) {
                    'segment' => $this->submissions->submitSegment($author, Validator::make(
                        $data, ContributionSubmissions::segmentRules(), ContributionSubmissions::segmentMessages(),
                    )->validate()),
                    'trace' => $this->submissions->submitTrace($author, Validator::make(
                        $data, TraceContributionService::rules(), TraceContributionService::messages(),
                    )->validate()),
                    'event' => $this->submissions->submitEvent($author, Validator::make(
                        $data, EventContributions::rules(), EventContributions::messages(),
                    )->validate()),
                    'media' => $this->submissions->submitMedia($author, Validator::make(
                        $data, MediaContributions::rules(), MediaContributions::messages(),
                    )->validate()),
                    'line_material' => $this->submissions->submitLineMaterial($author, Validator::make(
                        $data, LineMaterialContributions::rules(), LineMaterialContributions::messages(),
                    )->validate()),
                    default => $this->submissions->submitMaterialSeries($author, Validator::make(
                        $data, MaterialSeriesContributions::rules(), MaterialSeriesContributions::messages(),
                    )->validate()),
                };
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(self::prefixed($e->errors(), $prefix));
            } catch (ModelNotFoundException $e) {
                // Ligne, gare ou ère inconnue (le site répond 404) : une erreur de saisie dans l'API.
                throw ValidationException::withMessages(match (true) {
                    $e->getModel() === Era::class => [$prefix.'era_year' => 'Unknown era: use a decade from 1830 to 2030.'],
                    $e->getModel() === Station::class => [$prefix.'station_slug' => 'Unknown station.'],
                    $e->getModel() === Material::class => [$prefix.'material_slug' => 'Unknown rolling stock series.'],
                    default => [$prefix.($kind === 'segment' ? 'line_code' : 'line_slug') => 'Unknown line.'],
                });
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
