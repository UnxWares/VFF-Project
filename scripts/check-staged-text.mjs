#!/usr/bin/env node
// Contrôle de texte avant commit (crochet Lefthook `pre-commit`), sur les fichiers donnés :
//   node scripts/check-staged-text.mjs dashes <fichiers...>        tirets interdits
//   node scripts/check-staged-text.mjs attribution <fichiers...>   mentions d'attribution IA
//
// « dashes » reprend, ligne à ligne, la règle de tests/Unit/Static/TypographicDashTest.php
// (tiret long, demi-cadratin, « -- » de ponctuation, avec les mêmes tolérances : normalisation
// de sources externes, commentaires SQL, séparateur d'options shell). Le test PHPUnit reste
// l'arbitre (CI) ; ce script en est un préfiltre rapide.
import { readFileSync } from 'node:fs';

const [mode, ...files] = process.argv.slice(2);

const LONG_DASH = /[\u2014\u2013]/u;
const DOUBLE_DASH = /\S -- \S/u;
const NORMALIZATION =
    /preg_|str_replace|strtr|mb_str|split\(|\[[^\]]*[\u2013\u2014\u2012\u2015]|[\u2013\u2014][^\]]*\]|trim\(.*[\u2013\u2014]/u;
const SQL_COMMENT =
    /\b(SELECT|FROM|WHERE|JOIN|AND|OR|NOT|NULL|UNION|ALL|CASE|WHEN|THEN|ELSE|END|GROUP BY|ORDER BY|LIMIT|EXISTS|LATERAL|ON|AS|IN|IS|SET|VALUES|WITH|RETURNING)\b.* -- /u;
const SHELL_SEPARATOR =
    /\b(git|pnpm|npm|npx|podman|docker|exec|xargs|php|artisan|composer|bin\/\w+)\b.* -- /u;

// Mentions d'attribution à un outil d'IA, interdites partout (code, docs, commits).
// Écrites en morceaux pour que ce fichier ne se signale pas lui-même.
const ATTRIBUTION = new RegExp(
    [
        'Co-Authored' + '-By',
        'Generated ' + 'with',
        'Made ' + 'with Claude',
        'Claude' + ' Code',
        'Anthro' + 'pic',
        'claude' + '\\.(com|ai)',
        '\u{1F916}',
    ].join('|'),
    'iu',
);

// Fichiers hors périmètre : ce script, la configuration des crochets, les consignes d'agents.
const IGNORED = /^(scripts\/check-staged-text\.mjs|lefthook\.yml|CLAUDE\.md|\.claude\/|pnpm-lock\.yaml|composer\.lock)/u;
const BINARY = /\.(png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|pdf|zip|gz|mp4|webm|geojson|json|neon)$/iu;

// Périmètre de la règle des tirets : celui de TypographicDashTest (les tests, eux, citent les tirets).
const DASH_SCOPE =
    /^((app|database|docs|routes|config|lang|\.github|resources\/(js|css|views))\/|(README|CONTRIBUTING|ROADMAP|ARCHITECTURE)\.md$|Makefile$)/u;

function dashViolation(path, line) {
    if (!DASH_SCOPE.test(path) || /^resources\/images\/vendor\//u.test(path)) {
        return false;
    }
    if (path.endsWith('.blade.php') && /\{\{--|--\}\}/u.test(line)) {
        return false;
    }
    const dash =
        LONG_DASH.test(line) ||
        (DOUBLE_DASH.test(line) && !SQL_COMMENT.test(line) && !SHELL_SEPARATOR.test(line));

    return dash && !NORMALIZATION.test(line);
}

let failures = 0;
for (const path of files) {
    if (IGNORED.test(path) || BINARY.test(path)) {
        continue;
    }
    let content;
    try {
        content = readFileSync(path, 'utf8');
    } catch {
        continue; // fichier supprimé ou illisible
    }
    content.split('\n').forEach((line, index) => {
        const bad =
            mode === 'dashes' ? dashViolation(path, line) : mode === 'attribution' && ATTRIBUTION.test(line);
        if (bad) {
            failures++;
            console.error(`${path}:${index + 1} : ${line.trim().slice(0, 140)}`);
        }
    });
}

if (failures > 0) {
    console.error(
        mode === 'dashes'
            ? `\n${failures} tiret(s) interdit(s) : utiliser « - » (règle TypographicDashTest).`
            : `\n${failures} mention(s) d'attribution interdite(s) : à retirer.`,
    );
    process.exit(1);
}
