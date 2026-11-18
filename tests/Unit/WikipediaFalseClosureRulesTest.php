<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fausses fermetures lues dans Wikipédia (WikipediaLineParser, sans réseau), phrases réelles
 * (line_events.source_quote, 2026-10-19). Une fermeture récente et sourcée l'emporte sur le
 * statut SNCF « exploitée » dans le moteur (EraTimeline::isRecentSourcedClosure) : une fausse
 * fermeture ferme la portion en 2030 (366000 : 74 km entre Lison et Cherbourg, Carentan et
 * Valognes en gares fermées).
 *
 * Pas une fermeture : une fermeture temporaire (déraillement, « pendant plusieurs jours »),
 * une prévision (risque, menace, échéance « pour 2025 », crainte), une simple mention (sujet
 * d'un documentaire).
 */
class WikipediaFalseClosureRulesTest extends TestCase
{
    private WikipediaLineParser $p;

    protected function setUp(): void
    {
        $this->p = new WikipediaLineParser;
    }

    /** @return list<array{0: string, 1: string}> [type, date] des fermetures (closure*) */
    private function closures(string $wikitext, string $title): array
    {
        return array_values(array_map(fn ($e) => [$e['type'], $e['date']],
            array_filter($this->p->parseChronology($wikitext, $title), fn ($e) => str_starts_with($e['type'], 'closure'))));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function notAClosureProvider(): array
    {
        return [
            '366000 #10572 déraillement, fermeture pendant plusieurs jours' => ["== Histoire ==\n=== SNCF ===\nLe 11 janvier 2026, un train de marchandises déraille près de Carentan et endommage la voie, entraînant la fermeture de la section Lison - Cherbourg pendant plusieurs jours.",
                'Ligne de Mantes-la-Jolie à Cherbourg'],
            'fermeture pendant trois semaines' => ["== Histoire ==\nEn mars 2018, la section de Lison à Carentan est fermée pendant trois semaines.",
                'Ligne de Mantes-la-Jolie à Cherbourg'],
            '525000 #8007 risque et fermeture pour 2025' => ["== Histoire ==\n=== Menaces de fermeture ===\nEn 2018, des diagnostics de l'état de l’infrastructure entre Thouars et La Roche-sur-Yon montrent qu'il y a un risque de réduction de la vitesse limite à partir de 2021 et une fermeture pour 2025.",
                "Ligne des Sables-d'Olonne à Tours"],
            '525000 la CGT craint une fermeture définitive' => ["== Histoire ==\nEn août 2026, la CGT cheminots de Thouars craint une fermeture définitive de la section de Chantonnay à Bressuire.",
                "Ligne des Sables-d'Olonne à Tours"],
            'ligne menacée de fermeture' => ["== Histoire ==\nEn 2019, la section de Thouars à Bressuire est menacée de fermeture.",
                "Ligne des Sables-d'Olonne à Tours"],
            'fermeture prévue' => ["== Histoire ==\nLa fermeture de la section de Thouars à Bressuire est prévue en 2027.",
                "Ligne des Sables-d'Olonne à Tours"],
            '808000 sujet d\'un documentaire' => ["== Historique ==\n=== Histoire ===\nEn 2021, la fermeture de la ligne fait l'objet du documentaire Voyage à Bessèges du réalisateur suisse Manuel Lobmaier, diffusé par France 3 Occitanie.",
                'Ligne de Bessèges à Robiac'],
        ];
    }

    #[DataProvider('notAClosureProvider')]
    public function test_sentences_that_do_not_close_the_line(string $wikitext, string $title): void
    {
        $this->assertSame([], $this->closures($wikitext, $title));
    }

    /** @return array<string, array{0: string, 1: string, 2: list<array{0: string, 1: string}>}> */
    public static function realClosureProvider(): array
    {
        return [
            '525000 #8005 fermée par SNCF Réseau depuis le 20 novembre 2019' => ["== Histoire ==\n=== 2017 ===\nDepuis le 20 novembre 2019, la section Chantonnay - Bressuire est fermée par SNCF Réseau pour des raisons techniques (oxydation importante du rail dues aux conditions climatiques).",
                "Ligne des Sables-d'Olonne à Tours", [['closure', '2019-11-20']]],
            'fermée aux voyageurs' => ["== Histoire ==\nLa ligne est fermée aux voyageurs le 1er octobre 1938.",
                'Ligne de Alpha à Bravo', [['closure_passenger', '1938-10-01']]],
            '808000 fermée au trafic voyageurs (chronologie)' => ["== Historique ==\n=== Chronologie ===\n* 7 juillet 2012 : la ligne est fermée au trafic voyageurs.",
                'Ligne de Bessèges à Robiac', [['closure_passenger', '2012-07-07']]],
        ];
    }

    #[DataProvider('realClosureProvider')]
    public function test_real_closures_are_kept(string $wikitext, string $title, array $expected): void
    {
        $this->assertSame($expected, $this->closures($wikitext, $title));
    }
}
