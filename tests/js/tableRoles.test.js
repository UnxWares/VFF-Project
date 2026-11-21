// Rôles ARIA de tableau posés quand le CSS change l'affichage (cartes sur téléphone), rendu serveur
// de DirectoryTable compris.
import { render } from 'svelte/server';
import { createRawSnippet } from 'svelte';
import { describe, expect, it } from 'vitest';
import { roleFor } from '$lib/actions/tableRoles.js';
import DirectoryTable from '../../resources/js/Components/Directory/DirectoryTable.svelte';

describe('roleFor', () => {
    it('ne pose rien tant que l affichage est celui d un tableau', () => {
        expect(roleFor('TABLE', 'table')).toBeNull();
        expect(roleFor('TR', 'table-row')).toBeNull();
        expect(roleFor('TD', 'table-cell')).toBeNull();
        expect(roleFor('TBODY', 'table-row-group')).toBeNull();
    });

    it('pose les rôles quand le CSS passe en block, grid ou flex', () => {
        expect(roleFor('TABLE', 'block')).toBe('table');
        expect(roleFor('TBODY', 'block')).toBe('rowgroup');
        expect(roleFor('THEAD', 'block')).toBe('rowgroup');
        expect(roleFor('TR', 'grid')).toBe('row');
        expect(roleFor('TD', 'flow-root')).toBe('cell');
        expect(roleFor('TH', 'block', { scope: 'col' })).toBe('columnheader');
        expect(roleFor('TH', 'block', { inHead: true })).toBe('columnheader');
        expect(roleFor('TH', 'block', { scope: 'row' })).toBe('rowheader');
    });

    it('laisse les éléments display none et les balises étrangères', () => {
        expect(roleFor('TD', 'none')).toBeNull();
        expect(roleFor('DIV', 'block')).toBeNull();
    });
});

describe('DirectoryTable', () => {
    it('se rend en vrai tableau sans rôle posé côté serveur', () => {
        const children = createRawSnippet(() => ({
            render: () => '<tbody><tr><td>x</td></tr></tbody>',
        }));
        const { body } = render(DirectoryTable, { props: { label: 'L', caption: 'C', children } });
        expect(body).toContain('<table');
        expect(body).not.toContain('role="cell"');
    });
});
