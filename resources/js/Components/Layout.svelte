<script>
    import Header from './Header.svelte';
    import Footer from './Footer.svelte';

    let { children, compactHeader = false } = $props();
</script>

<Header compact={compactHeader} />
{@render children?.()}
<Footer />
