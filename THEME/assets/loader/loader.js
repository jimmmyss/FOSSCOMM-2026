/* FOSSCOMM 2026 — the loading screen, as a factory.
 *
 * window.FCLoader = { create, readConfig, EASE }. Defining it is ALL this file
 * does: nothing starts by itself. The front end runs it from assets/loader/boot.js
 * (both printed inline by inc/loader.php, right after the overlay's markup); the
 * dashboard runs the same factory on a preview box, which is the only way its
 * preview can be trusted to show what the site will do.
 *
 * A classic script rather than a module, on purpose: it has to run the moment the
 * overlay exists, in the middle of the HTML, and a module would wait for the whole
 * document. That is also why the mascot's squash maths is COPIED below rather than
 * imported — keep the two in step (assets/mascot/js/view.js and physics.js).
 *
 * State is written to the overlay as data-* attributes and inline styles only,
 * never classes. The Tailwind CDN runtime rescans the whole document on every
 * class change or inserted node, so the only nodes this ever adds on the front
 * end are the copies of the site the layers carry (scenes()), added once while
 * the lit logo is still up and nothing is moving.
 */
(function (w) {
    'use strict';
    if (w.FCLoader) return;

    /* The three curves the dashboard offers; mirrors fc_loader_eases(). */
    var EASE = {
        persona: 'cubic-bezier(0.76, 0, 0.24, 1)',
        smooth: 'cubic-bezier(0.65, 0, 0.35, 1)',
        glide: 'cubic-bezier(0.7, 0, 0.2, 1)'
    };

    /* The same curves as functions of progress, for the swipe, which is drawn
       frame by frame (see exit()). Standard cubic-bezier: solve x(t) for t with
       Newton's method, falling back to bisection, then return y(t). */
    function bezier(x1, y1, x2, y2) {
        var cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
        var cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
        function sx(t) { return ((ax * t + bx) * t + cx) * t; }
        function sy(t) { return ((ay * t + by) * t + cy) * t; }
        function dx(t) { return (3 * ax * t + 2 * bx) * t + cx; }
        function solve(x) {
            var t = x, i, d, e;
            for (i = 0; i < 8; i++) {
                e = sx(t) - x;
                if (Math.abs(e) < 1e-6) return t;
                d = dx(t);
                if (Math.abs(d) < 1e-6) break;
                t -= e / d;
            }
            var lo = 0, hi = 1;
            t = x;
            for (i = 0; i < 40; i++) {
                e = sx(t);
                if (Math.abs(e - x) < 1e-6) break;
                if (x > e) lo = t; else hi = t;
                t = (lo + hi) / 2;
            }
            return t;
        }
        return function (p) { return p <= 0 ? 0 : p >= 1 ? 1 : sy(solve(p)); };
    }
    var EASE_FN = {
        persona: bezier(0.76, 0, 0.24, 1),
        smooth: bezier(0.65, 0, 0.35, 1),
        glide: bezier(0.7, 0, 0.2, 1)
    };

    // The most steps a timeline may have, "Stays on" included (FC_LOADER_MAX_STEPS).
    var MAX_STEPS = 60;

    var DEFAULT_STEPS = [
        { on: false, ms: 500, bounce: 0 }, { on: true, ms: 60, bounce: 0.6 },
        { on: false, ms: 90, bounce: 0 }, { on: true, ms: 40, bounce: 0.4 },
        { on: false, ms: 280, bounce: 0 }, { on: true, ms: 120, bounce: 0.8 },
        { on: false, ms: 70, bounce: 0 }, { on: true, ms: 600, bounce: 2 }
    ];

    /* ── The mascot's squash and stretch, mirrored ─────────────────────────────
     * view.js 36-110 (squashDepth, squashPop, POP_MAX, ENTER_BOUNCE, SQUASH_MAX,
     * squashStack) and physics.js 159-239 (the spring: K 120, C 12, 940ms
     * reference, retimed by ms/940 with K/a² and C/a so the SHAPE stays the same
     * at every length, sub-stepped so dt·√k ≤ 0.05, and the settle test retimed
     * with it). A bounciness of 1 here is a bounciness of 1 on the mascot. */
    var ENTER_BOUNCE = 0.18;
    var POP_MAX = 0.6;
    var SQUASH_MAX = 0.8;
    var SQUASH_K = 120;
    var SQUASH_C = 12;
    var SQUASH_MS = 940;
    var SQUASH_MAX_STEP = 0.05;

    function squashDepth(b) { return b > 0 ? 0.86 * (b / (b + 1.45)) : 0; }
    function squashPop(base, b) { return b > 0 ? Math.min(POP_MAX, base * b) : 0; }
    function squashStack(cur, pop, b) {
        var depth = squashDepth(b);
        return depth > 0 ? Math.min(SQUASH_MAX / depth, cur + pop) : cur;
    }
    function stepSquash(s, dt, ms) {
        if (s.squash === 0 && !s.squashVel) return;
        var a = (isFinite(ms) && ms > 0) ? ms / SQUASH_MS : 1;
        var k = a === 1 ? SQUASH_K : SQUASH_K / (a * a);
        var c = a === 1 ? SQUASH_C : SQUASH_C / a;
        var n = Math.max(1, Math.ceil((dt * Math.sqrt(k)) / SQUASH_MAX_STEP));
        var h = dt / n;
        var vel = s.squashVel || 0;
        var pos = s.squash;
        for (var i = 0; i < n; i++) {
            vel += (-k * pos - c * vel) * h;
            pos += vel * h;
        }
        s.squashVel = vel;
        s.squash = pos;
        if (Math.abs(s.squash) < 0.002 && Math.abs(s.squashVel) < 0.02 / a) {
            s.squash = 0;
            s.squashVel = 0;
        }
    }

    /* ── The page background's dot field, mirrored ───────────────────────────
     * The white layer carries the site's own dots, lined up with them exactly:
     * the same grid, the same wave, drawn for the same instant as
     * assets/wave-bg.js draws the background (it publishes its clock as
     * FC_WAVE_T0 / FC_WAVE_NOW, and its GPU drawer as FC_DOT_LAYER). The same
     * constants as there — keep the three in step: wave-bg.js, this, and
     * assets/spotlight-dots.js, which mirrors the field the same way. */
    var DOT_PITCH = 40, DOT_SIZE = 6, DOT_COLOUR = '#E2E2DF';
    var AMP_1 = 18, KX_1 = 0.014, SPEED_1 = 0.45;
    var AMP_2 = 11, KX_2 = 0.027, SPEED_2 = -0.65;
    var AMP_3 = 5,  KX_3 = 0.060, KY_3 = 0.30 / 16, SPEED_3 = 0.85;
    /* wave-bg.js axis(): dot centres along one side, the remainder split
       between the two edges. */
    function dotAxis(len, pitch) {
        var n = Math.floor(len / pitch);
        if (n < 1) n = 1;
        var m = Math.round((len - (n - 1) * pitch) / 2);
        var out = new Float64Array(n);
        for (var k = 0; k < n; k++) out[k] = m + pitch * k;
        return out;
    }

    /* ── Config ──────────────────────────────────────────────────────────────
     * The ONE place in JS that reads the raw payload (inc/loader.php
     * fc_loader_js_config(), or the dashboard's form). Same ranges and fallbacks
     * as fc_loader_sanitize(), so nothing downstream has to defend itself. */
    function num(v, f) { var n = typeof v === 'number' ? v : parseFloat(v); return isFinite(n) ? n : f; }
    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function bool(v, f) {
        if (v === undefined || v === null || v === '') return f;
        return !(v === false || v === 0 || v === '0' || v === 'false');
    }
    function hex(v, f) { return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(v || '')) ? String(v) : f; }

    function readConfig(raw) {
        raw = raw && typeof raw === 'object' ? raw : {};
        var steps = [];
        (Array.isArray(raw.steps) ? raw.steps : []).forEach(function (r) {
            if (!r || typeof r !== 'object' || steps.length >= 1000) return;
            steps.push({
                on: r.state === 'on' || r.on === true,
                ms: clamp(Math.round(num(r.ms, 120)), 16, 10000),
                bounce: clamp(num(r.bounce, 0), 0, 10)
            });
        });
        if (!steps.length) steps = DEFAULT_STEPS.map(function (s) { return { on: s.on, ms: s.ms, bounce: s.bounce }; });
        // At most MAX_STEPS, losing rows from the middle and never the last:
        // fc_loader_sanitize_steps() does the same.
        if (steps.length > MAX_STEPS) steps = steps.slice(0, MAX_STEPS - 1).concat([steps[steps.length - 1]]);
        steps[steps.length - 1].on = true;     // the last one is the light staying on
        var easeName = EASE[raw.ease] ? raw.ease : 'persona';

        return {
            sizeDesktop: clamp(num(raw.size_desktop, 220), 24, 1200),
            sizeMobile: clamp(num(raw.size_mobile, 140), 24, 1200),
            // '' keeps a logo in its own colours; the placeholders fall back to
            // black (off) and white (on).
            tintOff: hex(raw.tint_off, ''),
            tintOn: hex(raw.tint_on, ''),
            glow: clamp(num(raw.glow, 40), 0, 100) / 100,
            glowSize: clamp(num(raw.glow_size, 60), 0, 400),
            glowColour: hex(raw.glow_colour, '#FFFFFF'),
            glowStyle: raw.glow_style === 'neon' || raw.glow_style === 'both' ? raw.glow_style : 'bloom',
            bounceOrigin: raw.bounce_origin === 'centre' ? 'centre' : 'bottom',
            bounceMs: clamp(num(raw.bounce_ms, 700), 100, 3000),
            steps: steps,
            maxWaitMs: clamp(num(raw.max_wait_s, 8), 1, 30) * 1000,
            buzz: bool(raw.buzz, true),
            mascot: bool(raw.mascot, false),
            fallAt: raw.fall_at === 'reveal' || raw.fall_at === 'swipe' ? raw.fall_at : 'floor',
            // the swipe timed to reach him as Opening ends, or started after it
            swipeSync: raw.swipe_sync === 'after' ? 'after' : 'reach',
            swipeDelay: clamp(num(raw.swipe_delay, 0), 0, 3000),
            colours: {
                layer1: hex(raw.layer1, '#0033FF'),
                layer2: hex(raw.layer2, '#FAFAF7'),
                layer3: hex(raw.layer3, '#0033FF')
            },
            swipeMs: clamp(num(raw.swipe_ms, 1000), 200, 5000),
            gapMs: clamp(num(raw.gap_ms, 420), 0, 5000),
            ease: EASE[easeName],
            easeFn: EASE_FN[easeName],
            edge: raw.edge === 'wave' ? 'wave' : 'straight',
            waveHeight: clamp(num(raw.wave_height, 26), 0, 200),
            waveLength: clamp(num(raw.wave_length, 340), 40, 3000),
            waveSpeed: clamp(num(raw.wave_speed, 1.2), 0, 6),
            dots: bool(raw.layer2_dots, true),
            spot: bool(raw.layer2_spot, true),
            build: bool(raw.reveal_build, true),
            breakpoint: num(raw.breakpoint, 1024),
            // the longest Opening is waited for (inc/loader.php FC_LOADER_OPENING_CAP)
            openingCapMs: clamp(num(raw.opening_cap_ms, 20000), 1000, 120000),
            repeat: raw.repeat === 'session' ? 'session' : 'every',
            preview: bool(raw.preview, false),
            slowMs: clamp(num(raw.slow_ms, 0), 0, 20000)
        };
    }

    /* The URL a logo slot draws, read back out of its --src: url("…"). */
    function artUrl(node) {
        if (!node) return '';
        var v = node.style.getPropertyValue('--src') || '';
        var m = /url\(\s*(["']?)(.*?)\1\s*\)/.exec(v);
        return m ? m[2].replace(/%22/g, '"') : '';
    }

    /* ── Copies of the site, for the layers to carry ─────────────────────────
     * A copy is the real element cloned, with the box values that depend on WHERE
     * it sits in the page frozen from the original: the wordmark's size comes from
     * a custom property only the hero defines, so a plain clone outside the hero
     * would come out at the wrong size. Colours are set per layer (scenes()). */
    var FREEZE = [
        'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
        'text-transform', 'text-align', 'white-space', 'display', 'box-sizing',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'height', 'flex-direction', 'align-items', 'justify-content', 'column-gap', 'row-gap',
        '-webkit-text-stroke-width'
    ];
    function freeze(from, to) {
        var cs = w.getComputedStyle(from);
        FREEZE.forEach(function (p) { to.style.setProperty(p, cs.getPropertyValue(p)); });
        for (var i = 0; i < from.children.length && i < to.children.length; i++) freeze(from.children[i], to.children[i]);
    }
    /* Light or dark, by the colour's own lightness — so swapping the layers'
       colours in the dashboard keeps every copy readable on its new ground. */
    function rgbOf(colour) {
        var s = String(colour || '').trim(), m;
        if ((m = /^#([0-9a-f]{3})$/i.exec(s))) s = '#' + m[1][0] + m[1][0] + m[1][1] + m[1][1] + m[1][2] + m[1][2];
        if ((m = /^#([0-9a-f]{6})$/i.exec(s))) {
            var n = parseInt(m[1], 16);
            return [(n >> 16) & 255, (n >> 8) & 255, n & 255, 1];
        }
        if ((m = /^rgba?\(([^)]+)\)$/i.exec(s))) {
            var p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat);
            return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1];
        }
        return null;
    }
    function isLight(colour) {
        var c = rgbOf(colour);
        if (!c) return true;
        return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255 > 0.6;
    }
    function sameColour(a, b) {
        var x = rgbOf(a), y = rgbOf(b);
        return !!(x && y && x[3] > 0.99 && y[3] > 0.99
            && Math.abs(x[0] - y[0]) < 2 && Math.abs(x[1] - y[1]) < 2 && Math.abs(x[2] - y[2]) < 2);
    }
    function px(n) { return (Math.round(n * 100) / 100) + 'px'; }

    /* ── The controller ──────────────────────────────────────────────────────
     * root     the .fc-loader element (fc_loader_markup()).
     * options  stage:     true in the dashboard — no page-level anything.
     *          direction: 'auto' (the hero's breakpoint, read when the swipe
     *                     starts) | 'left' | 'up'.
     *          maxLogo:   cap on the logo's width in px (the preview box).
     *          onStep:    called with each flicker step's index as it starts
     *                     (the dashboard lights up that cell of its timeline). */
    function create(root, rawConfig, options) {
        options = options || {};
        var cfg = readConfig(rawConfig);
        var q = function (sel) { return root.querySelector(sel); };
        var el = {
            layer1: q('[data-fcl-layer1]'),
            layer2: q('[data-fcl-layer2]'),
            layer3: q('[data-fcl-layer3]'),
            fill3: q('[data-fcl-fill]'),
            logo: q('[data-fcl-logo]'),
            bounce: q('[data-fcl-bounce]'),
            off: q('[data-fcl-off]'),
            on: q('[data-fcl-on]')
        };
        el.scene2 = el.layer2 && el.layer2.querySelector('[data-fcl-scene]');
        el.scene3 = el.layer3 && el.layer3.querySelector('[data-fcl-scene]');
        // What each logo slot is drawing: the file's URL, '' for the placeholder.
        var src = { off: artUrl(el.off), on: artUrl(el.on) };

        var spring = { squash: 0, squashVel: 0 };
        var depth = 1;          // the bounciness of the pop still in flight
        var raf = 0;
        var lastTs = 0;
        var timers = [];
        var anims = [];
        var run = 0;            // bumped by reset(): a flicker from an older run stops
        var buzzing = false;
        var buzzTimer = 0;

        function later(fn, ms) { var id = setTimeout(fn, ms); timers.push(id); return id; }

        function applyConfig() {
            var c = cfg.colours;
            var s = root.style;
            s.setProperty('--fcl-l1', c.layer1);
            s.setProperty('--fcl-l2', c.layer2);
            s.setProperty('--fcl-l3', c.layer3);
            var wd = cfg.sizeDesktop;
            if (options.maxLogo) wd = Math.min(wd, options.maxLogo);
            s.setProperty('--fcl-w', wd + 'px');
            s.setProperty('--fcl-w-m', cfg.sizeMobile + 'px');
            s.setProperty('--fcl-glow', String(cfg.glow));
            s.setProperty('--fcl-glow-size', cfg.glowSize + 'px');
            s.setProperty('--fcl-glow-c', cfg.glowColour);
            s.setProperty('--fcl-origin', cfg.bounceOrigin === 'centre' ? '50% 50%' : '50% 100%');
            root.setAttribute('data-glow', cfg.glowStyle);
            paintArt('off');
            paintArt('on');
        }

        /* A logo slot (fc_loader_art()): the file in its own colours ("img"), its
           shape filled with the chosen colour ("mask"), or the placeholder square
           ("ph"). Attribute and custom-property writes only — no nodes. */
        function paintArt(which) {
            var node = el[which];
            if (!node) return;
            var tint = which === 'on' ? cfg.tintOn : cfg.tintOff;
            var url = src[which];
            node.setAttribute('data-mode', url ? (tint ? 'mask' : 'img') : 'ph');
            if (url) node.style.setProperty('--src', 'url("' + url.replace(/"/g, '%22') + '")');
            else node.style.removeProperty('--src');
            node.style.setProperty('--tint', tint || (which === 'on' ? '#FFFFFF' : '#000000'));
        }

        /* Lit or dark. A hard cut on purpose — a tube does not fade. litSince is
           when it last came on, for litFor(). */
        var litSince = 0;
        function isLit() { return root.getAttribute('data-light') === 'on'; }
        function light(on) {
            if (on && !isLit()) litSince = performance.now();
            if (!on) litSince = 0;
            root.setAttribute('data-light', on ? 'on' : 'off');
        }
        /* How long the logo has been lit without a break, in ms; 0 while dark.
           The front end holds the reveal until this reaches the timeline's last
           hold, so whatever the light did while the page was loading, the first
           layer only ever moves once the logo is finally, steadily on. */
        function litFor() { return litSince ? performance.now() - litSince : 0; }

        /* Squash: scaleY = 1 − a, scaleX = 1 + 0.8a, exactly as the mascot's
           place() draws it. The spring swings negative after a pop, which is the
           stretch on the rebound. */
        function draw() {
            if (!el.bounce) return;
            var a = clamp(spring.squash * squashDepth(depth), -SQUASH_MAX, SQUASH_MAX);
            el.bounce.style.transform = Math.abs(a) < 0.002 ? ''
                : 'scaleX(' + (1 + a * 0.8).toFixed(3) + ') scaleY(' + (1 - a).toFixed(3) + ')';
        }
        function frame(ts) {
            raf = 0;
            var dt = lastTs ? Math.min(100, ts - lastTs) : 16;
            lastTs = ts;
            stepSquash(spring, dt / 1000, cfg.bounceMs);
            draw();
            if (spring.squash !== 0 || spring.squashVel !== 0) raf = requestAnimationFrame(frame);
            else lastTs = 0;
        }
        /* A pop is instant — the flattest frame first, then the spring brings it
           back — and pops stack, exactly like the mascot's. */
        function pop(b) {
            var p = squashPop(ENTER_BOUNCE, b);
            if (!(p > 0)) return;
            spring.squash = squashStack(spring.squash, p, b);
            depth = b;
            draw();
            if (!raf) { lastTs = 0; raf = requestAnimationFrame(frame); }
        }

        /* Play the timeline from dark. Resolves true once the last step's hold
           has passed (lit), false if a reset() — or another flicker — overtook
           it: reset() clears the timer the next step waits on, so it settles the
           promise itself (flickerDone) rather than leave it pending for good. */
        var flickerDone = null;
        function flicker() {
            var mine = ++run;
            var steps = cfg.steps;
            var i = 0;
            if (flickerDone) { var was = flickerDone; flickerDone = null; was(false); }
            light(false);
            return new Promise(function (resolve) {
                flickerDone = resolve;
                function settle(ok) {
                    if (flickerDone === resolve) flickerDone = null;
                    resolve(ok);
                }
                (function next() {
                    if (mine !== run) { settle(false); return; }
                    if (i >= steps.length) { settle(true); return; }
                    var step = steps[i++];
                    light(step.on);
                    if (step.bounce > 0) pop(step.bounce);
                    if (typeof options.onStep === 'function') options.onStep(i - 1);
                    later(next, step.ms);
                })();
            });
        }

        /* Still loading after the flicker: the odd stutter, so a lit logo that
           sits there for seconds still reads as alive. Stopped in the middle of
           a stutter, the light comes back on as the timeline's last step does —
           with its pop — because that is the light coming on for good. */
        function buzz(on) {
            if (!on) {
                if (!buzzing) return;
                buzzing = false;
                clearTimeout(buzzTimer);
                if (!isLit()) {
                    light(true);
                    pop(cfg.steps[cfg.steps.length - 1].bounce);
                }
                return;
            }
            if (buzzing) return;
            buzzing = true;
            (function again() {
                buzzTimer = setTimeout(function () {
                    if (!buzzing) return;
                    light(false);
                    buzzTimer = setTimeout(function () {
                        if (!buzzing) return;
                        light(true);
                        pop(0.3);
                        again();
                    }, 50 + Math.random() * 40);
                }, 1400 + Math.random() * 1800);
            })();
        }

        /* Where the logo sits, in viewport coordinates — read off the full-size
           centring wrapper, so a squash in progress does not move the answer. */
        function logoCentre() {
            var r = (el.logo || root).getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        }

        function hideLogo() { root.setAttribute('data-logo', 'hidden'); }

        function cancelAnims() {
            anims.forEach(function (a) { try { a.cancel(); } catch (e) {} });
            anims = [];
        }

        function direction() {
            if (options.direction === 'left' || options.direction === 'up') return options.direction;
            return w.matchMedia && w.matchMedia('(min-width: ' + cfg.breakpoint + 'px)').matches ? 'left' : 'up';
        }

        /* ── The swipe ───────────────────────────────────────────────────────
         * Three layers leave one after another towards the hero's blue panel —
         * the blue, then the white, then blue again — each `gap` after the last,
         * and the site is under the third.
         *
         * DRAWN FRAME BY FRAME, as a clip on each layer, rather than handed to
         * the browser as an animation. A layer the browser animates for itself
         * is moved onto a layer of the graphics card's, and type drawn there is
         * smoothed differently from type on the page — greyscale against the
         * page's ClearType on a Windows screen — so the copies of the top bar
         * and the wordmark came out a shade lighter than the real ones and the
         * difference showed the moment the real ones took over. A clip written
         * each frame leaves everything where the page would draw it.
         *
         * The edge is straight, or a WAVE: crests `waveHeight` px from the
         * centre line, `waveLength` px apart, running along the edge at
         * `waveSpeed` crests a second. The edge starts a whole wave clear of the
         * screen and ends one clear of it, so nothing shows before it starts or
         * after it is done. */
        var sweep = null;
        var sweepRaf = 0;
        var sweepDone = null;

        /* The edge's position along the travel at a point `s` across it, for
           layer i (0, 1, 2) at `t` ms into the swipe `sw`. The visible part of
           the layer is [0, front] along the travel. */
        function frontWith(sw, i, s, t) {
            var p = clamp((t - sw.starts[i]) / cfg.swipeMs, 0, 1);
            var e = cfg.easeFn(p);
            var A = sw.wave;
            var base = sw.size + A - e * (sw.size + 2 * A);
            if (!A) return base;
            return base + A * Math.sin(2 * Math.PI * (s / cfg.waveLength + cfg.waveSpeed * t / 1000) + i * 1.9);
        }
        function frontAt(i, s, t) { return frontWith(sweep, i, s, t); }

        /* The swipe's shape, as it would start now: its direction, the
           overlay's size — rounded UP and a pixel over: under a browser zoom or
           a fractional screen scale the overlay is a fraction of a pixel bigger
           than its rounded clientWidth, and a shape that stopped short would
           leave a hairline of the site showing down the far edge (the layers
           clip to their own boxes, so going over costs nothing) — and the
           wave. */
        function geometry() {
            var dir = direction();
            var box = root.getBoundingClientRect();
            var W = Math.ceil(box.width) + 1;
            var H = Math.ceil(box.height) + 1;
            var wave = cfg.edge === 'wave' ? cfg.waveHeight : 0;
            var span = dir === 'left' ? H : W;
            return {
                dir: dir,
                size: dir === 'left' ? W : H,       // the travel
                span: span,                         // the edge runs across this
                wave: wave,
                /* A point along the edge every 16px — closer for a short wave,
                   a tenth of a crest apart at most, so it stays a curve and
                   never turns into a zigzag. */
                n: wave ? clamp(Math.ceil(span / Math.min(16, cfg.waveLength / 10)), 8, 800) : 1,
                starts: [0, cfg.gapMs, cfg.gapMs * 2]
            };
        }

        /* How long after the swipe starts its first edge reaches a point (x, y)
           of the overlay — on a left swipe, his middle `x` at the height of his
           feet `y`; on an upward one, his feet `y` at his middle `x` — exactly
           as floorGone() will see it, wave and all. */
        function reachMs(x, y) {
            var g = geometry();
            var pos = g.dir === 'left' ? x : y;
            var s = g.dir === 'left' ? y : x;
            for (var t = 0; t <= cfg.swipeMs; t += 2) {
                if (frontWith(g, 0, s, t) <= pos) return t;
            }
            return cfg.swipeMs;
        }
        function shapeOf(i, t) {
            var sw = sweep, pts = [], k, s;
            if (sw.dir === 'left') {
                pts.push('0px 0px');
                for (k = 0; k <= sw.n; k++) {
                    s = sw.span * k / sw.n;
                    pts.push(px(frontAt(i, s, t)) + ' ' + px(s));
                }
                pts.push('0px ' + px(sw.span));
            } else {
                pts.push('0px 0px');
                pts.push(px(sw.span) + ' 0px');
                for (k = sw.n; k >= 0; k--) {
                    s = sw.span * k / sw.n;
                    pts.push(px(s) + ' ' + px(frontAt(i, s, t)));
                }
            }
            return 'polygon(' + pts.join(', ') + ')';
        }

        function exit() {
            cancelAnims();
            stopSweep();
            buzz(false);
            // a preview box's white layer: its own dots (on the site, scenes())
            if (options.stage) makeDots();
            sweep = geometry();
            sweep.t0 = performance.now();
            var dir = sweep.dir;
            root.setAttribute('data-dir', dir);
            root.setAttribute('data-phase', 'exit');
            var layers = [el.layer1, el.layer2, el.layer3];
            var whiteGone = cfg.gapMs + cfg.swipeMs;
            var total = cfg.gapMs * 2 + cfg.swipeMs;

            return new Promise(function (resolve) {
                sweepDone = resolve;
                // `ts` is the frame's own timestamp when called from a frame.
                function step(now, ts) {
                    sweepRaf = 0;
                    if (!sweep) return;
                    var t = Math.max(0, now - sweep.t0);
                    // The white layer's dots for this frame, while it is up;
                    // once it has gone, its dots and its goo are done with.
                    if (t < whiteGone) dotsFrame(ts);
                    else { stopDots(); stopGoo(); }
                    for (var i = 0; i < 3; i++) {
                        if (layers[i]) layers[i].style.clipPath = shapeOf(i, t);
                    }
                    if (t >= total) { finishSweep(true); return; }
                    sweepRaf = requestAnimationFrame(function (ts2) { step(ts2, ts2); });
                }
                // The first shape now, so nothing can move before it is set.
                step(performance.now());
            });
        }
        function finishSweep(ok) {
            if (sweepRaf) { cancelAnimationFrame(sweepRaf); sweepRaf = 0; }
            var done = sweepDone;
            sweepDone = null;
            if (done) done(ok);
        }
        function stopSweep() {
            finishSweep(false);
            sweep = null;
            [el.layer1, el.layer2, el.layer3].forEach(function (n) { if (n) n.style.removeProperty('clip-path'); });
        }

        /* ── The entrance, in a preview box ──────────────────────────────────
         * What the site does once the page is ready (assets/loader/boot.js):
         * the lit logo; the mascot taking its place and playing Opening, held
         * there; the three layers sweeping away — the edge as set, straight or a
         * wave — and his fall at the moment set ("He falls"). Played in a
         * dashboard box, with a mascot made in the same box (createMascot()),
         * so Opening, the swipe and the wave can be seen together. No page to
         * wait for: the lit logo holds for the timeline's last step, Opening
         * runs its course (capped as on the site). Resolves true when the
         * layers have gone, false when a reset() cut it short. Without a mascot:
         * the logo, then the swipe. */
        var waits = [];
        // a pause a reset() can call off: resolves true when it has run, false then
        function wait(ms) {
            return new Promise(function (resolve) {
                var rec = { resolve: resolve, id: 0 };
                rec.id = setTimeout(function () {
                    var at = waits.indexOf(rec);
                    if (at >= 0) waits.splice(at, 1);
                    resolve(true);
                }, Math.max(0, ms || 0));
                waits.push(rec);
            });
        }
        function cancelWaits() {
            var list = waits;
            waits = [];
            list.forEach(function (rec) { clearTimeout(rec.id); rec.resolve(false); });
        }
        /* The moment the first blue has swept out from under him — as boot.js
           floorGone() on the site: past his middle on a left swipe (measured at
           his feet), past his feet on an upward one. His box is this box. */
        function floorGone(m) {
            var mine = run;
            return new Promise(function (resolve) {
                (function check() {
                    var b = m.body;
                    if (mine !== run || !sweep || !b) { resolve(); return; }
                    var feet = b.y + b.height / 2;
                    var e = edgeAt(b.x, feet);
                    if (!e || (e.dir === 'up' ? e.pos <= feet : e.pos <= b.x)) { resolve(); return; }
                    requestAnimationFrame(check);
                })();
            });
        }
        /* ── The mascot takes over, and the layers go ─────────────────────────
         * He takes the lit logo's place (drawn where its middle is, in the same
         * task as it disappears, so no frame shows both or neither) and plays
         * Opening, HELD: when it ends he stays standing — on the blue, as far as
         * anyone can tell — until he is let go. And the layers sweep away:
         *
         *   swipe_sync 'reach' (the default) — timed so that the first blue's
         *       edge reaches him the moment Opening ends (plus the swipe delay):
         *       the floor goes from under him right where his animation stops,
         *       and he drops straight out of its last frame. The swipe starts
         *       that much before Opening's end — or, an Opening shorter than
         *       the edge's way to him, before he takes over, the logo still lit
         *       on the moving blue.
         *   'after' — once Opening has ended (capped), and the swipe delay.
         *
         * He falls at the moment set ("He falls"): when the blue has swept out
         * from under him, as the swipe starts, or once the site is uncovered.
         * Resolves with the swipe's result once the layers have gone; false
         * when a reset() called it off. The site (boot.js) and the dashboard's
         * previews (entrance()) both run this.
         *
         * `hooks.takeover()`: called the moment he has taken the logo's place
         * (a preview box with him standing about from before shows him from
         * then on — on the site he is nowhere until then). */
        var heldBy = 0;             // the run whose hold on the mascot stands
        function takeOver(m, hooks) {
            var mine = run;
            hooks = hooks || {};
            /* Let go of him — only while it is this run holding him: a preview
               played again over this one calls it off, and its letting go must
               not cancel the new one's hold. */
            function letGo() {
                if (heldBy === mine && typeof m.release === 'function') m.release();
            }
            function place() {
                var c = logoCentre();
                heldBy = mine;
                var opened = m.openAt(c.x, c.y, { hold: true });
                hideLogo();
                if (typeof hooks.takeover === 'function') {
                    try { hooks.takeover(); } catch (e) {}
                }
                return opened;
            }
            // his fall, at the moment set — from where he stands now
            function fallFrom() {
                if (cfg.fallAt === 'swipe') letGo();
                else if (cfg.fallAt === 'floor') floorGone(m).then(letGo);
            }
            function gone(leaving) {
                return leaving.then(function (done) { letGo(); return done; });
            }

            var plan = null;
            if (cfg.swipeSync === 'reach' && typeof m.openingPlan === 'function') {
                var c0 = logoCentre();
                try { plan = m.openingPlan(c0.x, c0.y); } catch (e) { plan = null; }
            }
            if (plan) {
                var opening = Math.min(Math.max(0, plan.ms || 0), cfg.openingCapMs);
                var lead = opening + cfg.swipeDelay - reachMs(plan.x, plan.feet);
                if (lead >= 0) {
                    place();
                    return wait(lead).then(function (ok) {
                        if (!ok || mine !== run) { letGo(); return false; }
                        var leaving = exit();
                        fallFrom();
                        return gone(leaving);
                    });
                }
                // the swipe first, and he takes over on its way to him
                var early = exit();
                wait(-lead).then(function (ok) {
                    if (!ok || mine !== run) return;
                    place();
                    fallFrom();
                });
                return gone(early);
            }

            var opened = place();
            return Promise.race([
                Promise.resolve(opened).then(function () { return true; }),
                wait(cfg.openingCapMs)
            ]).then(function (ran) {
                return ran && mine === run ? wait(cfg.swipeDelay) : false;
            }).then(function (ran) {
                if (!ran || mine !== run) { letGo(); return false; }
                var leaving = exit();
                fallFrom();
                return gone(leaving);
            });
        }

        /* The whole entrance from the lit logo on: the light coming on for good
           (the timeline's last step, its pop and its hold), then takeOver() —
           or, with no mascot, the swipe. */
        function entrance(mascot, hooks) {
            reset();
            var last = cfg.steps[cfg.steps.length - 1];
            light(true);
            pop(last.bounce);
            var mine = run;
            var m = mascot && typeof mascot.openAt === 'function' ? mascot : null;
            return wait(last.ms).then(function (ok) {
                if (!ok || mine !== run) return false;
                return m ? takeOver(m, hooks) : exit();
            });
        }

        /* Where the FIRST layer's edge is at a point of the screen right now: on
           a left swipe, how far across its edge reaches at height y; on an
           upward one, how far down it reaches at x. The front end watches it to
           know the moment the blue has swept out from under the mascot. Null
           before the swipe has started. */
        function edgeAt(x, y) {
            if (!sweep) return null;
            var t = performance.now() - sweep.t0;
            return { dir: sweep.dir, pos: frontAt(0, sweep.dir === 'left' ? y : x, t) };
        }

        /* ── The white layer: the site's own dots ────────────────────────────
         * The white layer is the page's background — its paper and its moving
         * dot field — lined up with it exactly. The field's own canvas never
         * leaves the page: the last blue layer uncovers the site while the
         * white one is still on its way out, and the site must have its dots
         * the moment any of it shows. The white layer draws the field itself
         * instead (see "The page background's dot field" above), on a canvas of
         * its own at the same place and size as the background's: the same
         * dots, at the same instant, every time the background is drawn — on
         * the GPU, through the background's own drawer, where there is one; on
         * a 2D canvas where there is not.
         *
         * FC_WAVE_TICK(ts) has the background draw itself for this frame first,
         * if it has not already (assets/spotlight-dots.js does the same), so
         * FC_WAVE_NOW is the instant on screen in this very frame.
         *
         * In a preview box (options.stage — the dashboard) there is no page
         * background to line up with: the field is the box's own, laid out on
         * the box as the background lays itself out on the window, on a clock
         * of its own — the same dots and the same wave, so the white layer
         * looks there as it does on the site. */
        var dots = null;
        function makeDots() {
            if (dots || !cfg.dots || !el.layer2) return;
            if (!options.stage && (!document.getElementById('fc-waves-canvas') || typeof w.FC_WAVE_T0 !== 'number')) return;
            var cv = document.createElement('canvas'), layer = null, ctx = null;
            try {
                layer = typeof w.FC_DOT_LAYER === 'function' ? w.FC_DOT_LAYER(cv, null) : null;
            } catch (e) {
                layer = null;
            }
            if (!layer) {
                // A canvas that has tried WebGL can never draw in 2D: a fresh one.
                cv = document.createElement('canvas');
                try { ctx = cv.getContext('2d', { alpha: true }); } catch (e) { ctx = null; }
                if (!ctx) return;
            }
            cv.setAttribute('aria-hidden', 'true');
            cv.style.cssText = 'position:absolute;top:0;left:0;width:0;height:0;display:block;pointer-events:none;';
            el.layer2.insertBefore(cv, el.layer2.firstChild);
            dots = { cv: cv, layer: layer, ctx: ctx, at: null, W: 0, H: 0, dpr: 0, shrink: 0,
                     stage: !!options.stage, t0: performance.now() };
        }
        /* Size and lay out the grid as wave-bg.js resize() does, from the same
           window measurements (a preview box: its own), whenever they change. */
        function dotsGrid(g) {
            var W = g.stage ? root.clientWidth : w.innerWidth;
            var H = g.stage ? root.clientHeight : w.innerHeight;
            var dpr = Math.max(1, Math.min(2, Math.round(w.devicePixelRatio || 1)));
            var shrink = Math.min(1, w.devicePixelRatio || 1);
            if (g.W === W && g.H === H && g.dpr === dpr && g.shrink === shrink) return;
            var unitW = Math.round(W * dpr), unitH = Math.round(H * dpr);
            g.cv.width = Math.max(1, Math.round(unitW * shrink));
            g.cv.height = Math.max(1, Math.round(unitH * shrink));
            g.cv.style.width = W + 'px';
            g.cv.style.height = H + 'px';
            var p = DOT_PITCH * dpr, xs = dotAxis(unitW, p), ys = dotAxis(unitH, p), i;
            g.cols = new Float64Array(xs.length);
            for (i = 0; i < xs.length; i++) g.cols[i] = Math.round(xs[i]);
            // one extra row above and below, as there
            g.rows = new Float64Array(ys.length + 2);
            g.rows[0] = Math.round(ys[0] - p);
            for (i = 0; i < ys.length; i++) g.rows[i + 1] = Math.round(ys[i]);
            g.rows[ys.length + 1] = Math.round(ys[ys.length - 1] + p);
            g.flat = new Float64Array(g.cols.length);
            g.cs = new Float64Array(g.cols.length);
            g.cc = new Float64Array(g.cols.length);
            g.rs = new Float64Array(g.rows.length);
            g.rc = new Float64Array(g.rows.length);
            for (i = 0; i < g.rows.length; i++) {
                var b = (g.rows[i] / dpr) * KY_3;
                g.rs[i] = Math.sin(b);
                g.rc[i] = Math.cos(b);
            }
            g.W = W; g.H = H; g.dpr = dpr; g.shrink = shrink;
        }
        /* The field at instant `now` (ms on the background's clock, or the
           box's own), drawn as wave-bg.js draw() draws it. */
        function drawDots(g, now) {
            dotsGrid(g);
            var t = (now - (g.stage ? g.t0 : w.FC_WAVE_T0)) / 1000, dpr = g.dpr, nc = g.cols.length, nr = g.rows.length, i;
            var p1 = t * SPEED_1, p2 = t * SPEED_2, p3 = t * SPEED_3;
            for (i = 0; i < nc; i++) {
                var xCss = g.cols[i] / dpr, a = xCss * KX_3 + p3;
                g.flat[i] = AMP_1 * Math.sin(xCss * KX_1 + p1) + AMP_2 * Math.sin(xCss * KX_2 + p2);
                g.cs[i] = Math.sin(a);
                g.cc[i] = Math.cos(a);
            }
            var u = dpr, half = Math.round(DOT_SIZE * dpr / 2);
            if (g.layer) {
                g.layer.drawField(g.cols, g.flat, g.cs, g.cc, nc, g.rows, g.rs, g.rc, nr,
                                  DOT_COLOUR, g.shrink, u, half, AMP_3);
                return;
            }
            var ctx = g.ctx, path = new Path2D(), ri, ci;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, g.cv.width, g.cv.height);
            ctx.setTransform(g.shrink, 0, 0, g.shrink, 0, 0);
            ctx.fillStyle = DOT_COLOUR;
            for (ri = 0; ri < nr; ri++) {
                var sb = g.rs[ri], cb = g.rc[ri], yb = g.rows[ri];
                for (ci = 0; ci < nc; ci++) {
                    var y = yb + Math.round((g.flat[ci] + AMP_3 * (g.cs[ci] * cb + g.cc[ci] * sb)) * dpr) - half;
                    var x = g.cols[ci] - half;
                    path.rect(x + u, y, u * 4, u);
                    path.rect(x, y + u, u * 6, u * 4);
                    path.rect(x + u, y + u * 5, u * 4, u);
                }
            }
            ctx.fill(path);
        }
        /* This frame's dots: the instant the background is showing, drawn once.
           `ts` is the frame's timestamp, or undefined outside a frame (the
           swipe's first shape), where the instant already on screen is used. */
        function dotsFrame(ts) {
            if (!dots) return;
            var at;
            if (dots.stage) {
                // the box's own field: at most 60 times a second, as the site's
                at = ts !== undefined ? ts : performance.now();
                if (dots.at !== null && at - dots.at < 1000 / 60 * 0.8) return;
            } else {
                if (ts !== undefined && typeof w.FC_WAVE_TICK === 'function') {
                    try { w.FC_WAVE_TICK(ts); } catch (e) {}
                }
                at = w.FC_WAVE_NOW;
                if (typeof at !== 'number' || at === dots.at) return;
            }
            try {
                drawDots(dots, at);
                dots.at = at;
            } catch (e) {
                stopDots();
            }
        }
        /* The white layer has gone: nothing more to draw, and the canvas's
           memory and graphics context given back (the canvas itself stays where
           it is, clipped away, rather than be taken out of the page mid-swipe —
           in a preview box, which plays again and again, it goes). */
        function stopDots() {
            if (!dots) return;
            var g = dots;
            dots = null;
            try {
                if (g.layer) {
                    var gl = g.cv.getContext('webgl2');
                    var lose = gl && gl.getExtension('WEBGL_lose_context');
                    if (lose) lose.loseContext();
                }
                g.cv.width = 0;
                g.cv.height = 0;
                if (g.stage && g.cv.parentNode) g.cv.parentNode.removeChild(g.cv);
            } catch (e) {}
        }

        /* ── The white layer: the cursor's goo ───────────────────────────────
         * The white layer is the site's own ground, so the cursor's goo is on it
         * as it is on the site (assets/spotlight-dots.js): the SAME goo, from
         * the same trail, drawn there as well while the layer is up — over its
         * dots and under its copies — its drops falling through it, and the blue
         * wordmark's white twin shown inside it as type on the site turns. The
         * trail runs on under the whole cover, so the goo the site shows once it
         * is uncovered is the one that was on the white layer: one animation,
         * carried on. Over the blue layers it is hidden, as over the hero's blue
         * panel. */
        var goo = null;
        function spotOn() {
            return !options.stage && cfg.spot && typeof w.FC_SPOT_MIRROR === 'function';
        }
        function makeGoo(turned) {
            if (goo || !spotOn() || !el.layer2) return;
            var host = document.createElement('div');
            host.setAttribute('aria-hidden', 'true');
            host.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;';
            el.layer2.insertBefore(host, el.scene2 && el.scene2.parentNode === el.layer2 ? el.scene2 : null);
            try {
                goo = w.FC_SPOT_MIRROR(host, turned ? [turned] : []) || null;
            } catch (e) {
                goo = null;
            }
        }
        function stopGoo() {
            if (!goo) return;
            var g = goo;
            goo = null;
            try { g.stop(); } catch (e) {}
        }

        /* ── The copies each layer carries ───────────────────────────────────
         *
         *   white layer   the dots (above), the top bar as a BLUE strip with
         *                 white words, the wordmark in blue;
         *   last blue     the top bar as a WHITE strip with blue words, the
         *                 wordmark in white — nothing else;
         *   the site      the bar blue again, the wordmark white.
         *
         * Each copy is laid exactly over its original, measured now in the
         * viewport the overlay covers.
         *
         * On the last blue layer the wordmark is not a copy at all where it can
         * help it: the layer is cut open over it and the REAL wordmark shows
         * through — it is white on that same blue — so the moment the layer goes
         * nothing about it can change. (A copy is used only when the layer's
         * blue is not the colour behind the real one.) */
        var clocks = [];
        var clockTimer = 0;
        var TYPE_WHITE = '#FFFFFF';
        function blueOf() {
            var c = cfg.colours;
            if (!isLight(c.layer1)) return c.layer1;
            if (!isLight(c.layer3)) return c.layer3;
            return '#0033FF';
        }
        function whiteOf() {
            return isLight(cfg.colours.layer2) ? cfg.colours.layer2 : TYPE_WHITE;
        }
        // type on a light layer is blue; on a dark one, the site's pure type white
        function inkFor(ground) { return isLight(ground) ? blueOf() : TYPE_WHITE; }

        /* Colour a copy's type, reading each piece's original to tell a hollow
           outline (the "/26") from filled type: an outline keeps its transparent
           fill and only its stroke takes the colour. */
        function paint(orig, copy, colour) {
            var from = [orig].concat(Array.prototype.slice.call(orig.querySelectorAll('*')));
            var to = [copy].concat(Array.prototype.slice.call(copy.querySelectorAll('*')));
            for (var i = 0; i < to.length && i < from.length; i++) {
                var cs = w.getComputedStyle(from[i]);
                if (cs.getPropertyValue('color') !== 'rgba(0, 0, 0, 0)') to[i].style.setProperty('color', colour, 'important');
                to[i].style.setProperty('-webkit-text-stroke-color', colour, 'important');
            }
        }
        /* A copy is a picture: no ids to collide with, nothing for the site's
           scripts to find, nothing to read out or click. */
        function clean(copy) {
            [copy].concat(Array.prototype.slice.call(copy.querySelectorAll('*'))).forEach(function (node) {
                node.removeAttribute('id');
                node.removeAttribute('data-fc-island');
                node.removeAttribute('data-fc-scroll-top');
                node.removeAttribute('data-fc-reveal');
                node.removeAttribute('data-fc-drag');
                if (node.tagName === 'A') node.removeAttribute('href');
            });
            copy.setAttribute('aria-hidden', 'true');
            copy.setAttribute('inert', '');
        }
        function place(copy, r) {
            var s = copy.style;
            s.setProperty('position', 'absolute', 'important');
            s.setProperty('left', r.left + 'px', 'important');
            s.setProperty('top', r.top + 'px', 'important');
            s.setProperty('right', 'auto', 'important');
            s.setProperty('bottom', 'auto', 'important');
            s.setProperty('width', r.width + 'px', 'important');
            s.setProperty('height', r.height + 'px', 'important');
            s.setProperty('margin', '0', 'important');
            s.setProperty('transform', 'none', 'important');
            s.setProperty('z-index', 'auto', 'important');
        }
        function visible(r) { return r && r.width > 0 && r.height > 0; }

        function addBar(scene, orig, ground) {
            var r = orig.getBoundingClientRect();
            if (!visible(r)) return;
            var copy = orig.cloneNode(true);
            freeze(orig, copy);
            clean(copy);
            place(copy, r);
            // The site's own blue-bar class forces its own colours with
            // !important; every colour here is the layer's call.
            copy.classList.remove('fc-topbar-blue');
            var lightGround = isLight(ground);
            copy.style.setProperty('background-color', lightGround ? blueOf() : whiteOf(), 'important');
            copy.style.setProperty('border-bottom-color', lightGround ? '#8CA3FF' : '#DDDDDA', 'important');
            paint(orig, copy, lightGround ? TYPE_WHITE : ground);
            scene.appendChild(copy);
            // The countdown ticks every second; its copies tick with it.
            var realClock = orig.querySelector('[data-fc-countdown-clock]');
            var copyClock = copy.querySelector('[data-fc-countdown-clock]');
            if (realClock && copyClock) clocks.push([realClock, copyClock]);
        }
        /* `ink`: the type's colour, when it is not simply the one that reads on
           the ground. The copy, or null when there is nothing to copy. */
        function addMark(scene, orig, ground, ink) {
            var r = orig.getBoundingClientRect();
            if (!visible(r)) return null;
            var copy = orig.cloneNode(true);
            freeze(orig, copy);
            clean(copy);
            place(copy, r);
            copy.style.setProperty('background-color', 'transparent', 'important');
            paint(orig, copy, ink || inkFor(ground));
            scene.appendChild(copy);
            return copy;
        }
        /* The last blue layer cut open over the real wordmark — see above. True
           when the cut was made; false when a copy has to stand in. */
        function openOver(mark) {
            if (!el.fill3) return false;
            var behind = mark.parentElement, colour = '';
            while (behind && behind !== document.body) {
                var c = w.getComputedStyle(behind).backgroundColor;
                if (c && c !== 'transparent' && !/rgba\([^)]*,\s*0\)$/.test(c)) { colour = c; break; }
                behind = behind.parentElement;
            }
            if (!colour || !behind || !sameColour(colour, cfg.colours.layer3)) return false;
            // The whole drawing — the "/" of "/26" hangs below its own box —
            // with room round it, kept inside the blue it sits on.
            var r = mark.getBoundingClientRect();
            var x1 = r.left, y1 = r.top, x2 = r.right, y2 = r.bottom;
            Array.prototype.forEach.call(mark.querySelectorAll('*'), function (n) {
                var b = n.getBoundingClientRect();
                if (!visible(b)) return;
                x1 = Math.min(x1, b.left); y1 = Math.min(y1, b.top);
                x2 = Math.max(x2, b.right); y2 = Math.max(y2, b.bottom);
            });
            var pad = 0.2 * parseFloat(w.getComputedStyle(mark).fontSize || '0') || 12;
            var box = behind.getBoundingClientRect();
            x1 = Math.max(box.left, x1 - pad); y1 = Math.max(box.top, y1 - pad);
            x2 = Math.min(box.right, x2 + pad); y2 = Math.min(box.bottom, y2 + pad);
            if (!(x2 > x1 && y2 > y1)) return false;
            el.fill3.style.clipPath = 'polygon(evenodd, 0 0, 100% 0, 100% 100%, 0 100%, 0 0, '
                + px(x1) + ' ' + px(y1) + ', ' + px(x2) + ' ' + px(y1) + ', '
                + px(x2) + ' ' + px(y2) + ', ' + px(x1) + ' ' + px(y2) + ', ' + px(x1) + ' ' + px(y1) + ')';
            return true;
        }
        /* Fill the second and third layers. `parts`: { bar, mark } — the real
           top bar and wordmark (either may be missing). Called once, while the
           lit logo is still up. */
        function scenes(parts) {
            parts = parts || {};
            var turned = null;
            if (el.scene2) {
                if (parts.bar) addBar(el.scene2, parts.bar, cfg.colours.layer2);
                if (parts.mark) {
                    addMark(el.scene2, parts.mark, cfg.colours.layer2);
                    /* …and its white twin over it, shown only inside the
                       cursor's goo (see makeGoo), as type on the site turns
                       there — cut to nothing until the goo reaches it. */
                    if (spotOn()) {
                        turned = addMark(el.scene2, parts.mark, cfg.colours.layer2, TYPE_WHITE);
                        if (turned) turned.style.setProperty('clip-path', 'inset(50%)');
                    }
                }
            }
            if (el.scene3) {
                if (parts.bar) addBar(el.scene3, parts.bar, cfg.colours.layer3);
                if (parts.mark && !openOver(parts.mark)) addMark(el.scene3, parts.mark, cfg.colours.layer3);
            }
            makeDots();
            makeGoo(turned);
            if (clocks.length && !clockTimer) {
                clockTimer = setInterval(function () {
                    clocks.forEach(function (pair) {
                        if (pair[1].textContent !== pair[0].textContent) pair[1].textContent = pair[0].textContent;
                    });
                }, 200);
            }
        }

        /* Reduced motion: no swipe at all — the overlay simply fades. */
        function fade() {
            cancelAnims();
            stopSweep();
            buzz(false);
            if (typeof root.animate !== 'function') return Promise.resolve(true);
            var a = root.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 250, easing: 'ease', fill: 'forwards' });
            anims.push(a);
            return a.finished.then(function () { return true; }, function () { return false; });
        }

        /* Back to dark and covering — the dashboard's replays. */
        function reset() {
            run++;
            timers.forEach(clearTimeout);
            timers = [];
            cancelWaits();
            if (flickerDone) { var fd = flickerDone; flickerDone = null; fd(false); }
            buzzing = false;
            clearTimeout(buzzTimer);
            cancelAnims();
            stopSweep();
            stopDots();
            stopGoo();
            if (raf) { cancelAnimationFrame(raf); raf = 0; }
            spring.squash = 0;
            spring.squashVel = 0;
            lastTs = 0;
            draw();
            root.removeAttribute('data-dir');
            root.removeAttribute('data-phase');
            root.removeAttribute('data-logo');
            light(false);
        }

        /* Swap a logo for another URL, or for the placeholder square with ''
           (the dashboard, as files are picked). The box takes the lit logo's
           proportions — or the dark one's — once the file has loaded. */
        function setLogos(logos) {
            ['off', 'on'].forEach(function (which) {
                if (!logos || !(which in logos)) return;
                src[which] = String(logos[which] || '');
                paintArt(which);
            });
            updateRatio();
        }
        function updateRatio() {
            var url = src.on || src.off;
            if (!url) { root.style.setProperty('--fcl-ratio', '1'); return; }
            var probe = new Image();
            probe.onload = function () {
                if ((src.on || src.off) !== url) return;          // a newer pick won
                var r = probe.naturalWidth && probe.naturalHeight ? probe.naturalWidth / probe.naturalHeight : 1;
                root.style.setProperty('--fcl-ratio', String(Math.round(r * 10000) / 10000));
            };
            probe.src = url;
        }

        /* The files the two slots draw — the front end decodes them before the
           flicker starts, so it never flickers an empty box. */
        function logoUrls() {
            return [src.off, src.on].filter(Boolean);
        }

        function setConfig(raw) {
            cfg = readConfig(raw);
            applyConfig();
        }

        function destroy() {
            reset();
            clearInterval(clockTimer);
            clockTimer = 0;
            clocks = [];
            if (el.fill3) el.fill3.style.removeProperty('clip-path');
        }

        // On the front end inc/loader.php has already written every one of these
        // onto the overlay; only a preview box, built once and then edited live,
        // needs them applied from here.
        if (options.stage) applyConfig();

        return {
            root: root,
            get config() { return cfg; },
            setConfig: setConfig,
            setLogos: setLogos,
            logoUrls: logoUrls,
            scenes: scenes,
            edgeAt: edgeAt,
            light: light,
            litFor: litFor,
            pop: pop,
            flicker: flicker,
            buzz: buzz,
            logoCentre: logoCentre,
            hideLogo: hideLogo,
            exit: exit,
            takeOver: takeOver,
            entrance: entrance,
            fade: fade,
            reset: reset,
            destroy: destroy
        };
    }

    w.FCLoader = { create: create, readConfig: readConfig, EASE: EASE, MAX_STEPS: MAX_STEPS };
}(window));
