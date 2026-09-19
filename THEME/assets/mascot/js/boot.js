/**
 * Front-end entry point. The ONLY file with a side effect on import.
 *
 * Kept apart from main.js on purpose: wp-admin imports createMascot() to drive
 * the preview stage, and an auto-boot inside main.js would mean importing the
 * factory also tried to start a second mascot on whatever page did the
 * importing. A module that does something merely by being imported is a module
 * you can't reuse.
 */

import { createMascot } from './main.js';

function boot() {
    const root = document.getElementById('fosscomm-mascot');
    if (!root || root.dataset.fcmBooted) return;
    root.dataset.fcmBooted = '1';
    createMascot(root, window.FC_MASCOT).then((mascot) => {
        // One handle, for the console and for anything that wants to poke him.
        window.fcMascot = mascot;
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
