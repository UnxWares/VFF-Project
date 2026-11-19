<script>
    import { Link, useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { focusFirstError } from './authForm.js';
    import { LOCALES, i18n } from '$lib/i18n.js';

    let { profile } = $props();

    // PUT pour matcher la route REST `Route::put('/profile', ...)`.
    // Inertia transmet automatiquement _method=PUT en POST + CSRF. Valeurs de départ du formulaire.
    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: profile.name ?? '',
        bio: profile.bio ?? '',
        locale_preference: profile.locale_preference ?? 'fr',
    });

    const FIELDS = ['name', 'bio', 'locale'];
    const ROLE_TONES = {
        visitor: 'muted',
        contributor: 'info',
        validator: 'success',
        admin: 'accent',
    };

    let flash = $derived(page.props.flash?.success ?? null);
    let roleLabel = $derived($i18n.t(`auth.roles.${profile.role}`));
    let memberSince = $derived(
        profile.created_at
            ? new Date(profile.created_at).toLocaleDateString($i18n.language, {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : null,
    );

    // Suppression du compte (DELETE /profile), confirmée par le mot de passe dans une fenêtre
    // modale native : le navigateur piège le focus, ferme à Échap et rend le focus au bouton.
    const deleteForm = useForm({ password: '' });
    let deleteDialog = $state();

    function openDelete() {
        deleteForm.reset();
        deleteForm.clearErrors();
        deleteDialog.showModal();
    }

    function confirmDelete(e) {
        e.preventDefault();
        deleteForm.clearErrors();
        if (!deleteForm.password) {
            deleteForm.setError('password', $i18n.t('account.data.delete_password_required'));
            focusFirstError(['delete-password']);
            return;
        }
        deleteForm.delete('/profile', {
            preserveScroll: true,
            onError: () => focusFirstError(['delete-password']),
        });
    }

    function submit(e) {
        e.preventDefault();
        form.clearErrors();
        if (!form.name.trim()) {
            form.setError('name', $i18n.t('auth.errors.name_required'));
            focusFirstError(FIELDS);
            return;
        }
        form.put('/profile', {
            preserveScroll: true,
            onError: () => focusFirstError(FIELDS),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.profile_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('auth.profile_title')}
            subtitle={$i18n.t('auth.profile_subtitle')}
        />

        <div class="vff-container profile-page">
            <div class="profile-grid">
                <aside class="identity" aria-labelledby="identity-name">
                    {#if profile.avatar_path}
                        <img
                            class="avatar"
                            src={profile.avatar_path}
                            alt=""
                            width="96"
                            height="96"
                        />
                    {:else}
                        <i class="bi bi-person-circle avatar-icon" aria-hidden="true"></i>
                    {/if}
                    <h2 id="identity-name">{profile.name}</h2>
                    <p class="email">{profile.email}</p>

                    <dl class="meta">
                        <div>
                            <dt>{$i18n.t('auth.role_label')}</dt>
                            <dd>
                                <span class="vff-badge {ROLE_TONES[profile.role] ?? 'muted'}"
                                    >{roleLabel}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('auth.reputation_label')}</dt>
                            <dd>{profile.reputation.toLocaleString($i18n.language)}</dd>
                        </div>
                        {#if memberSince}
                            <div>
                                <dt>{$i18n.t('auth.member_since')}</dt>
                                <dd><time datetime={profile.created_at}>{memberSince}</time></dd>
                            </div>
                        {/if}
                    </dl>

                    <Link class="vff-link" href="/contribute/mine"
                        >{$i18n.t('auth.my_contributions')}</Link
                    >
                </aside>

                <section class="edit" aria-labelledby="edit-title">
                    <h2 id="edit-title" class="vff-section-title">{$i18n.t('auth.edit_title')}</h2>

                    <div aria-live="polite">
                        {#if flash}
                            <Notice tone="success" live="off">{flash}</Notice>
                        {/if}
                    </div>

                    <form class="vff-panel" onsubmit={submit} novalidate>
                        <Field
                            id="name"
                            label={$i18n.t('auth.name_label')}
                            hint={$i18n.t('auth.name_hint')}
                            error={form.errors.name}
                            required
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    name="name"
                                    class="vff-input"
                                    type="text"
                                    autocomplete="name"
                                    maxlength="255"
                                    required
                                    bind:value={form.name}
                                />
                            {/snippet}
                        </Field>

                        <Field
                            id="bio"
                            label={$i18n.t('auth.bio_label')}
                            hint={$i18n.t('auth.bio_hint')}
                            error={form.errors.bio}
                        >
                            {#snippet children(attrs)}
                                <textarea
                                    {...attrs}
                                    name="bio"
                                    class="vff-textarea"
                                    rows="5"
                                    maxlength="2000"
                                    bind:value={form.bio}
                                ></textarea>
                            {/snippet}
                        </Field>

                        <Field
                            id="locale"
                            label={$i18n.t('auth.locale_label')}
                            hint={$i18n.t('auth.locale_hint')}
                            error={form.errors.locale_preference}
                        >
                            {#snippet children(attrs)}
                                <select
                                    {...attrs}
                                    name="locale_preference"
                                    class="vff-select"
                                    bind:value={form.locale_preference}
                                >
                                    {#each LOCALES as l (l.code)}
                                        <option value={l.code} lang={l.code}>{l.name}</option>
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>

                        <div class="actions">
                            <button
                                type="submit"
                                class="vff-btn primary"
                                disabled={form.processing}
                            >
                                <i class="bi bi-floppy" aria-hidden="true"></i>
                                {$i18n.t('auth.save_profile')}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="my-data" aria-labelledby="data-title">
                    <h2 id="data-title" class="vff-section-title">
                        {$i18n.t('account.data.title')}
                    </h2>
                    <p class="data-intro">
                        {$i18n.t('account.data.intro')}
                        <Link href="/privacy">{$i18n.t('account.data.privacy_link')}</Link>
                    </p>

                    <div class="vff-panel data-panel">
                        <div class="data-row">
                            <h3>{$i18n.t('account.data.export_title')}</h3>
                            <p>{$i18n.t('account.data.export_text')}</p>
                            <!-- Lien ordinaire, pas Inertia : la réponse est un fichier JSON. -->
                            <a class="vff-btn secondary" href="/profile/data" download>
                                <i class="bi bi-download" aria-hidden="true"></i>
                                {$i18n.t('account.data.export_button')}
                            </a>
                        </div>
                        <div class="data-row danger-zone">
                            <h3>{$i18n.t('account.data.delete_title')}</h3>
                            <p>{$i18n.t('account.data.delete_text')}</p>
                            <button type="button" class="vff-btn danger" onclick={openDelete}>
                                {$i18n.t('account.data.delete_button')}
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <dialog
            bind:this={deleteDialog}
            class="delete-dialog"
            aria-labelledby="delete-title"
            aria-describedby="delete-text"
        >
            <form onsubmit={confirmDelete} novalidate>
                <h2 id="delete-title">{$i18n.t('account.data.delete_confirm_title')}</h2>
                <p id="delete-text">{$i18n.t('account.data.delete_confirm_text')}</p>

                <Field
                    id="delete-password"
                    label={$i18n.t('account.data.delete_password_label')}
                    error={deleteForm.errors.password}
                >
                    {#snippet children(attrs)}
                        <!-- svelte-ignore a11y_autofocus -->
                        <input
                            {...attrs}
                            name="password"
                            class="vff-input"
                            type="password"
                            autocomplete="current-password"
                            required
                            autofocus
                            bind:value={deleteForm.password}
                        />
                    {/snippet}
                </Field>

                <div class="dialog-actions">
                    <button
                        type="button"
                        class="vff-btn secondary"
                        onclick={() => deleteDialog.close()}
                    >
                        {$i18n.t('account.data.delete_cancel')}
                    </button>
                    <button type="submit" class="vff-btn danger" disabled={deleteForm.processing}>
                        {$i18n.t('account.data.delete_confirm_button')}
                    </button>
                </div>
            </form>
        </dialog>
    </main>
</Layout>

<style>
    .profile-page {
        padding-block: var(--vff-section-space);
    }
    .profile-grid {
        display: grid;
        grid-template-columns: minmax(220px, 280px) minmax(0, 640px);
        gap: var(--vff-block-space) 64px;
        align-items: start;
    }

    /* Identité : une fiche à filets, sans cadre. */
    .identity {
        min-width: 0;
    }
    .avatar-icon {
        display: block;
        font-size: 72px;
        line-height: 1;
        color: var(--vff-text-muted);
    }
    .avatar {
        display: block;
        width: 96px;
        height: 96px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--vff-border-strong);
    }
    .identity h2 {
        margin: 16px 0 4px;
        font-size: 24px;
        line-height: var(--vff-lh-heading);
        overflow-wrap: anywhere;
    }
    .email {
        margin: 0 0 24px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .meta {
        margin: 0 0 20px;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .meta > div {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .meta dt {
        color: var(--vff-text-muted);
    }
    .meta dd {
        margin: 0;
        font-weight: 600;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    /* Formulaire. */
    .edit {
        min-width: 0;
    }
    .edit :global(.vff-section-title) {
        margin: 0 0 var(--vff-space-5);
    }
    .edit div[aria-live] :global(.vff-notice) {
        margin-bottom: var(--vff-space-5);
    }
    form {
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 28px;
    }
    .actions {
        margin-top: 4px;
    }

    /* Mes données : sous le formulaire, dans la même colonne. */
    .my-data {
        grid-column: 2;
        min-width: 0;
        margin-top: var(--vff-block-space);
    }
    .my-data :global(.vff-section-title) {
        margin: 0 0 var(--vff-space-3);
    }
    .data-intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
    }
    .data-panel {
        padding-block: 0;
    }
    .data-row {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-3);
        padding-block: var(--vff-pad-box);
    }
    /* La suppression est séparée de l'export par un filet, son bouton porte la couleur d'erreur. */
    .data-row + .data-row {
        border-top: 1px solid var(--vff-border);
    }
    .data-row h3 {
        margin: 0;
    }
    .data-row p {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-1);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Fenêtre de confirmation de la suppression. */
    .delete-dialog {
        width: min(520px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100dvh - 32px);
        padding: 0;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
    }
    .delete-dialog:modal {
        overscroll-behavior: contain;
    }
    .delete-dialog::backdrop {
        overflow: hidden;
        overscroll-behavior: contain;
        background-color: rgb(0 0 0 / 0.55);
    }
    .delete-dialog form {
        gap: var(--vff-space-5);
    }
    .delete-dialog h2 {
        margin: 0;
        font-size: var(--vff-fs-h3);
        font-family: var(--vff-font-display);
        text-wrap: balance;
    }
    .delete-dialog p {
        margin: 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .dialog-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--vff-space-3);
    }

    @media (max-width: 900px) {
        .profile-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .identity {
            max-width: 420px;
        }
        .my-data {
            grid-column: auto;
        }
    }
    @media (max-width: 600px) {
        form {
            padding: 20px 16px;
        }
        .actions :global(.vff-btn),
        .data-row :global(.vff-btn),
        .dialog-actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>
