/* FOSSCOMM 2026 — front-end client islands.
 * Hand-rolled vanilla JS; no React. Tiny on purpose.
 * Each block self-mounts if its anchor exists; safe to load on any page.
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Countdown ticker in the status bar ----------
    (function countdown() {
        var startIso = (window.FC_DATA && window.FC_DATA.eventStart) || '2026-10-17T09:00:00+03:00';
        var target = new Date(startIso).getTime();
        var el = document.querySelector('[data-fc-countdown]');
        if (!el) return;
        function tick() {
            var diff = target - Date.now();
            if (diff < 0) { el.textContent = 'LIVE NOW'; return; }
            var s = Math.floor(diff / 1000);
            var d = Math.floor(s / 86400);
            var h = Math.floor((s % 86400) / 3600);
            var m = Math.floor((s % 3600) / 60);
            el.textContent = 'T-' + String(d).padStart(3, '0') + 'd ' + String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm';
        }
        tick();
        setInterval(tick, 30000);
    })();

    // ---------- Section nav active-link highlight ----------
    (function sectionNav() {
        var nav = document.querySelector('[data-fc-island="section-nav"]');
        if (!nav) return;
        var links = Array.from(nav.querySelectorAll('[data-fc-nav-target]'));
        var sections = links.map(function (a) {
            return document.getElementById(a.getAttribute('data-fc-nav-target'));
        }).filter(Boolean);
        if (!sections.length || !('IntersectionObserver' in window)) return;
        var io = new IntersectionObserver(function (entries) {
            var visible = entries.filter(function (e) { return e.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (!visible[0]) return;
            var id = visible[0].target.id;
            links.forEach(function (a) {
                a.classList.toggle('text-accent', a.getAttribute('data-fc-nav-target') === id);
            });
        }, { rootMargin: '-30% 0px -60% 0px', threshold: [0, 0.25, 0.5, 1] });
        sections.forEach(function (el) { io.observe(el); });
    })();

    // ---------- Schedule filter bar: DAY // ROOM // CATEGORY (native selects) ----------
    (function scheduleFilter() {
        document.querySelectorAll('[data-fc-island="schedule-filter"]').forEach(function (root) {
            var daySel   = root.querySelector('[data-fc-filter="day"]');
            var roomSel  = root.querySelector('[data-fc-filter="room"]');
            var trackSel = root.querySelector('[data-fc-filter="track"]');
            // A select that isn't in the DOM means that axis has nothing to
            // filter by (the schedule template drops empty segments), so it has
            // to read as the no-filter value — '' — exactly like the
            // placeholder option. The old 'all' default was a leftover from
            // markup that carried an explicit "all" option: every comparison
            // below tests the value for truthiness, so 'all' matched nothing and
            // hid every day-list and every session instead of none of them.
            function val(sel) { return sel ? sel.value : ''; }

            function applyFilters() {
                // Empty value = the placeholder label (DAY/ROOM/CATEGORY) = no filter.
                var day = val(daySel), room = val(roomSel), track = val(trackSel);
                // Day filters whole day-lists (no day picked → show both).
                root.querySelectorAll('[data-fc-day-list]').forEach(function (list) {
                    list.hidden = (!!day && list.getAttribute('data-fc-day-list') !== day);
                });
                // Room + category filter individual rows.
                root.querySelectorAll('[data-fc-session]').forEach(function (li) {
                    var liRoom = li.getAttribute('data-fc-room') || '';
                    var tracks = (li.getAttribute('data-fc-tracks') || '').split(/\s+/).filter(Boolean);
                    var roomOK  = !room  || liRoom === room;
                    var trackOK = !track || tracks.indexOf(track) !== -1;
                    li.hidden = !(roomOK && trackOK);
                });
            }

            // Show the CURRENT option's text in the segment's visible label.
            //
            // The select itself is invisible and laid over that label (see
            // .fc-sched-seg in template-parts/sections/schedule.php), so the
            // segment is exactly as wide as the text in whatever font is on
            // screen. This used to measure a hidden copy of the text and set the
            // select's width — once, before the webfont loaded, which cut the text
            // off. Nothing is measured now, so there is nothing to get wrong.
            //
            // Also run at start-up: a browser restoring form state on Back can
            // bring a select back on a different option than the server rendered.
            function showChoice(sel) {
                var seg   = sel.closest('.fc-sched-seg');
                var label = seg && seg.querySelector('[data-fc-filter-value]');
                var opt   = sel.options[sel.selectedIndex];
                if (label && opt) label.textContent = opt.text;
            }

            [daySel, roomSel, trackSel].forEach(function (sel) {
                if (!sel) return;
                showChoice(sel);
                sel.addEventListener('change', function () {
                    showChoice(sel);
                    applyFilters();
                });
            });
            applyFilters();
        });
    })();

    // ---------- FAQ accordion ----------
    (function faqAccordion() {
        document.querySelectorAll('[data-fc-island="faq-list"]').forEach(function (list) {
            list.querySelectorAll('[data-fc-faq-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var item = btn.closest('[data-fc-faq-item]');
                    var body = item && item.querySelector('[data-fc-faq-body]');
                    var marker = btn.querySelector('[data-fc-faq-marker]');
                    if (!body) return;
                    var open = !body.classList.contains('hidden');
                    body.classList.toggle('hidden', open);
                    if (marker) marker.textContent = open ? '[+]' : '[−]';
                });
            });
        });
    })();

    // ---------- Glyph scramble ----------
    (function scramble() {
        if (reducedMotion) return;
        // Same global "type-in" reveal + weird glyph set as assets/scramble.js.
        var glyphs = 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ▓▒░█0123456789@#$%&*<>=+/?!';
        document.querySelectorAll('[data-fc-island="scramble"]').forEach(function (el) {
            var target = el.textContent || '';
            var payload = el.getAttribute('data-fc-payload');
            var delay = 0;
            if (payload) {
                try { delay = (JSON.parse(payload).delay) || 0; } catch (e) {}
            }
            var duration = Math.min(Math.max(target.length * 45, 300), 2200); // ~45ms/char, per-letter speed
            setTimeout(function () { runScramble(el, target, duration); }, delay);
        });

        function runScramble(el, target, duration) {
            var start = performance.now();
            var len = target.length;
            var EDGE = 3;
            function frame(t) {
                var progress = Math.min(1, (t - start) / duration);
                var shown = Math.floor(progress * len);
                var out = '';
                for (var i = 0; i < len; i++) {
                    var ch = target[i];
                    if (i < shown || ch === ' ' || ch === '\n') {
                        out += ch;
                    } else if (i < shown + EDGE) {
                        out += glyphs[Math.floor(Math.random() * glyphs.length)];
                    } else {
                        break;
                    }
                }
                el.textContent = out;
                if (progress < 1) {
                    requestAnimationFrame(frame);
                } else {
                    el.textContent = target;
                }
            }
            requestAnimationFrame(frame);
        }
    })();

    // (Hero background grain is now pure CSS — see .fc-hero-dots in assets/site.css.)

    // ---------- Venue map ----------
    // The interactive venue map lives in assets/venue-map.js (MapLibre GL JS +
    // OpenFreeMap). It self-mounts on [data-fc-island="venue-map"].
})();
