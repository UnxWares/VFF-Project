<script>
    import LegalPage from '$components/LegalPage.svelte';

    /** `updated` : date de la version en vigueur (config/legal.php). */
    let { updated } = $props();
</script>

<LegalPage
    namespace="use_conditions"
    sections={[
        'purpose',
        'acceptance',
        'access',
        'account',
        'contributing',
        'licence',
        'moderation',
        'conduct',
        'safety',
        'reuse',
        'liability',
        'property',
        'suspension',
        'changes',
        'law',
        'contact',
    ]}
    {updated}
/>
