<script>
    import maplibregl from '$lib/maplibre.js';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import HistoricBasemapPicker from './HistoricBasemapPicker.svelte';
    import {
        boundsOf,
        dedupe,
        distanceM,
        nearestOnLine,
        portionColor,
        round7,
        samePoint,
    } from '$lib/traceGeometry.js';

    /**
     * Carte de l'éditeur de tracé.
     *
     * Modes : view (naviguer, sélectionner, reprendre un tracé de référence),
     *         draw (dessiner une portion ; partir de l'extrémité de la portion
     *               sélectionnée la prolonge), edit (déplacer / insérer /
     *               supprimer des sommets), cut (couper une portion en deux).
     * Accrochage (snap) aux tracés de référence (source_traces, line_segments),
     * aux autres portions et au tracé actuel de la ligne. Maj enfoncée = sans snap.
     *
     * Les portions ({ key, label, from, to, coordinates, events, origin }) sont
     * possédées par la page parente (bind:portions) ; `onBeforeChange` est
     * appelé avant chaque modification (pile d'annulation).
     */
    let {
        portions = $bindable([]),
        selectedKey = $bindable(null),
        mode = $bindable('view'),
        snapEnabled = $bindable(true),
        basemap = $bindable('ign-scan50-1950'),
        opacity = $bindable(0.8),
        line = null,
        era = 2030,
        height = '620px',
        canUndo = false,
        onUndo = null,
        onBeforeChange = () => {},
        onImport = null,
        onLineLoaded = null,
        newKey = () => `p${Date.now().toString(36)}`,
    } = $props();

    const SNAP_PX = 12;
    const REF_MIN_ZOOM = 10;
    const EMPTY = { type: 'FeatureCollection', features: [] };
    const REF_SOURCES = [
        { key: 'archeoferroviaire', color: '#a0522d' },
        { key: 'ohm', color: '#7c3aed' },
        { key: 'bdtopo', color: '#0f766e' },
        { key: 'contrib', color: '#16a34a' },
    ];
    const SNAP_LAYERS = [
        'draft-line',
        'ref-traces',
        'ref-segments',
        'ref-before-line',
        'ref-ctx-lines',
    ];

    let mapContainer = $state();
    // $state.raw : référence réactive à l'instance MapLibre, sans proxy profond.
    let map = $state.raw(null);
    let ready = $state(false);
    let popup = null;

    let drawing = $state([]);
    let cursor = $state(null);
    let snapHit = $state(null);
    let dragging = null;
    let dragMoved = false;
    let suppressClick = false;
    let shiftDown = false;

    let refState = $state('zoom'); // zoom | loading | ok | truncated | error
    let refEnabled = $state(Object.fromEntries(REF_SOURCES.map((s) => [s.key, true])));
    let showSegments = $state(true);
    let refIndex = new Map();
    let beforeIndex = new Map();
    let refAbort = null;
    let refTimer = null;
    // Légende repliée d'emblée sur téléphone (elle masquerait la moitié de la carte).
    let legendOpen = $state(
        typeof window === 'undefined' || !window.matchMedia?.('(max-width: 600px)').matches,
    );

    const selected = $derived(portions.find((p) => p.key === selectedKey) ?? null);
    // Dépend uniquement du slug (pas de l'objet) : évite de recharger à chaque mise à jour du parent.
    const lineSlug = $derived(line?.slug ?? null);

    // ── Rendu GeoJSON ───────────────────────────────────────────────────────
    function draftFC() {
        const snap = $state.snapshot(portions);
        return {
            type: 'FeatureCollection',
            features: snap
                .map((p, i) => ({
                    type: 'Feature',
                    properties: {
                        key: p.key,
                        color: portionColor(i),
                        selected: p.key === selectedKey,
                    },
                    geometry: { type: 'LineString', coordinates: p.coordinates },
                }))
                .filter((f) => f.geometry.coordinates.length >= 2),
        };
    }

    function verticesFC() {
        if (!['edit', 'cut'].includes(mode)) return EMPTY;
        const idx = portions.findIndex((p) => p.key === selectedKey);
        if (idx < 0) return EMPTY;
        const coords = $state.snapshot(portions[idx].coordinates);
        const color = portionColor(idx);
        const features = coords.map((c, i) => ({
            type: 'Feature',
            properties: {
                key: selectedKey,
                idx: i,
                mid: false,
                end: i === 0 || i === coords.length - 1,
                color,
            },
            geometry: { type: 'Point', coordinates: c },
        }));
        if (mode === 'edit') {
            for (let i = 0; i < coords.length - 1; i++) {
                const a = coords[i];
                const b = coords[i + 1];
                features.push({
                    type: 'Feature',
                    properties: { key: selectedKey, idx: i, mid: true, end: false, color },
                    geometry: {
                        type: 'Point',
                        coordinates: [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2],
                    },
                });
            }
        }
        return { type: 'FeatureCollection', features };
    }

    function drawFC() {
        const pts = $state.snapshot(drawing);
        const features = pts.map((c) => ({
            type: 'Feature',
            properties: {},
            geometry: { type: 'Point', coordinates: c },
        }));
        const linePts =
            mode === 'draw' && cursor && pts.length ? [...pts, $state.snapshot(cursor)] : pts;
        if (linePts.length >= 2) {
            features.push({
                type: 'Feature',
                properties: {},
                geometry: { type: 'LineString', coordinates: linePts },
            });
        }
        return { type: 'FeatureCollection', features };
    }

    $effect(() => {
        if (!ready) return;
        map.getSource('draft')?.setData(draftFC());
        map.getSource('edit-vertices')?.setData(verticesFC());
    });

    $effect(() => {
        if (!ready) return;
        map.getSource('draw-temp')?.setData(drawFC());
    });

    $effect(() => {
        if (!ready) return;
        const c = snapHit ? $state.snapshot(snapHit) : null;
        map.getSource('snap-pt')?.setData(
            c
                ? {
                      type: 'Feature',
                      properties: { cut: mode === 'cut' },
                      geometry: { type: 'Point', coordinates: c },
                  }
                : EMPTY,
        );
    });

    // Mode : curseur, double-clic, abandon du dessin en cours.
    $effect(() => {
        const m = mode;
        if (!ready) return;
        map.getCanvas().style.cursor = m === 'draw' || m === 'cut' ? 'crosshair' : '';
        if (m === 'draw') map.doubleClickZoom.disable();
        else map.doubleClickZoom.enable();
        if (m !== 'draw') {
            drawing = [];
            cursor = null;
        }
        snapHit = null;
        popup?.remove();
    });

    // Ligne de référence (« avant ») : tracé actuel + tracés bruts rattachés.
    $effect(() => {
        const slug = lineSlug;
        if (!ready) return;
        loadBefore(slug);
    });

    // Filtre d'affichage des sources de référence.
    $effect(() => {
        const enabled = REF_SOURCES.filter((s) => refEnabled[s.key]).map((s) => s.key);
        const segs = showSegments;
        if (!ready) return;
        map.setFilter('ref-traces', [
            'all',
            ['==', ['get', 'kind'], 'trace'],
            ['in', ['get', 'source'], ['literal', enabled]],
        ]);
        map.setLayoutProperty('ref-segments', 'visibility', segs ? 'visible' : 'none');
        scheduleReference();
    });

    // ── Données de référence ────────────────────────────────────────────────
    function linesOf(geometry) {
        if (!geometry) return [];
        if (geometry.type === 'LineString') return [geometry.coordinates];
        if (geometry.type === 'MultiLineString') return geometry.coordinates;
        return [];
    }

    function scheduleReference() {
        clearTimeout(refTimer);
        refTimer = setTimeout(loadReference, 250);
    }

    async function loadReference() {
        if (!map) return;
        const z = map.getZoom();
        if (z < REF_MIN_ZOOM) {
            refAbort?.abort();
            refIndex = new Map();
            map.getSource('ref')?.setData(EMPTY);
            refState = 'zoom';
            return;
        }
        const b = map.getBounds();
        const padX = (b.getEast() - b.getWest()) * 0.1;
        const padY = (b.getNorth() - b.getSouth()) * 0.1;
        const bbox = [
            b.getWest() - padX,
            b.getSouth() - padY,
            b.getEast() + padX,
            b.getNorth() + padY,
        ]
            .map((v) => v.toFixed(5))
            .join(',');
        const params = new URLSearchParams({
            bbox,
            zoom: z.toFixed(1),
            sources: REF_SOURCES.filter((s) => refEnabled[s.key])
                .map((s) => s.key)
                .join(','),
            segments: showSegments ? '1' : '0',
        });
        refAbort?.abort();
        refAbort = new AbortController();
        refState = 'loading';
        try {
            const res = await fetch(`/contribute/trace/reference?${params}`, {
                signal: refAbort.signal,
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            // Index local d'une fonction, jamais lu par le rendu : pas de réactivité.
            // eslint-disable-next-line svelte/prefer-svelte-reactivity
            const idx = new Map();
            for (const f of data.features ?? [])
                idx.set(`${f.properties.kind}:${f.properties.id}`, linesOf(f.geometry));
            refIndex = idx;
            map?.getSource('ref')?.setData(data);
            refState = data.too_large ? 'zoom' : data.truncated ? 'truncated' : 'ok';
        } catch (err) {
            if (err.name !== 'AbortError') refState = 'error';
        }
    }

    async function loadBefore(slug) {
        beforeIndex = new Map();
        map.getSource('ref-before')?.setData(EMPTY);
        if (!slug) return;
        try {
            const res = await fetch(`/contribute/trace/lines/${encodeURIComponent(slug)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            const features = [
                ...(data.segments?.features ?? []),
                ...(data.traces?.features ?? []),
            ].map((f) => ({
                ...f,
                properties: {
                    ...f.properties,
                    line_id: data.line.id,
                    line_name: data.line.name,
                    line_slug: data.line.slug,
                },
            }));
            // Index local d'une fonction, jamais lu par le rendu : pas de réactivité.
            // eslint-disable-next-line svelte/prefer-svelte-reactivity
            const idx = new Map();
            for (const f of features)
                idx.set(`${f.properties.kind}:${f.properties.id}`, linesOf(f.geometry));
            beforeIndex = idx;
            map.getSource('ref-before')?.setData({ type: 'FeatureCollection', features });
            onLineLoaded?.(data);
            if (data.bbox && !portions.length) {
                map.fitBounds(
                    [
                        [data.bbox[0], data.bbox[1]],
                        [data.bbox[2], data.bbox[3]],
                    ],
                    { padding: 60, duration: 600, maxZoom: 13 },
                );
            }
        } catch (_) {
            /* l'éditeur reste utilisable sans le tracé « avant » */
        }
    }

    // ── Accrochage ──────────────────────────────────────────────────────────
    function candidateLines(f) {
        const lid = f.layer.id;
        if (lid === 'draft-line') {
            const p = portions.find((q) => q.key === f.properties.key);
            return p ? [$state.snapshot(p.coordinates)] : [];
        }
        if (f.properties?.kind && f.properties?.id != null) {
            const k = `${f.properties.kind}:${f.properties.id}`;
            const hit = lid === 'ref-before-line' ? beforeIndex.get(k) : refIndex.get(k);
            if (hit) return hit;
        }
        return linesOf(f.geometry);
    }

    const project = (c) => map.project(c);
    const unproject = (s) => {
        const ll = map.unproject([s.x, s.y]);
        return [ll.lng, ll.lat];
    };
    const rank = (h) => h.d - (h.type === 'vertex' ? SNAP_PX * 0.5 : 0);

    function snapAt(point, excludeKey = null) {
        if (!snapEnabled || shiftDown || !map) return null;
        const r = SNAP_PX;
        const layers = SNAP_LAYERS.filter(
            (id) => map.getLayer(id) && map.getLayoutProperty(id, 'visibility') !== 'none',
        );
        const feats = map.queryRenderedFeatures(
            [
                [point.x - r, point.y - r],
                [point.x + r, point.y + r],
            ],
            { layers },
        );
        let best = null;
        // Index local d'une fonction, jamais lu par le rendu : pas de réactivité.
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
        const seen = new Set();
        for (const f of feats) {
            if (f.layer.id === 'draft-line' && f.properties.key === excludeKey) continue;
            const sig =
                f.layer.id === 'draft-line'
                    ? `d:${f.properties.key}`
                    : f.properties?.id != null
                      ? `${f.layer.id}:${f.properties.kind}:${f.properties.id}`
                      : null;
            if (sig) {
                if (seen.has(sig)) continue;
                seen.add(sig);
            }
            for (const coords of candidateLines(f)) {
                const hit = nearestOnLine(coords, point, project, r, unproject);
                if (hit && (!best || rank(hit) < rank(best))) best = hit;
            }
        }
        return best;
    }

    /** Cible de coupe : point le plus proche sur une portion (la sélectionnée en priorité). */
    function cutTarget(point) {
        const r = SNAP_PX;
        const order = selected ? [selected, ...portions.filter((p) => p !== selected)] : portions;
        for (const p of order) {
            const coords = $state.snapshot(p.coordinates);
            const hit = nearestOnLine(coords, point, project, r, unproject);
            if (hit) return { ...hit, key: p.key, n: coords.length };
        }
        return null;
    }

    // ── Édition ─────────────────────────────────────────────────────────────
    function finishDrawing() {
        // Retire les sommets quasi confondus (double-clic de fin).
        const pts = [];
        for (const c of dedupe($state.snapshot(drawing))) {
            if (!pts.length || distanceM(pts[pts.length - 1], c) > 0.5) pts.push(c);
        }
        drawing = [];
        if (pts.length < 2) return;
        onBeforeChange();
        // Prolongement de la portion sélectionnée si l'on part de l'une de ses extrémités.
        const sel = portions.find((p) => p.key === selectedKey);
        if (sel && sel.coordinates.length >= 2) {
            const sc = sel.coordinates;
            if (samePoint(pts[0], sc[sc.length - 1])) {
                sel.coordinates.push(...pts.slice(1));
                return;
            }
            if (samePoint(pts[0], sc[0])) {
                sel.coordinates.unshift(...pts.slice(1).reverse());
                return;
            }
        }
        const p = {
            key: newKey(),
            label: '',
            from: '',
            to: '',
            coordinates: pts,
            events: [],
            origin: null,
        };
        portions.push(p);
        selectedKey = p.key;
    }

    function cutAt(t) {
        const i = portions.findIndex((p) => p.key === t.key);
        if (i < 0) return;
        const p = portions[i];
        const coords = $state.snapshot(p.coordinates);
        let k = t.index;
        if (t.type === 'edge') {
            coords.splice(t.index + 1, 0, round7(t.coord));
            k = t.index + 1;
        }
        if (k <= 0 || k >= coords.length - 1) return; // extrémité : rien à couper
        onBeforeChange();
        const b = {
            key: newKey(),
            label: p.label ? `${p.label} (2)` : '',
            from: '',
            to: p.to ?? '',
            coordinates: coords.slice(k),
            events: structuredClone($state.snapshot(p.events ?? [])),
            origin: p.origin ? structuredClone($state.snapshot(p.origin)) : null,
        };
        p.coordinates = coords.slice(0, k + 1);
        p.to = '';
        portions.splice(i + 1, 0, b);
        selectedKey = b.key;
    }

    function deleteVertex(key, idx) {
        const p = portions.find((q) => q.key === key);
        if (!p || p.coordinates.length <= 2) return;
        onBeforeChange();
        p.coordinates.splice(idx, 1);
    }

    function startDrag(key, idx) {
        onBeforeChange();
        dragging = { key, idx };
        dragMoved = false;
        map.dragPan.disable();
    }

    function endDrag() {
        if (!dragging) return;
        dragging = null;
        map?.dragPan.enable();
        snapHit = null;
        // Le « click » DOM éventuel suit immédiatement le mouseup : on ne l'ignore
        // que dans ce même tour d'événements, sinon le clic suivant serait perdu.
        if (dragMoved) {
            suppressClick = true;
            setTimeout(() => (suppressClick = false), 0);
        }
    }

    function onMove(e) {
        const p = e.point;
        if (dragging) {
            const hit = snapAt(p, dragging.key);
            const c = round7(hit ? hit.coord : [e.lngLat.lng, e.lngLat.lat]);
            const portion = portions.find((q) => q.key === dragging.key);
            if (portion) portion.coordinates[dragging.idx] = c;
            dragMoved = true;
            snapHit = hit ? c : null;
            return;
        }
        if (mode === 'draw') {
            const hit = snapAt(p);
            cursor = round7(hit ? hit.coord : [e.lngLat.lng, e.lngLat.lat]);
            snapHit = hit ? cursor : null;
            return;
        }
        if (mode === 'cut') {
            const t = cutTarget(p);
            snapHit = t ? round7(t.coord) : null;
            return;
        }
        if (snapHit) snapHit = null;
    }

    const box = (pt, r) => [
        [pt.x - r, pt.y - r],
        [pt.x + r, pt.y + r],
    ];

    function onClick(e) {
        if (suppressClick) {
            suppressClick = false;
            return;
        }
        if (mode === 'draw') {
            const hit = snapAt(e.point);
            const c = round7(hit ? hit.coord : [e.lngLat.lng, e.lngLat.lat]);
            if (!samePoint(drawing[drawing.length - 1], c)) drawing.push(c);
            return;
        }
        if (mode === 'cut') {
            const t = cutTarget(e.point);
            if (t) cutAt(t);
            return;
        }
        const hits = map.queryRenderedFeatures(box(e.point, 6), { layers: ['draft-line'] });
        if (hits.length) {
            selectedKey = hits[0].properties.key;
            return;
        }
        if (mode === 'view' && onImport) {
            const layers = ['ref-traces', 'ref-segments', 'ref-before-line'].filter(
                (id) => map.getLayer(id) && map.getLayoutProperty(id, 'visibility') !== 'none',
            );
            const refs = map.queryRenderedFeatures(box(e.point, 5), { layers });
            if (refs.length) openRefPopup(refs, e.lngLat);
        }
    }

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function openRefPopup(features, lngLat) {
        const t = $i18n.t.bind($i18n);
        // Index local d'une fonction, jamais lu par le rendu : pas de réactivité.
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
        const seen = new Set();
        const items = [];
        for (const f of features) {
            const k = `${f.properties.kind}:${f.properties.id}`;
            if (seen.has(k)) continue;
            seen.add(k);
            items.push(f.properties);
            if (items.length >= 4) break;
        }
        const root = el('div', 'trace-popup');
        for (const p of items) {
            const block = el('div', 'trace-popup-item');
            const title =
                p.kind === 'segment'
                    ? (p.line_name ?? t('editor.map.segment'))
                    : p.name || `${t(`editor.sources.${p.source}`)} · ${p.layer ?? ''}`;
            block.append(el('strong', null, title));
            const meta =
                p.kind === 'segment'
                    ? `${t('editor.map.segment')} #${p.id}${p.era ? ` · ${p.era}` : ''}${p.status ? ` · ${p.status}` : ''}`
                    : `${t(`editor.sources.${p.source}`)}${p.layer ? ` · ${p.layer}` : ''}${p.opened_year ? ` · ${p.opened_year}` : ''}${p.closed_year ? `-${p.closed_year}` : ''}`;
            block.append(el('small', null, meta));
            const actions = el('div', 'trace-popup-actions');
            const btn = el(
                'button',
                'trace-popup-btn',
                t(p.kind === 'segment' ? 'editor.map.take_segment' : 'editor.map.take_trace'),
            );
            btn.type = 'button';
            btn.addEventListener('click', () => {
                popup?.remove();
                onImport({ kind: p.kind, id: p.id });
            });
            actions.append(btn);
            if (p.kind === 'segment' && p.line_id) {
                const all = el('button', 'trace-popup-btn ghost', t('editor.map.take_line'));
                all.type = 'button';
                all.addEventListener('click', () => {
                    popup?.remove();
                    onImport({ kind: 'line', id: p.line_id });
                });
                actions.append(all);
            }
            block.append(actions);
            root.append(block);
        }
        popup?.remove();
        popup = new maplibregl.Popup({ closeButton: true, maxWidth: '320px' })
            .setLngLat(lngLat)
            .setDOMContent(root)
            .addTo(map);
    }

    // ── API publique (bind:this) ────────────────────────────────────────────
    export function fitPortions(list = null) {
        const src = list ?? $state.snapshot(portions).map((p) => p.coordinates);
        const b = boundsOf(src);
        if (b && map) map.fitBounds(b, { padding: 70, duration: 600, maxZoom: 16 });
    }

    export function flyTo(lng, lat, zoom = 13) {
        map?.flyTo({ center: [lng, lat], zoom, duration: 900 });
    }

    // ── Clavier ─────────────────────────────────────────────────────────────
    function isTyping(e) {
        const tag = e.target?.tagName;
        return (
            tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable
        );
    }

    function onKeydown(e) {
        if (e.key === 'Shift') shiftDown = true;
        if (isTyping(e)) return;
        if (e.key === 'Escape') {
            if (drawing.length) drawing = [];
            else if (mode !== 'view') mode = 'view';
            popup?.remove();
        } else if (e.key === 'Enter' && mode === 'draw') {
            e.preventDefault();
            finishDrawing();
        } else if (
            (e.key === 'Backspace' || e.key === 'Delete') &&
            mode === 'draw' &&
            drawing.length
        ) {
            e.preventDefault();
            drawing.pop();
        }
    }

    function onKeyup(e) {
        if (e.key === 'Shift') shiftDown = false;
    }

    // ── Initialisation ──────────────────────────────────────────────────────
    /** @returns {import('maplibre-gl').StyleSpecification} */
    function buildStyle() {
        return {
            version: 8,
            sources: {
                'osm-raster': {
                    type: 'raster',
                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    tileSize: 256,
                    maxzoom: 19,
                    attribution: $i18n.t('common.osm_attribution', {
                        link: '<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }),
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
        };
    }

    function addLayers() {
        map.addSource('ref-ctx', {
            type: 'vector',
            tiles: [`${window.location.origin}/api/v1/eras/${era}/tiles/{z}/{x}/{y}.mvt`],
            minzoom: 0,
            maxzoom: 14,
        });
        map.addLayer({
            id: 'ref-ctx-lines',
            type: 'line',
            source: 'ref-ctx',
            'source-layer': 'vff-lines',
            maxzoom: REF_MIN_ZOOM,
            paint: { 'line-color': '#475569', 'line-width': 1.6, 'line-opacity': 0.75 },
        });
        map.addSource('ref-before', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'ref-before-line',
            type: 'line',
            source: 'ref-before',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#38bdf8',
                'line-width': ['interpolate', ['linear'], ['zoom'], 6, 4, 14, 11],
                'line-opacity': 0.45,
            },
        });
        map.addSource('ref', {
            type: 'geojson',
            data: EMPTY,
            attribution: $i18n.t('editor.map.ref_attribution'),
        });
        map.addLayer({
            id: 'ref-segments',
            type: 'line',
            source: 'ref',
            filter: ['==', ['get', 'kind'], 'segment'],
            paint: { 'line-color': '#475569', 'line-width': 2, 'line-opacity': 0.85 },
        });
        map.addLayer({
            id: 'ref-traces',
            type: 'line',
            source: 'ref',
            filter: ['==', ['get', 'kind'], 'trace'],
            paint: {
                'line-color': [
                    'match',
                    ['get', 'source'],
                    ...REF_SOURCES.flatMap((s) => [s.key, s.color]),
                    '#6b7280',
                ],
                'line-width': 2,
                'line-dasharray': [2, 1.5],
                'line-opacity': 0.9,
            },
        });
        map.addSource('draft', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'draft-casing',
            type: 'line',
            source: 'draft',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#ffffff',
                'line-width': ['case', ['get', 'selected'], 10, 7],
                'line-opacity': 0.9,
            },
        });
        map.addLayer({
            id: 'draft-line',
            type: 'line',
            source: 'draft',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': ['get', 'color'],
                'line-width': ['case', ['get', 'selected'], 5.5, 3.5],
            },
        });
        map.addSource('draw-temp', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'draw-line',
            type: 'line',
            source: 'draw-temp',
            filter: ['==', ['geometry-type'], 'LineString'],
            paint: { 'line-color': '#ff6500', 'line-width': 3, 'line-dasharray': [2, 1] },
        });
        map.addLayer({
            id: 'draw-pts',
            type: 'circle',
            source: 'draw-temp',
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 4.5,
                'circle-color': '#ffffff',
                'circle-stroke-color': '#ff6500',
                'circle-stroke-width': 2,
            },
        });
        map.addSource('edit-vertices', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'edit-mid',
            type: 'circle',
            source: 'edit-vertices',
            filter: ['==', ['get', 'mid'], true],
            paint: {
                'circle-radius': 4,
                'circle-color': '#ffffff',
                'circle-opacity': 0.85,
                'circle-stroke-color': ['get', 'color'],
                'circle-stroke-width': 1.5,
                'circle-stroke-opacity': 0.8,
            },
        });
        map.addLayer({
            id: 'edit-vertex',
            type: 'circle',
            source: 'edit-vertices',
            filter: ['==', ['get', 'mid'], false],
            paint: {
                'circle-radius': ['case', ['get', 'end'], 7, 5.5],
                'circle-color': ['case', ['get', 'end'], ['get', 'color'], '#ffffff'],
                'circle-stroke-color': ['case', ['get', 'end'], '#ffffff', ['get', 'color']],
                'circle-stroke-width': 2.5,
            },
        });
        map.addSource('snap-pt', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'snap-ring',
            type: 'circle',
            source: 'snap-pt',
            paint: {
                'circle-radius': 9,
                'circle-color': 'rgba(0,0,0,0)',
                'circle-stroke-color': ['case', ['get', 'cut'], '#dc2626', '#16a34a'],
                'circle-stroke-width': 3,
            },
        });
    }

    function setupInteractions() {
        map.on('mousemove', onMove);
        map.on('click', onClick);
        map.on('dblclick', (e) => {
            if (mode !== 'draw') return;
            e.preventDefault();
            finishDrawing();
        });
        map.on('mouseup', endDrag);
        map.on('touchend', endDrag);
        map.on('touchmove', (e) => {
            if (!dragging) return;
            e.preventDefault();
            onMove(e);
        });

        const grabVertex = (e) => {
            if (mode !== 'edit' || (e.originalEvent.button ?? 0) !== 0) return;
            const f = e.features?.[0];
            if (!f) return;
            e.preventDefault();
            if (e.originalEvent.altKey) {
                deleteVertex(f.properties.key, f.properties.idx);
                return;
            }
            startDrag(f.properties.key, f.properties.idx);
        };
        const grabMid = (e) => {
            if (mode !== 'edit' || (e.originalEvent.button ?? 0) !== 0) return;
            if (map.queryRenderedFeatures(e.point, { layers: ['edit-vertex'] }).length) return;
            const f = e.features?.[0];
            if (!f) return;
            e.preventDefault();
            const p = portions.find((q) => q.key === f.properties.key);
            if (!p) return;
            onBeforeChange();
            p.coordinates.splice(f.properties.idx + 1, 0, round7(f.geometry.coordinates));
            dragging = { key: p.key, idx: f.properties.idx + 1 };
            dragMoved = false;
            map.dragPan.disable();
        };
        map.on('mousedown', 'edit-vertex', grabVertex);
        map.on('touchstart', 'edit-vertex', grabVertex);
        map.on('mousedown', 'edit-mid', grabMid);
        map.on('touchstart', 'edit-mid', grabMid);
        map.on('contextmenu', 'edit-vertex', (e) => {
            if (mode !== 'edit') return;
            e.preventDefault();
            e.originalEvent.preventDefault();
            const f = e.features?.[0];
            if (f) deleteVertex(f.properties.key, f.properties.idx);
        });

        for (const id of ['edit-vertex', 'edit-mid']) {
            map.on('mouseenter', id, () => {
                if (mode === 'edit') map.getCanvas().style.cursor = 'move';
            });
            map.on('mouseleave', id, () => {
                if (mode === 'edit') map.getCanvas().style.cursor = '';
            });
        }
        for (const id of ['ref-traces', 'ref-segments', 'ref-before-line', 'draft-line']) {
            map.on('mouseenter', id, () => {
                if (mode === 'view') map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', id, () => {
                if (mode === 'view') map.getCanvas().style.cursor = '';
            });
        }
        map.on('moveend', scheduleReference);
    }

    onMount(() => {
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center: [2.5, 46.8],
            zoom: 5.5,
            attributionControl: { compact: true },
            dragRotate: false,
            pitchWithRotate: false,
        });
        map.touchZoomRotate.disableRotation();
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-right');
        map.on('load', () => {
            addLayers();
            setupInteractions();
            ready = true;
            scheduleReference();
            // Sur téléphone, crédits repliés derrière leur bouton (i) : dépliés, ils couvraient
            // le tiers de la carte.
            if (window.matchMedia?.('(max-width: 600px)').matches) {
                mapContainer
                    ?.querySelector('.maplibregl-ctrl-attrib')
                    ?.classList.remove('maplibregl-compact-show');
            }
        });
        // Crochet de test bout-en-bout (serveur de dev Vite uniquement).
        if (import.meta.env.DEV) window.__vffTraceMap = map;
        window.addEventListener('mouseup', endDrag);
    });

    onDestroy(() => {
        clearTimeout(refTimer);
        refAbort?.abort();
        window.removeEventListener('mouseup', endDrag);
        popup?.remove();
        map?.remove();
        map = null;
    });

    const MODES = [
        { key: 'view', icon: 'bi-hand-index' },
        { key: 'draw', icon: 'bi-pencil' },
        { key: 'edit', icon: 'bi-bezier2' },
        { key: 'cut', icon: 'bi-scissors' },
    ];

    const hint = $derived.by(() => {
        if (mode === 'draw') {
            return drawing.length
                ? $i18n.t('editor.hints.drawing', { count: drawing.length })
                : $i18n.t('editor.hints.draw');
        }
        if (mode === 'edit')
            return selected ? $i18n.t('editor.hints.edit') : $i18n.t('editor.hints.select_first');
        if (mode === 'cut') return $i18n.t('editor.hints.cut');
        return $i18n.t('editor.hints.view');
    });
</script>

<svelte:window onkeydown={onKeydown} onkeyup={onKeyup} />

<div class="editor-map">
    <div class="toolbar">
        <div class="modes" role="toolbar" aria-label={$i18n.t('editor.toolbar.label')}>
            {#each MODES as m (m.key)}
                <button
                    type="button"
                    class="tool"
                    class:active={mode === m.key}
                    aria-pressed={mode === m.key}
                    onclick={() => (mode = m.key)}
                    title={$i18n.t(`editor.toolbar.${m.key}_title`)}
                >
                    <i class="bi {m.icon}" aria-hidden="true"></i>
                    <span class="tool-label">{$i18n.t(`editor.toolbar.${m.key}`)}</span>
                </button>
            {/each}
            {#if mode === 'draw' && drawing.length >= 2}
                <button
                    type="button"
                    class="tool finish"
                    onclick={finishDrawing}
                    aria-keyshortcuts="Enter"
                >
                    <i class="bi bi-check2" aria-hidden="true"></i>
                    <span class="tool-label">{$i18n.t('editor.toolbar.finish')}</span>
                </button>
            {/if}
            <button
                type="button"
                class="tool"
                onclick={() => onUndo?.()}
                disabled={!canUndo}
                title={$i18n.t('editor.toolbar.undo_title')}
                aria-keyshortcuts="Control+Z"
            >
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                <span class="tool-label">{$i18n.t('editor.toolbar.undo')}</span>
            </button>
            <label class="vff-check snap-toggle" title={$i18n.t('editor.toolbar.snap_title')}>
                <input type="checkbox" bind:checked={snapEnabled} />
                <i class="bi bi-magnet" aria-hidden="true"></i>
                {$i18n.t('editor.toolbar.snap')}
            </label>
        </div>
        <div class="basemap">
            <HistoricBasemapPicker map={ready ? map : null} bind:value={basemap} bind:opacity />
        </div>
    </div>
    <p class="hint" aria-live="polite">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>{hint}</span>
    </p>

    <div class="map-wrap">
        <div class="map" bind:this={mapContainer} style:height></div>

        <div class="legend" class:collapsed={!legendOpen}>
            <button
                type="button"
                class="legend-toggle"
                onclick={() => (legendOpen = !legendOpen)}
                aria-expanded={legendOpen}
                aria-controls="trace-legend-body"
            >
                <i class="bi bi-stack" aria-hidden="true"></i>
                {$i18n.t('editor.map.reference')}
                <i class="bi {legendOpen ? 'bi-chevron-up' : 'bi-chevron-down'}" aria-hidden="true"
                ></i>
            </button>
            {#if legendOpen}
                <div class="legend-body" id="trace-legend-body">
                    {#each REF_SOURCES as s (s.key)}
                        <label class="vff-check">
                            <input type="checkbox" bind:checked={refEnabled[s.key]} />
                            <span class="swatch dashed" style:--c={s.color} aria-hidden="true"
                            ></span>
                            {$i18n.t(`editor.sources.${s.key}`)}
                        </label>
                    {/each}
                    <label class="vff-check">
                        <input type="checkbox" bind:checked={showSegments} />
                        <span class="swatch" style:--c="#475569" aria-hidden="true"></span>
                        {$i18n.t('editor.map.segments')}
                    </label>
                    {#if line}
                        <span class="legend-row"
                            ><span class="swatch thick" style:--c="#38bdf8" aria-hidden="true"
                            ></span>
                            {$i18n.t('editor.map.before')}</span
                        >
                    {/if}
                    <p class="ref-state ref-{refState}">
                        {$i18n.t(`editor.map.ref_state.${refState}`, { zoom: REF_MIN_ZOOM })}
                    </p>
                </div>
            {/if}
        </div>
    </div>
</div>

<style>
    .editor-map {
        display: flex;
        flex-direction: column;
        min-width: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-elevated);
    }
    .toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: var(--vff-space-3) var(--vff-space-5);
        padding: var(--vff-space-3);
        background-color: var(--vff-bg);
        border-bottom: 1px solid var(--vff-border);
    }
    .modes {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        align-items: center;
    }
    .tool {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 36px;
        padding: 6px 12px;
        background: var(--vff-bg);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition),
            color var(--vff-transition);
    }
    .tool:hover:not(:disabled):not(.active) {
        border-color: var(--vff-accent-ink);
        color: var(--vff-accent-ink);
    }
    .tool.active {
        background: var(--vff-accent);
        border-color: var(--vff-accent);
        color: var(--vff-on-accent);
    }
    .tool.active:focus-visible {
        outline-color: var(--vff-text);
    }
    .tool.finish {
        border-color: var(--vff-success);
        color: var(--vff-success);
    }
    .tool:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .snap-toggle {
        margin-left: var(--vff-space-2);
        min-height: 36px;
    }
    .snap-toggle .bi {
        color: var(--vff-text-muted);
    }
    .basemap {
        min-width: 0;
        flex: 0 1 460px;
    }
    .hint {
        display: flex;
        align-items: baseline;
        gap: var(--vff-space-2);
        margin: 0;
        padding: var(--vff-space-2) var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
        background-color: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .hint i {
        color: var(--vff-accent-ink);
    }
    .map-wrap {
        position: relative;
    }
    .map {
        width: 100%;
    }
    /* Légende en haut à gauche : la barre d'attribution (bas) reste lisible en entier. */
    .legend {
        position: absolute;
        left: var(--vff-space-3);
        top: var(--vff-space-3);
        z-index: var(--vff-z-raised);
        max-width: 250px;
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
    }
    .legend-toggle {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        min-height: 36px;
        padding: 6px 10px;
        background: transparent;
        border: 0;
        border-radius: var(--vff-radius-md);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .legend-toggle .bi:last-child {
        margin-left: auto;
    }
    .legend-body {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 0 10px 10px;
    }
    .legend-body .vff-check {
        gap: 8px;
        font-size: var(--vff-fs-xs);
    }
    .legend-row {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 24px;
    }
    .swatch {
        width: 18px;
        height: 0;
        border-top: 3px solid var(--c);
        flex-shrink: 0;
    }
    .swatch.dashed {
        border-top-style: dashed;
    }
    .swatch.thick {
        border-top-width: 6px;
        opacity: 0.6;
    }
    .ref-state {
        margin: 6px 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
        line-height: 1.4;
    }
    .ref-state.ref-error {
        color: var(--vff-danger);
    }
    .ref-state.ref-truncated {
        color: var(--vff-warning);
    }
    /* Contrôles et fenêtres MapLibre aux couleurs du thème (clair et sombre). */
    .editor-map :global(.maplibregl-ctrl-attrib),
    .editor-map :global(.maplibregl-ctrl-group),
    .editor-map :global(.maplibregl-ctrl-scale) {
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
    }
    .editor-map :global(.maplibregl-ctrl-scale) {
        border-color: var(--vff-text-muted);
    }
    .editor-map :global(.maplibregl-ctrl-attrib a) {
        color: var(--vff-link);
    }
    :global([data-theme='dark'])
        .editor-map
        :global(.maplibregl-ctrl-group button .maplibregl-ctrl-icon),
    :global([data-theme='dark']) .editor-map :global(.maplibregl-ctrl-attrib-button) {
        filter: invert(1);
    }
    .editor-map :global(.maplibregl-popup-content) {
        padding: 12px 14px;
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .editor-map :global(.maplibregl-popup-close-button) {
        width: 28px;
        height: 28px;
        color: var(--vff-text-muted);
        font-size: 18px;
    }
    :global([data-theme='dark']) .editor-map :global(.maplibregl-popup-tip) {
        border-top-color: var(--vff-bg-elevated);
        border-bottom-color: var(--vff-bg-elevated);
    }
    :global(.trace-popup) {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    :global(.trace-popup-item strong) {
        display: block;
        padding-right: 20px;
    }
    :global(.trace-popup-item small) {
        display: block;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
        margin-bottom: 6px;
    }
    :global(.trace-popup-actions) {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    :global(.trace-popup-btn) {
        min-height: 32px;
        padding: 4px 12px;
        border: 1px solid var(--vff-accent);
        background: var(--vff-accent);
        color: var(--vff-on-accent);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        cursor: pointer;
    }
    :global(.trace-popup-btn:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-text);
        outline-offset: var(--vff-focus-offset);
    }
    :global(.trace-popup-btn.ghost) {
        background: transparent;
        border-color: var(--vff-accent-ink);
        color: var(--vff-accent-ink);
    }
    @media (max-width: 600px) {
        /* Outils en icônes seules : le libellé reste lu (nom accessible). */
        .tool-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
        .tool {
            min-width: 44px;
            min-height: 44px;
            justify-content: center;
        }
        .legend {
            max-width: 200px;
        }
    }
</style>
