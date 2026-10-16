/**
 * The state machine: which animation is playing, and what replaces it.
 *
 * Rules live on the STATE, never on the entry. A state may hold several named
 * entries — "fall mad", "fall sad" — and one is picked at random when the state
 * is entered and kept for the whole run of it. They are two faces of one
 * behaviour, so anything about timing or what comes next is asked of the state.
 *
 * Two kinds of state:
 *   • driven   — physics owns the exit (held ends when you let go, fall ends on
 *                impact). The loop count and `next` are both ignored.
 *   • timed    — ends once its animation has been round `loops` times, and
 *                hands over to `next`.
 *
 * A state with no art never stalls the machine. One that hands over resolves
 * straight to its `next`; one that doesn't borrows Idle's animation for as long
 * as it lasts. Between them, a mascot with ONLY Idle configured still drops in,
 * lands, walks and can be thrown — he just wears the same animation throughout.
 * Idle is therefore the single thing you have to fill in.
 */

import { Animator } from './animator.js';
import { buildClips } from './sheet.js';
import { STATE_SHAPE } from './config.js';

/**
 * States the physics enters and leaves on its own, so the clip finishing means
 * nothing. Land is one of them: it starts on the first thud and holds through
 * however many bounces follow, and only the body coming to rest ends it.
 */
const DRIVEN = new Set(['walk', 'slide', 'jump', 'fall', 'held', 'land']);

export class Machine {
    constructor(config, view, onEnter) {
        this.config = config;
        this.view = view;
        this.onEnter = onEnter || (() => {});
        this.clips = {};        // state key → clip[]
        this.state = null;
        this.clip = null;
        this.animator = null;
        this.loopsWanted = 1;   // full passes this animation gets before it is done
        this.pending = null;    // a section reaction currently overriding
        this.chain = 0;         // depth of the pass-through walk in enter()
        this.entry = null;      // the entry behind the current clip
        this.frameChanged = false;  // the drawing changed on the last update()
        this.frameAppeared = false; // …or came round again on a single-frame clip
    }

    /** Load every state's art up front. One await, then no I/O at runtime. */
    async load() {
        const keys = Object.keys(this.config.states);
        const built = await Promise.all(
            keys.map((key) => buildClips(this.config.states[key].entries))
        );
        keys.forEach((key, i) => { this.clips[key] = built[i]; });
    }

    rules(key) {
        const state = this.config.states[key];
        return (state && state.rules) || {};
    }

    /** The configured animations for a state, art aside. */
    entries(key) {
        const state = this.config.states[key];
        return (state && state.entries) || [];
    }

    has(key) {
        return !!(this.clips[key] && this.clips[key].length);
    }

    isDriven(key) {
        return DRIVEN.has(key);
    }

    /**
     * Bounciness for the frame ON SCREEN RIGHT NOW.
     *
     * Per frame rather than per animation, so a walk can give on the footfalls
     * and stay rigid through the rest of the cycle. Falls back to the
     * animation's own value, and then to 1, so an animation whose frames were
     * never given individual values behaves exactly as it did before.
     */
    get bounce() {
        const entry = this.entry;
        if (!entry) return 1;
        const per = entry.bounces;
        if (this.animator && Array.isArray(per) && per.length) {
            const v = per[this.animator.cell];
            if (Number.isFinite(v)) return v;
        }
        return Number.isFinite(entry.bounce) ? entry.bounce : 1;
    }

    /**
     * The pop on ARRIVING in this state — one moment, belonging to the animation
     * rather than to any one of its frames.
     *
     * The animation's own field, at any frame count. A single-frame animation
     * used to read its timeline box here instead, because the admin hid the
     * animation-level field for those; it does not any more, so there is no
     * special case left to make. Arriving and holding are two moments even when
     * they are drawn the same.
     */
    get entryBounce() {
        const entry = this.entry;
        if (!entry) return 1;
        return Number.isFinite(entry.bounce) ? entry.bounce : 1;
    }

    /** Where a state hands over when it finishes. Wiring, not configuration. */
    nextOf(key) {
        const shape = STATE_SHAPE[key];
        return (shape && shape.next) || '';
    }

    /**
     * Enter a state.
     *
     * `force` replays a state that is already current — a second landing should
     * restart the Land animation, but the ordinary per-frame "should be idle"
     * call must not restart Idle sixty times a second.
     */
    enter(key, force = false) {
        if (!force && this.state === key && !this.pending) return;
        if (!this.config.states[key]) return;

        // No art of its own. Two different right answers, and which one applies
        // falls out of the wiring rather than a list to keep in step:
        //
        //   • a state that hands over (Land → After-land → Idle) is TRANSPARENT.
        //     Standing in for it would mean playing an idle loop for the length
        //     of a landing, which is worse than not animating the landing.
        //   • a state that does NOT hand over (Fall, Walk, Held, Jump, Sleep)
        //     lasts until the physics says otherwise, so he has to look like
        //     something for as long as it holds. It borrows Idle.
        //
        // That is what lets a mascot with ONE configured animation work: he
        // drops in, lands and stands there, all wearing Idle.
        if (!this.has(key)) {
            const next = this.nextOf(key);
            // The guard is a chain length, not a visited set — a deliberate
            // a→b→a pair should stop, not throw.
            if (next && next !== key && this.chain < 8) {
                this.chain++;
                this.enter(next, force);
                this.chain--;
                return;
            }

            const stand = this.clips.idle;
            if (key !== 'idle' && stand && stand.length) {
                // The FIRST idle animation, not a random one: a borrowed look
                // should be stable, or he would reshuffle every time the physics
                // flicked him between walking and falling.
                const clip = stand[0];
                this.playFrom(key, clip, this.entries('idle').find((e) => e.png === clip.url) || null);
                return;
            }

            // Nothing at all to draw. Record the state anyway, so the
            // physics-driven logic still knows what he is notionally doing.
            this.state = key;
            this.pending = null;
            this.view.setState(key);
            return;
        }

        const options = this.clips[key];
        // One pick for the whole run of the state.
        const clip = options[Math.floor(Math.random() * options.length)];
        this.playFrom(key, clip, this.entries(key).find((e) => e.png === clip.url) || null);
    }

    /**
     * Enter a state playing a CHOSEN animation rather than a random pick.
     *
     * The Random pool needs this: which animation plays was already settled by
     * the per-animation chance roll, so re-rolling here would throw that away
     * and could land on one whose own chance had just failed.
     */
    playFrom(key, clip, entry) {
        const rules = this.rules(key);
        const options = this.clips[key] || [];

        this.state = key;
        this.pending = null;
        this.clip = clip;
        this.entry = entry || null;

        // How many full passes this animation gets before the state is done
        // with it. From the ANIMATION, not the state: a state can hold several
        // sheets and they are not the same length, so a single duration on the
        // state could never be a whole number of passes for all of them.
        this.loopsWanted = Math.max(1, (this.entry && this.entry.loops) || 1);

        // The animator has to be told to repeat if we want more than one pass
        // out of it — a one-shot clip stops on its last cell and never comes
        // round again. Continuous states repeat regardless; the count is what
        // decides when to move on, not the animator.
        const repeats = !!STATE_SHAPE[key].loops || this.loopsWanted > 1;
        this.animator = new Animator(clip, repeats);
        void options;
        void rules;

        this.view.setState(key);
        this.view.paint(clip, this.animator.cell);
        this.onEnter(key, clip, this.entry);
    }

    /**
     * Play a one-shot animation over the top of whatever is current — a section
     * reaction. Returns to the state underneath when its time is up.
     */
    play(entryClip, entry) {
        if (!entryClip) return;
        this.pending = { returnTo: this.state || 'idle' };
        this.clip = entryClip;
        this.entry = entry || null;
        // Counted in passes, exactly like every other animation. A reaction used
        // to carry its own duration in ms, which had all the same problems as
        // the state-level one it outlived: kept in step by hand, and able to stop
        // the animation part-way through.
        this.loopsWanted = Math.max(1, (this.entry && this.entry.loops) || 1);
        this.animator = new Animator(entryClip, true);
        this.view.paint(entryClip, this.animator.cell);
        this.onEnter(this.state, entryClip, this.entry);
    }

    /** True while a section reaction is on screen. */
    get overriding() {
        return !!this.pending;
    }

    /**
     * Cut a section reaction short. Returns the state to go back to, or null if
     * nothing was overriding.
     */
    cancelOverride() {
        if (!this.pending) return null;
        const back = this.pending.returnTo;
        this.pending = null;
        return back;
    }

    /**
     * Advance the current animation.
     * Returns the state key to move to, or null to stay put.
     */
    update(dt) {
        this.frameChanged = false;
        this.frameAppeared = false;
        if (!this.animator || !this.clip) return null;

        // Whether the drawing actually changed this frame, not merely that time
        // passed. main.js hangs the per-frame bounce off it, and a bounce that
        // fired every tick rather than every frame would just be a blur.
        const was = this.animator.cell;
        const wasLoops = this.animator.loops;
        const cell = this.animator.update(dt);
        this.frameChanged = cell !== was;

        // "This drawing has just come up", which is not quite the same question.
        //
        // A SINGLE-FRAME animation never changes cell — there is only one — so
        // `frameChanged` is false for its whole life and anything hung off it
        // fires exactly never. That is why a one-frame sheet with a bounciness
        // set on it sat perfectly still on the site while the preview, which
        // pops once per pass, bounced it. Counting a completed pass as the frame
        // appearing again makes the two agree, and makes a held pose with a
        // 1500ms frame give once every 1500ms rather than once ever.
        this.frameAppeared = this.frameChanged
            || (this.clip.count === 1 && this.animator.loops > wasLoops);

        this.view.paint(this.clip, cell);

        // A section reaction, counted in passes like everything else. It also
        // ends the moment you leave the section, which sections.js handles by
        // calling cancelOverride() — so this is the "you stayed long enough to
        // see it through" half.
        if (this.pending) {
            if (this.animator.loops >= this.loopsWanted) {
                const back = this.pending.returnTo;
                this.pending = null;
                return back;
            }
            return null;
        }

        // Counted in PASSES of the animation, never in milliseconds.
        //
        // A count can only ever end the animation where the animation ends. The
        // duration it replaced could not: any value that was not an exact
        // multiple of the timeline stopped it part-way through a frame, and it
        // went stale the moment anybody retimed one.
        //
        // What "done" means still depends on how the state ends:
        //
        //   • a state the physics owns (Fall, Held, Land, Walk…) cannot be ended
        //     by counting — he is airborne until he lands, however many passes
        //     that takes. There, finishing the count just picks another
        //     animation from the pool, so a state with several sheets cycles
        //     through them.
        //   • a one-shot (Random, After-land, the sleep transitions) hands over
        //     to its next state, which is what actually ends it.
        if (this.animator.loops >= this.loopsWanted) {
            const handsOver = !this.isDriven(this.state) && this.nextOf(this.state);
            if (handsOver) return this.nextOf(this.state);

            if ((this.clips[this.state] || []).length > 1) this.enter(this.state, true);
            else this.animator.loops = 0;      // go round again
            return null;
        }

        if (this.isDriven(this.state)) return null;       // physics decides

        // A clip with no frames to count — fall back to the animator's own idea
        // of having finished.
        if (this.animator.done) return this.nextOf(this.state) || 'idle';
        return null;
    }
}
