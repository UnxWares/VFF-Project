<script>
    import { formatNumber, NBSP } from '$lib/typo.js';
    import {
        BAND_H,
        CROSSING_SWITCHES,
        ROW_H,
        SERVICE_KINDS,
        axisLabel,
        bandCells,
        centerScroll,
        clusterMarks,
        flattenRanges,
        formatPermille,
        joinRanges,
        laneRows,
        meanPermille,
        pathD,
        pkLabel,
        placeBoxes,
        refSpots,
        repeatedPieces,
        viewCenterTracker,
        rowAtX,
        plainPieceAt,
        stackLabels,
        textWidth,
        tickLabels,
        trackPaths,
        valueAt,
    } from '$lib/trackLayout.js';
    import { platformShapes } from '$lib/stationTracks.js';

    /**
     * Dessin du schéma des voies (onglet « Schéma ») ou de la bande seule (onglet « Bande »), dans un cadre qui
     * défile horizontalement : zones grisées et noms des points d'exploitation, voies par rangées, diagonales des
     * aiguillages, traversées, heurtoirs, signaux, passages à niveau, puis la bande des PK, vitesses et
     * déclivités. Le dessin est décoratif pour les lecteurs d'écran : le tableau de parcours donne les mêmes
     * objets (la région le dit). Seule la partie proche de la fenêtre est dessinée, sauf à l'impression ; les
     * rangées sont celles des voies de cette partie (une grande gare n'épaissit pas toute la ligne).
     *
     * `data` est la vue repliée (foldLayout) : un groupe de voies de service (`kind: 'group'`) est une bande avec
     * un bouton qui le déplie (`ontoggle`). `onview` reçoit les PK visibles (lecture par portions des longues lignes)
     * et celui du milieu quand la vue a bougé (null sinon).
     * `axisMode` : `line` (PK de la ligne) ou `route` (livret de parcours : kilomètres parcourus, sens de marche).
     * `platforms` : bords de quai d'une gare (page des voies d'une gare, lib/stationTracks.js) : un quai central entre
     * deux voies voisines est dessiné entre elles, un bord seul le long de sa voie.
     *
     * @type {{
     *   data: any, axis: any, rows: Array<any>, zoom: number, mode?: 'schema'|'band',
     *   axisMode?: 'line'|'route', selectedPk?: number|null, printing?: boolean, label: string, helpId: string,
     *   lang: string, t: (key: string, options?: object) => string, onpick: (index: number) => void,
     *   onkey?: (event: KeyboardEvent) => void, groups?: Array<any>, ontoggle?: (group: any) => void,
     *   onview?: (from: number, to: number, center: number|null) => void, platforms?: Array<any>,
     * }}
     */
    let {
        data,
        axis,
        rows,
        axisMode = 'line',
        groups = [],
        ontoggle = () => {},
        onview = () => {},
        zoom,
        mode = 'schema',
        selectedPk = null,
        printing = false,
        label,
        helpId,
        lang,
        t,
        onpick,
        onkey,
        platforms = [],
    } = $props();

    /** Marges horizontales : à gauche, la place des en-têtes de la bande. */
    const PAD_L = 76;
    /** Taille (px) des noms des points d'exploitation et du texte d'interface du cadre (boutons, en-têtes). */
    const NAME_FS = 17;
    const UI_FS = 17;
    const PAD_R = 40;
    const NAME_ROW = 24;
    const STRIP_ROW = 30;
    const OBJECT_ROW = 40;

    /** @type {HTMLDivElement|undefined} */
    let frame = $state();
    let view = $state(0);
    let scroll = $state(0);
    /** Abscisse du curseur dans le dessin, ou null. */
    let cursor = $state(/** @type {number|null} */ (null));

    const schema = $derived(mode === 'schema');
    const X = (pk) => PAD_L + axis.x(pk) * zoom;
    // Au moins la largeur du cadre : les noms des bouts de ligne ont la place d'être écrits.
    const width = $derived(Math.max(Math.ceil(axis.width * zoom + PAD_L + PAD_R), view - 2));

    // Partie dessinée : la fenêtre et une fenêtre de chaque côté (tout à l'impression), recalculée par demi-fenêtre
    // de défilement seulement.
    const span = $derived(Math.max(view, 400));
    const lo = $derived(printing ? -Infinity : Math.floor(scroll / (span / 2)) * (span / 2) - span);
    const hi = $derived(printing ? Infinity : lo + 3.5 * span);
    const near = (x) => x >= lo && x <= hi;
    const nearSpan = (a, b) => b >= lo && a <= hi;
    const pkLo = $derived(lo === -Infinity ? -Infinity : axis.pk((lo - PAD_L) / zoom));
    const pkHi = $derived(hi === Infinity ? Infinity : axis.pk((hi - PAD_L) / zoom));

    // Voies de la partie dessinée ; les rangées sont celles des voies visibles (recalculées par demi-fenêtre de
    // défilement : la fenêtre est toujours dans [base, base + 1,5 fenêtre]), pour qu'une grande gare voisine
    // n'épaississe pas le dessin.
    const winTracks = $derived(
        data.tracks.filter((tr) => tr.pk_to_m >= pkLo && tr.pk_from_m <= pkHi),
    );
    const rowLo = $derived(
        printing
            ? -Infinity
            : axis.pk((Math.floor(scroll / (span / 2)) * (span / 2) - PAD_L) / zoom),
    );
    const rowHi = $derived(
        printing
            ? Infinity
            : axis.pk((Math.floor(scroll / (span / 2)) * (span / 2) + 1.5 * span - PAD_L) / zoom),
    );
    const lanes = $derived(
        laneRows(winTracks.filter((tr) => tr.pk_to_m >= rowLo && tr.pk_from_m <= rowHi)),
    );
    const winData = $derived({
        ...data,
        tracks: winTracks,
        switches: data.switches.filter((sw) => sw.pk_m >= pkLo && sw.pk_m <= pkHi),
    });

    // PK visibles (avec une demi-fenêtre de marge) pour la lecture par portions, et celui du milieu quand la vue a bougé
    // (viewCenterTracker).
    const centerOfView = viewCenterTracker();
    $effect(() => {
        if (!view) return;
        const a = axis.pk((scroll - view / 2 - PAD_L) / zoom);
        const b = axis.pk((scroll + 1.5 * view - PAD_L) / zoom);
        onview(
            a,
            b,
            centerOfView(scroll, view, () => axis.pk((scroll + view / 2 - PAD_L) / zoom)),
        );
    });

    // Noms des points d'exploitation, sur deux étages au plus au-dessus des zones : gares et haltes toujours,
    // bifurcations et évitements à partir de 150 %, faisceaux et autres points mineurs à partir de 300 % ; un nom
    // reste entier dans la partie visible du cadre (ramené au bord plutôt que coupé).
    const NAME_ZOOM = { 0: 0, 1: 1.5, 2: 3 };
    const nameRank = (op) =>
        op.kind === 'station' || op.kind === 'halt'
            ? 0
            : op.kind === 'junction' || op.kind === 'passing_loop' || op.kind === 'border'
              ? 1
              : 2;
    const names = $derived.by(() => {
        const left = printing ? 0 : scroll + PAD_L - 8;
        const right = printing ? width : scroll + (view || width);
        const ops = data.operational_points.filter(
            (op) =>
                (printing || zoom >= NAME_ZOOM[nameRank(op)]) &&
                X(op.pk_m) >= left - 200 &&
                X(op.pk_m) <= right + 200,
        );
        const placed = ops.map((op) => {
            const half = (textWidth(op.name, NAME_FS) + 4) / 2;
            const at = X(op.pk_m);
            return {
                op,
                at,
                half,
                x: Math.min(right - 8 - half, Math.max(left + 8 + half, at)),
            };
        });
        const rowsOf = stackLabels(
            placed.map((n) => ({ x: n.x, w: 2 * n.half + 10, rank: nameRank(n.op), order: n.at })),
            { maxRows: 2, gap: 12 },
        );
        return placed.map((n, i) => ({ ...n, row: rowsOf[i] }));
    });
    // Fond gris de chaque zone dilatée : il épouse les voies dessinées (de la première à la dernière voie locale, de
    // la rangée la plus haute à la plus basse), pas toute la hauteur du dessin.
    const zoneBoxes = $derived.by(() => {
        if (!schema) return [];
        const out = [];
        for (const z of axis.zones) {
            if (!nearSpan(X(z.from_m), X(z.to_m))) continue;
            const here = winTracks.filter(
                (tr) => tr.kind !== 'crossover' && tr.pk_to_m > z.from_m && tr.pk_from_m < z.to_m,
            );
            const local = here.filter(
                (tr) => tr.pk_from_m >= z.from_m - 1 && tr.pk_to_m <= z.to_m + 1,
            );
            const pks = z.ops
                .map((id) => data.operational_points.find((o) => o.id === id)?.pk_m)
                .filter((pk) => pk !== undefined);
            const from = Math.max(
                z.from_m,
                Math.min(...local.map((tr) => tr.pk_from_m), ...pks.map((pk) => pk - 60)) - 40,
            );
            const to = Math.min(
                z.to_m,
                Math.max(...local.map((tr) => tr.pk_to_m), ...pks.map((pk) => pk + 60)) + 40,
            );
            if (!(to > from)) continue;
            const ys = (here.length ? here : [{ lane: 0 }]).map((tr) => Y(tr.lane));
            out.push({
                key: `${z.from_m}`,
                x: X(from),
                w: Math.max(8, X(to) - X(from)),
                y0: Math.min(...ys) - ROW_H / 2 + 2,
                y1: Math.max(...ys) + ROW_H / 2 - 2,
            });
        }
        return out;
    });
    // Un bouton par zone, sous ses voies (les deux côtés d'une zone se replient ensemble).
    const openGroups = $derived.by(() => {
        if (!schema) return [];
        /** @type {Record<string, { g: any, count: number, from: number, to: number }>} */
        const byKey = {};
        for (const g of groups) {
            if (!g.expanded) continue;
            const o = byKey[g.key];
            byKey[g.key] = {
                g,
                count: (o?.count ?? 0) + g.count,
                from: Math.min(o?.from ?? Infinity, g.pk_from_m),
                to: Math.max(o?.to ?? -Infinity, g.pk_to_m),
            };
        }
        return Object.values(byKey)
            .map((o) => ({ ...o, x: X((o.from + o.to) / 2) }))
            .filter((o) => near(o.x));
    });
    const nameRows = $derived(Math.max(1, ...names.map((n) => n.row + 1)));
    const TOP = $derived(10 + nameRows * NAME_ROW + 12);
    const Y = (lane) => TOP + lanes.row(lane) * ROW_H + ROW_H / 2;
    const tracksH = $derived(schema ? lanes.count * ROW_H : OBJECT_ROW);
    // Sous les voies, la place des boutons « Replier » des zones dépliées.
    const FOLD_ROW = 40;
    const stripTop = $derived(TOP + tracksH + (openGroups.length ? FOLD_ROW : 14));
    const height = $derived(stripTop + 3 * STRIP_ROW + 8);

    const zoneStarts = $derived(
        axis.pieces.filter((p) => p.kind === 'zone' && p.first).map((p) => PAD_L + p.x0 * zoom),
    );

    // La voie parcourue est dessinée en dernier : rien ne la recouvre.
    const paths = $derived(
        schema
            ? trackPaths(winData, X, Y)
                  .filter((p) => p.track.kind !== 'group')
                  .sort(
                      (a, b) =>
                          Number(kindOf(a.track) === 'travelled') -
                          Number(kindOf(b.track) === 'travelled'),
                  )
            : [],
    );
    // Groupes repliés : une bande par groupe, et son bouton (HTML, au clavier) qui le déplie.
    const bands = $derived(
        schema
            ? winTracks
                  .filter((tr) => tr.kind === 'group')
                  .map((tr) => {
                      const g = groups.find((x) => x.id === tr.id);
                      const x0 = X(tr.pk_from_m);
                      const x1 = Math.max(X(tr.pk_to_m), x0 + 12);
                      // Bouton au milieu de la partie visible de la bande (jamais coupé par le bord du cadre).
                      const text = t(g?.service ? 'tracks.fold.count' : 'tracks.fold.all', {
                          count: g?.count ?? 0,
                      });
                      const half = textWidth(text, UI_FS) / 2 + 14;
                      const va = Math.max(x0, scroll + PAD_L);
                      const vb = Math.min(x1, scroll + view - 8);
                      const mid = (x0 + x1) / 2;
                      let at = mid;
                      if (vb - va >= 2 * half) at = Math.min(vb - half, Math.max(va + half, mid));
                      else if (vb > va)
                          at = Math.max(
                              scroll + half + 4,
                              Math.min(scroll + view - half - 4, (va + vb) / 2),
                          );
                      // Bande hors de la fenêtre (gare centrée à 320 px) : son bouton reste dehors, jamais à moitié
                      // coupé par le bord du cadre.
                      else if (!printing && view)
                          at =
                              x1 <= va
                                  ? Math.min(mid, scroll - half)
                                  : Math.max(mid, scroll + view + half);
                      // Le bouton est dans la rangée réservée au-delà de la bande (marge de la zone).
                      return {
                          tr,
                          g,
                          x0,
                          x1,
                          y: Y(tr.lane),
                          labelY: Y(tr.lane + (g?.side ?? 0)),
                          at,
                          text,
                      };
                  })
                  .filter((b) => b.g && nearSpan(b.x0, b.x1))
            : [],
    );
    // Groupes dépliés de la partie dessinée : un bouton pour les replier, au-dessus de leur zone.
    const tracksById = $derived(new Map(data.tracks.map((tr) => [tr.id, tr])));
    const laneOf = (trackId) => tracksById.get(trackId)?.lane ?? 0;

    // Quais (page des voies d'une gare) : seulement le long des voies dessinées de la partie visible.
    const yRow = (row) => TOP + row * ROW_H + ROW_H / 2;
    const platformMarks = $derived.by(() => {
        if (!schema || platforms.length === 0) return [];
        const rowOf = (id) => {
            const tr = tracksById.get(id);
            if (
                !tr ||
                tr.kind === 'group' ||
                tr.kind === 'crossover' ||
                !lanes.lanes.includes(tr.lane)
            )
                return null;
            return lanes.row(tr.lane);
        };
        const has = (lane) => lanes.lanes.includes(lane);
        const pivot =
            has(0) && has(1)
                ? (lanes.row(0) + lanes.row(1)) / 2
                : has(0)
                  ? lanes.row(0) + 0.5
                  : 0.5;
        return platformShapes(platforms, rowOf, pivot)
            .map((sh) => {
                const x0 = X(sh.from_m);
                const x1 = Math.max(X(sh.to_m), x0 + 4);
                const [y0, y1] =
                    sh.side === 'between'
                        ? [yRow(sh.row - 0.5) + 7, yRow(sh.row + 0.5) - 7]
                        : sh.side === 'above'
                          ? [yRow(sh.row) - 13, yRow(sh.row) - 5]
                          : [yRow(sh.row) + 5, yRow(sh.row) + 13];
                return { ...sh, x0, x1, y0, y1 };
            })
            .filter((m) => nearSpan(m.x0, m.x1));
    });

    /** Voies présentes à un PK : rangées haute et basse (passages à niveau, traversées). */
    function spanAt(pk) {
        // Voies dessinées seulement : un passage à niveau ne traverse pas la bande d'un groupe replié.
        const here = winTracks.filter(
            (tr) =>
                tr.kind !== 'crossover' &&
                tr.kind !== 'group' &&
                tr.kind !== 'group-label' &&
                tr.pk_from_m <= pk &&
                tr.pk_to_m >= pk,
        );
        if (here.length === 0) return [Y(0), Y(0)];
        const ys = here.map((tr) => Y(tr.lane));
        return [Math.min(...ys), Math.max(...ys)];
    }

    const kindOf = (tr) =>
        tr.kind === 'running' && tr.lane === 0
            ? 'travelled'
            : SERVICE_KINDS.has(tr.kind)
              ? 'service'
              : tr.kind === 'crossover' || tr.kind === 'derived'
                ? tr.kind
                : 'main';

    const switchMarks = $derived(
        schema
            ? data.switches
                  .map((sw) => {
                      const x = X(sw.pk_m);
                      const ya = Y(laneOf(sw.from_track));
                      const yb = Y(laneOf(sw.to_track));
                      // Vers un groupe replié : sur la voie dessinée, jamais à mi-chemin de la bande.
                      const toGroup = tracksById.get(sw.to_track)?.kind === 'group';
                      const cross = CROSSING_SWITCHES.has(sw.kind);
                      return { sw, x, y: cross && !toGroup ? (ya + yb) / 2 : ya, cross };
                  })
                  .filter((m) => near(m.x) && tracksById.get(m.sw.from_track)?.kind !== 'group')
            : [],
    );
    // En pleine ligne comprimée, une seule marque par portion (par voie et par sens pour les signaux) : les objets
    // de quelques kilomètres tiendraient sinon en quelques pixels.
    const pieceOf = (m) => plainPieceAt(axis.pieces, m.pk);
    /** Regroupe des marques par clé (même rangée, même sens), puis par portion comprimée. */
    function clustered(marks, keyOf) {
        /** @type {Record<string, any[]>} */
        const by = {};
        for (const m of marks.sort((a, b) => a.x - b.x)) (by[keyOf(m)] ??= []).push(m);
        return Object.values(by).flatMap((list) => clusterMarks(list, pieceOf));
    }
    const signalMarks = $derived(
        schema
            ? clustered(
                  data.signals
                      .map((s) => {
                          const dir = s.direction === 'down' ? -1 : 1;
                          return { s, pk: s.pk_m, x: X(s.pk_m), y: Y(laneOf(s.track)), dir };
                      })
                      .filter((m) => near(m.x)),
                  (m) => `${m.y}:${m.dir}`,
              )
            : [],
    );
    // Traversées piétonnes des quais (zones dilatées des gares) : dans le tableau seulement, elles cacheraient les voies.
    const inZone = (pk) => axis.zones.some((z) => pk >= z.from_m && pk <= z.to_m);
    const crossingMarks = $derived(
        clustered(
            data.crossings
                .filter((c) => !(c.use === 'pedestrian' && inZone(c.pk_m)))
                .map((c) => {
                    const [y0, y1] = schema ? spanAt(c.pk_m) : [TOP + 20, TOP + 20];
                    // Traversée de voies : sur la voie qu'elle croise quand on la connaît.
                    const on = (c.tracks ?? []).find((id) => tracksById.has(id));
                    const yc = schema && on ? Y(laneOf(on)) : y0;
                    return {
                        c,
                        pk: c.pk_m,
                        x: X(c.pk_m),
                        y0: c.kind === 'level_crossing' ? y0 : yc,
                        y1,
                    };
                })
                .filter((m) => near(m.x)),
            (m) => `${m.c.kind}:${m.c.use === 'pedestrian'}`,
        ),
    );
    const bufferMarks = $derived(
        schema
            ? data.buffer_stops
                  .map((b) => ({ b, x: X(b.pk_m), y: Y(laneOf(b.track)) }))
                  .filter((m) => near(m.x))
            : [],
    );

    // Étiquettes des voies, signaux et aiguillages : sans chevauchement, par priorité.
    const labels = $derived.by(() => {
        if (!schema) return [];
        const out = [];
        // Voie en plusieurs pièces (voie du RINF coupée à chaque section, voie d'OpenStreetMap à chaque aiguillage) :
        // son numéro une seule fois, sur la plus longue pièce d'une voie de gare, au seul début d'une voie de ligne.
        const pieces = repeatedPieces(winTracks);
        const fits = (p) => p.label && p.label.b - p.label.a >= textWidth(p.track.ref, 14) + 14;
        // Première pièce de chaque voie où son numéro tient : elle l'écrit à son début, même si elle en prolonge une autre.
        const first = new Map(
            [...paths]
                .filter((p) => pieces.run.has(p.track.id) && fits(p))
                // La dernière écrite l'emporte : la plus petite PK, et à PK égal la tête de la suite (deux voies V1 du RINF
                // superposées à Paris-Nord ne prennent pas chacune un numéro).
                .sort(
                    (x, y) =>
                        y.track.pk_from_m - x.track.pk_from_m ||
                        Number(pieces.continued.has(y.track.id)) -
                            Number(pieces.continued.has(x.track.id)),
                )
                .map((p) => [pieces.run.get(p.track.id), p.track.id]),
        );
        for (const p of paths) {
            if (!p.label || !nearSpan(p.label.a, p.label.b) || pieces.hidden.has(p.track.id))
                continue;
            const w = textWidth(p.track.ref, 14) + 4;
            if (!fits(p)) continue;
            const goesOn =
                pieces.continued.has(p.track.id) &&
                first.get(pieces.run.get(p.track.id)) !== p.track.id;
            for (const spot of refSpots(p, zoneStarts)) {
                if (!near(spot.x)) continue;
                if (goesOn && spot.x === p.label.a + 8) continue;
                const x0 = spot.anchor === 'middle' ? spot.x - w / 2 : spot.x;
                const over = (a, b, y0, y1) =>
                    platformMarks.filter((m) => m.x0 < b && a < m.x1 && m.y0 < y1 && y0 < m.y1);
                const covering = over(x0, x0 + w, p.label.y - 19, p.label.y - 4);
                const group = `ref|${p.track.id}|${spot.x}`;
                if (covering.length === 0) {
                    out.push({
                        text: p.track.ref,
                        cls: 'ref',
                        x: spot.x,
                        y: p.label.y - 7,
                        anchor: spot.anchor,
                        box: {
                            x0,
                            x1: x0 + w,
                            y0: p.label.y - 19,
                            y1: p.label.y - 4,
                            rank: 0,
                            group,
                        },
                    });
                    // Début d'une voie pris (aiguillage au bord de la fenêtre à Brétigny) : un peu plus loin.
                    const xs = x0 + w + 30;
                    if (spot.anchor === 'start' && xs + w <= p.label.b - 4) {
                        out.push({
                            text: p.track.ref,
                            cls: 'ref',
                            x: xs,
                            y: p.label.y - 7,
                            anchor: 'start',
                            box: {
                                x0: xs,
                                x1: xs + w,
                                y0: p.label.y - 19,
                                y1: p.label.y - 4,
                                rank: 0.4,
                                group,
                            },
                        });
                    }
                    continue;
                }
                // Un quai au-dessus de la voie (voie du bas d'un quai central) : le numéro passe sous sa voie, un peu à
                // droite (le début de la voie suivante garde la place de son propre numéro), sinon juste après le quai,
                // une seule fois, après les numéros des autres voies.
                const xb = x0 + textWidth('00', 14) + 18;
                if (
                    xb + w <= p.label.b - 4 &&
                    over(xb, xb + w, p.label.y + 4, p.label.y + 19).length === 0
                ) {
                    out.push({
                        text: p.track.ref,
                        cls: 'ref',
                        x: xb,
                        y: p.label.y + 17,
                        anchor: 'start',
                        box: {
                            x0: xb,
                            x1: xb + w,
                            y0: p.label.y + 5,
                            y1: p.label.y + 20,
                            rank: 0.5,
                            group,
                        },
                    });
                }
                const xa = Math.max(...covering.map((m) => m.x1)) + 6;
                if (xa + w <= p.label.b - 4) {
                    out.push({
                        text: p.track.ref,
                        cls: 'ref',
                        x: xa,
                        y: p.label.y - 7,
                        anchor: 'start',
                        box: {
                            x0: xa,
                            x1: xa + w,
                            y0: p.label.y - 19,
                            y1: p.label.y - 4,
                            rank: 0.6,
                            group,
                        },
                    });
                }
            }
        }
        for (const m of signalMarks) {
            if (!m.s.ref || m.count > 1) continue;
            const w = textWidth(m.s.ref, 14);
            const up = m.dir > 0;
            const x = up ? m.x + 10 : m.x - 10;
            const y = up ? m.y - 9 : m.y + 21;
            const x0 = up ? x : x - w;
            out.push({
                text: m.s.ref,
                cls: 'sig-ref',
                x,
                y,
                anchor: up ? 'start' : 'end',
                box: { x0, x1: x0 + w, y0: y - 12, y1: y + 2, rank: 1 },
            });
        }
        for (const m of switchMarks) {
            if (!m.sw.ref) continue;
            const w = textWidth(m.sw.ref, 14);
            out.push({
                text: m.sw.ref,
                cls: 'sw-ref',
                x: m.x,
                y: m.y + 21,
                anchor: 'middle',
                box: { x0: m.x - w / 2, x1: m.x + w / 2, y0: m.y + 9, y1: m.y + 23, rank: 2 },
            });
        }
        // Marque regroupée en pleine ligne : le nombre d'objets qu'elle porte.
        for (const m of [...signalMarks, ...crossingMarks]) {
            if (m.count < 2) continue;
            const text = `×${formatNumber(m.count, lang)}`;
            const w = textWidth(text, 14);
            const y = (m.y0 ?? m.y) - 14;
            out.push({
                text,
                cls: 'count',
                x: m.x + 6,
                y,
                anchor: 'start',
                box: { x0: m.x + 6, x1: m.x + 6 + w, y0: y - 12, y1: y + 2, rank: 1 },
            });
        }
        // Les symboles eux-mêmes occupent leur place : une étiquette ne les recouvre pas.
        const symbols = [
            ...switchMarks.map((m) => ({
                x0: m.x - 6,
                x1: m.x + 6,
                y0: m.y - 6,
                y1: m.y + 6,
                rank: -1,
            })),
            ...signalMarks.map((m) => ({
                x0: m.x - 2,
                x1: m.x + 2 + 8 * m.dir,
                y0: m.dir > 0 ? m.y - 20 : m.y,
                y1: m.dir > 0 ? m.y : m.y + 20,
                rank: -1,
            })),
        ];
        const shown = placeBoxes([...symbols, ...out.map((l) => l.box)]);
        return out.filter((_, i) => shown[symbols.length + i]);
    });

    // Bande : graduation des PK (gares d'abord), vitesses, déclivités.
    // Chaque candidat a son trait ; seuls ceux dont le texte tient à 24 px de ses voisins sont écrits (les chiffres
    // tabulaires font environ 0,62 em).
    const pkTicks = $derived.by(() => {
        const cands = data.operational_points.map((op) => ({ pk: op.pk_m, rank: 0 }));
        for (const p of axis.pieces) {
            if (p.kind === 'plain' || p.first) {
                cands.push({ pk: p.from_m, rank: p.kind === 'zone' ? 1 : 2 });
            }
        }
        cands.push({ pk: data.pk.end_m, rank: 1 });
        // Kilomètres ronds, là où la place le permet.
        const step = zoom >= 1 ? 1000 : zoom >= 0.4 ? 5000 : 10000;
        for (let k = Math.ceil(data.pk.start_m / step) * step; k <= data.pk.end_m; k += step) {
            cands.push({ pk: k, rank: 3 });
        }
        // Un trait par abscisse (au pixel près), le plus important gardé.
        /** @type {Record<number, any>} */
        const byX = {};
        for (const c of cands) {
            const x = X(c.pk);
            if (!near(x)) continue;
            const k = Math.round(x);
            if (!byX[k] || byX[k].rank > c.rank) byX[k] = { ...c, x };
        }
        // Texte centré sur son trait, sans passer sous les en-têtes de la bande (64 px à gauche).
        const items = Object.values(byX).map((c) => {
            const text = axisLabel(c.pk, axisMode, lang);
            const half = (text.length * 14 * 0.62) / 2;
            return {
                ...c,
                tx: Math.min(width - 4 - half, Math.max(68 + half, c.x)),
                half,
                text,
            };
        });
        const written = tickLabels(items, 24);
        return items.map((c, i) => ({ ...c, written: written[i] }));
    });

    // Bande des vitesses et des déclivités : portions sans chevauchement, les trop courtes regroupées en une case
    // marquée (moyenne pondérée des déclivités, plus petite et plus grande vitesse).
    const permille = (v) => formatPermille(v, lang);
    // Bande sans chevauchement (voies principales d'abord) : cases, curseur.
    const speedBand = $derived(flattenRanges(data.strip.speeds));
    const gradientBand = $derived(flattenRanges(data.strip.gradients));
    // Largeur lisible d'une case : de quoi écrire « 140 » ou « ≈ -12,5 ».
    function cells(ranges, text, minPx, value) {
        // Portions voisines de même valeur : une seule case.
        const joined = joinRanges(ranges, (a, b) => value(a) === value(b));
        return bandCells(joined, X, { minPx })
            .filter((c) => nearSpan(c.x0, c.x1))
            .map((c) => {
                const label = text(c);
                return { ...c, label, fits: c.x1 - c.x0 >= textWidth(label, 15) + 10 };
            });
    }
    const speedCells = $derived(
        cells(
            speedBand,
            (c) => {
                const v = c.items.map((r) => r.kmh);
                const [lo, hi] = [Math.min(...v), Math.max(...v)];
                return lo === hi
                    ? formatNumber(lo, lang)
                    : `${formatNumber(lo, lang)}-${formatNumber(hi, lang)}`;
            },
            44,
            (r) => r.kmh,
        ),
    );
    const gradientCells = $derived(
        cells(
            gradientBand,
            (c) =>
                c.merged
                    ? `≈${NBSP}${permille(meanPermille(c.items))}`
                    : permille(c.items[0].permille),
            64,
            (r) => r.permille,
        ),
    );

    /** Texte d'une case : au milieu de sa partie visible (une longue case garde sa valeur à l'écran). */
    function cellX(c) {
        const half = textWidth(c.label, 15) / 2 + 6;
        const a = Math.max(c.x0, printing ? c.x0 : scroll + PAD_L);
        const b = Math.min(c.x1, printing ? c.x1 : scroll + (view || width));
        return b - a < 2 * half ? (c.x0 + c.x1) / 2 : (a + b) / 2;
    }

    // Curseur : PK, vitesse et déclivité du point survolé.
    const cursorPk = $derived(cursor === null ? null : axis.pk((cursor - PAD_L) / zoom));
    const reading = $derived.by(() => {
        if (cursorPk === null) return null;
        const v = valueAt(speedBand, cursorPk);
        const g = valueAt(gradientBand, cursorPk);
        return {
            pk:
                axisMode === 'route'
                    ? t('tracks.route.cursor', { km: axisLabel(cursorPk, axisMode, lang) })
                    : t('tracks.cursor_pk', { pk: pkLabel(Math.round(cursorPk)) }),
            speed: v ? `${formatNumber(v.kmh, lang)} km/h` : null,
            gradient: g ? `${permille(g.permille)} ‰` : null,
        };
    });

    function pointerX(event) {
        const box = /** @type {SVGElement} */ (event.currentTarget).getBoundingClientRect();
        return event.clientX - box.left;
    }
    function onmove(event) {
        if (event.pointerType === 'touch') return;
        cursor = Math.min(width - PAD_R, Math.max(PAD_L, pointerX(event)));
    }
    function onclick(event) {
        const i = rowAtX(axis, zoom, PAD_L, rows, pointerX(event));
        if (i >= 0) onpick(i);
    }

    /** Centre la fenêtre sur un PK (synchronisation depuis le tableau). */
    export function centerOn(pk, smooth = true) {
        if (!frame) return;
        const left = centerScroll(X(pk), frame.clientWidth, frame.scrollWidth);
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        frame.scrollTo({ left, behavior: smooth && !reduce ? 'smooth' : 'auto' });
    }

    /** Amène un PK au bord gauche de la fenêtre, juste après les en-têtes de la bande (ouverture de la page). */
    export function startAt(pk) {
        if (!frame) return;
        frame.scrollLeft = Math.max(0, X(pk) - PAD_L - 40);
    }

    /** PK au milieu de la fenêtre (pour garder le même endroit en changeant le zoom). */
    export function centerPk() {
        if (!frame) return data.pk.start_m;
        return axis.pk((frame.scrollLeft + frame.clientWidth / 2 - PAD_L) / zoom);
    }

    /** Largeur utile de la fenêtre, pour « Ajuster ». */
    export function viewWidth() {
        return frame?.clientWidth ?? 0;
    }
</script>

<div class={['diagram', !schema && 'band']}>
    <!-- Région défilante atteinte au clavier (flèches : objet précédent ou suivant, + et - : zoom) ; le dessin
         est décrit par le tableau de parcours. -->
    <!-- svelte-ignore a11y_no_noninteractive_tabindex, a11y_no_noninteractive_element_interactions -->
    <div
        class="frame"
        bind:this={frame}
        bind:clientWidth={view}
        onscroll={() => (scroll = frame?.scrollLeft ?? 0)}
        role="region"
        aria-label={label}
        aria-describedby={helpId}
        tabindex="0"
        onkeydown={onkey}
    >
        <svg
            {width}
            {height}
            viewBox="0 0 {width} {height}"
            aria-hidden="true"
            onpointermove={onmove}
            onpointerleave={() => (cursor = null)}
            {onclick}
        >
            <!-- Zones des points d'exploitation et leurs noms. -->
            {#each zoneBoxes as z (z.key)}
                <rect class="zone" x={z.x} y={z.y0} width={z.w} height={z.y1 - z.y0} rx="8" />
            {/each}
            {#each names as n (n.op.id)}
                {#if n.row >= 0 && near(n.x)}
                    <!-- Le trait descend du nom à son point, quand le nom n'a pas été ramené loin de lui. -->
                    {#if Math.abs(n.at - n.x) <= n.half}
                        <line
                            class="name-tick"
                            x1={n.at}
                            x2={n.at}
                            y1={10 + (n.row + 1) * NAME_ROW + 2}
                            y2={TOP - 6}
                        />
                    {/if}
                    <text
                        class={['op-name', n.op.kind, n.op.precision === 'approximate' && 'approx']}
                        x={n.x}
                        y={10 + (n.row + 1) * NAME_ROW - 4}
                        text-anchor="middle">{n.op.name}</text
                    >
                {/if}
            {/each}

            {#if schema}
                <!-- Quais, sous les voies. -->
                {#each platformMarks as m (m.key)}
                    <rect
                        class={['platform', m.approx && 'approx']}
                        x={m.x0}
                        y={m.y0}
                        width={m.x1 - m.x0}
                        height={m.y1 - m.y0}
                        rx="3"
                    />
                {/each}
                <!-- Voies : la voie parcourue en orange, les voies de service en pointillé. -->
                {#each paths as p (p.track.id)}
                    {#if nearSpan(p.points[0][0], p.points.at(-1)[0])}
                        <path
                            class={[
                                'track',
                                kindOf(p.track),
                                p.track.precision === 'approximate' && 'approx',
                            ]}
                            d={pathD(p.points)}
                        />
                    {/if}
                {/each}
                {#each bands as b (b.tr.id)}
                    <rect
                        class="group"
                        x={b.x0}
                        y={b.y - BAND_H / 2}
                        width={b.x1 - b.x0}
                        height={BAND_H}
                        rx={BAND_H / 2}
                    />
                {/each}
                {#each bufferMarks as m (m.b.id ?? m.b.pk_m)}
                    <line class="buffer" x1={m.x} x2={m.x} y1={m.y - 8} y2={m.y + 8} />
                {/each}
                {#each switchMarks as m (m.sw.id)}
                    {#if m.cross}
                        <path
                            class="cross"
                            d="M{m.x - 6} {m.y - 6} L{m.x + 6} {m.y + 6} M{m.x - 6} {m.y +
                                6} L{m.x + 6} {m.y - 6}"
                        />
                    {:else}
                        <circle class="switch" cx={m.x} cy={m.y} r="4.5" />
                    {/if}
                {/each}
                {#each signalMarks as m (m.s.id)}
                    <g class="signal">
                        <line x1={m.x} x2={m.x} y1={m.y} y2={m.y - 13 * m.dir} />
                        <circle cx={m.x + 4 * m.dir} cy={m.y - 13 * m.dir} r="3.8" />
                    </g>
                {/each}
            {/if}

            {#each crossingMarks as m (m.c.id)}
                {#if m.c.kind === 'level_crossing'}
                    <g class={['pn', m.c.use === 'pedestrian' && 'foot']}>
                        <line x1={m.x - 3} x2={m.x - 3} y1={m.y0 - 10} y2={m.y1 + 10} />
                        <line x1={m.x + 3} x2={m.x + 3} y1={m.y0 - 10} y2={m.y1 + 10} />
                    </g>
                {:else}
                    <path
                        class="cross"
                        d="M{m.x - 7} {m.y0 - 7} L{m.x + 7} {m.y0 + 7} M{m.x - 7} {m.y0 + 7} L{m.x +
                            7} {m.y0 - 7}"
                    />
                {/if}
            {/each}

            {#if !schema}
                <!-- Bande seule : une rangée d'objets (signaux, aiguillages) le long de l'axe. -->
                <line class="axis" x1={PAD_L} x2={width - PAD_R} y1={TOP + 20} y2={TOP + 20} />
                {#each data.signals as s (s.id)}
                    {#if near(X(s.pk_m))}
                        {@const dir = s.direction === 'down' ? -1 : 1}
                        <g class="signal">
                            <line
                                x1={X(s.pk_m)}
                                x2={X(s.pk_m)}
                                y1={TOP + 20}
                                y2={TOP + 20 - 13 * dir}
                            />
                            <circle cx={X(s.pk_m) + 4 * dir} cy={TOP + 20 - 13 * dir} r="3.8" />
                        </g>
                    {/if}
                {/each}
                {#each data.switches as sw (sw.id)}
                    {#if near(X(sw.pk_m))}
                        <circle class="switch" cx={X(sw.pk_m)} cy={TOP + 20} r="4" />
                    {/if}
                {/each}
            {/if}

            {#each labels as l, i (i)}
                <text class={l.cls} x={l.x} y={l.y} text-anchor={l.anchor}>{l.text}</text>
            {/each}

            <!-- Bande : PK, vitesses, déclivités. -->
            <g class="strip">
                <line class="rule" x1={PAD_L} x2={width - PAD_R} y1={stripTop} y2={stripTop} />
                {#each pkTicks as tk (tk.pk)}
                    <line
                        class="tick"
                        x1={tk.x}
                        x2={tk.x}
                        y1={stripTop}
                        y2={stripTop + (tk.written ? 6 : 4)}
                    />
                    {#if tk.written}
                        <text class="pk" x={tk.tx} y={stripTop + 21} text-anchor="middle"
                            >{tk.text}</text
                        >
                    {/if}
                {/each}
                {#each [{ list: speedCells, row: 1, cls: 'speed' }, { list: gradientCells, row: 2, cls: 'gradient' }] as band (band.cls)}
                    {#each band.list as c, i (i)}
                        <rect
                            class={['cell', band.cls, i % 2 && 'alt', c.merged && 'merged']}
                            x={c.x0}
                            y={stripTop + band.row * STRIP_ROW + 2}
                            width={Math.max(1, c.x1 - c.x0)}
                            height={STRIP_ROW - 4}
                            rx="4"
                        />
                        {#if c.fits}
                            <text
                                class="val"
                                x={cellX(c)}
                                y={stripTop + band.row * STRIP_ROW + 20}
                                text-anchor="middle">{c.label}</text
                            >
                        {/if}
                    {/each}
                {/each}
            </g>

            {#if selectedPk !== null}
                <line class="selected" x1={X(selectedPk)} x2={X(selectedPk)} y1="4" y2={height} />
            {/if}
            {#if cursor !== null}
                <line class="cursor" x1={cursor} x2={cursor} y1="0" y2={height} />
            {/if}
        </svg>
        {#each bands as b (b.tr.id)}
            <button
                type="button"
                class="fold"
                style:left="{b.at}px"
                style:top="{b.labelY}px"
                aria-expanded="false"
                aria-label={t('tracks.fold.expand', {
                    count: b.g.count,
                    name: b.g.op?.name ?? axisLabel(b.g.pk_from_m, axisMode, lang),
                })}
                onclick={() => ontoggle(b.g)}>{b.text}</button
            >
        {/each}
        {#each openGroups as o (o.g.key)}
            <button
                type="button"
                class="fold open"
                style:left="{o.x}px"
                style:top="{TOP + tracksH + FOLD_ROW / 2 - 2}px"
                aria-expanded="true"
                aria-label={t('tracks.fold.collapse', {
                    count: o.count,
                    name: o.g.op?.name ?? axisLabel(o.from, axisMode, lang),
                })}
                onclick={() => ontoggle(o.g)}>{t('tracks.fold.fold')}</button
            >
        {/each}
        {#if reading && cursor !== null}
            <div
                class={['reading', cursor > scroll + view - 200 && 'left']}
                style:--at="{cursor}px"
                aria-hidden="true"
            >
                <strong>{reading.pk}</strong>
                {#if reading.speed}<span>{reading.speed}</span>{/if}
                {#if reading.gradient}<span>{reading.gradient}</span>{/if}
            </div>
        {/if}
    </div>
    <!-- En-têtes de la bande, fixes pendant le défilement. -->
    <div class="heads" aria-hidden="true" style:--strip-top="{stripTop}px">
        <span style:--row="0"
            >{t(axisMode === 'route' ? 'tracks.route.strip' : 'tracks.strip.pk')}</span
        >
        <span style:--row="1">{t('tracks.strip.speed')}</span>
        <span style:--row="2">{t('tracks.strip.gradient')}</span>
        {#if !schema}<span class="objects" style:--top="{TOP + 8}px"
                >{t('tracks.strip.objects')}</span
            >{/if}
    </div>
</div>

<style>
    .diagram {
        position: relative;
        min-width: 0;
    }
    .frame {
        position: relative;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        scrollbar-width: thin;
    }
    .frame:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    svg {
        display: block;
        cursor: crosshair;
        font-family: var(--vff-font-heading);
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .zone {
        fill: var(--tl-zone);
        stroke: var(--vff-border);
    }
    .name-tick {
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .op-name {
        font-size: 17px;
        font-weight: 600;
        fill: var(--vff-text);
        paint-order: stroke;
        stroke: var(--vff-bg-elevated);
        stroke-width: 4px;
    }
    .op-name.halt,
    .op-name.junction,
    .op-name.passing_loop {
        font-weight: 500;
    }
    .op-name.approx {
        font-style: italic;
    }
    .track {
        fill: none;
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    .track.travelled {
        stroke: var(--vff-accent-strong);
        stroke-width: 4;
    }
    .track.service {
        stroke: var(--vff-text-muted);
        stroke-width: 2;
        stroke-dasharray: 6 4;
    }
    .track.crossover {
        stroke-width: 2;
    }
    .track.approx {
        stroke-dasharray: 2 5;
    }
    /* Raccord déduit par VFF (aucun aiguillage connu) : court, fin et discret. */
    .track.derived {
        stroke: var(--vff-text-muted);
        stroke-width: 1.5;
        stroke-dasharray: 3 3;
    }
    .group {
        fill: var(--tl-zone);
        stroke: var(--vff-text-muted);
        stroke-width: 1.5;
        stroke-dasharray: 6 4;
    }
    .fold {
        position: absolute;
        z-index: 1;
        min-height: 28px;
        padding: 0 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: 17px;
        font-weight: 600;
        line-height: 1.5;
        white-space: nowrap;
        cursor: pointer;
        transform: translate(-50%, -50%);
    }
    .fold:hover {
        border-color: var(--vff-accent-strong);
    }
    .fold:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 1px;
    }
    .fold.open {
        color: var(--vff-text-muted);
        font-weight: 500;
    }
    .switch {
        fill: var(--vff-bg-elevated);
        stroke: var(--vff-text);
        stroke-width: 1.8;
    }
    .cross {
        fill: none;
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    .buffer {
        stroke: var(--vff-text);
        stroke-width: 4;
    }
    .signal line {
        stroke: var(--vff-info);
        stroke-width: 2;
    }
    .signal circle {
        fill: var(--vff-info);
    }
    .pn line {
        stroke: var(--vff-text-muted);
        stroke-width: 1.8;
    }
    /* Traversée piétonne (souvent sur les quais d'une gare) : plus discrète qu'un passage à niveau routier. */
    .pn.foot line {
        stroke-width: 1;
        stroke-dasharray: 2 3;
    }
    .axis {
        stroke: var(--vff-text);
        stroke-width: 2.4;
    }
    /* Quai : un aplat discret sous les voies ; placé à peu près (sans PK déclaré), en pointillé. */
    .platform {
        fill: var(--tl-platform);
        stroke: var(--tl-platform-edge);
        stroke-width: 1;
    }
    .platform.approx {
        stroke-dasharray: 3 3;
    }
    .ref,
    .sig-ref,
    .sw-ref,
    .count {
        font-size: 14px;
        paint-order: stroke;
        stroke: var(--vff-bg-elevated);
        stroke-width: 3px;
        font-variant-numeric: tabular-nums;
    }
    .ref {
        font-weight: 700;
        fill: var(--vff-text);
    }
    .sig-ref {
        fill: var(--vff-info);
    }
    .sw-ref,
    .count {
        fill: var(--vff-text-muted);
    }
    .rule,
    .tick {
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .pk {
        font-size: 14px;
        fill: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .cell {
        fill: var(--vff-bg-subtle);
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .cell.alt {
        fill: var(--vff-bg-muted);
    }
    /* Case qui regroupe des portions trop courtes pour l'échelle. */
    .cell.merged {
        stroke-dasharray: 3 3;
    }
    .val {
        font-size: 15px;
        fill: var(--vff-text);
        font-variant-numeric: tabular-nums;
    }
    .selected {
        stroke: var(--vff-accent-strong);
        stroke-width: 2;
        stroke-dasharray: 5 4;
    }
    .cursor {
        stroke: var(--vff-text-muted);
        stroke-width: 1;
        pointer-events: none;
    }
    .reading {
        position: absolute;
        top: 8px;
        left: calc(var(--at) + 12px);
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 6px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        pointer-events: none;
    }
    .reading.left {
        transform: translateX(calc(-100% - 24px));
    }
    .heads {
        position: absolute;
        inset: 1px auto 1px 1px;
        width: 64px;
        border-radius: var(--vff-radius-md) 0 0 var(--vff-radius-md);
        background: linear-gradient(
            to right,
            var(--vff-bg-elevated) 80%,
            color-mix(in srgb, var(--vff-bg-elevated) 0%, transparent)
        );
        pointer-events: none;
    }
    .heads span {
        position: absolute;
        left: 10px;
        top: calc(var(--strip-top) + var(--row) * 30px + 4px);
        font-family: var(--vff-font-heading);
        font-size: 17px;
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .heads .objects {
        top: var(--top);
    }
    @media print {
        .frame {
            overflow: visible;
            border: 0;
        }
        .frame svg {
            width: 100%;
            height: auto;
        }
        .heads,
        .reading,
        .cursor,
        .fold {
            display: none;
        }
    }
</style>
