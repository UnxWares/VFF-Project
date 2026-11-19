<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import Field from '$components/ui/Field.svelte';
    import PasswordField from './PasswordField.svelte';
    import { PASSWORD_MIN, focusFirstError, isEmail } from './authForm.js';
    import { i18n } from '$lib/i18n.js';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const FIELDS = ['name', 'email', 'password', 'password_confirmation'];

    /** Contrôle dans le navigateur avant l'envoi (le serveur refait les mêmes contrôles). */
    function check() {
        /** @type {Record<string, string>} */
        const errors = {};
        if (!form.name.trim()) {
            errors.name = $i18n.t('auth.errors.name_required');
        }
        if (!form.email.trim()) {
            errors.email = $i18n.t('auth.errors.email_required');
        } else if (!isEmail(form.email)) {
            errors.email = $i18n.t('auth.errors.email_invalid');
        }
        if (!form.password) {
            errors.password = $i18n.t('auth.errors.password_new_required');
        } else if (form.password.length < PASSWORD_MIN) {
            errors.password = $i18n.t('auth.errors.password_short');
        }
        if (!form.password_confirmation) {
            errors.password_confirmation = $i18n.t('auth.errors.confirmation_required');
        } else if (form.password_confirmation !== form.password) {
            errors.password_confirmation = $i18n.t('auth.errors.confirmation_mismatch');
        }
        return errors;
    }

    function submit(e) {
        e.preventDefault();
        form.clearErrors();
        const errors = check();
        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            focusFirstError(FIELDS);
            return;
        }
        form.post('/register', {
            onError: () => focusFirstError(FIELDS),
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
        >
            <form class="auth-form" onsubmit={submit} novalidate>
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
                            maxlength="255"
                            required
                            bind:value={form.email}
                        />
                    {/snippet}
                </Field>

                <PasswordField
                    id="password"
                    label={$i18n.t('auth.password_label')}
                    hint={$i18n.t('auth.password_hint')}
                    autocomplete="new-password"
                    error={form.errors.password}
                    bind:value={form.password}
                />

                <PasswordField
                    id="password_confirmation"
                    label={$i18n.t('auth.password_confirmation_label')}
                    autocomplete="new-password"
                    error={form.errors.password_confirmation}
                    bind:value={form.password_confirmation}
                />

                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <p class="auth-terms">{@html $i18n.t('auth.register_terms')}</p>

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary block" disabled={form.processing}>
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        {$i18n.t('auth.register_cta')}
                    </button>
                </div>

                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <p class="auth-terms privacy">{@html $i18n.t('auth.register_privacy')}</p>

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

<style>
    .privacy {
        padding-top: 16px;
        border-top: 1px solid var(--vff-border);
    }
</style>
