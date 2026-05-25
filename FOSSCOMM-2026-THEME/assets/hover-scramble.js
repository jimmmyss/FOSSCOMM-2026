/* FOSSCOMM 2026 — link/button "hover scramble swap".
 *
 * Drives the optional "hover text" the admin sets per CTA (Home CTAs, Get
 * Involved cards, Sponsor "Become a sponsor", Footer links). When a link has
 * any descendant with a non-empty data-fc-hover-alt, hovering the WHOLE link
 * scrambles each labelled span from its default text into the alt text using
 * window.fcScramble (same engine as the FAQ swap and the venue hover-swap).
 *
 * Links WITHOUT any hover-alt set keep their existing CSS hover (accent-link
 * underline) untouched — no JS interference.
 *
 * Mobile (viewport < lg breakpoint = 1024px) behaviour:
 *   • CSS :hover stays off (that's gated by the lg media queries in the
 *     per-section stylesheets — not this file).
 *   • A tap on a link with hover-alt swaps the text into the alt (first tap)
 *     and either navigates (second tap, real link) or swaps back (second
 *     tap, dead link). Tapping anywhere else resets to default.
 *
 * Additionally, this file installs a global click guard: any <a> whose href
 * is missing, empty, or just "#" no longer navigates anywhere (the browser
 * default of "reload / jump to top" was getting users sent home from CTAs
 * that the admin had left blank).
 */
(function () {
    'use strict';

    var mqMobile = window.matchMedia && window.matchMedia('(max-width: 1023.98px)');
    var isMobile = mqMobile ? mqMobile.matches : false;
    if (mqMobile) {
        var onChange = function (e) { isMobile = e.matches; };
        if (mqMobile.addEventListener) mqMobile.addEventListener('change', onChange);
        else if (mqMobile.addListener) mqMobile.addListener(onChange);
    }

    function isDeadAnchor(a) {
        if (!a || a.tagName !== 'A') return false;
        var href = a.getAttribute('href');
        if (href === null) return true;
        var t = href.trim();
        return t === '' || t === '#';
    }

    // Global: a click on any <a> with empty/missing/"#" href is silently
    // cancelled. Runs in capture so it beats anything else that might want
    // to act on the click (analytics, smooth-scroll handlers, etc.) — but
    // it ONLY fires for dead hrefs, so real links are untouched.
    document.addEventListener('click', function (e) {
        var anchor = e.target && e.target.closest && e.target.closest('a');
        if (anchor && isDeadAnchor(anchor)) {
            e.preventDefault();
        }
    }, true);

    function bind(link) {
        var targets = link.querySelectorAll('[data-fc-hover-default][data-fc-hover-alt]');
        // No populated alt anywhere → nothing to scramble. Leave the link's
        // native :hover styling (accent-link, underline-link) alone.
        var hasAlt = false;
        for (var i = 0; i < targets.length; i++) {
            if ((targets[i].getAttribute('data-fc-hover-alt') || '') !== '') { hasAlt = true; break; }
        }
        if (!hasAlt) return;

        function scrambleTo(attr) {
            targets.forEach(function (el) {
                var to = el.getAttribute(attr);
                // attr unset = leave the span alone. Empty string is allowed
                // and DOES animate — it scrambles the current text out to
                // nothing, which is how "only English hover set" makes the
                // Greek span disappear (and vice versa).
                if (to === null) return;
                if (typeof window.fcScramble === 'function') {
                    window.fcScramble(el, to);
                } else {
                    el.textContent = to;     // graceful fallback if scramble.js failed
                }
            });
        }

        // ----- Desktop pointer hover + keyboard focus -----
        link.addEventListener('mouseenter', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-alt');
        });
        link.addEventListener('mouseleave', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-default');
        });
        link.addEventListener('focus', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-alt');
        }, true);
        link.addEventListener('blur', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-default');
        }, true);

        // ----- Mobile tap-to-scramble -----
        // First tap: scramble to the alt text and swallow the click.
        // Second tap on the same link: navigate (real href) or swap back to
        // the default (dead href). Tapping anywhere outside the link resets.
        var altShowing = false;
        link.addEventListener('click', function (e) {
            if (!isMobile) return;
            if (!altShowing) {
                e.preventDefault();
                scrambleTo('data-fc-hover-alt');
                altShowing = true;
                return;
            }
            // alt is already showing
            if (isDeadAnchor(link)) {
                e.preventDefault();
                scrambleTo('data-fc-hover-default');
                altShowing = false;
                return;
            }
            // Real link, second tap → let the browser navigate normally.
        });
        // Capture-phase global listener so a tap elsewhere resets us before
        // any other handler can act on the click.
        document.addEventListener('click', function (e) {
            if (!isMobile || !altShowing) return;
            if (link.contains(e.target)) return;
            scrambleTo('data-fc-hover-default');
            altShowing = false;
        }, true);
    }

    function init() {
        document.querySelectorAll('[data-fc-hover-link]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
