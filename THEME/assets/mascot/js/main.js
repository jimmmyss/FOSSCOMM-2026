/**
 * The mascot, assembled.
 *
 * This is the only file that knows about all the others, and it is deliberately
 * the only one holding mutable cross-cutting state. Everything it does is either
 * wiring two modules together or deciding which state the body's situation
 * implies — no physics maths, no DOM writes, no sheet parsing.
 *
 * The frame:
 *   1. read input                → intent
 *   2. step physics with intent  → position, and any landing event
 *   3. decide the state that situation implies
 *   4. advance the animation, which may hand back a different state
 *   5. draw
 */

import { readConfig } from './config.js';
import { createView, squashPop, squashStack, FRAME_BOUNCE, ENTER_BOUNCE } from './view.js';
import { Machine } from './machine.js';
import { ParticleSystem } from './particles.js';
import { createInput } from './input.js';
import { createSections } from './sections.js';
import { buildClip, isSolidPixel, drawnInsets } from './sheet.js';
import * as Physics from './physics.js';
import { MODE } from './physics.js';

// FRAME_BOUNCE / ENTER_BOUNCE now live in view.js, beside squashPop, so the
// admin preview can draw with the same numbers instead of its own copy.

/**
 * Where the keys reach him.
 *
 * An animation, once started, RUNS TO THE END. Nothing you press cuts a Random
 * short, walks him out of a trip, hurries a yawn along or interrupts him getting
 * back up — the keys are ignored, not queued, until the animation is done and he
 * is his own again. Sleep is in that same category: asleep is asleep, and he
 * ignores the keys completely rather than springing up when you press a
 * direction. Scrolling wakes him; so does picking him up.
 *
 * That leaves four states where he answers to you. Three are the ordinary
 * ground ones — standing, walking, and skidding, the last so that pressing the
 * other way during a skid can turn him round, which is what Turn braking is
 * for. The fourth is mid-jump, which is not an animation waiting to finish but
 * a move already in progress: a jump you chose to make stays yours until you
 * land, and the integrator allows steering only while body.jumping.
 */
const MOVABLE = new Set(['idle', 'walk', 'slide', 'jump']);

/**
 * A third, narrower question: when is he free for something ELSE to start?
 *
 * Deliberately not MOVABLE. Being walkable through a jump does not make a jump
 * a good moment to drop a section reaction on him, or to start wandering off on
 * his own. Listing the free states rather than the busy ones means a new state
 * is busy by default, which is the safe way round — the version that named the
 * busy states missed Trip when it was added, and a flourish could hijack a fall.
 */
const FREE_STATES = new Set(['idle', 'walk', 'slide']);

export async function createMascot(root, rawConfig) {
    const config = readConfig(rawConfig);
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * The box he lives in.
     *
     * Read off the mount's own positioning context, which makes the admin
     * preview fall out for free: on the front end the mount is `position: fixed`
     * so it has no offsetParent and the frame is the viewport; inside the
     * admin's `position: relative` stage the offsetParent IS the stage, so the
     * same code confines him to it. No preview-mode flag anywhere.
     */
    /**
     * The LAYOUT viewport: the box `position: fixed; inset: 0` resolves to, and
     * the one the page is laid out in.
     *
     * clientWidth/Height, never window.inner*: the inner pair includes the
     * scrollbars, so he could stand half under the vertical bar. It is also
     * immune to pinch zoom, which window.inner* is not — reading innerWidth for
     * the size multiplier meant a pinch could cross the mobile breakpoint and
     * resize him mid-gesture.
     */
    function layoutSize() {
        const doc = document.documentElement;
        return {
            width: doc.clientWidth || window.innerWidth,
            height: doc.clientHeight || window.innerHeight,
        };
    }

    /**
     * Cache for viewportRect(), because measuring it is a LAYOUT READ and it was
     * happening on every animation frame.
     *
     * The frame loop writes a transform at the end of each tick and then read
     * clientHeight at the start of the next — the classic read-after-write. The
     * browser cannot reuse the layout it already has and recomputes the whole
     * document synchronously, every frame, forever. On a long page with sticky
     * bars and a full-page canvas that is enough to feel, and it feels worst on
     * a phone, which is where it was reported.
     *
     * Safe to hold, because nothing that changes this value fails to fire an
     * event: resizing, rotating and a toolbar sliding in all reach
     * onViewportChange(), which clears it. Scrolling does not change it — so
     * scrolling now costs the mascot no layout work at all.
     */
    let viewportCache = null;

    /**
     * What he can actually see of the page, in LAYOUT pixels.
     *
     * On a phone the layout viewport is not what is on screen. Safari lays the
     * page out against the toolbar-collapsed height and then slides the toolbars
     * over the top of it, so the layout viewport says the floor is lower than
     * the glass — which is why he stood behind the search bar, and why the floor
     * did not move when scrolling shrank it.
     *
     * visualViewport is what is genuinely visible. The catch is that its height
     * shrinks for TWO unrelated reasons — a toolbar sliding in, and a pinch
     * zoom — and only the first should move his floor. Multiplying by the scale
     * cancels the zoom exactly: at 2x the visual viewport covers half as many
     * CSS pixels, so height x scale returns the same number it had at 1x, while
     * a toolbar (which does not change the scale) still comes straight through.
     *
     * That is the whole pinch fix. He is inside a fixed layer, so the browser
     * already zooms him with the page for free; the bug was this code reading
     * the shrinking visual height as "the window got shorter" and hauling the
     * floor — and him with it — up toward the top of the screen.
     */
    function viewportRect() {
        if (viewportCache) return viewportCache;
        const layout = layoutSize();
        const vv = window.visualViewport;
        if (!vv) {
            viewportCache = { left: 0, top: 0, width: layout.width, height: layout.height };
            return viewportCache;
        }

        // The visible size is AUTHORITATIVE, and is deliberately not clamped
        // against the layout box.
        //
        // It was, briefly, to stop him sinking below the layer when zoomed out.
        // But which viewport documentElement.clientHeight reports on iOS is
        // exactly the thing this function exists to stop trusting: if it is the
        // toolbar-EXPANDED height, then clamping against it pins the floor there
        // and the toolbar collapsing can never lower it — which is the original
        // bug, reintroduced by its own guard. The clamp is kept only for the
        // zoomed-out case it was written for, where the visual viewport really
        // does extend past the layout viewport.
        //
        // `fit` guards the other end. Mid-rotation the visual viewport can report
        // a zero or a nonsense height for a frame or two, and an unguarded zero
        // puts the floor at the top of the screen — which looks exactly like him
        // launching into the sky. Anything implausible defers to the layout box
        // until the reading settles.
        const fit = (visual, layoutPx) => {
            const px = visual * vv.scale;
            if (!Number.isFinite(px) || px < layoutPx * 0.2) return layoutPx;
            return vv.scale < 1 ? Math.min(layoutPx, px) : px;
        };

        viewportCache = {
            left: 0,
            top: 0,
            width: fit(vv.width, layout.width),
            height: fit(vv.height, layout.height),
        };
        return viewportCache;
    }

    function frameRect() {
        const parent = root.offsetParent;
        if (!parent) return viewportRect();
        const r = parent.getBoundingClientRect();
        return { left: r.left, top: r.top, width: r.width, height: r.height };
    }

    const isMobile = () => layoutSize().width < config.mobileBreakpoint;

    const scaleFor = () => (isMobile() ? config.scaleMobile : config.scale);

    // Art drawn facing left is mirrored so that "facing right" really is right.
    const artFlip = config.artFacing === 'right' ? 1 : -1;
    const view = createView(root, scaleFor(), artFlip);
    const particles = new ParticleSystem(view.el.particles, view.scale);
    const machine = new Machine(config, view, onStateEntered);
    const body = Physics.createBody();

    await machine.load();
    measureBase();

    // Warm every sheet — the states' and the section reactions' — as a CSS
    // background before any of them is needed, so an animation change never
    // waits on a decode. Section art is included because a reaction is exactly
    // the case where a sheet is seen for the first time, mid-page.
    view.preload([
        ...Object.values(machine.clips).flat().map((c) => c.url),
        ...config.sections.map((row) => row.png),
    ]);

    /* ── the bits main.js owns ──────────────────────────────────────────── */

    let running = false;
    let lastTs = 0;
    let sleepTimer = 0;        // ms of stillness accumulated toward Before-sleep
    let asleep = false;
    let spawning = false;
    let wasWalking = false;    // last frame, so the END of a walk is detectable
    let landHold = 0;          // ms of Land still owed, so a short landing still shows it
    // The hop that Wake up does is in the air right now, and its landing must
    // NOT be allowed to trip him.
    //
    // Every other jump is him arriving on his own feet with speed to shed, which
    // is exactly what a trip is for. This one is him getting back UP from a trip
    // — so letting it roll makes a loop: trip, get up, hop, land, trip again, at
    // the trip chance, indefinitely.
    let recovering = false;
    // dir/stepLeft describe a wander step in progress; until counts down to the
    // next unprompted move; home is where he last landed, which bounds how far
    // the wandering may take him.
    // `turned` is spent once he has thought better of a step and gone the other
    // way, so a step can turn at one wall but not bounce between two.
    const activity = { dir: 0, stepLeft: 0, until: 0, home: 0, turned: false };

    /**
     * The animation that defines his physical size — Idle, or the first thing
     * configured if Idle has none.
     *
     * Everything physical is measured against this ONE clip and never against
     * whatever is currently playing. That is the fix for a taller animation
     * misbehaving: a 64×64 sprite used to widen and heighten the collision box,
     * which moved the floor and the walls under him — so leaving that state
     * dropped him, and standing in a corner shoved him sideways. His body is now
     * always the same size, and a bigger sprite is bigger PICTURE drawn around
     * it, aligned bottom-centre so his feet stay put.
     */
    function baseClip() {
        if (machine.has('idle')) return machine.clips.idle[0];
        for (const key of Object.keys(machine.clips)) {
            if (machine.clips[key] && machine.clips[key].length) return machine.clips[key][0];
        }
        return null;
    }

    /**
     * Fix the collision box from the base animation. Called once at boot and
     * again on resize, because the insets are in scaled pixels.
     *
     * `insets` are the transparent margin around the drawing — with them the
     * walls and floor meet his silhouette rather than the corners of his sprite
     * cell, which is what stops him hovering a few pixels off every surface.
     */
    function measureBase() {
        const clip = baseClip();
        if (!clip) return;
        const size = view.spriteSize(clip);
        if (!size.height) return;

        // Grow from the bottom edge so a rescale never lifts him off the floor.
        if (body.height && size.height !== body.height) {
            body.y += (size.height - body.height) / 2;
        }
        body.width = size.width;
        body.height = size.height;
        view.setBox(size.width, size.height);

        const inset = drawnInsets(clip);
        body.insetL = inset.left * view.scale;
        body.insetR = inset.right * view.scale;
        body.insetT = inset.top * view.scale;
        body.insetB = inset.bottom * view.scale;
    }

    function onStateEntered(key, clip, entry) {
        // NB: no re-measuring. The box belongs to the base animation.
        //
        // ARRIVING IS THE ANIMATION'S MOMENT, NOT THE FIRST FRAME'S. This used
        // to set the depth from machine.bounce — the frame the animation happens
        // to open on — while the pop below took its size from the animation's
        // own field. Two different numbers driving one movement, and the frame
        // won: set frame 1's bounciness to 0 and the arrival pop vanished
        // however high "Bounciness on entering" was, because depth 0 is no
        // deformation at all whatever the amplitude.
        //
        // The depth is set with the pop, further down, so that it belongs to the
        // movement it scales for the whole of that movement's life. Setting it
        // here would be the same mistake one step earlier: an arrival worth
        // nothing would flatten a bounce that was still in the air.

        // Wake up always leaves the ground, however it was reached — woken by a
        // scroll, or picking himself up after a trip. It lived in wake() before,
        // which meant the trip route got the animation standing perfectly still.
        //
        // `jumping` is what keeps it ONE move: it makes the landing dead rather
        // than bouncy and skips Fall and Land entirely, so the whole hop plays
        // inside this animation instead of being cut into three.
        if (key === 'after_sleep') {
            const hop = machine.rules('after_sleep').hop;
            if (hop > 0 && body.mode === MODE.GROUND) {
                body.vy = -hop;
                body.mode = MODE.AIR;
                body.onFloor = false;
                body.jumping = true;
                // …but this particular jump is a recovery, not a leap. See the
                // note on the flag: without it, getting up from a trip can trip
                // him again on the way down.
                recovering = true;
            }
        }

        // A pop of squash on the change itself, so one animation eases into the
        // next instead of hard-cutting.
        //
        // Scaled by the ANIMATION's own "Bounciness on entering", deliberately —
        // not by the per-frame value. This used to read machine.bounce, which
        // resolves per frame, so the size of the arrival pop was decided by
        // whichever frame the animation happened to open on. That is the wrong
        // number for it: entering is one moment, and it belongs to the animation
        // as a whole. The per-frame values govern the give from then on.
        // Stacked onto whatever give is already in him, like every other pop:
        // arriving in a new animation a beat after a hard landing should read as
        // one deeper give, not as the landing being overwritten. The depth is
        // written here, with the pop, and holds until he is still again.
        const pop = squashPop(ENTER_BOUNCE, machine.entryBounce);
        if (pop > 0) {
            view.setBounce(machine.entryBounce);
            body.squash = squashStack(body.squash, pop, machine.entryBounce);
        }

        particles.use(entry ? entry.particle : null);
    }

    /* ── input ──────────────────────────────────────────────────────────── */

    /**
     * Is this screen point on a drawn pixel of him?
     *
     * Undoes the render transform — translate, rotate, flip, scale — to land in
     * the sprite cell's own pixel grid, then asks the sheet. Squash is ignored:
     * it lasts a couple of frames after an impact and accounting for it would
     * make the maths harder to follow for no gain in accuracy that anyone could
     * perceive.
     */
    function hitTest(clientX, clientY) {
        const clip = machine.clip;
        if (!clip || !machine.animator) return true;

        const box = frameRect();
        const dx = (clientX - box.left) - body.x;
        const dy = (clientY - box.top) - body.y;

        const rad = (-body.angle * Math.PI) / 180;      // un-rotate
        const cos = Math.cos(rad);
        const sin = Math.sin(rad);
        let lx = dx * cos - dy * sin;
        const ly = dx * sin + dy * cos;

        if (body.facing * artFlip < 0) lx = -lx;        // un-flip

        // The sprite sits bottom-centre inside the collision box and may be
        // larger than it, so its own centre is offset from the body's.
        const sh = clip.frameH * view.scale;
        const centreOffsetY = (body.height - sh) / 2;

        const u = lx / view.scale + clip.frameW / 2;
        const v = (ly - centreOffsetY) / view.scale + clip.frameH / 2;
        return isSolidPixel(clip, machine.animator.cell, u, v);
    }

    const input = createInput(view, {
        canGrab: () => !spawning,
        frameOffset: frameRect,
        hitTest,
        onGrab(anchor) {
            // Hangs him off a string from the pointer, placed so nothing snaps.
            Physics.beginGrab(body, anchor, config.physics);
            landHold = 0;
            // Catching him mid-recovery ends the recovery. Left set, the flag
            // would survive to suppress the trip on his next REAL jump landing.
            recovering = false;
            wake(true);   // picked up: awake at once, no waking-up animation
            machine.enter('held', true);
        },
        onRelease() {
            // The bounds are what the catapult measures the overshoot against —
            // without them a release outside the window has nothing to spring
            // off and he just falls from wherever he was dropped.
            Physics.release(
                body,
                config.physics,
                Physics.worldBounds(body, config.physics.world, frameRect())
            );
            // Fall, deliberately, even when he was flung back in from off-screen:
            // "it will use the normal fall sequence of animations".
            machine.enter('fall');
        },
    });

    /* ── sleep ──────────────────────────────────────────────────────────── */

    /**
     * Wake him.
     *
     * `instant` skips the waking-up animation — that is for grabbing him, where
     * a yawn and a stretch while dangling from your cursor would be nonsense.
     * Scrolling plays the Wake up state properly; the startle hop it does comes
     * from onStateEntered, so it happens whichever route got him there.
     */
    function wake(instant) {
        sleepTimer = 0;
        // Ask the machine, not the local flag: it is the one that knows whether
        // the pass-through chain actually landed him in Sleep.
        if (machine.state !== 'sleep') return;
        asleep = false;
        machine.enter(instant ? 'idle' : 'after_sleep', true);
    }

    // Scrolling is what puts him to sleep, so it is what takes him out of it.
    //
    // The keys deliberately do NOT: asleep is asleep, and he should ignore them
    // completely rather than springing up the moment you press a direction. The
    // other way out is picking him up, which wakes him instantly via the drag
    // callbacks — no yawn while dangling from a cursor.
    window.addEventListener('scroll', () => wake(false), { passive: true });

    /* ── unprompted activity, and the Random pool ───────────────────────── */

    /** Space out the next unprompted move. */
    function scheduleActivity(walkRules) {
        const min = walkRules.gap_min_ms;
        const max = Math.max(min, walkRules.gap_max_ms);
        activity.until = min + Math.random() * (max - min);
        activity.dir = 0;
    }

    /**
     * Roll the Random pool, and play a winner.
     *
     * Each animation carries its own chance and is rolled separately, so they
     * don't have to add up to anything — one animation at 50 gives an even split
     * between wandering and doing that instead. Candidates are shuffled before
     * rolling rather than taken in order, or a run of high-chance entries at the
     * top of the list would starve everything below them.
     */
    function rollRandom() {
        if (!machine.has('random')) return false;
        const entries = machine.entries('random');
        const clips = machine.clips.random;

        const order = entries.map((_, i) => i);
        for (let i = order.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [order[i], order[j]] = [order[j], order[i]];
        }

        for (const i of order) {
            if (Math.random() * 100 >= entries[i].chance) continue;
            const clip = clips.find((c) => c.url === entries[i].png);
            if (!clip) continue;
            machine.playFrom('random', clip, entries[i]);
            return true;
        }
        return false;
    }

    /**
     * Does he catch his foot, or get away with it?
     *
     * Rolled at the two moments a stumble reads as one: the end of a walk, where
     * the Slide would otherwise begin, and the touchdown of a jump. Both are him
     * arriving somewhere on his own feet with speed to shed, which is what a trip
     * is; a throw is not, and neither is a drop — those land, and Land already
     * covers them.
     *
     * A trip does not replace the slide, it goes IN FRONT of it and hands over
     * when it finishes, so he goes over and then skids along the ground. Friction
     * runs underneath throughout, which is why there is still momentum to slide
     * on. Landing a jump on the spot trips just as happily; the slide it hands to
     * simply has nothing to carry and settles straight into Idle.
     *
     * The guards live in here rather than at the two call sites, so the second
     * moment cannot quietly drift from the first.
     */
    function rollTrip() {
        if (asleep || input.dragging || machine.overriding) return false;
        if (body.mode !== MODE.GROUND) return false;
        if (!machine.has('trip')) return false;
        if (Math.random() * 100 >= machine.rules('trip').chance) return false;
        machine.enter('trip', true);
        return machine.state === 'trip';
    }

    /**
     * How far he still travels after he stops walking.
     *
     * Friction is a constant deceleration — `stepGround` takes `walk.stop * dt`
     * off his speed every sub-step — so the skid is the schoolbook v²/2a and is
     * exact rather than a guess. This is the distance the Slide animation covers,
     * and it is the distance that decides whether a wall is already unavoidable.
     */
    function skidDistance(speed) {
        const stop = config.physics.walk.stop;
        if (!(stop > 0)) return Infinity;      // no friction: he never stops
        return (speed * speed) / (2 * stop);
    }

    /** Clear floor between him and the wall on one side. */
    function roomAhead(bounds, dir) {
        return dir > 0 ? bounds.right - body.x : body.x - bounds.left;
    }

    function stepActivity(dt, walkRules) {
        if (isBusy()) { activity.dir = 0; return; }

        const walkPhys = config.physics.walk;
        const bounds = Physics.worldBounds(body, config.physics.world, frameRect());

        // Mid-step: hold the direction until the step is walked out.
        if (activity.stepLeft > 0) {
            activity.stepLeft -= dt;
            if (activity.stepLeft <= 0) { activity.dir = 0; scheduleActivity(walkRules); return; }

            // The point of no return: from here on, letting go would still carry
            // him into the wall. Measured against his CURRENT speed, so it is the
            // real skid and not a nominal one.
            //
            // He turns round and spends the rest of the step going the other way
            // rather than grinding into the edge with the walk cycle playing. The
            // turn is not a teleport — `stepGround` brakes him through zero first,
            // which is the moment Slide covers, so it reads as him thinking better
            // of it. One turn per step: at the second wall there is nowhere left
            // to go, so he stops and waits for the next one.
            if (activity.dir !== 0
                && roomAhead(bounds, activity.dir) <= skidDistance(Math.abs(body.vx))) {
                if (!activity.turned && roomAhead(bounds, -activity.dir) > skidDistance(walkPhys.speed)) {
                    activity.dir = -activity.dir;
                    activity.turned = true;
                } else {
                    activity.dir = 0;
                    activity.stepLeft = 0;
                    scheduleActivity(walkRules);
                }
            }
            return;
        }

        activity.until -= dt;
        if (activity.until > 0) return;

        // The coin flip: take a step, or do something instead of taking one.
        scheduleActivity(walkRules);

        // A section sprite that is waiting on him goes FIRST.
        //
        // stepActivity runs ahead of tryReaction() every tick, so a Random
        // rolled on the same frame simply wins the race and the reaction queues
        // up behind it. That is one whole animation of delay each time it
        // happens — and it happens most on the FIRST visit to a section, where
        // the reaction is also waiting on its sheet to decode. Scrolling to a
        // section should show you that section's animation, not a flourish.
        //
        // Only Randoms are held. He still takes his walk step, because a
        // reaction plays straight over a walk.
        if (!queuedReaction && rollRandom()) return;

        const range = walkRules.range;
        const roomL = Math.max(0, roomAhead(bounds, -1));
        const roomR = Math.max(0, roomAhead(bounds, 1));
        let dir;

        if (body.x - activity.home > range) {
            // Turn back rather than drift away forever: a step that would take
            // him past the range from where he landed goes the other way.
            dir = -1;
        } else if (activity.home - body.x > range) {
            dir = 1;
        } else {
            // Otherwise the roomier side wins — but as a WEIGHTED coin, not a
            // verdict. A hard "always take the bigger gap" is deterministic
            // everywhere except the exact centre, so he would pace left, right,
            // left, right for ever and never wander. Weighting by the room keeps
            // him drawn to open floor at every position — near a wall it is
            // nearly certain, mid-frame it is nearly even — while leaving the
            // wander a wander.
            const total = roomL + roomR;
            dir = total <= 0
                ? (Math.random() < 0.5 ? -1 : 1)
                : (Math.random() * total < roomR ? 1 : -1);
        }

        // Whatever picked the direction, a wall overrules it. Somewhere he cannot
        // take a single step without the skid putting him into the edge is not
        // somewhere to set off towards, and that includes the leash above: home
        // can easily be a corner he was thrown into.
        const skid = skidDistance(walkPhys.speed);
        if (dir > 0 && roomR <= skid) dir = roomL > skid ? -1 : 0;
        else if (dir < 0 && roomL <= skid) dir = roomR > skid ? 1 : 0;

        if (dir === 0) return;      // boxed in: stand still and try again later

        activity.dir = dir;
        activity.turned = false;
        activity.stepLeft = 260 + Math.random() * 420;
    }

    /* ── section reactions ──────────────────────────────────────────────── */

    const reactionClips = new Map();
    let queuedReaction = null;    // waiting for him to be free
    let activeRow = null;         // the section he is in right now
    let loadingReaction = false;  // a queued reaction's art is on its way

    /**
     * States that own him until they are done. A section reaction must never cut
     * across one — that is what put the wrong sprite on him mid-air.
     *
     * Idle and Walk are the quiet ones: he is on the ground doing nothing in
     * particular, and interrupting them costs nothing.
     */
    function isBusy() {
        if (asleep || spawning || input.dragging) return true;
        if (body.mode !== MODE.GROUND) return true;      // airborne: land first
        if (machine.overriding) return true;             // a section reaction is on screen
        // Anything outside FREE_STATES owns him: Trip, Random, the landing pair,
        // the sleep transitions. Listing the free states rather than the busy
        // ones is deliberate — a new state is busy by default, which is the safe
        // way round. The version of this that named the busy states missed Trip
        // when it was added, and a flourish could hijack a fall mid-tumble.
        return !FREE_STATES.has(machine.state);
    }

    /** Play a queued reaction the moment he is free to take it. */
    async function tryReaction() {
        if (!queuedReaction || loadingReaction || isBusy() || machine.overriding) return;
        const row = queuedReaction;

        if (!reactionClips.has(row)) {
            // The art is fetched once, and the QUEUE SLOT IS HELD for the whole
            // fetch. Clearing it up front lost the reaction outright: the await
            // hands the frame back, something else can start in that gap, and
            // the re-check below then finds him busy with nothing left to put
            // back. First visit to a section, every time.
            loadingReaction = true;
            try {
                reactionClips.set(row, await buildClip(row));
            } finally {
                loadingReaction = false;
            }
        }

        // He may have moved on while it loaded, in either sense: scrolled out
        // of the section, or started doing something. Only the first is fatal.
        const clip = reactionClips.get(row);
        if (!clip || activeRow !== row) {
            // …and only clear the slot if it is still OURS. A different section
            // may have claimed it during the await.
            if (queuedReaction === row) queuedReaction = null;
            return;
        }
        // Busy again: leave it queued and let the next frame try. Dropping it
        // here is what used to make a section sprite vanish if a Random
        // happened to start while its sheet was decoding.
        if (isBusy() || machine.overriding) return;

        queuedReaction = null;
        machine.play(clip, row);
    }

    const sections = createSections(
        config.sections,
        (row) => {
            // Queued rather than played. If he is mid-fall, mid-landing or
            // mid-anything, it waits for him — "the animation has to land first
            // and finish" — instead of replacing whatever he is doing.
            activeRow = row;
            queuedReaction = row;
            tryReaction();
        },
        () => {
            activeRow = null;
            queuedReaction = null;
            const back = machine.cancelOverride();
            if (back) machine.enter(back, true);
        },
    );

    /* ── the state his situation implies ────────────────────────────────── */

    function impliedState() {
        if (input.dragging) return 'held';
        if (body.mode === MODE.AIR) {
            // A jump is ONE animation for the whole hop — up, over and down.
            // Not Fall on the way down and not Land on arrival: a jump you chose
            // to make is a single move, and cutting it into three read as the
            // animation glitching mid-air. Fall is for flights you did not
            // choose: the drop onto the page, and anything thrown.
            if (body.jumping) return 'jump';
            // …and only once he is moving fast enough for it to look like
            // falling. Lift him a few pixels and let go and he just drops; the
            // alternative is a full panic-plummet for a one-inch set-down.
            const speed = Math.hypot(body.vx, body.vy);
            if (speed >= machine.rules('fall').min_speed) return 'fall';
            return machine.state === 'fall' ? 'fall' : (asleep ? 'sleep' : 'idle');
        }
        if (asleep) return 'sleep';

        // Skidding: still carrying speed the way he was going, with nothing
        // pushing him that way any more — either you let go, or you turned round
        // and his momentum has not caught up yet. It ends by itself the moment
        // he is still, or moving the way he is now being pushed.
        const drift = body.vx;
        const pushing = input.intent.walkDir;
        if (Math.abs(drift) > machine.rules('slide').min_speed
            && (pushing === 0 || Math.sign(drift) !== pushing)) {
            return 'slide';
        }

        // Walking means being PUSHED. Coasting with nothing pressed is a slide
        // above the threshold and standing still below it — never a walk. This
        // used to also accept any speed over 4px/s, which meant the last scrap
        // of a coast, between that and the slide threshold, came out as a couple
        // of frames of walking with nobody walking him.
        if (pushing !== 0) return 'walk';
        return 'idle';
    }

    /* ── the frame ──────────────────────────────────────────────────────── */

    /**
     * NB: called `tick`, not `frame`. It used to be `frame`, and it also declared
     * `const frame = frameRect()` further down — which hoists a block-scoped
     * binding over the whole function body and put the `requestAnimationFrame`
     * on the first line into the temporal dead zone. The very first tick threw
     * "Cannot access 'frame' before initialization", the loop died, and he sat
     * above the top of the screen where he had spawned, never falling in.
     * Syntactically fine, so neither the parser nor a lint caught it.
     */
    function tick(ts) {
        if (!running) return;
        requestAnimationFrame(tick);

        const dt = lastTs ? Math.min(100, ts - lastTs) : 16;
        lastTs = ts;

        const walkRules = machine.rules('walk');
        stepActivity(dt, walkRules);

        const box = frameRect();
        const intent = input.read(activity.dir);

        // Ignored, never queued — that is the whole rule. Holding a direction
        // through an animation must not fire him sideways the moment it ends,
        // and an animation you can walk out of halfway through never really
        // played. Sleep falls out of this for free: it is not in MOVABLE, so a
        // sleeping mascot simply does not hear you.
        //
        // `overriding` is a section reaction, which is an animation like any
        // other and is checked separately because it plays OVER whatever state
        // he was in — the state underneath may well be Idle, which is movable.
        if (machine.overriding || !MOVABLE.has(machine.state)) {
            intent.walkDir = 0;
            intent.jump = false;
        }

        // Jumping is a GROUND move, always — no double jump, and no relaunching
        // out of a fall or a throw. Steering an existing jump is separate and
        // lives in the integrator, which allows it only while body.jumping.
        if (body.mode !== MODE.GROUND) intent.jump = false;

        const walkingNow = intent.walkDir !== 0 && body.mode === MODE.GROUND;
        const events = Physics.step(body, dt, config.physics, intent, box);
        input.consumeJump();

        // The moment a walk ends — yours or his own — is when he either drifts
        // to a stop or goes over. Rolled here so the trip REPLACES the slide
        // rather than interrupting it a moment later.
        if (wasWalking && !walkingNow) rollTrip();
        wasWalking = walkingNow;

        // The first thud starts Land, which then HOLDS through however many
        // bounces follow. Not forced, so the second and third bounce find him
        // already in Land and leave it running rather than restarting it.
        if (events.touched) {
            spawning = false;
            // Where he came to rest is the centre his wandering is measured
            // from, and the countdown to his first unprompted move starts here
            // rather than on load — otherwise it would elapse during the drop
            // and fire the instant he touched down.
            activity.home = body.x;
            scheduleActivity(walkRules);
            // Sticking the landing, or not. A jump is him arriving on his own
            // feet with speed to shed, exactly as the end of a walk is, so it
            // gets the same roll — and it is rolled BEFORE the Land branch below
            // because a jump never reaches that branch anyway.
            //
            // Except the hop Wake up does, which is him getting back UP from a
            // trip. Rolling that one closes a loop — trip, get up, hop, land,
            // trip — that runs at the trip chance until it happens to miss.
            if (events.fromJump && !recovering) rollTrip();
            recovering = false;

            // Two reasons to skip the landing animation. A jump is self-contained
            // and ends by simply going back to Idle; and a gentle set-down is not
            // worth animating at all, or he stutters every time he steps off a
            // one-pixel ledge.
            if (!events.fromJump
                && events.impact >= machine.rules('land').min_impact
                && machine.state !== 'land') {
                machine.enter('land');
                // Owed time, so Land is always SEEN. A jump touches down and
                // comes to rest in the same frame, and without this the whole
                // landing would be entered and replaced between two paints.
                if (machine.state === 'land') {
                    landHold = machine.animator ? machine.animator.durationMs : 0;
                }
            }
        }

        // After-land follows once he has both stopped moving AND given Land a
        // full pass — whichever of the two takes longer.
        if (machine.state === 'land') {
            landHold = Math.max(0, landHold - dt);
            if (body.mode === MODE.GROUND && landHold <= 0) machine.enter('after_land', true);
        } else if (!events.touched && !machine.overriding) {
            const implied = impliedState();
            const current = machine.state;
            // The one-shots — random, land, after_land, before_sleep,
            // after_sleep — must be allowed to finish. Only something the
            // physics forced cuts them short, which is handled above and by the
            // drag callbacks.
            const interruptible = current === null || current === 'idle' || current === 'walk'
                || current === 'slide' || current === 'fall' || current === 'jump'
                || current === 'held' || current === 'sleep';

            if (interruptible && implied !== current) machine.enter(implied);
        }

        // THE DEPTH BELONGS TO THE POP THAT IS STILL IN FLIGHT, not to whatever
        // frame happens to be on screen while it plays out.
        //
        // `bounce` scales a squash at DRAW time, so whatever it says when the
        // frame is painted decides how deep the deformation looks — including a
        // deformation created some other moment entirely. Handing it to each new
        // frame as it came up therefore reached back and rewrote a bounce that
        // was already happening, and `squashDepth(0)` is exactly zero, so a
        // single frame with its bounciness at 0 did not merely fail to add a
        // give: it ERASED the one in progress, mid-air.
        //
        // A 50ms nine-frame sheet with the arrival set to 2 and the frames left
        // at 0 showed the arrival for 42ms and then nothing — measured, and it
        // is what "it cuts off, it is way too fast" was describing. The arrival
        // is worth 480ms of movement; it was being shown for one frame of it.
        //
        // So the depth is only ever written by a pop, below, and only reverts to
        // the current frame once he is completely still — which is the one
        // moment nothing can be spoiled, and the moment before an outside squash
        // (a landing) arrives wanting the current animation's springiness.
        if (body.squash === 0 && !body.squashVel) view.setBounce(machine.bounce);

        // EVERY frame gives by its own Bounciness as it comes up — not just the
        // walk's.
        //
        // This used to be `machine.state === 'walk'` and nothing else, so on any
        // other animation a per-frame value could only scale the DEPTH of a
        // squash something else had caused; it never caused one. Set "Bounciness
        // on entering" to 0 and there was nothing to scale, so the whole
        // timeline did nothing whatsoever — while the preview, which pops on
        // every frame change, showed it working perfectly.
        //
        // Hung off the frame coming UP rather than off a timer, so the bounce
        // stays locked to the drawing however the animation is retimed.
        //
        // Two weights, and the split is what makes this safe to generalise. The
        // timeline boxes are SEEDED from the animation's own Bounciness, so
        // every animation ever saved already carries a full set of values — if
        // they all popped at the arrival weight, every idle in the theme would
        // start juddering. At the default of 1, FRAME_BOUNCE gives 0.06 against
        // a depth of 0.35: a two-percent give, the texture the walk has always
        // had. Only a frame somebody deliberately raised reads as a bounce.
        //
        // One weight, at any frame count. A one-frame animation used to pop at
        // the heavier ARRIVAL weight on each pass, because the entry field was
        // hidden for those and its timeline box was the only number it had. The
        // field is back for every animation now, so a frame is a frame — it pops
        // at the per-frame weight, and arriving is the arrival's business.
        //
        // …and they STACK. A frame too short for the last give to have finished
        // is not a reason to drop the next one: they add, and a fast run of
        // frames digs deeper than a slow one. See squashStack for the ceiling.
        if (machine.frameAppeared) {
            const pop = squashPop(FRAME_BOUNCE, machine.bounce);
            // The depth comes with the pop. A frame worth nothing leaves both
            // alone rather than flattening what is already in flight.
            if (pop > 0) {
                view.setBounce(machine.bounce);
                body.squash = squashStack(body.squash, pop, machine.bounce);
            }
        }

        const next = machine.update(dt);
        // Wake up covers the whole startle hop. Handing over mid-air would show
        // him idling at the top of his own jump; if the animation runs out first
        // it simply holds its last frame until he is back down.
        const airborneWake = machine.state === 'after_sleep' && body.mode !== MODE.GROUND;
        if (next && !airborneWake) machine.enter(next, true);

        // Read the flag back off the machine rather than setting it alongside
        // the transition. A state with no art is passed straight through, so
        // asking for Before-sleep can land him in Sleep in one call — setting
        // the flag only on an explicit `next === 'sleep'` left him asleep in the
        // machine but awake here, and the two fought every frame.
        asleep = machine.state === 'sleep';

        // Idle long enough and he nods off. Only from a settled Idle, so he
        // can't fall asleep mid-throw or while you are holding him.
        const sleepAfter = machine.rules('idle').sleep_after_ms;
        if (sleepAfter > 0 && !asleep && machine.state === 'idle' && body.mode === MODE.GROUND) {
            sleepTimer += dt;
            if (sleepTimer >= sleepAfter) {
                sleepTimer = 0;
                if (machine.has('before_sleep')) {
                    machine.enter('before_sleep', true);
                } else {
                    asleep = true;
                    machine.enter('sleep', true);
                }
            }
        }

        // A reaction that arrived while he was busy waits here for him to be
        // free — landed, settled, and back to just standing about.
        if (queuedReaction) tryReaction();

        particles.update(dt);
        view.place(body);
    }

    /* ── spawn ──────────────────────────────────────────────────────────── */

    function spawn() {
        const world = config.physics.world;
        const box = frameRect();
        const size = view.spriteSize(machine.clip);
        body.width = size.width || body.width;
        body.height = size.height || body.height;

        body.x = (box.width * world.spawn_x) / 100;
        body.vx = 0;
        body.vy = 0;
        body.spin = 0;
        body.angle = 0;
        body.squash = 0;
        body.jumping = false;
        // He starts the arrival drop above the top of the window, so the ceiling
        // must not exist until he is under it.
        body.enclosed = false;
        landHold = 0;
        activity.home = body.x;
        activity.dir = 0;
        activity.stepLeft = 0;
        scheduleActivity(machine.rules('walk'));
        asleep = false;
        sleepTimer = 0;

        if (reduced) {
            // No drop: appear where he would have landed. The whole point of
            // reduced motion is not to animate large positional changes.
            body.y = Physics.worldBounds(body, world, box).floor;
            body.mode = MODE.GROUND;
            spawning = false;
            machine.enter('idle', true);
        } else {
            body.y = -world.spawn_height;
            body.mode = MODE.AIR;
            spawning = true;
            machine.enter('fall', true);
        }
        Physics.clampInside(body, world, frameRect());
        view.place(body);
    }

    /* ── lifecycle ──────────────────────────────────────────────────────── */

    function onViewportChange() {
        // Drop the cached measurement FIRST — everything below re-measures
        // through it, and a stale read here would be baked in until the next
        // resize.
        viewportCache = null;
        view.setScale(scaleFor());
        particles.setScale(view.scale);
        // Re-measure rather than just resizing: the insets are in scaled pixels.
        measureBase();

        const box = frameRect();

        // On a phone the bottom edge moves ON ITS OWN. Safari's toolbar slides
        // away as you scroll down and slides back as you scroll up, and each
        // slide resizes the visual viewport without a single window resize — so
        // the floor drops sixty-odd pixels out from under a mascot who is
        // standing perfectly still.
        //
        // The ordinary rule then takes over and does the wrong thing: collide()
        // sees GROUND with no ground beneath it, puts him in AIR, and he falls.
        // Fall, Land, After-land, every time the toolbar so much as twitches —
        // which is the flying.
        //
        // Standing on the floor is therefore STICKY on mobile. The floor moves,
        // he moves with it, and nothing about his state changes: no fall, no
        // landing, no interruption to whatever he was doing. Only a body already
        // resting on it is carried; mid-air he keeps falling, and to the NEW
        // floor, which is what you want anyway.
        //
        // Desktop is left exactly as it was — dragging a window taller and
        // watching him drop to the new floor is deliberate there, and a desktop
        // viewport never resizes unless somebody resized it.
        if (isMobile() && body.mode === MODE.GROUND) {
            body.y = Physics.worldBounds(body, config.physics.world, box).floor;
            body.onFloor = true;
        }

        Physics.clampInside(body, config.physics.world, box);
    }

    window.addEventListener('resize', onViewportChange, { passive: true });

    // A phone's toolbars sliding in and out as you scroll resize the VISUAL
    // viewport and nothing else — window.resize never fires. Without this the
    // floor sat wherever it was last put and he stood behind the search bar
    // until something else happened to resize the window.
    //
    // Pinch zoom fires this too, which is harmless: everything it recomputes is
    // derived from the layout viewport or from viewportRect(), and both of those
    // divide the zoom back out, so a pinch lands on the same numbers it started
    // with and he simply zooms with the page.
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', onViewportChange, { passive: true });
    }

    // Nothing to animate while the tab is hidden, and resuming from a long gap
    // is what the physics catch-up cap exists to survive.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { running = false; }
        else if (!running) { running = true; lastTs = 0; requestAnimationFrame(tick); }
    });

    const api = {
        config,
        body,
        machine,
        view,
        /** Drop him in again from the top — the admin preview's Spawn button. */
        respawn() { spawn(); },
        start() {
            if (running) return;
            running = true;
            lastTs = 0;
            requestAnimationFrame(tick);
        },
        stop() { running = false; },
        destroy() {
            running = false;
            window.removeEventListener('resize', onViewportChange);
            if (window.visualViewport) {
                window.visualViewport.removeEventListener('resize', onViewportChange);
            }
            input.destroy();
            sections.destroy();
            particles.clear();
            view.destroy();
        },
    };

    const delay = reduced ? 0 : config.physics.world.spawn_delay;
    setTimeout(() => { spawn(); api.start(); }, delay);

    return api;
}

