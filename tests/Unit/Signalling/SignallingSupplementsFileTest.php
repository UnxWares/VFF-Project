<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingSupplementsFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fichier des faits de signalisation relus dans les articles (SignallingSupplementsFile) : contrôle de chaque
 * fait, recherche de la phrase dans l'article. Le vrai fichier du dépôt est contrôlé aussi.
 */
class SignallingSupplementsFileTest extends TestCase
{
    private const TODAY = '2026-11-23';

    /** @param  array<string, mixed>  $over */
    private static function entry(array $over = []): array
    {
        return array_merge([
            'line' => '677000', 'system' => 'BAL', 'date' => '1981-09-23', 'scope' => 'section', 'from' => 'Narbonne', 'to' => 'Elne',
            'quote' => 'datent du 23 septembre 1981 pour la première section de Narbonne à Elne',
        ], $over);
    }

    public function test_a_valid_fact_is_flattened_with_its_role_precision_and_confidence(): void
    {
        ['facts' => $facts, 'errors' => $errors] = SignallingSupplementsFile::parse([self::entry()], new \DateTimeImmutable(self::TODAY));

        $this->assertSame([], $errors);
        $this->assertSame(['block', '1981-09-23', 'day', 'high'], [$facts[0]['role'], $facts[0]['date'], $facts[0]['precision'], $facts[0]['confidence']]);
        $this->assertSame(['Narbonne', 'Elne'], [$facts[0]['section_from'], $facts[0]['section_to']]);
        $this->assertNull($facts[0]['article']);
    }

    public function test_a_year_gives_a_medium_confidence_and_the_article_can_be_another_one(): void
    {
        ['facts' => $facts] = SignallingSupplementsFile::parse([self::entry(['date' => '1981', 'article' => ' Gare de Salses ', 'scope' => 'unspecified'])], new \DateTimeImmutable(self::TODAY));

        $this->assertSame(['1981-01-01', 'year', 'medium', 'Gare de Salses'], [$facts[0]['date'], $facts[0]['precision'], $facts[0]['confidence'], $facts[0]['article']]);
        $this->assertNull($facts[0]['section_from']);
    }

    public function test_a_speed_control_system_keeps_its_own_role(): void
    {
        ['facts' => $facts] = SignallingSupplementsFile::parse([self::entry(['system' => 'KVB', 'date' => '1990', 'scope' => 'whole_line'])], new \DateTimeImmutable(self::TODAY));

        $this->assertSame('speed_control', $facts[0]['role']);
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function refused(): iterable
    {
        yield 'système inconnu' => [['system' => 'CBTC'], 'système inconnu'];
        yield 'date illisible' => [['date' => '23/09/1981'], 'date illisible'];
        yield 'date impossible' => [['date' => '1981-02-30'], 'date impossible'];
        yield 'date future' => [['date' => '2027-01'], 'date future'];
        yield 'avant le système' => [['system' => 'ETCS_L2', 'date' => '1990'], 'date antérieure au système'];
        yield 'portée inconnue' => [['scope' => 'region'], 'portée inconnue'];
        yield 'portion sans fin' => [['to' => ''], 'portion sans ses deux extrémités'];
        yield 'phrase manquante' => [['quote' => ' '], 'champ « quote » manquant'];
        yield 'phrase trop longue' => [['quote' => str_repeat('a', 256)], 'phrase de plus de 255'];
        yield 'tiret long' => [['quote' => "Narbonne \u{2013} Elne"], 'tiret long'];
        yield 'lien du texte brut' => [['quote' => 'la ⟦ligne⟧ de Narbonne'], 'lien'];
    }

    /** @param  array<string, mixed>  $over */
    #[DataProvider('refused')]
    public function test_a_wrong_fact_is_refused_and_not_kept(array $over, string $message): void
    {
        ['facts' => $facts, 'errors' => $errors] = SignallingSupplementsFile::parse([self::entry($over), self::entry()], new \DateTimeImmutable(self::TODAY));

        $this->assertCount(1, $facts, 'seul le fait juste est gardé');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString($message, $errors[0]);
        $this->assertStringContainsString('fait n° 1', $errors[0]);
    }

    public function test_the_quote_is_found_in_the_article_whatever_the_spaces_dashes_and_links(): void
    {
        $plain = "Le chantier\u{00A0}: l’électrification et la ⟦signalisation⟧ de type block automatique lumineux (BAL) datent du 23 septembre 1981\n pour la section de Narbonne \u{2013} Elne.";

        $this->assertTrue(SignallingSupplementsFile::quoteIn("l'électrification et la signalisation de type block automatique lumineux (BAL) datent du 23 septembre 1981 pour la section de Narbonne - Elne", $plain));
        $this->assertTrue(SignallingSupplementsFile::quoteIn('Le chantier : l\'électrification', $plain));
        $this->assertFalse(SignallingSupplementsFile::quoteIn('datent du 24 septembre 1981', $plain), 'une autre date n\'est pas la phrase');
        $this->assertFalse(SignallingSupplementsFile::quoteIn('  ', $plain));
    }

    public function test_the_repository_file_is_valid_and_each_fact_has_a_date_a_system_and_a_unique_quote_per_fact(): void
    {
        $entries = require dirname(__DIR__, 3).'/database/data/signalling_history_wikipedia_supplements.php';
        ['facts' => $facts, 'errors' => $errors] = SignallingSupplementsFile::parse($entries);

        $this->assertSame([], $errors);
        $this->assertCount(count($entries), $facts);
        $keys = array_map(fn (array $f): string => implode('|', [$f['line'], $f['system'], $f['date'], $f['section_from'], $f['section_to']]), $facts);
        $this->assertSame($keys, array_values(array_unique($keys)), 'un même fait ne figure qu\'une fois');
        foreach ($facts as $f) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $f['line'], 'code SNCF Réseau à 6 chiffres');
            $this->assertMatchesRegularExpression('/\b(?:1[89]|20)\d\d\b/', $f['quote'].' '.$f['date'], $f['line']);
        }
    }
}
