<?php
// php fill.php titres.txt : range dans le cache les articles listes (un par ligne, hors lignes ## et FIN), via l'API (User-Agent du projet).
require __DIR__.'/../../../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cache = new App\Services\Signalling\WikipediaArticleCache(app(App\Services\WikimediaClient::class), App\Services\Signalling\WikipediaArticleCache::defaultDirectory());
$t = array_values(array_filter(array_map('trim', file($argv[1])), fn ($l) => $l !== '' && ! str_starts_with($l, '##') && ! str_starts_with($l, 'FIN') && ! str_starts_with($l, 'ERR')));
$t = array_merge($t, ['Contrôle de vitesse par balises', 'Crocodile (chemin de fer)', 'Répétition des signaux', 'Dispositif d\'arrêt automatique des trains', 'Transmission voie-machine', 'European Rail Traffic Management System', 'Signalisation ferroviaire en France', 'Signalisation ferroviaire']);
echo $cache->fill($t)." rangés sur ".count($t)."\n";
