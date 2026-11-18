<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Trois portes d'entrée de l'encyclopédie, chacune vers sa rubrique. Le cercle posé sur le haut de
     * chaque encadré porte un pictogramme du sujet, dessiné ici sur une même grille de 24 unités, même
     * trait arrondi, centré sur (12, 12) : carte pliée et son tracé entre deux gares, avant de train,
     * accolades de données. `strokes` : tracés au trait ; `dots` : points pleins [x, y, rayon].
     */
    const boxes = [
        {
            key: 'box1',
            href: '/maps',
            strokes: [
                'M3.5 6 L9 4 L15 6 L20.5 4 V18 L15 20 L9 18 L3.5 20 Z',
                'M7.5 15 L10.5 11.5 L13.5 13 L16.5 9',
            ],
            dots: [
                [7.5, 15, 1.5],
                [16.5, 9, 1.5],
            ],
        },
        {
            key: 'box2',
            href: '/lines',
            strokes: [
                'M8.5 3.5 H15.5 A3 3 0 0 1 18.5 6.5 V15 A3 3 0 0 1 15.5 18 H8.5 A3 3 0 0 1 5.5 15 V6.5 A3 3 0 0 1 8.5 3.5 Z',
                'M8 7.5 H16 V11 H8 Z',
                'M9 18 L7 20.5',
                'M15 18 L17 20.5',
            ],
            dots: [
                [9, 14.5, 1.1],
                [15, 14.5, 1.1],
            ],
        },
        {
            key: 'box3',
            href: '/api',
            strokes: [
                'M8.5 3.5 C6.6 3.5 5.8 4.4 5.8 6.3 V9.6 C5.8 10.9 5 11.6 3.8 12 C5 12.4 5.8 13.1 5.8 14.4 V17.7 C5.8 19.6 6.6 20.5 8.5 20.5',
                'M15.5 3.5 C17.4 3.5 18.2 4.4 18.2 6.3 V9.6 C18.2 10.9 19 11.6 20.2 12 C19 12.4 18.2 13.1 18.2 14.4 V17.7 C18.2 19.6 17.4 20.5 15.5 20.5',
            ],
            dots: [
                [9.8, 12, 1.2],
                [14.2, 12, 1.2],
            ],
        },
    ];
</script>

<!-- Bande orange oblique et trois encadrés (dessin d'origine, styles dans css/index/main/maps.css).
     Le cercle à cheval sur le haut de chaque encadré porte le pictogramme de son sujet (décoratif : le
     titre porte le sens), plus le logo VFF (demande de l'utilisateur, 18/11). -->
<section id="maps">
    <div class="maps-background" aria-hidden="true"></div>
    <div class="maps-container">
        {#each boxes as box (box.key)}
            <article class="maps-box">
                <span class="maps-badge" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        {#each box.strokes as d (d)}<path {d} />{/each}
                        {#each box.dots as [cx, cy, r] (`${cx},${cy}`)}<circle
                                {cx}
                                {cy}
                                {r}
                                class="dot"
                            />{/each}
                    </svg>
                </span>
                <h2>{$i18n.t(`home.maps.${box.key}_title`)}</h2>
                <p>{$i18n.t(`home.maps.${box.key}_text`)}</p>
                <Link href={box.href} class="vff-link maps-link">
                    {$i18n.t(`home.maps.${box.key}_link`)}
                </Link>
            </article>
        {/each}
    </div>
</section>
