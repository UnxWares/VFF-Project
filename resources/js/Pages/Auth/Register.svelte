<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import { i18n } from '$lib/i18n.js';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.register_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard
            title={$i18n.t('auth.register_title')}
            subtitle={$i18n.t('auth.register_subtitle')}
            icon="bi-person-plus"
        >
            <form class="auth-form" onsubmit={submit} novalidate>
                <div class="auth-field">
                    <label for="name">{$i18n.t('auth.name_label')}</label>
                    <input
                        id="name"
                        type="text"
                        autocomplete="name"
                        required
                        bind:value={form.name}
                    />
                    {#if form.errors.name}
                        <span class="error">{form.errors.name}</span>
                    {/if}
                </div>

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
                        autocomplete="new-password"
                        required
                        bind:value={form.password}
                    />
                    {#if form.errors.password}
                        <span class="error">{form.errors.password}</span>
                    {/if}
                </div>

                <div class="auth-field">
                    <label for="password_confirmation"
                        >{$i18n.t('auth.password_confirmation_label')}</label
                    >
                    <input
                        id="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        bind:value={form.password_confirmation}
                    />
                </div>

                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <p class="auth-terms">{@html $i18n.t('auth.register_terms')}</p>

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-person-plus"></i>
                        {$i18n.t('auth.register_cta')}
                    </button>
                </div>

                <div class="auth-links">
                    <span>
                        {$i18n.t('auth.has_account')}
                        <Link href="/login">{$i18n.t('auth.login_cta')}</Link>
                    </span>
                </div>
            </form>
        </AuthCard>
    </main>
</Layout>
