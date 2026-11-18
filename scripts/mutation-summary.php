<?php

/**
 * MSI par classe à partir du journal JSON d'Infection (composer test:mutation).
 *
 *   php scripts/mutation-summary.php [storage/app/reports/infection/infection.json]
 *
 * MSI = tués / (total - ignorés) ; MSI couvert = tués / (couverts). Les mutants
 * « timeout » et « erreur » comptent comme détectés, comme dans Infection.
 */
$log = $argv[1] ?? __DIR__.'/../storage/app/reports/infection/infection.json';
$data = is_file($log) ? json_decode((string) file_get_contents($log), true) : null;
if (! is_array($data)) {
    fwrite(STDERR, "Journal JSON Infection introuvable ou illisible : {$log}\n");
    exit(1);
}

$detected = ['killed', 'timeouted', 'errored', 'syntaxErrors'];
$counted = [...$detected, 'escaped', 'uncovered'];
$classes = [];
foreach ($counted as $kind) {
    foreach ($data[$kind] ?? [] as $entry) {
        $file = (string) ($entry['mutator']['originalFilePath'] ?? '?');
        $class = preg_replace('#^.*/app/#', '', $file) ?? $file;
        $classes[$class] ??= ['detected' => 0, 'escaped' => 0, 'uncovered' => 0];
        $bucket = in_array($kind, $detected, true) ? 'detected' : $kind;
        $classes[$class][$bucket]++;
    }
}
ksort($classes);

printf("%-34s %8s %8s %8s %8s %10s\n", 'Classe', 'Mutants', 'Tués', 'Évadés', 'Non couv.', 'MSI');
$sum = ['detected' => 0, 'escaped' => 0, 'uncovered' => 0];
foreach ($classes as $class => $c) {
    $total = array_sum($c);
    foreach ($sum as $k => $_) {
        $sum[$k] += $c[$k];
    }
    printf("%-34s %8d %8d %8d %8d %9.1f %%\n", $class, $total, $c['detected'], $c['escaped'], $c['uncovered'],
        $total ? 100 * $c['detected'] / $total : 0);
}
$total = array_sum($sum);
printf("%-34s %8d %8d %8d %8d %9.1f %%\n", 'TOTAL', $total, $sum['detected'], $sum['escaped'], $sum['uncovered'],
    $total ? 100 * $sum['detected'] / $total : 0);
