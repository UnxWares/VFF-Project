<script>
    import { Link, useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import { i18n } from '$lib/i18n.js';

    const form = useForm({ email: '' });

    let flash = $derived(page.props.flash?.success ?? null);

    function submit(e) {
        e.preventDefault();
        form.post('/forgot-password');
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.forgot_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard
            title={$i18n.t('auth.forgot_title')}
            subtitle={$i18n.t('auth.forgot_subtitle')}
            icon="bi-key"
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

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-envelope-paper"></i>
                        {$i18n.t('auth.forgot_cta')}
                    </button>
                </div>

                <div class="auth-links">
                    <Link href="/login">{$i18n.t('auth.back_to_login')}</Link>
                </div>
            </form>
        </AuthCard>
    </main>
</Layout>
