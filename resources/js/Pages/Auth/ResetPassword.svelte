<script>
    import { useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import AuthCard from '$components/AuthCard.svelte';
    import Field from '$components/ui/Field.svelte';
    import PasswordField from './PasswordField.svelte';
    import { PASSWORD_MIN, focusFirstError, isEmail } from './authForm.js';
    import { i18n } from '$lib/i18n.js';

    let { token, email = '' } = $props();

    // token vient du segment URL /reset-password/{token}, l'email du query string : valeurs de départ.
    // svelte-ignore state_referenced_locally
    const form = useForm({
        token,
        // Le serveur envoie null quand le lien ne porte pas d'adresse.
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const FIELDS = ['email', 'password', 'password_confirmation'];

    /** Contrôle dans le navigateur avant l'envoi (le serveur refait les mêmes contrôles). */
    function check() {
        /** @type {Record<string, string>} */
        const errors = {};
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
        form.post('/reset-password', {
            onError: () => focusFirstError(FIELDS),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('auth.reset_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <AuthCard title={$i18n.t('auth.reset_title')} subtitle={$i18n.t('auth.reset_subtitle')}>
            <form class="auth-form" onsubmit={submit} novalidate>
                <Field id="email" label={$i18n.t('auth.email_label')} error={form.errors.email}>
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
                    label={$i18n.t('auth.password_new_label')}
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

                <div class="auth-actions">
                    <button type="submit" class="vff-btn primary block" disabled={form.processing}>
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        {$i18n.t('auth.reset_cta')}
                    </button>
                </div>
            </form>
        </AuthCard>
    </main>
</Layout>
