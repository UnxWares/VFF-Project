<script>
    /**
     * Encarts des lignes soutenues par VFF (ligne menacée, campagne de réouverture), sous la
     * présentation de l'accueil. Contenu fixe : pas de role="alert", un encart nommé par son titre.
     * Le type de campagne est écrit en toutes lettres ; la couleur (rouge menacée, turquoise
     * réouverture, comme sur la carte) ne fait que le souligner.
     * Titre, message et bouton viennent de la base (App\Support\Advocacy), avec leur langue.
     * Ordre et forme viennent aussi de la base (`home`, déjà triés par le contrôleur) : encarts complets,
     * puis les mentions courtes (une phrase et le lien vers la fiche), plus discrètes.
     */
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ lines?: Array<{ slug: string, name: string, advocacy?: { kind?: string|null, title?: string|null, message?: string|null, cta_label?: string|null, cta_url?: string|null, lang?: Record<string, string|null> }, home?: { rank?: number|null, display?: string, summary?: string|null, lang?: string|null } }> }} */
    let { lines = [] } = $props();

    const uid = $props.id();

    const items = $derived(
        lines.map((l) => {
            const kind = l.advocacy?.kind === 'reopening' ? 'reopening' : 'threatened';
            return { ...l, kind, a: l.advocacy ?? {} };
        }),
    );
    const cards = $derived(items.filter((i) => i.home?.display !== 'mention' || !i.home?.summary));
    const mentions = $derived(
        items.filter((i) => i.home?.display === 'mention' && i.home?.summary),
    );
</script>

{#if items.length}
    <section class="advocacy" aria-label={$i18n.t('home.advocacy.label')}>
        <!-- Grille seulement s'il y a au moins un encart (sinon une grille vide gardait sa marge). -->
        {#if cards.length}
            <div class={['advocacy-grid', { single: cards.length === 1 }]}>
                {#each cards as item (item.slug)}
                    <section class="plea {item.kind}" aria-labelledby="{uid}-{item.slug}">
                        <p class="kind">
                            <i
                                class="bi {item.kind === 'reopening'
                                    ? 'bi-megaphone'
                                    : 'bi-exclamation-triangle'}"
                                aria-hidden="true"
                            ></i>
                            {$i18n.t(`home.advocacy.kind_${item.kind}`)}
                        </p>
                        <p
                            class="title"
                            id="{uid}-{item.slug}"
                            lang={item.a.lang?.title ?? undefined}
                        >
                            {item.a.title ?? $i18n.t(`home.advocacy.${item.kind}`)}
                        </p>
                        {#if item.a.message}
                            <p class="message" lang={item.a.lang?.message ?? undefined}>
                                {item.a.message}
                            </p>
                        {/if}
                        <div class="actions">
                            <Link class="vff-link" href={`/lines/${item.slug}`}>
                                {$i18n.t('home.advocacy.see_line')}<span class="vff-sr-only">
                                    : {item.name}</span
                                >
                            </Link>
                            {#if item.a.cta_url}
                                <a
                                    class="vff-btn secondary small"
                                    href={item.a.cta_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    lang={item.a.lang?.cta_label ?? undefined}
                                >
                                    {item.a.cta_label || $i18n.t('home.advocacy.act')}<i
                                        class="bi bi-box-arrow-up-right vff-ext"
                                        aria-hidden="true"
                                    ></i><span class="vff-sr-only">
                                        ({$i18n.t('common.new_tab')})</span
                                    >
                                </a>
                            {/if}
                        </div>
                    </section>
                {/each}
            </div>
        {/if}
        {#each mentions as item (item.slug)}
            <p class={['mention', { single: cards.length === 1 }]}>
                <span lang={item.home?.lang ?? undefined}>{item.home?.summary}</span>
                <Link class="vff-link" href={`/lines/${item.slug}`}>
                    {$i18n.t('home.advocacy.see_line')}<span class="vff-sr-only">
                        : {item.name}</span
                    >
                </Link>
            </p>
        {/each}
    </section>
{/if}

<style>
    .advocacy {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto var(--vff-section-space);
        padding-inline: var(--vff-gutter);
    }
    .advocacy-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-block-space);
        /* Encarts côte à côte de même hauteur ; les liens se rangent au pied de chacun. */
        align-items: stretch;
    }
    .advocacy-grid.single {
        grid-template-columns: minmax(0, 760px);
    }
    /* Nombre impair d'encarts : le dernier prend toute la largeur au lieu de laisser un trou. */
    .advocacy-grid:not(.single) > .plea:last-child:nth-child(odd) {
        grid-column: 1 / -1;
    }

    /* Couleur de campagne (railStyle.js : THREATENED_COLOR, REOPENING_COLOR) sur l'icône et le type de
       campagne seulement, assombrie pour le texte sur fond clair (>= 4,5:1) et éclaircie en thème
       sombre. L'encart lui-même est neutre : fond teinté, filet fin, coins arrondis. */
    .plea {
        --plea-ink: var(--vff-danger);
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding: var(--vff-pad-box);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
    }
    .plea.reopening {
        --plea-ink: #0f766e;
    }
    :global([data-theme='dark']) .plea.reopening {
        --plea-ink: #5eead4;
    }

    .kind {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--plea-ink);
    }
    .title {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .message {
        max-width: var(--vff-measure);
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-5);
        margin-top: auto;
        padding-top: var(--vff-space-5);
    }

    /* Mention courte : sous les encarts, sans couleur de campagne ni icône, texte atténué. */
    .mention {
        max-width: var(--vff-measure);
        margin: var(--vff-block-space) 0 0;
        padding: var(--vff-space-3) var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .mention.single {
        max-width: 760px;
    }
    /* Le lien suit la phrase après une espace ; renvoyé à la ligne, il reste au bord gauche. */
    .mention :global(.vff-link) {
        white-space: nowrap;
    }

    @media (max-width: 900px) {
        .advocacy-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .title {
            font-size: 1.125rem;
        }
    }
</style>
