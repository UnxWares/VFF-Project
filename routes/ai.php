<?php

use App\Mcp\Servers\VffServer;
use Laravel\Mcp\Facades\Mcp;

// ── Serveur MCP public (lecture seule, docs/MCP.md) ─────────────────────────
// HTTP « streamable » : POST /mcp, sans authentification obligatoire, même jeton facultatif et même
// limiteur que l'API publique (compteur commun, limite par profil : config/api.php). Fichier chargé
// par le paquet laravel/mcp, hors du groupe « web » (ni session ni CSRF).
Mcp::web('/mcp', VffServer::class)
    ->middleware(['api.token', 'throttle:public-api'])
    ->name('mcp');

// stdio, pour un client MCP local : php artisan mcp:start vff
Mcp::local('vff', VffServer::class);
