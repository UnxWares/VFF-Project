<?php

namespace App\Services\TraceExport;

use XMLWriter;

/**
 * Écrit le tracé d'une ligne en GPX 1.1 (https://www.topografix.com/GPX/1/1/) : une trace (`trk`) au
 * nom de la ligne, et un segment de trace (`trkseg`) par tronçon, dans l'ordre des PK. Les métadonnées
 * portent le nom, la description (ère, adresse de la fiche, sources et licences), le droit d'auteur
 * (base ODbL 1.0) et le lien vers la fiche.
 */
final class GpxWriter
{
    public const CONTENT_TYPE = 'application/gpx+xml; charset=UTF-8';

    public function write(LineTrace $trace, TraceCaption $caption): string
    {
        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('gpx');
        $w->writeAttribute('version', '1.1');
        $w->writeAttribute('creator', 'Voies Ferrées de France (https://vff-project.org)');
        $w->writeAttribute('xmlns', 'http://www.topografix.com/GPX/1/1');
        $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $w->writeAttribute('xsi:schemaLocation', 'http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd');

        // Ordre imposé par le schéma : name, desc, author, copyright, link, time, keywords, bounds.
        $w->startElement('metadata');
        $w->writeElement('name', $caption->title);
        $w->writeElement('desc', $caption->description());
        $w->startElement('copyright');
        $w->writeAttribute('author', $caption->database);
        $w->writeElement('year', (string) now()->year);
        $w->writeElement('license', $caption->licenseUrl);
        $w->endElement();
        $this->link($w, $caption->url, $trace->line->name);
        $w->endElement();

        // Ordre imposé par le schéma : name, cmt, desc, src, link, number, type, extensions, trkseg.
        $w->startElement('trk');
        $w->writeElement('name', $trace->line->name);
        $w->writeElement('desc', $caption->summary);
        $w->writeElement('src', implode(' ; ', $caption->sources()));
        $this->link($w, $caption->url, $trace->line->name);
        $w->writeElement('type', 'railway');
        foreach ($trace->parts as $part) {
            $w->startElement('trkseg');
            foreach ($part->coordinates as [$lng, $lat]) {
                $w->startElement('trkpt');
                $w->writeAttribute('lat', TracePart::format($lat));
                $w->writeAttribute('lon', TracePart::format($lng));
                $w->endElement();
            }
            $w->endElement();
        }
        $w->endElement();

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    private function link(XMLWriter $w, string $href, string $text): void
    {
        $w->startElement('link');
        $w->writeAttribute('href', $href);
        $w->writeElement('text', $text);
        $w->endElement();
    }
}
