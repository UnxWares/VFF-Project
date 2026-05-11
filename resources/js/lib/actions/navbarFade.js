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

                if (entry.isIntersecting) {
                    nav.style.opacity = '0';
                    background.style.position = 'sticky';
                    isOut = false;
                } else {
                    nav.style.opacity = '1';
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
