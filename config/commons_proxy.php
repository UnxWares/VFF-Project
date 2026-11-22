<?php

/*
| Relais des vignettes de Wikimedia Commons (App\Http\Controllers\CommonsImageController).
|
| Activé, VFF sert lui-même les vignettes de Commons des fiches, du journal et des galeries à
| /media/commons/{chemin} : le navigateur ne contacte plus upload.wikimedia.org (ni ses cookies tiers).
| Le serveur les lit sur upload.wikimedia.org et les garde dans un cache disque limité en taille et en durée.
| Le crédit, l'auteur, la licence et le lien vers la page Commons restent affichés comme avant.
| Désactivé (par défaut) : la route répond 404 et les pages gardent les adresses de Wikimedia.
| Déploiement (place disque, nginx) : docs/DEPLOIEMENT-NGINX.md, section « Relais des vignettes de Commons ».
*/
return [
    'enabled' => (bool) env('VFF_COMMONS_PROXY', false),

    // Dossier du cache (un fichier `.nobackup` y est posé : rien à sauvegarder, tout se relit sur Commons).
    'cache_path' => env('VFF_COMMONS_PROXY_CACHE_PATH', storage_path('app/cache/commons')),

    // Taille maximale du cache, en Mo. Au-delà, vff:commons-cache-prune retire les vignettes lues le plus
    // anciennement sur Commons jusqu'à revenir à 90 % de cette taille. Mesure du 22/11/2026 : une vignette de
    // 500 px pèse 65 Ko en moyenne, une de 1 280 px environ 330 Ko.
    'cache_max_mb' => (int) env('VFF_COMMONS_PROXY_CACHE_MB', 2048),

    // Durée de vie d'une vignette du cache (jours) : passé ce délai, elle est relue sur Commons à la demande
    // suivante (un fichier peut y être remplacé par une nouvelle version), et c'est aussi la durée de cache
    // HTTP annoncée au navigateur.
    'ttl_days' => (int) env('VFF_COMMONS_PROXY_TTL_DAYS', 30),

    // Requêtes par minute et par adresse IP vers le relais (une fiche peut afficher plusieurs dizaines d'images).
    'requests_per_minute' => (int) env('VFF_COMMONS_PROXY_REQUESTS_PER_MINUTE', 600),

    // Lectures sur Commons par minute pour tout le serveur (vignettes absentes du cache) : au-delà, 429
    // avec Retry-After, pour rester un client poli de Wikimedia. Un 429 de Commons met aussi le relais en
    // pause pendant le délai demandé.
    'upstream_per_minute' => (int) env('VFF_COMMONS_PROXY_UPSTREAM_PER_MINUTE', 300),

    // Les mêmes lectures, par adresse IP du visiteur : une seule adresse qui demande des images absentes du
    // cache ne prend pas tout le plafond du serveur (les images déjà gardées restent servies).
    'upstream_per_client_per_minute' => (int) env('VFF_COMMONS_PROXY_UPSTREAM_PER_CLIENT', 120),

    // Poids maximal d'une image lue sur Commons (octets) : au-delà, elle est refusée sans être gardée.
    'max_bytes' => (int) env('VFF_COMMONS_PROXY_MAX_BYTES', 8_388_608),

    // User-Agent envoyé à Wikimedia (politique : https://meta.wikimedia.org/wiki/User-Agent_policy) : nom,
    // version et adresse de contact du projet, sans donnée personnelle.
    'user_agent' => env('VFF_COMMONS_PROXY_USER_AGENT', 'VFF-ImageRelay/1.0 (https://vff-project.org) Laravel-HTTP'),

    // Production derrière nginx : préfixe de la location interne qui sert les fichiers du cache (ex.
    // `/_commons/`). VFF vérifie la requête puis répond X-Accel-Redirect ; vide : PHP envoie le fichier.
    'accel_redirect' => env('VFF_COMMONS_PROXY_ACCEL_REDIRECT', ''),
];
