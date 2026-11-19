<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyEventCorrections;
use Tests\TestCase;

/**
 * Invariants des corrections sourcées versionnées (database/data/gares_corrections.php et
 * line_event_corrections*.php), vérifiés sur les fichiers eux-mêmes :
 *   - une page Wikipédia est toujours citée par un permalien (oldid), jamais par la page vivante ;
 *   - un événement ajouté cite le passage de sa source ;
 *   - une gare n'a qu'un état du jour imposé (action « current »), qui ne contredit pas ses
 *     propres événements corrigés : « desservie » sans fermeture ajoutée non suivie d'une
 *     réouverture, « plus desservie » sans réouverture ajoutée après sa dernière fermeture ;
 *   - une portion de ligne ajoutée nomme ses deux bouts, ou aucun.
 */
class SourcedCorrectionsInvariantsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function stationCorrections(): array
    {
        return require database_path('data/gares_corrections.php');
    }

    /** @return array<string, array<string, mixed>> */
    private function lineCorrections(): array
    {
        $out = [];
        foreach (ApplyEventCorrections::files() as $file) {
            foreach (require $file as $i => $c) {
                $out[basename($file, '.php').' n° '.$i] = $c;
            }
        }

        return $out;
    }

    public function test_wikipedia_sources_are_permalinks(): void
    {
        $all = array_merge(array_map(fn ($c) => [$c['station']['name'] ?? '?', $c['source_url'] ?? ''], $this->stationCorrections()),
            array_map(fn ($c, $k) => [$k, $c['source_url'] ?? ''], $this->lineCorrections(), array_keys($this->lineCorrections())));
        foreach ($all as [$label, $url]) {
            if (str_contains((string) $url, 'wikipedia.org')) {
                $this->assertMatchesRegularExpression('/[?&]oldid=\d+/', (string) $url, "{$label} : permalien oldid attendu");
            }
        }
    }

    public function test_every_added_event_quotes_its_source(): void
    {
        foreach ($this->stationCorrections() as $i => $c) {
            if ($c['action'] === 'add') {
                $this->assertNotSame('', trim((string) ($c['quote'] ?? '')), "gare #{$i} ({$c['station']['name']}) : citation manquante");
            }
        }
        foreach ($this->lineCorrections() as $k => $c) {
            if (($c['action'] ?? null) === 'add') {
                $this->assertNotSame('', trim((string) ($c['quote'] ?? '')), "{$k} : citation manquante");
            }
        }
    }

    public function test_today_status_corrections_agree_with_the_station_events(): void
    {
        $byStation = [];
        foreach ($this->stationCorrections() as $c) {
            $byStation[json_encode($c['station'])][] = $c;
        }
        foreach ($byStation as $key => $list) {
            $current = array_values(array_filter($list, fn ($c) => $c['action'] === 'current'));
            $this->assertLessThanOrEqual(1, count($current), "{$key} : plusieurs états du jour imposés");
            if ($current === []) {
                continue;
            }
            $dates = fn (array $types) => array_map(fn ($c) => $c['date'], array_filter($list, fn ($c) => $c['action'] === 'add' && in_array($c['type'], $types, true)));
            $lastClosure = max([''] + $dates(['closure', 'demolition']));
            $lastReopening = max([''] + $dates(['opening', 'reopening']));
            if ($current[0]['status'] === 'active') {
                $this->assertTrue($lastClosure === '' || $lastReopening > $lastClosure, "{$key} : desservie mais fermée par une correction");
            } else {
                $this->assertFalse($lastReopening !== '' && $lastReopening > $lastClosure, "{$key} : plus desservie mais rouverte par une correction");
            }
        }
    }

    public function test_added_portions_name_both_ends_or_none(): void
    {
        foreach ($this->lineCorrections() as $k => $c) {
            if (($c['action'] ?? null) === 'add') {
                $this->assertSame(isset($c['from']), isset($c['to']), "{$k} : portion « de … à … » incomplète");
            }
        }
    }
}
