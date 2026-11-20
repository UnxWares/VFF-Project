<script>
    import { Link, useForm, page } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import PasswordField from './PasswordField.svelte';
    import { focusFirstError, isEmail } from './authForm.js';
    import { i18n } from '$lib/i18n.js';

    // useForm gère la soumission Inertia (CSRF, errors, processing).
    // En cas d'échec, Laravel renvoie `errors.email` (via back()->withErrors).
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    let flash = $derived(page.props.flash?.success ?? null);
    /** Refus du serveur (identifiants faux, trop de tentatives) : message du formulaire, pas d'un champ. */
    let failure = $state('');

    /** Contrôle dans le navigateur avant l'envoi : champs vides, adresse mal formée. */
    function check() {
        /** @type {Record<string, string>} */
        const errors = {};
        if (!form.email.trim()) {
            errors.email = $i18n.t('auth.errors.email_required');
        } else if (!isEmail(form.email)) {
            errors.email = $i18n.t('auth.errors.email_invalid');
        }
        if (!form.password) {
            errors.password = $i18n.t('auth.errors.password_required');
        }
        return errors;
    }

    function submit(e) {
        e.preventDefault();
        failure = '';
        form.clearErrors();
        const errors = check();
        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            focusFirstError(['email', 'password']);
            return;
        }
        form.post('/login', {
            onError: (errs) => {
                failure = errs.email ?? errs.password ?? '';
                form.clearErrors();
                document.getElementById('password')?.focus();
            },
            onFinish: () => form.reset('password'),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.login_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard title={$i18n.t('auth.login_title')} subtitle={$i18n.t('auth.login_subtitle')}>
            {#if flash}
                <Notice tone="success">{flash}</Notice>
            {/if}

            <form class="auth-form" onsubmit={submit} novalidate>
                {#if failure}
                    <Notice
                        tone="danger"
                        live="assertive"
                        title={$i18n.t('auth.errors.login_failed_title')}>{failure}</Notice
                    >
                {/if}

                <Field
                    id="email"
                    label={$i18n.t('auth.email_label')}
                    error={form.errors.email}
                    required
                >
                    {#snippet children(attrs)}
                        <input
                            {...attrs}
                            name="email"
                            class="vff-input"
                            type="email"
                            autocomplete="username"
                            spellcheck="false"
                            autocapitalize="none"
                            required
                            bind:value={form.email}
                        />
                    {/snippet}
                </Field>

                <PasswordField
                    id="password"
                    label={$i18n.t('auth.password_label')}
                    autocomplete="current-password"
                    error={form.errors.password}
                    bind:value={form.password}
                />

                <label class="vff-check remember">
                    <input type="checkbox" name="remember" bind:checked={form.remember} />
                    {$i18n.t('auth.remember_label')}
                </label>

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary block" disabled={form.processing}>
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
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
