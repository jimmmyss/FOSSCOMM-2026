/* FOSSCOMM 2026 - highlight whatever is centred on screen, on touch only.
 *
 * On a pointer device CSS :hover does this and the file does nothing. On a phone
 * there is no pointer to ask, so position decides: the thing nearest the middle
 * of the screen gets `.is-hot`, and it follows the scroll. The speakers row does
 * the same thing horizontally along its belt; this is the vertical answer for
 * everything laid out down the page.
 *
 * Opt in from the markup:
 *
 *     <div data-fc-centre>
 *         <div data-fc-centre-item>...</div>   <- one gets .is-hot
 *         <div data-fc-centre-item>...</div>
 *     </div>
 *
 * THE MIDDLE 30% BAND, and nothing outside it.
 *
 * Two rules, applied in order: the nearest item wins among its siblings, AND
 * the winner has to be inside the central 30% of the viewport's height, or
 * nothing is lit at all.
 *
 * The band used to be the middle HALF, and only for single-item containers. Both
 * halves of that were wrong. A 50% band meant the venue name and the Get Involved
 * heading were lit for most of the time they were on screen — "always lit up",
 * which is a highlight that has stopped meaning anything. And exempting groups
 * meant a manifesto stat was lit even with the whole block at the very edge of
 * the screen. One rule, one band, everywhere: it lights as it passes through the
 * middle of the screen and goes out again.
 *
 * Inert with no containers on the page.
 */
(function () {
    'use strict';

    /**
     * How much of the viewport counts as "the middle", as a fraction of its
     * height. The speakers row uses the same 0.30 against its WIDTH, since its
     * belt runs sideways (assets/speakers-carousel.js).
     */
    var BAND = 0.30;

    var groups = [].slice.call(document.querySelectorAll('[data-fc-centre]'));
    if (!groups.length) return;

    groups = groups.map(function (root) {
        // querySelectorAll searches DESCENDANTS, never the element itself, so a
        // lone element marked with both attributes would have found zero items
        // and been dropped. With no items inside it, the container IS the item —
        // which also means the single-element case needs only `data-fc-centre`.
        var items = [].slice.call(root.querySelectorAll('[data-fc-centre-item]'));
        if (!items.length) items = [root];
        return { root: root, items: items, centres: null, hot: null };
    });

    // `(hover: none)` rather than a width breakpoint, and rather than
    // `pointer: coarse`. Those features describe the PRIMARY pointer, so a
    // touchscreen laptop reports coarse even with a mouse attached - asking "can
    // this input hover at all" is the honest question, and a phone still answers
    // no. Watched, so a tablet that gains or loses a trackpad flips live.
    var hoverless = window.matchMedia ? window.matchMedia('(hover: none)') : null;
    var touchMode = !!(hoverless && hoverless.matches);
    var raf = 0;

    /**
     * Every item's centre in DOCUMENT coordinates, measured once per group.
     *
     * Not per scroll frame. getBoundingClientRect() is a layout read, and one
     * per item per frame - on a page that also runs a wave canvas and a mascot
     * writing transforms - is the read-after-write thrash that made the speakers
     * section crawl once already. Document offsets only change when the page
     * reflows, so scrolling is arithmetic.
     */
    function measure(g) {
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        g.centres = g.items.map(function (el) {
            var r = el.getBoundingClientRect();
            return r.top + scrollY + r.height / 2;
        });
    }

    function setHot(g, el) {
        if (g.hot === el) return;
        if (g.hot) g.hot.classList.remove('is-hot');
        g.hot = el;
        if (g.hot) g.hot.classList.add('is-hot');
    }

    function update() {
        raf = 0;
        if (!touchMode) return;

        // clientHeight, not innerHeight: the layout viewport, which a pinch zoom
        // leaves alone. innerHeight shrinks as you zoom in, which would drag the
        // midpoint up the page and light the wrong thing.
        var viewH = document.documentElement.clientHeight || window.innerHeight;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var mid = scrollY + viewH / 2;
        // The central 30% of the height, so HALF of it either side of the middle.
        var band = viewH * BAND / 2;

        for (var gi = 0; gi < groups.length; gi++) {
            var g = groups[gi];
            if (!g.centres) measure(g);

            var best = null;
            var bestGap = Infinity;
            for (var i = 0; i < g.centres.length; i++) {
                var gap = Math.abs(g.centres[i] - mid);
                // Strict <, so the earliest item wins a tie and the choice is
                // deterministic rather than dependent on iteration order.
                if (gap < bestGap) { bestGap = gap; best = g.items[i]; }
            }
            // Near enough, or nothing — for a group exactly as for a lone item.
            if (bestGap > band) best = null;
            setHot(g, best);
        }
    }

    function schedule() {
        if (!raf) raf = requestAnimationFrame(update);
    }

    function remeasure() {
        for (var i = 0; i < groups.length; i++) groups[i].centres = null;
        schedule();
    }

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', remeasure, { passive: true });
    // Late images and webfonts move everything below them, and nothing else
    // would tell us. Load is the one moment worth re-measuring outright.
    window.addEventListener('load', remeasure);

    if (hoverless && hoverless.addEventListener) {
        hoverless.addEventListener('change', function (e) {
            touchMode = e.matches;
            if (!touchMode) {
                // A mouse appeared: drop the positional highlight and let CSS
                // :hover own it, or the last centred item would stay lit.
                for (var i = 0; i < groups.length; i++) setHot(groups[i], null);
            } else {
                schedule();
            }
        });
    }

    schedule();
}());
