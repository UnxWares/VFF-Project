#!/usr/bin/env node
// svelte-check (JavaScript + JSDoc, cf. jsconfig.json) avec une liste
// d'exceptions, comme la baseline PHPStan : les erreurs déjà connues sont
// listées dans svelte-check-baseline.json (par fichier et par message, sans
// numéro de ligne), seule une erreur NOUVELLE fait échouer le contrôle.
// Les avertissements (a11y, CSS inutilisé, state_referenced_locally…) sont
// affichés sans bloquer.
//
//   pnpm check                          # contrôle (CI, make check-front)
//   pnpm check --update-baseline        # régénère la liste d'exceptions
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const baselinePath = join(root, 'svelte-check-baseline.json');
const update = process.argv.includes('--update-baseline');

const require = createRequire(import.meta.url);
const bin = join(dirname(require.resolve('svelte-check/package.json')), 'bin', 'svelte-check');
const run = spawnSync(
    process.execPath,
    [bin, '--workspace', root, '--tsconfig', './jsconfig.json', '--output', 'machine-verbose'],
    {
        cwd: root,
        encoding: 'utf8',
        maxBuffer: 64 * 1024 * 1024,
        // svelte-check charge vite.config.js en mode serveur ; avec CI défini (GitHub Actions), le
        // greffon Laravel refuse ce mode et chaque page Svelte remonte une fausse erreur.
        env: { ...process.env, LARAVEL_BYPASS_ENV_CHECK: '1' },
    },
);
if (run.error) throw run.error;

/** @type {{ type: string, filename: string, start: { line: number, character: number }, message: string, code?: string | number }[]} */
const diagnostics = [];
for (const line of run.stdout.split('\n')) {
    const json = line.replace(/^\d+\s+/, '');
    if (!json.startsWith('{')) continue;
    diagnostics.push(JSON.parse(json));
}
if (!run.stdout.includes('COMPLETED')) {
    process.stderr.write(run.stdout + run.stderr);
    console.error('svelte-check ne s’est pas terminé normalement.');
    process.exit(2);
}

const errors = diagnostics.filter((d) => d.type === 'ERROR');
const warnings = diagnostics.filter((d) => d.type === 'WARNING');
const firstLine = (message) => message.split('\n')[0];

/** Comptes par fichier puis par message (première ligne). */
function count(list) {
    /** @type {Record<string, Record<string, number>>} */
    const out = {};
    for (const d of list) {
        const file = (out[d.filename] ??= {});
        const key = firstLine(d.message);
        file[key] = (file[key] ?? 0) + 1;
    }
    return out;
}

if (update) {
    const sorted = Object.fromEntries(
        Object.entries(count(errors))
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([file, messages]) => [
                file,
                Object.fromEntries(Object.entries(messages).sort(([a], [b]) => a.localeCompare(b))),
            ]),
    );
    writeFileSync(baselinePath, JSON.stringify(sorted, null, 4) + '\n');
    console.log(
        `svelte-check-baseline.json : ${errors.length} erreur(s) existante(s) mise(s) en exception.`,
    );
    process.exit(0);
}

/** @type {Record<string, Record<string, number>>} */
const baseline = existsSync(baselinePath) ? JSON.parse(readFileSync(baselinePath, 'utf8')) : {};
const budget = structuredClone(baseline);
const fresh = [];
for (const d of errors) {
    const left = budget[d.filename]?.[firstLine(d.message)] ?? 0;
    if (left > 0) {
        budget[d.filename][firstLine(d.message)] = left - 1;
    } else {
        fresh.push(d);
    }
}
const fixed = Object.values(budget)
    .flatMap((messages) => Object.values(messages))
    .reduce((a, b) => a + b, 0);

for (const d of warnings) {
    console.log(
        `AVERTISSEMENT ${d.filename}:${d.start.line + 1}:${d.start.character + 1}  ${firstLine(d.message)}`,
    );
}
for (const d of fresh) {
    console.log(`ERREUR ${d.filename}:${d.start.line + 1}:${d.start.character + 1}  ${d.message}`);
}
console.log(
    `\nsvelte-check : ${errors.length} erreur(s) dont ${errors.length - fresh.length} en exception (svelte-check-baseline.json), ` +
        `${fresh.length} nouvelle(s) ; ${warnings.length} avertissement(s).`,
);
if (fixed > 0) {
    console.log(
        `${fixed} exception(s) ne se produisent plus : pnpm check --update-baseline pour alléger la liste.`,
    );
}
process.exit(fresh.length > 0 ? 1 : 0);
