<script>
    /**
     * Quiz du rail (/quiz). Sans jeu choisi : choix du jeu et du niveau. Avec un jeu : la série tirée
     * par le serveur (graine dans l'adresse, à partager), jouée par QuizRun.
     */
    import { router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import QuizRun from '$components/Quiz/QuizRun.svelte';
    import { i18n } from '$lib/i18n.js';
    import { GAMES, LEVELS } from '$lib/quiz.js';

    let { available = {}, game = null, level = 'easy', seed = null, questions = [] } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));
    const playable = $derived(GAMES.filter((g) => available[g]));

    // Choix du formulaire : premier jeu disponible, niveau facile.
    let chosenGame = $state(GAMES.find((g) => available[g]) ?? 'lines');
    let chosenLevel = $state('easy');

    function start(event) {
        event.preventDefault();
        router.get('/quiz', { game: chosenGame, level: chosenLevel });
    }
</script>

<svelte:head>
    <title>{t('quiz.meta_title')} - VFF</title>
</svelte:head>

<SeoHead title={t('quiz.meta_title')} description={t('quiz.meta_description')} path="/quiz" />

<Layout compactHeader>
    <main>
        <PageHero title={t('quiz.title')} subtitle={t('quiz.subtitle')} />

        <div class="vff-container quiz">
            {#if game && questions.length}
                {#key `${game}-${level}-${seed}`}
                    <QuizRun {game} {level} {seed} {questions} />
                {/key}
            {:else if playable.length === 0}
                <EmptyState
                    icon="bi-question-circle"
                    headingLevel={2}
                    title={t('quiz.empty.title')}
                    text={t('quiz.empty.text')}
                />
            {:else}
                <form class="setup" onsubmit={start} action="/quiz" method="get">
                    <h2>{t('quiz.setup.title')}</h2>
                    <fieldset>
                        <legend>{t('quiz.setup.game')}</legend>
                        <div class="choices">
                            {#each GAMES as g (g)}
                                <label class={['choice', { off: !available[g] }]}>
                                    <input
                                        type="radio"
                                        name="game"
                                        value={g}
                                        disabled={!available[g]}
                                        bind:group={chosenGame}
                                    />
                                    <span>
                                        <strong>{t(`quiz.games.${g}`)}</strong>
                                        <span class="text"
                                            >{t(
                                                available[g]
                                                    ? `quiz.games.${g}_text`
                                                    : 'quiz.games.unavailable',
                                            )}</span
                                        >
                                    </span>
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>{t('quiz.setup.level')}</legend>
                        <div class="choices">
                            {#each LEVELS as l (l)}
                                <label class="choice">
                                    <input
                                        type="radio"
                                        name="level"
                                        value={l}
                                        bind:group={chosenLevel}
                                    />
                                    <span>
                                        <strong>{t(`quiz.levels.${l}`)}</strong>
                                        <span class="text">{t(`quiz.levels.${l}_text`)}</span>
                                    </span>
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                    <div class="go">
                        <button type="submit" class="vff-btn primary"
                            >{t('quiz.setup.start')}</button
                        >
                        <p>{t('quiz.setup.privacy')}</p>
                    </div>
                </form>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .quiz {
        padding-block: var(--vff-section-space);
    }

    .setup {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        max-width: 880px;
    }

    .setup h2 {
        margin: 0;
        font-size: var(--vff-fs-h2);
    }

    fieldset {
        margin: 0;
        padding: 0;
        border: 0;
        min-width: 0;
    }

    legend {
        padding: 0;
        margin-bottom: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
    }

    .choices {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }

    .choice {
        flex: 1 1 18rem;
        min-width: 0;
        display: flex;
        gap: var(--vff-space-3);
        align-items: flex-start;
        padding: var(--vff-space-4) var(--vff-space-5);
        background: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }

    .choice:hover {
        background: var(--vff-bg-muted);
    }

    .choice:has(input:checked) {
        background: var(--vff-bg-accent);
        border-color: var(--vff-accent-ink);
    }

    .choice.off {
        cursor: default;
        color: var(--vff-text-muted);
    }

    .choice input {
        width: 24px;
        height: 24px;
        margin: 2px 0 0;
        flex: none;
        accent-color: var(--vff-accent-ink);
    }

    .choice strong {
        display: block;
        font-weight: 700;
    }

    .choice .text {
        display: block;
        margin-top: var(--vff-space-1);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        text-wrap: pretty;
    }

    .go {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-5);
    }

    .go p {
        margin: 0;
        flex: 1 1 20rem;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
</style>
