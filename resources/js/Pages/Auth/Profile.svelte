<script>
    import { useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import { LOCALES, i18n } from '$lib/i18n.js';

    let { profile } = $props();

    // PUT pour matcher la route REST `Route::put('/profile', ...)`.
    // Inertia transmet automatiquement _method=PUT en POST + CSRF.
    const form = useForm({
        name: profile.name ?? '',
        bio: profile.bio ?? '',
        locale_preference: profile.locale_preference ?? 'fr',
    });

    let flash = $derived(page.props.flash?.success ?? null);
    let roleLabel = $derived($i18n.t(`auth.roles.${profile.role}`));
    let memberSince = $derived(
        profile.created_at ? new Date(profile.created_at).toLocaleDateString($i18n.language) : '-',
    );

    function submit(e) {
        e.preventDefault();
        form.put('/profile', { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.profile_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main class="profile-page">
        <div class="profile-grid">
            <aside class="profile-sidebar">
                <div class="avatar">
                    {#if profile.avatar_path}
                        <img src={profile.avatar_path} alt={profile.name} />
                    {:else}
                        <i class="bi bi-person-circle"></i>
                    {/if}
                </div>
                <h2>{profile.name}</h2>
                <p class="email">{profile.email}</p>

                <dl class="meta">
                    <dt>{$i18n.t('auth.role_label')}</dt>
                    <dd><span class="badge role-{profile.role}">{roleLabel}</span></dd>

                    <dt>{$i18n.t('auth.reputation_label')}</dt>
                    <dd><i class="bi bi-star-fill"></i> {profile.reputation}</dd>

                    <dt>{$i18n.t('auth.member_since')}</dt>
                    <dd>{memberSince}</dd>
                </dl>
            </aside>

            <section class="profile-form-wrap">
                <div class="page-header">
                    <h1>{$i18n.t('auth.profile_title')}</h1>
                    <p class="subtitle">{$i18n.t('auth.profile_subtitle')}</p>
                </div>

                {#if flash}
                    <div class="flash"><i class="bi bi-check2-circle"></i> {flash}</div>
                {/if}

                <form onsubmit={submit} novalidate>
                    <div class="field">
                        <label for="name">{$i18n.t('auth.name_label')}</label>
                        <input id="name" type="text" required bind:value={form.name} />
                        {#if form.errors.name}<span class="error">{form.errors.name}</span>{/if}
                    </div>

                    <div class="field">
                        <label for="bio">{$i18n.t('auth.bio_label')}</label>
                        <textarea
                            id="bio"
                            rows="5"
                            placeholder={$i18n.t('auth.bio_placeholder')}
                            bind:value={form.bio}
                        ></textarea>
                        {#if form.errors.bio}<span class="error">{form.errors.bio}</span>{/if}
                    </div>

                    <div class="field">
                        <label for="locale">{$i18n.t('auth.locale_label')}</label>
                        <select id="locale" bind:value={form.locale_preference}>
                            {#each LOCALES as l}
                                <option value={l.code}>{l.flag} {l.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div class="actions">
                        <button type="submit" class="vff-btn primary" disabled={form.processing}>
                            <i class="bi bi-floppy"></i>
                            {$i18n.t('auth.save_profile')}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</Layout>

<style>
    .profile-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 48px 20px;
    }
    .profile-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 32px;
    }
    .profile-sidebar {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        padding: 28px 22px;
        text-align: center;
    }
    .avatar :global(i.bi) {
        font-size: 96px;
        color: var(--vff-accent);
    }
    .avatar img {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--vff-accent);
    }
    .profile-sidebar h2 {
        margin: 12px 0 4px;
        font-family: var(--vff-font-display);
        font-size: 22px;
        color: var(--vff-text);
    }
    .email {
        margin: 0 0 18px;
        color: var(--vff-text-muted);
        font-size: 14px;
        word-break: break-all;
    }
    .meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 12px;
        margin: 0;
        text-align: left;
    }
    .meta dt {
        font-size: 13px;
        color: var(--vff-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .meta dd {
        margin: 0;
        font-size: 14px;
        color: var(--vff-text);
        font-weight: 600;
    }
    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: var(--vff-radius-pill);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
    }
    .badge.role-admin {
        background-color: #fee2e2;
        color: #b91c1c;
    }
    .badge.role-validator {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .profile-form-wrap {
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        padding: 32px 28px;
    }
    .profile-form-wrap .page-header h1 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 26px;
    }
    .profile-form-wrap .subtitle {
        margin: 6px 0 24px;
        color: var(--vff-text-muted);
        font-size: 15px;
    }
    .flash {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        padding: 10px 12px;
        border-radius: var(--vff-radius-sm);
        margin-bottom: 18px;
        font-size: 14px;
    }
    form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .field label {
        font-size: 14px;
        font-weight: 600;
        color: var(--vff-text);
    }
    .field input,
    .field textarea,
    .field select {
        padding: 10px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 15px;
        transition:
            border-color var(--vff-transition),
            box-shadow var(--vff-transition);
    }
    .field input:focus,
    .field textarea:focus,
    .field select:focus {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }
    .error {
        color: #c53030;
        font-size: 13px;
    }
    .actions {
        margin-top: 8px;
    }

    @media (max-width: 720px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
