/* FOSSCOMM 2026 — reusable glyph-scramble ("hacking" text effect).
 *
 *     window.fcScramble(el, toText [, opts])
 *     window.fcScrambleHTML(el, html [, opts])
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

    /* ── The same effect, over text that is already MARKED UP ────────────────
     *
     *     window.fcScrambleHTML(el, html [, opts])
     *
     * fcScramble() above animates a flat string, so anything with markup in it
     * had to be typed in as plain text and have its HTML swapped in at the end.
     * That is what made a *marked* run in a FAQ line arrive in the body face and
     * then jump to the accent's pixel face the instant the animation finished —
     * the highlight was always the last thing to appear, on text that had
     * already been read.
     *
     * Here the FINAL markup is built first and the animation runs over its text
     * NODES, in document order, as though they were one string. Every character
     * therefore lands already inside its own span: a marked run is in the accent
     * face and colour from the first glyph of it, and a link is a link while it
     * is still decoding.
     *
     * A node that has not been reached yet is emptied rather than removed, so
     * the structure never changes shape mid-animation — and the element is held
     * at the finished text's height, for the same reason fcScramble reserves a
     * box: a block building up from nothing would push the page around under the
     * reader.
     */
    function textNodes(root) {
        var out = [];
        (function walk(node) {
            for (var n = node.firstChild; n; n = n.nextSibling) {
                if (n.nodeType === 3) {
                    if (n.nodeValue !== '') out.push({ node: n, text: n.nodeValue });
                } else if (n.nodeType === 1) {
                    walk(n);
                }
            }
        }(root));
        return out;
    }

    function fcScrambleHTML(el, html, opts) {
        if (!el) return;
        opts = opts || {};

        var prev = inFlight.get(el);
        if (prev) {
            cancelAnimationFrame(prev.raf);
            clearTimeout(prev.timer);
            if (prev.release) prev.release();
            inFlight.delete(el);
        }

        el.innerHTML = (html == null) ? '' : String(html);
        var parts = textNodes(el);
        var total = 0;
        for (var i = 0; i < parts.length; i++) total += parts[i].text.length;

        function finish() {
            for (var j = 0; j < parts.length; j++) parts[j].node.nodeValue = parts[j].text;
            if (typeof opts.onComplete === 'function') opts.onComplete();
        }

        if (reducedMotion || !total) {
            finish();
            return;
        }

        // The same per-letter pacing as the plain version, so a question and the
        // answer it swaps into run at one speed.
        var duration = opts.duration || Math.min(Math.max(total * 45, 300), 2200);
        var rec = { raf: 0, timer: 0, release: null };
        inFlight.set(el, rec);

        rec.timer = setTimeout(function () {
            // Measured with the finished markup in place — it is already in the
            // document — and only then emptied, by the first frame.
            var savedMinHeight = el.style.minHeight;
            el.style.minHeight = el.getBoundingClientRect().height + 'px';
            rec.release = function () { el.style.minHeight = savedMinHeight; };

            var start = performance.now();
            var EDGE = 3;   // flickering "decoding" chars leading the reveal
            function frame(t) {
                var progress = Math.min(1, (t - start) / duration);
                var shown = Math.floor(progress * total);
                var base = 0;
                var done = false;
                for (var p = 0; p < parts.length; p++) {
                    var text = parts[p].text;
                    var out = '';
                    if (!done) {
                        for (var k = 0; k < text.length; k++) {
                            var g = base + k, ch = text[k];
                            if (g < shown || ch === ' ' || ch === '\n') {
                                out += ch;
                            } else if (g < shown + EDGE) {
                                out += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
                            } else {
                                done = true;   // the tail has not "arrived" yet
                                break;
                            }
                        }
                    }
                    parts[p].node.nodeValue = out;
                    base += text.length;
                }
                if (progress < 1) {
                    rec.raf = requestAnimationFrame(frame);
                } else {
                    if (rec.release) { rec.release(); rec.release = null; }
                    inFlight.delete(el);
                    finish();
                }
            }
            rec.raf = requestAnimationFrame(frame);
        }, opts.delay || 0);
    }

    window.fcScramble = fcScramble;
    window.fcScrambleHTML = fcScrambleHTML;
})();
