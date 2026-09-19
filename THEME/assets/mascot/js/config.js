/**
 * Reads the payload wp prints as `window.FC_MASCOT` and hands back a shape the
 * rest of the modules can rely on.
 *
 * This is the ONLY module that knows the payload can be missing, half-saved or
 * the wrong type. Everything downstream gets whole objects and real numbers, so
 * no other file has to write `|| 0` — which is how the old build ended up with
 * the same fallback constant repeated in four places and drifting between them.
 */

/** Physics arrives flat and prefixed (`grab_follow`); split it back into groups. */
const GROUPS = ['grab', 'throw', 'walk', 'world', 'squash'];

const PHYSICS_FALLBACK = {
    grab:  { min_rod: 0.12, gravity: 2400, damping: 0.5, spin_max: 1800 },
    throw: {
        gravity: 2600, release_max: 3200, floor_bounce: 0.35, wall_bounce: 0.45,
        floor_friction: 0.70, air_drag: 0.06, spin_drag: 0.80, rest_speed: 90,
        upright_max: 90, snap_back: 7,
    },
    walk:  { speed: 110, stop: 420, turn: 1100, jump: 820 },
    world: { spawn_x: 85, spawn_height: 220, spawn_delay: 350, floor: 0, ceiling: 0 },
    // 940 is the length the spring's own constants produce; see SQUASH_MS.
    squash: { ms: 940 },
};

/**
 * The machine's WIRING: whether a state loops, and what follows it.
 *
 * Fixed, not configurable. Land always hands over to After-land, Idle is where
 * everything returns to, a one-shot is a one-shot. These were editable once and
 * it was a mistake — every state carried two dropdowns whose only correct answer
 * was the one already selected, and a wrong answer wired the machine into a
 * shape it couldn't run. Mirrors fc_mascot_states() in mascot.php.
 */
export const STATE_SHAPE = {
    idle:         { loops: true,  next: '' },
    walk:         { loops: true,  next: '' },
    slide:        { loops: true,  next: '' },
    // Goes over, then gets straight back up. The skid is NOT part of this: if
    // he still has momentum the friction carries him along underneath and Slide
    // shows on its own once he is upright, and a Slide that started on its own
    // just ends — only a trip earns a getting-up.
    trip:         { loops: false, next: 'after_sleep' },
    random:       { loops: false, next: 'idle' },
    jump:         { loops: false, next: '' },
    fall:         { loops: true,  next: '' },
    // Loops because it has to cover an unknown number of bounces.
    land:         { loops: true,  next: 'after_land' },
    after_land:   { loops: false, next: 'idle' },
    held:         { loops: true,  next: '' },
    before_sleep: { loops: false, next: 'sleep' },
    sleep:        { loops: true,  next: '' },
    after_sleep:  { loops: false, next: 'idle' },
};

/**
 * The numbers that DO vary, per state.
 *
 * No `play_ms` any more: how long a state runs is a property of the ANIMATION
 * now — its own length times its Loops count — not a duration typed against the
 * state. See `loops` in readEntry().
 */
const RULE_FALLBACK = {
    idle: { sleep_after_ms: 20000 },
    walk: { gap_min_ms: 4500, gap_max_ms: 11000, range: 64 },
    slide: { min_speed: 26 },
    trip: { chance: 20 },
    fall: { min_speed: 620 },
    land: { min_impact: 280 },
    after_sleep: { hop: 520 },
};

export const STATE_KEYS = Object.keys(STATE_SHAPE);

function num(value, fallback) {
    const n = typeof value === 'number' ? value : parseFloat(value);
    return Number.isFinite(n) ? n : fallback;
}

/** One animation entry, with everything the sheet loader and animator need. */
function readEntry(raw) {
    if (!raw || typeof raw !== 'object' || !raw.png) return null;
    return {
        name: String(raw.name || ''),
        png: String(raw.png),
        direction: String(raw.direction || 'forward'),
        // Squash and stretch. `bounce` is the animation's own value and is what
        // a frame with none of its own uses; `bounces` is per frame, aligned to
        // `frames`, so one drawing in a walk can give more than the rest.
        bounce: Math.max(0, Math.min(10, num(raw.bounce, 1))),
        bounces: readBounces(raw.bounces, Math.max(0, Math.min(10, num(raw.bounce, 1)))),
        frames: readFrames(raw.frames),
        particle: readParticle(raw.particle),
        // Per-animation extras, read unconditionally: a missing value costs
        // nothing, and a state growing an extra later then needs no change here.
        chance: Math.max(0, Math.min(100, num(raw.chance, 50))),
        // How many full passes of this animation before the state is done with
        // it. Replaced a per-state duration in ms, which could not be a whole
        // number of passes unless someone kept it in step with the timeline by
        // hand — and stopped the animation mid-frame whenever they did not.
        loops: Math.max(1, Math.min(50, Math.round(num(raw.loops, 1)))),
    };
}

function readFrames(raw) {
    return Array.isArray(raw) ? raw.map((ms) => Math.max(16, num(ms, 120))) : [];
}

/**
 * Per-frame bounciness, index-aligned to `frames`.
 *
 * A missing entry falls back to the animation's own value, NOT to zero — zero is
 * rigid and is something you might deliberately want on a single frame, so it
 * cannot double as "unset".
 */
function readBounces(raw, fallback) {
    if (!Array.isArray(raw)) return [];
    return raw.map((v) => Math.max(0, Math.min(10, num(v, fallback))));
}

/** The optional particle attached to an entry. Null when no art is set. */
function readParticle(raw) {
    if (!raw || typeof raw !== 'object' || !raw.png) return null;
    return {
        png: String(raw.png),
        direction: 'forward',
        frames: readFrames(raw.frames),
        motion: String(raw.motion || 'rise'),
        count: Math.max(1, Math.round(num(raw.count, 3))),
        distance: Math.max(0, num(raw.distance, 7)),
        speedMs: Math.max(100, num(raw.speed_ms, 1400)),
        offsetX: num(raw.offset_x, 0),
        offsetY: num(raw.offset_y, -10),
    };
}

export function readConfig(raw) {
    const src = raw && typeof raw === 'object' ? raw : {};

    const physics = {};
    for (const group of GROUPS) {
        physics[group] = { ...PHYSICS_FALLBACK[group] };
        const flat = src.physics && typeof src.physics === 'object' ? src.physics : {};
        for (const key of Object.keys(PHYSICS_FALLBACK[group])) {
            physics[group][key] = num(flat[`${group}_${key}`], PHYSICS_FALLBACK[group][key]);
        }
    }

    const states = {};
    for (const key of STATE_KEYS) {
        const stored = (src.states && src.states[key]) || {};
        const rulesRaw = stored.rules || {};
        const rules = { ...(RULE_FALLBACK[key] || {}) };
        for (const rk of Object.keys(rules)) {
            rules[rk] = num(rulesRaw[rk], rules[rk]);
        }

        const entries = [];
        for (const raw of (Array.isArray(stored.entries) ? stored.entries : [])) {
            const entry = readEntry(raw);
            if (entry) entries.push(entry);
        }
        states[key] = { ...STATE_SHAPE[key], rules, entries };
    }

    const sections = [];
    for (const raw of (Array.isArray(src.sections) ? src.sections : [])) {
        const entry = readEntry(raw);
        if (!entry || !raw.selector) continue;
        entry.selector = String(raw.selector);
        // No hold in ms: `loops` is already read by readEntry(), and a reaction
        // ends either when it has been round that many times or the moment you
        // scroll out of the section — whichever comes first.
        sections.push(entry);
    }

    return {
        debug: !!src.debug,
        scale: num(src.scale, 3),
        scaleMobile: num(src.scaleMobile, 2),
        mobileBreakpoint: num(src.mobileBreakpoint, 768),
        artFacing: src.artFacing === 'right' ? 'right' : 'left',
        physics,
        states,
        sections,
    };
}
