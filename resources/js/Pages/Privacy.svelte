<script>
    import LegalPage from '$components/LegalPage.svelte';

    /** `updated` : date de la version en vigueur (config/legal.php). */
    let { updated } = $props();
</script>

<LegalPage
    namespace="privacy"
    sections={[
        'summary',
        'controller',
        'visitors',
        'cookies',
        'third_party',
        'account',
        'contributions',
        'error_reports',
        'emails',
        'recipients',
        'transfers',
        'security',
        'rights',
        'minors',
        'changes',
        'contact',
    ]}
    {updated}
/>
