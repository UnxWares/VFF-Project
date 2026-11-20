<?php

namespace App\Services\TraceExport;

use XMLWriter;

/**
 * Écrit le tracé d'une ligne en KML 2.2 (OGC 07-147r2) : un `Document` au nom de la ligne, une
 * `Placemark` par tronçon avec un `LineString` (PK, état et source en description), dans l'ordre des
 * PK. Le document porte l'ère, l'adresse de la fiche (`atom:link`), les sources et leurs licences.
 */
final class KmlWriter
{
    public const CONTENT_TYPE = 'application/vnd.google-earth.kml+xml; charset=UTF-8';

    public function write(LineTrace $trace, TraceCaption $caption): string
    {
        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('kml');
        $w->writeAttribute('xmlns', 'http://www.opengis.net/kml/2.2');
        $w->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

        // Ordre imposé par le schéma : name, atom:author, atom:link, description, styles, puis les placemarks.
        $w->startElement('Document');
        $w->writeElement('name', $caption->title);
        $w->startElement('atom:author');
        $w->writeElement('atom:name', $caption->database);
        $w->endElement();
        $w->startElement('atom:link');
        $w->writeAttribute('href', $caption->url);
        $w->endElement();
        $w->writeElement('description', $caption->description());

        $w->startElement('Style');
        $w->writeAttribute('id', 'vff-line');
        $w->startElement('LineStyle');
        // Orange VFF (#ff6500) ; KML écrit aabbggrr.
        $w->writeElement('color', 'ff0065ff');
        $w->writeElement('width', '3');
        $w->endElement();
        $w->endElement();

        foreach ($trace->parts as $part) {
            $w->startElement('Placemark');
            $w->writeElement('name', trans('trace_export.segment', ['id' => $part->id], $caption->lang));
            $w->writeElement('description', $caption->segmentText($part));
            $w->writeElement('styleUrl', '#vff-line');
            $w->startElement('LineString');
            $w->writeElement('tessellate', '1');
            $w->writeElement('coordinates', implode(' ', array_map(
                fn (array $c): string => TracePart::format($c[0]).','.TracePart::format($c[1]),
                $part->coordinates,
            )));
            $w->endElement();
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }
}
