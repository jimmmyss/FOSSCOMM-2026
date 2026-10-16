/* FOSSCOMM 2026 — global wave background.
 *
 * Replaces the static dotted background with an animated dot-wave field that
 * reads like a topographical halftone surface: a grid of small dots where
 * each dot's Y position is displaced by a sum of sin() waves of x, row and
 * time. Where the waves compress adjacent rows together you get darker
 * "ridges"; where they pull rows apart you get lighter "valleys".
 *
 * Architecture: a single <canvas> appended to <body>, position: fixed,
 * z-index: -1. It paints on top of <html>'s paper background and behind
 * every section. Sections that should HIDE the waves carry bg-paper
 * (fc_section_open() adds it by default); sections that show the waves
 * carry .fc-section-dots and skip bg-paper. The paint area is the whole
 * viewport, so the waves stay visually locked to the screen as you scroll.
 *
 * The animation runs continuously — no visibility pauses — per user request.
 *
 * Performance notes:
 *   • Per-frame draw is ONE Path2D fill (batch rect()), not 15k individual
 *     fillRect calls. Browsers JIT this into a single GPU upload.
 *   • Canvas is rendered at devicePixelRatio = 1 — the wave is a soft
 *     background, not text. Saves 4× pixels on retina displays.
 *   • A wider grid (12×16) keeps the visual feel while cutting dot count
 *     by ~2.5× vs the old 8×12.
 *   • Animation honours prefers-reduced-motion: one static frame, no loop.
 */
(function () {
    'use strict';

    var canvas = document.createElement('canvas');
    canvas.id = 'fc-waves-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = [
        'position:fixed',
        'inset:0',
        'width:100vw',
        'height:100vh',
        'z-index:-1',
        'pointer-events:none',
        'display:block'
    ].join(';');

    var ctx;
    var W = 0, H = 0;
    var startMs = 0;
    var reducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Tunable knobs.
    var STEP_X = 12;       // horizontal grid spacing (px)
    var STEP_Y = 16;       // vertical grid spacing (px)
    var DOT    = 1.6;      // square dot size (px)
    var COLOUR = '#C9C7BF';

    var AMP_1 = 18, KX_1 = 0.014, SPEED_1 =  0.45;
    var AMP_2 = 11, KX_2 = 0.027, SPEED_2 = -0.65;
    var AMP_3 = 5,  KX_3 = 0.060, KR_3 = 0.30, SPEED_3 = 0.85;

    function mount() {
        if (document.getElementById('fc-waves-canvas')) return;
        document.body.appendChild(canvas);
        ctx = canvas.getContext('2d', { alpha: true });

        var cssColor = '';
        try {
            cssColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-ink-faint').trim();
        } catch (_e) {}
        if (cssColor) COLOUR = cssColor;

        resize();
        startMs = performance.now();

        if (reducedMotion) {
            draw(startMs);                  // one static frame, no loop
        } else {
            requestAnimationFrame(loop);
        }
    }

    function loop(t) {
        draw(t);
        requestAnimationFrame(loop);
    }

    function resize() {
        if (!ctx) return;
        W = window.innerWidth;
        H = window.innerHeight;
        // Force DPR = 1. The wave is a soft, low-contrast background — sharper
        // dots don't help visually and quadruple the per-frame pixel fill cost
        // on 2× displays.
        canvas.width  = W;
        canvas.height = H;
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        if (reducedMotion) draw(performance.now());
    }

    /* Scratch buffers for draw(), grown as needed and then reused. Allocating
       these per frame would hand the garbage collector a few hundred KB a second
       for no reason. */
    var colX = null;        // x of each column
    var colFlat = null;     // the part of dy that does not depend on the row
    var colSin = null;      // sin/cos of wave 3's per-column phase, so the
    var colCos = null;      //   row term needs no sin() of its own
    var rowSin = null;
    var rowCos = null;
    var haveCols = -1, haveRows = -1;

    function draw(nowMs) {
        var t = (nowMs - startMs) / 1000;
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = COLOUR;

        var rows = Math.ceil(H / STEP_Y) + 4;
        var cols = Math.ceil(W / STEP_X) + 4;

        /* ── separate what varies by column from what varies by row ──────────
         *
         * dy was three Math.sin() calls per DOT. But look at what they depend on:
         * waves 1 and 2 are functions of x and t only — no r — so every row was
         * recomputing the identical value. Wave 3 does depend on the row, through
         * `rowPhase`, but only as an added phase, and
         *
         *     sin(A + B) = sin A cos B + cos A sin B
         *
         * splits it into a column part (A) and a row part (B). Precompute sin/cos
         * of each and the inner loop needs no transcendental at all.
         *
         * Per frame that turns 3·rows·cols sin() calls into 4·cols + 2·rows: on a
         * 1920×1080 desktop, ~35,000 down to ~800. The dot positions are the same
         * numbers — this is the same expression, factored.
         */
        if (cols !== haveCols) {
            colX = new Float64Array(cols + 2);
            colFlat = new Float64Array(cols + 2);
            colSin = new Float64Array(cols + 2);
            colCos = new Float64Array(cols + 2);
            haveCols = cols;
        }
        if (rows !== haveRows) {
            rowSin = new Float64Array(rows + 2);
            rowCos = new Float64Array(rows + 2);
            haveRows = rows;
        }

        var p1 = t * SPEED_1, p2 = t * SPEED_2, p3 = t * SPEED_3;
        var i, c, r;
        for (i = 0, c = -2; c < cols; c++, i++) {
            var kx = c * STEP_X;
            colX[i] = kx;
            colFlat[i] = AMP_1 * Math.sin(kx * KX_1 + p1)
                       + AMP_2 * Math.sin(kx * KX_2 + p2);
            var a = kx * KX_3 + p3;
            colSin[i] = Math.sin(a);
            colCos[i] = Math.cos(a);
        }
        var nCols = i;
        for (i = 0, r = -2; r < rows; r++, i++) {
            var b = r * KR_3;
            rowSin[i] = Math.sin(b);
            rowCos[i] = Math.cos(b);
        }
        var nRows = i;

        // Batch every dot into a single Path2D, then fill once. One GPU
        // submission instead of thousands of fillRect calls.
        var path = new Path2D();
        for (var ri = 0; ri < nRows; ri++) {
            var ry = (ri - 2) * STEP_Y;
            var sb = rowSin[ri], cb = rowCos[ri];
            for (var ci = 0; ci < nCols; ci++) {
                var dy = colFlat[ci] + AMP_3 * (colSin[ci] * cb + colCos[ci] * sb);
                path.rect(colX[ci], ry + dy, DOT, DOT);
            }
        }
        ctx.fill(path);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
    } else {
        mount();
    }

    window.addEventListener('resize', resize);

    // Respect runtime changes to the reduced-motion preference.
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        var listener = function (e) {
            var wasReduced = reducedMotion;
            reducedMotion = e.matches;
            if (wasReduced && !reducedMotion) requestAnimationFrame(loop);
        };
        if (mq.addEventListener) mq.addEventListener('change', listener);
        else if (mq.addListener) mq.addListener(listener);
    }
})();
