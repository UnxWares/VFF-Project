// Action Svelte : retire `.noTranslate` puis ajoute `.animate` quand l'élément entre
// dans le viewport. Reproduit le comportement de l'ancien `scrollAnimation.js`.

export function scrollReveal(node, options = {}) {
    const threshold = options.threshold ?? 0.5;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    node.classList.remove('noTranslate');
                    setTimeout(() => node.classList.add('animate'), 20);
                }
            });
        },
        { threshold },
    );

    observer.observe(node);

    return {
        destroy() {
            observer.disconnect();
        },
    };
}
