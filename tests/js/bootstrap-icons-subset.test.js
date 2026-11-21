// Police d'icônes réduite (scripts/bootstrap-icons/subset.sh) : chaque icône utilisée par le site doit être
// dans la feuille réduite ET dans la police réduite. Après l'ajout d'une icône dans le code, ce test échoue
// tant que le script n'a pas été relancé.
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { brotliDecompressSync } from 'node:zlib';
import { describe, expect, it } from 'vitest';
import {
    SUBSET_CSS,
    SUBSET_FONT,
    findBuiltNames,
    iconMap,
    usedIcons,
} from '../../scripts/bootstrap-icons/used-icons.mjs';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));
const FULL_FONT = 'node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff2';

/** Entier WOFF2 « UIntBase128 » : 7 bits par octet, le bit fort annonce la suite. */
function readBase128(bytes, at) {
    let value = 0;
    for (let i = 0; i < 5; i += 1) {
        const byte = bytes[at.offset++];
        value = value * 128 + (byte & 0x7f);
        if (!(byte & 0x80)) return value;
    }
    throw new Error('UIntBase128 trop long');
}

/** Table `cmap` d'une police WOFF2 (une seule police, sans collection), décompressée. */
function woff2Cmap(path) {
    const bytes = readFileSync(join(ROOT, path));
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    if (view.getUint32(0) !== 0x774f4632) throw new Error(`${path} n'est pas une police WOFF2`);
    const numTables = view.getUint16(12);
    const compressedSize = view.getUint32(20);
    // Index des étiquettes connues de la spécification WOFF2 : 0 = cmap, 10 = glyf, 11 = loca, 3 = hmtx.
    const at = { offset: 48 };
    const tables = [];
    for (let i = 0; i < numTables; i += 1) {
        const flags = bytes[at.offset++];
        const tagIndex = flags & 0x3f;
        if (tagIndex === 63) at.offset += 4;
        const transform = flags >> 6;
        const origLength = readBase128(bytes, at);
        const transformed = tagIndex === 10 || tagIndex === 11 ? transform !== 3 : transform !== 0;
        const length = transformed ? readBase128(bytes, at) : origLength;
        tables.push({ tagIndex, length });
    }
    const data = brotliDecompressSync(bytes.subarray(at.offset, at.offset + compressedSize));
    let start = 0;
    for (const table of tables) {
        if (table.tagIndex === 0) return data.subarray(start, start + table.length);
        start += table.length;
    }
    throw new Error(`${path} : table cmap absente`);
}

/** Points de code reliés à un glyphe (sous-tables de format 4 et 12). */
function mappedCodepoints(cmap) {
    const view = new DataView(cmap.buffer, cmap.byteOffset, cmap.byteLength);
    const codes = new Set();
    for (let i = 0; i < view.getUint16(2); i += 1) {
        const offset = view.getUint32(4 + i * 8 + 4);
        const format = view.getUint16(offset);
        if (format === 4) {
            const segX2 = view.getUint16(offset + 6);
            const ends = offset + 14;
            const starts = ends + segX2 + 2;
            const deltas = starts + segX2;
            const ranges = deltas + segX2;
            for (let s = 0; s < segX2; s += 2) {
                const end = view.getUint16(ends + s);
                const first = view.getUint16(starts + s);
                const delta = view.getInt16(deltas + s);
                const rangeOffset = view.getUint16(ranges + s);
                for (let c = first; c <= end && c !== 0xffff; c += 1) {
                    const glyph = rangeOffset
                        ? view.getUint16(ranges + s + rangeOffset + (c - first) * 2)
                        : (c + delta) & 0xffff;
                    if (glyph) codes.add(c);
                }
            }
        } else if (format === 12) {
            for (let g = 0; g < view.getUint32(offset + 12); g += 1) {
                const group = offset + 16 + g * 12;
                for (let c = view.getUint32(group); c <= view.getUint32(group + 4); c += 1) {
                    codes.add(c);
                }
            }
        }
    }
    return codes;
}

/** Règles d'icônes de la feuille réduite : nom → point de code. */
function subsetRules() {
    const css = readFileSync(join(ROOT, SUBSET_CSS), 'utf8');
    const rules = new Map();
    for (const [, name, code] of css.matchAll(
        /\.bi-([a-z0-9-]+)::before\s*\{\s*content:\s*['"]\\([0-9a-f]+)['"];?\s*\}/g,
    )) {
        rules.set(name, parseInt(code, 16));
    }
    return rules;
}

describe('police d icônes réduite', () => {
    const used = usedIcons(ROOT);
    const codes = iconMap(ROOT);

    it('recense les icônes du site, écrites en entier dans le code', () => {
        expect(findBuiltNames(ROOT)).toEqual([]);
        // Repères : barre, pied de page, fiches, formulaires.
        for (const name of ['list', 'x-lg', 'moon-stars', 'github', 'train-front', 'eye-slash']) {
            expect(used.has(name), name).toBe(true);
        }
    });

    it('lit la table cmap de la police complète (contrôle du lecteur)', () => {
        const full = mappedCodepoints(woff2Cmap(FULL_FONT));
        for (const code of Object.values(codes)) expect(full.has(code)).toBe(true);
    });

    it('garde dans la feuille réduite une règle pour chaque icône utilisée', () => {
        const rules = subsetRules();
        const missing = [...used.keys()].filter((name) => rules.get(name) !== codes[name]);
        expect(
            missing,
            `relancer scripts/bootstrap-icons/subset.sh (manque : ${missing.join(', ')})`,
        ).toEqual([]);
    });

    it('garde dans la police réduite le glyphe de chaque icône utilisée', () => {
        const kept = mappedCodepoints(woff2Cmap(SUBSET_FONT));
        const missing = [...used]
            .filter(([name]) => !kept.has(codes[name]))
            .map(([name, file]) => `bi-${name} (${file})`);
        expect(
            missing,
            `relancer scripts/bootstrap-icons/subset.sh (manque : ${missing.join(', ')})`,
        ).toEqual([]);
        // Et presque rien d'autre : la police réduite reste petite.
        expect(kept.size).toBeLessThan(used.size + 10);
    });

    it('sert la feuille et la police réduites, plus la police complète', () => {
        const general = readFileSync(join(ROOT, 'resources/css/general.css'), 'utf8');
        expect(general).toContain("@import url('./bootstrap-icons-subset.css');");
        expect(general).not.toContain('bootstrap-icons/font/bootstrap-icons');
        const subset = readFileSync(join(ROOT, SUBSET_CSS), 'utf8');
        expect(subset).toMatch(
            /url\(['"]\.\.\/fonts\/bootstrap-icons-subset\.woff2['"]\) format\(['"]woff2['"]\)/,
        );
        expect(subset).not.toMatch(/fonts\/bootstrap-icons\.woff/);
    });
});
