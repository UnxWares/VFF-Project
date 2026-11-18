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
            </div>
        </div>
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

    @media (max-width: 900px) {
        .profile-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .identity {
            max-width: 420px;
        }
    }
    @media (max-width: 600px) {
        form {
            padding: 20px 16px;
        }
        .actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>
