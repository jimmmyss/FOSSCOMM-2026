/* FOSSCOMM 2026 — hold and drag, for anything that drifts.
 *
 * Used by the speakers row and by the hero's three strips. Both drift with a CSS
 * animation, and this drags them by SCRUBBING THAT ANIMATION'S TIMELINE rather
 * than adding an offset of its own.
 *
 * That is the whole design, and it is what the first attempt got wrong. A
 * separate offset is easy — a variable, a transform, done — but then the row has
 * two positions: the one the animation believes and the one the hand gave it.
 * Anything else riding the same clock, the indicator above the row, keeps the
 * animation's answer and drifts out of step with what is on screen. Scrubbing
 * leaves exactly one position, so the dots follow the hand for free.
 *
 * An animation that moves its content by `span` over `dur` is a position in
 * disguise: sliding by dx is the same as moving time by dx × dur / span. Every
 * animation in the subtree that belongs to the belt is moved by the same amount,
 * which is what keeps the row and its indicator together.
 *
 * The only frame loop here is a flick's decay, which ends itself in about a
 * second. The drift stays on the compositor.
 *
 * Markup contract:
 *   [data-fc-drag]     the element to drag. Every belt animation inside it is
 *                      scrubbed; it also carries .is-dragging while held.
 *   --fc-drag-span     how far one cycle of that animation moves the content,
 *                      SIGNED: negative when the content travels left.
 *                      Zero (or unset) means there is nothing to drag.
 *   --fc-drag-gain     optional multiplier on the hand's movement, for content
 *                      drawn at a different scale than it is seen. The hero's
 *                      strips are built k times too big and scaled back inside
 *                      their own transform, so a hand moving 100 screen pixels
 *                      moves the words k times that in their own space.
 */
(function () {
    'use strict';

    var SLOP = 6;          // px of travel before a press counts as a drag
    var DECAY = 0.0025;    // velocity remaining after one second
    var MIN_FLICK = 40;    // px/s under which a release adds nothing

    // The animations this file is allowed to move. Anything else in the subtree
    // — the ONLINE badge's pulse, say — is left alone.
    var BELT_ANIMATIONS = ['fc-spk-belt', 'fc-spk-progress', 'fc-band-marquee'];

    var reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var targets = [].slice.call(document.querySelectorAll('[data-fc-drag]'));
    if (!targets.length) return;

    targets.forEach(function (el) {
        var pointer = null;
        var startX = 0, moved = 0;
        var lastX = 0, lastT = 0, velocity = 0;
        var raf = 0;

        function number(name) {
            var v = parseFloat(getComputedStyle(el).getPropertyValue(name));
            return isFinite(v) ? v : 0;
        }

        /** The belt's own animations, inside this element. */
        function belts() {
            if (!el.getAnimations) return [];
            return el.getAnimations({ subtree: true }).filter(function (a) {
                return BELT_ANIMATIONS.indexOf(a.animationName) > -1;
            });
        }

        /** Move the belt by dx CSS pixels, as time. */
        function slide(dx) {
            var span = number('--fc-drag-span');
            if (!span) return;
            belts().forEach(function (a) {
                var dur = a.effect && a.effect.getTiming().duration;
                if (!dur || dur === Infinity) return;
                var t = a.currentTime || 0;
                // Time runs in a circle: one cycle is one span, so the position
                // stays in range however far the hand goes.
                a.currentTime = ((t + dx * dur / span) % dur + dur) % dur;
            });
        }

        function glide(ts) {
            raf = 0;
            var dt = lastT ? Math.min(0.05, (ts - lastT) / 1000) : 0.016;
            lastT = ts;
            slide(velocity * dt);
            velocity *= Math.pow(DECAY, dt);
            if (Math.abs(velocity) > 1) {
                raf = requestAnimationFrame(glide);
            } else {
                belts().forEach(function (a) { a.play(); });
            }
        }

        el.addEventListener('pointerdown', function (e) {
            if (pointer !== null || e.button > 0) return;
            // Nothing behind the edges to slide in: a row that fits on the
            // screen, or a strip whose words do not repeat.
            if (!number('--fc-drag-span')) return;
            pointer = e.pointerId;
            startX = lastX = e.clientX;
            moved = 0;
            velocity = 0;
            lastT = 0;
            if (raf) { cancelAnimationFrame(raf); raf = 0; }
            /* The hand closes the moment it holds, not after it has travelled:
             * the class is the cursor, and a cursor that only appears once you
             * have already moved is telling you what you just did rather than
             * what you can do. The SLOP below still decides what counts as a
             * drag for the click it might land on. */
            el.classList.add('is-dragging');
            // Held still under the hand — and pausing first means the drift
            // cannot advance between two moves and fight the drag.
            belts().forEach(function (a) { a.pause(); });
            // Capture keeps the moves coming when the hand leaves the element.
            // It throws if the pointer is not really down; the drag works anyway.
            try { el.setPointerCapture(e.pointerId); } catch (err) {}
        });

        el.addEventListener('pointermove', function (e) {
            if (pointer !== e.pointerId) return;
            var raw = e.clientX - lastX;
            moved = Math.max(moved, Math.abs(e.clientX - startX));

            var gain = number('--fc-drag-gain') || 1;
            slide(raw * gain);

            // Velocity from the LAST movement only, so a release throws it the
            // way the hand was going rather than the average of the whole drag.
            var now = e.timeStamp || performance.now();
            if (lastT) {
                var secs = (now - lastT) / 1000;
                if (secs > 0) velocity = raw * gain / secs;
            }
            lastX = e.clientX;
            lastT = now;
        });

        function release(e) {
            if (pointer !== e.pointerId) return;
            pointer = null;
            el.classList.remove('is-dragging');
            if (el.hasPointerCapture && el.hasPointerCapture(e.pointerId)) {
                el.releasePointerCapture(e.pointerId);
            }
            if (!reduced && Math.abs(velocity) > MIN_FLICK) {
                lastT = 0;
                raf = requestAnimationFrame(glide);   // a flick throws it
            } else {
                belts().forEach(function (a) { a.play(); });
            }
            // A drag that ends on a link must not also follow it.
            if (moved > SLOP) {
                var swallow = function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                };
                el.addEventListener('click', swallow, { capture: true, once: true });
                setTimeout(function () {
                    el.removeEventListener('click', swallow, true);
                }, 0);
            }
        }
        el.addEventListener('pointerup', release);
        el.addEventListener('pointercancel', release);

        // A picture is not a thing to drag off the page; the gesture is ours.
        el.addEventListener('dragstart', function (e) { e.preventDefault(); });
    });
}());
