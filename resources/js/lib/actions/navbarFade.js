// Action Svelte : observe le header complet, fait apparaître la nav en sticky
// dès qu'il sort du viewport, et la cache quand il y rerentre.
// Reproduit `navbarPosition.js`.
// Usage : <header use:navbarFade={{ nav: navEl, background: bgEl }}>...</header>

export function navbarFade(node, params) {
    let { nav, background } = params ?? {};
    let isOut = false;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!nav || !background) return;

                // Opacité portée par des classes (header.css), plus par un style en ligne : la feuille
                // garde la main (pages internes, téléphone) sans !important.
                if (entry.isIntersecting) {
                    // Invisible : ne capte plus les clics (cf. header.css), sauf au clavier.
                    nav.classList.add('nav-hidden');
                    nav.classList.remove('nav-shown');
                    background.style.position = 'sticky';
                    isOut = false;
                } else {
                    nav.classList.add('nav-shown');
                    nav.classList.remove('nav-hidden');
                    isOut = true;
                    setTimeout(() => {
                        if (isOut) background.style.position = 'relative';
                    }, 1000);
                }
            });
        },
        { threshold: 0.07 },
    );

    observer.observe(node);

    return {
        update(newParams) {
            nav = newParams?.nav ?? nav;
            background = newParams?.background ?? background;
        },
        destroy() {
            observer.disconnect();
        },
    };
}
