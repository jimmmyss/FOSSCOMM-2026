/* FOSSCOMM 2026 — the pixel-sharp cursor spotlight.
 *
 * One goo on the page — a trail, see THE TRAIL — and one ground under it: the
 * landing page's paper with the page background's dot field (assets/wave-bg.js)
 * under it, and the goo drawn over that field and under all of the content,
 * across every seam as one shape. The sections with a paper ground of their
 * own (Manifesto, Schedule, Venue, Get Involved, FAQ) are white panels in
 * front of all three: the trail runs on behind them.
 *
 * ── THE SCHEME ────────────────────────────────────────────────────────────
 * data-fc-spotlight="paper" — every section the field shows through: the
 *     hero's paper panel, Speakers, Communities, News, Sponsors, the footer.
 *     The page's paper and dots outside; inside the goo, BLUE, with the field's
 *     dots in it, and the type in the blue colours: white, the small print
 *     #8CA3FF, the highlight --fc-blue-hi. Outline #99D0DE at 50/25/10%.
 * data-fc-spotlight="blue" — kept for a panel marked blue (a blue dot ground
 *     with a WHITE goo, the type inside in the paper colours; outline #FFEE87).
 *     Nothing on the landing page is any more.
 *
 * ── LAYERS, bottom to top ─────────────────────────────────────────────────
 *   field     the page background's canvas (wave-bg.js)       body, z -1
 *   goo       ONE canvas drawn by the GPU over the lit sections' extent, a
 *             window and a half of it round the view (see gooLayer) — or, with
 *             no GPU, a canvas in each section for its own share   z -1
 *   content   the sections' own markup, plus the inverted copies
 *             clipped to the core                                 z auto / 1
 *
 * ── LIGHT ─────────────────────────────────────────────────────────────────
 * Nothing is drawn that has not changed. The ground costs nothing a frame.
 * The goo is a picture one pixel a cell, uploaded only when it changes; the
 * dots inside it are redrawn only where they were and are, and not at all
 * while the goo holds still. Nothing reads the layout inside a frame (see
 * MEASURING). A copy out of reach is not rendered at all. The loop sleeps
 * when there is no pointer and nothing left to settle, and drops to every
 * other frame on a machine that cannot keep up. On a screen with no hover
 * (touch) there is no trail and no copies at all.
 *
 * ── THE COPIES ────────────────────────────────────────────────────────────
 * Each piece of type that inverts is cloned, the clone laid exactly over it
 * and masked to the disc. Three things keep a clone honest:
 *
 *   POSITION — the rows are belts that slide, so a clone's offset is stored
 *     against its ANCHOR (the belt rail or sticky bar it rides on, else the
 *     section), and only the anchors are measured each frame.
 *   CONTENT — a button's label scrambles, a belt clones a card: a
 *     MutationObserver rebuilds the clone of whatever changed.
 *   STATE — the pointer never touches a clone, so :hover never reaches one.
 *     The element under the pointer is mapped to its twin in the clone, which
 *     gets [data-fc-hov], and every :hover rule on the page is re-issued once
 *     with [data-fc-hov] in its place. Animations in a clone are run on the original's
 *     clock, so a marquee or a wave never slips at the rim.
 *
 * ── THE HIGHLIGHT ON THE BLUE ─────────────────────────────────────────────
 * The belt lights a whole card (.is-hot) when the pointer is on any part of
 * it. Here the parts light themselves: a name only while the pointer is on its
 * LETTERS, a portrait's ring only while it is on the portrait.
 *
 * ── QUANTISED TO THE DOT ──────────────────────────────────────────────────
 * Every edge — core and rings — steps one DOT (6px) at a time, and the centre
 * snaps to that lattice in document space, so every section agrees on it. Dots
 * straddling the rim split pixel by pixel.
 */
(function () {
    'use strict';

    var PITCH = 40, DOT = 6, Q = DOT;
    var BLUE_GROUND = '#0033FF', BLUE_DOT = '#3860FF';
    var WHITE_GROUND = '#FAFAF7', WHITE_DOT = '#E2E2DF';
    var RADIUS = 80;                           // the core at rest, CSS px — the same everywhere (was 104)
    var RING_STEPS = 2;                        // each halo ring = 2 lattice steps
    var HALO = {
        blue:  ['rgba(255,238,135,0.50)',      // #FFEE87, round the white disc on the blue
                'rgba(255,238,135,0.25)',
                'rgba(255,238,135,0.10)'],
        paper: ['rgba(153,208,222,0.50)',      // #99D0DE, round the blue disc on the white
                'rgba(153,208,222,0.25)',
                'rgba(153,208,222,0.10)']
    };
    var HALO_R = RADIUS + 3 * RING_STEPS * Q;

    /* What inverts inside the disc. A target inside another target is carried
     * by the outer one's copy. */
    var TARGETS = [
        '.fc-spk-name', '.fc-section-heading', '.fc-spk-roles', '.fc-label', '.fc-copy',
        '.fc-foot-links', '.fc-spk-online', '.fc-spk-shot', '.fc-tier-title',
        '.fc-hero-wordmark', '.fc-band', '.fc-hero-cta', '.fc-btn', '.fc-tier-rule',
        // the belts' progress dots: ink on the paper, the small print in the goo
        '.fc-spk-dots', '.fc-com-dots',
        // the News grid whole, so its 1px rules turn with the cards
        'ol:has(> .fc-news)', '.fc-news',
        // the sponsors' progress dots, as the speakers'
        '.fc-belt-dots',
        /* a community's or a sponsor's logo box, where it has a second logo for
           inside the highlight (see swapIn) — one without stays as it is */
        '.fc-com-logo-box.fc-logo-in', '.fc-belt-box.fc-logo-in'
    ].join(', ');
    /* Copies that cover the WHOLE box, not just the type: things with a fill or
     * a strip of their own. (Anything with an opaque background is added to
     * these automatically.) And a logo's box: its size is the box's own (a
     * sponsor's from the logo's shape), not its contents' — the second logo
     * need not be the same shape as the first. */
    var BOXES = '.fc-news, .fc-band, .fc-tier-rule, ol:has(> .fc-news), .fc-com-logo-box.fc-logo-in, .fc-belt-box.fc-logo-in';
    // never copied: modal sheets, inline drawings, and this script's own layers
    var EXCLUDE = 'dialog, template, noscript, svg, .fc-spot-dup, .fc-spot-ground, .fc-spot-core';
    // things that slide their contents
    var RAILS = '[data-fc-spk-rail], [data-fc-belt-rail]';
    // class changes that are only ever state, never content
    var STATE_CLASSES = { 'is-hot': 1, 'is-holding': 1, 'is-hovered': 1, 'is-dragging': 1 };

    // the wave — same constants as assets/wave-bg.js
    var AMP_1 = 18, KX_1 = 0.014, SPEED_1 = 0.45;
    var AMP_2 = 11, KX_2 = 0.027, SPEED_2 = -0.65;
    var AMP_3 = 5,  KX_3 = 0.060, KY_3 = 0.30 / 16, SPEED_3 = 0.85;

    var reducedQuery = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    var reduced = !!reducedQuery && reducedQuery.matches;
    // …and followed when it is changed while the page is open
    if (reducedQuery) {
        var onReduced = function (e) { reduced = e.matches; };
        if (reducedQuery.addEventListener) reducedQuery.addEventListener('change', onReduced);
        else if (reducedQuery.addListener) reducedQuery.addListener(onReduced);
    }

    // the pointer, in CLIENT coordinates — scroll is added per frame, so the
    // disc stays under a still cursor while the page scrolls beneath it
    // seq counts the moves, so a frame can tell whether the pointer moved
    var ptr = { cx: 0, cy: 0, on: false, seq: 0 };
    // what is under the pointer is asked again when this is set (see frame)
    var probeDue = true;

    /* ── the page background's dot field ────────────────────────────────────
     * A replica of assets/wave-bg.js's grid, in VIEWPORT device pixels: the
     * columns and rows it lays across the window, and each dot's wave offset on
     * its clock. Worked out once per frame and shared by every panel, so a blue
     * panel, a paper disc and the background itself all put a dot in exactly the
     * same place. */
    function bgAxis(len, p) {
        var n = Math.floor(len / p);
        if (n < 1) n = 1;
        var m = Math.round((len - (n - 1) * p) / 2), out = [];
        for (var k = 0; k < n; k++) out.push(Math.round(m + p * k));
        return out;
    }
    /* Rows past the window's edges, on the same lattice. The background stops at
     * one extra row, because it is fixed and never shows more; a blue panel is
     * not fixed, and on a fast scroll it can show a strip the next frame has not
     * painted yet — this way that strip carries dots rather than bare blue. */
    var SPARE_ROWS = 8;
    var field = { now: NaN, dpr: 0, w: 0, h: 0 };
    // the window's inner size, as the background's canvas takes it — kept from
    // the last resize, so a frame never has to ask (see MEASURING)
    var inner = { w: window.innerWidth, h: window.innerHeight };
    function fieldAt(now, dpr) {
        var W = Math.round(inner.w * dpr), H = Math.round(inner.h * dpr);
        if (field.now === now && field.dpr === dpr && field.w === W && field.h === H) return field;
        var gp = PITCH * dpr, i;
        if (field.dpr !== dpr || field.w !== W || field.h !== H) {
            field.cols = bgAxis(W, gp);
            var ys = bgAxis(H, gp), rows = [];
            for (i = SPARE_ROWS; i >= 1; i--) rows.push(ys[0] - gp * i);
            rows = rows.concat(ys);
            for (i = 1; i <= SPARE_ROWS; i++) rows.push(ys[ys.length - 1] + gp * i);
            field.rows = rows;
            field.rowSin = rows.map(function (y) { return Math.sin((y / dpr) * KY_3); });
            field.rowCos = rows.map(function (y) { return Math.cos((y / dpr) * KY_3); });
            field.flat = new Float64Array(field.cols.length);
            field.cS = new Float64Array(field.cols.length);
            field.cC = new Float64Array(field.cols.length);
            field.dpr = dpr; field.w = W; field.h = H;
        }
        // the background's clock: wave-bg.js publishes its start time, and both
        // scripts are handed the same rAF timestamp in a frame. With reduced
        // motion the background holds one still instant, which it publishes
        // as FC_WAVE_NOW like any other (see waveNow); without it, the first.
        var t0 = window.FC_WAVE_T0 != null ? window.FC_WAVE_T0 : START;
        var tg = reduced && window.FC_WAVE_NOW == null ? 0 : (now - t0) / 1000;
        var p1 = tg * SPEED_1, p2 = tg * SPEED_2, p3 = tg * SPEED_3;
        for (i = 0; i < field.cols.length; i++) {
            var xCss = field.cols[i] / dpr, a = xCss * KX_3 + p3;
            field.flat[i] = AMP_1 * Math.sin(xCss * KX_1 + p1) + AMP_2 * Math.sin(xCss * KX_2 + p2);
            field.cS[i] = Math.sin(a);
            field.cC[i] = Math.cos(a);
        }
        field.now = now;
        return field;
    }
    /* Every dot of the field at its instant — top-left, units, x then y — for
     * the grounds drawn on the GPU; worked out once an instant, for all of
     * them. `stamp` moves on only when some dot moved to a new pixel. */
    var dotsAt = { now: NaN, f: null, pos: null, ys: null, n: 0, stamp: 0 };
    function fieldDots(f) {
        var D = dotsAt;
        if (D.f === f && D.now === f.now && D.dpr === f.dpr && D.w === f.w && D.h === f.h) return D;
        var nc = f.cols.length, nr = f.rows.length, n = nc * nr, dpr = f.dpr;
        var half = Math.round(DOT * dpr / 2), moved = false, k = 0;
        if (!D.pos || D.n !== n || D.dpr !== dpr || D.w !== f.w || D.h !== f.h) {
            D.pos = new Float32Array(n * 2); D.ys = new Float64Array(n).fill(NaN); D.n = n; moved = true;
        }
        for (var j = 0; j < nr; j++) {
            var sb = f.rowSin[j], cb = f.rowCos[j], yb = f.rows[j];
            for (var i = 0; i < nc; i++, k++) {
                var y = yb + Math.round((f.flat[i] + AMP_3 * (f.cS[i] * cb + f.cC[i] * sb)) * dpr) - half;
                if (y !== D.ys[k]) { D.ys[k] = y; moved = true; }
                D.pos[2 * k] = f.cols[i] - half; D.pos[2 * k + 1] = y;
            }
        }
        if (moved) D.stamp++;
        D.f = f; D.now = f.now; D.dpr = dpr; D.w = f.w; D.h = f.h;
        return D;
    }
    var START = performance.now();
    /* The instant the page background's field on screen was drawn for
     * (assets/wave-bg.js draws it at most 60 times a second, 20 with no GPU):
     * every dot this script draws is drawn for that same instant, so the two
     * fields agree pixel for pixel whatever frames each is drawn on. */
    function waveNow(now) { return window.FC_WAVE_NOW != null ? window.FC_WAVE_NOW : now; }

    /* The field's dots inside the core, cut to it — each of a dot's three bars
     * cut to the core's runs on the cell rows it crosses, so a dot on the rim
     * splits pixel by pixel. That is what a canvas clip to the core did, and a
     * clip that shape was most of what the GPU spent on a frame; this is a few
     * dozen rects.
     *
     * In a canvas's device px: (vx, vy) is the canvas's top-left in WINDOW
     * device px (the field is fixed to the window), (ox, oy) in document CSS
     * px (the goo is fixed to the page). `key` lists the dots' positions, so a
     * frame where neither the goo nor a dot inside it moved can be told from
     * one where something did. Null when no core reaches the canvas. */
    function coreDots(f, g, ox, oy, dpr, vx, vy, H, Wd) {
        var cb = g.core, i, j;
        /* The core's box, cut to THIS canvas (Wd x H): a goo flung across
           several sections is drawn by each of them, and each only needs the
           dots over its own canvas — the rest would land off it. */
        var bx0 = Math.max(0, (cb[0] * Q - ox) * dpr), bx1 = Math.min(Wd, (cb[2] * Q - ox) * dpr);
        var by0 = Math.max(0, (cb[1] * Q - oy) * dpr), by1 = Math.min(H, (cb[3] * Q - oy) * dpr);
        if (by1 <= by0 || bx1 <= bx0) return null;
        var u = dpr, half = Math.round(DOT * dpr / 2), s = DOT * dpr;
        var reach = (AMP_1 + AMP_2 + AMP_3) * dpr + s;
        var path = new Path2D(), key = '';
        // the columns that can reach the box (f.cols is in ascending order)
        var i0 = 0, i1 = f.cols.length;
        while (i0 < i1 && f.cols[i0] - vx + s <= bx0) i0++;
        while (i1 > i0 && f.cols[i1 - 1] - vx - s >= bx1) i1--;
        for (j = 0; j < f.rows.length; j++) {
            var yr = f.rows[j] - vy;
            if (yr < by0 - reach || yr > by1 + reach) continue;
            var sb = f.rowSin[j], cbr = f.rowCos[j];
            for (i = i0; i < i1; i++) {
                var xr = f.cols[i] - vx;
                if (xr + s <= bx0 || xr - s >= bx1) continue;
                var dy = f.flat[i] + AMP_3 * (f.cS[i] * cbr + f.cC[i] * sb);
                var x = xr - half, y = yr + Math.round(dy * dpr) - half;     // whole device pixels
                if (x + s <= bx0 || x >= bx1 || y + s <= by0 || y >= by1) continue;
                // a dot the core does not touch adds nothing: passed over (with
                // drops all over the window, that is nearly every dot in the box)
                if (!touches(g, ox, oy, dpr, x, y, x + s, y + s)) continue;
                key += x + ',' + y + ';';
                cut(path, g, ox, oy, dpr, x + u, y, x + u * 5, y + u);
                cut(path, g, ox, oy, dpr, x, y + u, x + u * 6, y + u * 5);
                cut(path, g, ox, oy, dpr, x + u, y + u * 5, x + u * 5, y + u * 6);
            }
        }
        return { path: path, key: key };
    }
    // one bar of a dot, cut to the core's runs on the (one or two) cell rows
    // it crosses
    function cut(path, g, ox, oy, dpr, x0, y0, x1, y1) {
        var rows = g.spans[1], cs = Q * dpr;
        var r0 = Math.max(0, Math.floor((oy + y0 / dpr) / Q) - g.y0);
        var r1 = Math.min(g.h - 1, Math.floor((oy + (y1 - 1) / dpr) / Q) - g.y0);
        for (var r = r0; r <= r1; r++) {
            var runs = rows[r];
            if (!runs) continue;
            var ry0 = ((g.y0 + r) * Q - oy) * dpr;
            var c = Math.max(y0, ry0), d = Math.min(y1, ry0 + cs);
            if (c >= d) continue;
            // the runs that can overlap [x0, x1): from the first that ends past
            // x0, while they start before x1 (a row of a core full of drops
            // holds dozens of runs)
            for (var k = firstRun(runs, ox, dpr, x0); k < runs.length; k += 2) {
                var ra = (runs[k] * Q - ox) * dpr;
                if (ra >= x1) break;
                var a = Math.max(x0, ra), b = Math.min(x1, (runs[k + 1] * Q - ox) * dpr);
                if (a < b) path.rect(a, c, b - a, d - c);
            }
        }
    }
    // in a row's runs [a, b, a, b, …], the index of the first that ends past x
    // (in device px from the canvas's left, the canvas at ox)
    function firstRun(runs, ox, dpr, x) {
        var lo = 0, hi = runs.length >> 1;
        while (lo < hi) {
            var mid = (lo + hi) >> 1;
            if ((runs[2 * mid + 1] * Q - ox) * dpr <= x) lo = mid + 1; else hi = mid;
        }
        return 2 * lo;
    }
    // does the core cover any of the box [x0, x1) × [y0, y1)? (as cut sees it)
    function touches(g, ox, oy, dpr, x0, y0, x1, y1) {
        var rows = g.spans[1], cs = Q * dpr;
        var r0 = Math.max(0, Math.floor((oy + y0 / dpr) / Q) - g.y0);
        var r1 = Math.min(g.h - 1, Math.floor((oy + (y1 - 1) / dpr) / Q) - g.y0);
        for (var r = r0; r <= r1; r++) {
            var runs = rows[r];
            if (!runs) continue;
            var ry0 = ((g.y0 + r) * Q - oy) * dpr;
            if (Math.max(y0, ry0) >= Math.min(y1, ry0 + cs)) continue;
            var k = firstRun(runs, ox, dpr, x0);
            if (k < runs.length && (runs[k] * Q - ox) * dpr < x1) return true;
        }
        return false;
    }

    /* ── THE TRAIL ─────────────────────────────────────────────────────────────
     *
     * The highlight is not a circle any more: it is goo. One field over the
     * page, summed from everything below, thresholded on the 6px cell lattice —
     * metaballs, so whatever comes close melts together and whatever pulls away
     * necks and snaps, and every edge is a step of one cell:
     *
     *   HEAD    an under-damped spring on the pointer: it lags, overshoots and
     *           settles. Still, with its tail gathered under it, it is a disc
     *           of exactly RADIUS.
     *   TAIL    a rope of shrinking blobs, each chasing the one ahead with its
     *           own slower spring and never further than a link away — the
     *           delay you see when the pointer moves, the snake when it is fast.
     *   DROPS   flung off a fast head; they fall through the whole page — it is
     *           one ground, with nothing to hit — shrinking as they go, and are
     *           gone at the end of their short life (or sooner, out of sight
     *           below the screen); the ones still close are drawn back into
     *           the head and swallowed.
     *   CLICK   a burst of drops and a pixel shockwave running out from the head.
     *
     * The outline is three rings of two cells each, measured as true distance
     * from the goo (an exact distance transform on the cells), so it follows the
     * shape everywhere: round the head, along the neck, round every drop.
     *
     * Everything is in DOCUMENT px, and every section paints only its own
     * share: one trail across the seams. A scroll moves the page under a still
     * pointer, which is the pointer moving over the page: the head chases it,
     * the tail stretches and drops fly, exactly as for the mouse. */
    var T_ISO = 0.25;                                       // the goo's surface
    /* The tail: eleven blobs, the first nearly the head's size and each a
     * tenth smaller, a link apart — a long, full rope when the pointer moves.
     * (Was nine, from 110 down by an eighth, 44 apart.) */
    var TAIL = 11, LINK = 48, TAIL_R = 104, TAIL_SHRINK = 0.9;
    function kernel(d, R) { var t = 1 - d * d / (R * R); return t > 0 ? t * t * t : 0; }
    /* The head is sized so the goo AT REST — head and tail all stacked on the
     * pointer, the tail's field adding to the head's — is a disc of exactly
     * RADIUS: the head's own share at RADIUS is what is left of the surface
     * value once the tail's is taken off, and its size follows from that. */
    var R_HEAD = (function () {
        var tails = 0;
        for (var i = 0; i < TAIL; i++) tails += kernel(RADIUS, TAIL_R * Math.pow(TAIL_SHRINK, i));
        var own = Math.max(0.02, T_ISO - tails);             // (1 − r²/R²)³ = own
        return RADIUS / Math.sqrt(1 - Math.cbrt(own));
    })();
    // (no cap on drops: as many fly as the pointer throws, each shrinking away
    // over its life, or gone sooner out of sight or swallowed back into the head)
    var GRAVITY = 1700, DRAG = 1.1;
    var RINGS = 3, RING_CELLS = 2;                          // the 6px outline: 3 × 2 cells
    var MARGIN_CELLS = RINGS * RING_CELLS + 1;

    var trail = {
        head: { x: 0, y: 0, vx: 0, vy: 0 },
        tail: [],
        parts: [],          // the drops
        shocks: [],
        alive: 0,           // 0..1 — grows in when the pointer arrives, out when it leaves
        pulse: 0,           // a click's squash, decaying
        placed: false,
        emit: 0,
        t: 0
    };
    for (var ti = 0; ti < TAIL; ti++) {
        trail.tail.push({ x: 0, y: 0, vx: 0, vy: 0, R: TAIL_R * Math.pow(TAIL_SHRINK, ti) });
    }

    function rnd(a, b) { return a + Math.random() * (b - a); }

    function spawn(x, y, vx, vy, R, life, g, kind) {
        trail.parts.push({ x: x, y: y, vx: vx, vy: vy, R: R, age: 0, life: life, g: g, kind: kind });
    }

    // a drop flung off the back of a moving head
    function fling() {
        var h = trail.head, n = trail.tail[Math.floor(Math.random() * 4)];
        var a = Math.atan2(h.vy, h.vx) + Math.PI + rnd(-1.15, 1.15), s = rnd(160, 470);
        spawn(n.x, n.y, n.vx * 0.4 + Math.cos(a) * s, n.vy * 0.4 + Math.sin(a) * s - 120,
              rnd(46, 78), rnd(0.7, 1.5), 1, 'drop');
    }

    // a click: drops all round and a ring running out — and the head squashes
    function burst() {
        if (reduced || trail.alive < 0.5) return;
        var h = trail.head;
        for (var i = 0; i < 16; i++) {
            var a = i / 16 * Math.PI * 2 + rnd(-0.2, 0.2), s = rnd(480, 980);
            spawn(h.x + Math.cos(a) * 40, h.y + Math.sin(a) * 40, Math.cos(a) * s + h.vx * 0.3, Math.sin(a) * s + h.vy * 0.3 - 160,
                  rnd(44, 74), rnd(0.9, 1.5), 0.75, 'drop');
        }
        trail.shocks.push({ x: h.x, y: h.y, age: 0, life: 0.55 });
        trail.pulse = 1;
    }

    function stepTrail(dt, tx, ty, on, view) {
        var tr = trail, h = tr.head, i, n;
        tr.t += dt;
        if (on && !tr.placed) {
            // arriving: the goo is born at the pointer, not dragged across the page
            h.x = tx; h.y = ty; h.vx = h.vy = 0;
            for (i = 0; i < TAIL; i++) { n = tr.tail[i]; n.x = tx; n.y = ty; n.vx = n.vy = 0; }
            tr.placed = true;
        }
        tr.alive += ((on ? 1 : 0) - tr.alive) * Math.min(1, dt * (on ? 10 : 6));
        if (!on && tr.alive < 0.02) { tr.alive = 0; tr.placed = false; }
        tr.pulse *= Math.exp(-dt * 6);

        if (reduced) {
            // no motion asked for: the head sits on the pointer, nothing flies
            h.x = tx; h.y = ty;
            for (i = 0; i < TAIL; i++) { n = tr.tail[i]; n.x = tx; n.y = ty; }
            tr.parts.length = 0; tr.shocks.length = 0;
            return;
        }

        // the head: an under-damped spring — it lags, overshoots, settles
        var K = 420, C = 20;
        h.vx += ((tx - h.x) * K - h.vx * C) * dt;
        h.vy += ((ty - h.y) * K - h.vy * C) * dt;
        h.x += h.vx * dt; h.y += h.vy * dt;

        // the tail: each blob chases the one ahead, on a rope
        var prev = h;
        for (i = 0; i < TAIL; i++) {
            n = tr.tail[i];
            var k = 560 * Math.pow(0.84, i), c = 2 * 0.62 * Math.sqrt(k);
            n.vx += ((prev.x - n.x) * k - n.vx * c) * dt;
            n.vy += ((prev.y - n.y) * k - n.vy * c) * dt;
            n.x += n.vx * dt; n.y += n.vy * dt;
            var dx = n.x - prev.x, dy = n.y - prev.y, d = Math.sqrt(dx * dx + dy * dy);
            if (d > LINK) { n.x = prev.x + dx / d * LINK; n.y = prev.y + dy / d * LINK; }
            prev = n;
        }

        // drops fling off a fast head
        var sp = Math.sqrt(h.vx * h.vx + h.vy * h.vy);
        if (on && sp > 650) {
            tr.emit += dt * (sp - 650) / 42;
            while (tr.emit >= 1) { tr.emit -= 1; fling(); }
        } else tr.emit = 0;

        /* The drops, in order, the ones that stay packed down as they go (a
         * whole rain leaving the screen at once was a splice per drop, each
         * moving all the rest). */
        var kept = 0, f = Math.exp(-DRAG * dt);
        for (i = 0; i < tr.parts.length; i++) {
            var p = tr.parts[i];
            p.age += dt;
            if (p.age >= p.life) continue;                          // shrunk away (see fieldCells)
            p.vy += GRAVITY * p.g * dt;
            p.vx *= f; p.vy *= f;
            /* A drop that is still near the head, after a moment in the air, is
             * pulled back in and swallowed. Within 36px: a drop held under a
             * resting head settles where the pull meets what is left of its
             * weight, 29.5px below it at 60 frames a second but 30.3 at 144 —
             * outside the 30 it used to be, so on a fast screen it could hang
             * there for good, a bulge under the disc. */
            if (p.age > 0.2 && tr.alive > 0.5) {
                var hx = h.x - p.x, hy = h.y - p.y, hd = Math.sqrt(hx * hx + hy * hy);
                if (hd < 160) {
                    p.vx += hx * 11 * dt; p.vy += hy * 11 * dt - GRAVITY * p.g * dt * 0.8;
                    if (hd < 36) continue;
                }
            }
            p.x += p.vx * dt; p.y += p.vy * dt;
            /* NO WALLS. The page is one ground, so nothing on it stops a drop:
             * it falls, whole, through every section and off the bottom of the
             * screen — gone once it is out of sight below it, or out at a side
             * (or flung far above it). */
            if (p.y - p.R > view.y1 || p.x + p.R < view.x0 || p.x - p.R > view.x1 || p.y + p.R < view.y0 - 2000) continue;
            tr.parts[kept++] = p;
        }
        tr.parts.length = kept;
        for (i = tr.shocks.length - 1; i >= 0; i--) {
            tr.shocks[i].age += dt;
            if (tr.shocks[i].age >= tr.shocks[i].life) tr.shocks.splice(i, 1);
        }
    }

    /* ── the field, on the cells ─────────────────────────────────────────── */

    var cells = null;           // this frame's goo, or null when there is none
    var GOO = null;             // the GPU layer that draws it for the whole page (see gooLayer)
    /* …and whether it is drawing THIS frame. Not while its context is lost
     * (a GPU reset, a driver update): then every section draws its own share
     * in 2D, rings and all, until it is back. */
    var GPU = false;
    /* The disc of cell offsets within the outline's reach of a cell, with each
     * one's squared distance — and, for a box W cells wide, each as a step
     * through the flat array. In sixteen parts, one for each set of open
     * sides a core cell can have (1 left, 2 right, 4 up, 8 down): only the
     * offsets that lie towards its open sides (see fieldCells). */
    var disc = null;
    function discOffsets(W) {
        var m, i, s;
        if (!disc) {
            var R = RINGS * RING_CELLS, dx = [], dy = [], d2 = [];
            for (var y = -R; y <= R; y++) for (var x = -R; x <= R; x++) {
                if (x * x + y * y <= R * R) { dx.push(x); dy.push(y); d2.push(x * x + y * y); }
            }
            disc = { sets: [], w: -1 };
            for (m = 0; m < 16; m++) {
                s = { n: 0, dx: [], dy: [], d2: [], at: null };
                for (i = 0; i < dx.length; i++) {
                    if ((dx[i] < 0 && !(m & 1)) || (dx[i] > 0 && !(m & 2)) || (dy[i] < 0 && !(m & 4)) || (dy[i] > 0 && !(m & 8))) continue;
                    s.dx.push(dx[i]); s.dy.push(dy[i]); s.d2.push(d2[i]);
                }
                s.n = s.dx.length; s.d2 = new Uint8Array(s.d2); s.at = new Int32Array(s.n);
                disc.sets.push(s);
            }
        }
        if (disc.w !== W) {
            for (m = 0; m < 16; m++) { s = disc.sets[m]; for (i = 0; i < s.n; i++) s.at[i] = s.dy[i] * W + s.dx[i]; }
            disc.w = W;
        }
        return disc;
    }

    var bufs = { n: 0 };
    // last frame's goo, and the core it was made from
    var memo = { g: null, mask: null, x0: 0, y0: 0, w: 0, h: 0 };
    function buffers(n) {
        if (bufs.n < n) {
            bufs.n = n;
            bufs.f = new Float32Array(n);
            bufs.d2 = new Uint8Array(n);
            bufs.cls = new Uint8Array(n);
            bufs.mask = new Uint8Array(n);
            bufs.core = new Int32Array(n);
            bufs.xs = new Int32Array(n);
            memo.g = null; memo.mask = new Uint8Array(n);
        }
        return bufs;
    }
    /* The goo of this frame as classes per cell — 1 the core, 2..4 the rings —
     * and as runs per row, which is what everything downstream draws with.
     * `view` limits the work to what can be seen. */
    function fieldCells(view) {
        var tr = trail, a = tr.alive, ents = [], i, n;
        if (a > 0.01) {
            var sa = Math.sqrt(a);
            var Rh = R_HEAD * sa * (1 + 0.025 * Math.sin(tr.t * 2.4) + 0.22 * tr.pulse);
            ents.push([tr.head.x, tr.head.y, Rh, 1]);
            for (i = 0; i < TAIL; i++) { n = tr.tail[i]; ents.push([n.x, n.y, n.R * sa, 1]); }
        }
        // the drops, shrinking to nothing over their life (see stepTrail)
        for (i = 0; i < tr.parts.length; i++) {
            var p = tr.parts[i], left = 1 - p.age / p.life;
            ents.push([p.x, p.y, p.R * Math.sqrt(left), 1]);
        }
        /* Shockwaves are a RIPPLE in the outline's colours, not goo: a ring of
         * cells the width of the outline, running out from the click. As goo it
         * re-cut every copy it crossed on every frame of its run, which is what a
         * click used to cost. [x, y, radius, fade] */
        var rings = [];
        for (i = 0; i < tr.shocks.length; i++) {
            var s = tr.shocks[i], u = s.age / s.life;
            rings.push([s.x, s.y, 40 + 520 * Math.sqrt(u), u]);
        }
        if (!ents.length && !rings.length) return null;

        // the cells that can matter: everything's reach, plus the outline, clipped
        // to the window
        var bx0 = Infinity, by0 = Infinity, bx1 = -Infinity, by1 = -Infinity;
        for (i = 0; i < ents.length; i++) {
            var e = ents[i];
            if (e[2] < 1) continue;
            bx0 = Math.min(bx0, e[0] - e[2]); bx1 = Math.max(bx1, e[0] + e[2]);
            by0 = Math.min(by0, e[1] - e[2]); by1 = Math.max(by1, e[1] + e[2]);
        }
        var RIPPLE = RINGS * RING_CELLS * Q;
        for (i = 0; i < rings.length; i++) {
            var rr = rings[i][2] + RIPPLE;
            bx0 = Math.min(bx0, rings[i][0] - rr); bx1 = Math.max(bx1, rings[i][0] + rr);
            by0 = Math.min(by0, rings[i][1] - rr); by1 = Math.max(by1, rings[i][1] + rr);
        }
        var M = MARGIN_CELLS;
        var cx0 = Math.max(Math.floor(bx0 / Q) - M, Math.floor(view.x0 / Q) - M);
        var cy0 = Math.max(Math.floor(by0 / Q) - M, Math.floor(view.y0 / Q) - M);
        var cx1 = Math.min(Math.ceil(bx1 / Q) + M, Math.ceil(view.x1 / Q) + M);
        var cy1 = Math.min(Math.ceil(by1 / Q) + M, Math.ceil(view.y1 / Q) + M);
        var W = cx1 - cx0, H = cy1 - cy0;
        if (W <= 0 || H <= 0) return null;
        var N = W * H, B = buffers(N), F = B.f, j, x, y, L, iq;
        /* Only the TILES something can reach are worked on: 8×8 cells, marked
         * for each blob's box with the outline's reach round it, and for each
         * ripple's. The box above spans everything at once — the head at one
         * side of the window and a drop at the other made every pass below
         * walk the whole window's cells, and a zoomed-out window holds several
         * times as many. Outside the marked tiles every cell is class 0, which
         * is all the passes ever found there: the goo is the same to the cell.
         * iv[tile row] = the marked runs of tiles on it, as [x0, x1) in cells. */
        var reach = RINGS * RING_CELLS, TW = (W + 7) >> 3, TH = (H + 7) >> 3;
        var tiles = new Uint8Array(TW * TH), ti, tj;
        function mark(i0, j0, i1, j1) {                 // cells, inclusive
            i0 = Math.max(0, i0) >> 3; j0 = Math.max(0, j0) >> 3;
            i1 = Math.min(W - 1, i1) >> 3; j1 = Math.min(H - 1, j1) >> 3;
            for (var mb = j0; mb <= j1; mb++) for (var mc = i0; mc <= i1; mc++) tiles[mb * TW + mc] = 1;
        }
        /* Round each blob, its own box and — when the rings are worked out here —
           the outline's reach (with the GPU layer they are worked out there, from
           the core alone: a cell's margin is all the core needs). */
        var mm = GPU ? 1 : reach + 1;
        for (i = 0; i < ents.length; i++) {
            var me = ents[i];
            if (me[2] < 1) continue;
            mark(Math.floor((me[0] - me[2]) / Q - 0.5) - cx0 - mm, Math.floor((me[1] - me[2]) / Q - 0.5) - cy0 - mm,
                 Math.ceil((me[0] + me[2]) / Q) - cx0 + mm, Math.ceil((me[1] + me[2]) / Q) - cy0 + mm);
        }
        for (i = 0; i < rings.length; i++) {
            var mo = rings[i][2] + RIPPLE;
            mark(Math.floor((rings[i][0] - mo) / Q) - cx0, Math.floor((rings[i][1] - mo) / Q) - cy0,
                 Math.ceil((rings[i][0] + mo) / Q) - cx0, Math.ceil((rings[i][1] + mo) / Q) - cy0);
        }
        var iv = new Array(TH);
        for (tj = 0; tj < TH; tj++) {
            var runs = null;
            for (ti = 0; ti < TW; ti++) {
                if (!tiles[tj * TW + ti]) continue;
                var t0 = ti << 3;
                while (ti + 1 < TW && tiles[tj * TW + ti + 1]) ti++;
                (runs || (runs = [])).push(t0, Math.min(W, (ti + 1) << 3));
            }
            iv[tj] = runs;
        }
        // the core, and the box round it; its cells listed, and (on the CPU
        // path) the tiles that hold any
        var cls = B.cls, mask = B.mask, any = false;
        var kx0 = W, ky0 = H, kx1 = -1, ky1 = -1;
        var core = B.core, nCore = 0, coreTiles = GPU ? null : new Uint8Array(TW * TH);
        for (y = 0; y < H; y++) {
            L = iv[y >> 3];
            if (!L) continue;
            for (iq = 0; iq < L.length; iq += 2) {
                F.fill(0, y * W + L[iq], y * W + L[iq + 1]);
                if (GPU) mask.fill(0, y * W + L[iq], y * W + L[iq + 1]);
            }
        }

        /* blobs: the Wyvill kernel, (1 − d²/R²)³ inside R, nothing outside.
           Every blob only ever ADDS to a cell, so a cell is core from the moment
           its sum reaches the surface — with the GPU layer that moment is
           caught here, as it happens, and there is no pass over the box after
           (the sum is the same float32 the pass would have read). */
        for (i = 0; i < ents.length; i++) {
            var ex = ents[i][0], ey = ents[i][1], R = ents[i][2], wgt = ents[i][3];
            if (R < 1) continue;
            var R2 = R * R;
            var j0 = Math.max(0, Math.floor((ey - R) / Q - 0.5) - cy0), j1 = Math.min(H - 1, Math.ceil((ey + R) / Q) - cy0);
            for (j = j0; j <= j1; j++) {
                var dy = (cy0 + j + 0.5) * Q - ey, dy2 = dy * dy;
                if (dy2 >= R2) continue;
                var hw = Math.sqrt(R2 - dy2);
                var i0 = Math.max(0, Math.floor((ex - hw) / Q - 0.5) - cx0), i1 = Math.min(W - 1, Math.ceil((ex + hw) / Q) - cx0);
                var row = j * W;
                for (x = i0; x <= i1; x++) {
                    var dx = (cx0 + x + 0.5) * Q - ex, t = 1 - (dx * dx + dy2) / R2;
                    if (t <= 0) continue;
                    if (!GPU) { F[row + x] += wgt * t * t * t; continue; }
                    var at1 = row + x, f0 = F[at1], f1 = Math.fround(f0 + wgt * t * t * t);
                    F[at1] = f1;
                    if (f0 < T_ISO && f1 >= T_ISO) {
                        mask[at1] = 1; core[nCore++] = at1;
                        if (x < kx0) kx0 = x;
                        if (x > kx1) kx1 = x;
                        if (j < ky0) ky0 = j;
                        if (j > ky1) ky1 = j;
                    }
                }
            }
        }
        if (GPU) any = nCore > 0;
        else for (y = 0; y < H; y++) {
            L = iv[y >> 3];
            if (!L) continue;
            var mrow = y * W, trow = (y >> 3) * TW;
            for (iq = 0; iq < L.length; iq += 2) {
                for (x = L[iq]; x < L[iq + 1]; x++) {
                    if (F[mrow + x] >= T_ISO) {
                        mask[mrow + x] = 1; any = true;
                        core[nCore++] = mrow + x; coreTiles[trow + (x >> 3)] = 1;
                        if (x < kx0) kx0 = x;
                        if (x > kx1) kx1 = x;
                        if (y < ky0) ky0 = y;
                        ky1 = y;
                    } else mask[mrow + x] = 0;
                }
            }
        }
        if (!any && !rings.length) { memo.g = null; return null; }
        /* The same core as last frame — the pointer resting, the head only
         * breathing inside one cell — is the same goo, outline and all: hand
         * back last frame's, and everything downstream sees nothing new. */
        // (not across a change of who draws it: the GPU's goo carries no rings)
        if (!rings.length && memo.g && memo.gpu === GPU && memo.x0 === cx0 && memo.y0 === cy0 && memo.w === W && memo.h === H) {
            // tile by tile, over the tiles either frame marked: an unmarked
            // tile's cells are all outside the core
            var same = true, pm = memo.mask, pt = memo.tiles;
            for (tj = 0; tj < TH && same; tj++) for (ti = 0; ti < TW && same; ti++) {
                var tc = tiles[tj * TW + ti], tp = pt[tj * TW + ti];
                if (!tc && !tp) continue;
                for (y = tj << 3, j = Math.min(H, y + 8); y < j && same; y++) {
                    for (x = y * W + (ti << 3), i = y * W + Math.min(W, (ti + 1) << 3); x < i; x++) {
                        if ((tc ? mask[x] : 0) !== (tp ? pm[x] : 0)) { same = false; break; }
                    }
                }
            }
            if (same) return memo.g;
        }
        // every cell of the box to 0 (one memset): the classes go up whole to
        // the GPU goo layer (see gooLayer), not just the marked tiles' cells
        cls.fill(0, 0, N);
        if (any && GPU) {
            /* THE RINGS ON THE GPU (see gooLayer): only the core goes up, and the
               GPU measures every cell's distance to it there — the same exact
               distances as below, for none of the work here. */
            for (var cc = 0; cc < nCore; cc++) cls[core[cc]] = 1;
        } else if (any) {
            /* Every other cell's distance to the core, out to the outline's
             * reach. The nearest core cell to anything outside is always on the
             * core's EDGE, so each edge cell stamps the disc of offsets round
             * it, keeping the smallest squared distance per cell. Exact for
             * every distance the rings use — what a full distance transform of
             * the box gave — for a few hundred edge cells' work instead of the
             * whole box's.
             *
             * And only TOWARDS ITS OPEN SIDES. Whatever outside cell a core
             * cell is nearest to, the core cell's neighbour on that side —
             * right, if the outside cell lies to its right — is not core,
             * or that neighbour would be nearer still. So a cell stamps only
             * the offsets that lie towards its open sides: on a smooth edge
             * a line of seven cells, not a disc of 113. The same distances,
             * exactly — and a frame full of drops costs a fraction of what it
             * did. */
            var R2max = reach * reach;
            var wx0 = Math.max(0, kx0 - reach), wx1 = Math.min(W - 1, kx1 + reach);
            var wy0 = Math.max(0, ky0 - reach), wy1 = Math.min(H - 1, ky1 + reach);
            var D2 = B.d2, fa, fb, ci;
            discOffsets(W);
            /* Only the tiles NEXT TO the core — a tile holding core cells, and
             * the eight round it (a tile is wider than the outline's reach) —
             * can be within reach of it; of those, the marked ones. With drops
             * all over the window, the rest of it is left alone.
             * ivN[tile row]: those tiles' runs, as iv. */
            var near = new Uint8Array(TW * TH), ivN = new Array(TH);
            for (tj = 0; tj < TH; tj++) for (ti = 0; ti < TW; ti++) {
                if (!coreTiles[tj * TW + ti]) continue;
                for (var nj = Math.max(0, tj - 1); nj <= Math.min(TH - 1, tj + 1); nj++)
                    for (var ni = Math.max(0, ti - 1); ni <= Math.min(TW - 1, ti + 1); ni++)
                        if (tiles[nj * TW + ni]) near[nj * TW + ni] = 1;
            }
            for (tj = 0; tj < TH; tj++) {
                var nruns = null;
                for (ti = 0; ti < TW; ti++) {
                    if (!near[tj * TW + ti]) continue;
                    var n0 = ti << 3;
                    while (ti + 1 < TW && near[tj * TW + ti + 1]) ti++;
                    (nruns || (nruns = [])).push(n0, Math.min(W, (ti + 1) << 3));
                }
                ivN[tj] = nruns;
            }
            for (y = wy0; y <= wy1; y++) {
                L = ivN[y >> 3];
                if (!L) continue;
                for (iq = 0; iq < L.length; iq += 2) {
                    fa = Math.max(wx0, L[iq]); fb = Math.min(wx1 + 1, L[iq + 1]);
                    if (fa < fb) D2.fill(255, y * W + fa, y * W + fb);
                }
            }
            for (ci = 0; ci < nCore; ci++) {
                {
                    var at = core[ci];
                    y = (at / W) | 0; x = at - y * W;
                    // its open sides (the box's edge counts as open)
                    var open = (x === 0 || !mask[at - 1] ? 1 : 0) | (x === W - 1 || !mask[at + 1] ? 2 : 0)
                             | (y === 0 || !mask[at - W] ? 4 : 0) | (y === H - 1 || !mask[at + W] ? 8 : 0);
                    if (!open) continue;                            // inside the core
                    var off = disc.sets[open], o, t;
                    if (x >= reach && x < W - reach && y >= reach && y < H - reach) {
                        for (o = 0; o < off.n; o++) {
                            t = at + off.at[o];
                            if (D2[t] > off.d2[o]) D2[t] = off.d2[o];
                        }
                    } else {
                        for (o = 0; o < off.n; o++) {
                            var tx = x + off.dx[o], ty = y + off.dy[o];
                            if (tx < 0 || tx >= W || ty < 0 || ty >= H) continue;
                            t = ty * W + tx;
                            if (D2[t] > off.d2[o]) D2[t] = off.d2[o];
                        }
                    }
                }
            }
            var lim1 = RING_CELLS * RING_CELLS, lim2 = 4 * lim1;
            for (y = wy0; y <= wy1; y++) {
                L = ivN[y >> 3];
                if (!L) continue;
                for (iq = 0; iq < L.length; iq += 2) for (x = Math.max(wx0, L[iq]), fb = Math.min(wx1 + 1, L[iq + 1]); x < fb; x++) {
                    var at0 = y * W + x;
                    if (mask[at0]) { cls[at0] = 1; continue; }
                    var d2 = D2[at0];
                    cls[at0] = d2 <= lim1 ? 2 : d2 <= lim2 ? 3 : d2 <= R2max ? 4 : 0;
                }
            }
        }        /* The ripples, laid over that: the outline's three bands, strongest at
         * the leading edge, fading out as the ring runs. A cell keeps whichever
         * band is stronger, so a ripple crossing the goo leaves it alone.
         * (rbx0..rby1: the box of the cells they set.) */
        var rbx0 = W, rby0 = H, rbx1 = -1, rby1 = -1;
        for (i = 0; i < rings.length; i++) {
            var rx = rings[i][0], ry = rings[i][1], rho = rings[i][2], fade = rings[i][3];
            var first = 2 + Math.min(2, Math.floor(fade * 3));       // 50% → 25% → 10% as it runs
            var out = rho + RIPPLE;
            var sj0 = Math.max(0, Math.floor((ry - out) / Q) - cy0), sj1 = Math.min(H - 1, Math.ceil((ry + out) / Q) - cy0);
            var si0 = Math.max(0, Math.floor((rx - out) / Q) - cx0), si1 = Math.min(W - 1, Math.ceil((rx + out) / Q) - cx0);
            for (j = sj0; j <= sj1; j++) {
                var qy = (cy0 + j + 0.5) * Q - ry;
                /* Only the cells by the ring itself — the two arcs where this
                 * row crosses it, a cell wider each way — are tried, each with
                 * the same test: the rest of the ring's square never passes it,
                 * and with clicks in quick succession that was most of a frame. */
                var o2 = rho * rho - qy * qy;
                if (o2 < 0) continue;                                      // the row misses the ring
                var ho = Math.sqrt(o2), rin = rho - RIPPLE;
                var hi = rin > Math.abs(qy) ? Math.sqrt(rin * rin - qy * qy) : -1;
                // [-ho, -hi] and [hi, ho] in qx — or [-ho, ho] with no hole — as cells
                var spansX = hi < 0 ? [-ho, ho] : [-ho, -hi, hi, ho];
                for (var sx = 0; sx < spansX.length; sx += 2) {
                    var xa = Math.max(si0, Math.floor((spansX[sx] + rx) / Q - cx0 - 0.5) - 1);
                    var xb = Math.min(si1, Math.ceil((spansX[sx + 1] + rx) / Q - cx0 - 0.5) + 1);
                    if (sx && spansX.length === 4) xa = Math.max(xa, prevXb + 1);    // the arcs never overlap
                    for (x = xa; x <= xb; x++) {
                        var qx = (cx0 + x + 0.5) * Q - rx;
                        var band = Math.floor((rho - Math.sqrt(qx * qx + qy * qy)) / (RING_CELLS * Q));
                        if (band < 0 || band >= RINGS) continue;           // inside the ring only, behind its edge
                        var cl = first + band;
                        if (cl > 4) continue;
                        var at = j * W + x;
                        if (!cls[at] || cls[at] > cl) {
                            cls[at] = cl;
                            if (x < rbx0) rbx0 = x;
                            if (x > rbx1) rbx1 = x;
                            if (j < rby0) rby0 = j;
                            if (j > rby1) rby1 = j;
                        }
                    }
                    var prevXb = xb;
                }
            }
        }

        // runs per row and class: spans[k][row] = [x0, x1, x0, x1, …] in cells
        var spans = [null, [], [], [], []], kb = [null, [Infinity, Infinity, -Infinity, -Infinity], null, null, null];
        var ext = [Infinity, Infinity, -Infinity, -Infinity];
        for (var k = 1; k <= 4; k++) spans[k] = new Array(H);
        if (GPU) {
            /* With the GPU layer only the core's runs are read (the copies'
               clips), and they come straight from the core's cells — sorted
               into rows, a counting sort, each row in order — with no pass over
               the box. The box: the core's grown by the outline's reach (the
               rings are the GPU's; the furthest ring cell on each side is the
               reach straight out from the core's furthest cell), and the
               ripples'. */
            if (nCore) {
                var rowN = new Int32Array(H + 1), xsB = B.xs, cc2, a2, yy;
                for (cc2 = 0; cc2 < nCore; cc2++) rowN[((core[cc2] / W) | 0) + 1]++;
                for (y = 0; y < H; y++) rowN[y + 1] += rowN[y];
                var put = rowN.slice(0, H);
                for (cc2 = 0; cc2 < nCore; cc2++) { a2 = core[cc2]; yy = (a2 / W) | 0; xsB[put[yy]++] = a2 - yy * W; }
                for (y = ky0; y <= ky1; y++) {
                    var s0 = rowN[y], s1 = rowN[y + 1];
                    if (s0 === s1) continue;
                    var seg = xsB.subarray(s0, s1);
                    seg.sort();
                    var runsY = [], ra = seg[0], rb = ra + 1;
                    for (var q3 = 1; q3 < seg.length; q3++) {
                        if (seg[q3] === rb) rb++;
                        else { runsY.push(cx0 + ra, cx0 + rb); ra = seg[q3]; rb = ra + 1; }
                    }
                    runsY.push(cx0 + ra, cx0 + rb);
                    spans[1][y] = runsY;
                }
                kb[1] = [cx0 + kx0, cy0 + ky0, cx0 + kx1 + 1, cy0 + ky1 + 1];
                ext = [cx0 + Math.max(0, kx0 - reach), cy0 + Math.max(0, ky0 - reach),
                       cx0 + Math.min(W, kx1 + 1 + reach), cy0 + Math.min(H, ky1 + 1 + reach)];
            }
            if (rbx1 >= 0) {
                ext[0] = Math.min(ext[0], cx0 + rbx0); ext[2] = Math.max(ext[2], cx0 + rbx1 + 1);
                ext[1] = Math.min(ext[1], cy0 + rby0); ext[3] = Math.max(ext[3], cy0 + rby1 + 1);
            }
        } else for (y = 0; y < H; y++) {
            for (k = 1; k <= 4; k++) spans[k][y] = null;
            L = iv[y >> 3];
            // each marked stretch of the row, a run closing at its end (beyond
            // it the row is class 0)
            for (iq = 0; L && iq < L.length; iq += 2) for (var rbase = y * W, run = 0, start = L[iq], xb = L[iq + 1], x = start; x <= xb; x++) {
                var c = x < xb ? cls[rbase + x] : 0;
                if (c !== run) {
                    if (run) {
                        // the rings' runs only feed a section's own 2D picture: with
                        // the GPU layer drawing the goo, the core's are all that is
                        // read (the copies' clips) — the box still counts every class
                        if (run === 1 || !GPU) (spans[run][y] || (spans[run][y] = [])).push(cx0 + start, cx0 + x);
                        if (run === 1) {
                            kb[1][0] = Math.min(kb[1][0], cx0 + start); kb[1][2] = Math.max(kb[1][2], cx0 + x);
                            kb[1][1] = Math.min(kb[1][1], cy0 + y); kb[1][3] = Math.max(kb[1][3], cy0 + y + 1);
                        }
                        ext[0] = Math.min(ext[0], cx0 + start); ext[2] = Math.max(ext[2], cx0 + x);
                        ext[1] = Math.min(ext[1], cy0 + y); ext[3] = Math.max(ext[3], cy0 + y + 1);
                    }
                    run = c; start = x;
                }
            }
        }
        var g = {
            x0: cx0, y0: cy0, w: W, h: H, spans: spans,
            core: kb[1],                                    // the core's bbox, in cells
            ext: ext,                                       // the outline's bbox, in cells
            rects: [],                                      // see rectsOf()
            cls: cls                                        // the classes, W x H (good while this is the goo)
        };
        // keep this core for the next frame to compare against (the two mask
        // buffers swap, so neither is copied)
        var keep = memo.mask;
        memo.mask = mask; B.mask = keep; memo.tiles = tiles;
        memo.g = rings.length ? null : g;
        memo.x0 = cx0; memo.y0 = cy0; memo.w = W; memo.h = H; memo.gpu = GPU;
        return g;
    }

    /* A class's runs as rectangles: each row's runs, merged down the rows
     * wherever a run repeats exactly. [x0, y0, x1, y1] in cells, document
     * space. Worked out once a frame and shared by every panel and every copy:
     * a round head is a few dozen of these rather than a hundred runs. */
    function rectsOf(g, k) {
        if (g.rects[k]) return g.rects[k];
        var rows = g.spans[k], out = [], prev = [], prevAt = [];
        for (var y = 0; y < g.h; y++) {
            var cur = rows[y] || [], curAt = [], p = 0;
            for (var i = 0; i < cur.length; i += 2) {
                var a = cur[i], b = cur[i + 1];
                while (p < prev.length && prev[p] < a) p += 2;
                if (p < prev.length && prev[p] === a && prev[p + 1] === b) {
                    out[prevAt[p >> 1]][3] = g.y0 + y + 1;
                    curAt.push(prevAt[p >> 1]);
                } else {
                    curAt.push(out.length);
                    out.push([a, g.y0 + y, b, g.y0 + y + 1]);
                }
            }
            prev = cur; prevAt = curAt;
        }
        return (g.rects[k] = out);
    }

    /* ── the goo as a picture, one pixel a cell ──────────────────────────────
     * Every edge of the goo and of its outline is a cell edge, so the whole of
     * it is a small picture: one pixel per 6px cell, shown at six times its
     * size with the scaling kept hard (image-rendering: pixelated). For the GPU
     * that is one small upload and one textured square a frame — drawn as
     * shapes, the same goo was a thousand anti-aliased rectangles, and a
     * millisecond or two of the GPU's frame.
     *
     * One picture per scheme, shared by every panel of it; the buffer is the
     * size of the largest goo the window can hold, and only its top-left g.w ×
     * g.h is in use — everything past that is kept clear. */
    var picture = { w: 0, h: 0, bufs: {} };
    function rgbaOf(css) {
        if (css.charAt(0) === '#') { var c = rgbOf(css); return [c[0], c[1], c[2], 255]; }
        var m = css.match(/[\d.]+/g).map(Number);
        return [m[0], m[1], m[2], m.length > 3 ? Math.round(m[3] * 255) : 255];
    }
    function packed(c) { return ((c[3] << 24) | (c[2] << 16) | (c[1] << 8) | c[0]) >>> 0; }   // RGBA, little-endian
    function cellPicture(g, scheme, core, halo) {
        var b = picture.bufs[scheme];
        if (!b || b.img.width !== picture.w || b.img.height !== picture.h) {
            var img = new ImageData(picture.w, picture.h);
            b = picture.bufs[scheme] = {
                img: img, u32: new Uint32Array(img.data.buffer), g: null, w: 0, h: 0,
                cols: [0, packed(rgbaOf(core)), packed(rgbaOf(halo[0])), packed(rgbaOf(halo[1])), packed(rgbaOf(halo[2]))]
            };
        }
        if (b.g === g) return b;
        var BW = picture.w, u = b.u32, y, i, k;
        /* The last picture, gone: its runs painted out — not the whole box it
           sat in, which with drops all over the window is all of it. */
        var pg = b.g;
        if (pg) {
            for (k = 1; k <= 4; k++) {
                var prow = pg.spans[k];
                for (y = 0; y < b.h; y++) {
                    var pr = prow[y];
                    if (!pr) continue;
                    for (i = 0; i < pr.length; i += 2) {
                        var pa = pr[i] - pg.x0, pe = Math.min(pr[i + 1] - pg.x0, b.w);
                        if (pa < pe) u.fill(0, y * BW + pa, y * BW + pe);
                    }
                }
            }
        } else for (y = 0; y < b.h; y++) u.fill(0, y * BW, y * BW + b.w);
        var W = Math.min(g.w, BW), H = Math.min(g.h, picture.h);
        for (var k = 1; k <= 4; k++) {
            var rows = g.spans[k], col = b.cols[k];
            for (y = 0; y < H; y++) {
                var r = rows[y];
                if (!r) continue;
                for (i = 0; i < r.length; i += 2) {
                    var a = r[i] - g.x0, e = Math.min(r[i + 1] - g.x0, W);
                    if (a < e) u.fill(col, y * BW + a, y * BW + e);
                }
            }
        }
        // into the scheme's own canvas, which each panel copies from — the part
        // in use now, and whatever the last picture used beyond it
        if (!b.canvas || b.canvas.width !== BW || b.canvas.height !== picture.h) {
            b.canvas = document.createElement('canvas');
            b.canvas.width = BW; b.canvas.height = picture.h;
            b.ctx = b.canvas.getContext('2d');
            b.pw = b.ph = 0;
        }
        b.ctx.putImageData(b.img, 0, 0, 0, 0, Math.max(W, b.pw || 0, b.w), Math.max(H, b.ph || 0, b.h));
        b.g = g; b.w = W; b.h = H; b.pw = W; b.ph = H;
        return b;
    }

    /* The core as a clip-path for one copy, in the copy's own px: the core's
     * rectangles that come within `pad` of it. Null when none does — then the
     * copy is hidden outright. */
    function clipFor(g, x0, y0, w, h, pad) {
        var xlo = (x0 - pad) / Q, xhi = (x0 + w + pad) / Q, ylo = (y0 - pad) / Q, yhi = (y0 + h + pad) / Q;
        var cb = g.core;
        if (cb[2] < xlo || cb[0] > xhi || cb[3] < ylo || cb[1] > yhi) return null;
        var rs = rectsOf(g, 1), out = '';
        for (var i = 0; i < rs.length; i++) {
            var R = rs[i];
            if (R[2] < xlo || R[0] > xhi || R[3] < ylo || R[1] > yhi) continue;
            var l = R[0] * Q - x0, r = R[2] * Q - x0;
            out += 'M' + l + ' ' + (R[1] * Q - y0) + 'H' + r + 'V' + (R[3] * Q - y0) + 'H' + l + 'Z';
        }
        return out ? 'path("' + out + '")' : null;
    }

    /* ── THE GOO ON THE GPU ───────────────────────────────────────────────────
     * The page is one ground now — every lit section transparent, the dot
     * field under all of it — so the goo needs no canvas in each section: ONE
     * canvas, just above the page background's and under all of the content,
     * from the top of the first lit section to the foot of the last, never
     * more than about a window and a half tall. It is IN the page, not fixed,
     * so it scrolls with the type it lights, exactly.
     *
     * Each frame the cells' classes (the core, the three rings, the ripples)
     * go up as a picture one byte a cell, and the GPU paints every pixel from
     * it: a ring's colour, or the core's blue with the field's dots in it —
     * the same dots, placed the same way as the page background's on the GPU
     * (assets/wave-bg.js), cut at the core's edge cell by cell. The page itself
     * draws nothing per cell or per dot. Null where there is no GPU (each
     * section then draws its share in 2D, see Panel.paint).
     *
     * `host`: for the loading screen's white layer (see THE LOADING SCREEN) —
     * an element that covers the window and stands still in it. The canvas
     * then goes in there and IS the window: the view's corner on the page is
     * its top-left, and it is drawn over all of the window, lit or not. */
    function gooLayer(host) {
        if (window.FC_SOFTWARE) return null;
        var cv = document.createElement('canvas');
        cv.className = 'fc-spot-goo';
        cv.setAttribute('aria-hidden', 'true');
        var cs = cv.style;
        cs.position = 'absolute'; cs.left = '0'; cs.top = '0';
        // in the page: over the background's dots and under all of the content;
        // in a host, in the host's own order (over its dots, under its type)
        cs.zIndex = host ? '0' : '-1';
        cs.pointerEvents = 'none'; cs.display = 'block'; cs.margin = '0';
        cs.maxWidth = 'none'; cs.maxHeight = 'none'; cs.width = '0'; cs.height = '0';
        var gl = null;
        try {
            gl = cv.getContext('webgl2', {
                alpha: true, premultipliedAlpha: true, antialias: false, depth: false, stencil: false,
                preserveDrawingBuffer: false, failIfMajorPerformanceCaveat: true
            });
        } catch (e) { gl = null; }
        if (!gl) return null;
        var VS = '#version 300 es\n'
            + 'void main() {\n'                                   // one triangle over the whole canvas
            + '  vec2 c = vec2(float((gl_VertexID << 1) & 2), float(gl_VertexID & 2));\n'
            + '  gl_Position = vec4(c * 2.0 - 1.0, 0.0, 1.0);\n'
            + '}\n';
        var FS = '#version 300 es\n'
            + 'precision highp float; precision highp int;\n'
            + 'uniform highp sampler2D cells;\n'                  // R8: the goo's class a cell (0 none, 1 core, 2..4 rings)
            + 'uniform highp sampler2D field;\n'                  // RGBA32F: row 0 per column (x, flat, sin, cos), row 1 per row (y, sin, cos)
            + 'uniform vec2 size, anchor, win;\n'                 // canvas px; its top-left in page CSS px; and in window units
            + 'uniform vec4 gbox;\n'                              // the class picture's first cell (x, y) and its cells across, down
            + 'uniform float sk, dpr, q, pitch, colM, rowM, amp3, hd;\n'
            + 'uniform int nc, nr, dots;\n'
            + 'uniform vec4 cCore, cH2, cH3, cH4, cDot;\n'        // premultiplied
            + 'out vec4 o;\n'
            + 'int classAt(vec2 d) {\n'                           // d: page CSS px
            + '  vec2 t = floor(d / q) - gbox.xy;\n'
            + '  if (t.x < 0.0 || t.y < 0.0 || t.x >= gbox.z || t.y >= gbox.w) return 0;\n'
            + '  return int(texelFetch(cells, ivec2(t), 0).r * 255.0 + 0.5);\n'
            + '}\n'
            + 'float ov(float a0, float a1, float b0, float b1) { return max(0.0, min(a1, b1) - max(a0, b0)); }\n'
            + 'float bar(vec4 r, vec2 a, vec2 b) {\n'             // r: canvas px; a, b: window units
            + '  vec2 A = (a - win) * sk, B = (b - win) * sk;\n'
            + '  return ov(r.x, r.z, A.x, B.x) * ov(r.y, r.w, A.y, B.y);\n'
            + '}\n'
            + 'float dotsIn(vec4 r) {\n'                          // how much of r the field's dots cover
            + '  vec2 c = (r.xy + r.zw) * 0.5 / sk + win;\n'      // its middle, window units
            + '  int ci = int(clamp(floor((c.x - colM) / pitch + 0.5), 0.0, float(nc - 1)));\n'
            + '  vec4 cl = texelFetch(field, ivec2(ci, 0), 0);\n'
            + '  float reach = 40.0 * dpr;\n'                      // the waves' 34 CSS px and a dot
            + '  int j0 = int(max(0.0, floor((c.y - rowM - reach) / pitch)));\n'
            + '  int j1 = int(min(float(nr - 1), ceil((c.y - rowM + reach) / pitch)));\n'
            + '  float cov = 0.0, u = dpr;\n'
            + '  for (int j = j0; j <= j1; j++) {\n'
            + '    vec4 rw = texelFetch(field, ivec2(j, 1), 0);\n'
            + '    float dy = cl.y + amp3 * (cl.z * rw.z + cl.w * rw.y);\n'
            + '    vec2 tl = vec2(cl.x - hd, rw.x + floor(dy * u + 0.5) - hd);\n'
            + '    cov += bar(r, tl + vec2(u, 0.0), tl + vec2(5.0 * u, u))\n'
            + '         + bar(r, tl + vec2(0.0, u), tl + vec2(6.0 * u, 5.0 * u))\n'
            + '         + bar(r, tl + vec2(u, 5.0 * u), tl + vec2(5.0 * u, 6.0 * u));\n'
            + '  }\n'
            + '  return cov;\n'
            + '}\n'
            + 'void main() {\n'
            + '  vec2 p = vec2(floor(gl_FragCoord.x), size.y - 1.0 - floor(gl_FragCoord.y));\n'
            + '  float k = 1.0 / (sk * dpr);\n'                    // canvas px to CSS px
            + '  vec2 d0 = anchor + p * k, d1 = anchor + (p + 1.0) * k;\n'
            + '  int base = classAt((d0 + d1) * 0.5);\n'          // the cell under the pixel\'s middle
            + '  float cov = 0.0;\n'
            + '  if (dots == 1) {\n'
            + '    // the dots, over the CORE only: the pixel split where a cell edge\n'
            + '    // crosses it (a cell is wider than a pixel: one edge each way at most)\n'
            + '    vec2 e = ceil(d0 / q) * q;\n'
            + '    float xs = (e.x > d0.x && e.x < d1.x) ? e.x : d1.x;\n'
            + '    float ys = (e.y > d0.y && e.y < d1.y) ? e.y : d1.y;\n'
            + '    for (int a = 0; a < 2; a++) for (int b = 0; b < 2; b++) {\n'
            + '      float x0 = a == 0 ? d0.x : xs, x1 = a == 0 ? xs : d1.x;\n'
            + '      float y0 = b == 0 ? d0.y : ys, y1 = b == 0 ? ys : d1.y;\n'
            + '      if (x1 <= x0 || y1 <= y0) continue;\n'
            + '      if (classAt(vec2(x0 + x1, y0 + y1) * 0.5) != 1) continue;\n'
            + '      cov += dotsIn(vec4((vec2(x0, y0) - anchor) / k, (vec2(x1, y1) - anchor) / k));\n'
            + '    }\n'
            + '  }\n'
            + '  if (base == 0 && cov <= 0.0) discard;\n'
            + '  vec4 bg = base == 1 ? cCore : base == 2 ? cH2 : base == 3 ? cH3 : base == 4 ? cH4 : vec4(0.0);\n'
            + '  float c1 = min(cov, 1.0);\n'
            + '  o = bg * (1.0 - c1) + cDot * c1;\n'                // the dot over the cell's colour
            + '}\n';
        /* THE RINGS, at the cells' own resolution, in two passes over the
         * picture of the core (1) and the ripples (2..4): first each cell's
         * distance up or down its column to the nearest core cell (up to the
         * outline's reach), then across its row the least dx² + dy² — the exact
         * squared distance to the core wherever it is within reach, which is
         * what decides the three rings. A ripple's band is kept where it is the
         * stronger, as on the CPU (see fieldCells). */
        var RR = RINGS * RING_CELLS, L1 = RING_CELLS * RING_CELLS;
        var FS_V = '#version 300 es\n'
            + 'precision highp float; precision highp int;\n'
            + 'uniform highp sampler2D src; uniform ivec2 dim; out vec4 o;\n'
            + 'void main() {\n'
            + '  ivec2 c = ivec2(gl_FragCoord.xy); int best = ' + (RR + 1) + ';\n'
            + '  for (int d = -' + RR + '; d <= ' + RR + '; d++) {\n'
            + '    int y = c.y + d;\n'
            + '    if (y < 0 || y >= dim.y) continue;\n'
            + '    if (int(texelFetch(src, ivec2(c.x, y), 0).r * 255.0 + 0.5) == 1) best = min(best, abs(d));\n'
            + '  }\n'
            + '  o = vec4(float(best) / 255.0, 0.0, 0.0, 1.0);\n'
            + '}\n';
        var FS_H = '#version 300 es\n'
            + 'precision highp float; precision highp int;\n'
            + 'uniform highp sampler2D src, vdist; uniform ivec2 dim; out vec4 o;\n'
            + 'void main() {\n'
            + '  ivec2 c = ivec2(gl_FragCoord.xy);\n'
            + '  int m = int(texelFetch(src, c, 0).r * 255.0 + 0.5), k = 0;\n'
            + '  if (m == 1) k = 1;\n'
            + '  else {\n'
            + '    int d2 = 100000;\n'
            + '    for (int d = -' + RR + '; d <= ' + RR + '; d++) {\n'
            + '      int x = c.x + d;\n'
            + '      if (x < 0 || x >= dim.x) continue;\n'
            + '      int v = int(texelFetch(vdist, ivec2(x, c.y), 0).r * 255.0 + 0.5);\n'
            + '      if (v <= ' + RR + ') d2 = min(d2, d * d + v * v);\n'
            + '    }\n'
            + '    k = d2 <= ' + L1 + ' ? 2 : d2 <= ' + (4 * L1) + ' ? 3 : d2 <= ' + (RR * RR) + ' ? 4 : 0;\n'
            + '    if (m >= 2) k = k == 0 ? m : min(k, m);\n'
            + '  }\n'
            + '  o = vec4(float(k) / 255.0, 0.0, 0.0, 1.0);\n'
            + '}\n';
        function sh(type, src) { var x = gl.createShader(type); gl.shaderSource(x, src); gl.compileShader(x); return x; }
        var P = null, PV = null, PH = null, U = {}, UV = {}, UH = {}, texCells = null, texField = null, texV = null, texK = null;
        var fbV = null, fbK = null, vao = null;
        function program(fs) {
            var pr = gl.createProgram();
            gl.attachShader(pr, sh(gl.VERTEX_SHADER, VS));
            gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs));
            gl.linkProgram(pr);
            if (gl.getProgramParameter(pr, gl.LINK_STATUS)) return pr;
            if (window.console) console.warn('spotlight-dots: goo shader', gl.getProgramInfoLog(pr));
            return null;
        }
        function build() {
            P = program(FS); PV = program(FS_V); PH = program(FS_H);
            if (!P || !PV || !PH) return false;
            ['cells', 'field', 'size', 'anchor', 'win', 'gbox', 'sk', 'dpr', 'q', 'pitch', 'colM', 'rowM', 'amp3', 'hd',
             'nc', 'nr', 'dots', 'cCore', 'cH2', 'cH3', 'cH4', 'cDot'].forEach(function (n) { U[n] = gl.getUniformLocation(P, n); });
            ['src', 'dim'].forEach(function (n) { UV[n] = gl.getUniformLocation(PV, n); });
            ['src', 'vdist', 'dim'].forEach(function (n) { UH[n] = gl.getUniformLocation(PH, n); });
            vao = gl.createVertexArray();
            function tex() {
                var t = gl.createTexture();
                gl.bindTexture(gl.TEXTURE_2D, t);
                gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
                gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
                gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
                gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
                return t;
            }
            texCells = tex(); texField = tex(); texV = tex(); texK = tex();
            fbV = gl.createFramebuffer(); fbK = gl.createFramebuffer();
            st.cw = st.ch = st.fw = 0; st.g = null; st.fkey = '';
            gl.disable(gl.BLEND);
            return true;
        }
        // the three cell-sized pictures, grown together; the two the passes write into
        function cellTextures(w, h) {
            [texCells, texV, texK].forEach(function (t) {
                gl.bindTexture(gl.TEXTURE_2D, t);
                gl.texImage2D(gl.TEXTURE_2D, 0, gl.R8, w, h, 0, gl.RED, gl.UNSIGNED_BYTE, null);
            });
            gl.bindFramebuffer(gl.FRAMEBUFFER, fbV);
            gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texV, 0);
            gl.bindFramebuffer(gl.FRAMEBUFFER, fbK);
            gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texK, 0);
            gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        }
        // the rings, into texK (see FS_V, FS_H)
        function rings(g) {
            gl.disable(gl.SCISSOR_TEST);
            gl.viewport(0, 0, g.w, g.h);
            gl.bindFramebuffer(gl.FRAMEBUFFER, fbV);
            gl.useProgram(PV);
            gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, texCells);
            gl.uniform1i(UV.src, 0); gl.uniform2i(UV.dim, g.w, g.h);
            gl.drawArrays(gl.TRIANGLES, 0, 3);
            gl.bindFramebuffer(gl.FRAMEBUFFER, fbK);
            gl.useProgram(PH);
            gl.activeTexture(gl.TEXTURE2); gl.bindTexture(gl.TEXTURE_2D, texV);
            gl.uniform1i(UH.src, 0); gl.uniform1i(UH.vdist, 2); gl.uniform2i(UH.dim, g.w, g.h);
            gl.drawArrays(gl.TRIANGLES, 0, 3);
            gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        }
        // left, top: the canvas's top-left on the page, CSS px
        var st = { lost: false, drawn: false, g: null, fkey: '', cw: 0, ch: 0, fw: 0, key: '', left: 0, top: NaN, H: 0, W: 0, bw: 0, bh: 0 };
        // a context this could not build in is let go of, not left holding a GPU slot
        function release() {
            var x = gl.getExtension('WEBGL_lose_context');
            if (x) x.loseContext();
        }
        if (!build()) { release(); return null; }
        /* A lost context (a GPU reset, a driver update) has nothing on it: the
           goo is drawn by the sections in 2D meanwhile (see GPU), and back here
           once it is restored and built again. */
        cv.addEventListener('webglcontextlost', function (e) {
            e.preventDefault();
            st.lost = true; st.drawn = false; st.g = null; st.key = '';
        });
        cv.addEventListener('webglcontextrestored', function () {
            st.lost = !build(); st.key = ''; st.g = null; st.drawn = false;
        });
        var fieldData = null;
        function premul(css) { var c = rgbaOf(css), a = c[3] / 255; return [c[0] / 255 * a, c[1] / 255 * a, c[2] / 255 * a, a]; }
        var CORE = premul(BLUE_GROUND), H2 = premul(HALO.paper[0]), H3 = premul(HALO.paper[1]), H4 = premul(HALO.paper[2]), DOTC = premul(BLUE_DOT);

        function place(o) {
            if (host) {
                /* The window, exactly, wherever the page is scrolled: sized
                   afresh only when the window is, moved (drawn afresh) when the
                   view moves on the page. */
                if (cv.parentNode !== host) host.appendChild(cv);
                var shifted = st.left !== o.sx || st.top !== o.sy;
                st.left = o.sx; st.top = o.sy;
                if (st.W === o.vw && st.H === o.vh && st.dpr === o.dpr && st.sk === o.sk) return shifted;
                st.W = o.vw; st.H = o.vh; st.dpr = o.dpr; st.sk = o.sk;
                cs.width = o.vw + 'px'; cs.height = o.vh + 'px';
                st.bw = Math.max(1, Math.round(o.vw * o.dpr * o.sk)); st.bh = Math.max(1, Math.round(o.vh * o.dpr * o.sk));
                cv.width = st.bw; cv.height = st.bh;
                return true;
            }
            // over the page background's canvas (tree order decides between the two)
            var wc = document.getElementById('fc-waves-canvas');
            if (wc && wc.parentNode && cv.previousElementSibling !== wc) wc.parentNode.insertBefore(cv, wc.nextSibling);
            else if (!cv.parentNode) document.body.appendChild(cv);
            /* A band of the page about a window and a half tall round the view, on
             * the cells' lattice, cut to the lit sections (a canvas running past
             * the last of them would lengthen the page). Moved only when the view
             * nears its edge. */
            var lo = o.top0, hi = o.bottom;
            var needTop = Math.max(lo, o.sy - 0.05 * o.vh), needBottom = Math.min(hi, o.sy + 1.05 * o.vh);
            var covered = st.top === st.top && st.top <= needTop && st.top + st.H >= needBottom;
            if (covered && st.W === o.vw && st.dpr === o.dpr && st.sk === o.sk && st.lo === lo && st.hi === hi) return false;
            var top = Math.max(lo, Math.floor((o.sy - 0.3 * o.vh) / Q) * Q);
            var H = Math.max(1, Math.min(Math.ceil(1.6 * o.vh), hi - top));
            st.top = top; st.H = H; st.W = o.vw; st.dpr = o.dpr; st.sk = o.sk; st.lo = lo; st.hi = hi;
            cs.top = top + 'px'; cs.width = o.vw + 'px'; cs.height = H + 'px';
            st.bw = Math.max(1, Math.round(o.vw * o.dpr * o.sk)); st.bh = Math.max(1, Math.round(H * o.dpr * o.sk));
            cv.width = st.bw; cv.height = st.bh;
            return true;
        }

        return {
            canvas: cv,
            drawn: function () { return st.drawn; },
            // can it draw this frame? (not with its context lost)
            ok: function () { return !st.lost && !gl.isContextLost(); },
            /* draw the goo g (null: none) for the field f, the view o:
               { sx, sy, vw, vh, dpr, sk, top0, bottom } */
            draw: function (g, f, o) {
                if (st.lost || gl.isContextLost()) { st.drawn = false; st.g = null; st.key = ''; return; }
                if (!g) {
                    if (st.drawn) {
                        gl.viewport(0, 0, cv.width, cv.height);
                        gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT);
                        st.drawn = false; st.key = '';
                    }
                    st.g = null;
                    return;
                }
                var moved = place(o);
                var hasCore = g.core[2] > g.core[0];
                var wx = Math.round((st.left - o.sx) * o.dpr), wy = Math.round((st.top - o.sy) * o.dpr);
                /* the field's instant AND its grid: the background draws at
                   most 60 times a second, so on a faster screen two frames share
                   an instant — and with reduced motion every frame does — and a
                   resize between them is a new grid all the same */
                var fkey = f.now + ',' + f.dpr + ',' + f.w + ',' + f.h;
                var key = st.left + ',' + st.top + ',' + st.H + ',' + wx + ',' + wy + (hasCore ? ',' + fkey : '');
                if (!moved && g === st.g && key === st.key) return;
                gl.bindVertexArray(vao);
                /* the core and the ripples, a byte a cell, the goo's box at the
                   texture's corner — and from them the rings, on the GPU */
                if (g !== st.g) {
                    if (g.w > st.cw || g.h > st.ch) {
                        st.cw = Math.max(g.w, Math.ceil(st.cw * 1.25)); st.ch = Math.max(g.h, Math.ceil(st.ch * 1.25));
                        cellTextures(st.cw, st.ch);
                    }
                    gl.activeTexture(gl.TEXTURE0);
                    gl.bindTexture(gl.TEXTURE_2D, texCells);
                    gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
                    gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, g.w, g.h, gl.RED, gl.UNSIGNED_BYTE, g.cls, 0);
                    rings(g);
                    st.g = g;
                }
                gl.useProgram(P);
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, texK);
                // the field's terms (the dots in the core), when the field moved
                gl.activeTexture(gl.TEXTURE1);
                gl.bindTexture(gl.TEXTURE_2D, texField);
                var nc = f.cols.length, nr = f.rows.length;
                if (hasCore && fkey !== st.fkey) {
                    var w = Math.max(nc, nr), i, k2;
                    if (!fieldData || fieldData.length < w * 8) fieldData = new Float32Array(w * 8);
                    for (i = 0; i < nc; i++) { k2 = 4 * i; fieldData[k2] = f.cols[i]; fieldData[k2 + 1] = f.flat[i]; fieldData[k2 + 2] = f.cS[i]; fieldData[k2 + 3] = f.cC[i]; }
                    for (i = 0; i < nr; i++) { k2 = 4 * (w + i); fieldData[k2] = f.rows[i]; fieldData[k2 + 1] = f.rowSin[i]; fieldData[k2 + 2] = f.rowCos[i]; fieldData[k2 + 3] = 0; }
                    if (w !== st.fw) { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA32F, w, 2, 0, gl.RGBA, gl.FLOAT, fieldData, 0); st.fw = w; }
                    else gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, w, 2, gl.RGBA, gl.FLOAT, fieldData, 0);
                    st.fkey = fkey;
                }
                gl.uniform1i(U.cells, 0); gl.uniform1i(U.field, 1);
                gl.uniform2f(U.size, cv.width, cv.height);
                gl.uniform2f(U.anchor, st.left, st.top);
                gl.uniform2f(U.win, wx, wy);
                gl.uniform4f(U.gbox, g.x0, g.y0, g.w, g.h);
                gl.uniform1f(U.sk, o.sk); gl.uniform1f(U.dpr, o.dpr); gl.uniform1f(U.q, Q);
                gl.uniform1f(U.pitch, PITCH * o.dpr);
                gl.uniform1f(U.colM, nc ? f.cols[0] : 0); gl.uniform1f(U.rowM, nr ? f.rows[0] : 0);
                gl.uniform1f(U.amp3, AMP_3); gl.uniform1f(U.hd, Math.round(DOT * o.dpr / 2));
                gl.uniform1i(U.nc, nc); gl.uniform1i(U.nr, nr); gl.uniform1i(U.dots, hasCore && nc && nr ? 1 : 0);
                gl.uniform4fv(U.cCore, CORE); gl.uniform4fv(U.cH2, H2); gl.uniform4fv(U.cH3, H3); gl.uniform4fv(U.cH4, H4);
                gl.uniform4fv(U.cDot, DOTC);
                gl.viewport(0, 0, cv.width, cv.height);
                gl.disable(gl.SCISSOR_TEST);
                gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT);
                // only the pixels the goo's outline box can touch are worked out
                var e = g.ext, kx = o.dpr * o.sk;
                var x0 = Math.floor((e[0] * Q - st.left) * kx) - 1, x1 = Math.ceil((e[2] * Q - st.left) * kx) + 1;
                var y0 = Math.floor((e[1] * Q - st.top) * kx) - 1, y1 = Math.ceil((e[3] * Q - st.top) * kx) + 1;
                x0 = Math.max(0, x0); y0 = Math.max(0, y0); x1 = Math.min(cv.width, x1); y1 = Math.min(cv.height, y1);
                if (x1 > x0 && y1 > y0) {
                    gl.enable(gl.SCISSOR_TEST);
                    gl.scissor(x0, cv.height - y1, x1 - x0, y1 - y0);   // GL counts rows from the bottom
                    gl.drawArrays(gl.TRIANGLES, 0, 3);
                    gl.disable(gl.SCISSOR_TEST);
                }
                st.key = key; st.drawn = true;
            },
            /* done with (a host's, when the loading screen's layer has gone): the
               GPU slot given back and the canvas emptied — left where it is, so
               nothing is taken out of the page in the middle of a swipe */
            destroy: function () {
                st.lost = true; st.drawn = false; st.g = null; st.key = '';
                release();
                cv.width = 0; cv.height = 0;
            }
        };
    }

    /* ── the blue panels' ground ─────────────────────────────────────────────
     * A blue panel's dots are the page background's own field (wave-bg.js) in
     * blue: the same columns and rows, on the same clock. They are drawn on a
     * canvas the WINDOW's size, position: fixed, inside a wrapper clipped to
     * the section — fixed to the window exactly as the background is, so under
     * a scroll they stand still with the background's, dot for dot across every
     * seam, with nothing redrawn. And drawn the way the background is: only the
     * dots that moved to a new pixel this frame, a few dozen, each painted out
     * where it was and in where it is. A filter over the backdrop (as before)
     * cost the GPU a pass a frame, and a machine with no GPU a frame's worth of
     * work; this costs either almost nothing. Only a panel on screen is kept
     * up to date; one coming back on screen is drawn whole. */
    function rgbOf(hex) { return [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16); }); }

    function Ground(section) {
        this.wrap = document.createElement('div');
        this.wrap.className = 'fc-spot-ground';
        this.wrap.setAttribute('aria-hidden', 'true');
        this.canvas = document.createElement('canvas');
        var s = this.canvas.style;
        s.position = 'fixed'; s.left = '0'; s.top = '0'; s.display = 'block';
        s.maxWidth = 'none'; s.maxHeight = 'none'; s.margin = '0';
        this.wrap.appendChild(this.canvas);
        section.insertBefore(this.wrap, section.firstChild);
        // drawn by the GPU where there is one (assets/wave-bg.js), else in 2D
        this.layer = window.FC_DOT_LAYER ? window.FC_DOT_LAYER(this.canvas, BLUE_GROUND) : null;
        if (!this.layer && this.canvas.fcTainted) {
            // the GPU path failed after taking the canvas: a fresh one for the 2D
            var fresh = this.canvas.cloneNode(false);
            this.wrap.replaceChild(fresh, this.canvas);
            this.canvas = fresh;
        }
        this.ctx = this.layer ? null : this.canvas.getContext('2d', { alpha: false });
        this.w = 0; this.h = 0; this.dpr = 0;
        this.shownY = null;         // where each dot is drawn now, device px
        this.full = true;           // the canvas has to be drawn whole
    }
    /* `top` and `bottom`: the section's edges in the window, CSS px. */
    Ground.prototype.update = function (f, on, top, bottom) {
        if (!on) { this.full = true; return; }          // drawn whole when it comes back
        /* Zoomed out (a ratio below 1) the canvas is drawn at the screen's own
         * resolution, through a scale, as the background's is (wave-bg.js): the
         * same dots in the same units, without drawing the window at several
         * times the pixels the screen shows for the browser to shrink. */
        var sk = Math.min(1, window.devicePixelRatio || 1);
        var dpr = f.dpr, W = f.w, H = f.h, cv = this.canvas, ctx = this.ctx;
        if (W !== this.w || H !== this.h || dpr !== this.dpr || sk !== this.sk) {
            cv.width = Math.max(1, Math.round(W * sk)); cv.height = Math.max(1, Math.round(H * sk));
            cv.style.width = W / dpr + 'px'; cv.style.height = H / dpr + 'px';
            if (ctx) ctx.setTransform(sk, 0, 0, sk, 0, 0);
            this.w = W; this.h = H; this.dpr = dpr; this.sk = sk;
            this.full = true;
        }
        /* ON THE GPU: the whole window's dots, every time any of them moved, in
         * one draw — the same as the background's (see dotLayer in
         * assets/wave-bg.js) — and nothing at all when none did. */
        if (this.layer) {
            if (!this.full && f.now === this.at && !this.layer.blank()) return;
            this.at = f.now;
            var fd = fieldDots(f);
            if (!this.full && fd.stamp === this.stamp && !this.layer.blank()) return;
            this.full = !this.layer.draw(fd.pos, fd.n, BLUE_DOT, sk, dpr);
            this.stamp = fd.stamp;
            return;
        }
        /* ONLY THE BAND THE SECTION SHOWS. The canvas is the window's size, and
         * the section's clip shows only the part of it the section covers: at
         * the foot of the page a strip a few rows tall, and zoomed out a part
         * of a window that holds tens of thousands of dots, a third of them
         * moving every frame. Kept up to date across the whole window, every
         * blue section on screen redrew all of them each frame, and zoomed out
         * that was more than a GPU could keep up with. The canvas is kept
         * exact over the rows the section shows, with a margin above and below
         * for a scroll to run into before the next frame, in steps so a scroll
         * does not move it every frame; when it moves, it is drawn whole, as
         * one shape, exactly as the whole canvas used to be. The rest of the
         * canvas is never seen and is left as it is.
         * [b0, b1): the band, in the canvas's own pixel rows. */
        var CH = cv.height, STEP = 96;
        var b0 = Math.max(0, Math.floor((top * dpr * sk - STEP) / STEP) * STEP);
        var b1 = Math.min(CH, Math.ceil((bottom * dpr * sk + STEP) / STEP) * STEP);
        if (b1 <= b0) return;
        if (!this.full && f.now === this.at && b0 === this.b0 && b1 === this.b1) return;
        this.at = f.now;
        // shrunk, a dot's soft edge reaches into the next screen pixel: painted
        // out that much wider (the nearest other dot is 30 units off)
        var e = sk < 1 ? Math.ceil(1 / sk) : 0;
        var nc = f.cols.length, nr = f.rows.length, n = nc * nr;
        if (!this.shownY || this.shownY.length !== n) { this.shownY = new Float64Array(n); this.full = true; }
        var shown = this.shownY;
        var u = dpr, half = Math.round(DOT * dpr / 2), s = DOT * dpr;
        // how far from its row's line a dot, painted out and all, can reach (units)
        var stray = (AMP_1 + AMP_2 + AMP_3 + 1) * dpr + s + e + 1;
        function rowsOf(a, c) {         // the rows whose dots can touch pixel rows [a, c)
            var lo = a / sk - stray, hi = c / sk + stray, j0 = 0, j1;
            while (j0 < nr && f.rows[j0] < lo) j0++;
            for (j1 = j0; j1 < nr && f.rows[j1] <= hi; j1++);
            return [j0, j1];
        }
        // every dot of the rows that touch [a, c), where it is this frame
        function whole(a, c) {
            var rr = rowsOf(a, c), path = null;
            for (var j = rr[0]; j < rr[1]; j++) {
                var sb = f.rowSin[j], cb = f.rowCos[j], yb = f.rows[j];
                for (var i = 0, k = j * nc; i < nc; i++, k++) {
                    var y = yb + Math.round((f.flat[i] + AMP_3 * (f.cS[i] * cb + f.cC[i] * sb)) * dpr) - half;
                    shown[k] = y;
                    if (y + s < 0 || y > H) continue;
                    var x = f.cols[i] - half;
                    if (!path) path = new Path2D();
                    path.rect(x + u, y, u * 4, u);
                    path.rect(x, y + u, u * 6, u * 4);
                    path.rect(x + u, y + u * 5, u * 4, u);
                }
            }
            return path;
        }
        if (this.full || b0 !== this.b0 || b1 !== this.b1) {
            // the whole canvas plain ground, to its last pixel, and the band drawn whole
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.fillStyle = BLUE_GROUND; ctx.fillRect(0, 0, cv.width, CH);
            ctx.setTransform(sk, 0, 0, sk, 0, 0);
            var all = whole(b0, b1);
            if (all) { ctx.fillStyle = BLUE_DOT; ctx.fill(all); }
        } else {
            // the dots that moved to a new pixel, in the rows the band had
            var rr = rowsOf(this.b0, this.b1), out = null, dots = null;
            for (var j = rr[0]; j < rr[1]; j++) {
                var sb = f.rowSin[j], cb = f.rowCos[j], yb = f.rows[j];
                for (var i = 0, k = j * nc; i < nc; i++, k++) {
                    var dy = f.flat[i] + AMP_3 * (f.cS[i] * cb + f.cC[i] * sb);
                    var y = yb + Math.round(dy * dpr) - half;
                    if (y === shown[k]) continue;
                    var x = f.cols[i] - half;
                    (out || (out = new Path2D())).rect(x - e, shown[k] - e, s + 2 * e, s + 2 * e);   // where it was
                    shown[k] = y;
                    if (y + s < 0 || y > H) continue;
                    if (!dots) dots = new Path2D();
                    dots.rect(x + u, y, u * 4, u);
                    dots.rect(x, y + u, u * 6, u * 4);
                    dots.rect(x + u, y + u * 5, u * 4, u);
                }
            }
            if (out) { ctx.fillStyle = BLUE_GROUND; ctx.fill(out); }
            if (dots) { ctx.fillStyle = BLUE_DOT; ctx.fill(dots); }
        }
        this.b0 = b0; this.b1 = b1;
        this.full = false;
    };

    /* The portrait rings for the blue panels — the theme's own filter
     * (template-parts/partials/speaker-filters.php), flooded with this scheme's
     * colours instead of the dashboard's. On the blue: white, and the highlight
     * (--fc-blue-hi, see assets/site.css) while the pointer is on the portrait.
     * Inside the disc: blue, and orange. The colours are CSS, not attributes, so
     * the highlight follows the variable. */
    function ringDefs() {
        if (document.getElementById('fc-spot-rings')) return;
        var rings = { 'rest': '#FAFAF7', 'hot': 'var(--fc-blue-hi, #55FFFF)', 'in-rest': '#0033FF', 'in-hot': '#EE8101' };
        var html = '';
        for (var k in rings) {
            html += '<filter id="fc-spot-ring-' + k + '" x="-10%" y="-10%" width="120%" height="120%"'
                  + ' color-interpolation-filters="sRGB">'
                  + '<feGaussianBlur in="SourceAlpha" stdDeviation="3" result="spread"/>'
                  + '<feComponentTransfer in="spread" result="ring"><feFuncA type="linear" slope="60" intercept="-3.6"/></feComponentTransfer>'
                  + '<feFlood result="paint" style="flood-color:' + rings[k] + '"/>'
                  + '<feComposite in="paint" in2="ring" operator="in" result="outline"/>'
                  + '<feMerge><feMergeNode in="outline"/><feMergeNode in="SourceGraphic"/></feMerge>'
                  + '</filter>';
        }
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.id = 'fc-spot-rings';
        svg.setAttribute('width', '0');
        svg.setAttribute('height', '0');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
        svg.innerHTML = html;
        document.body.appendChild(svg);
    }

    /* ── :hover, re-issued as [data-fc-hov] ────────────────────────────────────────
     * Every rule with :hover in it, written again with [data-fc-hov] in its place, so
     * a copy can be put in its original's hover state by a class. @media,
     * @supports, @layer and nesting are kept, so each rule lands where its
     * original sits in the cascade.
     *
     * `var(--color-accent` is wrapped in `var(--fc-hover-accent, …)` on the way:
     * on the blue scheme the accent is the HIGHLIGHT colour and the hover colour
     * is set on its own (both #99D0DE now, but they are two decisions). Where
     * --fc-hover-accent is not set — inside a blue panel's white disc — the
     * site's own accent is used.
     *
     * This script's own rules are left alone: they are written for the copies
     * already. Sheets from another origin cannot be read and are skipped. */
    function wrapAccent(text) {
        var out = '', i = 0, key = 'var(--color-accent';
        for (;;) {
            var at = text.indexOf(key, i);
            if (at < 0) return out + text.slice(i);
            var depth = 0, j = at;
            for (; j < text.length; j++) {
                var ch = text.charAt(j);
                if (ch === '(') depth++;
                else if (ch === ')') { depth--; if (depth === 0) break; }
            }
            out += text.slice(i, at) + 'var(--fc-hover-accent, ' + text.slice(at, j + 1) + ')';
            i = j + 1;
        }
    }
    function hoverRule(rule, inHover) {
        if (rule.selectorText !== undefined) {
            var sel = rule.selectorText;
            if (/data-fc-spotlight|fc-spot-/.test(sel)) return '';
            var has = inHover || sel.indexOf(':hover') >= 0;
            var inner = '';
            if (rule.cssRules) for (var i = 0; i < rule.cssRules.length; i++) inner += hoverRule(rule.cssRules[i], has);
            var decls = has && rule.style ? rule.style.cssText : '';
            if (!decls && !inner) return '';
            return sel.replace(/:hover/g, '[data-fc-hov]') + '{' + decls + inner + '}';
        }
        if (rule.cssRules) {
            var head = rule.media ? '@media ' + rule.media.mediaText
                     : rule.conditionText !== undefined
                        ? (rule.containerName !== undefined || /Container/.test(rule.constructor && rule.constructor.name)
                            ? '@container ' : '@supports ') + rule.conditionText
                     : rule.name !== undefined ? '@layer ' + rule.name
                     : null;
            if (!head) return '';
            var body = '';
            for (var k = 0; k < rule.cssRules.length; k++) body += hoverRule(rule.cssRules[k], inHover);
            return body ? head + '{' + body + '}' : '';
        }
        // nested bare declarations (CSSNestedDeclarations)
        if (inHover && rule.style && rule.style.cssText) return rule.style.cssText;
        return '';
    }
    var hoverCount = -1;
    function buildHoverSheet() {
        var text = '', sheets = document.styleSheets;
        for (var s = 0; s < sheets.length; s++) {
            var sh = sheets[s], rules;
            if (sh.ownerNode && sh.ownerNode.id === 'fc-spot-hover') continue;
            try { rules = sh.cssRules; } catch (err) { continue; }
            if (!rules) continue;
            for (var r = 0; r < rules.length; r++) text += hoverRule(rules[r], false);
        }
        text = wrapAccent(text);
        if (text.length === hoverCount) return;
        hoverCount = text.length;
        var el = document.getElementById('fc-spot-hover');
        if (!el) {
            el = document.createElement('style');
            el.id = 'fc-spot-hover';
            document.head.appendChild(el);
        }
        el.textContent = text;
    }

    /* ── the inverted copies ─────────────────────────────────────────────── */

    function px(v) { return parseFloat(v) || 0; }
    function alphaOf(c) {
        if (!c || c === 'transparent') return 0;
        var m = c.match(/rgba?\(([^)]+)\)/);
        if (!m) return 1;
        var p = m[1].split(/[\s,\/]+/).filter(Boolean);
        return p.length > 3 ? parseFloat(p[3]) : 1;
    }
    function isPaperish(c) {
        var m = c && c.match(/rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/);
        return !!m && alphaOf(c) > 0.5 && +m[1] > 235 && +m[2] > 235 && +m[3] > 230;
    }

    /* The copy of an element, if it has one, inside its wrapper (see build): a
     * photo's wrapper sits beside it (an <img> holds no children), anything
     * else's inside it. */
    function isCopy(n) {
        return n.classList.contains('fc-spot-dup') || n.classList.contains('fc-spot-dupwrap');
    }
    function dupOf(el) {
        if (el.tagName === 'IMG') {
            var n = el.nextElementSibling;
            return n && n.classList.contains('fc-spot-dupwrap') ? n.firstElementChild : null;
        }
        for (var c = el.firstElementChild; c; c = c.nextElementSibling) {
            if (c.classList.contains('fc-spot-dupwrap')) return c.firstElementChild;
        }
        return null;
    }

    /* Take everything out of a clone that would make it act: ids, the data-fc-*
     * hooks every script binds by, focusability, frames and players. Elements are
     * REPLACED rather than removed, so a clone's children line up one for one
     * with its original's — the hover mapping walks them by position. */
    function scrub(root, inner) {
        var nested = root.querySelectorAll('.fc-spot-dup, .fc-spot-dupwrap');
        for (var i = nested.length - 1; i >= 0; i--) nested[i].parentNode.removeChild(nested[i]);
        var all = [root].concat([].slice.call(root.querySelectorAll('*')));
        for (i = 0; i < all.length; i++) {
            var n = all[i], t = n.tagName;
            if (t === 'IFRAME' || t === 'VIDEO' || t === 'CANVAS' || t === 'OBJECT') {
                var stub = document.createElement('div');
                stub.className = n.className && n.className.baseVal === undefined ? n.className : '';
                stub.setAttribute('style', n.getAttribute('style') || '');
                n.parentNode.replaceChild(stub, n);
                continue;
            }
            if (n.removeAttribute) {
                n.removeAttribute('id');
                var at = n.attributes;
                for (var k = at.length - 1; k >= 0; k--) {
                    var nm = at[k].name;
                    if (nm.indexOf('data-fc-') === 0 || nm === 'aria-label' || nm === 'aria-labelledby'
                        || nm === 'aria-describedby' || nm === 'autofocus') n.removeAttribute(nm);
                }
            }
            if (t === 'A' || t === 'BUTTON' || (n.hasAttribute && n.hasAttribute('tabindex'))) n.setAttribute('tabindex', '-1');
            if (t === 'IMG') n.removeAttribute('loading');
            if (t === 'SCRIPT') n.textContent = '';
            if (t === 'TEMPLATE') n.innerHTML = '';
        }
        // (a piece going INTO a copy is already inside an inert, hidden root)
        if (inner) return;
        root.setAttribute('aria-hidden', 'true');
        root.setAttribute('inert', '');
    }

    /* The copy is absolutely positioned, so nothing collapses through it. The
     * original is often an ordinary block, and then its first child's top
     * margin collapses THROUGH it and lands outside — but inside the copy the
     * same margin stays, and pushes the type down by that much (Sponsors: the
     * line under each button, 4px low). Take it off in the copy, down the whole
     * chain it collapses through. */
    function flowFirst(o) {
        for (var n = o.firstChild; n; n = n.nextSibling) {
            if (n.nodeType === 3) { if (/\S/.test(n.textContent)) return null; continue; }
            if (n.nodeType !== 1 || isCopy(n)) continue;
            var s = getComputedStyle(n);
            if (s.display === 'none' || s.position === 'absolute' || s.position === 'fixed' || s.cssFloat !== 'none') continue;
            return n;
        }
        return null;
    }
    function collapseFix(el, dup) {
        var o = el, d = dup;
        // a flex or grid item holds its children's margins itself
        var pd = el.parentElement ? getComputedStyle(el.parentElement).display : '';
        if (/flex|grid/.test(pd)) return;
        for (var depth = 0; depth < 6 && o && d; depth++) {
            var cs = getComputedStyle(o);
            if (!/^(block|list-item)$/.test(cs.display) || cs.overflowX !== 'visible' || cs.overflowY !== 'visible'
                || cs.cssFloat !== 'none' || /absolute|fixed/.test(cs.position)
                || /layout|paint|content|strict/.test(cs.contain || '')
                || px(cs.borderTopWidth) || px(cs.paddingTop)) return;
            var oc = flowFirst(o);
            if (!oc) return;
            var dc = d.firstElementChild;
            for (var i = indexIn(oc); dc && i > 0; i--) dc = dc.nextElementSibling;
            if (!dc || dc.tagName !== oc.tagName) return;
            var ccs = getComputedStyle(oc);
            if (/^inline/.test(ccs.display)) return;              // a line of type: nothing collapses
            if (px(ccs.marginTop)) dc.style.marginTop = '0';
            o = oc; d = dc;
        }
    }

    /* Build (or rebuild) the copy of one element.
     *
     * The clone keeps its classes — they are what give it the same font, padding
     * and line breaks — and its layout is written INLINE, because an inline
     * style beats any non-!important rule and nothing in the theme can then drop
     * it back into the flow. It goes in as the FIRST child, so structural rules
     * in the theme (Tailwind's space-y is `> :not(:last-child)`) still see the
     * original children exactly as before, and a z-index of 1 puts it over any
     * positioned children all the same. Margins go; padding and borders stay,
     * and it is pulled out over the original's border, so its content lands on
     * the original's pixel for pixel.
     *
     * It sits in a WRAPPER that takes the original's box (its padding box, for a
     * photo the photo box's), and the clip goes on the wrapper. Out of the
     * goo's reach a wrapper is clipped to nothing and its contents are not
     * rendered at all (content-visibility, see site.css); a photo in reach is
     * put on a layer of its own inside it, so a new clip each frame redraws the
     * clip's mask and not the photo with the blur its ring is made of (see
     * write). The wrapper's layout is all inline, against any rule for the
     * original's children (a title's lines are > span { display: block; width:
     * fit-content }). */
    function wrapper() {
        var w = document.createElement('fc-spot');
        w.className = 'fc-spot-dupwrap';
        w.setAttribute('aria-hidden', 'true');
        w.style.cssText = 'position:absolute;inset:0;display:block;width:auto;height:auto;margin:0;padding:0;border:0;'
            + 'max-width:none;min-width:0;transform:none;z-index:1;pointer-events:none';
        return w;
    }
    /* A LOGO'S SECOND VERSION. A community's or a sponsor's logo can come with
     * one for inside the highlight — a white one that reads on the blue — named
     * on the logo by the template (data-fc-in-src / -srcset). In the copy, which
     * is what shows inside the highlight, it takes the logo's place: same box,
     * same fit, same `sizes`. Done before the copy is scrubbed of data-fc-*,
     * and marked so a change to the original's src is not carried over it (see
     * patch). */
    function swapIn(root, from) {
        var imgs = [].slice.call(root.querySelectorAll('img[data-fc-in-src]'));
        if (root.matches && root.matches('img[data-fc-in-src]')) imgs.push(root);
        // the originals, in the same order, for how each logo sits in its box
        var orig = from ? [].slice.call(from.querySelectorAll('img[data-fc-in-src]')) : [];
        if (from && from.matches && from.matches('img[data-fc-in-src]')) orig.push(from);
        for (var k = 0; k < imgs.length; k++) {
            var im = imgs[k], set = im.getAttribute('data-fc-in-srcset'), first = im.getAttribute('src');
            /* The first logo is painted OUT under the second: the copy lies over
               the original, and wherever the second is transparent the first
               would show through it. So under the second logo goes a layer of
               the goo's blue cut to the FIRST logo's shape (a mask of it, fitted
               as the logo is) — it covers exactly the original's ink and nothing
               else, whatever shape the second logo is. Same classes as the logo,
               so the same box; stacked in its place (the same grid cell, or over
               a box it fills) and under it (z-index -1 inside the copy). After
               it in the tree, so the copy's children still line up one for one
               with the original's (see twins). All inline: no class new to the
               page (see apply). */
            if (first && im.parentNode) {
                var o = orig[k], grid = !!o && o.parentNode && getComputedStyle(o.parentNode).display.indexOf('grid') >= 0;
                var mk = 'url("' + first.replace(/["\\]/g, '\\$&') + '") center / contain no-repeat';
                var under = document.createElement('span');
                under.className = im.className;
                under.setAttribute('aria-hidden', 'true');
                under.style.cssText = (grid ? 'grid-area:1/1;position:relative;' : 'position:absolute;inset:0;')
                    + 'z-index:-1;pointer-events:none;background-color:' + BLUE_GROUND;
                under.style.setProperty('-webkit-mask', mk);
                under.style.setProperty('mask', mk);
                if (grid) im.style.gridArea = '1 / 1';
                im.parentNode.insertBefore(under, im.nextSibling);
            }
            if (set) im.setAttribute('srcset', set); else im.removeAttribute('srcset');
            im.setAttribute('src', im.getAttribute('data-fc-in-src'));
            im.setAttribute('alt', '');
            im.__fcIn = true;
        }
    }

    function build(el) {
        var old = dupOf(el);
        var isImg = el.tagName === 'IMG';
        var cs = getComputedStyle(el);
        if (!isImg && cs.display === 'inline') {
            if (old) old.parentNode.parentNode.removeChild(old.parentNode);
            return null;
        }
        var host = isImg ? el.parentNode : el;
        if (getComputedStyle(host).position === 'static') host.style.position = 'relative';

        var dup = el.cloneNode(true);
        swapIn(dup, el);
        scrub(dup);
        dup.classList.add('fc-spot-dup');
        var bg = cs.backgroundColor;
        if (!isImg && isPaperish(bg)) dup.classList.add('fc-spot-solid');
        var box = !isImg && (el.matches(BOXES) || alphaOf(bg) > 0);

        var s = dup.style;
        s.removeProperty('--fc-band-from');
        s.removeProperty('--fc-faq-from');
        // the belt's progress, written on the dots many times a second: the
        // copy takes it from the original it sits in (see bareStyle)
        s.removeProperty('--fc-belt-p');
        s.position = 'absolute';
        s.margin = '0';
        s.pointerEvents = 'none';
        s.zIndex = '1';
        if (isImg) {
            /* Width and height are left to the class: the photo is sized by
             * width:100% and --fc-spk-photo-h, and overriding either would stop
             * the copy matching the original pixel for pixel. */
            s.top = '0'; s.left = '0';
        } else {
            s.transform = 'none';
            s.top = -px(cs.borderTopWidth) + 'px';
            s.left = -px(cs.borderLeftWidth) + 'px';
            s.right = -px(cs.borderRightWidth) + 'px';
            s.bottom = box ? -px(cs.borderBottomWidth) + 'px' : 'auto';
            s.width = 'auto'; s.height = 'auto';
            s.maxWidth = 'none'; s.minWidth = '0';
        }
        if (old) {
            // the wrapper stays, with its clip — still right, so
            // the new copy is cut correctly on its very first frame
            old.parentNode.replaceChild(dup, old);
        } else {
            var wrap = wrapper();
            wrap.appendChild(dup);
            if (isImg) host.insertBefore(wrap, el.nextSibling);
            else el.insertBefore(wrap, el.firstChild);
        }
        if (!isImg) collapseFix(el, dup);
        return dup;
    }

    /* THE SEAM. A lit section's top rule is its own (an inset line, see
     * assets/site.css), so no copy of its contents carries it — and with the
     * GPU layer under every section it is drawn over the goo, a grey line
     * across the blue. So each such section is given one more copy: the same
     * line in the goo's colours (the small-print blue, as every line in the
     * goo), clipped to the core like the rest. Drawn the same way on a box
     * with the section's own top edge, so it falls on the same pixels at
     * every zoom; a few px tall, so only the core near it is cut for it. */
    function seamCopy(section) {
        if (!section.classList.contains('border-t')) return;
        for (var c = section.firstElementChild; c; c = c.nextElementSibling) {
            if (c.classList.contains('fc-spot-dupwrap') && c.firstElementChild
                && c.firstElementChild.classList.contains('fc-spot-seam')) return;
        }
        var wrap = wrapper(), line = document.createElement('div');
        wrap.style.bottom = 'auto'; wrap.style.height = '8px';
        line.className = 'fc-spot-dup fc-spot-seam';
        line.style.cssText = 'position:absolute;inset:0;margin:0;padding:0;border:0;'
            + 'box-shadow:inset 0 1px 0 var(--color-border);pointer-events:none';
        wrap.appendChild(line);
        section.insertBefore(wrap, section.firstChild);
    }

    // the outermost target that holds `node`, inside `section`
    function outerTarget(node, section) {
        var el = node && (node.nodeType === 1 ? node : node.parentElement), t = null;
        for (; el && el !== section; el = el.parentElement) {
            if (el.classList && (el.classList.contains('fc-spot-dup') || el.classList.contains('fc-spot-dupwrap'))) return null;
            if (el.matches && el.matches(TARGETS)) t = el;
        }
        return el === section ? t : null;
    }

    function eligible(el, section) {
        if (el.closest(EXCLUDE)) return false;
        if (el.closest('[data-fc-spotlight]') !== section) return false;
        var anc = el.parentElement && el.parentElement.closest(TARGETS);
        return !(anc && section.contains(anc) && !anc.closest(EXCLUDE));
    }

    /* Under a fill of its own — a card, a panel with a solid ground that is not
     * itself copied — the goo cannot be seen, so a copy there would show the
     * inverted type on the card with no blue behind it. Such targets are left
     * alone. Each element is asked once. */
    var filled = new WeakMap();
    function underFill(el, section) {
        for (var n = el.parentElement; n && n !== section; n = n.parentElement) {
            var f = filled.get(n);
            if (f === undefined) { f = alphaOf(getComputedStyle(n).backgroundColor) > 0.5; filled.set(n, f); }
            if (f) return true;
        }
        return false;
    }
    function copyable(el, section) {
        return eligible(el, section) && !underFill(el, section);
    }

    function mountDups(section) {
        var els = section.querySelectorAll(TARGETS);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (dupOf(el) || !copyable(el, section)) continue;
            build(el);
        }
    }

    /* ── a panel ─────────────────────────────────────────────────────────── */

    function Panel(section, withCopies) {
        this.section = section;
        this.scheme = section.getAttribute('data-fc-spotlight');
        this.paper = this.scheme === 'paper';
        this.halo = HALO[this.scheme] || HALO.blue;
        // the hero's blue panel: plain blue, no dots at all
        this.plain = section.classList.contains('fc-hero-blue');

        // a blue panel's dots (see Ground)
        this.ground = this.paper || this.plain ? null : new Ground(section);
        /* The goo: drawn for the whole page by the GPU layer where there is one
         * (see gooLayer) — or else here, on a canvas no bigger than this
         * section's share of it (see size). Over the ground, under all of the
         * content. Made only when it is needed: with the GPU layer, not until
         * that loses its context, if it ever does. */
        this.coreWrap = null;
        if (!GOO) this.core();
        this.shown = false;

        this.withCopies = withCopies;
        this.w = 0; this.h = 0; this.dpr = 0;
        this.copies = [];           // [{ orig, dup, anchor, ai, offX, offY, w, h, next, clip, far, pairs }]
        this.anchors = [];          // unique anchors other than the section
        this.anchorRects = [];
        this.active = false;
        this.offsetsDirty = true;
        this.measured = 0;
        this.rect = null;
        this.near = false;
        this.sticky = new WeakMap();
        if (withCopies && this.paper) seamCopy(section);
        if (withCopies) this.refresh();
    }

    Panel.prototype.core = function () {
        if (!this.coreWrap) {
            var w = this.coreWrap = document.createElement('div'), s = this.section;
            w.className = 'fc-spot-core';
            w.setAttribute('aria-hidden', 'true');
            w.style.visibility = 'hidden';
            s.insertBefore(w, this.ground ? this.ground.wrap.nextSibling : s.firstChild);
        }
        return this.coreWrap;
    };

    // re-scan the DOM: new elements, new belt copies, new anchors
    Panel.prototype.refresh = function () {
        mountDups(this.section);
        this.collect();
    };

    Panel.prototype.anchorOf = function (el) {
        var s = this.section;
        for (var n = el; n && n !== s; n = n.parentElement) {
            if (n.matches(RAILS)) return n;
            var st = this.sticky.get(n);
            if (st === undefined) { st = getComputedStyle(n).position === 'sticky'; this.sticky.set(n, st); }
            if (st) return n;
        }
        return s;
    };

    /* How far a copy's ink can stand outside its box — how close the core has
     * to come before the copy is clipped to it at all. A strip or a box with a
     * slab of its own: far (the hero strips' magnified ends). A photo: its
     * ring. Type: its outline and its overhang, which grow with its size.
     * Every copy within reach takes a new clip every frame the goo moves, and
     * one clipped for nothing costs as much as one that shows. */
    var pads = new WeakMap();
    function inkPad(orig, img) {
        var p = pads.get(orig);
        if (p === undefined) {
            p = img ? 16 : orig.matches(BOXES) ? 80 : 16 + 0.1 * (parseFloat(getComputedStyle(orig).fontSize) || 16);
            pads.set(orig, p);
        }
        return p;
    }

    Panel.prototype.collect = function () {
        var s = this.section, list = s.querySelectorAll('.fc-spot-dup'), out = [], anchors = [];
        /* What was known of each copy — its place, its clip — is carried over
           by its wrapper, which outlives a rebuild of the copy in it: a frame
           between this and the next measuring then keeps every copy where it
           was, rather than taking each for out of reach and hiding it. */
        var before = new Map(), i;
        for (i = 0; i < this.copies.length; i++) before.set(this.copies[i].box, this.copies[i]);
        for (i = 0; i < list.length; i++) {
            var d = list[i];
            if (d.parentElement.closest('.fc-spot-dup')) continue;       // inside another copy
            // a photo's copy is clipped and shown through its wrapper (see build)
            var wrapped = d.parentElement.classList.contains('fc-spot-dupwrap');
            var box = wrapped ? d.parentElement : d;
            var img = d.tagName === 'IMG';
            var orig = img ? box.previousElementSibling : box.parentElement;
            if (!orig) continue;
            var a = this.anchorOf(img ? box.parentElement : orig);
            if (a !== s && anchors.indexOf(a) < 0) anchors.push(a);
            var was = before.get(box), kept = was && was.orig === orig && was.anchor === a;
            out.push({ orig: orig, dup: d, box: box, anchor: a, ai: a === s ? -1 : anchors.indexOf(a),
                       offX: kept ? was.offX : null, offY: kept ? was.offY : null,
                       w: kept ? was.w : 0, h: kept ? was.h : 0, next: kept ? was.next : null,
                       // a rebuilt copy has animations of its own, asked for afresh
                       pairs: kept && was.dup === d ? was.pairs : null,
                       pad: inkPad(orig, img),
                       clip: box.style.clipPath || null, far: !box.style.clipPath });
        }
        this.copies = out;
        this.anchors = anchors;
        this.offsetsDirty = true;
    };

    /* Where an image's PICTURE is inside its box, as object-fit / -position
     * place it (the box is often bigger: a portrait stands in a square frame). */
    var fits = new WeakMap();
    function pictureBox(img, r) {
        var nw = img.naturalWidth, nh = img.naturalHeight;
        if (!nw || !nh) return null;
        var f = fits.get(img);
        if (!f) {
            var cs = getComputedStyle(img), pos = cs.objectPosition.split(' ');
            var pc = function (v, len, free) {          // '100%' / '12px' -> px
                return /%$/.test(v) ? free * parseFloat(v) / 100 : parseFloat(v) || 0;
            };
            f = { fit: cs.objectFit, px: pos[0] || '50%', py: pos[1] || '50%', pc: pc };
            fits.set(img, f);
        }
        if (f.fit === 'fill' || f.fit === '') return { x: r.left, y: r.top, w: r.width, h: r.height };
        var s = f.fit === 'cover' ? Math.max(r.width / nw, r.height / nh)
              : f.fit === 'none' ? 1 : Math.min(r.width / nw, r.height / nh);
        if (f.fit === 'scale-down') s = Math.min(1, s);
        var w = nw * s, h = nh * s;
        return { x: r.left + f.pc(f.px, w, r.width - w), y: r.top + f.pc(f.py, h, r.height - h), w: w, h: h };
    }

    /* Is (x, y), in client px, on an OPAQUE pixel of this image's picture? The
     * alpha is read once into a 64×64 map, as assets/speakers-belt.js does for
     * the row — so a portrait lights only under the person, not over the air
     * round them, on /speakers/ too, where there is no belt to decide it. A
     * picture that cannot be read (another origin) counts all over. */
    var alphaMaps = new WeakMap();
    function onPicture(img, x, y) {
        if (!img.complete || !img.naturalWidth) return false;
        var pb = pictureBox(img, img.getBoundingClientRect());
        if (!pb) return false;
        var u = (x - pb.x) / pb.w, v = (y - pb.y) / pb.h;
        if (u < 0 || u > 1 || v < 0 || v > 1) return false;
        var map = alphaMaps.get(img);
        if (map === undefined) {
            try {
                var c = document.createElement('canvas');
                c.width = c.height = 64;
                var g = c.getContext('2d', { willReadFrequently: true });
                g.drawImage(img, 0, 0, 64, 64);
                map = g.getImageData(0, 0, 64, 64).data;
            } catch (err) { map = null; }
            alphaMaps.set(img, map);
        }
        if (!map) return true;
        var col = Math.min(63, Math.floor(u * 64)), row = Math.min(63, Math.floor(v * 64));
        return map[(row * 64 + col) * 4 + 3] > 40;
    }

    /* One copy's animations paired with its original's, in tree order. Asked
     * only when a copy comes into view — asking all hundred copies of a row,
     * every couple of seconds, was a frame of its own. */
    function cssAnim(a) { return typeof a.animationName === 'string'; }
    function pairsFor(c) {
        if (!c.dup.getAnimations) return [];
        var da = c.dup.getAnimations({ subtree: true }).filter(cssAnim);
        if (!da.length) return [];
        var oa = c.orig.getAnimations({ subtree: c.orig.tagName !== 'IMG' }).filter(function (a) {
            var t = a.effect && a.effect.target;
            return cssAnim(a) && !(t && t.closest && t.closest('.fc-spot-dup'));
        });
        var out = [];
        for (var k = 0; k < da.length && k < oa.length; k++) {
            if (da[k].animationName === oa[k].animationName) out.push([oa[k], da[k]]);
        }
        return out;
    }

    /* The goo is ONE canvas per panel, in .fc-spot-core (over a blue panel's
     * ground, see Ground), no bigger than the goo itself: its outline's box,
     * cut to the section, in whole cells. A canvas is composited whole every
     * frame it changes — on a machine with no GPU, by the CPU, pixel by pixel —
     * so a window-sized one cost that every frame wherever the goo was; this
     * costs the goo's own few hundred pixels square.
     *
     * Sized INLINE, to the pixel, against everything round it: the base styles
     * cap a canvas at 100%, and the bleeding sections (.fc-section-bleed in
     * assets/site.css) hand every grandchild the column's measure and auto
     * margins. */
    Panel.prototype.size = function (w, h, dpr, vh) {
        this.w = w; this.h = h; this.dpr = dpr; this.vh = vh;
        if (!this.goo) {
            var cv = document.createElement('canvas'), s = cv.style;
            s.maxWidth = 'none'; s.maxHeight = 'none'; s.margin = '0';
            cv.className = 'fc-spot-core-img';
            this.coreWrap.appendChild(cv);
            // bw, bh: its size in cells; x, y: where it sits in the section;
            // g, key: what it shows; drawn: whether there is anything on it
            this.goo = { canvas: cv, ctx: cv.getContext('2d'), bw: 0, bh: 0, x: NaN, y: NaN, g: null, key: null, drawn: false };
        }
        this.goo.bw = 0;            // a new dpr or window: sized afresh on the next paint
    };

    /* ── MEASURING ───────────────────────────────────────────────────────────
     * Nothing is read from the layout inside a frame. Reading it there — even
     * the scroll position — makes the browser bring style and layout up to
     * date on the spot, after the page's own animations have marked them out
     * of date, and then again at the end of the frame: a second style and
     * layout pass every frame, which on this page cost more than everything
     * the spotlight itself does. Everything is measured instead just AFTER a
     * frame is drawn (see measure in init), when layout is already clean and a
     * read costs nothing, and the next frame uses that. */

    // the section's padding box in DOCUMENT px — moves only when the layout does
    Panel.prototype.measureBox = function (sx, sy) {
        var s = this.section, bb = s.getBoundingClientRect();
        this.doc = {
            left: bb.left + s.clientLeft + sx, top: bb.top + s.clientTop + sy,
            width: s.clientWidth, height: s.clientHeight
        };
    };

    /* While the goo is about: where the copies' anchors are, in DOCUMENT px (a
     * belt slides its copies along with it), the copies' places on them every
     * second or when something moved them, and the animations of any copy the
     * goo has just reached. */
    Panel.prototype.measureCopies = function (sx, sy, now) {
        if (!this.copies.length || !this.doc) return;
        var i, k, c, ar, d = this.doc, an = [];
        for (i = 0; i < this.anchors.length; i++) {
            var b = this.anchors[i].getBoundingClientRect();
            an.push({ left: b.left + sx, top: b.top + sy });
        }
        this.anchorDoc = an;
        if (now - this.measured > 1000) this.offsetsDirty = true;
        if (this.offsetsDirty) {
            for (k = 0; k < this.copies.length; k++) {
                c = this.copies[k];
                if (!c.box.isConnected) continue;
                var dr = c.box.getBoundingClientRect();
                ar = c.ai < 0 ? d : an[c.ai];
                c.offX = dr.left + sx - ar.left;
                c.offY = dr.top + sy - ar.top;
                c.w = dr.width;
                c.h = dr.height;
            }
            this.offsetsDirty = false;
            this.measured = now;
            this.measures = (this.measures || 0) + 1;
        }
        for (k = 0; k < this.copies.length; k++) {
            c = this.copies[k];
            if (c.next && !c.pairs) c.pairs = pairsFor(c);
        }
    };

    /* In the frame, from what was measured: the section's box on screen. */
    Panel.prototype.place = function (sx, sy, dpr) {
        var d = this.doc;
        var r = { left: d.left - sx, top: d.top - sy, width: d.width, height: d.height };
        r.right = r.left + r.width; r.bottom = r.top + r.height;
        this.rect = r;
        this.sx = sx; this.sy = sy;
        // the canvases' origin in DOCUMENT px, as the browser places them: on
        // whole device pixels
        this.docX = Math.round(d.left * dpr) / dpr;
        this.docY = Math.round(d.top * dpr) / dpr;
    };

    /* …and, once the goo of this frame is known: is any of it in here, and if
     * so, each copy's clip — the core's rectangles near it, in its own px, or
     * null when the goo does not reach it. The margin covers ink that stands
     * outside a copy's box: a strip's slab, a ring. */
    Panel.prototype.clips = function () {
        var r = this.rect, g = cells, sx = this.sx, sy = this.sy, d = this.doc;
        this.near = !!g && g.ext[2] * Q > r.left + sx && g.ext[0] * Q < r.right + sx
                         && g.ext[3] * Q > r.top + sy && g.ext[1] * Q < r.bottom + sy;
        if (!this.near || !this.copies.length) return;
        var an = this.anchorDoc || [], key = d.left + ',' + d.top + '!' + this.measures, k, c, ar;
        for (k = 0; k < an.length; k++) key += ';' + an[k].left + ',' + an[k].top;
        // the same goo over the copies where they were: every clip stands
        if (g === this.clipG && key === this.clipKey) return;
        this.clipG = g; this.clipKey = key;
        for (k = 0; k < this.copies.length; k++) {
            c = this.copies[k];
            ar = c.ai >= 0 ? an[c.ai] : d;
            if (c.offX == null || !ar) { c.next = null; continue; }
            c.next = clipFor(g, ar.left + c.offX, ar.top + c.offY, c.w, c.h, c.pad);
        }
    };
    /* WRITE phase — no layout reads from here on. */
    Panel.prototype.write = function (vh, now, dpr) {
        var r = this.rect, g = cells, near = this.near;
        if (!GPU) {
            // this section's share of the goo, drawn here (no GPU layer this frame)
            this.core();
            var w = Math.max(1, Math.round(r.width)), h = Math.max(1, Math.round(r.height));
            if (!this.goo || w !== this.w || h !== this.h || dpr !== this.dpr || vh !== this.vh) this.size(w, h, dpr, vh);
            var show = near;
            if (show || this.goo.drawn) this.paint(near ? g : null, r, now);
            if (show !== this.shown) { this.coreWrap.style.visibility = show ? 'visible' : 'hidden'; this.shown = show; }
        } else if (this.goo && (this.shown || this.goo.drawn)) {
            // the GPU layer drawing again: this section's own share put away
            this.paint(null, r, now);
            this.coreWrap.style.visibility = 'hidden'; this.shown = false;
        }

        if (near) {
            /* Each copy, clipped to the goo. A copy the goo does not reach is
             * clipped to nothing (see hide), so it cannot show anywhere
             * whatever its ink does outside its box; the rest take this frame's
             * clip, written only if it changed. */
            for (var k = 0; k < this.copies.length; k++) {
                var c = this.copies[k], clip = c.next;
                if (!clip) { if (!c.far) hide(c); continue; }
                if (c.far) {
                    c.far = false;
                    /* A photo in reach goes on a layer of its own, under its
                     * wrapper's clip: a new clip each frame then redraws the
                     * clip's mask and not the photo, with the blur its ring is
                     * made of. Only while in reach — a layer for every photo
                     * on the page costs more than it saves. Not when it is
                     * still laid out from a moment ago (see cool). */
                    if (c.box.style.contentVisibility !== 'visible') {
                        if (c.dup.tagName === 'IMG') c.dup.style.willChange = 'transform';
                        c.box.style.contentVisibility = 'visible';
                    }
                }
                if (clip !== c.clip) { c.box.style.clipPath = clip; c.clip = clip; }
                // (its animations were set going already: see syncAnimations)
            }
        } else if (this.active) {
            for (var q = 0; q < this.copies.length; q++) if (!this.copies[q].far) hide(this.copies[q]);
        }
        this.active = near;
    };

    /* A copy out of the goo's reach is clipped to nothing — its wrapper back
     * to the stylesheet's inset(50%) — and its animations are held: the
     * stylesheet holds every copy's still until the goo first reaches it, or
     * every unseen copy on the page ticked every frame. Held on the next
     * frame, with the rest of the animations' reading (see syncAnimations) —
     * clipped to nothing, it shows nothing meanwhile. They play again, on the
     * original's clock, the frame it comes back. */
    function hide(c) {
        c.box.style.clipPath = '';
        c.clip = null;
        c.far = true;
        cooling.set(c.box, { dup: c.dup, at: frameAt });
        if (c.pairs) holds.push(c);
    }

    /* …and a moment later, if the goo has not come back to it, it is no
     * longer laid out either (content-visibility back to the stylesheet's
     * hidden) and a photo leaves its layer. Every switch of
     * content-visibility builds the copy's boxes afresh, styles and all, and
     * the goo sweeping back and forth over the same few copies switched them
     * dozens of times a second — the more of them, the further out the
     * window is zoomed. Clipped to nothing, a copy shows nothing either way.
     * Keyed by the wrapper, which outlives a re-collect of the copies; the
     * wrapper's own clip says whether the goo is on it again. */
    var COOL_MS = 1000, cooling = new Map(), frameAt = 0;
    function cool(now) {
        cooling.forEach(function (w, box) {
            if (box.style.clipPath) { cooling.delete(box); return; }    // in reach again
            if (now - w.at < COOL_MS) return;
            if (w.dup.tagName === 'IMG') w.dup.style.willChange = '';
            box.style.contentVisibility = '';
            cooling.delete(box);
        });
    }

    /* The copies' animations on their originals' clocks — live ones only: a
     * cancelled animation (its copy rebuilt, or display:none) must never be
     * played again, or it outlives its element and piles up; a pair that has
     * gone idle is asked for again. Every copy the goo reaches this frame, and
     * every copy it left last frame (held, see hide).
     *
     * All the READING first, for every panel, and only then the changes.
     * Asking a CSS animation its playState makes the browser bring the whole
     * page's style up to date before it answers; asked between the frame's
     * writes, as it was, that was a style pass for every copy the goo touched
     * — more of them the more of the page there is on screen, which is why a
     * zoomed-out window ran slower. Read together, before anything is
     * written, it is one pass at most. pause(), play() and currentTime do not
     * ask for one. */
    var holds = [], acts = [], syncs = 0;
    function syncAnimations(panels) {
        var i, a, c, pr, o, d, v;
        syncs++;
        for (var k = 0; k < panels.length; k++) {
            if (!panels[k].near) continue;
            var copies = panels[k].copies;
            for (i = 0; i < copies.length; i++) {
                c = copies[i]; pr = c.pairs;
                if (!c.next) continue;                      // out of reach: hidden in write
                c.synced = syncs;
                for (a = 0; pr && a < pr.length; a++) {
                    o = pr[a][0]; d = pr[a][1];
                    if (o.playState === 'idle' || d.playState === 'idle') { c.pairs = null; break; }
                    var de = d.effect && d.effect.target;
                    if (!de || !de.isConnected) { c.pairs = null; break; }
                    if (o.playState === 'paused') { if (d.playState !== 'paused') acts.push(d, 'pause'); }
                    else if (d.playState === 'paused') acts.push(d, 'play');
                    var ct = o.currentTime;
                    if (ct != null && d.currentTime !== ct) acts.push(d, ct);
                }
            }
        }
        for (i = 0; i < holds.length; i++) {
            c = holds[i];
            if (!c.far || c.synced === syncs || !c.pairs) continue;     // back in reach
            for (a = 0; a < c.pairs.length; a++) {
                d = c.pairs[a][1];
                if (d.playState === 'running') acts.push(d, 'pause');
            }
        }
        holds.length = 0;
        for (i = 0; i < acts.length; i += 2) {
            d = acts[i]; v = acts[i + 1];
            if (v === 'pause') d.pause();
            else if (v === 'play') d.play();
            else d.currentTime = v;
        }
        acts.length = 0;
    }

    /* Paint the goo (see size): its rings and core from the scheme's picture,
     * one pixel a cell scaled up with the scaling kept hard, and the field's
     * dots inside the core over that. Only when something changed — the goo,
     * a dot inside it, or where the canvas sits — and not at all otherwise. */
    Panel.prototype.paint = function (g, r, now) {
        var o = this.goo, ctx = o.ctx, dpr = this.dpr, d = this.doc;
        // the goo's outline box, cut to the section, in cells
        var e = g ? g.ext : null, cx0, cy0, cx1, cy1;
        if (e) {
            cx0 = Math.max(e[0], Math.floor(d.left / Q)); cy0 = Math.max(e[1], Math.floor(d.top / Q));
            cx1 = Math.min(e[2], Math.ceil((d.left + d.width) / Q)); cy1 = Math.min(e[3], Math.ceil((d.top + d.height) / Q));
        }
        if (!e || cx1 <= cx0 || cy1 <= cy0) {
            if (o.drawn) { ctx.clearRect(0, 0, o.canvas.width, o.canvas.height); o.drawn = false; }
            o.g = null; o.key = null;
            return;
        }
        var wc = cx1 - cx0, hc = cy1 - cy0;
        /* Zoomed out (a ratio below 1), at the screen's own resolution, as the
         * dots' canvases are: drawn at a pixel per CSS px, a goo flung across a
         * zoomed-out window was a canvas many times the screen's size, redrawn
         * every frame for the browser to shrink. The cells are the same
         * picture scaled with the scaling kept hard; the dots go through a
         * scale. */
        var sk = Math.min(1, window.devicePixelRatio || 1);
        // the canvas in steps of 16 cells, so it is not resized every frame
        var bw = Math.ceil(wc / 16) * 16, bh = Math.ceil(hc / 16) * 16, resized = false;
        if (bw > o.bw || bh > o.bh || bw * 2 < o.bw || bh * 2 < o.bh || sk !== o.sk) {
            o.bw = bw; o.bh = bh; o.sk = sk;
            o.canvas.width = Math.max(1, Math.round(bw * Q * dpr * sk)); o.canvas.height = Math.max(1, Math.round(bh * Q * dpr * sk));
            o.canvas.style.width = bw * Q + 'px'; o.canvas.style.height = bh * Q + 'px';
            resized = true;
        }
        // its top-left: cell (cx0, cy0), in the section's px
        var tx = cx0 * Q - this.docX, ty = cy0 * Q - this.docY;
        if (tx !== o.x || ty !== o.y) { o.canvas.style.transform = 'translate(' + tx + 'px,' + ty + 'px)'; o.x = tx; o.y = ty; }
        // …in document px, and in window device px (the dots are the window's)
        var ox = cx0 * Q, oy = cy0 * Q;
        var vx = Math.round((ox - this.sx) * dpr), vy = Math.round((oy - this.sy) * dpr);
        var H = o.bh * Q * dpr;                         // its height in drawing units
        var dots = this.plain ? null : coreDots(fieldAt(waveNow(now), dpr), g, ox, oy, dpr, vx, vy, H, o.bw * Q * dpr);
        var key = cx0 + ',' + cy0 + ',' + vx + ',' + vy + (dots ? '|' + dots.key : '');
        if (!resized && g === o.g && key === o.key) return;
        o.g = g; o.key = key;
        ctx.clearRect(0, 0, o.canvas.width, o.canvas.height);
        var pic = cellPicture(g, this.scheme, this.paper ? BLUE_GROUND : WHITE_GROUND, this.halo);
        var sw = Math.min(wc, pic.w - (cx0 - g.x0)), sh = Math.min(hc, pic.h - (cy0 - g.y0));
        if (sw > 0 && sh > 0) {
            ctx.imageSmoothingEnabled = false;
            ctx.drawImage(pic.canvas, cx0 - g.x0, cy0 - g.y0, sw, sh, 0, 0, sw * Q * dpr * sk, sh * Q * dpr * sk);
        }
        if (dots) {
            ctx.fillStyle = this.paper ? BLUE_DOT : WHITE_DOT;
            if (sk !== 1) ctx.setTransform(sk, 0, 0, sk, 0, 0);
            ctx.fill(dots.path);
            if (sk !== 1) ctx.setTransform(1, 0, 0, 1, 0, 0);
        }
        o.drawn = true;
    };

    /* ── THE LOADING SCREEN ──────────────────────────────────────────────────
     * While the site comes in, the loading screen (assets/loader/) covers it
     * with layers, one of them the site's own white ground: its paper and its
     * dot field. The goo belongs on that layer too, exactly as on the page
     * under it — the SAME goo, from the same trail, drawn a second time: over
     * the whole window, into an element of the loading screen's that covers
     * the window and stands still in it. The trail runs on under the cover all
     * the while, so the goo the site shows when the cover has gone is the one
     * that was on the layer: one animation, carried on.
     *
     * window.FC_SPOT_MIRROR(host, copies) starts it and hands back { stop }.
     * `copies`: the loading screen's own type that turns inside the goo as the
     * page's copies do (its white copy of the wordmark) — each clipped to the
     * goo's core every frame, and to nothing out of its reach. Drawn by a GPU
     * goo layer of its own while the page's goo is on the GPU, else in 2D the
     * way a section draws its share (Panel.paint). */
    var mirrors = [];
    function Mirror(host, copies) {
        this.host = host;
        this.layer = undefined;     // its GPU goo layer: made on first use (null: none to be had)
        this.flat = null;           // its 2D share: a stand-in panel, the window for its box
        this.copies = [];
        for (var i = 0; copies && i < copies.length; i++) {
            if (copies[i]) this.copies.push({ el: copies[i], x: 0, y: 0, w: 0, h: 0, pad: 16, clip: null });
        }
        this.vw = -1; this.vh = -1;
    }
    /* Where the copies are in the window (they stand still in it), and how far
       their ink reaches past their boxes — read outside the frame (see
       MEASURING): when it starts, and when the window changes size. */
    Mirror.prototype.measure = function (vw, vh) {
        this.vw = vw; this.vh = vh;
        for (var i = 0; i < this.copies.length; i++) {
            var c = this.copies[i], r = c.el.getBoundingClientRect();
            c.x = r.left; c.y = r.top; c.w = r.width; c.h = r.height;
            c.pad = 16 + 0.1 * (parseFloat(getComputedStyle(c.el).fontSize) || 16);
        }
    };
    Mirror.prototype.frame = function (g, now, sx, sy, vw, vh, dpr) {
        var sk = Math.min(1, window.devicePixelRatio || 1);
        var o = { sx: sx, sy: sy, vw: vw, vh: vh, dpr: dpr, sk: sk };
        if (this.layer === undefined && GPU) this.layer = gooLayer(this.host);
        if (GPU && this.layer && this.layer.ok()) {
            if (this.flat && this.flat.goo && this.flat.goo.drawn) this.paint2d(null, now, sx, sy, vw, vh, dpr);
            this.layer.draw(g, g ? fieldAt(waveNow(now), dpr) : null, o);
        } else {
            if (this.layer && this.layer.drawn()) this.layer.draw(null, null, o);
            this.paint2d(g, now, sx, sy, vw, vh, dpr);
        }
        // the copies: in the core's shape where it reaches them, else nothing
        for (var k = 0; k < this.copies.length; k++) {
            var c = this.copies[k];
            var clip = g ? clipFor(g, c.x + sx, c.y + sy, c.w, c.h, c.pad) : null;
            var v = clip || 'inset(50%)';
            if (v !== c.clip) { c.el.style.clipPath = v; c.clip = v; }
        }
    };
    /* The 2D share: a stand-in panel whose box is the window (in document
       px), painted as a section paints its own (see Panel.paint). With the GPU
       drawing the page's goo, the rings are worked out there and not here, so
       only the core and its dots come out this way — that only when this
       mirror could not have a GPU layer of its own. */
    // the stand-in panel, made (and put in the host) once
    Mirror.prototype.flatPanel = function () {
        var p = this.flat;
        if (p) return p;
        p = this.flat = Object.create(Panel.prototype);
        p.scheme = 'paper'; p.paper = true; p.plain = false; p.halo = HALO.paper;
        p.goo = null; p.w = 0; p.h = 0; p.dpr = 0;
        var wrap = p.coreWrap = document.createElement('div');
        wrap.className = 'fc-spot-core';
        wrap.setAttribute('aria-hidden', 'true');
        wrap.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;pointer-events:none';
        this.host.appendChild(wrap);
        return p;
    };
    Mirror.prototype.paint2d = function (g, now, sx, sy, vw, vh, dpr) {
        var p = this.flat;
        if (!p) {
            if (!g) return;
            p = this.flatPanel();
        }
        p.doc = { left: sx, top: sy, width: vw, height: vh };
        p.sx = sx; p.sy = sy;
        p.docX = Math.round(sx * dpr) / dpr; p.docY = Math.round(sy * dpr) / dpr;
        if (!p.goo || vw !== p.w || vh !== p.h || dpr !== p.dpr) p.size(vw, vh, dpr, vh);
        if (g || p.goo.drawn) p.paint(g, null, now);
    };
    // anything on it still to be cleared away (the loop runs on until it is)
    Mirror.prototype.busy = function () {
        return !!((this.layer && this.layer.drawn()) || (this.flat && this.flat.goo && this.flat.goo.drawn));
    };
    /* Done: the GPU slot given back, the 2D picture emptied, the copies cut to
       nothing — all left where they are (nothing is taken out of the page in
       the middle of the loading screen's swipe). */
    Mirror.prototype.stop = function () {
        if (this.layer) this.layer.destroy();
        this.layer = null;
        if (this.flat && this.flat.goo) {
            this.flat.goo.canvas.width = 0; this.flat.goo.canvas.height = 0;
            this.flat.goo.drawn = false;
        }
        for (var i = 0; i < this.copies.length; i++) this.copies[i].el.style.clipPath = 'inset(50%)';
    };

    /* ── what is the pointer on? ─────────────────────────────────────────── */

    // is (x, y) on the LETTERS of this element — not the empty end of its line?
    var range = document.createRange();
    function onText(host, x, y) {
        for (var c = host.firstElementChild; c; c = c.nextElementSibling) {
            if (isCopy(c)) continue;
            range.selectNodeContents(c);
            var rs = range.getClientRects();
            for (var i = 0; i < rs.length; i++) {
                var r = rs[i];
                if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) return true;
            }
        }
        return false;
    }

    // its position among its siblings, not counting copies
    function indexIn(n) {
        var i = 0;
        for (var s = n.previousElementSibling; s; s = s.previousElementSibling) {
            if (!isCopy(s)) i++;
        }
        return i;
    }

    /* The element under the pointer, and its twin in the copy: that twin and
     * everything above it up to the copy's root are what :hover would have lit. */
    function twins(el, section) {
        var T = outerTarget(el, section);
        if (!T) return [];
        var d = dupOf(T);
        if (!d) return [];
        if (T.tagName === 'IMG') return [d];
        var path = [];
        for (var n = el; n && n !== T; n = n.parentElement) path.push(indexIn(n));
        var out = [d], m = d;
        for (var k = path.length - 1; k >= 0 && m; k--) {
            m = m.firstElementChild;
            for (var i = path[k]; m && i > 0; i--) m = m.nextElementSibling;
            if (m) out.push(m);
        }
        return out;
    }

    function probe() {
        var el = document.elementFromPoint(ptr.cx, ptr.cy);
        var section = el && el.closest('[data-fc-spotlight]');
        if (!section) return null;
        var hit = { name: null, photo: null, online: null, hov: twins(el, section) };
        if (section.getAttribute('data-fc-spotlight') === 'blue') {
            var nm = el.closest('.fc-spk-name');
            if (nm && !nm.classList.contains('fc-spot-dup') && onText(nm, ptr.cx, ptr.cy)) hit.name = nm;
            // the [ONLINE] badge hugs its own words (width: fit-content)
            var ol = el.closest('.fc-spk-online');
            if (ol && !ol.classList.contains('fc-spot-dup')) hit.online = ol;
            // a portrait: only on an OPAQUE pixel of it, not the air round it
            var ph = el.closest('.fc-spk-photo');
            if (ph) {
                for (var c = ph.firstElementChild; c; c = c.nextElementSibling) {
                    if (c.classList.contains('fc-spk-shot') && !c.classList.contains('fc-spot-dup')) {
                        if (onPicture(c, ptr.cx, ptr.cy)) hit.photo = ph;
                        break;
                    }
                }
            }
        }
        return hit;
    }

    /* State is written as data-* ATTRIBUTES, never classes. Tailwind's browser
     * build watches every class on the page and recompiles its whole stylesheet
     * the first time it sees a new one — a full-page restyle, felt as a stall on
     * the first hover and the first click. */
    var lit = { name: null, photo: null, online: null }, hovMarked = [];
    function apply(hit) {
        ['name', 'photo', 'online'].forEach(function (k) {
            var now = hit ? hit[k] : null;
            if (lit[k] === now) return;
            if (lit[k]) lit[k].removeAttribute('data-fc-on');
            if (now) now.setAttribute('data-fc-on', '');
            lit[k] = now;
        });
        var list = hit ? hit.hov : [];
        var same = list.length === hovMarked.length;
        for (var i = 0; same && i < list.length; i++) same = list[i] === hovMarked[i];
        if (same) return;
        for (i = 0; i < hovMarked.length; i++) hovMarked[i].removeAttribute('data-fc-hov');
        for (i = 0; i < list.length; i++) list[i].setAttribute('data-fc-hov', '');
        hovMarked = list;
    }

    /* ── keeping the copies current ──────────────────────────────────────── */

    // a style attribute with this script's own per-frame values taken out
    function bareStyle(v) {
        return (v || '').replace(/--fc-(anchor|off|spot|band-from|faq-from|belt-p)[\w-]*\s*:[^;]*;?/g, '').replace(/\s+/g, ' ').trim();
    }
    function sameState(a, b) {
        var x = (a || '').split(/\s+/).filter(function (c) { return c && !STATE_CLASSES[c]; }).sort().join(' ');
        var y = (b || '').split(/\s+/).filter(function (c) { return c && !STATE_CLASSES[c]; }).sort().join(' ');
        return x === y;
    }
    function allDups(nodes) {
        for (var i = 0; i < nodes.length; i++) {
            var n = nodes[i];
            if (n.nodeType !== 1 || !(n.classList.contains('fc-spot-dup') || n.classList.contains('fc-spot-dupwrap')
                || n.classList.contains('fc-spot-core'))) return false;
        }
        return true;
    }

    /* A change INSIDE a copied element is carried over to the same place in
     * its copy, rather than the copy being cloned all over again: a button
     * whose label scrambles changes a span every few frames, and re-cloning the
     * button for each was a restyle of half the page every time. Gives up —
     * false, and the copy is rebuilt — when the change is to the element
     * itself or the two trees no longer line up. */
    function patch(T, el, rec) {
        if (el === T || T.tagName === 'IMG') return false;
        var m = dupOf(T), path = [];
        if (!m) return false;
        for (var n = el; n && n !== T; n = n.parentElement) path.push(indexIn(n));
        for (var k = path.length - 1; k >= 0 && m; k--) {
            m = m.firstElementChild;
            for (var i = path[k]; m && i > 0; i--) m = m.nextElementSibling;
        }
        if (!m || m.tagName !== el.tagName) return false;
        if (rec.type === 'attributes') {
            // a logo's second version keeps its own source (see swapIn)
            if (m.__fcIn && /^(src|srcset|sizes)$/.test(rec.attributeName)) return true;
            var v = el.getAttribute(rec.attributeName);
            if (v === null) m.removeAttribute(rec.attributeName);
            else if (m.getAttribute(rec.attributeName) !== v) m.setAttribute(rec.attributeName, v);
            return true;
        }
        // the content: plain words are copied as words, anything else afresh
        if (!el.firstElementChild) {
            if (m.textContent !== el.textContent) m.textContent = el.textContent;
            return true;
        }
        var fresh = el.cloneNode(true);
        swapIn(fresh, el);
        scrub(fresh, true);
        if (m.hasAttribute('data-fc-hov')) fresh.setAttribute('data-fc-hov', '');
        m.parentNode.replaceChild(fresh, m);
        probeDue = true;                // the hover marks below it went with it
        return true;
    }

    function watch(p) {
        if (!window.MutationObserver) return;
        var queued = false, dirty = [], rescan = false;
        function handle(records) {
            for (var i = 0; i < records.length; i++) {
                var rec = records[i], tgt = rec.target;
                var el = tgt.nodeType === 1 ? tgt : tgt.parentElement;
                if (!el || el.closest('.fc-spot-dup, .fc-spot-dupwrap, .fc-spot-core')) continue;
                if (rec.type === 'childList' && allDups(rec.addedNodes) && allDups(rec.removedNodes)) continue;
                if (rec.type === 'attributes') {
                    if (rec.attributeName === 'style' && bareStyle(rec.oldValue) === bareStyle(el.getAttribute('style'))) continue;
                    if (rec.attributeName === 'class' && sameState(rec.oldValue, el.getAttribute('class'))) continue;
                }
                var t = outerTarget(el, p.section);
                if (t) {
                    if (dirty.indexOf(t) < 0 && !patch(t, el, rec)) dirty.push(t);
                }
                else if (rec.type === 'childList') rescan = true;     // new cards, new rows
                else if (rec.type === 'attributes') {
                    if (rec.attributeName === 'class') rescan = true;
                    // a part shown or hidden (a schedule day, an open answer)
                    // moves what comes after it: measured again
                    else if (rec.attributeName !== 'style') p.offsetsDirty = true;
                }
            }
            if (queued || (!dirty.length && !rescan)) return;
            queued = true;
            requestAnimationFrame(function () {
                queued = false;
                var list = dirty; dirty = [];
                for (var k = 0; k < list.length; k++) {
                    /* Its copy rebuilt — or built again where a script wrote
                       the target's whole content (textContent, innerHTML) and
                       took the copy's wrapper with it. */
                    if (list[k].isConnected && (dupOf(list[k]) || copyable(list[k], p.section))) build(list[k]);
                }
                if (rescan) { rescan = false; mountDups(p.section); }
                p.collect();
                // a lit name or part copied by a belt keeps its class; drop strays
                var on = p.section.querySelectorAll('[data-fc-on]');
                for (k = 0; k < on.length; k++) if (on[k] !== lit.name && on[k] !== lit.photo && on[k] !== lit.online) on[k].removeAttribute('data-fc-on');
                hovMarked = hovMarked.filter(function (n) { return n.isConnected; });
                probeDue = true;
                mo.takeRecords();
            });
        }
        var mo = new MutationObserver(handle);
        mo.observe(p.section, {
            childList: true, subtree: true, characterData: true,
            attributes: true, attributeOldValue: true,
            attributeFilter: ['style', 'class', 'src', 'srcset', 'open', 'hidden', 'aria-expanded']
        });
        /* The frame writes a clip or two into the copies every frame, and each
         * would come back here as a record to be looked at and thrown away.
         * Instead the frame takes what is waiting just before it writes, and
         * drops what its own writes queued just after. */
        p.drain = function () { handle(mo.takeRecords()); };
        p.discard = function () { mo.takeRecords(); };
    }

    /* ── wiring ──────────────────────────────────────────────────────────── */

    function init() {
        ringDefs();
        var w;
        /* ONE GROUND, WHITE. The landing page's paper, the one dot field
         * (assets/wave-bg.js) and the trail under all of it: every section
         * the field shows through is lit the paper way — the hero's paper
         * panel, Speakers, Communities, News, Sponsors, the footer. Not a
         * section with a paper ground of its own (bg-paper: Manifesto,
         * Schedule, Venue, Get Involved, FAQ): a white panel in FRONT of the
         * field and the trail, which run on behind it. Nor the hero's blue
         * panel, the title's side. Only the landing page: the standalone pages
         * are documents, not this. */
        if (document.body.classList.contains('fc-landing') || document.querySelector('.fc-hero')) {
            var paper = document.querySelectorAll('main section[id], footer.fc-section-dots, .fc-hero-paper');
            for (w = 0; w < paper.length; w++) {
                var sec = paper[w];
                if (sec.hasAttribute('data-fc-spotlight') || sec.classList.contains('fc-hero')
                    || sec.classList.contains('bg-paper')) continue;
                sec.setAttribute('data-fc-spotlight', 'paper');
            }
        }
        var sections = document.querySelectorAll('[data-fc-spotlight]');
        if (!sections.length) return;

        /* A screen with no hover — a phone, a tablet — gets the blue panels'
         * dots and nothing else: no trail, no copies, nothing listening to a
         * pointer it does not have. */
        var canHover = !window.matchMedia || window.matchMedia('(any-hover: hover)').matches;

        if (canHover) {
            buildHoverSheet();
            window.addEventListener('load', function () {
                buildHoverSheet();
                // the Tailwind browser build writes its rules after it has seen
                // the page, which can be after load — one more look a moment later
                setTimeout(buildHoverSheet, 1500);
            });
        }

        /* Trying a highlight colour without editing the stylesheet: add
         * ?fc-hi=55FFFF (any six-digit hex) to the page's address. */
        var hi = /[?&]fc-hi=#?([0-9a-f]{6})(?:&|$)/i.exec(location.search);
        if (hi) document.documentElement.style.setProperty('--fc-blue-hi', '#' + hi[1]);
        // the funding figure's wave is an SVG with its colour baked in: redraw
        // it in whatever the highlight is
        var hiNow = getComputedStyle(document.documentElement).getPropertyValue('--fc-blue-hi').trim();
        if (canHover && /^#[0-9a-f]{6}$/i.test(hiNow)) {
            var wave = document.createElement('style');
            wave.id = 'fc-spot-wave';
            wave.textContent = '[data-fc-spotlight="paper"] .fc-spot-dup .fc-fund-digits{background-image:'
                + 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'24\' viewBox=\'0 0 120 24\' preserveAspectRatio=\'none\'%3E%3Cpath d=\'M0 12 Q15 2 30 12 T60 12 T90 12 T120 12 V24 H0 Z\' fill=\'%23'
                + hiNow.slice(1) + '\'/%3E%3C/svg%3E"),linear-gradient(' + hiNow + ',' + hiNow + '),linear-gradient(#8CA3FF,#8CA3FF)}';
            document.head.appendChild(wave);
        }

        /* ONE GPU layer for the whole page's goo, where there is a GPU and every
           lit section is the paper scheme (on the landing page they all are);
           else each section draws its own share. */
        var allPaper = [].every.call(sections, function (s) { return s.getAttribute('data-fc-spotlight') === 'paper'; });
        GOO = canHover && allPaper ? gooLayer() : null;
        GPU = !!GOO;
        var panels = [];
        for (var i = 0; i < sections.length; i++) panels.push(new Panel(sections[i], canHover));
        if (canHover) panels.forEach(watch);

        /* What the frame works from — all of it read where reading is cheap
         * (see MEASURING): the scroll position as the scroll event reports it
         * (that runs before the page's animations have touched anything), the
         * window's size on a resize, and the rest just after a frame is drawn. */
        var scroll = { x: window.scrollX || 0, y: window.scrollY || 0 };
        var win = { w: 0, h: 0 };
        function readWindow() {
            win.w = document.documentElement.clientWidth || window.innerWidth;
            win.h = window.innerHeight;
            inner.w = window.innerWidth; inner.h = window.innerHeight;
        }
        readWindow();
        var boxesDirty = true, hit = null, hitNew = false;
        var probeAt = 0, probeSeq = -1, probeSy = null, probeSx = null, boxesAt = 0;

        function measure() {
            measureQueued = false;
            var sx = window.scrollX || 0, sy = window.scrollY || 0, now = performance.now(), k;
            scroll.x = sx; scroll.y = sy;
            // the sections' boxes: when something may have moved them, and once
            // a second whatever happens
            if (boxesDirty || now - boxesAt > 1000) {
                for (k = 0; k < panels.length; k++) panels[k].measureBox(sx, sy);
                boxesDirty = false; boxesAt = now;
            }
            // the loading screen's copies, when the window changed size
            for (k = 0; k < mirrors.length; k++) {
                if (mirrors[k].vw !== win.w || mirrors[k].vh !== win.h) mirrors[k].measure(win.w, win.h);
            }
            if (!canHover) return;
            if (!(ptr.on || trail.alive > 0 || trail.parts.length)) {
                if (hit !== null) { hit = null; hitNew = true; }
                probeDue = true;
                return;
            }
            var any = false;
            for (k = 0; k < panels.length; k++) {
                var p = panels[k], d = p.doc;
                // a panel on screen, or the goo in it, keeps its copies measured
                if (p.near || (d && d.top - sy < win.h && d.top + d.height - sy > 0)) p.measureCopies(sx, sy, now);
                any = any || p.near;
            }
            /* What is under the pointer — asked when the pointer moved or the
             * page did, and otherwise a few times a second (a belt slides under
             * a pointer that holds still). */
            if (ptr.on && any) {
                if (probeDue || ptr.seq !== probeSeq || sy !== probeSy || sx !== probeSx || now - probeAt > 60) {
                    hit = probe(); hitNew = true;
                    probeDue = false; probeSeq = ptr.seq; probeSy = sy; probeSx = sx; probeAt = now;
                }
            } else {
                if (hit !== null) { hit = null; hitNew = true; }
                probeDue = true;
            }
        }
        // a task right after the frame is drawn
        var measureQueued = false, channel = window.MessageChannel ? new MessageChannel() : null;
        if (channel) channel.port1.onmessage = measure;
        function afterFrame() {
            if (measureQueued) return;
            measureQueued = true;
            if (channel) channel.port2.postMessage(0); else setTimeout(measure, 0);
        }

        /* THE LOOP SLEEPS when there is nothing to do — no pointer, the goo all
         * gone, no copied ground on screen — and anything that could give it
         * something to do wakes it. */
        var running = false;
        function wake() {
            if (running) return;
            running = true;
            requestAnimationFrame(frame);
        }

        // the loading screen's white layer (see THE LOADING SCREEN)
        window.FC_SPOT_MIRROR = function (host, copies) {
            if (!host || !canHover) return null;
            var m = new Mirror(host, copies);
            /* Everything it draws on, made and put in the host NOW, while the
               loading screen is still (it calls this as it builds its layers):
               not in the middle of a frame — the GPU layer builds shaders — and
               not in the middle of the swipe, where a node added to the page
               has the Tailwind runtime scan the whole of it. The canvases are
               0 × 0 until they are first drawn on. */
            if (GOO) m.layer = gooLayer(host);
            if (m.layer) host.appendChild(m.layer.canvas);
            else m.flatPanel().size(win.w, win.h, Math.max(1, Math.min(2, Math.round(window.devicePixelRatio || 1))), win.h);
            m.measure(win.w, win.h);
            mirrors.push(m);
            wake();
            return {
                stop: function () {
                    var at = mirrors.indexOf(m);
                    if (at < 0) return;
                    mirrors.splice(at, 1);
                    m.stop();
                }
            };
        };

        /* The pointer is tracked on the WHOLE document, not just the panels: the
         * goo follows it everywhere and each lit section draws what reaches it.
         * Capture phase, because the belts stop pointer events propagating. Touch
         * is ignored — this is a hover effect. */
        if (canHover) {
            document.addEventListener('pointermove', function (e) {
                if (e.pointerType === 'touch') { ptr.on = false; return; }
                ptr.cx = e.clientX; ptr.cy = e.clientY; ptr.on = true; ptr.seq++;
                wake();
            }, { passive: true, capture: true });
            document.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'touch' || e.button !== 0) return;
                ptr.cx = e.clientX; ptr.cy = e.clientY; ptr.on = true; ptr.seq++;
                burst();
                wake();
            }, { passive: true, capture: true });
            document.addEventListener('mouseout', function (e) {
                if (!e.relatedTarget) ptr.on = false;           // left the window
            }, { passive: true });
            window.addEventListener('blur', function () { ptr.on = false; });
        }
        window.addEventListener('scroll', function () {
            scroll.x = window.scrollX || 0; scroll.y = window.scrollY || 0;
            wake();
        }, { passive: true });

        function moved() {
            boxesDirty = true;
            for (var k = 0; k < panels.length; k++) panels[k].offsetsDirty = true;
            afterFrame();
            wake();
        }
        window.addEventListener('resize', function () { readWindow(); moved(); });
        window.addEventListener('load', moved);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(moved);
        /* a section changing size, or anything above it changing the page's —
           and with it whatever is inside the section (an answer opening, an
           image arriving): the copies measured again too */
        if (window.ResizeObserver) {
            var ro = new ResizeObserver(function () {
                boxesDirty = true;
                for (var k = 0; k < panels.length; k++) panels[k].offsetsDirty = true;
                afterFrame();
            });
            ro.observe(document.body);
            for (var ri = 0; ri < panels.length; ri++) ro.observe(panels[ri].section);
        }
        measure();

        /* ON A MACHINE THAT CANNOT KEEP UP — frames coming slower than ~38 a
         * second, all told, for a second and a half of trail — the trail
         * moves to every other frame: half the work, the same goo, a little
         * less fluid in motion and identical at rest. Back to every frame each
         * time the loop goes to sleep. */
        var gov = { stride: 1, ema: 0, since: 0, n: 0, prev: 0, slot: -1 };
        function govern(now) {
            /* With no GPU the page keeps one beat, 30 a second, for everything
             * that moves (FC_SLOT, see assets/wave-bg.js): the trail steps on
             * it too, so the frames between have nothing new to composite. */
            var SLOT = window.FC_SLOT;
            if (SLOT) {
                var s = Math.floor(now / SLOT);
                if (s === gov.slot) return false;
                gov.slot = s;
                return true;
            }
            if (gov.prev) {
                var gap = now - gov.prev;
                gov.ema = gov.ema ? gov.ema + (gap - gov.ema) * 0.08 : gap;
            } else gov.since = now;
            gov.prev = now;
            if (gov.stride === 1 && now - gov.since > 1500 && gov.ema > 1000 / 38) gov.stride = 2;
            return ++gov.n % gov.stride === 0;
        }

        // the blue panels' grounds: kept up to date on every frame one is on
        // screen, whatever else the frame does or skips
        function grounds(now, dpr) {
            var f = null, any = false, sy = scroll.y;
            for (var k = 0; k < panels.length; k++) {
                var gr = panels[k].ground, d = panels[k].doc;
                if (!gr) continue;
                var on = !!d && d.top - sy < win.h && d.top + d.height - sy > 0;
                if (on && !f) f = fieldAt(waveNow(now), dpr);
                gr.update(f, on, d ? d.top - sy : 0, d ? d.top + d.height - sy : 0);
                any = any || on;
            }
            return any;
        }

        var last = 0;
        function frame(now) {
            // the page background's step for this frame first, whichever of
            // the two scripts' callbacks runs first (see wave-bg.js tick)
            if (window.FC_WAVE_TICK) window.FC_WAVE_TICK(now);
            var grounded = grounds(now, Math.max(1, Math.min(2, Math.round(window.devicePixelRatio || 1))));
            if (!govern(now)) { requestAnimationFrame(frame); return; }
            // a tab that was away comes back to a calm step, not a leap
            var dt = last ? Math.min(1 / 30, Math.max(0, (now - last) / 1000)) : 1 / 60;
            last = now;
            /* A scroll is the pointer moving over the page (see THE TRAIL): its
             * place on the page is where it is on the screen plus the scroll, so
             * the head chases it up and down the page as it would the mouse. */
            var sx = scroll.x, sy = scroll.y;
            var vh = win.h, vw = win.w;
            var dpr = Math.max(1, Math.min(2, Math.round(window.devicePixelRatio || 1)));
            // who draws the goo this frame (see GPU)
            GPU = !!GOO && GOO.ok();
            // the goo's picture can be as big as the window plus the margins round it
            picture.w = Math.ceil((vw + 120) / Q) + 2 * MARGIN_CELLS + 2;
            picture.h = Math.ceil((vh + 120) / Q) + 2 * MARGIN_CELLS + 2;
            var k;
            for (k = 0; k < panels.length; k++) panels[k].place(sx, sy, dpr);
            var alive = canHover && (ptr.on || trail.alive > 0 || trail.parts.length || trail.shocks.length);
            if (alive) {
                var view = { x0: sx, x1: sx + vw, y0: sy, y1: sy + vh };
                // the goo moves (two half steps: stiff springs stay stable at 30fps)
                var tx = ptr.cx + sx, ty = ptr.cy + sy;
                stepTrail(dt / 2, tx, ty, ptr.on, view);
                stepTrail(dt / 2, tx, ty, ptr.on, view);
                cells = fieldCells({ x0: view.x0 - 60, x1: view.x1 + 60, y0: view.y0 - 60, y1: view.y1 + 60 });
            } else cells = null;
            for (k = 0; k < panels.length; k++) panels[k].clips();
            // the copies' animations: all the reading, then their changes, before
            // any other write (see syncAnimations)
            syncAnimations(panels);
            // the writes: changes waiting in the copies first, then the frame's own
            for (k = 0; k < panels.length; k++) if (panels[k].drain) panels[k].drain();
            frameAt = now;
            for (k = 0; k < panels.length; k++) panels[k].write(vh, now, dpr);
            if (GPU) {
                // the goo for the whole page, over the lit sections' extent
                var lo = Infinity, hi = -Infinity;
                for (k = 0; k < panels.length; k++) {
                    var pd = panels[k].doc;
                    if (pd) { lo = Math.min(lo, pd.top); hi = Math.max(hi, pd.top + pd.height); }
                }
                if (lo < hi) GOO.draw(cells, cells ? fieldAt(waveNow(now), dpr) : null, {
                    sx: sx, sy: sy, vw: vw, vh: vh, dpr: dpr,
                    sk: Math.min(1, window.devicePixelRatio || 1), top0: lo, bottom: hi
                });
            }
            // …and again on the loading screen's white layer, while it is up
            for (k = 0; k < mirrors.length; k++) mirrors[k].frame(cells, now, sx, sy, vw, vh, dpr);
            if (cooling.size) cool(now);
            if (hitNew) { apply(hit); hitNew = false; }
            for (k = 0; k < panels.length; k++) if (panels[k].discard) panels[k].discard();

            var busy = alive || grounded || holds.length > 0 || cooling.size > 0 || !!(GOO && GOO.drawn());
            for (k = 0; k < panels.length && !busy; k++) busy = panels[k].shown || !!(panels[k].goo && panels[k].goo.drawn);
            for (k = 0; k < mirrors.length && !busy; k++) busy = mirrors[k].busy();
            if (busy) requestAnimationFrame(frame);
            else { running = false; last = 0; gov.stride = 1; gov.ema = 0; gov.prev = 0; }
            afterFrame();
        }
        wake();
    }


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
