<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    const groups = [
        {
            key: 'backend',
            items: [
                { name: 'PHP 8.5', url: 'https://www.php.net', license: 'PHP License' },
                { name: 'Laravel 13', url: 'https://laravel.com', license: 'MIT' },
                { name: 'Inertia Laravel', url: 'https://inertiajs.com', license: 'MIT' },
                {
                    name: 'Clickbar Magellan',
                    url: 'https://github.com/Clickbar/laravel-magellan',
                    license: 'MIT',
                    note: 'magellan',
                },
                {
                    name: 'Laravel Sanctum',
                    url: 'https://laravel.com/docs/sanctum',
                    license: 'MIT',
                },
                { name: 'Predis', url: 'https://github.com/predis/predis', license: 'MIT' },
                {
                    name: 'Composer',
                    url: 'https://getcomposer.org',
                    license: 'MIT',
                    note: 'composer',
                },
            ],
        },
        {
            key: 'frontend',
            items: [
                { name: 'Svelte 5', url: 'https://svelte.dev', license: 'MIT' },
                { name: 'Inertia Svelte', url: 'https://inertiajs.com', license: 'MIT' },
                {
                    name: 'MapLibre GL JS',
                    url: 'https://maplibre.org',
                    license: 'BSD-3-Clause',
                    note: 'maplibre',
                },
                {
                    name: 'PMTiles',
                    url: 'https://github.com/protomaps/PMTiles',
                    license: 'BSD-3-Clause',
                    note: 'pmtiles',
                },
                {
                    name: 'Protomaps Basemaps',
                    url: 'https://github.com/protomaps/basemaps',
                    license: 'BSD-3-Clause',
                    note: 'protomaps',
                },
                {
                    name: 'i18next',
                    url: 'https://www.i18next.com',
                    license: 'MIT',
                },
                { name: 'Bootstrap Icons', url: 'https://icons.getbootstrap.com', license: 'MIT' },
                { name: 'Vite 8', url: 'https://vitejs.dev', license: 'MIT' },
                {
                    name: 'pnpm',
                    url: 'https://pnpm.io',
                    license: 'MIT',
                    note: 'pnpm',
                },
            ],
        },
        {
            key: 'infra',
            items: [
                { name: 'PostgreSQL 18', url: 'https://www.postgresql.org', license: 'PostgreSQL' },
                {
                    name: 'PostGIS 3.7',
                    url: 'https://postgis.net',
                    license: 'GPL-2.0+',
                    note: 'postgis',
                },
                {
                    name: 'Valkey 9',
                    url: 'https://valkey.io',
                    license: 'BSD-3-Clause',
                    note: 'valkey',
                },
                {
                    name: 'Podman',
                    url: 'https://podman.io',
                    license: 'Apache-2.0',
                    note: 'podman',
                },
                {
                    name: 'Docker / Compose',
                    url: 'https://docker.com',
                    license: 'Apache-2.0',
                    note: 'docker',
                },
                {
                    name: 'NVIDIA Container Toolkit',
                    url: 'https://github.com/NVIDIA/nvidia-container-toolkit',
                    license: 'Apache-2.0',
                    note: 'nvidia',
                },
                {
                    name: 'OpenStreetMap',
                    url: 'https://www.openstreetmap.org',
                    license: 'ODbL',
                    note: 'osm',
                },
                {
                    name: 'Overpass API',
                    url: 'https://overpass-api.de',
                    license: 'AGPL-3.0',
                    note: 'overpass',
                },
            ],
        },
        {
            key: 'ai',
            items: [
                {
                    name: 'Python',
                    url: 'https://www.python.org',
                    license: 'PSF',
                    note: 'python',
                },
                {
                    name: 'PyTorch',
                    url: 'https://pytorch.org',
                    license: 'BSD-3-Clause',
                    note: 'pytorch',
                },
                {
                    name: 'Segmentation Models PyTorch',
                    url: 'https://github.com/qubvel-org/segmentation_models.pytorch',
                    license: 'MIT',
                    note: 'smp',
                },
                {
                    name: 'torchvision (ResNet-34)',
                    url: 'https://pytorch.org/vision',
                    license: 'BSD-3-Clause',
                    note: 'torchvision',
                },
                {
                    name: 'IGN - BD ORTHO historique 1950-1965',
                    url: 'https://geoservices.ign.fr/bdortho-historique',
                    license: 'Licence Ouverte 2.0',
                    note: 'ign_ortho',
                },
            ],
        },
        {
            key: 'quality',
            items: [
                {
                    name: 'PHPUnit + ParaTest',
                    url: 'https://phpunit.de',
                    license: 'BSD-3-Clause',
                    note: 'phpunit',
                },
                {
                    name: 'Infection',
                    url: 'https://infection.github.io',
                    license: 'BSD-3-Clause',
                    note: 'infection',
                },
                {
                    name: 'PHPStan + Larastan',
                    url: 'https://phpstan.org',
                    license: 'MIT',
                    note: 'phpstan',
                },
                { name: 'Laravel Pint', url: 'https://github.com/laravel/pint', license: 'MIT' },
                {
                    name: 'Vitest',
                    url: 'https://vitest.dev',
                    license: 'MIT',
                    note: 'vitest',
                },
                {
                    name: 'ESLint + svelte-check',
                    url: 'https://eslint.org',
                    license: 'MIT',
                    note: 'eslint',
                },
                { name: 'Prettier', url: 'https://prettier.io', license: 'MIT' },
            ],
        },
        {
            key: 'fonts',
            items: [
                {
                    name: 'Gabarito',
                    url: 'https://fonts.google.com/specimen/Gabarito',
                    license: 'OFL',
                },
                { name: 'Lato', url: 'https://fonts.google.com/specimen/Lato', license: 'OFL' },
                { name: 'Inter', url: 'https://rsms.me/inter/', license: 'OFL' },
                {
                    name: 'Montserrat',
                    url: 'https://fonts.google.com/specimen/Montserrat',
                    license: 'OFL',
                },
                {
                    name: 'Noto Sans',
                    url: 'https://notofonts.github.io',
                    license: 'OFL',
                    note: 'noto',
                },
            ],
        },
        {
            key: 'sources',
            items: [
                {
                    name: 'OpenStreetMap',
                    url: 'https://www.openstreetmap.org',
                    license: 'ODbL',
                    note: 'osm_data',
                },
                {
                    name: 'OpenRailwayMap',
                    url: 'https://www.openrailwaymap.org',
                    license: 'CC BY-SA',
                    note: 'orm',
                },
                {
                    name: 'SNCF Réseau - RFN',
                    url: 'https://www.sncf-reseau.com',
                    licenseKey: 'open_data',
                    note: 'rfn',
                },
                {
                    name: 'SNCF Open Data',
                    url: 'https://ressources.data.sncf.com',
                    license: 'ODbL',
                    note: 'sncf_open_data',
                },
                {
                    name: 'IGN - BD TOPO',
                    url: 'https://geoservices.ign.fr/bdtopo',
                    license: 'Licence Ouverte 2.0',
                    note: 'bdtopo',
                },
                {
                    name: 'OpenHistoricalMap',
                    url: 'https://www.openhistoricalmap.org',
                    license: 'CC0',
                    note: 'ohm',
                },
                {
                    name: 'Wikidata',
                    url: 'https://www.wikidata.org',
                    license: 'CC0',
                    note: 'wikidata',
                },
                {
                    name: 'Wikimedia Commons',
                    url: 'https://commons.wikimedia.org',
                    licenseKey: 'per_file',
                    note: 'commons',
                },
                {
                    name: 'archeoferroviaire',
                    url: 'http://archeoferroviaire.free.fr',
                    licenseKey: 'credit_and_link',
                    note: 'archeo',
                },
                {
                    name: 'lignes-oubliees',
                    url: 'https://www.lignes-oubliees.com',
                    licenseKey: 'credit_required',
                    note: 'lignes_oubliees',
                },
                {
                    name: 'Wikipédia',
                    url: 'https://fr.wikipedia.org',
                    license: 'CC BY-SA 4.0',
                    note: 'wikipedia',
                },
                {
                    name: 'BnF Gallica',
                    url: 'https://gallica.bnf.fr',
                    licenseKey: 'bnf',
                    note: 'gallica',
                },
                {
                    name: 'Archives SNCF',
                    url: 'https://www.archives.sncf.com',
                    license: 'ODbL',
                    note: 'sncf_archives',
                },
            ],
        },
    ];

    const byKey = Object.fromEntries(groups.map((g) => [g.key, g]));
    const softwareGroups = ['backend', 'frontend', 'infra', 'quality', 'fonts'].map(
        (k) => byKey[k],
    );
    const sourceItems = byKey.sources.items;
    const mlItems = byKey.ai.items;
</script>

<svelte:head>
    <title>{$i18n.t('credits.tech.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('credits.tech.meta_description')} />
</svelte:head>

{#snippet techList(items, columns = false)}
    <ul class="tech-list" class:columns>
        {#each items as i (i.name)}
            <li>
                <div class="head">
                    <a href={i.url} target="_blank" rel="noopener noreferrer" class="name"
                        >{i.name}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"
                        ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                    >
                    <span class="vff-badge"
                        ><span class="vff-sr-only"
                            >{$i18n.t('credits.tech.name_license')} :
                        </span>{i.licenseKey
                            ? $i18n.t(`credits.tech.licenses.${i.licenseKey}`)
                            : i.license}</span
                    >
                </div>
                {#if i.note}
                    <p class="note">{$i18n.t(`credits.tech.notes.${i.note}`)}</p>
                {/if}
            </li>
        {/each}
    </ul>
{/snippet}

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('credits.tech.title')}
            subtitle={$i18n.t('credits.tech.subtitle')}
        />

        <SectionBlock title={$i18n.t('credits.tech.software_title')} maxWidth="1180px">
            <div class="groups">
                {#each softwareGroups as g (g.key)}
                    <div class="group">
                        <h3>{$i18n.t(`credits.tech.groups.${g.key}`)}</h3>
                        {@render techList(g.items)}
                    </div>
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('credits.tech.sources_title')} maxWidth="1180px">
            {@render techList(sourceItems, true)}
        </SectionBlock>

        <SectionBlock title={$i18n.t('credits.tech.ai.title')} maxWidth="1180px">
            <div class="ml">
                <div class="vff-prose">
                    <!-- eslint-disable svelte/no-at-html-tags -->
                    <p>{@html $i18n.t('credits.tech.ai.p1')}</p>
                    <p>{@html $i18n.t('credits.tech.ai.p2')}</p>
                    <p>{@html $i18n.t('credits.tech.ai.p3')}</p>
                    <!-- eslint-enable svelte/no-at-html-tags -->
                    <p>
                        {$i18n.t('credits.tech.ai.repo')}
                        <a
                            href="https://github.com/UnxWares/VFF-Rail-Extraction"
                            target="_blank"
                            rel="noopener noreferrer"
                            >VFF-Rail-Extraction<i
                                class="bi bi-box-arrow-up-right vff-ext"
                                aria-hidden="true"
                            ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                        >.
                    </p>
                </div>
                <div class="group">
                    <h3>{$i18n.t('credits.tech.groups.ai')}</h3>
                    {@render techList(mlItems)}
                </div>
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('credits.tech.commitment.title')} maxWidth="780px">
            <div class="vff-prose">
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <p>{@html $i18n.t('credits.tech.commitment.p1')}</p>
                <p>
                    {$i18n.t('credits.tech.commitment.p2')}
                    <a
                        href="https://github.com/UnxWares/VFF-Project"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{$i18n.t('credits.tech.commitment.repo_link')}<i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                    >.
                </p>
            </div>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('credits.tech.cta.title')}
            intro={$i18n.t('credits.tech.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    {$i18n.t('credits.tech.cta.button')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Les groupes se placent côte à côte sur grand écran : la page ne s'allonge pas d'une liste à l'autre. */
    .groups {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-section-space) 64px;
        align-items: start;
    }
    .group h3 {
        margin: 0 0 var(--vff-space-3);
    }
    .ml {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: var(--vff-block-space) 64px;
        align-items: start;
    }

    /* Liste de crédits : une ligne par outil, filet entre deux lignes. Seul le nom est un lien. */
    .tech-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .tech-list li {
        padding: 12px 0;
        border-top: 1px solid var(--vff-border);
    }
    .group .tech-list li:last-child {
        border-bottom: 1px solid var(--vff-border);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-1) var(--vff-space-3);
    }
    .name {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        min-width: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration-color: var(--vff-border-control);
        text-underline-offset: 0.2em;
    }
    .name:hover {
        color: var(--vff-accent-ink);
        text-decoration-color: currentColor;
    }
    .note {
        margin: 4px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.45;
        color: var(--vff-text-muted);
    }
    /* Liste des sources : deux colonnes sur grand écran. */
    @media (min-width: 901px) {
        .tech-list.columns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 64px;
        }
        .tech-list.columns li:nth-last-child(-n + 2) {
            border-bottom: 1px solid var(--vff-border);
        }
    }

    @media (max-width: 900px) {
        .groups,
        .ml {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
