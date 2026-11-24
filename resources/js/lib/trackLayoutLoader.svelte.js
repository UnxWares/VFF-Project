// Lecture du schéma des voies d'une ligne (GET /api/v1/lines/{line}/track-layout) sans jamais bloquer la page :
//
// 1. un aperçu léger (`kinds=operational_points`) : étendue, sources, points d'exploitation, couverture et nombre
//    d'objets de chaque famille (`counts`), assez pour le résumé de la fiche et pour l'axe ;
// 2. une ligne de moins de FULL_LIMIT objets est ensuite lue d'un coup ; une plus longue (Paris - Marseille : 4 Mo)
//    par portions d'environ 10 km (chunkBounds), au fil du défilement et du zoom (`ensure`), la plus proche du milieu
//    de la vue d'abord, deux à la fois au plus. Les portions lues restent en mémoire.

import { SvelteMap } from 'svelte/reactivity';
import {
    DETAIL_KINDS,
    FULL_LIMIT,
    MAX_CHUNKS,
    chunkBounds,
    chunksFor,
    fetchTrackLayout,
    mergeLayouts,
    objectCount,
} from '$lib/trackLayout.js';

/** Lectures simultanées au plus. */
const PARALLEL = 2;

export class TrackLayoutLoader {
    /** @type {'idle'|'loading'|'ready'|'error'} lecture de l'aperçu */
    status = $state('idle');
    /** @type {'idle'|'loading'|'ready'|'error'} lecture du détail d'une ligne courte (une longue est « ready » d'emblée) */
    detail = $state('idle');
    /** @type {any} aperçu (kinds=operational_points) */
    overview = $state.raw(null);
    /** @type {any} réponse complète d'une ligne courte */
    full = $state.raw(null);
    /** @type {SvelteMap<number, any>} portions lues, par index */
    parts = new SvelteMap();
    /** Portions en cours de lecture. */
    loading = $state(0);
    /** La vue demande trop de portions (ligne entière ajustée) : rien n'est lu avant un zoom. */
    tooWide = $state(false);
    /** Une portion n'a pas pu être lue. */
    failed = $state(false);

    /** @type {Array<[number, number]>} */
    bounds = $derived.by(() => {
        const o = this.overview;
        if (!o || !this.chunked) return [];
        return chunkBounds(
            o.pk?.start_m ?? 0,
            o.pk?.end_m ?? 0,
            (o.operational_points ?? []).map((p) => p.pk_m),
        );
    });

    /** Ligne lue par portions. */
    chunked = $derived(objectCount(this.overview?.counts) > FULL_LIMIT);

    /** Réponse au format du contrat : complète, ou aperçu et portions lues. */
    layout = $derived.by(() => {
        if (this.full) return this.full;
        if (!this.overview) return null;
        return this.chunked ? mergeLayouts(this.overview, [...this.parts.values()]) : null;
    });

    /** @type {number[]} portions à lire, la plus urgente d'abord */
    #queue = [];
    /** @type {number[]} portions en cours de lecture */
    #inflight = [];

    /**
     * @param {string} slug
     * @param {typeof fetch} [fetchImpl]
     */
    constructor(slug, fetchImpl = (url, init) => fetch(url, init)) {
        this.slug = slug;
        this.fetch = fetchImpl;
    }

    /** @type {Promise<void>|null} lecture de l'aperçu en cours */
    #overviewRead = null;

    /** Lit l'aperçu (résumé de la fiche, axe) ; un second appel pendant la lecture attend la même. */
    loadOverview() {
        this.#overviewRead ??= this.#readOverview().finally(() => {
            this.#overviewRead = null;
        });
        return this.#overviewRead;
    }

    async #readOverview() {
        this.status = 'loading';
        try {
            this.overview = await fetchTrackLayout(this.slug, this.fetch, {
                kinds: ['operational_points'],
            });
            this.status = 'ready';
        } catch {
            this.status = 'error';
        }
    }

    /** Lit le détail : toute la ligne si elle est courte ; sinon rien, les portions viennent avec `ensure`. */
    async loadDetail() {
        if (this.detail === 'loading' || this.full) return;
        if (!this.overview) await this.loadOverview();
        if (!this.overview) return;
        if (this.chunked) {
            this.detail = 'ready';
            return;
        }
        this.detail = 'loading';
        try {
            this.full = await fetchTrackLayout(this.slug, this.fetch);
            this.detail = 'ready';
        } catch {
            this.detail = 'error';
        }
    }

    /**
     * Portion de ligne visible (PK en mètres, marges comprises) : lit les portions qui manquent, la plus proche du
     * milieu d'abord. Les demandes d'une vue précédente encore en attente sont remplacées.
     */
    ensure(from, to) {
        if (!this.chunked || !Number.isFinite(from) || !Number.isFinite(to)) return;
        const wanted = chunksFor(this.bounds, Math.min(from, to), Math.max(from, to));
        this.tooWide = wanted.length > MAX_CHUNKS;
        if (this.tooWide) {
            this.#queue = [];
            return;
        }
        this.#queue = wanted.filter((i) => !this.parts.has(i) && !this.#inflight.includes(i));
        this.#pump();
    }

    #pump() {
        while (this.#inflight.length < PARALLEL && this.#queue.length) {
            const i = /** @type {number} */ (this.#queue.shift());
            this.#read(i);
        }
    }

    async #read(i) {
        const [from, to] = this.bounds[i];
        this.#inflight.push(i);
        this.loading = this.#inflight.length;
        try {
            const part = await fetchTrackLayout(this.slug, this.fetch, {
                from_pk_m: from,
                to_pk_m: to,
                kinds: DETAIL_KINDS,
            });
            this.parts.set(i, part);
            this.failed = false;
        } catch {
            this.failed = true;
        } finally {
            this.#inflight = this.#inflight.filter((j) => j !== i);
            this.loading = this.#inflight.length;
            this.#pump();
        }
    }
}
