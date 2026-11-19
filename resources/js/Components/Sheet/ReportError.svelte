<script>
    /**
     * « Signaler une erreur » en bas d'une fiche (ligne, gare, matériel, accident) : un bouton
     * discret qui ouvre une fenêtre modale native avec un formulaire court (ce qui est faux, une
     * source, une adresse facultative). Ouvert aux visiteurs comme aux membres connectés.
     *
     *   <ReportError type="line" slug={line.slug} name={line.name} />
     *
     * Anti-abus sans service externe : à l'ouverture, la fenêtre demande un jeton daté
     * (GET /reports/token) qui impose une durée minimale de remplissage ; un champ piège caché
     * (`homepage`) et une limite de débit côté serveur complètent le dispositif. Aucun traceur.
     */
    import { Link, page, useForm } from '@inertiajs/svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { focusFirstError } from '$pages/Auth/authForm.js';
    import { DESCRIPTION_MAX, reportErrors } from '$lib/errorReport.js';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';

    /** @type {{ type: 'line'|'station'|'material'|'accident', slug: string, name: string }} */
    let { type, slug, name } = $props();

    const FIELDS = ['report-description', 'report-source', 'report-contact'];
    /** Champs contrôlés avant l'envoi (reportErrors), dans l'ordre d'affichage. */
    const CHECKED = /** @type {const} */ (['description', 'source_url', 'contact_email']);

    /** @type {HTMLDialogElement|undefined} */
    let dialog = $state();
    let sent = $state(false);
    let tokenFailed = $state(false);
    /** Compte suspendu : le serveur refuse les signalements, la fenêtre l'annonce au lieu du formulaire. */
    const suspended = $derived(page.props.auth?.user?.suspended === true);

    const form = useForm({
        subject_type: '',
        subject_slug: '',
        description: '',
        source_url: '',
        contact_email: '',
        homepage: '',
        form_token: '',
    });

    /**
     * Traduction en texte simple (les props des composants attendent une chaîne).
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     * @returns {string}
     */
    const t = (key, opts) => String($i18n.t(key, opts));
    /** Erreur générale (jeton, limite de débit, fiche disparue), hors des champs. */
    const formError = $derived(/** @type {Record<string, string>} */ (form.errors).form);
    const optional = $derived(` (${t('reports.dialog.optional')})`);

    async function open() {
        form.reset();
        form.clearErrors();
        form.subject_type = type;
        form.subject_slug = slug;
        sent = false;
        tokenFailed = false;
        dialog?.showModal();
        try {
            const response = await fetch('/reports/token', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            form.form_token = (await response.json()).token ?? '';
        } catch {
            tokenFailed = true;
        }
    }

    function submit(e) {
        e.preventDefault();
        form.clearErrors();
        const errors = reportErrors(form);
        if (Object.keys(errors).length) {
            for (const field of CHECKED) {
                if (errors[field]) form.setError(field, t(errors[field].key, errors[field].params));
            }
            focusFirstError(FIELDS);
            return;
        }
        form.post('/reports', {
            preserveScroll: true,
            preserveState: true,
            // Rien à recharger de la fiche : seules les erreurs éventuelles reviennent.
            only: ['errors'],
            onSuccess: () => {
                if (!form.hasErrors) sent = true;
            },
            onError: () => focusFirstError(FIELDS),
        });
    }
</script>

<div class="report-error">
    <div class="inner">
        <p>{t('reports.prompt')}</p>
        <button type="button" class="vff-btn secondary small" onclick={open}>
            <i class="bi bi-flag" aria-hidden="true"></i>
            {t('reports.button')}
        </button>
    </div>
</div>

<dialog
    bind:this={dialog}
    class="report-dialog"
    aria-labelledby={sent ? 'report-thanks-title' : 'report-title'}
>
    {#if sent}
        <div class="body">
            <!-- Focus sur le titre dès son affichage : le remerciement est lu aussitôt. -->
            <h2 id="report-thanks-title" tabindex="-1" {@attach (el) => el.focus()}>
                {t('reports.dialog.thanks_title')}
            </h2>
            <p>{t('reports.dialog.thanks_text')}</p>
            <div class="actions">
                <button type="button" class="vff-btn primary" onclick={() => dialog?.close()}>
                    {t('reports.dialog.close')}
                </button>
            </div>
        </div>
    {:else if suspended}
        <div class="body">
            <h2 id="report-title">{t('reports.dialog.title')}</h2>
            <Notice tone="warning" live="off">{t('moderation.report.suspended')}</Notice>
            <div class="actions">
                <Link class="vff-btn primary" href="/profile"
                    >{t('moderation.report.profile_link')}</Link
                >
                <button type="button" class="vff-btn secondary" onclick={() => dialog?.close()}>
                    {t('reports.dialog.close')}
                </button>
            </div>
        </div>
    {:else}
        <form class="body" onsubmit={submit} novalidate>
            <div class="head">
                <h2 id="report-title">{t('reports.dialog.title')}</h2>
                <!-- Nom de la fiche en français, quelle que soit la langue de la page. -->
                <p class="subject">
                    {t('reports.dialog.subject')}{colon($i18n.language)}<span lang="fr">{name}</span
                    >
                </p>
                <p class="intro">{t('reports.dialog.intro')}</p>
            </div>

            {#if tokenFailed}
                <Notice tone="warning">{t('reports.errors.token')}</Notice>
            {/if}
            <div class="form-error" aria-live="assertive">
                {#if formError}<Notice tone="danger" live="off">{formError}</Notice>{/if}
            </div>

            <Field
                id="report-description"
                label={t('reports.dialog.description_label')}
                hint={t('reports.dialog.description_hint')}
                error={form.errors.description}
                required
            >
                {#snippet children(attrs)}
                    <!-- svelte-ignore a11y_autofocus -->
                    <textarea
                        {...attrs}
                        name="description"
                        class="vff-textarea"
                        rows="5"
                        maxlength={DESCRIPTION_MAX}
                        required
                        autofocus
                        bind:value={form.description}
                    ></textarea>
                {/snippet}
            </Field>

            <Field
                id="report-source"
                label={t('reports.dialog.source_label') + optional}
                hint={t('reports.dialog.source_hint')}
                error={form.errors.source_url}
            >
                {#snippet children(attrs)}
                    <input
                        {...attrs}
                        name="source_url"
                        class="vff-input"
                        type="url"
                        inputmode="url"
                        maxlength="500"
                        placeholder="https://"
                        bind:value={form.source_url}
                    />
                {/snippet}
            </Field>

            <Field
                id="report-contact"
                label={t('reports.dialog.contact_label') + optional}
                hint={t('reports.dialog.contact_hint')}
                error={form.errors.contact_email}
            >
                {#snippet children(attrs)}
                    <input
                        {...attrs}
                        name="contact_email"
                        class="vff-input"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        bind:value={form.contact_email}
                    />
                {/snippet}
            </Field>

            <!-- Champ piège : invisible et hors du parcours au clavier ; un robot le remplit. -->
            <div class="trap" aria-hidden="true">
                <label for="report-homepage">{t('reports.dialog.honeypot_label')}</label>
                <input
                    id="report-homepage"
                    name="homepage"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                    bind:value={form.homepage}
                />
            </div>

            <p class="privacy">
                {t('reports.dialog.privacy')}
                <Link href="/privacy">{t('reports.dialog.privacy_link')}</Link>
            </p>

            <div class="actions">
                <button type="button" class="vff-btn secondary" onclick={() => dialog?.close()}>
                    {t('reports.dialog.cancel')}
                </button>
                <button type="submit" class="vff-btn primary" disabled={form.processing}>
                    {t('reports.dialog.submit')}
                </button>
            </div>
        </form>
    {/if}
</dialog>

<style>
    /* Même grille que SheetSection : bord gauche commun avec le reste de la fiche. */
    .report-error {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: var(--vff-section-space) auto 0;
        padding: 0 var(--vff-gutter);
    }
    .inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-4);
        padding-top: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    .report-error p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }

    .report-dialog {
        width: min(600px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100dvh - 32px);
        padding: 0;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
    }
    .report-dialog:modal {
        overscroll-behavior: contain;
    }
    .report-dialog::backdrop {
        overflow: hidden;
        overscroll-behavior: contain;
        background-color: rgb(0 0 0 / 0.55);
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding: var(--vff-space-6);
    }
    .head {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }
    h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        line-height: var(--vff-lh-heading);
        text-wrap: balance;
    }
    h2:focus {
        outline: none;
    }
    .body p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
    }
    .subject {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .intro,
    .body .privacy {
        color: var(--vff-text-muted);
    }
    .body .privacy {
        font-size: var(--vff-fs-sm);
    }
    .form-error:empty {
        display: none;
    }
    /* Hors de l'écran plutôt que display: none, que certains robots savent repérer. */
    .trap {
        position: absolute;
        left: -10000px;
        width: 1px;
        height: 1px;
        overflow: hidden;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--vff-space-3);
    }

    @media (max-width: 600px) {
        .body {
            padding: var(--vff-space-5) var(--vff-space-4);
        }
        .actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>
