<script>
    import { Link, useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import { i18n } from '$lib/i18n.js';

    // useForm gère la soumission Inertia (CSRF, errors, processing).
    // En cas d'échec, Laravel renvoie `errors.email` (via back()->withErrors).
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    let flash = $derived(page.props.flash?.success ?? null);

    function submit(e) {
        e.preventDefault();
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.login_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard
            title={$i18n.t('auth.login_title')}
            subtitle={$i18n.t('auth.login_subtitle')}
            icon="bi-box-arrow-in-right"
        >
            {#if flash}
                <div class="auth-flash">{flash}</div>
            {/if}

            <form class="auth-form" onsubmit={submit} novalidate>
                <div class="auth-field">
                    <label for="email">{$i18n.t('auth.email_label')}</label>
                    <input
                        id="email"
                        type="email"
                        autocomplete="email"
                        required
                        bind:value={form.email}
                    />
                    {#if form.errors.email}
                        <span class="error">{form.errors.email}</span>
                    {/if}
                </div>

                <div class="auth-field">
                    <label for="password">{$i18n.t('auth.password_label')}</label>
                    <input
                        id="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        bind:value={form.password}
                    />
                    {#if form.errors.password}
                        <span class="error">{form.errors.password}</span>
                    {/if}
                </div>

                <label class="auth-checkbox">
                    <input type="checkbox" bind:checked={form.remember} />
                    {$i18n.t('auth.remember_label')}
                </label>

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-box-arrow-in-right"></i>
                        {$i18n.t('auth.login_cta')}
                    </button>
                </div>

                <div class="auth-links">
                    <Link href="/forgot-password">{$i18n.t('auth.forgot_password')}</Link>
                    <span>
                        {$i18n.t('auth.no_account')}
                        <Link href="/register">{$i18n.t('auth.register_cta')}</Link>
                    </span>
                </div>
            </form>
        </AuthCard>
    </main>
</Layout>
