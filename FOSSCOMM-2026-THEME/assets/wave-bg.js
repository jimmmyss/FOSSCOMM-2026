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

    function draw(nowMs) {
        var t = (nowMs - startMs) / 1000;
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = COLOUR;

        var rows = Math.ceil(H / STEP_Y) + 4;
        var cols = Math.ceil(W / STEP_X) + 4;

        // Batch every dot into a single Path2D, then fill once. One GPU
        // submission instead of thousands of fillRect calls.
        var path = new Path2D();
        for (var r = -2; r < rows; r++) {
            var ry = r * STEP_Y;
            var rowPhase = r * KR_3;
            for (var c = -2; c < cols; c++) {
                var x  = c * STEP_X;
                var kx = c * STEP_X;
                var dy =
                    AMP_1 * Math.sin(kx * KX_1 + t * SPEED_1) +
                    AMP_2 * Math.sin(kx * KX_2 + t * SPEED_2) +
                    AMP_3 * Math.sin(kx * KX_3 + rowPhase + t * SPEED_3);
                path.rect(x, ry + dy, DOT, DOT);
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
