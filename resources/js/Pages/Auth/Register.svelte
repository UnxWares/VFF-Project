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
        accept_terms: false,
    });

    const FIELDS = ['name', 'email', 'password', 'password_confirmation', 'accept_terms'];

    const termsError = $derived(form.errors.accept_terms || '');

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
        if (!form.accept_terms) {
            errors.accept_terms = $i18n.t('auth.errors.terms_required');
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

                <!-- Case obligatoire : la date et la version des CGU acceptées sont enregistrées. -->
                <div class="vff-field">
                    <label class="vff-check terms">
                        <input
                            id="accept_terms"
                            name="accept_terms"
                            type="checkbox"
                            required
                            aria-describedby={termsError ? 'accept_terms-error' : undefined}
                            aria-invalid={termsError ? 'true' : undefined}
                            bind:checked={form.accept_terms}
                        />
                        <span
                            ><!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html $i18n.t('auth.register_terms')}<span class="vff-required"
                                >{` (${$i18n.t('common.required')})`}</span
                            ></span
                        >
                    </label>
                    <p class="vff-error" id="accept_terms-error" aria-live="polite">
                        {#if termsError}<i class="bi bi-exclamation-circle" aria-hidden="true"
                            ></i><span>{termsError}</span>{/if}
                    </p>
                </div>

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
    /* Libellé sur plusieurs lignes au besoin : la case reste en haut, alignée sur la première. */
    .terms {
        align-items: flex-start;
    }
    .vff-required {
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .privacy {
        padding-top: 16px;
        border-top: 1px solid var(--vff-border);
    }
</style>
