<script>
    // Gabarit réduit à son contenu, pour rendre une page dans les tests sans en-tête ni pied de page.
    let { children } = $props();
</script>

{@render children?.()}
