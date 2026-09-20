/**
 * Pointer and keyboard. Turns events into the small `intent` object physics.js
 * reads, and owns nothing else.
 *
 * The pointer half tracks a smoothed velocity for the anchor. Raw per-event
 * deltas are far too noisy to drive a torque — they produce a visible jitter in
 * the swing and a spin that fires off random flicks — so the anchor velocity is
 * low-pass filtered and the physics only ever sees the smoothed number.
 */

const VELOCITY_SMOOTHING = 0.35;
const STALE_VELOCITY_MS = 120;   // a pointer that stopped before release does not throw

export function createInput(view, opts) {
    // `frameOffset()` is where his positioning context sits in the viewport.
    // Pointer events are always viewport-relative but he lives in frame space,
    // so every client coordinate is translated on the way in — without it a drag
    // inside the admin's stage box would fling him by the stage's own offset.
    const { onGrab, onRelease, canGrab, frameOffset, hitTest } = opts;
    const offset = frameOffset || (() => ({ left: 0, top: 0 }));
    const hits = hitTest || (() => true);

    const intent = { walkDir: 0, jump: false, anchor: null };
    const keys = { left: false, right: false };

    const drag = {
        id: null,
        active: false,
        offsetX: 0, offsetY: 0,      // where on his body you took hold
        lastX: 0, lastY: 0, lastTs: 0,
        touch: false,
    };

    const anchor = { x: 0, y: 0, vx: 0, vy: 0 };

    /* ── pointer ────────────────────────────────────────────────────────── */

    function beginDrag(clientX, clientY) {
        const frame = offset();
        drag.active = true;
        anchor.x = clientX - frame.left - drag.offsetX;
        anchor.y = clientY - frame.top - drag.offsetY;
        anchor.vx = 0;
        anchor.vy = 0;
        intent.anchor = anchor;
        document.documentElement.setAttribute('data-fc-grabbing', '');
        onGrab(anchor);
    }

    function endDrag(throwIt) {
        const wasActive = drag.active;
        drag.id = null;
        drag.active = false;
        intent.anchor = null;
        document.documentElement.removeAttribute('data-fc-grabbing');
        if (wasActive) onRelease(throwIt ? anchor : { vx: 0, vy: 0 });
    }

    /**
     * Grab on contact — no hold, no movement threshold, mouse and touch alike.
     *
     * Touch used to need a 300ms press before it would pick him up, so the
     * browser could still claim a swipe that began on him. That is the right
     * trade for a big draggable panel and the wrong one for a 96px sprite: the
     * cost was that picking him up on a phone felt broken. He now takes the
     * gesture immediately, which is why .fcm-sprite carries `touch-action: none`
     * — the page simply does not scroll from a finger that starts on him.
     */
    function onPointerDown(e) {
        if (drag.id !== null || !canGrab()) return;
        if (e.button !== undefined && e.button !== 0) return;
        // The sprite's box is square and mostly empty; only the drawn pixels
        // count, so the transparent corners are not a grab handle.
        if (!hits(e.clientX, e.clientY)) return;

        // The anchor is the RAW pointer, with no grab offset preserved: he is
        // picked up BY a fixed point on himself (the top of his head), so where
        // within his sprite you happened to click does not follow you around.
        // physics.beginGrab snaps him onto the cursor accordingly.
        drag.id = e.pointerId;
        drag.offsetX = 0;
        drag.offsetY = 0;
        drag.lastX = e.clientX;
        drag.lastY = e.clientY;
        drag.lastTs = 0;
        drag.touch = e.pointerType === 'touch' || e.pointerType === 'pen';

        try { view.el.sprite.setPointerCapture(e.pointerId); } catch { /* not fatal */ }
        if (e.cancelable) e.preventDefault();
        beginDrag(e.clientX, e.clientY);
    }

    /**
     * Show the grab cursor only where he actually IS.
     *
     * The sprite's box is square and mostly empty, so a plain `cursor: grab` on
     * it promises a handle over transparent corners that onPointerDown then
     * refuses. Same hit test, so what the cursor says and what a click does
     * cannot disagree.
     */
    function onHover(e) {
        if (drag.active) return;
        setHot(hits(e.clientX, e.clientY));
    }

    function setHot(on) {
        if (on) view.root.setAttribute('data-fcm-hot', '');
        else view.root.removeAttribute('data-fcm-hot');
    }

    function onPointerMove(e) {
        if (drag.id !== e.pointerId || !drag.active) return;
        if (e.cancelable) e.preventDefault();
        const frame = offset();
        anchor.x = e.clientX - frame.left - drag.offsetX;
        anchor.y = e.clientY - frame.top - drag.offsetY;

        const now = e.timeStamp || performance.now();
        if (drag.lastTs) {
            const dt = (now - drag.lastTs) / 1000;
            if (dt > 0.001) {
                anchor.vx += (((e.clientX - drag.lastX) / dt) - anchor.vx) * VELOCITY_SMOOTHING;
                anchor.vy += (((e.clientY - drag.lastY) / dt) - anchor.vy) * VELOCITY_SMOOTHING;
            }
        }
        drag.lastX = e.clientX;
        drag.lastY = e.clientY;
        drag.lastTs = now;
    }

    function onPointerUp(e) {
        if (drag.id !== e.pointerId) return;
        try { view.el.sprite.releasePointerCapture(e.pointerId); } catch { /* fine */ }
        if (!drag.lastTs) { anchor.vx = 0; anchor.vy = 0; }   // pressed and released without moving
        // A velocity from a pointer that stopped moving before release should not
        // launch him: let go while still and he drops.
        const age = (e.timeStamp || performance.now()) - drag.lastTs;
        if (age > STALE_VELOCITY_MS) { anchor.vx = 0; anchor.vy = 0; }
        endDrag(true);
    }

    function onPointerCancel(e) {
        if (drag.id !== e.pointerId) return;
        endDrag(false);
    }

    /**
     * Belt and braces against the scroller.
     *
     * `touch-action: none` on the sprite should already stop the browser
     * treating a finger on him as a pan, but Safari in particular can still
     * start one from a touch that began before the style applied — and the
     * moment it does, it fires pointercancel and drops him mid-air. Suppressing
     * the default while a drag is live closes that window.
     *
     * (This comment used to describe `pan-y` and a long press. Both are gone:
     * he is grabbed the instant a finger lands on him.)
     */
    function onTouchMove(e) {
        if (drag.active) e.preventDefault();
    }

    /* ── keyboard ───────────────────────────────────────────────────────── */

    function isTyping(target) {
        if (!target) return false;
        const tag = target.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
    }

    /**
     * Arrows and WASD both. `e.key` is lower-cased because a held shift turns
     * 'a' into 'A' and the mascot should not stop responding mid-press.
     */
    const LEFT = new Set(['arrowleft', 'a']);
    const RIGHT = new Set(['arrowright', 'd']);
    const JUMP = new Set(['arrowup', 'w', ' ', 'spacebar']);

    function onKeyDown(e) {
        if (isTyping(e.target) || e.metaKey || e.ctrlKey || e.altKey) return;
        const k = String(e.key).toLowerCase();
        if (LEFT.has(k)) { keys.left = true; e.preventDefault(); }
        else if (RIGHT.has(k)) { keys.right = true; e.preventDefault(); }
        else if (JUMP.has(k)) { intent.jump = true; e.preventDefault(); }
    }

    function onKeyUp(e) {
        const k = String(e.key).toLowerCase();
        if (LEFT.has(k)) keys.left = false;
        else if (RIGHT.has(k)) keys.right = false;
    }

    /* ── wiring ─────────────────────────────────────────────────────────── */

    const sprite = view.el.sprite;
    sprite.addEventListener('pointerdown', onPointerDown);
    sprite.addEventListener('pointermove', onHover, { passive: true });
    sprite.addEventListener('pointerleave', () => setHot(false), { passive: true });
    sprite.addEventListener('pointermove', onPointerMove);
    sprite.addEventListener('pointerup', onPointerUp);
    sprite.addEventListener('pointercancel', onPointerCancel);
    sprite.addEventListener('touchmove', onTouchMove, { passive: false });
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('keyup', onKeyUp);
    // Alt-tabbing mid-hold means pointerup may never arrive, which would leave
    // him stuck hanging off a cursor that is no longer there.
    window.addEventListener('blur', () => { if (drag.id !== null) endDrag(false); });

    return {
        intent,
        get dragging() { return drag.active; },

        /**
         * Called once per frame, before physics.
         *
         * A step he takes by himself and a step you ask for are the same step —
         * there is no separate wander speed any more. Only the DIRECTION differs
         * in where it came from.
         */
        read(wanderDir) {
            const keyDir = (keys.right ? 1 : 0) - (keys.left ? 1 : 0);
            intent.walkDir = keyDir !== 0 ? keyDir : wanderDir;
            return intent;
        },

        /** Physics consumes `jump`; clear it once it has been handed over. */
        consumeJump() { intent.jump = false; },

        destroy() {
            setHot(false);
            sprite.removeEventListener('pointerdown', onPointerDown);
            sprite.removeEventListener('pointermove', onHover);
            sprite.removeEventListener('pointermove', onPointerMove);
            sprite.removeEventListener('pointerup', onPointerUp);
            sprite.removeEventListener('pointercancel', onPointerCancel);
            sprite.removeEventListener('touchmove', onTouchMove);
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('keyup', onKeyUp);
        },
    };
}
