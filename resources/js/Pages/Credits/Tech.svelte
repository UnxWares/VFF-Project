<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

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
                    name: 'i18next + svelte-i18next',
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
                    name: 'PostGIS 3.6',
                    url: 'https://postgis.net',
                    license: 'GPL-2.0+',
                    note: 'postgis',
                },
                {
                    name: 'Valkey 8',
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
                    license: 'CC-BY-SA',
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
                    license: 'CC-BY-SA',
                    note: 'wikipedia',
                },
                {
                    name: 'BNF Gallica',
                    url: 'https://gallica.bnf.fr',
                    licenseKey: 'public_domain',
                    note: 'gallica',
                },
            ],
        },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('credits.tech.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('credits.tech.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('credits.eyebrow')}
            title={$i18n.t('credits.tech.title')}
            subtitle={$i18n.t('credits.tech.subtitle')}
        />

        {#each groups as g (g.key)}
            <SectionBlock title={$i18n.t(`credits.tech.groups.${g.key}`)} maxWidth="900px">
                <ul class="tech-list">
                    {#each g.items as i (i.name)}
                        <li>
                            <a href={i.url} target="_blank" rel="noopener">
                                <strong>{i.name}</strong>
                                <span class="license"
                                    >{i.licenseKey
                                        ? $i18n.t(`credits.tech.licenses.${i.licenseKey}`)
                                        : i.license}</span
                                >
                            </a>
                            {#if i.note}<p class="note">
                                    {$i18n.t(`credits.tech.notes.${i.note}`)}
                                </p>{/if}
                        </li>
                    {/each}
                </ul>
            </SectionBlock>
        {/each}

        <SectionBlock title={$i18n.t('credits.tech.ai.title')} maxWidth="780px">
            <!-- eslint-disable svelte/no-at-html-tags -->
            <p class="paragraph">{@html $i18n.t('credits.tech.ai.p1')}</p>
            <p class="paragraph">{@html $i18n.t('credits.tech.ai.p2')}</p>
            <p class="paragraph">{@html $i18n.t('credits.tech.ai.p3')}</p>
            <p class="paragraph">
                {$i18n.t('credits.tech.ai.repo')}
                <a
                    href="https://github.com/UnxWares/VFF-Rail-Extraction"
                    target="_blank"
                    rel="noopener">VFF-Rail-Extraction</a
                >.
            </p>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('credits.tech.commitment.title')} maxWidth="780px">
            <p class="paragraph">{@html $i18n.t('credits.tech.commitment.p1')}</p>
            <!-- eslint-enable svelte/no-at-html-tags -->
            <p class="paragraph">
                {$i18n.t('credits.tech.commitment.p2')}
                <a href="https://github.com/UnxWares/VFF-Project" target="_blank" rel="noopener"
                    >{$i18n.t('credits.tech.commitment.repo_link')}</a
                >.
            </p>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('credits.tech.cta.title')}</h2>
            <p>{$i18n.t('credits.tech.cta.text')}</p>
            <a href="/donate" class="vff-btn on-cta ink">
                <i class="bi bi-heart-fill"></i>
                {$i18n.t('credits.tech.cta.button')}
            </a>
        </SectionBlock>
    </main>
</Layout>

<style>
    .tech-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 12px;
    }
    .tech-list li {
        padding: 14px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        transition: border-color var(--vff-transition);
    }
    .tech-list li:hover {
        border-color: var(--vff-accent);
    }
    .tech-list a:hover strong {
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
        text-underline-offset: 3px;
    }
    .tech-list a:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 3px;
        border-radius: 2px;
    }
    .tech-list a {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        text-decoration: none;
        color: var(--vff-text);
    }
    @media (max-width: 768px) {
        .tech-list a {
            padding: 4px 0;
        }
    }
    .tech-list strong {
        font-family: var(--vff-font-display);
        font-size: 15px;
    }
    .tech-list .license {
        font-family: var(--vff-font-heading);
        font-size: 12px;
        font-weight: 600;
        color: var(--vff-text);
        background-color: var(--vff-bg-muted);
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        flex-shrink: 0;
    }
    .tech-list .note {
        margin: 6px 0 0;
        font-size: 13.5px;
        line-height: 1.45;
        color: var(--vff-text-muted);
    }
    .paragraph {
        font-size: 16px;
        line-height: 1.65;
        color: var(--vff-text);
        margin: 0 0 14px;
    }
    .paragraph a {
        color: var(--vff-accent-ink);
    }
</style>
