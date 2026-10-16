/**
 * Front-end entry point. The ONLY file with a side effect on import.
 *
 * Kept apart from main.js on purpose: wp-admin imports createMascot() to drive
 * the preview stage, and an auto-boot inside main.js would mean importing the
 * factory also tried to start a second mascot on whatever page did the
 * importing. A module that does something merely by being imported is a module
 * you can't reuse.
 *
 * ── The loading screen ──────────────────────────────────────────────────────
 * When the loading screen is up (inc/loader.php), he must not drop in on his own:
 * he takes the lit logo's place, plays Opening there and falls onto the site as
 * it is revealed — and the loader decides when. So he is built in 'wait' mode
 * (every sheet loaded and decoded, nothing on screen, no frame loop), announced
 * with `fc:mascot-ready`, and left for the loader to call openAt() on.
 *
 * window.fcLoader is the loader's STICKY state, written by an inline head script
 * long before this module runs; the events are not sticky, which is why both are
 * checked. If the loader finished — revealed the site, gave up waiting, or its
 * failsafe fired — before his art had loaded, he simply arrives the ordinary way,
 * and `fc:loader-done` does the same for a loader that ends without using him.
 */

import { createMascot } from './main.js';

function boot() {
    const root = document.getElementById('fosscomm-mascot');
    if (!root || root.dataset.fcmBooted) return;
    root.dataset.fcmBooted = '1';

    const loader = window.fcLoader;
    const waiting = !!(loader && loader.state === 'loading' && document.getElementById('fc-loader'));

    createMascot(root, window.FC_MASCOT, { arrival: waiting ? 'wait' : 'drop' }).then((mascot) => {
        // One handle, for the console, for the loading screen, and for anything
        // else that wants to poke him.
        window.fcMascot = mascot;
        if (!waiting) return;

        // Over already, while his sheets were loading: no logo left to take over.
        if (!window.fcLoader || window.fcLoader.state !== 'loading') {
            mascot.arrive();
            return;
        }
        // arrive() is a no-op once the loader has placed him, and lets him go if
        // it is still holding him in the air — so this is safe whichever way the
        // loading screen ends.
        document.addEventListener('fc:loader-done', () => mascot.arrive(), { once: true });
        document.dispatchEvent(new CustomEvent('fc:mascot-ready', { detail: mascot }));
    }, (err) => {
        // No mascot after all (his art would not load): a loading screen waiting
        // to hand over to him is told so, rather than left waiting out its cap.
        if (window.console) console.warn('[mascot]', err);
        document.dispatchEvent(new CustomEvent('fc:mascot-failed'));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
