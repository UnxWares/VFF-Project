// Action Svelte : translate les deux figures TGV en fonction du scroll vertical
// pour créer un effet d'écartement progressif. Reproduit `scrollTGV.js`.
// Usage : <div use:parallaxTGV={{ left: leftEl, right: rightEl }}>...</div>

export function parallaxTGV(_node, params) {
    let { left, right } = params ?? {};

    function update() {
        if (!left || !right) return;
        if (window.scrollY <= 1000) {
            const v = (window.scrollY / 10) * 0.185;
            left.style.transform = `translateX(${-v - 1}vw)`;
            right.style.transform = `translateX(${v + 1}vw)`;
        } else {
            left.style.transform = 'translateX(-19.5vw)';
            right.style.transform = 'translateX(19.5vw)';
        }
    }

    window.addEventListener('scroll', update, { passive: true });
    update();

    return {
        update(newParams) {
            left = newParams?.left ?? left;
            right = newParams?.right ?? right;
            update();
        },
        destroy() {
            window.removeEventListener('scroll', update);
        },
    };
}
