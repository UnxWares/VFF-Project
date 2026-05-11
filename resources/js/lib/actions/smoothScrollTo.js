// Helper standalone (pas une action) : scroll fluide jusqu'à une position absolue
// ou jusqu'au bas du header. Réutilisable depuis n'importe quel composant.

export function scrollToContent() {
    window.scrollTo({
        top: window.innerHeight * 2 - 130,
        behavior: 'smooth',
    });
}
