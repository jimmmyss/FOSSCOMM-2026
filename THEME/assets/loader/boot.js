/* FOSSCOMM 2026 — the loading screen, run.
 *
 * Printed inline by inc/loader.php straight after the overlay and loader.js, so
 * it starts the moment the overlay exists. The sequence:
 *
 *   1. the logos decode (capped) — the flicker must not start on a blank box;
 *   2. the flicker plays, ending lit;
 *   3. it waits until the page has really loaded — `load`, THEN the fonts (read
 *      after load: before it, `document.fonts.ready` can be an already-settled
 *      promise from before any font was requested), two frames, and a short
 *      settle for the scripts that lay out on `load` — and, if he is on, for the
 *      mascot's art; all of it capped, counted from when the page started
 *      loading, and buzzing gently while it waits; then until the logo has been
 *      steadily lit for the timeline's last hold;
 *   4. the mascot takes the logo's place and plays Opening, and the three layers
 *      sweep away one after another — timed, by default, so that the first
 *      one's edge reaches him the moment Opening ends: the floor goes right
 *      where his animation stops and he drops straight out of its last frame
 *      (or as set in the dashboard; loader.js takeOver());
 *   5. the last layer reveals the site, and finish() takes the overlay away.
 *
 * Anything that throws, anywhere, finishes at once: the worst a broken loading
 * screen may do is get out of the way early. window.fcLoader (from the head
 * script) owns the way out — finish() there is also what its failsafe timer
 * calls — and this file only adds its own cleanup to it.
 */
(function (w, d) {
    'use strict';

    var L = w.fcLoader;
    var root = d.getElementById('fc-loader');

    function removeOverlay() {
        if (root && root.parentNode) root.parentNode.removeChild(root);
    }

    if (!L || L.state !== 'loading' || !root || !w.FCLoader) {
        removeOverlay();
        if (L && L.state === 'loading' && L.finish) L.finish();
        return;
    }

    var cfg = w.FCLoader.readConfig(w.FC_LOADER);
    var ctl = w.FCLoader.create(root, w.FC_LOADER, {});
    var reduced = !!(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches);

    /* ── No scrolling underneath ────────────────────────────────────────────
     * Never by touching overflow on <html>/<body>: on a desktop with real
     * scrollbars that changes the layout width the belts, the schedule, the dot
     * field and the mascot measure while the page loads, and they would all be
     * one scrollbar off when it came back. The overlay swallows the gestures
     * instead (it is touch-action: none, too), and the keys that scroll. */
    var SCROLL_KEYS = { ' ': 1, Spacebar: 1, PageUp: 1, PageDown: 1, Home: 1, End: 1, ArrowUp: 1, ArrowDown: 1, ArrowLeft: 1, ArrowRight: 1 };
    function stop(e) { if (e.cancelable) e.preventDefault(); }
    function stopKeys(e) {
        if (!SCROLL_KEYS[e.key]) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault();
    }
    w.addEventListener('wheel', stop, { passive: false });
    w.addEventListener('touchmove', stop, { passive: false });
    w.addEventListener('keydown', stopKeys, true);
    function unguard() {
        w.removeEventListener('wheel', stop, { passive: false });
        w.removeEventListener('touchmove', stop, { passive: false });
        w.removeEventListener('keydown', stopKeys, true);
    }

    // The head script's finish() calls this however the end comes — normally,
    // or from its failsafe timer if this file has died somewhere.
    L.cleanup = function () {
        unguard();
        try { ctl.destroy(); } catch (e) {}
        removeOverlay();
    };

    var done = false;
    function finish() {
        if (done) return;
        done = true;
        if (L.finish) L.finish();
        else { L.cleanup(); L.state = 'done'; d.documentElement.removeAttribute('data-fc-loader'); }
    }
    function fail(err) {
        if (w.console && console.error) console.error('[loader]', err);
        finish();
    }
    function over() { return done || L.state !== 'loading'; }

    // Restored from the back/forward cache: whatever it was doing is moot.
    w.addEventListener('pageshow', function (e) { if (e.persisted) finish(); });

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, Math.max(0, ms)); }); }
    function within(p, ms) { return Promise.race([p, sleep(ms)]); }
    function sinceStart() { return w.performance && performance.now ? performance.now() : 0; }
    function untilCap() { return Math.max(0, cfg.maxWaitMs - sinceStart()); }

    /* The logos are drawn as CSS backgrounds or masks; decoding the same file
       through an Image warms the cache they draw from, so the flicker never
       starts on an empty box. */
    function logosDecoded() {
        var all = ctl.logoUrls().map(function (url) {
            var img = new Image();
            img.src = url;
            if (typeof img.decode === 'function') return img.decode().catch(function () {});
            return new Promise(function (r) { img.onload = img.onerror = r; });
        });
        return within(Promise.all(all), 1500);
    }

    /* The second and third layers carry part of the page before the page itself
       (loader.js, scenes()): the white one the site's own dots, the top bar and
       the wordmark in blue; the last blue one the bar and the wordmark in white
       — and then the site, with both already exactly where they are. Built
       now, while the lit logo is still up and nothing moves. The dots are the
       white layer's own setting; the copies are "build". */
    function buildScenes() {
        var parts = {};
        if (cfg.build) {
            parts.bar = d.querySelector('[data-fc-reveal="bar"]') || d.querySelector('header.fc-bar');
            parts.mark = d.querySelector('[data-fc-reveal="wordmark"]') || d.querySelector('.fc-hero-wordmark');
        }
        try {
            ctl.scenes(parts);
        } catch (err) {
            if (w.console && console.warn) console.warn('[loader] copies of the page skipped', err);
        }
    }

    /* The first layer only moves once the logo is finally, steadily on: lit,
       without a break, for at least the timeline's last hold. Straight after the
       flicker that is already true; after a stutter while the page was loading,
       this waits for the light to have settled again. */
    function steadilyLit() {
        var hold = cfg.steps[cfg.steps.length - 1].ms;
        return sleep(hold - ctl.litFor());
    }

    function pageReady() {
        var loaded = d.readyState === 'complete' ? Promise.resolve()
            : new Promise(function (r) { w.addEventListener('load', r, { once: true }); });
        return loaded
            .then(function () { return d.fonts && d.fonts.ready ? d.fonts.ready.catch(function () {}) : null; })
            .then(function () { return new Promise(function (r) { requestAnimationFrame(function () { requestAnimationFrame(r); }); }); })
            // 200ms for the speakers belt's debounced relayout and the Tailwind
            // runtime's late rules; plus, in a dashboard preview, the "slow load".
            .then(function () { return sleep(200 + cfg.slowMs); });
    }

    function mascotNow() {
        var m = w.fcMascot;
        return m && typeof m.openAt === 'function' ? m : null;
    }
    /* He is built after the page has been parsed (assets/mascot/js/boot.js),
       long after this runs at the top of <body>: so the wait is for his
       announcement — that he is ready, or that his art would not load — and,
       once the page has been parsed, for the discovery that there is no mascot
       on it after all. The cap on `ready` bounds it whatever happens. */
    function mascotReady() {
        if (!cfg.mascot) return Promise.resolve(null);
        var m = mascotNow();
        if (m) return Promise.resolve(m);
        return new Promise(function (r) {
            d.addEventListener('fc:mascot-ready', function (e) { r((e && e.detail) || mascotNow()); }, { once: true });
            d.addEventListener('fc:mascot-failed', function () { r(null); }, { once: true });
            function noMount() { if (!d.getElementById('fosscomm-mascot')) r(null); }
            if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', noMount, { once: true });
            else noMount();
        });
    }

    function reveal() {
        if (over()) return null;
        buildScenes();
        var m = cfg.mascot ? mascotNow() : null;
        if (!m || m.arrived) return ctl.exit();

        /* He takes the logo's place and plays Opening, and the layers sweep
           away — by default timed so the first edge reaches him the moment
           Opening ends, and he drops straight out of its last frame (see
           loader.js takeOver(), which the dashboard's previews run too). The
           Opening cap there is the same number the PHP failsafe budgets for
           (fc_loader_failsafe_ms). */
        var letGo = function () { if (typeof m.release === 'function') m.release(); };
        return ctl.takeOver(m).then(function (done) {
            letGo();            // whatever happened, he is not left hanging
            return done;
        }, function (err) {
            letGo();
            throw err;
        });
    }

    function start() {
        // A tab opened in the background: no frames are drawn there, so there is
        // nothing to watch — the site is simply there when it is looked at.
        if (d.visibilityState === 'hidden') { finish(); return; }

        var ready = Promise.all([pageReady(), mascotReady()]);
        var isReady = false;
        ready.then(function () { isReady = true; });

        logosDecoded().then(function () {
            if (over()) return null;

            if (reduced) {
                // No strobe, no squash, no swipe: lit at once, and a fade once
                // the page is ready (sooner than the full cap — nothing is moving).
                ctl.light(true);
                return within(ready, Math.min(4000, untilCap()))
                    .then(function () { return over() ? null : ctl.fade(); });
            }

            return ctl.flicker().then(function (completed) {
                if (!completed || over()) return null;
                if (!isReady && cfg.buzz) ctl.buzz(true);
                return within(ready, untilCap()).then(function () {
                    ctl.buzz(false);
                    return steadilyLit();
                }).then(reveal);
            });
        }).then(finish, fail);
    }

    try { start(); } catch (err) { fail(err); }
}(window, document));
