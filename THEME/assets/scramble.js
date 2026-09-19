/* FOSSCOMM 2026 — reusable glyph-scramble ("hacking" text effect).
 *
 *     window.fcScramble(el, toText [, opts])
 *
 *   el       — element whose textContent is animated
 *   toText   — the final string to resolve to
 *   opts     — { duration, delay, onComplete }
 *
 * One global look everywhere (hover CTAs, FAQ, venue title, …): the text TYPES
 * IN one character at a time with a short flickering "decoding" edge of weird
 * glyphs, building up from empty to the final string. Before animating, the
 * element's FINAL box is reserved so the build-up + wide glyphs never shift the
 * layout — inline targets (CTA labels) get a fixed width (inline-block); block
 * targets (FAQ lines, venue title) get a min-height (their width is already
 * fixed by the layout).
 *
 * Honors prefers-reduced-motion (sets text instantly). Calling it again on the
 * same element cancels the in-flight animation AND releases its box reservation
 * first, so rapid hover in/out stays clean.
 */
(function () {
    'use strict';

    var GLYPHS = 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ▓▒░█0123456789@#$%&*<>=+/?!';
    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var inFlight = new WeakMap();

    // Reserve the element at its FINAL text's box. Returns a release() fn.
    function reserveBox(el, toText) {
        var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
        var disp = cs ? cs.display : '';
        var inline = disp === 'inline' || disp === 'inline-block' || disp === 'inline-flex';
        var saved = {
            width: el.style.width, minHeight: el.style.minHeight,
            display: el.style.display, whiteSpace: el.style.whiteSpace,
            textAlign: el.style.textAlign
        };
        var prevText = el.textContent;
        el.textContent = toText;
        if (inline) {
            el.style.display = 'inline-block';
            el.style.whiteSpace = 'nowrap';
            el.style.textAlign = 'left';
            el.style.width = el.getBoundingClientRect().width + 'px';
        } else {
            el.style.minHeight = el.getBoundingClientRect().height + 'px';
        }
        el.textContent = prevText;
        return function () {
            el.style.width = saved.width;
            el.style.minHeight = saved.minHeight;
            el.style.display = saved.display;
            el.style.whiteSpace = saved.whiteSpace;
            el.style.textAlign = saved.textAlign;
        };
    }

    function fcScramble(el, toText, opts) {
        if (!el) return;
        toText = (toText == null) ? '' : String(toText);
        opts = opts || {};

        // Cancel any animation already running on this element; release its box.
        var prev = inFlight.get(el);
        if (prev) {
            cancelAnimationFrame(prev.raf);
            clearTimeout(prev.timer);
            if (prev.release) prev.release();
            inFlight.delete(el);
        }

        if (reducedMotion) {
            el.textContent = toText;
            if (typeof opts.onComplete === 'function') opts.onComplete();
            return;
        }

        // Per-LETTER speed: total time scales with length so every character
        // takes the same ~45ms to land (a short label no longer crawls while a
        // long one races). Bounded so 1-char strings aren't instant and very long
        // ones (FAQ answers) don't drag.
        var duration = opts.duration || Math.min(Math.max(toText.length * 45, 300), 2200);
        var delay = opts.delay || 0;
        var rec = { raf: 0, timer: 0, release: null };
        inFlight.set(el, rec);

        rec.timer = setTimeout(function () {
            rec.release = reserveBox(el, toText);
            var start = performance.now();
            var len = toText.length;
            var EDGE = 3;   // flickering "decoding" chars leading the reveal
            function frame(t) {
                var progress = Math.min(1, (t - start) / duration);
                var shown = Math.floor(progress * len);
                var out = '';
                for (var i = 0; i < len; i++) {
                    var ch = toText[i];
                    if (i < shown || ch === ' ' || ch === '\n') {
                        out += ch;
                    } else if (i < shown + EDGE) {
                        out += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
                    } else {
                        break;   // tail hasn't "arrived" yet — text builds up L→R
                    }
                }
                el.textContent = out;
                if (progress < 1) {
                    rec.raf = requestAnimationFrame(frame);
                } else {
                    el.textContent = toText;
                    if (rec.release) { rec.release(); rec.release = null; }
                    inFlight.delete(el);
                    if (typeof opts.onComplete === 'function') opts.onComplete();
                }
            }
            rec.raf = requestAnimationFrame(frame);
        }, delay);
    }

    window.fcScramble = fcScramble;
})();
