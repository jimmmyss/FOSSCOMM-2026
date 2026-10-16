/**
 * The whole physical model: one body, four walls, a fixed timestep.
 *
 * No library. A rigid-body engine (Matter, Planck) is 90–200KB of broad-phase
 * collision detection between many arbitrary shapes, and there is exactly one
 * body here hitting four axis-aligned lines. What actually matters is FEEL —
 * how far he lags behind the cursor, how a flick turns into a spin, how dead the
 * bounce is — and that is a dozen tunable numbers you want to read directly, not
 * a solver you tune around.
 *
 * Coordinates: `x`/`y` are the CENTRE of the sprite in viewport pixels, which is
 * also the point it rotates about. Feet are `y + height / 2`.
 *
 * The step is fixed at 1/120s and accumulated. Variable-dt integration makes the
 * bounce height depend on the frame rate — the same throw lands differently on a
 * 60Hz and a 144Hz screen, and a dropped frame can put him through the floor.
 */

const STEP_MS = 1000 / 120;
const MAX_CATCHUP_MS = 250;   // a backgrounded tab resumes, it does not fast-forward

export const MODE = {
    GROUND: 'ground',   // stood on the floor, walkable
    AIR: 'air',         // ballistic — thrown, jumped, or dropped in
    HELD: 'held',       // hanging off the pointer
};

export function createBody() {
    return {
        x: 0, y: 0,
        vx: 0, vy: 0,
        angle: 0,          // degrees, 0 = upright
        spin: 0,           // degrees per second
        width: 0, height: 0,
        facing: 1,         // 1 right, -1 left — art is drawn facing right
        mode: MODE.AIR,
        squash: 0,         // spring displacement: + is flattened, − is stretched
        squashVel: 0,      // …and its velocity, so it can overshoot and wobble
        landedImpact: 0,   // |vy| at the last floor contact, for the Land rules
        onFloor: false,
        jumping: false,    // this flight began with a jump, not a throw or a drop
        touchedFloor: false,   // set for one step on contact; step() reads and clears
        landedFromJump: false, // the landing that just happened ended a jump

        // ── pendulum, while held ────────────────────────────────────────────
        rod: 0,            // px from the pivot to him — the string
        phi: 0,            // rad from straight down, unwrapped so turns accumulate
        gripAngle: 0,      // rad: where the grip sits on HIM, so an off-centre hold rotates him
        enclosed: false,   // he has been inside the frame, so the ceiling now applies
        prevX: 0, prevY: 0,           // Verlet: last position IS the velocity
        pivotOffX: 0, pivotOffY: 0,   // pivot relative to the pointer, fixed at grab
        pivotPrevX: 0, pivotPrevY: 0, // last frame's pivot, to sweep from

        // ── righting himself on landing ─────────────────────────────────────
        unwinding: false,  // mid-landing: the spin is scripted, not free
        hopsLeft: 0,       // bounces still to come, to spread the rotation over
        accum: 0,          // fixed-timestep leftover
    };
}

/**
 * Start a hold: he is pinned wherever you actually clicked, and the string is
 * however far that point is from his centre of mass.
 *
 * This is the answer to "can I just grab it anywhere and have it spin
 * accordingly" — yes, and it is both more realistic and simpler than a fixed
 * hold point. A rigid body pinned at a point swings about that pin with a lever
 * arm equal to the distance to its centre of mass, so grabbing him by an
 * extremity gives a long arm and a big lazy swing, and grabbing him near the
 * middle gives almost none. Holding something exactly at its centre of mass and
 * having it not swing at all is correct, not a bug.
 *
 * It also removes the snap and the floating cursor: the pin is the cursor, and
 * he is already hanging correctly from it the instant you press, whatever angle
 * he was at and wherever on him you grabbed.
 *
 * `gripAngle` is the bit that makes an off-centre grab behave. It is where the
 * centre of mass sits relative to the grip in HIS OWN frame, so his drawn tilt
 * is the string's angle minus that. Grab him by the shoulder and he rotates
 * until that shoulder is uppermost, exactly as a held object does.
 */
export function beginGrab(body, anchor, tuning) {
    // World-space arm from the grip to his centre of mass.
    let wx = body.x - anchor.x;
    let wy = body.y - anchor.y;
    let rod = Math.hypot(wx, wy);

    // Grabbed at (or within a hair of) his centre: there is no lever arm and
    // therefore nothing to swing on, and the constraint has no direction to
    // work with. Give it the shortest arm that still behaves.
    const minRod = Math.max(4, tuning.grab.min_rod * (body.height || 32));
    if (rod < minRod) {
        if (rod < 1e-3) { wx = 0; wy = minRod; }
        else { const k = minRod / rod; wx *= k; wy *= k; }
        rod = minRod;
    }

    const phi = Math.atan2(wx, wy);
    body.rod = rod;
    body.phi = phi;
    // theta = gripAngle - phi, so gripAngle = theta + phi. Solved here once, it
    // keeps his current tilt exactly and lets an off-centre grip swing him.
    body.gripAngle = (body.angle * Math.PI) / 180 + phi;

    // The pivot is the pointer, offset only by however much the arm had to be
    // lengthened above — which is zero for any grab outside his middle.
    body.pivotOffX = (body.x - wx) - anchor.x;
    body.pivotOffY = (body.y - wy) - anchor.y;

    // Verlet carries velocity in the gap between these two, so equal means he
    // starts from rest in your hand rather than continuing whatever he was doing.
    body.prevX = body.x;
    body.prevY = body.y;
    body.pivotPrevX = anchor.x + body.pivotOffX;
    body.pivotPrevY = anchor.y + body.pivotOffY;
    body.mode = MODE.HELD;
    body.jumping = false;
    body.unwinding = false;
    body.spin = 0;
}

/**
 * The box he lives in, in FRAME pixels.
 *
 * `frame` is the size of his positioning context — the viewport on the front
 * end, the stage box in the admin preview. Everything about him is expressed in
 * that space rather than the viewport's, which is the whole reason the same code
 * runs in both places: the preview is not a special case, it is a smaller frame.
 *
 * Derived on demand rather than cached, because it depends on both the frame
 * size and his own size, and both change (a resize, and a state whose frames are
 * a different size to the last one).
 */
export function worldBounds(body, world, frame) {
    // Measured to his DRAWING, not to his sprite cell — the transparent margin
    // around the art is subtracted, so touching the left edge means his arm
    // touches it rather than the invisible corner of a 32px box.
    //
    // There is deliberately no left/right gap setting. The edge of the window is
    // the edge of his world; a configurable inset there was only ever a way to
    // accidentally leave a border in.
    const halfW = body.width / 2 - (body.insetL || 0);
    const halfWR = body.width / 2 - (body.insetR || 0);
    const halfH = body.height / 2;
    return {
        left: halfW,
        right: Math.max(halfW, frame.width - halfWR),
        top: world.ceiling + halfH - (body.insetT || 0),
        floor: frame.height - world.floor - halfH + (body.insetB || 0),
    };
}

/** Decay helper — frame-rate independent exponential falloff. */
function damp(value, perSecond, dt) {
    return value * Math.exp(-perSecond * dt);
}

/** Squash spring: stiffness, and damping below critical so it overshoots. */
const SQUASH_K = 120;
const SQUASH_C = 12;

/**
 * How long those constants take to settle — measured against the stillness test
 * at the bottom of stepSquash, not estimated. 940ms, converging from below as
 * the timestep shrinks.
 *
 * It is the REFERENCE for the Bounce length setting, not a limit: the setting
 * scales the spring away from here in either direction, and asking for 940
 * leaves it exactly as it was.
 */
export const SQUASH_MS = 940;

/**
 * dt × ω the integrator is allowed to take in one bite.
 *
 * Semi-implicit Euler bleeds energy in proportion to this, and the bleed comes
 * out of the OVERSHOOT — which is the entire point of using a spring. At the
 * physics sub-step of 8.33ms a bounce retimed down to 100ms rebounded 0.8% past
 * neutral instead of 12.6%: not a fast bounce, just a soft squash with the
 * bounce integrated out of it. 0.05 keeps the rebound within a fraction of a
 * percent across the whole range the setting offers.
 */
const SQUASH_MAX_STEP = 0.05;

/**
 * Settle a squash back to nothing, as a damped spring.
 *
 * A spring rather than a decay because a decay only ever goes one way: he
 * flattens, then un-flattens, and stops. A spring overshoots — flatten, rebound
 * PAST neutral into a stretch, wobble, settle — which is the whole shape of a
 * bounce. Damping is deliberately below critical (12 against 2√120 ≈ 21.9) so
 * there is a visible overshoot rather than a soft landing.
 *
 * `ms` retimes the whole thing without changing its shape.
 *
 * Both constants have to move together, and by different powers. A damped
 * spring's CHARACTER is its damping ratio, C / 2√K — that is what decides how
 * far it overshoots and how many times it wobbles. Substituting y(t) = x(t/a)
 * into ÿ = −Ky − Cẏ gives K → K/a², C → C/a, and those leave the ratio exactly
 * where it was: 0.548, every setting. Scale K alone and you have not made the
 * bounce longer, you have made it a different bounce — softer, with the
 * overshoot damped out of it.
 *
 * Takes anything with `squash` and `squashVel`, so wp-admin's preview can run
 * the identical spring rather than approximating it.
 */
export function stepSquash(s, dt, ms) {
    if (s.squash === 0 && !s.squashVel) return;

    const a = (Number.isFinite(ms) && ms > 0) ? ms / SQUASH_MS : 1;
    const k = a === 1 ? SQUASH_K : SQUASH_K / (a * a);
    const c = a === 1 ? SQUASH_C : SQUASH_C / a;

    // Sub-stepped, because a shorter bounce is a STIFFER spring and the caller's
    // dt is fixed. Without this the setting stopped meaning what it says at the
    // fast end — see SQUASH_MAX_STEP. n is 2 at the default and about 18 at the
    // shortest setting, which is a handful of multiply-adds either way.
    const n = Math.max(1, Math.ceil((dt * Math.sqrt(k)) / SQUASH_MAX_STEP));
    const h = dt / n;
    let vel = s.squashVel || 0;
    let pos = s.squash;
    for (let i = 0; i < n; i++) {
        vel += (-k * pos - c * vel) * h;
        pos += vel * h;
    }
    s.squashVel = vel;
    s.squash = pos;

    // The "close enough to nothing" test has to be retimed as well. Position is
    // position at any speed, but VELOCITY scales by 1/a — a spring stretched to
    // twice the length moves at half the speed throughout — so a fixed velocity
    // cutoff would call a slow bounce finished part-way through its last wobble
    // and snap it to zero. That is a visible clip, and it is exactly the tail
    // somebody lengthening the bounce is asking to see.
    if (Math.abs(s.squash) < 0.002 && Math.abs(s.squashVel) < 0.02 / a) {
        s.squash = 0;
        s.squashVel = 0;
    }
}

/**
 * One fixed sub-step.
 *
 * `intent` is what the rest of the app wants this frame:
 *   walkDir   -1 | 0 | 1     arrow keys or a wander step
 *   walkSpeed px/s           which speed that direction should reach
 *   jump      bool           one-shot, consumed here
 *   anchor    {x,y,vx,vy}    the pointer, while held
 */
function integrate(body, dt, tuning, intent, frame) {
    const { grab, throw: air, walk, world } = tuning;
    const bounds = worldBounds(body, world, frame);

    // Squash settles FIRST, before any mode can return early. It used to live at
    // the bottom, past the `held` branch's return — so picking him up froze
    // whatever squash he had, and the pop from entering Held stayed pressed into
    // him for as long as you held him.
    stepSquash(body, dt, tuning.squash && tuning.squash.ms);

    if (body.mode === MODE.HELD && intent.anchor) {
        stepHeld(body, dt, grab, intent.anchor);
        // Held is the one mode with no collision: he can be dragged anywhere,
        // including off the top of the window, and is only clamped on release.
        return;
    }

    if (body.mode === MODE.GROUND) {
        stepGround(body, dt, walk, intent);
    } else {
        stepAir(body, dt, air, walk, intent);
    }

    collide(body, dt, bounds, air);
}

/**
 * px/s of sideways speed a release needs before it counts as a THROW and turns
 * him to face that way. Below it he was set down, not thrown.
 */
const RELEASE_TURN_SPEED = 60;

/** Speed ceiling on the bob while held, so one huge pointer jump can't explode it. */
const MAX_HELD_SPEED = 12000;

/** Fold degrees into (-180, 180] — the short way round to upright. */
export function normalizeDeg(a) {
    let n = a % 360;
    if (n > 180) n -= 360;
    if (n <= -180) n += 360;
    return n;
}

/**
 * Held: a mass on a rigid string, solved by position.
 *
 * The previous version integrated the textbook pendulum equation driven by the
 * pivot's ACCELERATION. That is correct on paper and wrong in practice, for one
 * reason: there is no such thing as pointer acceleration. It has to be
 * differenced from a sampled, already-smoothed velocity, and differentiating
 * noise amplifies it — so every jitter became a torque spike. Worse, the
 * `(g - Ay)` term means moving the pivot UP and DOWN modulates the effective
 * gravity, which is textbook parametric excitation: shaking him vertically fed
 * energy straight into the swing and span him on the spot. Both symptoms were
 * the model, not the tuning.
 *
 * This is position-based dynamics instead — the same approach a cloth or rope
 * solver uses:
 *
 *   1. move him by his own inertia (Verlet: velocity is implied by where he was)
 *   2. apply gravity
 *   3. project him back onto the circle of radius L around the pivot
 *
 * Nothing differentiates the pointer at all, so pointer noise cannot become
 * force. The constraint in step 3 is what transfers the pivot's motion into him:
 * drag the pivot and he trails because his inertia leaves him behind; whip it in
 * a circle and the projection keeps hauling him round, so he goes over the top
 * and keeps going. Moving the pivot straight up pulls along the string, which is
 * exactly the direction that produces no rotation — so lifting him no longer
 * spins him.
 */
function stepHeld(body, dt, grab, anchor) {
    const px = anchor.x + body.pivotOffX;
    const py = anchor.y + body.pivotOffY;

    // 1 + 2. Verlet step. `damping` bleeds the swing out over time.
    const keep = Math.exp(-grab.damping * dt);
    let vx = (body.x - body.prevX) * keep;
    let vy = (body.y - body.prevY) * keep;

    // A single enormous pointer jump (a dropped frame, a tab regaining focus)
    // would otherwise be read as enormous inertia on the very next step.
    const carried = Math.hypot(vx, vy) / dt;
    if (carried > MAX_HELD_SPEED) {
        const k = (MAX_HELD_SPEED * dt) / Math.hypot(vx, vy);
        vx *= k; vy *= k;
    }

    body.prevX = body.x;
    body.prevY = body.y;
    body.x += vx;
    body.y += vy + grab.gravity * dt * dt;

    // 3. The string. One projection, and it is the whole of the coupling.
    let dx = body.x - px;
    let dy = body.y - py;
    let d = Math.hypot(dx, dy);
    if (d < 1e-6) { dx = 0; dy = 1; d = 1; }   // degenerate: hang him straight down
    body.x = px + (dx / d) * body.rod;
    body.y = py + (dy / d) * body.rod;

    // Read the rotation off the string, so his angle and his position are the
    // same fact and can never disagree. Unwrapped, so a full turn keeps counting
    // rather than snapping between +180 and -180.
    const phi = Math.atan2(body.x - px, body.y - py);
    let delta = phi - body.phi;
    while (delta > Math.PI) delta -= 2 * Math.PI;
    while (delta < -Math.PI) delta += 2 * Math.PI;
    body.phi += delta;

    // Velocity for the eventual throw, straight from the Verlet positions —
    // this already includes the tangential part of a wound-up swing.
    body.vx = (body.x - body.prevX) / dt;
    body.vy = (body.y - body.prevY) / dt;

    // His drawn tilt is the string's angle offset by where the grip sits on him,
    // so grabbing him off-centre rotates him until that point is uppermost.
    // (CSS rotation is clockwise; phi is measured the other way.)
    body.angle = ((body.gripAngle - body.phi) * 180) / Math.PI;
    body.spin = -((delta / dt) * 180) / Math.PI;
}

/**
 * On the floor. Starting is instant; only stopping takes time.
 *
 * There is no acceleration curve at the start of a walk — press a key and he is
 * at full speed on that frame. Ramping up made him feel like he was wading, and
 * the ramp is invisible anyway at the distances he covers. All the weight lives
 * at the other end: `stop` is friction, and it is what makes him skid on past
 * after you let go.
 *
 * Turning round is the one case that is NOT instant. His own momentum still
 * carries him the old way, so he skids to a halt first and only then leaves in
 * the new direction — which is exactly the moment the Slide animation covers.
 */
function stepGround(body, dt, walk, intent) {
    const dir = intent.walkDir;
    const drag = walk.stop * dt;

    if (dir === 0) {
        // Nothing pressed: friction, down to a dead stop.
        if (body.vx > 0) body.vx = Math.max(0, body.vx - drag);
        else if (body.vx < 0) body.vx = Math.min(0, body.vx + drag);
    } else if (body.vx !== 0 && Math.sign(body.vx) !== dir) {
        // Pushing against his own momentum. This is BRAKING, not coasting: he is
        // actively working against the skid, so it uses its own rate rather than
        // plain friction — pressing the other way should visibly kill the slide
        // faster than letting go does. He leaves in the new direction the instant
        // the old momentum reaches zero, which is also where Slide ends.
        const brake = walk.turn * dt;
        if (body.vx > 0) body.vx = Math.max(0, body.vx - brake);
        else body.vx = Math.min(0, body.vx + brake);
        if (body.vx === 0) body.vx = dir * walk.speed;
    } else {
        body.vx = dir * walk.speed;
    }

    if (dir !== 0) body.facing = dir;

    if (intent.jump) {
        body.vy = -walk.jump;
        body.mode = MODE.AIR;
        body.onFloor = false;
        // Marks the whole flight. It is what tells the state machine to show
        // Jump rather than Fall, and what stops the landing bouncing: a jump you
        // chose to make should put you back down where you meant to be, not
        // ricochet the way a thrown body does.
        body.jumping = true;
    }

    body.x += body.vx * dt;

    // Rotation settles to upright on the ground, so a spin he landed with
    // unwinds instead of leaving him permanently askew.
    body.angle = damp(body.angle, 6, dt);
    body.spin = damp(body.spin, 8, dt);
    if (Math.abs(body.angle) < 0.2) { body.angle = 0; body.spin = 0; }
}

/**
 * Airborne: gravity, drag, and steering IF this flight is a jump.
 *
 * `body.jumping` is the whole distinction. A jump is a move he chose to make,
 * so it stays his until he lands: hold a direction and he goes that way. A fall
 * or a throw is something that happened TO him and is not steerable at all.
 *
 * This used to be a tunable — Air control, default 0.35 — that applied to every
 * flight indiscriminately, which is what let a drop be flown around and a
 * landing be walked out of. Narrowing it to jumps removes the setting: there is
 * nothing left to tune, because steering a jump runs at the same instant walk
 * speed as the ground does, so a running jump reads as one continuous move
 * rather than the ground and the air disagreeing about how fast he is.
 */
function stepAir(body, dt, air, walk, intent) {
    if (body.jumping && intent.walkDir !== 0) {
        body.vx = intent.walkDir * walk.speed;
        body.facing = intent.walkDir;
    }

    body.vy += air.gravity * dt;
    body.vx = damp(body.vx, air.air_drag, dt);
    body.vy = damp(body.vy, air.air_drag, dt);
    // Air drag must not touch a landing rotation: that one is scripted to arrive
    // at upright exactly as he stops bouncing, and bleeding it would leave him
    // permanently askew.
    if (!body.unwinding) body.spin = damp(body.spin, air.spin_drag, dt);

    body.x += body.vx * dt;
    body.y += body.vy * dt;
    body.angle += body.spin * dt;
}

/**
 * How many more times he will hit the floor after a contact at `impact`.
 *
 * Each bounce keeps `e` of the speed, and bouncing stops once that drops below
 * the settle threshold. Knowing the count up front is what lets the landing
 * rotation be divided evenly across the bounces instead of guessed at.
 */
function predictBounces(impact, e, rest) {
    if (e <= 0 || rest <= 0) return 0;
    let v = impact * e;
    let n = 0;
    while (v >= rest && n < 32) { n++; v *= e; }
    return n;
}

/** Walls and floor. Sets `landedImpact` on the frame he touches down. */
function collide(body, dt, bounds, air) {
    // A body OUTSIDE the wall and travelling back in is left alone.
    //
    // Snapping it to the edge regardless is what a wall is for when something
    // hits it from the inside, but it is wrong for something returning from
    // beyond it: the catapult in release() launches him from wherever he was
    // let go, and clamping on the first frame would teleport him to the edge
    // and throw away the whole flight back.
    if (body.x < bounds.left && body.vx <= 0) {
        body.x = bounds.left;
        if (body.vx < 0) { body.vx = -body.vx * air.wall_bounce; body.spin = -body.spin * 0.5; }
    } else if (body.x > bounds.right && body.vx >= 0) {
        body.x = bounds.right;
        if (body.vx > 0) { body.vx = -body.vx * air.wall_bounce; body.spin = -body.spin * 0.5; }
    }

    // The ceiling only exists once he has been under it. The arrival drop starts
    // above the top of the screen on purpose, and a ceiling that applied from
    // frame one would catch him there and never let him in.
    if (body.y >= bounds.top) {
        body.enclosed = true;
    } else if (body.enclosed && body.vy <= 0) {
        // Same tolerance as the side walls: pulled out through the top and
        // catapulted back down, he falls in from where he was let go.
        body.y = bounds.top;
        if (body.vy < 0) {
            body.vy = -body.vy * air.floor_bounce;
            body.vx *= air.floor_friction;
            body.spin *= air.floor_friction;
        }
    }

    if (body.y >= bounds.floor) {
        const impact = body.vy;
        body.y = bounds.floor;

        if (impact > 0) {
            body.landedImpact = impact;
            body.squash = Math.min(1, impact / 2200);
            body.touchedFloor = true;

            const settling = body.jumping || impact < air.rest_speed;

            // First contact of this flight: fold however many turns he did into
            // the short way round to upright, and work out how many bounces are
            // left to spread that rotation over.
            //
            // The folding is the fix for landing on his feet and then unwinding
            // five whole rotations to "get back to 0" — those turns are already
            // spent, and 1800 degrees and 0 degrees are the same picture.
            if (!body.unwinding) {
                body.angle = normalizeDeg(body.angle);

                // …and if he arrives badly askew, snap him to within the tilt
                // limit right now. This lands on the exact frame the Land
                // animation takes over, so the sprite change covers it: an
                // instant correction reads as part of the impact, whereas
                // touching down at 170 degrees and rotating upright over the
                // next second reads as him melting.
                const cap = air.upright_max;
                if (cap < 180) {
                    if (body.angle > cap) body.angle = cap;
                    else if (body.angle < -cap) body.angle = -cap;
                }

                body.unwinding = true;
                body.hopsLeft = settling ? 0 : predictBounces(impact, air.floor_bounce, air.rest_speed);
            }

            // A jump lands dead. Anything else — a throw, a drop — keeps
            // bouncing until it is too slow to be worth another one.
            if (settling) {
                body.vy = 0;
                body.vx *= air.floor_friction;
                body.mode = MODE.GROUND;
                body.onFloor = true;
                if (body.jumping) body.landedFromJump = true;
                body.jumping = false;
                body.unwinding = false;
                body.hopsLeft = 0;
            } else {
                const up = impact * air.floor_bounce;
                body.vy = -up;
                body.vx *= air.floor_friction;
                body.onFloor = false;

                // Turn the rest of the way upright in equal shares, one share
                // per remaining bounce, each timed to the arc it happens over —
                // so 180 degrees with three bounces to go is 60 per hop and he
                // arrives at 0 exactly as he comes to rest.
                const hops = Math.max(1, body.hopsLeft);
                const arc = Math.max(0.05, (2 * up) / air.gravity);
                body.spin = -(body.angle / hops) / arc;
                body.hopsLeft = hops - 1;
            }
        }
    } else if (body.mode === MODE.GROUND && body.y < bounds.floor - 0.5) {
        // The floor moved out from under him — a resize, or a state whose frames
        // are a different height. Fall rather than hang in the air.
        body.mode = MODE.AIR;
        body.onFloor = false;
    }
}

/**
 * Advance the body by real elapsed time, in fixed sub-steps.
 * Returns the events the rest of the app reacts to this frame.
 */
export function step(body, elapsedMs, tuning, intent, frame) {
    // Two distinct moments, because the animation wants both: `touched` is the
    // first thud, which may be followed by more bouncing; `settled` is when he
    // finally comes to rest. Land runs from one to the other.
    const events = { touched: false, settled: false, fromJump: false, impact: 0 };

    const before = body.mode;
    body.touchedFloor = false;
    body.landedFromJump = false;

    // Every sub-step is EXACTLY STEP_MS and the remainder is carried to the next
    // frame. The old loop shortened its final sub-step to use the time up, which
    // Verlet cannot tolerate: it infers velocity from a position difference, so
    // a step of a different length silently rescales that velocity and the swing
    // gains or loses energy depending on the frame rate.
    body.accum = Math.min((body.accum || 0) + elapsedMs, MAX_CATCHUP_MS);

    // `jump` is one-shot: hand it to the first sub-step only, or a single press
    // would be applied eight times in one frame.
    const dt = STEP_MS / 1000;
    const substeps = Math.floor(body.accum / STEP_MS);
    const target = intent.anchor;

    let local = { ...intent };
    for (let i = 0; i < substeps; i++) {
        if (target) {
            // Walk the pivot smoothly from where it was to where the pointer now
            // is, rather than teleporting it on the first sub-step and solving
            // the other seven against a stationary one. A fast drag covers real
            // distance in 16ms, and a pivot that jumps it all at once yanks him
            // on one sub-step and then lets him coast — which is felt as a snatch
            // rather than a pull.
            const t = (i + 1) / substeps;
            local = {
                ...local,
                anchor: {
                    x: body.pivotPrevX + (target.x - body.pivotPrevX) * t,
                    y: body.pivotPrevY + (target.y - body.pivotPrevY) * t,
                    vx: target.vx,
                    vy: target.vy,
                },
            };
        }
        integrate(body, dt, tuning, local, frame);
        if (local.jump) local = { ...local, jump: false };
        body.accum -= STEP_MS;
    }

    if (target && substeps > 0) {
        body.pivotPrevX = target.x;
        body.pivotPrevY = target.y;
    }

    if (body.touchedFloor) {
        events.touched = true;
        events.impact = body.landedImpact;
        events.fromJump = body.landedFromJump;
    }
    if (before !== MODE.GROUND && body.mode === MODE.GROUND) {
        events.settled = true;
        events.impact = body.landedImpact;
    }
    return events;
}

/**
 * Put him back inside the frame after it changed size.
 * Only ever moves him in; never lifts him off a floor he is resting on.
 */
export function clampInside(body, world, frame) {
    const bounds = worldBounds(body, world, frame);
    body.x = Math.max(bounds.left, Math.min(bounds.right, body.x));
    if (body.y > bounds.floor) {
        body.y = bounds.floor;
        if (body.mode === MODE.AIR && body.vy > 0) body.vy = 0;
    }
    // Same rule as the collision: never push him down out of an arrival drop he
    // has not completed yet.
    if (body.enclosed && body.y < bounds.top) {
        body.y = bounds.top;
        if (body.vy < 0) body.vy = 0;
    }
}

/**
 * Release from the pointer: inherit its velocity, capped so a flick can't fire
 * him off — plus the catapult, if he was let go outside the world.
 *
 * `bounds` is optional; without it the catapult simply does not fire.
 */
export function release(body, tuning, bounds) {
    // The catapult. Held is the one mode with no collision, so he can be dragged
    // clean off the edge of the window — and letting go out there used to leave
    // him drifting back on nothing but whatever flick you happened to end on.
    //
    // Pulling him out now stores energy like drawing a bow: the further past the
    // edge he is when you let go, the harder he comes back. A straight spring,
    // v += k * overshoot, because that IS the bow — force proportional to draw,
    // and the speed it releases at is proportional to the draw too.
    //
    // Added BEFORE the speed cap below, so a huge pull is limited by the same
    // ceiling as a huge flick rather than firing him across the page.
    const k = (bounds && tuning.throw.snap_back) || 0;
    if (k > 0) {
        if (body.x < bounds.left) body.vx += (bounds.left - body.x) * k;
        else if (body.x > bounds.right) body.vx -= (body.x - bounds.right) * k;
        if (body.y < bounds.top) body.vy += (bounds.top - body.y) * k;
        else if (body.y > bounds.floor) body.vy -= (body.y - bounds.floor) * k;
    }

    const max = tuning.throw.release_max;
    const speed = Math.hypot(body.vx, body.vy);
    if (speed > max) {
        body.vx = (body.vx / speed) * max;
        body.vy = (body.vy / speed) * max;
    }
    const spinCap = tuning.grab.spin_max;
    if (spinCap > 0) body.spin = Math.max(-spinCap, Math.min(spinCap, body.spin));

    // He faces the way he is thrown, and keeps facing that way until something
    // else turns him — a walk, a steered jump, or the next throw. Set from the
    // velocity at the MOMENT of release, so a wall bounce on the way does not
    // spin him round mid-flight.
    //
    // The threshold is there because a release with no real sideways speed —
    // picking him up and putting him down — should leave him as he was rather
    // than snapping him to whichever direction a pixel of jitter happened to
    // point.
    if (Math.abs(body.vx) > RELEASE_TURN_SPEED) {
        body.facing = body.vx < 0 ? -1 : 1;
    }

    body.mode = MODE.AIR;
    body.onFloor = false;
    // A thrown body is not a jump: it bounces, and it shows Fall.
    body.jumping = false;
    // A fresh flight gets a fresh landing plan.
    body.unwinding = false;
    body.hopsLeft = 0;
}
