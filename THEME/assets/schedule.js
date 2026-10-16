/* FOSSCOMM 2026 — the schedule's behaviour (template-parts/sections/schedule.php).
 *
 * Per schedule section ([data-fc-sx]; there are several while the candidate
 * layouts are being compared, and each is run on its own):
 *
 *   • the hour bar   each day's [data-fc-sx-hours] is its own sticky bar, not
 *                    part of the rail's scroller (a sticky element cannot stick
 *                    inside one), so its track is slid in step with the rail on
 *                    every scroll — one position, drawn twice.
 *   • day tabs       [data-fc-sx-day] buttons show one [data-fc-sx-panel], and
 *                    the new day opens at the SAME HOUR the old one was showing.
 *                    On a day of the conference, that day opens on load.
 *   • dragging       the rail and its hour bar are taken hold of with a mouse and
 *                    dragged sideways; a flick glides to a stop. The hand closes
 *                    on the press. Touch is left to the browser's own swipe. A
 *                    press that travels is a drag, and the click it ends on does
 *                    not open a session.
 *   • live state     sessions ([data-fc-sx-when]) are marked live or past by the
 *                    clock, and today's rail gets a line at the current minute.
 *   • details        tapping a session ([data-fc-sx-open]) opens its card in the
 *                    section's <dialog>.
 *
 * Nothing runs per frame except a flick's glide, which ends itself.
 * Inert without [data-fc-sx].
 */
(function () {
    'use strict';

    var roots = [].slice.call(document.querySelectorAll('[data-fc-sx]'));
    if (!roots.length) return;

    var SLOP = 5;          // px a press may travel and still be a click
    var DECAY = 0.004;     // velocity remaining after one second of glide

    function all(el, sel) { return [].slice.call(el.querySelectorAll(sel)); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function isoDay(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    /* ── One day: its scroller, its hour bar, and the geometry between them ── */
    var panels = all(document, '[data-fc-sx-panel]').map(function (panel) {
        var p = {
            el: panel,
            scroller: panel.querySelector('[data-fc-sx-scroll]'),
            hours: panel.querySelector('[data-fc-sx-hours]'),
            inner: panel.querySelector('[data-fc-sx-hours-inner]'),
            bar: panel.querySelector('[data-fc-sx-scrollbar]'),
            thumb: panel.querySelector('[data-fc-sx-thumb]'),
            lane: panel.querySelector('.fc-sx-lane'),
            from: +panel.getAttribute('data-from'),
            to: +panel.getAttribute('data-to')
        };
        /* The scrollbar's geometry: how far the thumb can travel, and how many
           pixels of rail one pixel of it is worth. */
        p.metrics = function () {
            var s = p.scroller;
            var track = p.bar ? p.bar.clientWidth : 0;
            var max = Math.max(0, s.scrollWidth - s.clientWidth);
            var thumb = max > 0 ? Math.max(24, track * s.clientWidth / s.scrollWidth) : 0;
            return { track: track, max: max, thumb: thumb, room: Math.max(1, track - thumb) };
        };
        // The hour bar and the scrollbar are drawn from the rail's position.
        p.sync = function () {
            var left = p.scroller.scrollLeft;
            if (p.inner) p.inner.style.transform = 'translate3d(' + (-left) + 'px,0,0)';
            if (p.thumb && p.bar && !p.bar.hidden) {
                var m = p.metrics();
                p.thumb.style.setProperty('--thumb-w', m.thumb.toFixed(1) + 'px');
                p.thumb.style.setProperty('--thumb-x', (m.max ? left / m.max * m.room : 0).toFixed(1) + 'px');

            }
        };
        p.scroller.addEventListener('scroll', p.sync, { passive: true });
        // Pixels per minute, as laid out — read off a lane, which is one day wide.
        p.ppm = function () {
            var w = p.lane ? p.lane.getBoundingClientRect().width : 0;
            return w / Math.max(1, p.to - p.from);
        };
        return p;
    });

    /* Whether a day has more of itself than fits — the only time it can be
       dragged, and the only time it wears the open hand. Re-decided on resize
       and when a day is shown, since a hidden panel measures as nothing. */
    function markDraggable() {
        panels.forEach(function (p) {
            var can = p.scroller.scrollWidth > p.scroller.clientWidth + 1;
            [p.scroller, p.hours].forEach(function (el) {
                if (!el) return;
                el.classList.toggle('is-draggable', can);
                // The custom cursor CSS (inc/bootstrap.php) dresses [data-fc-grab].
                if (can) el.setAttribute('data-fc-grab', ''); else el.removeAttribute('data-fc-grab');
            });
            if (p.bar) p.bar.hidden = !can;
            p.sync();
        });
    }
    window.addEventListener('resize', markDraggable, { passive: true });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(markDraggable);

    /* ── Dragging: the rail, and its hour bar, both move the rail ─────────── */
    panels.forEach(function (p) {
        var s = p.scroller;
        var id = null, handle = null, startX = 0, startLeft = 0, moved = 0, dragging = false;
        var lastX = 0, lastT = 0, velocity = 0, raf = 0, glideT = 0;
        var swallow = false;

        function glide(ts) {
            var dt = glideT ? Math.min(0.05, (ts - glideT) / 1000) : 0.016;
            glideT = ts;
            s.scrollLeft -= velocity * dt;
            velocity *= Math.pow(DECAY, dt);
            raf = Math.abs(velocity) > 20 ? requestAnimationFrame(glide) : 0;
        }

        function down(e) {
            // A mouse or pen only: a finger already scrolls this natively.
            if (e.pointerType === 'touch' || e.button !== 0 || id !== null) return;
            if (!s.classList.contains('is-draggable')) return;
            if (raf) { cancelAnimationFrame(raf); raf = 0; }
            id = e.pointerId;
            handle = e.currentTarget;
            startX = lastX = e.clientX;
            startLeft = s.scrollLeft;
            lastT = e.timeStamp;
            moved = 0;
            velocity = 0;
            dragging = false;
            swallow = false;
            // The hand closes the moment it holds — the class is the cursor. A
            // press that never travels is still a click on the session under it.
            handle.classList.add('is-held');
            handle.setAttribute('data-fc-grabbing', '');
        }

        function move(e) {
            if (e.pointerId !== id) return;
            var dx = e.clientX - startX;
            moved = Math.max(moved, Math.abs(dx));
            if (moved <= SLOP) return;
            if (!dragging) {
                // Only now is it a drag: take the pointer, so the moves keep
                // coming off the edge. Not on the press — capture there would
                // retarget the click away from the session it was aimed at.
                dragging = true;
                try { handle.setPointerCapture(e.pointerId); } catch (err) {}
            }
            s.scrollLeft = startLeft - dx;
            var dt = (e.timeStamp - lastT) / 1000;
            if (dt > 0.001) {
                velocity = (e.clientX - lastX) / dt;
                lastX = e.clientX;
                lastT = e.timeStamp;
            }
            e.preventDefault();
        }

        function end(e) {
            if (e.pointerId !== id) return;
            id = null;
            handle.classList.remove('is-held');
            handle.removeAttribute('data-fc-grabbing');
            if (handle.hasPointerCapture && handle.hasPointerCapture(e.pointerId)) handle.releasePointerCapture(e.pointerId);
            if (!dragging) return;
            dragging = false;
            swallow = true;
            // Stale velocity (the hand stopped before letting go) is no flick.
            if (e.timeStamp - lastT < 80 && Math.abs(velocity) > 60) {
                glideT = 0;
                raf = requestAnimationFrame(glide);
            }
        }

        [s, p.hours].forEach(function (el) {
            if (!el) return;
            el.addEventListener('pointerdown', down);
            el.addEventListener('pointermove', move);
            el.addEventListener('pointerup', end);
            el.addEventListener('pointercancel', end);
            el.addEventListener('dragstart', function (e) { e.preventDefault(); });
        });
        // And on the window, for a press that never became a drag and so was
        // never captured: let go off the rail and the rail hears nothing, which
        // would leave the hand closed.
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);

        // The click a drag ends on is part of the drag, not a tap on a session.
        s.addEventListener('click', function (e) {
            if (!swallow) return;
            swallow = false;
            e.preventDefault();
            e.stopPropagation();
        }, true);
    });

    /* ── The scrollbar's thumb: dragged, it moves the rail in proportion ── */
    panels.forEach(function (p) {
        if (!p.thumb) return;
        var id = null, startX = 0, startLeft = 0, ratio = 1;
        p.thumb.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            // Its own gesture: not a drag of the hour bar it sits on.
            e.stopPropagation();
            e.preventDefault();
            var m = p.metrics();
            id = e.pointerId;
            startX = e.clientX;
            startLeft = p.scroller.scrollLeft;
            ratio = m.max / m.room;
            p.thumb.classList.add('is-held');
            try { p.thumb.setPointerCapture(e.pointerId); } catch (err) {}
        });
        p.thumb.addEventListener('pointermove', function (e) {
            if (e.pointerId !== id) return;
            p.scroller.scrollLeft = startLeft + (e.clientX - startX) * ratio;
            p.sync();
        });
        function end(e) {
            if (e.pointerId !== id) return;
            id = null;
            p.thumb.classList.remove('is-held');
        }
        p.thumb.addEventListener('pointerup', end);
        p.thumb.addEventListener('pointercancel', end);
    });

    /* ── Per section: days, details, live state ─────────────────────────── */
    roots.forEach(function (root) {
        var tabs = all(root, '[data-fc-sx-day]');
        var mine = panels.filter(function (p) { return root.contains(p.el); });

        function showDay(key) {
            // The hour at the left edge of the day being left, in minutes.
            var old = mine.filter(function (p) { return !p.el.hidden; })[0];
            var minute = null;
            if (old && old.ppm() > 0) minute = old.from + old.scroller.scrollLeft / old.ppm();

            tabs.forEach(function (t) {
                var on = t.getAttribute('data-fc-sx-day') === key;
                t.classList.toggle('is-on', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            mine.forEach(function (p) { p.el.hidden = p.el.getAttribute('data-fc-sx-panel') !== key; });
            markDraggable();

            // …and the new day opens at that same hour, as near as it has one.
            var next = mine.filter(function (p) { return !p.el.hidden; })[0];
            if (next && minute !== null) {
                next.scroller.scrollLeft = Math.max(0, (minute - next.from) * next.ppm());
                next.sync();
            }
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                if (!t.classList.contains('is-on')) showDay(t.getAttribute('data-fc-sx-day'));
            });
        });
        var today = isoDay(new Date());
        tabs.forEach(function (t) {
            if (t.getAttribute('data-fc-sx-date') === today) showDay(t.getAttribute('data-fc-sx-day'));
        });

        var sheet = root.querySelector('[data-fc-sx-sheet]');
        var sheetBody = sheet && sheet.querySelector('[data-fc-sx-sheet-body]');
        root.addEventListener('click', function (e) {
            var block = e.target.closest && e.target.closest('[data-fc-sx-open]');
            if (!block || !sheet) return;
            var tpl = block.querySelector('template[data-fc-sx-detail]');
            if (!tpl) return;
            sheetBody.innerHTML = '';
            sheetBody.appendChild(tpl.content.cloneNode(true));
            // The card's own colour, for its bar and tint.
            var tone = getComputedStyle(block).getPropertyValue('--tone').trim();
            if (tone) sheet.style.setProperty('--sx-entry', tone); else sheet.style.removeProperty('--sx-entry');
            if (sheet.showModal) sheet.showModal(); else sheet.setAttribute('open', '');
        });
        if (sheet) {
            /* Close with the slide down where the stylesheet has one (a phone,
               motion allowed): mark it closing, and close the dialog when that
               animation ends. Elsewhere — or if no animation runs — at once. */
            var closeSheet = function () {
                if (!sheet.open || sheet.classList.contains('is-closing')) return;
                sheet.classList.add('is-closing');
                var name = getComputedStyle(sheet).animationName;
                if (!name || name === 'none' || name.indexOf('fc-sx-sheet-down') < 0) {
                    sheet.classList.remove('is-closing');
                    sheet.close();
                    return;
                }
                var timer = 0;
                var done = function () {
                    clearTimeout(timer);
                    sheet.removeEventListener('animationend', done);
                    if (!sheet.classList.contains('is-closing')) return;
                    sheet.classList.remove('is-closing');
                    sheet.close();
                };
                sheet.addEventListener('animationend', done);
                // A backstop: an animation that never reports its end (a tab
                // sent to the background mid-slide) must not leave it open.
                timer = setTimeout(done, 320);
            };
            sheet.querySelector('[data-fc-sx-close]').addEventListener('click', closeSheet);
            // The dialog fills its own padding, so a click whose target is the
            // <dialog> itself landed on the backdrop.
            sheet.addEventListener('click', function (e) { if (e.target === sheet) closeSheet(); });
            // Escape: the same slide, instead of the browser's instant close.
            sheet.addEventListener('cancel', function (e) { e.preventDefault(); closeSheet(); });
        }

        var rows = all(root, '[data-fc-sx-when]').map(function (el) {
            return { el: el, start: Date.parse(el.getAttribute('data-start')) || 0, end: Date.parse(el.getAttribute('data-end')) || 0 };
        }).filter(function (r) { return r.start; });

        var scrolledToNow = false;
        function tick() {
            var t = Date.now();
            rows.forEach(function (r) {
                r.el.classList.toggle('is-past', r.end <= t);
                r.el.classList.toggle('is-live', r.start <= t && t < r.end);
            });
            var now = new Date(t);
            var mins = now.getHours() * 60 + now.getMinutes();
            mine.forEach(function (p) {
                var rail = p.el.querySelector('[data-fc-sx-rail]');
                var line = rail.querySelector('[data-fc-sx-nowline]');
                var on = rail.getAttribute('data-date') === isoDay(now) && mins >= p.from && mins <= p.to;
                line.hidden = !on;
                if (!on) return;
                p.el.style.setProperty('--now', String(mins - p.from));
                // Once, on load: bring the current minute into view, a little in
                // from the left so what just started is visible too.
                if (!scrolledToNow) {
                    scrolledToNow = true;
                    p.scroller.scrollLeft = Math.max(0, line.offsetLeft - p.scroller.clientWidth * 0.3);
                    p.sync();
                }
            });
        }
        tick();
        setInterval(tick, 60000);
    });

    markDraggable();
}());
