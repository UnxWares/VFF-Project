<script>
    import { useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import { i18n } from '$lib/i18n.js';

    let { token, email = '' } = $props();

    // token vient du segment URL /reset-password/{token}, l'email du query string.
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.reset_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard
            title={$i18n.t('auth.reset_title')}
            subtitle={$i18n.t('auth.reset_subtitle')}
            icon="bi-shield-lock"
        >
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

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-check2-circle"></i>
                        {$i18n.t('auth.reset_cta')}
                    </button>
                </div>
            </form>
        </AuthCard>
    </main>
</Layout>
