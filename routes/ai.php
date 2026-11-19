<?php

use App\Mcp\Servers\VffServer;
use Laravel\Mcp\Facades\Mcp;

// ── Serveur MCP public (lecture seule, docs/MCP.md) ─────────────────────────
// HTTP « streamable » : POST /mcp, sans authentification, même limite par adresse IP que l'API
// publique (config mcp.vff.rate_limit_per_minute). Fichier chargé par le paquet laravel/mcp,
// hors du groupe « web » (ni session ni CSRF).
Mcp::web('/mcp', VffServer::class)
    ->middleware('throttle:'.(int) config('mcp.vff.rate_limit_per_minute', 300).',1')
    ->name('mcp');

// stdio, pour un client MCP local : php artisan mcp:start vff
Mcp::local('vff', VffServer::class);
