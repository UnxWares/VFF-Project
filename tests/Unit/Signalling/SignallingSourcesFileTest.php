<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingHistoryState;
use App\Services\Signalling\SignallingSourcesFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fichier des mises en service datées par des documents (SignallingSourcesFile) : contrôle de chaque fait et
 * règle de vérification (une source officielle ou deux sites différents) ; faits de Wikipédia redits par un
 * document (SignallingHistoryState::withoutRestatements). Le vrai fichier du dépôt est contrôlé aussi.
 */
class SignallingSourcesFileTest extends TestCase
{
    /** @param  array<string, mixed>  $over */
    private static function entry(array $over = []): array
    {
        return array_merge([
            'line' => '561000', 'system' => 'BAPR', 'date' => '2010-06-13', 'scope' => 'section', 'from' => 'Arnage', 'to' => 'La Membrolle-sur-Choisille',
            'summary' => 'Le BAPR remplace le block manuel entre Arnage et La Membrolle-sur-Choisille.',
            'sources' => [['publisher' => 'SNCF Réseau', 'title' => 'Communiqué', 'url' => 'https://www.sncf-reseau.com/fr/a', 'official' => true]],
        ], $over);
    }

    public function test_a_valid_fact_is_flattened_with_its_role_precision_and_verification(): void
    {
        ['facts' => $facts, 'errors' => $errors] = SignallingSourcesFile::parse([self::entry()]);

        $this->assertSame([], $errors);
        $this->assertSame('block', $facts[0]['role']);
        $this->assertSame(['2010-06-13', 'day', 'high'], [$facts[0]['date'], $facts[0]['precision'], $facts[0]['confidence']]);
        $this->assertSame(['Arnage', 'La Membrolle-sur-Choisille'], [$facts[0]['section_from'], $facts[0]['section_to']]);
        $this->assertSame('official', $facts[0]['verification']);
        $this->assertNull($facts[0]['documents'][0]['published']);
    }

    public function test_dates_give_their_precision(): void
    {
        $this->assertSame(['2010-06-13', 'day'], SignallingSourcesFile::date('2010-06-13'));
        $this->assertSame(['2010-06-01', 'month'], SignallingSourcesFile::date('2010-06'));
        $this->assertSame(['2010-01-01', 'year'], SignallingSourcesFile::date('2010'));
    }

    public function test_two_documents_from_two_sites_are_enough_without_an_official_one(): void
    {
        $sources = [
            ['publisher' => 'Ouest-France', 'title' => 'A', 'url' => 'https://www.ouest-france.fr/a'],
            ['publisher' => 'Le Télégramme', 'title' => 'B', 'url' => 'https://www.letelegramme.fr/b'],
        ];
        ['facts' => $facts] = SignallingSourcesFile::parse([self::entry(['sources' => $sources])]);

        $this->assertSame('two_sources', $facts[0]['verification']);
        $this->assertSame('medium', SignallingSourcesFile::parse([self::entry(['date' => '2010', 'sources' => $sources])])['facts'][0]['confidence']);
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function refused(): iterable
    {
        $one = [['publisher' => 'Ouest-France', 'title' => 'A', 'url' => 'https://www.ouest-france.fr/a']];
        yield 'une seule source non officielle' => [['sources' => $one], 'ni source officielle'];
        yield 'deux pages du même site' => [['sources' => [...$one, ['publisher' => 'Ouest-France', 'title' => 'B', 'url' => 'https://www.ouest-france.fr/b']]], 'ni source officielle'];
        yield 'système inconnu' => [['system' => 'BAL-X'], 'système inconnu'];
        yield 'date illisible' => [['date' => '13/06/2010'], 'date illisible'];
        yield 'date impossible' => [['date' => '2010-02-30'], 'date impossible'];
        yield 'date antérieure au système' => [['system' => 'TVM-430', 'date' => '1950'], 'antérieure'];
        yield 'portion sans extrémité' => [['to' => ''], 'deux extrémités'];
        yield 'portée inconnue' => [['scope' => 'partout'], 'portée inconnue'];
        yield 'tiret long' => [['summary' => 'BAPR — Arnage'], 'tiret long'];
        yield 'lien en http' => [['sources' => [['publisher' => 'SNCF Réseau', 'title' => 'A', 'url' => 'http://www.sncf-reseau.com/a', 'official' => true]]], 'https'];
        yield 'source sans titre' => [['sources' => [['publisher' => 'SNCF Réseau', 'url' => 'https://www.sncf-reseau.com/a', 'official' => true]]], 'sans titre'];
    }

    /** @param  array<string, mixed>  $over */
    #[DataProvider('refused')]
    public function test_a_doubtful_fact_is_refused(array $over, string $reason): void
    {
        ['facts' => $facts, 'errors' => $errors] = SignallingSourcesFile::parse([self::entry($over)]);

        $this->assertSame([], $facts);
        $this->assertStringContainsString($reason, $errors[0]);
    }

    public function test_the_repository_file_is_valid_and_has_no_duplicate(): void
    {
        $entries = require dirname(__DIR__, 3).'/database/data/signalling_history_sources.php';
        ['facts' => $facts, 'errors' => $errors] = SignallingSourcesFile::parse($entries);

        $this->assertSame([], $errors);
        $this->assertCount(count($entries), $facts);
        $keys = array_map(fn (array $f): string => implode('|', [$f['line'], $f['system'], $f['date'], $f['section_from'], $f['section_to']]), $facts);
        $this->assertSame($keys, array_values(array_unique($keys)), 'un fait par ligne, système, date et portion');
    }

    /** @param  array<string, mixed>  $over */
    private static function fact(string $provider, array $over = []): array
    {
        return array_merge(['system' => 'BAL', 'role' => 'block', 'date' => '1975-01-01', 'scope' => 'unspecified', 'section' => null,
            'source' => ['provider' => $provider]], $over);
    }

    public function test_a_wikipedia_fact_restated_by_a_document_is_dropped(): void
    {
        $wiki = self::fact('wikipedia');
        $doc = self::fact('manual', ['date' => '1975-05-25', 'scope' => 'whole_line']);

        $this->assertSame([$doc], SignallingHistoryState::withoutRestatements([$wiki, $doc]));
    }

    public function test_other_years_groups_and_portions_are_kept(): void
    {
        $doc = self::fact('manual', ['date' => '1975-05-25', 'scope' => 'section', 'section' => ['from' => 'Angers', 'to' => 'Nantes']]);
        $otherYear = self::fact('wikipedia', ['date' => '1978-01-01']);
        $otherGroup = self::fact('wikipedia', ['system' => 'KVB', 'role' => 'speed_control']);
        $otherPortion = self::fact('wikipedia', ['scope' => 'section', 'section' => ['from' => 'Tours', 'to' => 'Saumur']]);
        $samePortion = self::fact('wikipedia', ['scope' => 'section', 'section' => ['from' => 'angers', 'to' => 'Nantes']]);

        $this->assertSame([$otherYear, $otherGroup, $otherPortion, $doc],
            SignallingHistoryState::withoutRestatements([$otherYear, $otherGroup, $otherPortion, $samePortion, $doc]));
        $this->assertSame([$otherYear], SignallingHistoryState::withoutRestatements([$otherYear]), 'sans document, rien ne change');
    }

    public function test_a_whole_line_wikipedia_fact_is_kept_beside_a_document_on_one_portion(): void
    {
        // Le document ne date qu'une portion : le fait de l'article sur toute la ligne dit davantage
        // (le reste de la ligne), il n'est pas redit et reste dans l'historique comme sur la carte.
        $doc = self::fact('manual', ['date' => '1975-05-25', 'scope' => 'section', 'section' => ['from' => 'Angers', 'to' => 'Nantes']]);
        $wholeLine = self::fact('wikipedia', ['scope' => 'whole_line']);
        $unspecified = self::fact('wikipedia');

        $this->assertSame([$wholeLine, $doc], SignallingHistoryState::withoutRestatements([$wholeLine, $unspecified, $doc]));
    }
}
