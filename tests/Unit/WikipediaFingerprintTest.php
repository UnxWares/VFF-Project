<?php

namespace Tests\Unit;

use App\Console\Commands\ImportWikipediaChronology;
use App\Services\LineMerger;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Empreinte des événements Wikipédia (line_events.fingerprint) : la fusion de
 * lignes (LineMerger) recalcule l'empreinte d'un événement déplacé ; elle doit
 * être EXACTEMENT celle que produirait vff:import-wikipedia-chronology pour la
 * ligne cible, sinon une réimportation recrée l'événement en double.
 */
class WikipediaFingerprintTest extends TestCase
{
    private function importFingerprint(int $lineId, array $e): string
    {
        $command = (new \ReflectionClass(ImportWikipediaChronology::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($command, 'parser'))->setValue($command, new WikipediaLineParser);

        return (new \ReflectionMethod($command, 'fingerprint'))->invoke($command, $lineId, $e);
    }

    /** Événement tel que lu en base (line_events), pour LineMerger. */
    private function row(array $e): object
    {
        return (object) [
            'type' => $e['type'], 'event_date' => $e['date'].' 00:00:00', 'date_precision' => $e['precision'],
            'section_from' => $e['section_from'], 'section_to' => $e['section_to'],
            'section_pk_start_m' => $e['pk_start_m'], 'section_pk_end_m' => $e['pk_end_m'],
        ];
    }

    private function event(array $overrides = []): array
    {
        return $overrides + ['type' => 'closure_passenger', 'date' => '1938-05-15', 'precision' => 'day',
            'section_from' => 'Saint-Méen', 'section_to' => 'Loudéac', 'pk_start_m' => null, 'pk_end_m' => null];
    }

    public function test_merged_event_keeps_the_import_fingerprint(): void
    {
        $merger = new LineMerger(new WikipediaLineParser);

        foreach ([$this->event(), $this->event(['section_from' => null, 'section_to' => null]),
            $this->event(['pk_start_m' => 12_300, 'pk_end_m' => 45_800, 'precision' => 'year', 'date' => '1938-01-01'])] as $e) {
            $this->assertSame($this->importFingerprint(42, $e), $merger->wikipediaFingerprint(42, $this->row($e)));
        }
    }

    public function test_fingerprint_identifies_the_fact_not_its_spelling(): void
    {
        $merger = new LineMerger(new WikipediaLineParser);
        $base = $merger->wikipediaFingerprint(42, $this->row($this->event()));

        $this->assertStringStartsWith('wikipedia:', $base);
        // Graphie différente du même lieu (accents, casse) : même fait.
        $this->assertSame($base, $merger->wikipediaFingerprint(42, $this->row($this->event(['section_from' => 'SAINT-MEEN']))));
        // Autre ligne, autre date, autre précision, autre portion : autre fait.
        $this->assertNotSame($base, $merger->wikipediaFingerprint(43, $this->row($this->event())));
        $this->assertNotSame($base, $merger->wikipediaFingerprint(42, $this->row($this->event(['date' => '1938-05-16']))));
        $this->assertNotSame($base, $merger->wikipediaFingerprint(42, $this->row($this->event(['precision' => 'month']))));
        $this->assertNotSame($base, $merger->wikipediaFingerprint(42, $this->row($this->event(['section_to' => 'Mûr-de-Bretagne']))));
        $this->assertNotSame($base, $merger->wikipediaFingerprint(42, $this->row($this->event(['type' => 'closure']))));
    }
}
