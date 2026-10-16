/* FOSSCOMM 2026 — global wave background.
 *
 * A grid of hard-edged dots whose Y position is displaced by a sum of sin()
 * waves of x, y and time, so the field reads as a slow topographic surface.
 *
 * This is the SAME dot standard as the exported background assets
 * (backgrounds/*.png, see FOSSCOMM-2026-ASSET-RULEBOOK.txt §7): pitch 40,
 * a 6x6 dot with its four corners off, and a margin of half the pitch. A page
 * and a poster therefore carry the same texture at the same size.
 *
 * Architecture is unchanged: one <canvas> appended to <body>, position fixed,
 * z-index -1. It paints over <html>'s paper and behind every section. Sections
 * that should hide it carry bg-paper; sections that show it carry
 * .fc-section-dots. The paint area is the viewport, so the field stays locked
 * to the screen while the page scrolls.
 *
 * ── THE FOUR THINGS THIS HAS TO GET RIGHT ─────────────────────────────────
 *
 * 1. SHARP. Every dot lands on whole DEVICE pixels. The old field was soft for
 *    two reasons, and both had to go: the dot was 1.6px (a fractional size can
 *    never be crisp), and the wave offset was a raw float, so each dot sat on a
 *    fractional y and the renderer antialiased it. Here the canvas is sized at
 *    an INTEGER devicePixelRatio, the grid is integer, and the wave offset is
 *    rounded to whole device pixels before use.
 *
 * 2. THE SAME DOTS AT ANY SCREEN SIZE. Pitch and dot size are fixed in CSS
 *    pixels, so a wider screen shows MORE dots, never bigger ones, and nothing
 *    reflows when the window is resized. On a 2x display the dot is drawn at
 *    12 device px — the same pixel-doubling the 2x PNG assets use, so it stays
 *    the same size on screen and stays sharp rather than being smoothed up.
 *
 * 3. HALF-GAP EDGES. First dot centre sits half a pitch from the edge, and any
 *    remainder is split between the two edges (see axis() below) — the rule
 *    that lets two of these butt together with no visible seam.
 *
 * 4. CHEAP. The pitch went 12x16 -> 40x40, which is about an 8x cut in dot
 *    count: ~10,900 dots on a 1920x1080 desktop down to ~1,400. That pays for
 *    rendering at device resolution several times over. Per frame only the
 *    dots that moved to a new pixel are redrawn — a few dozen — and the
 *    per-dot trigonometry is factored out into per-column and per-row tables
 *    (see draw()).
 *
 * Animation honours prefers-reduced-motion: one static frame, no loop.
 */
(function () {
    'use strict';

    /* ── The dot standard ──────────────────────────────────────────────── */
    var PITCH = 40;        // centre to centre, CSS px, both axes
    var DOT   = 6;         // the dot mask is DOT x DOT with the corners off
    // Ground/dot pair from the exported assets: ink 10% over paper. The old
    // field used --color-ink-faint (#C9C7BF), which is the hairline grey and
    // noticeably darker; this is the value the PNG backgrounds actually carry.
    var COLOUR = '#E2E2DF';

    /* ── The wave ──────────────────────────────────────────────────────────
     * Amplitudes are unchanged from the previous field, which matters: at the
     * old 16px row step a 34px swing threw rows PAST each other and the grid
     * read as halftone ridges. Against a 40px pitch the same 34px is less than
     * one row, so the grid stays legible and undulates instead of churning —
     * which is what big dots want.
     *
     * KY_3 is the one constant that had to change. It was 0.30 radians PER ROW,
     * so its vertical wavelength was tied to the row step; at 40px rows that
     * would have stretched the wave 2.5x. Expressed per PIXEL (0.30/16) the
     * wavelength stays where it was.
     */
    var AMP_1 = 18, KX_1 = 0.014,  SPEED_1 =  0.45;
    var AMP_2 = 11, KX_2 = 0.027,  SPEED_2 = -0.65;
    var AMP_3 = 5,  KX_3 = 0.060,  KY_3 = 0.30 / 16, SPEED_3 = 0.85;

    var canvas = document.createElement('canvas');
    canvas.id = 'fc-waves-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = [
        'position:fixed', 'inset:0', 'width:100vw', 'height:100vh',
        'z-index:-1', 'pointer-events:none', 'display:block'
    ].join(';');

    var ctx = null, layer = null, W = 0, H = 0, dpr = 1, startMs = 0;
    var reducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Dot centres on one axis, with any remainder CENTRED.
     * For a length that is an exact multiple of the pitch this is simply
     * pitch/2 + pitch*k. For anything else — which a browser viewport almost
     * always is — the leftover is split between the two edges, so the GAPS
     * stay exactly one pitch and only the two outer margins absorb the error.
     * Same rule as the exported assets. */
    function axis(len, pitch) {
        /* FLOOR, not round. A viewport is almost never a multiple of the pitch,
         * and rounding UP squeezes the leftover into the two margins — at
         * 900px wide that left 10px a side instead of the 20px half-gap, so
         * the field ran right up to the edge. Flooring instead keeps the GAPS
         * at exactly one pitch and lets the margin land anywhere in
         * [pitch/2, pitch): never tighter than the half-gap rule, only ever
         * looser, and still symmetric because the remainder is split. */
        var n = Math.floor(len / pitch);
        if (n < 1) n = 1;
        var m = Math.round((len - (n - 1) * pitch) / 2);
        var out = new Float64Array(n);
        for (var k = 0; k < n; k++) out[k] = m + pitch * k;
        return out;
    }

    function mount() {
        if (document.getElementById('fc-waves-canvas')) return;
        document.body.appendChild(canvas);
        layer = dotLayer(canvas, null);                     // the GPU's, where there is one
        if (!layer && canvas.fcTainted) {
            // the GPU path failed after taking the canvas: a fresh one for the 2D
            var fresh = canvas.cloneNode(false);
            canvas.parentNode.replaceChild(fresh, canvas);
            canvas = fresh;
            canvas.addEventListener('contextrestored', onRestored);
        }
        if (!layer) ctx = canvas.getContext('2d', { alpha: true });
        resize();
        startMs = performance.now();
        /* Published so assets/spotlight-dots.js can draw a disc whose dots sit
           exactly on THIS field — same positions, same clock — and the field
           carries on through the disc's edge pixel for pixel instead of two
           grids meeting there. Both are rAF callbacks, so in any one frame they
           are handed the same timestamp. */
        window.FC_WAVE_T0 = startMs;
        if (reducedMotion) hold(startMs);
        else start();
        holdWhenAway('.fc-fund-digits');
    }

    /* Decorations animated on the page's main thread — the funding figure's
       wave is a moving background, repainted every frame it runs — hold still
       while they are off screen, where nobody can see them move. */
    function holdWhenAway(sel) {
        if (!('IntersectionObserver' in window)) return;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                en.target.style.animationPlayState = en.isIntersecting ? '' : 'paused';
            });
        }, { rootMargin: '100px' });
        [].forEach.call(document.querySelectorAll(sel), function (el) { io.observe(el); });
    }

    /* NO GPU? A machine with no GPU (or one the browser will not use) draws
     * the whole page on the CPU, and every frame in which anything on screen
     * changes is composited again pixel by pixel — the hero's turned strips
     * above all. The browser says so when asked for a WebGL context that would
     * be slow. Marked on <html> (data-fc-software) for whatever wants to ease
     * off there; read once. */
    var software = (function () {
        try {
            var c = document.createElement('canvas');
            var gl = c.getContext('webgl', { failIfMajorPerformanceCaveat: true });
            if (!gl) return true;
            var ext = gl.getExtension('WEBGL_debug_renderer_info');
            var name = String(gl.getParameter(ext ? ext.UNMASKED_RENDERER_WEBGL : gl.RENDERER));
            var lose = gl.getExtension('WEBGL_lose_context');
            if (lose) lose.loseContext();
            return /swiftshader|basic render|llvmpipe|softpipe|software|warp/i.test(name);
        } catch (e) { return false; }
    })();
    window.FC_SOFTWARE = software;
    if (software) document.documentElement.setAttribute('data-fc-software', '');

    /* ── THE DOTS ON THE GPU ───────────────────────────────────────────────
     * Drawn on a 2D canvas, every dot that moves is a shape the browser hands
     * to the GPU to be cut into triangles, one by one. A few hundred a frame
     * is nothing; zoomed out, a window holds tens of thousands of dots and a
     * third of them move every frame, and that alone took half of a CPU core
     * in the GPU's process — where the rest of the page is drawn too. So
     * where there is a GPU the dots are drawn with WebGL instead: ALL of them,
     * every time, in one go — one small square each, in which every pixel
     * works out how much of it the dot's three bars cover, exactly. That is
     * what the 2D canvas computes for them too: the same dots, the same
     * pixels (a level in 255 apart at the soft edge of a shrunk dot), for a
     * small fraction of the cost.
     *
     * dotLayer(canvas, ground) is null where there is no GPU (the 2D canvas
     * is used, drawn on the CPU) or no WebGL 2. `ground`: an opaque colour
     * the canvas is filled with under its dots (the blue panels' grounds in
     * assets/spotlight-dots.js); none for a transparent canvas. */
    function rgb(hex) { return [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16) / 255; }); }
    function dotLayer(cv, ground) {
        if (software) return null;
        var gl = null;
        try {
            gl = cv.getContext('webgl2', {
                alpha: !ground, antialias: false, depth: false, stencil: false,
                premultipliedAlpha: true, preserveDrawingBuffer: false,
                failIfMajorPerformanceCaveat: true
            });
        } catch (e) { gl = null; }
        if (!gl) return null;
        /* Two ways in. LIST: each dot's top-left handed over (pos). FIELD: the
         * dots' places worked out here, on the GPU, from the wave's terms —
         * one texel per column (x, the two x-waves, the third wave's sine and
         * cosine) and one per row (y, its sine and cosine) — so a frame of the
         * background costs the page a few hundred numbers, however many dots
         * the window holds. The same sum as the 2D canvas's, rounded the same
         * way (floor(v + 0.5) is JavaScript's Math.round). */
        var CORNER = '  vec2 q = floor(at) - 1.0 + corner * (ceil(6.0 * u * sk) + 3.0);\n'
            + '  gl_Position = vec4(q / size * vec2(2.0, -2.0) + vec2(-1.0, 1.0), 0.0, 1.0);\n';
        var VS = '#version 300 es\n'
            + 'in vec2 corner; in vec2 pos;\n'               // the square's corner (0..1), the dot's top-left (units)
            + 'uniform float sk, u; uniform vec2 size;\n'      // units to pixels, one mask pixel (units), canvas (px)
            + 'flat out vec2 at;\n'
            + 'void main() {\n'
            + '  at = pos * sk;\n'                             // the dot's top-left, canvas px
            + CORNER
            + '}\n';
        var VS_FIELD = '#version 300 es\n'
            + 'in vec2 corner;\n'
            + 'uniform highp sampler2D field; uniform int nc;\n'
            + 'uniform float sk, u, amp3, hd; uniform vec2 size;\n'   // hd: half a dot ("half" is reserved in GLSL)
            + 'flat out vec2 at;\n'
            + 'void main() {\n'
            + '  vec4 c = texelFetch(field, ivec2(gl_InstanceID % nc, 0), 0);\n'    // x, flat, sin, cos
            + '  vec4 r = texelFetch(field, ivec2(gl_InstanceID / nc, 1), 0);\n'    // y, sin, cos
            + '  float dy = c.y + amp3 * (c.z * r.z + c.w * r.y);\n'
            + '  at = vec2(c.x - hd, r.x + floor(dy * u + 0.5) - hd) * sk;\n'
            + CORNER
            + '}\n';
        var FS = '#version 300 es\n'
            + 'precision highp float;\n'
            + 'flat in vec2 at; uniform float sk, u; uniform vec2 size; uniform vec3 colour; out vec4 o;\n'
            + 'float ov(float a0, float a1, float b0, float b1) { return max(0.0, min(a1, b1) - max(a0, b0)); }\n'
            + 'float bar(vec2 p, vec2 a, vec2 b) { return ov(p.x, p.x + 1.0, a.x, b.x) * ov(p.y, p.y + 1.0, a.y, b.y); }\n'
            + 'void main() {\n'
            + '  vec2 p = vec2(floor(gl_FragCoord.x), size.y - 1.0 - floor(gl_FragCoord.y));\n'
            + '  float m = u * sk;\n'                          // the dot: rows of 4, 6, 6, 6, 6, 4 mask pixels
            + '  float c = bar(p, at + vec2(1.0, 0.0) * m, at + vec2(5.0, 1.0) * m)\n'
            + '          + bar(p, at + vec2(0.0, 1.0) * m, at + vec2(6.0, 5.0) * m)\n'
            + '          + bar(p, at + vec2(1.0, 5.0) * m, at + vec2(5.0, 6.0) * m);\n'
            + '  if (c <= 0.0) discard;\n'
            + '  o = vec4(colour, 1.0) * min(c, 1.0);\n'
            + '}\n';
        var st = { lost: false }, cap = 0, buf = null, U = {}, UF = {}, P = null, PF = null, vaoList = null, vaoField = null;
        var tex = null, texW = 0, texData = null;
        function build() {
            function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
            function program(vs) {
                var pr = gl.createProgram();
                gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs));
                gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, FS));
                gl.bindAttribLocation(pr, 0, 'corner');
                gl.bindAttribLocation(pr, 1, 'pos');
                gl.linkProgram(pr);
                if (gl.getProgramParameter(pr, gl.LINK_STATUS)) return pr;
                if (window.console) console.warn('wave-bg: dot shader', gl.getProgramInfoLog(pr));
                return null;
            }
            P = program(VS); PF = program(VS_FIELD);
            if (!P || !PF) return false;
            ['sk', 'u', 'size', 'colour'].forEach(function (k) { U[k] = gl.getUniformLocation(P, k); });
            ['sk', 'u', 'size', 'colour', 'field', 'nc', 'amp3', 'hd'].forEach(function (k) { UF[k] = gl.getUniformLocation(PF, k); });
            var quad = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, quad);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([0, 0, 1, 0, 0, 1, 1, 1]), gl.STATIC_DRAW);
            // LIST: the square's corners, and a top-left per dot
            vaoList = gl.createVertexArray();
            gl.bindVertexArray(vaoList);
            gl.bindBuffer(gl.ARRAY_BUFFER, quad);
            gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
            buf = gl.createBuffer(); cap = 0;
            gl.bindBuffer(gl.ARRAY_BUFFER, buf);
            gl.enableVertexAttribArray(1); gl.vertexAttribPointer(1, 2, gl.FLOAT, false, 0, 0);
            gl.vertexAttribDivisor(1, 1);
            // FIELD: the corners only — the rest comes from the field texture
            vaoField = gl.createVertexArray();
            gl.bindVertexArray(vaoField);
            gl.bindBuffer(gl.ARRAY_BUFFER, quad);
            gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
            gl.bindVertexArray(null);
            tex = gl.createTexture(); texW = 0;
            gl.bindTexture(gl.TEXTURE_2D, tex);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
            gl.enable(gl.BLEND);
            gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);     // premultiplied: the dot over what is there
            return true;
        }
        function begin(colour, prog, Uu, sk, u) {
            st.blank = false;
            gl.viewport(0, 0, cv.width, cv.height);
            gl.useProgram(prog);
            gl.uniform1f(Uu.sk, sk); gl.uniform1f(Uu.u, u);
            gl.uniform2f(Uu.size, cv.width, cv.height);
            var C = rgb(colour); gl.uniform3f(Uu.colour, C[0], C[1], C[2]);
            if (G) gl.clearColor(G[0], G[1], G[2], 1); else gl.clearColor(0, 0, 0, 0);
            gl.clear(gl.COLOR_BUFFER_BIT);
        }
        /* A canvas that has handed out a WebGL context can never draw in 2D:
           if the GPU path cannot be built, the caller is told to use a fresh
           canvas for the 2D one (cv.fcTainted). */
        if (!build()) {
            var lose = gl.getExtension('WEBGL_lose_context');
            if (lose) lose.loseContext();
            cv.fcTainted = true;
            return null;
        }
        // a GPU reset loses everything; it is built again when the context returns
        cv.addEventListener('webglcontextlost', function (e) { e.preventDefault(); st.lost = true; });
        cv.addEventListener('webglcontextrestored', function () { st.lost = !build(); st.blank = true; });
        var G = ground ? rgb(ground) : null;
        return {
            // true when the canvas has nothing on it — back from a lost context
            blank: function () { return !!st.blank; },
            /* draw the canvas whole: `pos` holds each dot's top-left in units,
               x then y; sk = units to canvas px; u = one mask pixel, in units */
            draw: function (pos, n, colour, sk, u) {
                if (st.lost || gl.isContextLost()) return false;
                begin(colour, P, U, sk, u);
                if (n) {
                    gl.bindVertexArray(vaoList);
                    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
                    if (n * 2 > cap) { cap = Math.ceil(n * 2 * 1.25); gl.bufferData(gl.ARRAY_BUFFER, cap * 4, gl.DYNAMIC_DRAW); }
                    gl.bufferSubData(gl.ARRAY_BUFFER, 0, pos, 0, n * 2);
                    gl.drawArraysInstanced(gl.TRIANGLE_STRIP, 0, 4, n);
                    gl.bindVertexArray(null);
                }
                return true;
            },
            /* draw the canvas whole from the field: per column its x (units),
               its two x-waves summed (CSS px) and the third wave's sine and
               cosine; per row its y (units) and the sine and cosine of its
               phase. half = half a dot (units), amp3 = the third wave's height. */
            drawField: function (xs, flat, cs, cc, nc, ys, rs, rc, nr, colour, sk, u, half, amp3) {
                if (st.lost || gl.isContextLost()) return false;
                begin(colour, PF, UF, sk, u);
                if (!nc || !nr) return true;
                var w = Math.max(nc, nr), i, k;
                if (!texData || texData.length < w * 8) texData = new Float32Array(w * 8);
                for (i = 0; i < nc; i++) { k = 4 * i; texData[k] = xs[i]; texData[k + 1] = flat[i]; texData[k + 2] = cs[i]; texData[k + 3] = cc[i]; }
                for (i = 0; i < nr; i++) { k = 4 * (w + i); texData[k] = ys[i]; texData[k + 1] = rs[i]; texData[k + 2] = rc[i]; texData[k + 3] = 0; }
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, tex);
                if (w !== texW) { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA32F, w, 2, 0, gl.RGBA, gl.FLOAT, texData, 0); texW = w; }
                else gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, w, 2, gl.RGBA, gl.FLOAT, texData, 0);
                gl.uniform1i(UF.field, 0); gl.uniform1i(UF.nc, nc);
                gl.uniform1f(UF.amp3, amp3); gl.uniform1f(UF.hd, half);
                gl.bindVertexArray(vaoField);
                gl.drawArraysInstanced(gl.TRIANGLE_STRIP, 0, 4, nc * nr);
                gl.bindVertexArray(null);
                return true;
            }
        };
    }
    window.FC_DOT_LAYER = dotLayer;

    /* HOW OFTEN. A dot moves in whole device pixels and at most ~20px a
     * second, so a step comes every 50ms at the very fastest: drawing the
     * field more often than that changes nothing on screen. It is drawn at
     * most 60 times a second (every frame on a 60Hz screen, every other one at
     * 120 or 144), and on a machine with no GPU 30 times a second, on the
     * page's beat (below). FC_WAVE_NOW is the instant the field on screen was drawn for:
     * assets/spotlight-dots.js draws its own dots for that same instant, so
     * the two fields agree pixel for pixel whatever their frames. */
    var EVERY = 1000 / 60, lastDraw = 0;

    /* With no GPU, every frame in which anything on screen changes costs the
     * CPU a whole composite, and things changing on different frames — the
     * field here, the hero's strips, the cursor's trail — added up to every
     * frame. So there everything that moves keeps ONE beat, 30 a second, and
     * the frames between beats have nothing new to draw: this field, the
     * trail (assets/spotlight-dots.js), the globe (every other beat,
     * assets/venue-map.js) and the hero's strips, whose marquees run in 30
     * steps a second (see hero.php) and are kept on the beat here — a marquee
     * resumed after a hover or a drag starts off the beat, and is put back on
     * it. FC_SLOT is the beat; 0 where there is a GPU and none is needed. */
    var SLOT = software ? 1000 / 30 : 0, lastSlot = -1;
    window.FC_SLOT = SLOT;
    if (SLOT) {
        setInterval(function () {
            if (!document.getAnimations) return;
            document.getAnimations().forEach(function (a) {
                if (a.animationName !== 'fc-band-marquee' || a.playState !== 'running' || a.startTime == null) return;
                var on = Math.round(a.startTime / SLOT) * SLOT;
                if (Math.abs(a.startTime - on) > 0.5) a.startTime = on;
            });
        }, 500);
    }

    /* ONE loop at a time: started where there is motion, and stopping itself
     * the frame reduced motion is asked for — the field then holds where it
     * is. `stillAt` is the instant a held field is drawn for, published as
     * FC_WAVE_NOW like any other, so the spotlight's dots hold with it. */
    var looping = false, stillAt = null;
    function start() {
        if (looping) return;
        looping = true; stillAt = null;
        requestAnimationFrame(loop);
    }
    function hold(at) {
        stillAt = at;
        draw(at);
        window.FC_WAVE_NOW = at;
    }
    function loop(t) {
        if (reducedMotion) { looping = false; hold(lastDraw || startMs); return; }
        tick(t);
        requestAnimationFrame(loop);
    }
    /* The frame's step: once per frame, whoever asks first. The spotlight
     * (assets/spotlight-dots.js) draws its dots for FC_WAVE_NOW, so the field
     * must have moved on BEFORE it draws — and the order of two rAF callbacks
     * is only the order they were asked in: this loop restarted after the
     * spotlight's (reduced motion turned off mid-visit) would run after it,
     * every frame, one instant behind. So the spotlight asks for the step
     * itself first (FC_WAVE_TICK), and the second ask in a frame is a no-op. */
    var lastTick = -1;
    function tick(t) {
        if (t === lastTick) return;
        lastTick = t;
        var due = SLOT ? Math.floor(t / SLOT) !== lastSlot : !lastDraw || t - lastDraw >= EVERY * 0.8;
        if (due) {
            draw(t);
            lastDraw = t;
            if (SLOT) lastSlot = Math.floor(t / SLOT);
            window.FC_WAVE_NOW = t;
        }
    }
    window.FC_WAVE_TICK = function (t) { if (looping && !reducedMotion) tick(t); };
    /* The field drawn again whole for the instant on screen, at once: after a
     * resize or a lost context, whose canvas is blank — left to the loop, the
     * frames before its next due one (most of them, at 144Hz or on the 30/s
     * beat) showed no field at all. */
    function redraw() {
        full = true;
        if (stillAt != null) draw(stillAt);
        else if (lastDraw) draw(lastDraw);
    }

    var cols = null, rowsBase = null;   // device-px grid, rebuilt on resize

    function resize() {
        if (!ctx && !layer) return;
        W = window.innerWidth;
        H = window.innerHeight;
        /* INTEGER device pixel ratio, capped at 2.
         * Integer because the dot mask is scaled by it: at a fractional ratio
         * the 6 mask pixels would land on fractional device pixels and the
         * whole point — sharpness — would be lost. Capped because past 2x the
         * extra pixels cost real fill time and buy nothing the eye can see on
         * a background. */
        dpr = Math.max(1, Math.min(2, Math.round(window.devicePixelRatio || 1)));
        /* ZOOMED OUT the ratio is below 1 — 0.5 at 50% — and the canvas used to
         * be drawn at one pixel per CSS pixel anyway: four times the screen's
         * pixels at 50%, every frame, for the browser to shrink back down. It
         * is drawn at the screen's own resolution now: the same field, laid
         * out exactly as before in the same units, drawn through a scale —
         * which is all the shrinking did, without the four times the work. */
        shrink = Math.min(1, window.devicePixelRatio || 1);
        unitW = Math.round(W * dpr); unitH = Math.round(H * dpr);
        canvas.width  = Math.max(1, Math.round(unitW * shrink));
        canvas.height = Math.max(1, Math.round(unitH * shrink));
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        if (ctx) ctx.setTransform(shrink, 0, 0, shrink, 0, 0);

        // grid in DEVICE pixels (units, at a ratio below 1), integers throughout
        var p = PITCH * dpr;
        var xs = axis(unitW, p);
        var ys = axis(unitH, p);
        cols = new Float64Array(xs.length);
        for (var i = 0; i < xs.length; i++) cols[i] = Math.round(xs[i]);
        /* One extra row above and below. The wave lifts dots by up to
         * AMP_1+AMP_2+AMP_3 px, so without them the field would pull away from
         * the top and bottom edges and leave a bald strip. */
        rowsBase = new Float64Array(ys.length + 2);
        rowsBase[0] = Math.round(ys[0] - p);
        for (var j = 0; j < ys.length; j++) rowsBase[j + 1] = Math.round(ys[j]);
        rowsBase[ys.length + 1] = Math.round(ys[ys.length - 1] + p);

        haveCols = -1; haveRows = -1;     // force the trig tables to rebuild
        full = true;                      // a resized canvas starts blank
        redraw();
    }

    /* Scratch buffers, grown once and reused — allocating these per frame
       would hand the collector a few hundred KB a second for nothing. */
    var colFlat = null, colSin = null, colCos = null;
    var rowSin = null, rowCos = null;
    var haveCols = -1, haveRows = -1;
    // where each dot is drawn now (device px), and whether the canvas has to be
    // drawn whole — the first frame, after a resize, after a lost context
    var shownY = null, full = true;
    // below a ratio of 1: the canvas's scale, and its size in drawing units
    var shrink = 1, unitW = 0, unitH = 0;

    function draw(nowMs) {
        if (!cols || !rowsBase) return;
        var t = (nowMs - startMs) / 1000;

        var nCols = cols.length, nRows = rowsBase.length;

        /* ── separate what varies by column from what varies by row ──────────
         * Waves 1 and 2 depend on x and t only, so every row was recomputing
         * the same value. Wave 3 does depend on y, but only as an added phase,
         * and sin(A+B) = sinA cosB + cosA sinB splits that into a column part
         * and a row part. Precompute both and the inner loop needs no
         * transcendental at all: 3*rows*cols sin() calls become 4*cols + 2*rows.
         */
        if (nCols !== haveCols) {
            colFlat = new Float64Array(nCols);
            colSin  = new Float64Array(nCols);
            colCos  = new Float64Array(nCols);
            haveCols = nCols;
        }
        if (nRows !== haveRows) {
            rowSin = new Float64Array(nRows);
            rowCos = new Float64Array(nRows);
            haveRows = nRows;
        }

        var p1 = t * SPEED_1, p2 = t * SPEED_2, p3 = t * SPEED_3;
        var i;
        for (i = 0; i < nCols; i++) {
            var xCss = cols[i] / dpr;                       // waves are specified in CSS px
            colFlat[i] = AMP_1 * Math.sin(xCss * KX_1 + p1)
                       + AMP_2 * Math.sin(xCss * KX_2 + p2);
            var a = xCss * KX_3 + p3;
            colSin[i] = Math.sin(a);
            colCos[i] = Math.cos(a);
        }
        for (i = 0; i < nRows; i++) {
            var b = (rowsBase[i] / dpr) * KY_3;
            rowSin[i] = Math.sin(b);
            rowCos[i] = Math.cos(b);
        }

        /* The dot: a DOT x DOT square with the four corners removed, which at
         * DOT = 6 gives rows of 4,6,6,6,6,4 = 32 pixels — the same mask the PNG
         * assets rasterise. Three rects express it exactly, and each mask pixel
         * is dpr device px square, so the shape pixel-doubles on a 2x screen
         * rather than being resampled. */
        var u = dpr;                       // one mask pixel, in device px
        var half = Math.round(DOT * dpr / 2);

        /* ONLY THE DOTS THAT MOVED. A dot drifts at most ~20px a second, and
         * moves in whole device pixels, so in any one frame only a few dozen of
         * the ~1,400 land somewhere new. Each of those is cleared where it was
         * and drawn where it is; the rest of the canvas is left as it is. The
         * pixels are exactly what a full redraw gives — no two dots' boxes
         * ever overlap (40px apart; a column's neighbours drift together) —
         * for a fraction of the work, every frame, on every page. The whole
         * field is drawn only on the first frame and after a resize. */
        var n = nRows * nCols;
        if (!shownY || shownY.length !== n) { shownY = new Float64Array(n); full = true; }
        var ri, ci, sb, cb, yb, x, y, k = 0;
        /* WITH A GPU (dotLayer, above): the field's terms only — a texel a
           column and a texel a row — and the GPU puts every dot in its place
           and draws it, in one go. Nothing per dot here. */
        if (layer) {
            full = !layer.drawField(cols, colFlat, colSin, colCos, nCols, rowsBase, rowSin, rowCos, nRows,
                                    COLOUR, shrink, u, half, AMP_3);
            return;
        }
        ctx.fillStyle = COLOUR;
        if (full) {                        // the whole canvas, to its last screen pixel
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.setTransform(shrink, 0, 0, shrink, 0, 0);
        }
        /* Shrunk, a dot's edges fall between screen pixels and are drawn soft;
           its old place is cleared a screen pixel wider all round so nothing
           of it is left behind (the next dot is 30 units off at the closest). */
        var e = shrink < 1 ? Math.ceil(1 / shrink) : 0;
        var path = null;
        for (ri = 0; ri < nRows; ri++) {
            sb = rowSin[ri]; cb = rowCos[ri];
            yb = rowsBase[ri];
            for (ci = 0; ci < nCols; ci++, k++) {
                var dyCss = colFlat[ci] + AMP_3 * (colSin[ci] * cb + colCos[ci] * sb);
                // round to WHOLE device pixels — the step that keeps it sharp
                y = yb + Math.round(dyCss * dpr) - half;
                if (!full && y === shownY[k]) continue;
                x = cols[ci] - half;
                if (!full) ctx.clearRect(x - e, shownY[k] - e, u * 6 + 2 * e, u * 6 + 2 * e);   // where it was
                shownY[k] = y;
                if (!path) path = new Path2D();
                path.rect(x + u,     y,         u * 4, u);
                path.rect(x,         y + u,     u * 6, u * 4);
                path.rect(x + u,     y + u * 5, u * 4, u);
            }
        }
        full = false;
        if (path) ctx.fill(path);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
    } else {
        mount();
    }

    window.addEventListener('resize', resize);
    // a GPU reset wipes the canvas: draw it whole again
    function onRestored() { redraw(); }
    canvas.addEventListener('contextrestored', onRestored);
    // …and the GPU's: dotLayer rebuilds itself first (its listener may come
    // after this one), so a moment later
    canvas.addEventListener('webglcontextrestored', function () { setTimeout(redraw, 0); });

    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        // the loop stops itself on the next frame when motion is turned off
        var listener = function (e) {
            reducedMotion = e.matches;
            if (!reducedMotion && startMs) start();
        };
        if (mq.addEventListener) mq.addEventListener('change', listener);
        else if (mq.addListener) mq.addListener(listener);
    }
})();
