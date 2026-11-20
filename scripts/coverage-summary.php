<?php

/**
 * Synthèse de couverture PHPUnit par dossier de app/ (lignes et méthodes),
 * à partir du rapport Clover produit par `composer test:coverage`.
 *
 *   php scripts/coverage-summary.php [storage/app/reports/coverage/clover.xml]
 *
 * Un fichier compte dans le groupe dont le préfixe est le plus long
 * (Http/Controllers/Api avant Http/Controllers). Les dossiers absents
 * (Policies, Requests…) sont signalés comme tels.
 */
$clover = $argv[1] ?? __DIR__.'/../storage/app/reports/coverage/clover.xml';
if (! is_file($clover)) {
    fwrite(STDERR, "Rapport Clover introuvable : {$clover} (lancer d'abord composer test:coverage)\n");
    exit(1);
}

$groups = [
    'Http/Controllers/Api', 'Http/Controllers', 'Http/Middleware', 'Http/Requests', 'GraphQL',
    'Models', 'Services', 'Console/Commands', 'Support', 'Enums', 'Policies', 'Providers',
];
usort($groups, fn ($a, $b) => strlen($b) <=> strlen($a));

$xml = simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "Rapport Clover illisible : {$clover}\n");
    exit(1);
}

$totals = [];
foreach ($xml->xpath('//file') as $file) {
    $path = str_replace('\\', '/', (string) $file['name']);
    // Chemin relatif à app/ : racine du projet sur l'hôte, ou /app dans le conteneur.
    $relative = $path;
    foreach ([str_replace('\\', '/', dirname(__DIR__)).'/app/', '/app/app/'] as $root) {
        if (str_starts_with($path, $root)) {
            $relative = substr($path, strlen($root));
            break;
        }
    }

    $group = 'Autres';
    foreach ($groups as $candidate) {
        if (str_starts_with($relative, $candidate.'/')) {
            $group = $candidate;
            break;
        }
    }

    $m = $file->metrics;
    $totals[$group] ??= ['files' => 0, 'lines' => 0, 'covered_lines' => 0, 'methods' => 0, 'covered_methods' => 0];
    $totals[$group]['files']++;
    $totals[$group]['lines'] += (int) $m['statements'];
    $totals[$group]['covered_lines'] += (int) $m['coveredstatements'];
    $totals[$group]['methods'] += (int) $m['methods'];
    $totals[$group]['covered_methods'] += (int) $m['coveredmethods'];
}

$pct = fn (int $covered, int $total): string => $total === 0 ? '   -  ' : sprintf('%5.1f %%', 100 * $covered / $total);

printf("%-22s %6s %16s %10s %16s %10s\n", 'Dossier', 'Fich.', 'Lignes', '%', 'Méthodes', '%');
$sum = ['files' => 0, 'lines' => 0, 'covered_lines' => 0, 'methods' => 0, 'covered_methods' => 0];
$order = ['Http/Controllers', 'Http/Controllers/Api', 'Http/Middleware', 'Http/Requests', 'GraphQL', 'Models',
    'Services', 'Console/Commands', 'Support', 'Enums', 'Policies', 'Providers', 'Autres'];
foreach ($order as $group) {
    if (! isset($totals[$group])) {
        if (in_array($group, ['Policies', 'Http/Requests'], true)) {
            printf("%-22s %s\n", $group, '(dossier absent)');
        }

        continue;
    }
    $t = $totals[$group];
    foreach ($sum as $k => $_) {
        $sum[$k] += $t[$k];
    }
    printf("%-22s %6d %7d / %-7d %10s %7d / %-7d %10s\n", $group, $t['files'],
        $t['covered_lines'], $t['lines'], $pct($t['covered_lines'], $t['lines']),
        $t['covered_methods'], $t['methods'], $pct($t['covered_methods'], $t['methods']));
}
printf("%-22s %6d %7d / %-7d %10s %7d / %-7d %10s\n", 'TOTAL', $sum['files'],
    $sum['covered_lines'], $sum['lines'], $pct($sum['covered_lines'], $sum['lines']),
    $sum['covered_methods'], $sum['methods'], $pct($sum['covered_methods'], $sum['methods']));
