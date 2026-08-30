/**
 * Particles attached to an animation entry.
 *
 * Every state entry may carry one: stars orbiting a dizzy landing, z's rising
 * off a sleep, dust bursting off a jump. The particle lives exactly as long as
 * the entry that owns it is on screen, so there is no separate lifecycle to
 * manage and no way to leave one running over the wrong animation.
 *
 * Behaviour is a PRESET plus four numbers rather than a JSON file to author.
 * Five presets cover everything a mascot needs, and a dropdown you can watch in
 * the preview immediately beats a file you have to write, upload and keep in
 * sync with the sheet beside it.
 *
 *   orbit   fixed set circling him, evenly spaced         (dizzy stars)
 *   rise    a steady stream drifting up and fading        (sleepy z's)
 *   fall    the same, downward
 *   burst   a salvo fired outward in a fan, fading        (impact dust)
 *   follow  a fixed set pinned to him, no motion          (a held prop)
 *
 * Emission rate is DERIVED, not configured: `count` particles each living
 * `speedMs`, evenly spaced, so the stream is continuous by construction. It was
 * a separate `interval_ms` field, which meant three numbers that had to be kept
 * consistent by hand and looked broken whenever they weren't.
 *
 * Positions are in SPRITE pixels from his centre and multiplied by the display
 * scale, so a particle sits in the same place on his body at any size.
 */

import { Animator, paintCell } from './animator.js';
import { buildClip } from './sheet.js';

const TAU = Math.PI * 2;

export class ParticleSystem {
    constructor(layer, scale) {
        this.layer = layer;
        this.scale = scale;
        this.spec = null;
        this.clip = null;
        this.items = [];
        this.emitLeft = 0;
        this.age = 0;
        this.cache = new Map();   // png url → clip promise
    }

    setScale(scale) {
        this.scale = scale;
    }

    /** Swap to the particle of a newly entered entry (or clear it with null). */
    async use(spec) {
        this.clear();
        this.spec = spec || null;
        if (!spec) return;

        if (!this.cache.has(spec.png)) this.cache.set(spec.png, buildClip(spec));
        const clip = await this.cache.get(spec.png);
        // A slower load than the next state change: the spec has moved on, so
        // this result is stale and must not repopulate a cleared layer.
        if (!clip || this.spec !== spec) return;

        this.clip = clip;
        this.age = 0;
        this.emitLeft = 0;
        if (spec.motion === 'orbit' || spec.motion === 'follow') this.fillFixed();
    }

    clear() {
        for (const item of this.items) item.el.remove();
        this.items = [];
        this.clip = null;
        this.spec = null;
    }

    /** orbit and follow keep a constant population for the life of the state. */
    fillFixed() {
        for (let i = 0; i < this.spec.count; i++) {
            this.items.push(this.spawn(i / this.spec.count));
        }
    }

    spawn(phase) {
        const el = document.createElement('div');
        el.className = 'fcm-particle';
        const animator = new Animator(this.clip, true);
        paintCell(el, this.clip, animator.cell, this.scale);
        this.layer.appendChild(el);
        return {
            el,
            animator,
            phase,                       // 0..1, an orbit's position round the ring
            life: 0,
            angle: 0,                    // burst direction, radians
            speed: 1,
        };
    }

    /**
     * One particle for a stream, or a whole salvo for a burst.
     *
     * A burst is all at once because that is what a burst is; rise and fall emit
     * one at a time so the stream is continuous rather than arriving in visible
     * clumps.
     */
    emit() {
        const spec = this.spec;
        const many = spec.motion === 'burst' ? spec.count : 1;
        for (let i = 0; i < many; i++) {
            const item = this.spawn(0);
            if (spec.motion === 'burst') {
                // A 180° fan centred on straight up: dust and impact debris go
                // up and outward, never down through the floor he just hit.
                item.angle = -Math.PI + Math.random() * Math.PI;
                item.speed = 0.6 + Math.random() * 0.8;
            } else {
                item.angle = (Math.random() - 0.5) * 0.6;   // a little sideways wander
                item.speed = 0.7 + Math.random() * 0.6;
            }
            this.items.push(item);
        }
    }

    update(dt) {
        if (!this.spec || !this.clip) return;
        const spec = this.spec;
        const s = this.scale;
        this.age += dt;

        if (spec.motion === 'rise' || spec.motion === 'fall' || spec.motion === 'burst') {
            this.emitLeft -= dt;
            if (this.emitLeft <= 0) {
                this.emit();
                // Spacing that keeps `count` alive at once: each lives speedMs,
                // so releasing one every speedMs/count holds the population
                // steady without a second number to tune. A burst re-fires as a
                // whole salvo, one lifetime apart.
                this.emitLeft = spec.motion === 'burst'
                    ? spec.speedMs
                    : Math.max(16, spec.speedMs / spec.count);
            }
        }

        const ox = spec.offsetX * s;
        const oy = spec.offsetY * s;
        // The layer is a zero-size point at his centre, so each particle is
        // offset by half its own size to sit centred on where it is aimed.
        const halfW = (this.clip.frameW * s) / 2;
        const halfH = (this.clip.frameH * s) / 2;

        for (let i = this.items.length - 1; i >= 0; i--) {
            const item = this.items[i];
            item.life += dt;
            paintCell(item.el, this.clip, item.animator.update(dt), s);

            let x = ox;
            let y = oy;
            let opacity = 1;

            switch (spec.motion) {
                case 'orbit': {
                    const t = (this.age / spec.speedMs + item.phase) % 1;
                    x += Math.cos(t * TAU) * spec.distance * s;
                    y += Math.sin(t * TAU) * spec.distance * s * 0.55;   // a flattened ring reads as perspective
                    break;
                }
                case 'follow':
                    break;
                case 'rise':
                case 'fall': {
                    const t = Math.min(1, item.life / spec.speedMs);
                    const dir = spec.motion === 'rise' ? -1 : 1;
                    y += dir * t * spec.distance * s * item.speed;
                    x += Math.sin(t * Math.PI * 2 + item.phase) * 3 * s + item.angle * t * spec.distance * s;
                    opacity = 1 - t * t;
                    break;
                }
                case 'burst': {
                    const t = Math.min(1, item.life / spec.speedMs);
                    // Ease out: fast off the mark, slowing as it fades.
                    const reach = (1 - Math.pow(1 - t, 2)) * spec.distance * s * item.speed;
                    x += Math.cos(item.angle) * reach;
                    y += Math.sin(item.angle) * reach;
                    opacity = 1 - t;
                    break;
                }
                default:
                    break;
            }

            item.el.style.transform =
                `translate3d(${(x - halfW).toFixed(1)}px, ${(y - halfH).toFixed(1)}px, 0)`;
            item.el.style.opacity = opacity.toFixed(3);

            const mortal = spec.motion !== 'orbit' && spec.motion !== 'follow';
            if (mortal && item.life >= spec.speedMs) {
                item.el.remove();
                this.items.splice(i, 1);
            }
        }
    }
}
