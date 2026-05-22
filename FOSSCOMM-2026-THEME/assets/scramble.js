/* FOSSCOMM 2026 — reusable glyph-scramble ("hacking" text effect).
 *
 * The original effect lives inside assets/dist/fc.js (the hero title), but it
 * is locked in a private IIFE and only auto-runs on [data-fc-island="scramble"].
 * fc.js is a compiled artifact we must not edit, so this is a faithful,
 * standalone port exposed as a single callable API:
 *
 *     window.fcScramble(el, toText [, opts])
 *
 *   el       — element whose textContent is animated
 *   toText   — the final string to resolve to
 *   opts     — { duration, delay, onComplete }
 *
 * Glyph set, timing (700 + len*25 ms) and left-to-right lock-in are kept
 * identical to the hero so the effect looks the same everywhere. Honors
 * prefers-reduced-motion (sets the text instantly, no animation). Calling it
 * again on the same element cancels the in-flight animation first, so it can
 * be driven by rapid hover in/out without flicker.
 */
(function () {
    'use strict';

    var GLYPHS = 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ░▒▓0123456789@#';
    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var inFlight = new WeakMap();

    function fcScramble(el, toText, opts) {
        if (!el) return;
        toText = (toText == null) ? '' : String(toText);
        opts = opts || {};

        // Cancel any animation already running on this element.
        var prev = inFlight.get(el);
        if (prev) {
            cancelAnimationFrame(prev.raf);
            clearTimeout(prev.timer);
            inFlight.delete(el);
        }

        if (reducedMotion) {
            el.textContent = toText;
            if (typeof opts.onComplete === 'function') opts.onComplete();
            return;
        }

        var duration = opts.duration || (700 + toText.length * 25);
        var delay = opts.delay || 0;
        var rec = { raf: 0, timer: 0 };
        inFlight.set(el, rec);

        rec.timer = setTimeout(function () {
            var start = performance.now();
            var len = toText.length;
            function frame(t) {
                var progress = Math.min(1, (t - start) / duration);
                var lockedChars = Math.floor(progress * len);
                var out = '';
                for (var i = 0; i < len; i++) {
                    var ch = toText[i];
                    if (i < lockedChars || ch === ' ' || ch === '\n') {
                        out += ch;
                    } else {
                        out += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
                    }
                }
                el.textContent = out;
                if (progress < 1) {
                    rec.raf = requestAnimationFrame(frame);
                } else {
                    el.textContent = toText;
                    inFlight.delete(el);
                    if (typeof opts.onComplete === 'function') opts.onComplete();
                }
            }
            rec.raf = requestAnimationFrame(frame);
        }, delay);
    }

    window.fcScramble = fcScramble;
})();
