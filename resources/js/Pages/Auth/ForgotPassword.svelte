<script>
    import { Link, useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { focusFirstError, isEmail } from './authForm.js';
    import { i18n } from '$lib/i18n.js';

    const form = useForm({ email: '' });

    let flash = $derived(page.props.flash?.success ?? null);

    function submit(e) {
        e.preventDefault();
        form.clearErrors();
        if (!form.email.trim()) {
            form.setError('email', $i18n.t('auth.errors.email_required'));
        } else if (!isEmail(form.email)) {
            form.setError('email', $i18n.t('auth.errors.email_invalid'));
        }
        if (form.hasErrors) {
            focusFirstError(['email']);
            return;
        }
        form.post('/forgot-password', {
            onError: () => focusFirstError(['email']),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.forgot_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard title={$i18n.t('auth.forgot_title')} subtitle={$i18n.t('auth.forgot_subtitle')}>
            {#if flash}
                <Notice tone="success">{flash}</Notice>
            {/if}

            <form class="auth-form" onsubmit={submit} novalidate>
                <Field id="email" label={$i18n.t('auth.email_label')} error={form.errors.email}>
                    {#snippet children(attrs)}
                        <input
                            {...attrs}
                            name="email"
                            class="vff-input"
                            type="email"
                            autocomplete="email"
                            spellcheck="false"
                            autocapitalize="none"
                            required
                            bind:value={form.email}
                        />
                    {/snippet}
                </Field>

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary block" disabled={form.processing}>
                        <i class="bi bi-envelope-paper" aria-hidden="true"></i>
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
