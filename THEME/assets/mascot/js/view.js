/**
 * Everything that touches the DOM.
 *
 * One rule: nothing else in the app reads or writes an element. The machine and
 * the physics deal in numbers, this turns numbers into pixels. That separation
 * is what makes the admin preview possible — it drives the same view against a
 * different root element and a different scale.
 *
 * The element tree, built once:
 *
 *   #fosscomm-mascot            positioning root, fixed to the viewport
 *     .fcm-body                 translated + rotated; the thing physics moves
 *       .fcm-sprite             the sheet window; flipped for facing, squashed on impact
 *     .fcm-particles            particle layer, un-rotated so particles stay world-aligned
 */

import { paintCell } from './animator.js';

/**
 * How much height a squash takes away, as a fraction, for a given Bounciness.
 *
 * Bounciness scales the DEPTH of the deformation and nothing else. It used to
 * multiply the whole envelope and then clamp the product — which quietly meant
 * that the harder you set it, the LONGER he stayed deformed: multiplying flattens
 * the top of a decaying envelope, so at 10 he sat pinned at the clamp, 16% tall,
 * for a third of a second before unsqueezing. That is the pancake.
 *
 * The saturating curve does the job the clamp was there for, without ever
 * clipping: depth rises with bounciness and levels off. Because the envelope is
 * only ever scaled, a squash now lasts the same length of time at every setting
 * — only its depth changes, which is what the control is supposed to mean.
 *
 * Tuned so 1 lands on 0.35, the value that was hard-coded before it was a
 * setting, and 10 reaches 0.58 — deep, but still recognisably him.
 */
export function squashDepth(bounce) {
    if (!(bounce > 0)) return 0;
    return 0.86 * (bounce / (bounce + 1.45));
}

/**
 * Peak squash for a one-off pop — an animation change, a footfall — at a given
 * Bounciness.
 *
 * Bounciness has to scale the AMPLITUDE as well as the depth, or the control
 * does nothing outside of hard landings: a fixed pop against a depth curve that
 * only ran 0.35→0.58 moved the sprite by 4% across the whole 0–10 range, which
 * is why it felt permanently stuck at 1.
 *
 * The cap is what keeps that safe. `squash × depth` must stay below 1 or scaleY
 * goes through zero and the sprite turns inside out — which is the failure the
 * old hard clamp was papering over, and the reason it pinned him flat. At 0.6
 * against a maximum depth of 0.75 the worst case is 0.45, comfortably clear.
 */
export const POP_MAX = 0.6;

export function squashPop(base, bounce) {
    if (!(bounce > 0)) return 0;
    return Math.min(POP_MAX, base * bounce);
}

/**
 * The two weights a pop can have, before the Bounciness scales them.
 *
 * FRAME_BOUNCE is lighter because it fires several times a second and only has
 * to read as weight coming down on a foot. ENTER_BOUNCE has a single hard cut
 * between two animations to soften, and gets one chance to do it.
 *
 * They live HERE, beside squashPop, rather than in main.js — wp-admin's preview
 * needs the identical numbers, and while it had its own copy it used the entry
 * weight for per-frame pops and showed every timeline value three times as
 * bouncy as the site would actually draw it.
 */
export const FRAME_BOUNCE = 0.06;
export const ENTER_BOUNCE = 0.18;

/**
 * The deepest he may ever be squeezed, as a fraction of his height.
 *
 * The sprite is drawn `scaleY = 1 - squeeze`. At 1 he collapses to a line and
 * past it he turns inside out, so this is a hard structural limit rather than a
 * matter of taste. 0.8 leaves a fifth of him, which is further than any single
 * pop can reach on its own and is only ever met by bounces piling up.
 */
export const SQUASH_MAX = 0.8;

/**
 * Add a pop to whatever give is already in him.
 *
 * Bounces STACK. A frame short enough that the last bounce has not finished is
 * not a reason to throw the new one away — two gives landing together should
 * read as one deeper give, the way they would on a real body.
 *
 * This replaced `Math.max(current, pop)`, which took the deeper of the two and
 * discarded the other. The effect was that speed stopped mattering: frames at
 * 60ms squashed him exactly as far as frames at 1000ms, because every pop after
 * the first landed on a deformation at least as deep as itself and was dropped.
 * All the rhythm somebody had typed into the timeline came out flat.
 *
 * The ceiling is applied HERE, against this bounciness's own depth, rather than
 * left to the drawing. Clamping only at paint time would let `squash` itself run
 * away while the picture sat pinned at the limit — he would arrive at maximum
 * squeeze and then stay there for as long as it took the excess to unwind, which
 * is the pancake the depth curve was rewritten to get rid of.
 */
export function squashStack(current, pop, bounce) {
    const depth = squashDepth(bounce);
    if (!(depth > 0)) return current;
    return Math.min(SQUASH_MAX / depth, current + pop);
}

/**
 * @param {number} artFlip  1 when the art is drawn facing RIGHT, -1 when it is
 *   drawn facing left. Everything downstream works in "facing" of 1 = right, so
 *   this is the one place the drawing's own orientation is accounted for.
 */
export function createView(root, scale, artFlip = 1) {
    const body = document.createElement('div');
    body.className = 'fcm-body';

    const sprite = document.createElement('div');
    sprite.className = 'fcm-sprite';
    body.appendChild(sprite);

    const particles = document.createElement('div');
    particles.className = 'fcm-particles';

    // Holds every sheet as a background so none of them is decoded for the
    // first time on the frame it is needed. See preload().
    const preloader = document.createElement('div');
    preloader.className = 'fcm-preload';
    preloader.setAttribute('aria-hidden', 'true');

    root.appendChild(body);
    root.appendChild(particles);
    root.appendChild(preloader);

    let current = null;   // the clip the sprite is currently painted from
    let cell = -1;

    return {
        root,
        el: { body, sprite, particles },
        scale,
        // The COLLISION box, which is fixed for the life of the mascot and is
        // not the size of whatever sprite happens to be playing. See setBox().
        boxW: 0,
        boxH: 0,
        bounce: 1,

        setScale(next) {
            this.scale = next;
            if (!current) return;
            // Repaint the cell he is ON, not frame 0 — a resize mid-animation
            // should change his size, not rewind what he is doing.
            const showing = cell < 0 ? 0 : cell;
            cell = -1;
            this.paint(current, showing);
        },

        /**
         * Fix the collision box, once, from the base animation.
         *
         * Everything physical — the floor, the walls, where he is — is measured
         * against THIS and never against the sprite currently on screen. A
         * 64×64 animation is simply drawn larger inside the same box.
         */
        setBox(width, height) {
            this.boxW = width;
            this.boxH = height;
            // The element itself is sized to the sprite, not to this — place()
            // is what relates the two.
            if (current) { const showing = cell < 0 ? 0 : cell; cell = -1; this.paint(current, showing); }
        },

        /** How rubbery the current animation is; scales squash and stretch. */
        setBounce(n) {
            this.bounce = Number.isFinite(n) ? n : 1;
        },

        /**
         * Rasterise every sheet as a BACKGROUND once, up front.
         *
         * decode() in sheet.js prepares each sheet as an <img>, but the sprite
         * is painted from `background-image`, and a CSS background is a
         * separate resource from the element that preloaded it — same download,
         * its own first-paint decode. That decode is what shows as a blank
         * sprite for a frame or two on an animation change.
         *
         * Stacking every URL as layered backgrounds on one hidden element makes
         * the browser prepare all of them as backgrounds now, so no animation is
         * ever the first use of its sheet in that role. It stays in the DOM
         * because dropping it would let them be evicted again.
         */
        preload(urls) {
            const unique = [...new Set(urls.filter(Boolean))];
            if (!unique.length) return;
            preloader.style.backgroundImage = unique.map((u) => `url("${u}")`).join(', ');
        },

        /** Size of a clip's cell on screen. */
        spriteSize(clip) {
            const c = clip || current;
            if (!c) return { width: 0, height: 0 };
            return { width: c.frameW * this.scale, height: c.frameH * this.scale };
        },

        /** Point the sprite at one cell. Cheap to call every frame — it early-outs. */
        paint(clip, nextCell) {
            if (!clip) return;
            if (clip === current && nextCell === cell) return;
            if (clip !== current) { current = clip; cell = -1; }
            cell = nextCell;
            paintCell(sprite, clip, nextCell, this.scale);

            // The moving element takes the SPRITE's size, so the sprite never
            // overflows it. It used to be the collision box, which for a 64×64
            // animation on a 32×32 body meant the sprite hung 48px out of every
            // side of its own compositing layer — and an overflowing child of a
            // promoted layer is rasterised at the wrong scale and resampled,
            // which is what made the big sprite look soft. place() does the
            // bottom-centre alignment instead, in the transform.
            this.spriteW = Math.round(clip.frameW * this.scale);
            this.spriteH = Math.round(clip.frameH * this.scale);
            body.style.width = `${this.spriteW}px`;
            body.style.height = `${this.spriteH}px`;
        },

        /**
         * Write the body's transform.
         *
         * Everything here is in whole pixels, and any transform that would be
         * the identity is left off entirely. That matters more than it sounds:
         * pixel art survives an integer translate untouched, but ANY rotation or
         * fractional scale resamples the whole layer bilinearly and softens
         * every edge. `rotate(0deg)` is not free — it still puts the compositor
         * on the rotation path — so it is only emitted when he is actually
         * rotated.
         */
        place(b) {
            const sw = this.spriteW || Math.round(b.width);
            const sh = this.spriteH || Math.round(b.height);

            // Bottom-centre of the sprite onto bottom-centre of the collision
            // box, so a taller animation grows upward out of the same footprint
            // — his feet stay on the floor and a flag over his head is just
            // more picture.
            const left = Math.round(b.x - sw / 2);
            const top = Math.round(b.y + b.height / 2 - sh);

            // He rotates about his physics centre, which is NOT the sprite's
            // centre once the sprite is taller than the box.
            body.style.transformOrigin = `${Math.round(sw / 2)}px ${Math.round(sh - b.height / 2)}px`;

            const spin = Math.abs(b.angle) < 0.01 ? '' : ` rotate(${b.angle.toFixed(2)}deg)`;
            body.style.transform = `translate3d(${left}px, ${top}px, 0)${spin}`;

            // Squash and stretch. `b.squash` is a spring, so it swings NEGATIVE
            // after an impact — that is the stretch on the rebound, and it is
            // what makes this read as bouncy rather than as him being sat on.
            //
            // Clamped as a last resort, not as the mechanism: squashStack()
            // already caps against the bounciness it is stacking at, and this
            // catches only the gap where the two disagree — the depth here is
            // whatever setBounce() last wrote, which for one frame after a state
            // change is the arriving animation's rather than the popping one's.
            // Beyond 1 the sprite inverts, so it is worth the two comparisons.
            const raw = b.squash * squashDepth(this.bounce);
            const amount = Math.max(-SQUASH_MAX, Math.min(SQUASH_MAX, raw));
            const face = b.facing * artFlip;
            if (Math.abs(amount) < 0.002) {
                // No squash: a plain mirror is an exact transform and costs no
                // sharpness, and no transform at all costs even less.
                sprite.style.transform = face < 0 ? 'scaleX(-1)' : '';
            } else {
                const squashY = 1 - amount;
                const squashX = 1 + amount * 0.8;   // widens as he flattens
                sprite.style.transform =
                    `scaleX(${(face * squashX).toFixed(3)}) scaleY(${squashY.toFixed(3)})`;
            }

            particles.style.transform = `translate3d(${Math.round(b.x)}px, ${Math.round(b.y)}px, 0)`;
        },

        /**
         * Mirror the current state onto the mount as `data-state`.
         *
         * Nothing in the mascot reads it — it is a hook for everything else:
         * the custom grab/grabbing cursors in inc/bootstrap.php key off
         * [data-state="held"], and it makes the live state visible in devtools
         * without instrumenting anything.
         */
        setState(key) {
            root.dataset.state = key;
        },

        setVisible(on) {
            root.style.visibility = on ? '' : 'hidden';
        },

        destroy() {
            body.remove();
            particles.remove();
            preloader.remove();
        },
    };
}
