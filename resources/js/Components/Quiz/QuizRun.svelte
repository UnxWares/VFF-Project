<script>
    /**
     * Partie en cours du quiz : une question à la fois, réponse vérifiée par le serveur après le
     * choix, puis le résultat. Le parent la recrée pour chaque série (`{#key}` sur la graine).
     *
     * Accessibilité : propositions en boutons radio dans un fieldset, version texte de chaque question
     * (indices), résultat annoncé par une région `aria-live` présente dès le départ, focus posé sur le
     * titre de la question suivante, puis sur le résultat final. Aucune minuterie. Le tracé ou la photo
     * est décrit par les indices (`aria-describedby`), même repliés au niveau difficile : pour un
     * lecteur d'écran, ils sont la seule version texte de la question.
     *
     * La version de la série (`version`) accompagne chaque réponse : si la base a changé depuis
     * l'affichage de la page, le serveur refuse de juger (409) et la page propose une nouvelle série.
     */
    import { Link } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { tick } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { mediaLicenseHref, mediaLicenseLabel, mediaSourceHref } from '$lib/mediaCredits.js';
    import {
        answerHref,
        lineHints,
        materialHints,
        quizHref,
        saveBest,
        scaleBar,
        scoreOf,
        STALE_STATUS,
        tracePath,
        traceViewBox,
        TRACE_MARGIN,
    } from '$lib/quiz.js';

    /** @type {{ game: string, level: string, seed: string, version: string, questions: any[] }} */
    let { game, level, seed, version, questions } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));
    const lang = $derived($i18n.language ?? 'fr');
    const total = $derived(questions.length);

    let index = $state(0);
    let choice = $state('');
    /** Réponses reçues du serveur, par numéro de question. */
    let results = $state({});
    let pending = $state(false);
    let live = $state('');
    let errorText = $state('');
    /** La série a changé côté serveur depuis l'affichage : plus aucune réponse n'est jugée. */
    let stale = $state(false);
    // Indices visibles d'emblée au niveau facile ; repliés (un bouton les ouvre) au niveau difficile.
    // svelte-ignore state_referenced_locally
    let hintsOpen = $state(level === 'easy');
    let finished = $state(false);
    let best = $state(null);
    let copied = $state(false);

    let questionHeading = $state();
    let nextButton = $state();
    let resultHeading = $state();

    const question = $derived(questions[index]);
    const result = $derived(results[index] ?? null);
    const score = $derived(scoreOf(results));
    const answered = $derived(Object.keys(results).length);
    const hints = $derived(
        game === 'lines'
            ? lineHints(question?.hints, t, lang)
            : materialHints(question?.hints, t, lang),
    );
    const trace = $derived(question?.trace ?? null);
    const bar = $derived(trace ? scaleBar(trace.m_per_unit, trace.width) : null);
    const photo = $derived(question?.photo ?? null);
    // Description du tracé ou de la photo : les indices affichés, ou leur copie pour les lecteurs d'écran.
    const hintsId = $derived(hintsOpen ? 'quiz-hints' : 'quiz-hints-text');
    const shareUrl = $derived(
        typeof location === 'undefined'
            ? quizHref(game, level, seed, version)
            : `${location.origin}${quizHref(game, level, seed, version)}`,
    );

    function photoLicense(p) {
        return p.rights_status === 'public_domain'
            ? t('media.rights.public_domain')
            : mediaLicenseLabel(p, t);
    }

    /**
     * Annonce dans la région polie. Elle est vidée d'abord : un même message (deuxième « choisissez
     * d'abord », même erreur) est annoncé de nouveau.
     */
    async function announce(text) {
        live = '';
        await tick();
        live = text;
    }

    async function submit(event) {
        event.preventDefault();
        if (result || pending || stale) return;
        if (!choice) {
            await announce(t('quiz.play.choose_first'));
            return;
        }
        pending = true;
        errorText = '';
        try {
            const res = await fetch(answerHref({ game, level, seed, version }, index, choice), {
                headers: { Accept: 'application/json' },
            });
            if (res.status === STALE_STATUS) {
                stale = true;
                errorText = t('quiz.play.stale');
                await announce(errorText);
                return;
            }
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            results = { ...results, [index]: data };
            live = data.correct
                ? t('quiz.answer.correct', { label: data.answer.label })
                : t('quiz.answer.wrong', { label: data.answer.label });
            await tick();
            nextButton?.focus();
        } catch {
            errorText = t('quiz.play.error');
            await announce(errorText);
        } finally {
            pending = false;
        }
    }

    async function next() {
        if (index + 1 >= total) {
            finished = true;
            best = saveBest(globalThis.localStorage, game, level, score);
            live = t('quiz.result.score', { score, total });
            await tick();
            resultHeading?.focus();
            return;
        }
        index += 1;
        choice = '';
        errorText = '';
        live = '';
        await tick();
        questionHeading?.focus();
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(shareUrl);
            copied = true;
            live = t('quiz.result.copied');
        } catch {
            copied = false;
        }
    }

    /** État d'une proposition une fois la réponse connue : bonne réponse, ou choix erroné. */
    function optionState(key) {
        if (!result) return null;
        if (key === result.answer.key) return 'right';
        if (key === result.choice) return 'wrong';
        return null;
    }
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<div class="run">
    <div class="status">
        <p>
            {t('quiz.play.status', {
                game: t(`quiz.games.${game}`),
                level: t(`quiz.levels.${level}`).toLocaleLowerCase(lang),
            })}
            <Link href="/quiz" class="vff-link">{t('quiz.play.change')}</Link>
        </p>
        {#if !finished}
            <p class="score">{t('quiz.play.score', { score, answered })}</p>
        {/if}
    </div>

    <p class="vff-sr-only" aria-live="polite">{live}</p>

    {#if finished}
        <section class="result" aria-labelledby="quiz-result-title">
            <h2 id="quiz-result-title" tabindex="-1" bind:this={resultHeading}>
                {t('quiz.result.title')}
            </h2>
            <p class="final">{t('quiz.result.score', { score, total })}</p>
            {#if best !== null}
                <p class="best">{t('quiz.result.best', { best, total })}</p>
            {/if}

            <h3>{t('quiz.result.recap')}</h3>
            <ol class="recap">
                {#each questions as q (q.index)}
                    {@const r = results[q.index]}
                    {#if r}
                        <li>
                            <Link href={r.answer.url}>{r.answer.label}</Link>
                            <span class={['vff-badge', r.correct ? 'success' : 'muted']}>
                                <i
                                    class={['bi', r.correct ? 'bi-check2' : 'bi-x']}
                                    aria-hidden="true"
                                ></i>
                                {t(r.correct ? 'quiz.result.found' : 'quiz.result.missed')}
                            </span>
                        </li>
                    {/if}
                {/each}
            </ol>

            <div class="share">
                <label class="vff-label" for="quiz-share">{t('quiz.result.share')}</label>
                <div class="share-row">
                    <input id="quiz-share" class="vff-input" type="url" readonly value={shareUrl} />
                    <button type="button" class="vff-btn secondary" onclick={copyLink}>
                        {t(copied ? 'quiz.result.copied' : 'quiz.result.copy')}
                    </button>
                </div>
            </div>

            <div class="actions">
                <Link href={quizHref(game, level)} class="vff-btn primary"
                    >{t('quiz.result.again')}</Link
                >
                <Link href="/quiz" class="vff-btn secondary">{t('quiz.play.change')}</Link>
            </div>
        </section>
    {:else if question}
        <section class="question" aria-labelledby="quiz-question-title">
            <h2 id="quiz-question-title" tabindex="-1" bind:this={questionHeading}>
                {t('quiz.play.progress', { current: index + 1, total })}
            </h2>

            <div class="layout">
                <div class="visual">
                    {#if game === 'lines'}
                        {#if trace && trace.paths.length}
                            <figure class="trace">
                                <svg
                                    viewBox={traceViewBox(trace)}
                                    role="img"
                                    aria-label={t('quiz.play.trace_alt')}
                                    aria-describedby={hintsId}
                                    preserveAspectRatio="xMidYMid meet"
                                >
                                    <path class="line" d={tracePath(trace.paths)} />
                                    {#if bar}
                                        <path
                                            class="bar"
                                            d={`M0 ${trace.height + TRACE_MARGIN} h${bar.units}`}
                                        />
                                    {/if}
                                </svg>
                                {#if bar}
                                    <figcaption>
                                        {t('quiz.play.scale', {
                                            value: bar.km.toLocaleString(lang),
                                        })}
                                    </figcaption>
                                {/if}
                            </figure>
                        {:else}
                            <p class="missing">{t('quiz.play.trace_missing')}</p>
                        {/if}
                    {:else if photo}
                        {@const licenseHref = mediaLicenseHref(photo)}
                        {@const fileHref = result
                            ? mediaSourceHref({ source_url: result.photo_source_url })
                            : null}
                        <figure class="vff-figure photo" style:--ratio="3 / 2">
                            <img
                                src={commonsSrc(photo.url)}
                                alt={t('quiz.play.photo_alt')}
                                aria-describedby={hintsId}
                                width={photo.width ?? 1280}
                                height={photo.height ?? 853}
                            />
                            <figcaption>
                                {t('quiz.play.photo_by', { author: photo.author })},
                                {#if licenseHref}<a
                                        href={licenseHref}
                                        target="_blank"
                                        rel="license noopener noreferrer"
                                        >{photoLicense(photo)}{@render ext()}</a
                                    >{:else}{photoLicense(photo)}{/if},
                                {#if fileHref}<a
                                        href={fileHref}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{t('quiz.play.commons_file')}{@render ext()}</a
                                    >{:else}{t('quiz.play.commons')}{/if}
                            </figcaption>
                        </figure>
                    {/if}
                </div>

                <div class="side">
                    <div class="hints">
                        <button
                            type="button"
                            class="vff-btn quiet"
                            aria-expanded={hintsOpen}
                            aria-controls="quiz-hints"
                            onclick={() => (hintsOpen = !hintsOpen)}
                        >
                            <i
                                class={['bi', hintsOpen ? 'bi-chevron-up' : 'bi-chevron-down']}
                                aria-hidden="true"
                            ></i>
                            {t(hintsOpen ? 'quiz.hints.hide' : 'quiz.hints.show')}
                        </button>
                        <div id="quiz-hints" hidden={!hintsOpen}>
                            {@render hintList()}
                        </div>
                        {#if !hintsOpen}
                            <!-- Indices repliés : copie pour les lecteurs d'écran, qui décrit le tracé ou la photo. -->
                            <div id="quiz-hints-text" class="vff-sr-only">{@render hintList()}</div>
                        {/if}
                    </div>

                    {#snippet hintList()}
                        {#if hints.length}
                            <dl>
                                {#each hints as h (h.id)}
                                    <div>
                                        <dt>{h.label}</dt>
                                        <dd lang={h.lang}>{h.value}</dd>
                                    </div>
                                {/each}
                            </dl>
                        {:else}
                            <p>{t('quiz.hints.none')}</p>
                        {/if}
                    {/snippet}

                    <form onsubmit={submit} novalidate>
                        <fieldset disabled={!!result || stale}>
                            <legend>{t(`quiz.play.question_${game}`)}</legend>
                            <ul class="options">
                                {#each question.options as o (o.key)}
                                    {@const state = optionState(o.key)}
                                    <li>
                                        <label class={['option', state]}>
                                            <input
                                                type="radio"
                                                name="quiz-choice"
                                                value={o.key}
                                                bind:group={choice}
                                            />
                                            <span class="label">{o.label}</span>
                                            {#if state === 'right'}
                                                <span class="vff-badge success">
                                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                                    {t('quiz.answer.right')}
                                                </span>
                                            {:else if state === 'wrong'}
                                                <span class="vff-badge danger">
                                                    <i class="bi bi-x" aria-hidden="true"></i>
                                                    {t('quiz.answer.yours')}
                                                </span>
                                            {/if}
                                        </label>
                                    </li>
                                {/each}
                            </ul>
                        </fieldset>
                        {#if stale}
                            <p id="quiz-error" class="error">{errorText}</p>
                            <Link href={quizHref(game, level)} class="vff-btn primary"
                                >{t('quiz.result.again')}</Link
                            >
                        {:else if !result}
                            <button
                                type="submit"
                                class="vff-btn primary"
                                aria-disabled={pending}
                                aria-describedby={errorText ? 'quiz-error' : undefined}
                            >
                                {t(pending ? 'quiz.play.checking' : 'quiz.play.submit')}
                            </button>
                            {#if errorText}
                                <p id="quiz-error" class="error">{errorText}</p>
                            {/if}
                        {/if}
                    </form>

                    {#if result}
                        <div class="feedback">
                            <p class="verdict">
                                <i
                                    class={[
                                        'bi',
                                        result.correct ? 'bi-check2-circle' : 'bi-x-circle',
                                    ]}
                                    aria-hidden="true"
                                ></i>
                                {result.correct
                                    ? t('quiz.answer.correct', { label: result.answer.label })
                                    : t('quiz.answer.wrong', { label: result.answer.label })}
                            </p>
                            {#if result.fact?.text}
                                <blockquote lang={result.fact.lang}>
                                    <p>{result.fact.text}</p>
                                </blockquote>
                                <p class="source">
                                    {t('quiz.answer.source', {
                                        source: result.fact.source.name,
                                    })}{#if result.fact.source.url},
                                        <a
                                            href={result.fact.source.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            lang="fr">{result.fact.source.title}{@render ext()}</a
                                        >{/if},
                                    <a
                                        href={result.fact.source.license_url}
                                        target="_blank"
                                        rel="license noopener noreferrer"
                                        >{result.fact.source.license}{@render ext()}</a
                                    >
                                </p>
                            {/if}
                            <p>
                                <Link href={result.answer.url} class="vff-link"
                                    >{t(`quiz.answer.sheet_${game}`)}</Link
                                >
                            </p>
                            <button
                                type="button"
                                class="vff-btn primary"
                                bind:this={nextButton}
                                onclick={next}
                            >
                                {t(index + 1 >= total ? 'quiz.play.finish' : 'quiz.play.next')}
                            </button>
                        </div>
                    {/if}
                </div>
            </div>
        </section>
    {/if}
</div>

<style>
    .run {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
    }

    .status {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-5);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    .status p {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-4);
        align-items: baseline;
    }

    .score {
        font-variant-numeric: tabular-nums;
    }

    h2 {
        margin: 0 0 var(--vff-space-5);
        font-size: var(--vff-fs-h2);
    }

    h2:focus-visible {
        outline-offset: 4px;
    }

    .layout {
        display: grid;
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        gap: var(--vff-space-5) var(--vff-space-6);
        align-items: start;
    }

    .visual,
    .side {
        min-width: 0;
    }

    .side {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
    }

    .trace {
        margin: 0;
    }

    .trace svg {
        display: block;
        width: 100%;
        max-height: min(70vh, 640px);
        aspect-ratio: 4 / 3;
        background: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }

    .trace .line {
        fill: none;
        stroke: var(--vff-accent);
        stroke-width: 4px;
        stroke-linecap: round;
        stroke-linejoin: round;
        vector-effect: non-scaling-stroke;
    }

    .trace .bar {
        fill: none;
        stroke: var(--vff-text);
        stroke-width: 3px;
        stroke-linecap: square;
        vector-effect: non-scaling-stroke;
    }

    .trace figcaption,
    .photo figcaption {
        margin-top: var(--vff-space-2);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }

    .photo img {
        object-fit: contain;
        background: var(--vff-bg-subtle);
    }

    .missing {
        margin: 0;
        padding: var(--vff-pad-box);
        background: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        font-size: var(--vff-fs-body);
    }

    .hints [hidden] {
        display: none;
    }

    .hints dl {
        margin: var(--vff-space-3) 0 0;
        border-top: 1px solid var(--vff-border);
    }

    .hints dl div {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
        gap: var(--vff-space-3);
        padding: var(--vff-space-2) 0;
        border-bottom: 1px solid var(--vff-border);
        font-size: var(--vff-fs-sm);
    }

    .hints dt {
        color: var(--vff-text-muted);
    }

    .hints dd {
        margin: 0;
        color: var(--vff-text);
    }

    .hints p {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        align-items: flex-start;
    }

    fieldset {
        margin: 0;
        padding: 0;
        border: 0;
        width: 100%;
        min-width: 0;
    }

    legend {
        padding: 0;
        margin-bottom: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
    }

    .options {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }

    .option {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
        min-height: 48px;
        padding: var(--vff-space-3) var(--vff-space-4);
        background: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }

    .option:hover {
        background: var(--vff-bg-muted);
    }

    .option:has(input:checked) {
        background: var(--vff-bg-accent);
        border-color: var(--vff-accent-ink);
    }

    .option input {
        width: 24px;
        height: 24px;
        margin: 0;
        flex: none;
        accent-color: var(--vff-accent-ink);
    }

    .option .label {
        flex: 1 1 12rem;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    fieldset:disabled .option {
        cursor: default;
    }

    fieldset:disabled .option:hover {
        background: var(--vff-bg-elevated);
    }

    /* Après la réponse, l'état l'emporte sur la sélection. */
    .option.right,
    .option.right:has(input:checked),
    fieldset:disabled .option.right:hover {
        background: var(--vff-success-bg);
        border-color: var(--vff-success);
    }

    .option.wrong,
    .option.wrong:has(input:checked),
    fieldset:disabled .option.wrong:hover {
        background: var(--vff-danger-bg);
        border-color: var(--vff-danger);
    }

    .error {
        margin: 0;
        color: var(--vff-danger);
        font-size: var(--vff-fs-sm);
    }

    .feedback {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        align-items: flex-start;
        padding: var(--vff-pad-box);
        background: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }

    .feedback p {
        margin: 0;
    }

    .verdict {
        display: flex;
        gap: var(--vff-space-2);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        font-weight: 700;
    }

    .verdict i {
        color: var(--vff-text-muted);
    }

    blockquote {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        text-wrap: pretty;
    }

    .source {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }

    .feedback .vff-btn {
        margin-top: var(--vff-space-2);
    }

    .result {
        max-width: 760px;
    }

    .result h2:focus-visible {
        outline-offset: 4px;
    }

    .final {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }

    .best {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }

    .result h3 {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }

    .recap {
        margin: 0;
        padding-left: 1.6em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
    }

    .recap li {
        padding: var(--vff-space-2) 0;
        border-bottom: 1px solid var(--vff-border);
    }

    .recap li :global(a) {
        margin-right: var(--vff-space-2);
    }

    .share {
        margin-top: var(--vff-block-space);
    }

    .share-row {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-2);
    }

    .share-row input {
        flex: 1 1 18rem;
        min-width: 0;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-block-space);
    }

    @media (max-width: 900px) {
        .layout {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
