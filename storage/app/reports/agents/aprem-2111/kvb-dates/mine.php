<?php
require __DIR__.'/../../../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = storage_path('app/data/signalisation-historique/wikipedia');
$ex = app(App\Services\Signalling\SignallingHistoryExtractor::class);
$supp = file_get_contents(database_path('data/signalling_history_wikipedia_supplements.php'));
$n=0;
foreach (glob($dir.'/*.json') as $f) {
    $a = json_decode(file_get_contents($f), true);
    if (! $a || ! empty($a['missing']) || empty($a['content'])) continue;
    $facts = $ex->extract($a['content'], $a['title']);
    $have = [];
    foreach ($facts as $x) $have[] = mb_substr($x['excerpt'] ?? '',0,60);
    $text = $ex->plainText($a['content'], $a['title']);
    foreach (preg_split('/\n+/u', $text) as $para) foreach (preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«"(⟦])/u', $para) as $s) {
        $s=trim($s);
        if (mb_strlen($s) > 600) continue;
        if (preg_match('/\bKVB\b|crocodile|\bDAAT\b|contrôle de vitesse par balises|\bTVM\b|\bETCS\b|\bERTMS\b|\bRPS\b|\bSACEM\b|\bNExT\b|\bCAPELEC\b|répétition des signaux/iu', $s) && preg_match('/\b(19[2-9]\d|20[0-2]\d)\b/', $s)) {
            $covered = false;
            foreach ($have as $h) if ($h !== '' && str_contains($s, $h)) $covered = true;
            if (str_contains($supp, mb_substr(str_replace(['⟦','⟧'],'',$s),0,50))) $covered = true;
            echo ($covered?'[F] ':'[ ] ').$a['title'].' | '.$s."\n";
        }
    }
}
