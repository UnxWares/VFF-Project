<script>
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';
    import vffLogo from '$images/vff.png';

    const boxes = [
        { titleKey: 'home.maps.box1_title', textKey: 'home.maps.box1_text' },
        { titleKey: 'home.maps.box2_title', textKey: 'home.maps.box2_text' },
        { titleKey: 'home.maps.box3_title', textKey: 'home.maps.box3_text' },
    ];
</script>

<section id="maps" class="noTranslate" use:scrollReveal>
    <div class="maps-background"></div>
    <div class="maps-container">
        {#each boxes as box}
            <div class="maps-box">
                <div>
                    <figure>
                        <img src={vffLogo} alt="" />
                    </figure>
                    <h2>{$i18n.t(box.titleKey)}</h2>
                    <p>{$i18n.t(box.textKey)}</p>
                </div>
            </div>
        {/each}
    </div>
</section>
