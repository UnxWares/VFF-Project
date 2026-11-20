<?php

namespace App\Mcp\Support;

use Illuminate\Pagination\Cursor;

/**
 * Pagination par curseur des outils MCP de liste (mêmes curseurs opaques que l'API REST) : l'outil
 * renvoie `next_cursor`, à repasser tel quel dans l'argument `cursor` pour la page suivante ;
 * `null` à la dernière page (voir VffTool::respondPage : une page raccourcie ne perd aucun élément).
 */
final class CursorPage
{
    public static function cursor(?string $encoded): ?Cursor
    {
        return $encoded !== null && $encoded !== '' ? Cursor::fromEncoded($encoded) : null;
    }
}
