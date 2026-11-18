<?php

namespace Tests\Unit;

use App\Support\Lgv;
use App\Support\StationNames;
use App\Support\TrackPriority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Aides partagées (App\Support) : définition unique des LGV, noms de gares
 * (dédoublonnage, casse des libellés SNCF), priorité d'affichage des voies
 * partagées entre plusieurs lignes.
 */
class SupportHelpersTest extends TestCase
{
    // ─── Lgv ─────────────────────────────────────────────────────────────

    /** @return array<string, array{0: ?string, 1: bool}> */
    public static function lgvNameProvider(): array
    {
        return [
            'LGV en tête' => ['LGV Sud-Est', true],
            'LGV suivie d\'un tiret' => ['LGV-Est européenne', true],
            'minuscules' => ['lgv bretagne - pays de la loire', true],
            'raccordement LGV' => ['Raccordement de la LGV Atlantique', true],
            'grande vitesse en toutes lettres' => ['Ligne à grande vitesse Paris - Lyon', true],
            'Grande Vitesse en majuscules' => ['Ligne à Grande Vitesse Rhin-Rhône', true],
            'ligne classique' => ['Ligne de Paris-Montparnasse à Brest', false],
            'LGV dans un mot : pas un signal' => ['Ligne de Valgvé à Brest', false],
            'vitesse seule' => ['Ligne à vitesse élevée', false],
            'nom absent' => [null, false],
        ];
    }

    #[DataProvider('lgvNameProvider')]
    public function test_lgv_name(?string $name, bool $expected): void
    {
        $this->assertSame($expected, Lgv::nameIsLgv($name));
    }

    public function test_lgv_sql_combines_every_signal_with_or(): void
    {
        $sql = Lgv::sql('l', 'ls');

        $this->assertStringStartsWith('(', $sql);
        $this->assertStringContainsString('COALESCE(l.is_lgv, false)', $sql);
        $this->assertStringContainsString("ILIKE '".Lgv::CATLIG_PATTERN."'", $sql);
        $this->assertStringContainsString('COALESCE(ls.max_speed_kmh, 0) >= '.Lgv::MIN_SPEED_KMH, $sql);
        $this->assertSame(3, substr_count($sql, ' OR ') - substr_count($sql, "name ILIKE '%grande vitesse%'"),
            'quatre signaux, reliés par OU (le nom compte deux motifs)');
        // Sans table des tronçons : pas de signal vitesse.
        $this->assertStringNotContainsString('max_speed_kmh', Lgv::sql('x', null));
        $this->assertStringContainsString('x.code', Lgv::sql('x', null));
    }

    public function test_lgv_speed_threshold_is_above_any_classic_french_line(): void
    {
        // Aucune ligne classique française ne dépasse 220 km/h.
        $this->assertGreaterThan(220, Lgv::MIN_SPEED_KMH);
        $this->assertSame(250, Lgv::MIN_SPEED_KMH);
    }

    // ─── StationNames ────────────────────────────────────────────────────

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function normalizeProvider(): array
    {
        return [
            'préfixe « gare de »' => ['Gare de Saint-Pol-de-Léon', 'saint pol de leon'],
            'halte, St abrégé, département' => ['Halte de St-Aubin (Ille-et-Vilaine)', 'saint aubin'],
            'suffixe « (gare) »' => ['Morlaix (gare)', 'morlaix'],
            'élision d\'' => ['Gare d\'Auray', 'auray'],
            'suffixe Ville conservé' => ['Pontoise Ville', 'pontoise ville'],
            'suffixe « gare »' => ['Brest Gare', 'brest'],
            'ancienne gare, Ste abrégé' => ['Ancienne gare de Ste-Cécile', 'sainte cecile'],
            's/ = sur' => ['Châlons-s-Marne', 'chalons sur marne'],
            'bâtiment voyageurs' => ['Bâtiment voyageurs de la gare de Rennes', 'rennes'],
            'BV' => ['BV de Cerisy', 'cerisy'],
            'gare SNCF' => ['Gare SNCF de Lyon-Perrache', 'lyon perrache'],
            'mot « gare » seul' => ['Gare', null],
            'blanc' => [' ', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function test_station_name_normalization(?string $name, ?string $expected): void
    {
        $this->assertSame($expected, StationNames::normalize($name));
    }

    public function test_same_station_from_two_sources_has_the_same_key(): void
    {
        $this->assertSame(StationNames::normalize('Gare de Saint-Brieuc'), StationNames::normalize('ST-BRIEUC'));
        $this->assertSame(StationNames::normalize('Gare de Lamballe'), StationNames::normalize('Lamballe (Côtes-d\'Armor)'));
        $this->assertNotSame(StationNames::normalize('Pontoise'), StationNames::normalize('Pontoise Ville'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function prettifyProvider(): array
    {
        return [
            'tirets et article au milieu' => ['ST-POL-DE-LEON', 'St-Pol-de-Leon'],
            'article en tête' => ['LA ROCHELLE VILLE', 'La Rochelle Ville'],
            'Le Mans' => ['LE MANS', 'Le Mans'],
            'Les Aubrais' => ['LES AUBRAIS', 'Les Aubrais'],
            'élision en tête' => ['L\'ISLE-ADAM', 'L\'Isle-Adam'],
            'élision au milieu (aucun accent inventé)' => ['VILLENEUVE-L\'ARCHEVEQUE', 'Villeneuve-l\'Archeveque'],
            'en' => ['AIX-EN-PROVENCE', 'Aix-en-Provence'],
            'accent en tête' => ['ÉPINAL', 'Épinal'],
            'déjà en casse mixte : inchangé' => ['Brest', 'Brest'],
            'casse mixte avec article : inchangé' => ['la Brohinière', 'la Brohinière'],
        ];
    }

    #[DataProvider('prettifyProvider')]
    public function test_sncf_label_casing(string $label, string $expected): void
    {
        $this->assertSame($expected, StationNames::prettify($label));
    }

    // ─── TrackPriority ───────────────────────────────────────────────────

    public function test_tie_break_never_outweighs_a_state_rank(): void
    {
        // draw_rank = rang × 100 + départage : le départage (0..49) ne doit jamais
        // faire passer une portion fermée devant une portion en service.
        preg_match_all('/THEN (\d+)/', TrackPriority::tieSql(), $m);
        preg_match_all('/ELSE (\d+) END/', TrackPriority::tieSql(), $else);
        $maxTie = max(array_map('intval', $m[1])) + 0 + max(array_map('intval', $else[1]));

        $this->assertLessThan(100, $maxTie);
        $this->assertStringContainsString('* 100 +', TrackPriority::drawRankSql('false'));
    }

    public function test_state_ranks_put_service_before_heritage_before_closed_before_gone(): void
    {
        $sql = TrackPriority::rankSql('false');
        $rank = function (string $pattern) use ($sql): int {
            $this->assertMatchesRegularExpression($pattern, $sql);
            preg_match($pattern, $sql, $m);

            return (int) $m[1];
        };

        $serviceTrack = $rank("/status = 'service_track'.*? THEN (\\d+)/s");
        $heritageRunning = $rank("/is_heritage, false\\) AND ls\\.status IN \\('in_service', 'single_track'\\) THEN (\\d+)/s");
        $construction = $rank("/status = 'construction' THEN (\\d+)/");
        $abandoned = $rank("/status = 'abandoned' THEN (\\d+)/");
        $dismantled = $rank("/status = 'dismantled' THEN (\\d+)/");
        $razed = $rank("/status = 'razed' THEN (\\d+)/");
        // Plus petite valeur d'une portion en service : 1000 + réseau privé (0) + trafic inconnu (10).
        $minInService = 1000 + 0 + 10;

        $this->assertGreaterThan($serviceTrack, $minInService);
        $this->assertGreaterThan($heritageRunning, $serviceTrack);
        $this->assertGreaterThan($construction, $heritageRunning);
        $this->assertGreaterThan($abandoned, $construction);
        $this->assertGreaterThan($dismantled, $abandoned);
        $this->assertGreaterThan($razed, $dismantled);
    }

    public function test_aliases_are_applied_everywhere(): void
    {
        $sql = TrackPriority::drawRankSql('lgv_flag', 'li', 'se');

        $this->assertStringNotContainsString(' ls.', $sql);
        $this->assertStringNotContainsString(' l.', $sql);
        $this->assertStringContainsString('se.status', $sql);
        $this->assertStringContainsString('li.network', $sql);
        $this->assertStringContainsString('(lgv_flag)', $sql);
        $this->assertSame("(x.id || ':' || x.status || ':' || x.traffic_type || ':' || x.is_heritage::text)", TrackPriority::stateSql('x'));
    }
}
