import { compile } from '../../node_modules/svelte/src/compiler/index.js';
import { readFileSync } from 'node:fs';
for (const f of process.argv.slice(2)) {
  try {
    const r = compile(readFileSync(f, 'utf8'), { filename: f, generate: 'client' });
    const w = r.warnings.map((x) => `  ${x.code} l.${x.start?.line}: ${x.message.split('\n')[0]}`);
    console.log(`${f}: ${w.length ? '\n' + w.join('\n') : 'OK'}`);
  } catch (e) { console.log(`${f}: ERREUR ${e.message}`); }
}
