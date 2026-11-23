<?php
// php search.php sortie.txt : recherches plein texte frwiki (API MediaWiki, User-Agent du projet, 5 s entre deux requetes) sur KVB,
// crocodile, DAAT, TVM, ETCS ; rapatrie dans le cache les articles absents (titres ecrits dans la sortie).
require __DIR__.'/../../../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$wiki = app(App\Services\WikimediaClient::class);
$cache = new App\Services\Signalling\WikipediaArticleCache($wiki, App\Services\Signalling\WikipediaArticleCache::defaultDirectory());
$out = $argv[1]; $seen = [];
foreach (['"contrôle de vitesse par balises"', 'KVB ligne équipée', '"KVB" mise en service ligne', '"répétition des signaux" crocodile ligne', '"dispositif d\'arrêt automatique des trains" DAAT', '"ETCS niveau 1" ligne mise en service', '"ETCS" "niveau 2" ligne', 'ERTMS ligne mise en service corridor', '"TVM 430" ligne', '"TVM 300"', '"transmission voie-machine" ligne classique', '"KVB" LGV raccordement', 'crocodile "répétition" signaux supprimé', 'KVB "contrôle de vitesse" tram-train', 'DAAT autorails ligne voie unique'] as $q) {
    for ($off = 0; $off < 300; $off += 50) {
        try { $j = $wiki->api(['action' => 'query', 'list' => 'search', 'srsearch' => $q, 'srnamespace' => 0, 'srlimit' => 50, 'sroffset' => $off, 'srprop' => '']); }
        catch (\Throwable $e) { file_put_contents($out, "ERR $q $off {$e->getMessage()}\n", FILE_APPEND); break; }
        $rows = $j['query']['search'] ?? [];
        if ($off === 0) file_put_contents($out, "## $q total ".($j['query']['searchinfo']['totalhits'] ?? '?')."\n", FILE_APPEND);
        foreach ($rows as $r) { $t = $r['title']; if (isset($seen[$t])) continue; $seen[$t] = 1; if (! $cache->has($t)) file_put_contents($out, $t."\n", FILE_APPEND); }
        sleep(5);
        if (count($rows) < 50) break;
    }
}
file_put_contents($out, 'FIN '.count($seen)." titres vus\n", FILE_APPEND);
