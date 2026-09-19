/**
 * Section reactions: he plays a one-off animation when a section scrolls in.
 *
 * The active section is decided exactly the way the sidebar nav decides it
 * (assets/section-nav.js) — the LAST <section> whose top has crossed a line 35%
 * down the viewport. Same rule, same constant, so the highlighted nav item and
 * the mascot's reaction always agree about which section you are in. Anything
 * else and he reacts a scroll-beat away from the sidebar, which reads as a bug.
 *
 * Reactions fire on ENTERING a section, once, and last for the hold time
 * configured on the row. Scrolling back into a section fires it again; sitting
 * in one does not re-fire it.
 */

const TRIGGER_FRACTION = 0.35;

export function createSections(reactions, onEnter, onLeave) {
    if (!reactions.length) return { destroy() {} };
    const leave = onLeave || (() => {});

    // selector → the reactions watching it. Several rows may target the same
    // section; one is picked at random per entry, the same way a state picks
    // between its entries.
    const byTarget = new Map();
    for (const row of reactions) {
        const list = byTarget.get(row.selector) || [];
        list.push(row);
        byTarget.set(row.selector, list);
    }

    let activeEl = null;
    let raf = 0;
    // The reaction for the section he is in right now, so a deferred one can be
    // checked against it before it finally plays.
    let currentRow = null;

    function resolve(el) {
        for (const [selector, rows] of byTarget) {
            let match = null;
            try { match = document.querySelector(selector); } catch { match = null; }
            if (match && match === el) return rows;
        }
        return null;
    }

    /**
     * Where every section starts, in DOCUMENT coordinates, measured once.
     *
     * This used to walk every <section> and call getBoundingClientRect() on each
     * one, on every animation frame you were scrolling — and it shares those
     * frames with the mascot's own loop, which writes a transform. Reading
     * layout after that write forces the browser to redo the whole document's
     * layout synchronously, so the cost landed on the scroll itself.
     *
     * Document offsets do not change as you scroll, only when the page reflows,
     * so they are cached and scrolling becomes pure arithmetic. `scrollHeight`
     * is the tripwire for a reflow — one read instead of one per section, and it
     * catches the cases that actually move things: images and fonts arriving,
     * something expanding, the window resizing.
     */
    let tops = null;
    let measuredAt = -1;
    let measuredH = 0;

    function measure() {
        const sections = document.querySelectorAll('section');
        const scrollY = window.scrollY;
        tops = [];
        for (const sec of sections) {
            tops.push({ el: sec, top: sec.getBoundingClientRect().top + scrollY });
        }
        measuredH = document.documentElement.scrollHeight;
        measuredAt = window.innerWidth;
    }

    function computeActive() {
        if (!tops
            || measuredAt !== window.innerWidth
            || document.documentElement.scrollHeight !== measuredH) {
            measure();
        }
        if (!tops.length) return null;

        // clientHeight, not innerHeight: the layout viewport, which a pinch zoom
        // leaves alone. innerHeight shrinks as you zoom in, which would drag the
        // trigger line up the page and fire reactions early.
        const viewH = document.documentElement.clientHeight || window.innerHeight;
        const trigger = window.scrollY + viewH * TRIGGER_FRACTION;
        let active = null;
        for (const row of tops) {
            if (row.top <= trigger) active = row.el;
        }
        return active;
    }

    function check() {
        raf = 0;
        const el = computeActive();
        if (el === activeEl) return;
        activeEl = el;

        // The section you were reacting to is no longer the one you are in, so
        // whatever it started stops here — scrolling past mid-animation should
        // leave the reaction behind with the section, not carry it up the page.
        // A no-op when nothing is playing.
        currentRow = null;
        leave();

        if (!el) return;
        const rows = resolve(el);
        if (!rows || !rows.length) return;
        currentRow = rows[Math.floor(Math.random() * rows.length)];
        onEnter(currentRow);
    }

    function schedule() {
        if (!raf) raf = requestAnimationFrame(check);
    }

    function remeasure() { tops = null; schedule(); }

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', remeasure, { passive: true });
    // Late-arriving images and webfonts move every section below them, and the
    // scrollHeight tripwire only catches that if the total height changed. Load
    // is the one moment worth re-measuring outright.
    window.addEventListener('load', remeasure, { once: true });
    // Settle the starting section without firing: landing mid-page should not
    // replay a reaction for a section you never scrolled into.
    activeEl = computeActive();

    return {
        /** The reaction for the section he is in now, or null. */
        get current() { return currentRow; },
        destroy() {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', remeasure);
            window.removeEventListener('load', remeasure);
            if (raf) cancelAnimationFrame(raf);
        },
    };
}
