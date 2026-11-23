<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit des fermetures 2 (23/11/2026) : 38 gares fermées dont la date de fermeture était déduite de la ligne (déclassement, fin du fret,
 * fermeture totale) alors que l'article de la gare ou de la ligne date autrement la fermeture (voyageurs du 15/05/1939, du 28/09/1969,
 * de 1980...). Chacune a une correction sourcée (section 44 de gares_corrections.php), une seule, datée, avec un permalien.
 */
class StationClosureAudit2CorrectionsTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> nom → [type, date, précision] */
    public static function corrections(): array
    {
        return [
            'Aire-sur-l\'Adour' => ['closure_passenger', '1970-10-12', 'day'],
            'Joudreville' => ['closure_passenger', '1939-05-15', 'day'],
            'Landres' => ['closure_passenger', '1939-05-15', 'day'],
            'Aumetz' => ['closure_passenger', '1948-05-05', 'day'],
            'Roisel' => ['closure_passenger', '1970-04-01', 'day'],
            'Mortagne-au-Perche' => ['closure_passenger', '1953-01-01', 'year'],
            'Gaël' => ['closure_passenger', '1972-03-06', 'day'],
            'Airvault-Ville' => ['closure_passenger', '1980-01-01', 'year'],
            'Gourge' => ['closure_passenger', '1980-09-27', 'day'],
            'St-André-de-Lidon' => ['closure_passenger', '1939-05-15', 'day'],
            'Chauvigny' => ['closure_passenger', '1940-01-01', 'year'],
            'Concremiers' => ['closure_passenger', '1940-01-01', 'year'],
            'Ingrandes-Mérigny' => ['closure_passenger', '1940-05-27', 'day'],
            'St-Hilaire (Indre)' => ['closure_passenger', '1933-03-01', 'day'],
            'Liglet' => ['closure_passenger', '1933-03-01', 'day'],
            'St-Denis-de-Jouhet' => ['closure_passenger', '1939-07-01', 'day'],
            'Larnagol-Calvignac' => ['closure_passenger', '1980-09-28', 'day'],
            'Montbrun' => ['closure_passenger', '1980-09-28', 'day'],
            'Noyant-d\'Allier' => ['closure_passenger', '1972-01-01', 'year'],
            'Champtercier' => ['closure_passenger', '1972-03-06', 'day'],
            'Draguignan' => ['closure', '1981-01-01', 'year'],
            'Céret' => ['closure_passenger', '1940-05-23', 'day'],
            'Drincham' => ['closure', '1951-01-01', 'year'],
            'Ouzouer - Dampierre' => ['closure_passenger', '1939-05-15', 'day'],
            'Saint-Aigny - Le Blanc' => ['closure_passenger', '1940-05-27', 'day'],
            'Rosteig' => ['closure_passenger', '1969-09-28', 'day'],
            'Saint-Louis-lès-Bitche' => ['closure_passenger', '1969-09-28', 'day'],
            'Montmorency' => ['closure_passenger', '1954-06-30', 'day'],
            'Soucht' => ['closure_passenger', '1969-09-28', 'day'],
            'Pouligny-Saint-Pierre' => ['closure_passenger', '1940-06-27', 'day'],
            'Cogolin La Foux' => ['closure', '1948-01-01', 'year'],
            'Pitgam' => ['closure', '1951-01-01', 'year'],
            'Audun-le-Tiche-Mont' => ['closure_passenger', '1948-05-05', 'day'],
            'Régneville-sur-Mer' => ['closure', '1942-01-01', 'year'],
            'Anderny' => ['closure_passenger', '1939-05-15', 'day'],
            'Belvianes' => ['closure_passenger', '1939-04-18', 'day'],
            'Amélie-les-Bains' => ['closure_passenger', '1940-05-23', 'day'],
            'Saint-Tropez' => ['closure', '1949-06-04', 'day'],
        ];
    }

    public function test_each_audited_closure_is_corrected_once_with_its_date(): void
    {
        $all = require database_path('data/gares_corrections.php');
        foreach (self::corrections() as $name => [$type, $date, $precision]) {
            $found = array_values(array_filter($all, fn ($c) => ($c['station']['name'] ?? null) === $name
                && ($c['action'] ?? null) === 'add' && str_starts_with((string) ($c['type'] ?? ''), 'closure')));
            $this->assertCount(1, $found, "{$name} : une seule correction de fermeture");
            $this->assertSame([$type, $date, $precision], [$found[0]['type'], $found[0]['date'], $found[0]['precision']], $name);
            $this->assertMatchesRegularExpression('/oldid=\d+/', (string) $found[0]['source_url'], "{$name} : permalien");
            $this->assertNotSame('', trim((string) $found[0]['quote']), "{$name} : citation");
        }
    }
}
