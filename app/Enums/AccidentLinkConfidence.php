<?php

namespace App\Enums;

/**
 * Confiance du rattachement d'un accident à une ligne VFF (accident_lines.confidence).
 *
 *   - high : position précise ET ligne désignée par une source (Wikidata, Wikipédia, code de
 *     ligne du passage à niveau), ou position précise à moins de 100 m d'une seule ligne ;
 *   - medium : position précise à quelques centaines de mètres de la ligne, ou commune
 *     seulement mais ligne désignée par une source ;
 *   - low : commune seulement, ligne la plus proche : à vérifier.
 */
enum AccidentLinkConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
