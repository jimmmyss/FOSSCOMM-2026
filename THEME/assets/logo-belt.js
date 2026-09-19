/**
 * Sponsor tiers: one continuous line of logos that becomes a draggable, drifting
 * belt when there are more than fit — and stays a plain centred row when there
 * are not.
 *
 * This is the speakers row's motion model (assets/speakers-carousel.js) with
 * everything speaker-specific removed. What is shared is the part that took the
 * work to get right:
 *
 *   • the loop/no-loop decision is MEASURED, never assumed — three sponsors get
 *     three centred logos, not a belt limping round with a hole in it;
 *   • the belt wraps by the width of ONE set, so the seam shows exactly what the
 *     originals would have;
 *   • the frame loop performs no layout reads, because during a scroll another
 *     script's style write would turn each one into a synchronous layout of the
 *     whole document;
 *   • nothing animates while the section is off-screen, or on a hidden tab.
 *
 * What is NOT shared, and why this is a separate file rather than an option on
 * that one: the speakers row decides what the pointer is on GEOMETRICALLY,
 * sampling each portrait's alpha channel into a mask, because a cut-out photo is
 * mostly transparent and pointer events fire over nothing. A sponsor logo is a
 * rectangle you either hover or do not, so CSS :hover is correct and the entire
 * hit-testing apparatus — masks, canvases, per-frame tests — is absent here.
 *
 * Several belts per page (one per tier), each measured and driven independently.
 */
(function () {
    'use strict';

    var DRIFT = 22;            // px/s, leftward. Matches the speakers row.
    var DECAY = 0.0025;        // velocity remaining after 1s (exponential)
    var MIN_FLICK = 40;        // px/s below which a release adds no inertia
    var DRAG_SLOP = 6;         // px of travel that turns a click into a drag

    var reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function init(wrap) {
        var viewport = wrap.querySelector('[data-fc-belt-viewport]');
        var rail = wrap.querySelector('[data-fc-belt-rail]');
        if (!viewport || !rail) return;

        var originals = Array.prototype.slice.call(rail.children);
        if (!originals.length) return;

        var looping = false;
        var setW = 0;          // width of ONE set of logos
        var offset = 0;
        var velocity = 0;
        var raf = 0;
        var lastTs = 0;
        var clones = [];
        var onScreen = true;
        var suppressClick = false;

        var drag = { id: null, startX: 0, startOffset: 0, lastX: 0, lastT: 0, moved: 0 };

        // Carousel progress. Rendered by the template whether or not the belt ends
        // up looping — CSS hides the dots until it does, so nothing has to be
        // built or torn down when that decision changes at a resize.
        var dots = wrap.querySelector('[data-fc-belt-dots]');
        var dotCount = originals.length;
        var lastP = null;

        /* ── measuring ──────────────────────────────────────────────────── */

        function measure() {
            // Clones must be gone before measuring, or the second pass would
            // measure two sets and never stop looping.
            removeClones();

            // First logo's left edge to last logo's right edge: the set as it is
            // actually laid out, internal gaps included.
            //
            // Not the sum of the widths — the cells carry no padding any more and
            // the space between logos is the rail's `gap`, which
            // getBoundingClientRect() does not report. Summing widths would say a
            // row fits when the gaps push it past the edge.
            //
            // Not rail.scrollWidth either, which looks like the obvious answer
            // and is wrong here: the rail is centred until it loops, so an
            // overflowing set hangs off BOTH sides, and scrollWidth does not
            // count what overflows to the left.
            var first = originals[0].getBoundingClientRect();
            var last = originals[originals.length - 1].getBoundingClientRect();
            var span = last.right - first.left;

            // A LOWER BOUND on the period: one more gap separates the last logo
            // of a set from the first of the next. The exact figure comes from
            // measurePeriod() once there is a clone to measure against. Being
            // short here is safe — it only over-estimates the clone count.
            setW = span;

            // +1 for sub-pixel: a row that fits EXACTLY should not loop.
            return span > viewport.clientWidth + 1;
        }

        function addClones() {
            // The belt wraps into [-setW, 0), so at the far end of that range the
            // originals have slid a full set left and the copies must cover the
            // viewport behind them: ceil(viewport / setW) EXTRA sets, no more.
            //
            // setW is a lower bound here (see measure()), so this can only ever
            // ask for MORE sets than are needed — never fewer, which would leave
            // a hole at the seam.
            var needed = Math.max(1, Math.ceil(viewport.clientWidth / Math.max(setW, 1)));
            for (var pass = 0; pass < needed; pass++) {
                for (var i = 0; i < originals.length; i++) {
                    var copy = originals[i].cloneNode(true);
                    // Duplicates are decoration: the real list is already in the
                    // accessibility tree, and announcing every sponsor twice is
                    // worse than announcing them once.
                    copy.setAttribute('aria-hidden', 'true');
                    var links = copy.querySelectorAll('a');
                    for (var a = 0; a < links.length; a++) links[a].tabIndex = -1;
                    rail.appendChild(copy);
                    clones.push(copy);
                }
            }
        }

        /**
         * The belt's true period: the distance from the first original to the
         * first clone of it.
         *
         * MEASURED, not computed. The obvious version — sum the widths and add
         * n x the gap read off getComputedStyle — is right today and quietly
         * wrong the moment anything else contributes to the pitch: a margin on a
         * cell, a row-gap where a column-gap was assumed, a border. Asking the
         * layout where the repeat actually lands cannot drift from the CSS,
         * because it IS the CSS.
         *
         * Wrapping by anything other than this exact number is the one failure
         * the seam makes visible: the belt jumps at every lap.
         */
        function measurePeriod() {
            if (!clones.length) return setW;
            var a = originals[0].getBoundingClientRect().left;
            var b = clones[0].getBoundingClientRect().left;
            var p = b - a;
            // Both rects carry the same transform, so it cancels. A non-positive
            // answer means the layout is not ready (display:none, zero-width
            // images); keep the lower bound rather than dividing by nothing.
            return p > 0 ? p : setW;
        }

        function removeClones() {
            for (var i = 0; i < clones.length; i++) {
                if (clones[i].parentNode) clones[i].parentNode.removeChild(clones[i]);
            }
            clones = [];
        }

        /* ── layout ─────────────────────────────────────────────────────── */

        function stop() {
            if (raf) { cancelAnimationFrame(raf); raf = 0; }
            lastTs = 0;
        }

        /** Re-decide everything. Safe to call repeatedly. */
        function layout() {
            var shouldLoop = measure();
            // The period is about to change, so the throttle's memory of the last
            // progress value is meaningless — clear it, or the dots can sit on a
            // stale fill until the belt has drifted a whole hundredth of a dot.
            lastP = null;

            if (!shouldLoop) {
                looping = false;
                stop();
                wrap.classList.remove('is-looping');
                rail.style.transform = '';
                offset = 0;
                velocity = 0;
                return;
            }

            looping = true;
            wrap.classList.add('is-looping');
            addClones();
            // Only now is the real period knowable — it needs a clone to measure
            // against. Everything after this point wraps by the exact figure.
            setW = measurePeriod();
            offset = wrapOffset(offset);
            render();
            start();
        }

        function start() {
            if (!raf && looping && onScreen && !document.hidden) {
                raf = requestAnimationFrame(frame);
            }
        }

        /* ── motion ─────────────────────────────────────────────────────── */

        /** Fold any offset into [-setW, 0) — the one interval that looks identical. */
        function wrapOffset(x) {
            if (setW <= 0) return 0;
            var m = x % setW;
            if (m > 0) m -= setW;
            return m;
        }

        function render() {
            rail.style.transform = 'translate3d(' + offset.toFixed(2) + 'px, 0, 0)';
            renderDots();
        }

        /**
         * How far round the belt is, as a number the dots can read.
         *
         * offset wraps in [-setW, 0), so -offset/setW is 0 at the start of a set
         * and approaches 1 at the end. Scaled by the dot count, the whole number
         * part is "how many dots are full" and the fraction is how far the next
         * one has filled — the entire calculation, done once here rather than per
         * dot in CSS.
         *
         * Throttled by a hundredth of a dot. At the drift speed a frame moves the
         * fill by about a thousandth of one, so writing every frame would be sixty
         * style invalidations a second to change nothing visible.
         */
        function renderDots() {
            if (!dots || !looping || setW <= 0) return;
            var p = (-offset / setW) * dotCount;
            if (lastP !== null && Math.abs(p - lastP) < 0.01) return;
            lastP = p;
            dots.style.setProperty('--fc-belt-p', p.toFixed(3));
        }

        function frame(ts) {
            raf = requestAnimationFrame(frame);
            // Clamped, so a tab that was throttled does not integrate the whole
            // absence into one enormous step.
            var dt = lastTs ? Math.min(0.05, (ts - lastTs) / 1000) : 0.016;
            lastTs = ts;

            if (drag.id === null) {
                // Held while the pointer is over the belt, so a logo can be read
                // and clicked without chasing it. Only on a real pointer: on touch
                // there is nothing hovering, and `is-holding` is never set.
                if (!wrap.classList.contains('is-holding') && !reduced) {
                    offset -= DRIFT * dt;
                }
                offset += velocity * dt;
                // Frame-rate independent decay: the same fraction is lost per
                // second whatever the display refresh rate.
                velocity *= Math.pow(DECAY, dt);
                if (Math.abs(velocity) < 1) velocity = 0;
                offset = wrapOffset(offset);
            }

            render();
        }

        /* ── pointer ────────────────────────────────────────────────────── */

        function onPointerDown(e) {
            suppressClick = false;
            if (!looping || drag.id !== null) return;
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            drag.id = e.pointerId;
            drag.startX = drag.lastX = e.clientX;
            drag.startOffset = offset;
            drag.lastT = e.timeStamp;
            drag.moved = 0;
            velocity = 0;
            wrap.classList.add('is-dragging');
            viewport.setPointerCapture(e.pointerId);
        }

        function onPointerMove(e) {
            if (drag.id !== e.pointerId) return;
            var dx = e.clientX - drag.startX;
            drag.moved = Math.max(drag.moved, Math.abs(dx));
            offset = wrapOffset(drag.startOffset + dx);

            // Velocity from the last movement only, so a release throws it the
            // way the hand was going at that moment rather than the average of
            // the whole gesture.
            var dt = (e.timeStamp - drag.lastT) / 1000;
            if (dt > 0.001) {
                velocity = (e.clientX - drag.lastX) / dt;
                drag.lastX = e.clientX;
                drag.lastT = e.timeStamp;
            }
            if (!raf) render();
            e.preventDefault();
        }

        function endDrag(e) {
            if (drag.id !== e.pointerId) return;
            drag.id = null;
            wrap.classList.remove('is-dragging');
            if (viewport.hasPointerCapture(e.pointerId)) {
                viewport.releasePointerCapture(e.pointerId);
            }
            if (Math.abs(velocity) < MIN_FLICK) velocity = 0;
            // A drag that TRAVELLED is not a click; a press that did not move is.
            suppressClick = drag.moved > DRAG_SLOP;
        }

        // The native image drag COMPETES with ours and wins: grab a logo and the
        // belt stops responding mid-gesture. `draggable="false"` on the tag and
        // `user-drag: none` in CSS cover Chromium and WebKit; Firefox has
        // historically started one anyway.
        viewport.addEventListener('dragstart', function (e) { e.preventDefault(); });

        viewport.addEventListener('pointerdown', onPointerDown);
        viewport.addEventListener('pointermove', onPointerMove);
        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);

        // Swallow only the click a DRAG produced. Unlike the speakers row this
        // does not take over navigation: a sponsor cell is a plain <a>, so the
        // browser's own click is correct in every case except this one.
        viewport.addEventListener('click', function (e) {
            if (!suppressClick) return;
            suppressClick = false;
            e.preventDefault();
            e.stopPropagation();
        }, true);

        // Hovering pauses the drift. Not on touch: there is no hover there, and
        // pointerenter fires on tap — which would stop the belt for good.
        if (window.matchMedia && window.matchMedia('(hover: hover)').matches) {
            viewport.addEventListener('pointerenter', function () {
                wrap.classList.add('is-holding');
            });
            viewport.addEventListener('pointerleave', function () {
                wrap.classList.remove('is-holding');
            });
        }

        // Nothing to animate on a hidden tab.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else start();
        });

        // Nor off-screen. Sponsors sit near the bottom of a long page, so without
        // this the belt would translate for the entire time somebody spends
        // reading everything above it.
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                onScreen = entries[0].isIntersecting;
                if (!onScreen) stop();
                else start();
            }, { rootMargin: '100px' }).observe(wrap);
        }

        // WIDTH only. On a phone `resize` fires every time the browser chrome
        // slides away — constantly, while scrolling — and layout() tears down
        // every clone and rebuilds it. Nothing here depends on the height.
        var resizeRaf = 0;
        var lastWidth = window.innerWidth;
        window.addEventListener('resize', function () {
            if (window.innerWidth === lastWidth) return;
            if (resizeRaf) return;
            resizeRaf = requestAnimationFrame(function () {
                resizeRaf = 0;
                lastWidth = window.innerWidth;
                layout();
            });
        }, { passive: true });

        // Cell widths depend on decoded logos — a logo's height comes from its
        // aspect ratio, so its width is not known until it loads. Measuring
        // before then would decide "no belt needed" from a row of empty boxes.
        layout();
        window.addEventListener('load', layout, { once: true });

        var imgs = rail.querySelectorAll('img');
        var left = imgs.length;
        var done = function () { if (--left <= 0) layout(); };
        for (var i = 0; i < imgs.length; i++) {
            if (imgs[i].complete) { done(); continue; }
            imgs[i].addEventListener('load', done, { once: true });
            imgs[i].addEventListener('error', done, { once: true });
        }
    }

    function boot() {
        var belts = document.querySelectorAll('[data-fc-belt]');
        for (var i = 0; i < belts.length; i++) init(belts[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
