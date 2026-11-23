<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\Context\AccidentContextValidator;
use App\Support\SourceRights;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Faits d'un article de presse dans un fichier de contexte (`sources.<clé>.facts`, relus par vff:verify-accidents) :
 * seulement sur un article lu, date absolue ou jour relatif à un article daté au jour, commune INSEE, bilan entier,
 * bilan provisoire marqué `true` avec un bilan.
 */
class AccidentContextPressFactsTest extends TestCase
{
    public function test_well_formed_facts_are_accepted(): void
    {
        $this->assertSame([], $this->allErrors(['date' => '1956-06-14', 'commune_insee' => '51250', 'deaths' => 11]), 'la clé facts est connue et le fichier reste valide');
        $this->assertSame([], $this->factErrors(['date_relative' => 'previous_day', 'commune_insee' => '2A004', 'deaths' => 0, 'deaths_provisional' => true]));
        $this->assertSame([], $this->factErrors(['date_relative' => 'same_day']));
    }

    /** @param array<string, mixed> $facts */
    #[DataProvider('brokenFacts')]
    public function test_broken_facts_are_refused(mixed $facts, string $expected, string $kind = 'press', string $access = 'open', ?string $published = '1956-06-15'): void
    {
        $errors = $this->factErrors($facts, $kind, $access, $published);
        $this->assertCount(1, $errors, implode(' | ', $errors));
        $this->assertStringContainsString($expected, $errors[0]);
        $this->assertStringStartsWith('sources.journal.facts : ', $errors[0]);
    }

    /** @return array<string, list<mixed>> */
    public static function brokenFacts(): array
    {
        return [
            'not a press article' => [['deaths' => 1], 'seulement sur un article de presse lu', 'web'],
            'not read' => [['deaths' => 1], 'seulement sur un article de presse lu', 'press', 'paywall'],
            'empty' => [[], 'tableau non vide attendu'],
            'not an array' => ['11 morts', 'tableau non vide attendu'],
            'unknown key' => [['deaths' => 1, 'injuries' => 3], 'clé inconnue « injuries »'],
            'both dates' => [['date' => '1956-06-14', 'date_relative' => 'previous_day'], 'pas les deux'],
            'bad date' => [['date' => '1956-02-30'], 'date AAAA-MM-JJ attendue'],
            'month only' => [['date' => '1956-06'], 'date AAAA-MM-JJ attendue'],
            'bad relative day' => [['date_relative' => 'next_day'], 'date_relative same_day ou previous_day'],
            'relative day not a string' => [['date_relative' => 1], 'date_relative same_day ou previous_day'],
            'relative day, article dated to the month' => [['date_relative' => 'previous_day'], 'article daté au jour', 'press', 'open', '1956-06'],
            'relative day, undated article' => [['date_relative' => 'same_day'], 'article daté au jour', 'press', 'open', null],
            'bad commune' => [['commune_insee' => '5125'], 'commune_insee à 5 caractères'],
            'commune with a prefix' => [['commune_insee' => 'x51250'], 'commune_insee à 5 caractères'],
            'commune with a suffix' => [['commune_insee' => '512501'], 'commune_insee à 5 caractères'],
            'commune as a number' => [['commune_insee' => 51250], 'commune_insee à 5 caractères'],
            'deaths as text' => [['deaths' => '11'], 'deaths entier'],
            'negative deaths' => [['deaths' => -1], 'deaths entier'],
            'too many deaths' => [['deaths' => 10000], 'deaths entier'],
            'provisional false' => [['deaths' => 3, 'deaths_provisional' => false], 'deaths_provisional vaut true'],
            'provisional without deaths' => [['deaths_provisional' => true], 'deaths_provisional vaut true'],
        ];
    }

    public function test_fact_errors_add_to_the_other_errors_of_the_source(): void
    {
        $errors = array_values(array_filter($this->allErrors(['deaths' => '11', 'commune_insee' => 'x'], title: ''), fn (string $e): bool => str_starts_with($e, 'sources.journal')));

        $this->assertSame([
            'sources.journal : titre de 1 à 500 caractères attendu',
            'sources.journal.facts : commune_insee à 5 caractères attendue',
            'sources.journal.facts : deaths entier de 0 à 9999 attendu',
        ], $errors);
    }

    public function test_a_source_without_facts_stays_valid_and_the_boundaries_are_kept(): void
    {
        $this->assertSame([], $this->factErrors(null));
        $this->assertSame([], $this->factErrors(['deaths' => 9999]));
        $this->assertSame(['same_day' => 0, 'previous_day' => 1], AccidentContextValidator::RELATIVE_DAYS);
    }

    /**
     * Erreurs du validateur qui portent sur les faits du document « journal » d'un fichier par ailleurs valide.
     *
     * @return list<string>
     */
    private function factErrors(mixed $facts, string $kind = 'press', string $access = 'open', ?string $published = '1956-06-15'): array
    {
        return array_values(array_filter($this->allErrors($facts, $kind, $access, $published), fn (string $e): bool => str_contains($e, '.facts')));
    }

    /** @return list<string> */
    private function allErrors(mixed $facts, string $kind = 'press', string $access = 'open', ?string $published = '1956-06-15', string $title = 'Elf doden'): array
    {
        $source = ['provider' => 'delpher', 'kind' => $kind, 'reliability' => 'press', 'title' => $title, 'publisher' => 'Krant',
            'published' => $published, 'url' => 'https://resolver.kb.nl/resolve?urn=x', 'access' => $access, 'consulted_on' => '2026-11-23'];
        if ($facts !== null) {
            $source['facts'] = $facts;
        }
        $data = [
            'slug' => '1956-06-14-fismes',
            'checked_on' => '2026-11-23',
            'sources' => [
                'liste' => ['provider' => 'wikipedia', 'kind' => 'encyclopedia', 'reliability' => 'encyclopedia', 'title' => 'Liste',
                    'url' => 'https://fr.wikipedia.org/wiki/Liste', 'access' => 'open', 'consulted_on' => '2026-11-23'],
                'journal' => $source,
            ],
            'sections' => ['victims' => [['fr' => 'Onze morts.', 'en' => 'Eleven dead.', 'de' => 'Elf Tote.', 'sources' => ['liste']]]],
            'corrections' => [],
        ];
        $rights = new SourceRights(require __DIR__.'/../../../database/data/source_rights.php');

        return (new AccidentContextValidator($rights))->errors($data, '1956-06-14-fismes');
    }
}
