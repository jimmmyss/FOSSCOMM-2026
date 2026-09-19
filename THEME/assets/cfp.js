/* FOSSCOMM 2026 — Get Involved section behaviour.
 *
 * 1. Submission countdown — [data-fc-cfp-countdown] reads its deadline from
 *    data-deadline (a datetime-local string, parsed local time) and ticks
 *    every second as "12D 04H 22M 10S", then shows data-closed when it lapses.
 *
 * 2. Over-goal funding bar — when raised > goal the server adds
 *    .fc-progress.is-over. Instead of a fixed CSS tempo, this jitters the
 *    fill's RIGHT edge out past the track by a random distance for a random
 *    time, picked from a small set, so it reads as alive / straining to
 *    escape rather than a metronome. The left edge never moves.
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- 1. Countdown ----------
    function pad(n) { return String(n).padStart(2, '0'); }

    function format(diff) {
        var s = Math.max(0, Math.floor(diff / 1000));
        var d = Math.floor(s / 86400);
        var h = Math.floor((s % 86400) / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        return d + 'D ' + pad(h) + 'H ' + pad(m) + 'M ' + pad(sec) + 'S';
    }

    function bindCountdown(el) {
        var raw = el.getAttribute('data-deadline');
        if (!raw) return;
        var target = new Date(raw).getTime();
        if (isNaN(target)) return;
        var closed = el.getAttribute('data-closed') || 'CLOSED';

        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) {
                el.textContent = closed;
                return true; // done
            }
            el.textContent = format(diff);
            return false;
        }

        if (!tick()) {
            var iv = setInterval(function () {
                if (tick()) clearInterval(iv);
            }, 1000);
        }
    }

    // ---------- 2. Over-goal bar: red stub past 100%, shaking width ----------
    // The accent fill stays full inside the track; the red-gradient stub
    // pinned at the right edge trembles in length. Its left edge never moves.
    var BASE = 48; // px the red stub sticks out by
    var AMP  = 12; // px shake amplitude (± around BASE → 36–60px; MAX 60px
                   // must match background-size in .fc-progress-over CSS)

    function shake(stub) {
        stub.style.transitionDuration = '60ms';
        function frame() {
            var delta = (Math.random() * 2 - 1) * AMP; // -AMP .. +AMP
            stub.style.width = (BASE + delta).toFixed(1) + 'px';
            setTimeout(frame, 40 + Math.random() * 50); // ~40-90ms, irregular
        }
        frame();
    }

    function initFunding() {
        if (reducedMotion) return; // CSS keeps the stub a static width
        document.querySelectorAll('.fc-progress.is-over .fc-progress-over')
            .forEach(shake);
    }

    // ---------- boot ----------
    function init() {
        document.querySelectorAll('[data-fc-cfp-countdown]').forEach(bindCountdown);
        initFunding();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
