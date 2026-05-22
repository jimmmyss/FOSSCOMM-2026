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
 * Mobile-mode (viewport < lg breakpoint = 1024px) is inert by design: taps on
 * touch screens fire phantom mouseenter events that would either "stick" the
 * alt text or swallow the click. The lg threshold matches the rest of the
 * theme's mobile/desktop split (venue editions bar, sponsor logo swap, globe).
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
                // Greek span disappear (and vice versa). mouseleave brings the
                // default text back the same way.
                if (to === null) return;
                if (typeof window.fcScramble === 'function') {
                    window.fcScramble(el, to);
                } else {
                    el.textContent = to;     // graceful fallback if scramble.js failed
                }
            });
        }

        link.addEventListener('mouseenter', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-alt');
        });
        link.addEventListener('mouseleave', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-default');
        });
        // Keyboard parity — focusing the link triggers the same swap, so it's
        // discoverable for tab-navigation users.
        link.addEventListener('focus', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-alt');
        }, true);
        link.addEventListener('blur', function () {
            if (isMobile) return;
            scrambleTo('data-fc-hover-default');
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
