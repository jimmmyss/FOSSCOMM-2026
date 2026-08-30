<?php
/**
 * Pixel mascot — WordPress side.
 *
 * This file is the CONTRACT between wp-admin and the browser, and nothing else.
 * It declares what a mascot is made of (states, entries, particles, physics),
 * validates it, and hands it to the front end as one JSON payload. It contains
 * no behaviour: how he falls, when he sleeps and how a sheet is cut into frames
 * all live in assets/mascot/js/, each in its own module.
 *
 * ── Where the art lives ──────────────────────────────────────────────────────
 * In the media library, uploaded through FOSSCOMM → Mascot. Nothing ships in
 * this folder — no PNGs, no atlas JSON. A sprite sheet is a plain horizontal (or
 * gridded) strip of equal cells; the frame size is derived from the image itself
 * and the per-frame timing is typed into the admin timeline, so there is no
 * export step and no second file to keep in sync.
 *
 * ── The shape of the option ──────────────────────────────────────────────────
 * One option, `fc_mascot`:
 *
 *   enabled, scale_desktop, scale_mobile, mobile_breakpoint, debug
 *   physics  => [ grab_*, throw_*, walk_*, world_* ]          flat, grouped by prefix
 *   states   => [ <state key> => [ 'rules' => [...], 'entries' => [ <entry>, … ] ] ]
 *   sections => [ [ 'selector', <entry> ], … ]
 *
 * An <entry> is one named animation:
 *
 *   [ 'name', 'png', 'direction', 'frames' => [ ms, … ],
 *     'particle' => [ 'png', 'frames' => [ ms, … ], 'motion', … ],
 *     …plus any per-animation extras the state declares (Random: chance, timing) ]
 *
 * There is no frame size: a sheet is a strip of square cells, so the cell is the
 * shorter side of the image and the count is the longer side divided by it.
 *
 * A state may hold SEVERAL entries. When the state is entered one is picked at
 * random and played for the whole run of that state, so "fall mad" and "fall
 * sad" are two faces of one behaviour — the rules live on the state, never on
 * the entry.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Option key. Deliberately not the old `fc_mascot_settings`: the shape changed. */
const FC_MASCOT_OPTION = 'fc_mascot';

/**
 * Viewport width (px) below which the mobile scale applies. Measured in JS off
 * the real viewport and re-measured on resize, never from the user agent — a
 * page cache can hand a phone the desktop HTML and defeat a UA check, and a UA
 * says nothing about a desktop window dragged narrow.
 */
const FC_MASCOT_BREAKPOINT = 768;

/* ─────────────────────────────────────────────────────────────────────────────
   STATES
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * The state machine, declared.
 *
 * `loops` and `next` are the machine's WIRING and are deliberately not editable.
 * Land always hands over to After-land; Idle is where everything comes back to;
 * a one-shot is a one-shot. Exposing those as form controls invited you to
 * rewire the machine into a shape it can't run, and every state then carried
 * two dropdowns whose only correct answer was the one already selected.
 *
 * `rules` are the numbers that genuinely vary, rendered as the state's own
 * controls: [ default, min, max, step, label, help ].
 *
 * `entry_extra` adds fields to each ANIMATION in the state rather than to the
 * state itself — for Random, how likely that particular animation is and when
 * it fires. That belongs on the animation because it is about which art gets
 * chosen, not about how the state behaves.
 *
 * Order is the order the admin page renders them in.
 */
/**
 * How many times ONE animation repeats before it is finished with.
 *
 * This replaced a per-state "Play for" in milliseconds, which was the wrong unit
 * for the job. A duration has to be kept in step by hand with the timeline
 * underneath it — retime a frame and the number is silently wrong — and any
 * value that is not an exact multiple of the animation's length stops it
 * part-way through: two sheets totalling 500ms with Play for at 1000 ran the
 * first 500, then the first 500 again, and cut off wherever 1000 landed.
 *
 * A count cannot be out of step with anything. Three loops is three loops
 * however the frames are timed, and the animation always ends where it ends.
 *
 * Per ANIMATION rather than per state, because a state can hold several sheets
 * and they are not the same length.
 *
 * `$note` says what happens when the last loop finishes, which differs by state.
 */
function fc_mascot_loops_field(string $note): array {
    return [
        'type' => 'number', 'default' => 1, 'min' => 1, 'max' => 50, 'step' => 1,
        'label' => 'Loops',
        'help'  => 'How many times this animation plays through before it is done. 1 is one full pass '
                 . 'of the timeline below. ' . $note,
    ];
}

function fc_mascot_states(): array {
    $states = fc_mascot_state_defs();

    // Loops is added to every state's ANIMATIONS from one definition here,
    // rather than the same declaration pasted a dozen times and drifting.
    //
    // Only states that actually end on their own get it. A state the physics
    // owns — Walk, Fall, Held, Sleep — runs until something physical stops it,
    // so a repeat count has nothing to control and a box that does nothing is
    // worse than no box.
    foreach ($states as $key => $def) {
        if (empty($def['loops'])) {
            $states[$key]['entry_extra'] = array_merge(
                ['loops' => fc_mascot_loops_field((string) ($def['play_note'] ?? ''))],
                (array) ($def['entry_extra'] ?? [])
            );
        }
        unset($states[$key]['play_note']);
    }
    return $states;
}

function fc_mascot_state_defs(): array {
    return [
        'idle' => [
            'label' => 'Idle',
            'help'  => 'Standing still, doing nothing in particular. This is the normal state — everything '
                     . 'comes back here when it has finished.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Idle animation, if there is more than one — with a single '
                         . 'one he simply carries on, never interrupted mid-loop.',
            'rules' => [
                'sleep_after_ms' => [20000, 0, 600000, 1, 'Sleep after',
                    'ms without scrolling before Before-sleep plays. 0 = he never falls asleep.'],
            ],
        ],
        'walk' => [
            'label' => 'Walk',
            'help'  => 'Moving along the floor. He walks when you hold an arrow key, and takes the odd step '
                     . 'by himself in between — the three numbers below are that unprompted wandering.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Walk animation. Walking itself ends when he stops moving, '
                         . 'not on this timer.',
            'rules' => [
                'gap_min_ms' => [4500, 200, 240000, 1, 'Pause between moves (min)',
                    'Shortest wait before he does something unprompted — a step, or one of the Random animations.'],
                'gap_max_ms' => [11000, 200, 240000, 1, 'Pause between moves (max)',
                    'Longest wait before the same. The actual gap is picked at random between the two, so he '
                    . 'does not move on a metronome.'],
                'range'      => [64, 0, 2000, 1, 'Wander distance',
                    'How far either side of where he landed his own steps may take him, in px. He turns back '
                    . 'at the edge instead of drifting away across the page. Your arrow keys ignore this.'],
            ],
        ],
        'slide' => [
            'label' => 'Slide',
            'help'  => 'Carrying on the way he was going after he has stopped pushing — letting go of the '
                     . 'key, or turning round while his own momentum is still taking him the other way. '
                     . 'Ends the instant he is either still or moving the way he is now facing, so it only '
                     . 'ever covers the skid itself. Walking STARTS instantly, with no run-up, so this is '
                     . 'the only place his movement has any weight; how long it lasts is Walk & jump → Stopping.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Slide animation. The skid itself ends when he stops or '
                         . 'turns, not on this timer.',
            'rules' => [
                'min_speed' => [26, 0, 800, 1, 'Slide above',
                    'px/s of leftover speed before the skid is worth animating. Below it he simply stops — '
                    . 'without this he would flicker into a slide for the last pixel of every step he takes.'],
            ],
        ],
        'trip' => [
            'label' => 'Trip',
            'help'  => 'He catches his foot and goes down — the moment of tripping and hitting the ground — '
                     . 'and then picks himself up with the Wake up animation. Trip → Wake up → Idle. Rolled '
                     . 'at the two moments he arrives on his own feet with speed to shed: the end of a walk, '
                     . 'yours or his own, and the touchdown of a jump. Not on a throw or a drop; those land, '
                     . 'and Land covers them. Any momentum he still has keeps carrying him along underneath '
                     . 'throughout, so Slide may show by itself once he is upright again — but a Slide is '
                     . 'only ever a skid, and one that starts on its own simply ends. He is busy while this '
                     . 'plays: walking and jumping are ignored, though you can still pick him up.',
            'loops' => false,
            'next'  => 'after_sleep',
            'play_note' => 'Then straight into Wake up, which is where he picks himself up. Short is right '
                         . 'here: this is the going-over itself, not the getting back up, which the Wake up '
                         . 'animation covers.',
            'rules' => [
                'chance' => [20, 0, 100, 1, 'Chance',
                    '% of walk-endings and jump-landings that turn into a trip. One number covers both, rolled '
                    . 'once each time, so 20 means roughly one walk in five and one jump in five. 0 turns it '
                    . 'off and he always just drifts to a stop or sticks the landing.'],
            ],
        ],
        'random' => [
            'label' => 'Random',
            'help'  => 'One-off things he does INSTEAD of wandering off — a wave, a stretch, a look around. '
                     . 'Every so often he decides to do something unprompted, and this is the coin flip: '
                     . 'take a step, or play one of these. Each carries its own chance, so a common shrug '
                     . 'and a rare flourish can share the pool. While one plays he is busy and walking is '
                     . 'ignored. Not the same as Idle — Idle is the loop he rests in, these happen on top '
                     . 'of it. For something that fires when a walk ENDS, see Fall down.',
            'loops' => false,
            'next'  => 'idle',
            'play_note' => 'Then back to Idle. Set this to 2 or 3 if you want a wave played through more ' . 'than once before he carries on.',
            'rules' => [],
            'entry_extra' => [
                'chance' => [
                    'type' => 'number', 'default' => 50, 'min' => 0, 'max' => 100, 'step' => 1,
                    'label' => 'Chance',
                    'help'  => '% chance this one is what he does instead of taking a step. Rolled separately '
                             . 'for each animation, so they do not have to add up to 100 — one animation at '
                             . '50 gives an even split between wandering and doing this.',
                ],
            ],
        ],
        'jump' => [
            'label' => 'Jump',
            'help'  => 'The whole hop — up, over and down — space, W or the up arrow. Not Fall on the way '
                     . 'down and not Land on arrival: a jump is one continuous move.',
            'loops' => false,
            'next'  => '',
            'play_note' => 'Then he picks another Jump animation. The hop itself ends when he touches down, ' . 'not on the loop count.',
        ],
        'fall' => [
            'label' => 'Fall',
            'help'  => 'Airborne and not by choice — the drop onto the page, and anything you throw. '
                     . 'A jump is not this; that is its own animation start to finish.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Fall animation. Falling itself ends when he lands, not on '
                         . 'this timer.',
            'rules' => [
                'min_speed' => [620, 0, 6000, 1, 'Start falling above',
                    'px/s of speed before he looks like he is falling. Below it he stays in whatever he was '
                    . 'doing and simply drops, so lifting him and letting go from just above the floor is a '
                    . 'set-down, not a plummet. At the default gravity 620 is about a 75px drop — roughly '
                    . 'his own height. 0 makes every airborne moment a fall, however small.'],
            ],
        ],
        'land' => [
            'label' => 'Land',
            'help'  => 'Hitting the ground. Starts on the first thud and holds — looping if it is short — '
                     . 'through however many times he bounces, so it covers the whole impact rather than '
                     . 'finishing while he is still in the air. After-land follows once he has stopped moving. '
                     . 'A jump lands dead, so it goes straight through.',
            'loops' => true,
            'next'  => 'after_land',
            'play_note' => 'Then he picks another Land animation. The landing itself lasts until he has '
                         . 'finished bouncing, however many bounces that takes, so it is not this timer '
                         . 'that ends it.',
            'rules' => [
                'min_impact' => [280, 0, 6000, 1, 'Minimum impact',
                    'px/s of downward speed below which a landing is too gentle to be worth animating — '
                    . 'he goes straight to Idle instead.'],
            ],
        ],
        'after_land' => [
            'label' => 'After land',
            'help'  => 'What he does having picked himself up — dazed, annoyed, dusting himself off. '
                     . 'Plays once, then back to Idle.',
            'loops' => false,
            'next'  => 'idle',
            'play_note' => 'Then back to Idle. Counted from the moment he comes to REST after the final ' . 'bounce — not from when he first hit the ground — so a long dizzy spell is ' . 'entirely these loops and never gets eaten by the bouncing.',
        ],
        'held' => [
            'label' => 'Held',
            'help'  => 'Hanging from your cursor. Lasts exactly as long as you hold the button down.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Held animation. Being held ends when you let go, not on '
                         . 'this timer.',
        ],
        'before_sleep' => [
            'label' => 'Before sleep',
            'help'  => 'Nodding off. Fires when Idle’s “Sleep after” timer runs out, then hands over to Sleep.',
            'loops' => false,
            'next'  => 'sleep',
            'play_note' => 'Then he is asleep.',
        ],
        'sleep' => [
            'label' => 'Sleep',
            'help'  => 'Asleep. Holds until you scroll.',
            'loops' => true,
            'next'  => '',
            'play_note' => 'Then he picks another Sleep animation. Sleeping itself lasts until something '
                         . 'wakes him, not until this runs out.',
        ],
        'after_sleep' => [
            'label' => 'Wake up',
            'help'  => 'Coming round — a stretch, a yawn, rubbing his eyes. Also doubles as GETTING UP: it '
                     . 'is what plays straight after a Trip, so he picks himself up off the floor instead '
                     . 'of springing back to standing. Otherwise it fires when SCROLLING disturbs him while '
                     . 'he is asleep — the keys will not, since a sleeping mascot ignores them entirely. '
                     . 'Grabbing him is the other exception: being picked up wakes him instantly and skips '
                     . 'this, since yawning while dangling from a cursor reads oddly. Plays once, then back '
                     . 'to Idle, and he takes no input at all until it has finished.',
            'loops' => false,
            'next'  => 'idle',
            'play_note' => 'Then back to Idle. If the startle hop below outlasts the loops he stays in this ' . 'animation until he has landed, so the hop is never cut off mid-air.',
            'rules' => [
                'hop' => [520, 0, 3000, 10, 'Startle hop',
                    'px/s of upward jump — he leaps out of his skin. Applies EVERY time this animation '
                    . 'plays, waking or getting up after a trip, so both are a little hop rather than one '
                    . 'of them happening flat-footed. The animation covers the whole hop, so it is one '
                    . 'continuous move rather than a jump with a fall and a landing on the end, and it '
                    . 'comes down dead without bouncing. 0 for no hop at all.'],
            ],
        ],
    ];
}

/* ─────────────────────────────────────────────────────────────────────────────
   PHYSICS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Every physics number, grouped the way the admin page shows them.
 *
 * Each row: [ default, min, max, step, label, help ]. A step of 1 renders an
 * integer box, anything smaller allows decimals. Add a row here and it appears
 * in wp-admin, gets clamped on save, and reaches the browser — the JS reads each
 * with its own fallback, so a key missing from a part-saved option is never
 * fatal.
 */
function fc_mascot_physics_groups(): array {
    return [
        'grab' => [
            'label'  => 'Grab',
            'help'   => 'He is pinned wherever you actually click and swings from that point under his own '
                      . 'weight — so grabbing him by an ear gives a long, lazy swing and grabbing him near '
                      . 'the middle barely swings at all, the way holding a real object does. Only his drawn '
                      . 'pixels can be grabbed, not the empty corners of his sprite. Drag and he trails, '
                      . 'stop and he keeps swinging, whip the cursor in a circle and he goes over the top. '
                      . 'Let go mid-swing and he is flung along the arc.',
            'fields' => [
                'min_rod'  => [0.12, 0.02, 2, 0.01, 'Minimum swing arm', 'The shortest string he will swing on, as a fraction of his height. Only comes into play when you grab him almost exactly in the middle, where there is no leverage and he would otherwise just follow your cursor rigidly. Raise it to make even a dead-centre grab swing.'],
                'gravity'  => [2400, 0, 12000, 50, 'Swing gravity', 'How hard he is pulled back under your pointer. Higher swings faster and is harder to turn all the way over; 0 leaves him weightless, so any nudge sends him drifting round forever.'],
                'damping'  => [0.5, 0, 20, 0.05, 'Swing damping', 'How quickly the swinging dies away. Low keeps momentum and lets you wind him up; high settles him under your cursor almost at once.'],
                'spin_max' => [1800, 0, 5000, 10, 'Spin limit', 'deg/s cap carried into the throw, so letting go mid-whip makes him spin rather than strobe.'],
            ],
        ],
        'throw' => [
            'label'  => 'Throw & fall',
            'help'   => 'Everything once he is off your cursor and in the air.',
            'fields' => [
                'gravity'        => [2600, 100, 12000, 50, 'Gravity', 'px/s². Higher falls faster and hits harder.'],
                'release_max'    => [3200, 100, 12000, 50, 'Throw limit', 'px/s cap on release speed. High enough that a hard flick actually flies — lower it if he leaves the screen faster than you like. Also caps the catapult below, so no pull can fire him further than a hard throw.'],
                'snap_back'      => [7, 0, 40, 0.5, 'Catapult', 'How hard he springs back when you drag him off the edge of the window and let go. It is a bow: this many px/s of return speed for every px you pulled him past the edge, so a small tug drifts him back and a long pull fires him in. 0 turns it off and he simply falls from wherever you dropped him.'],
                'floor_bounce'   => [0.35, 0, 0.95, 0.01, 'Floor bounce', 'Share of speed kept off the floor. 0 = lands dead.'],
                'wall_bounce'    => [0.45, 0, 0.95, 0.01, 'Wall bounce', 'The same, off the left and right edges.'],
                'floor_friction' => [0.70, 0, 1, 0.01, 'Floor friction', 'Share of sideways speed KEPT through a bounce. 1 = frictionless ice.'],
                'air_drag'       => [0.06, 0, 3, 0.01, 'Air drag', 'Per second. Slows him through the air; 0 = vacuum.'],
                'spin_drag'      => [0.80, 0, 10, 0.05, 'Spin drag', 'The same for rotation — how fast a thrown spin winds down.'],
                'rest_speed'     => [90, 5, 800, 5, 'Settle speed', 'px/s below which he stops bouncing and comes to rest.'],
                'upright_max'    => [90, 0, 180, 1, 'Landing tilt limit', 'On the first touchdown his tilt is snapped into ±this many degrees, instantly, on the frame the Land animation takes over — so he never lands upside down and then slowly rights himself. The bounces turn him the rest of the way. At 90 he can land on his side but never on his head; lower for a tidier landing, 0 for perfectly upright every time, 180 to leave whatever angle he arrived at.'],
            ],
        ],
        'walk' => [
            'label'  => 'Walk & jump',
            'help'   => 'Movement. Arrow keys or WASD walk, space or up jumps. An animation, once it starts, RUNS TO THE END: nothing you press cuts a Random short, walks him out of a trip, hurries a yawn along or interrupts a section reaction, and the keys are ignored rather than queued until it finishes. Asleep he ignores them too — scroll to wake him, or pick him up. So the keys reach him while he is standing, walking or skidding, and mid-jump, which he can steer until he lands.',
            'fields' => [
                'speed'       => [110, 10, 900, 5, 'Walk speed', 'px/s, reached instantly — he is at full speed on the frame you press. His own unprompted steps use this same number; a step is a step, whoever asked for it.'],
                'stop'        => [420, 20, 6000, 10, 'Stopping', 'px/s² of friction once you let go. There is no acceleration at the start of a walk, so this is most of the weight in his movement: it decides how far he skids on after you stop pressing, which is what the Slide animation covers. Lower is slippier.'],
                'turn'        => [1100, 20, 8000, 10, 'Turn braking', 'px/s² while you press the OPPOSITE way to the one he is travelling. Higher than Stopping because pushing against your own momentum should kill a skid faster than simply letting go does; set it equal to Stopping and turning round feels no different from coasting. The moment his old momentum reaches zero he leaves in the new direction and the Slide ends.'],
                'jump'        => [820, 100, 3000, 10, 'Jump strength', 'px/s of upward launch. Only from the ground — he cannot jump again in mid-air.'],
            ],
        ],
        'world' => [
            'label'  => 'World',
            'help'   => 'The box he lives in, and how he arrives in it.',
            'fields' => [
                'spawn_x'     => [85, 0, 100, 1, 'Drop position', '% across the window where he falls in. 0 = hard left, 100 = hard right.'],
                'spawn_height'=> [220, 0, 3000, 10, 'Drop height', 'px above the top of the window he starts from.'],
                'spawn_delay' => [350, 0, 10000, 50, 'Drop delay', 'ms after the page loads before he falls in.'],
                // No left/right setting on purpose: the sides of the window ARE
                // the sides of his world. An adjustable inset there was only a
                // way to leave a border in by accident. All four edges meet his
                // DRAWN silhouette, not his sprite cell, so the transparent
                // margin in the art no longer holds him off them either.
                'floor'       => [0, -200, 600, 1, 'Floor', 'px between the bottom of the window and his feet. 0 = standing right on the bottom edge.'],
                'ceiling'     => [0, -200, 600, 1, 'Ceiling', 'px between the top of the window and his head. He bounces off it, so a hard throw upward can no longer put him off the top of the screen. The arrival drop still comes in from above — the ceiling only starts applying once he is inside.'],
            ],
        ],
        'squash' => [
            'label'  => 'Squash & stretch',
            'help'   => 'How long one give lasts, wherever it comes from — a landing, a footfall, an animation '
                      . 'starting, a frame with its own Bounciness coming up. How DEEP each of those goes is the '
                      . 'Bounciness you set on the animation or the frame; this is only its length.',
            'fields' => [
                'ms' => [940, 100, 3000, 10, 'Bounce length', 'ms for one squash to flatten, rebound past neutral, wobble and settle. 940 is what he has always done. This is wall-clock time and has nothing to do with how long a frame is held: a pose on screen for 1500ms still gives one bounce of this length and then stands still for the rest. It retimes the whole spring without changing its shape, so he rebounds the same distance past neutral at 200ms as at 3000ms — just faster. Shorter reads as light and cartoony, longer as heavy and rubbery.'],
            ],
        ],
    ];
}

/**
 * The Landing tilt limit's previous default, kept only so the migration below
 * can recognise a value nobody has deliberately chosen.
 */
const FC_MASCOT_OLD_UPRIGHT_MAX = 45;

/**
 * One-time migration: raise a stored Landing tilt limit of 45 to the current
 * default of 90.
 *
 * Raising the default alone was not enough, and this is the half that was
 * missing. fc_mascot_settings() array_merges the SAVED physics over the
 * defaults, so a value written to the option when 45 was the default keeps
 * winning forever — the new default only ever reaches someone who has never
 * saved the Mascot page. That is the same trap the old Air control setting sat
 * in, and it is why "the default says 90" is not an answer to "why does he still
 * snap upright at 45".
 *
 * Only an exact 45 is touched, because that is precisely the value that means
 * "never changed". Anything else — 30, 60, 180 — is a decision and is left
 * alone. Idempotent by construction: after this runs the value is 90, which no
 * longer matches.
 *
 * admin_init with an autoloaded flag, matching fc_maybe_drop_seeded_programme()
 * in inc/seed.php, so the option is not re-read on every request forever.
 */
add_action('admin_init', 'fc_mascot_maybe_raise_upright_max');
function fc_mascot_maybe_raise_upright_max(): void {
    if (get_option('fc_mascot_upright_max_raised')) {
        return;
    }
    update_option('fc_mascot_upright_max_raised', 1, true);

    $saved = get_option(FC_MASCOT_OPTION, []);
    if (!is_array($saved) || !isset($saved['physics']['throw_upright_max'])) {
        return;
    }
    if ((float) $saved['physics']['throw_upright_max'] !== (float) FC_MASCOT_OLD_UPRIGHT_MAX) {
        return;
    }

    $saved['physics']['throw_upright_max'] = 90;
    update_option(FC_MASCOT_OPTION, $saved);
}

/** Flat defaults for every physics field, keyed `<group>_<field>`. */
function fc_mascot_physics_defaults(): array {
    $out = [];
    foreach (fc_mascot_physics_groups() as $gkey => $group) {
        foreach ($group['fields'] as $fkey => $def) {
            $out[$gkey . '_' . $fkey] = $def[0];
        }
    }
    return $out;
}

/** One physics value clamped to its declared range, int vs float preserved. */
function fc_mascot_clamp_physics(string $key, $value) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (fc_mascot_physics_groups() as $gkey => $group) {
            foreach ($group['fields'] as $fkey => $def) {
                $map[$gkey . '_' . $fkey] = $def;
            }
        }
    }
    if (!isset($map[$key])) return $value;
    [$default, $min, $max, $step] = $map[$key];
    if (!is_numeric($value)) return $default;
    $n = ($step >= 1) ? (int) round((float) $value) : (float) $value;
    return max($min, min($max, $n));
}

/* ─────────────────────────────────────────────────────────────────────────────
   ANIMATION ENTRIES
   ───────────────────────────────────────────────────────────────────────────*/

/** Playback directions, shared by the form, the validator and animator.js. */
function fc_mascot_directions(): array {
    return [
        'forward'          => 'Forward',
        'reverse'          => 'Reverse',
        'pingpong'         => 'Ping-pong (1→N→1)',
        'pingpong_reverse' => 'Ping-pong reverse (N→1→N)',
    ];
}

/**
 * How an attached particle moves.
 *
 * Deliberately a short list of presets with four numbers rather than a
 * hand-authored JSON file: every particle a mascot actually needs is one of
 * these five, and a preset is something you can pick in a dropdown and watch in
 * the preview immediately instead of a file to write, upload and keep in sync.
 */
function fc_mascot_particle_motions(): array {
    return [
        'orbit'  => 'Orbit — circle him (dizzy stars)',
        'rise'   => 'Rise — drift up and fade (sleepy z’s)',
        'fall'   => 'Fall — drift down and fade',
        'burst'  => 'Burst — fire outwards once, then fade',
        'follow' => 'Follow — sit on him, no motion',
    ];
}

/**
 * The particle numbers — four that mean the same thing whichever motion you
 * pick, plus where on him they come from.
 *
 * There were nine, several of which only applied to a single motion
 * (`orbit_ms`, `spread`, `interval_ms`), so most of the block was inert whatever
 * you chose. Emission rate is now derived rather than typed: `count` particles
 * each living `speed_ms`, spaced evenly, so "3 z's, 1.4 seconds each" gives one
 * every ~470ms with no fourth number to keep consistent with the other three.
 *
 * [ default, min, max, step, label, help ].
 */
function fc_mascot_particle_fields(): array {
    return [
        'count'    => [3, 1, 24, 1, 'How many', 'How many are on screen at once.'],
        'distance' => [7, 0, 300, 0.5, 'Distance', 'How far from him they get, in sprite px — orbit radius, drift height, burst reach. Scales with his size.'],
        'speed_ms' => [1400, 100, 30000, 1, 'Time', 'ms for one lap (orbit) or one particle’s whole life (everything else). Higher is slower.'],
        'offset_x' => [0, -200, 200, 0.5, 'Offset X', 'Sprite px from his centre. Positive is right.'],
        'offset_y' => [-10, -200, 200, 0.5, 'Offset Y', 'Sprite px from his centre. Negative is up — above his head.'],
    ];
}

/** Defaults for one particle block, art included. */
function fc_mascot_particle_defaults(): array {
    $out = ['png' => '', 'frames' => [], 'motion' => 'rise'];
    foreach (fc_mascot_particle_fields() as $key => $def) {
        $out[$key] = $def[0];
    }
    return $out;
}

/**
 * Defaults for one animation entry.
 *
 * No frame size here: it is derived from the image. A sheet is a strip of SQUARE
 * cells, so the cell is the shorter side of the image and the frame count is the
 * longer side divided by it — 128×32 is four 32×32 frames, 32×128 is four
 * stacked. Those were two form fields whose only correct value was the one the
 * image already implied.
 */
function fc_mascot_entry_defaults(): array {
    return [
        'name'      => '',
        'png'       => '',
        'direction' => 'forward',
        'bounce'    => 1,          // what a frame with no value of its own uses
        'frames'    => [],         // per-frame ms, index-aligned to the cut sheet
        'bounces'   => [],         // per-frame bounciness, same alignment
        'particle'  => fc_mascot_particle_defaults(),
    ];
}

/**
 * The squash-and-stretch control every animation carries.
 *
 * This one is the pop ON ENTERING, and only that. How much he gives WHILE an
 * animation plays is per frame, in the timeline — which is the useful place for
 * it, since the give belongs to the drawing rather than to the sheet.
 *
 * It doubles as the default for a frame left blank, so an animation whose frames
 * were never given individual numbers behaves exactly as it always did.
 *
 * Shown for EVERY animation, one frame or twenty. It was hidden for single-frame
 * sheets for a while, on the reasoning that one drawing means one bounciness —
 * but arriving in an animation and holding a pose are two different moments even
 * when they are drawn the same, and hiding it left a one-frame animation with no
 * arrival pop it was possible to set.
 */
function fc_mascot_bounce_field(): array {
    return [1, 0, 10, 0.1, 'Bounciness on entering',
        'The pop as this animation STARTS, 0–10, so one eases into the next instead of cutting hard. '
        . 'How springy he is while it plays is set per frame, in the timeline below — a frame left '
        . 'blank uses this number, and each frame gives again as it comes up. 0 is rigid: no give at '
        . 'all, and the sharpest possible pixels; 1 is a natural amount; 10 is cartoon rubber. Gives '
        . 'that overlap add together, so fast frames dig deeper than slow ones. How LONG each one '
        . 'lasts is Bounce length, under Physics. Watch it live in the preview beside the sheet.'];
}

/**
 * Validate one animation entry coming out of $_POST.
 *
 * Returns null for a row with no art — an entry without a sheet can never play,
 * and silently dropping it is how the "add a row, change your mind" case stays
 * harmless. Frame timings are clamped to something a browser can actually
 * schedule; a frame list longer than the sheet is trimmed by the front end, so
 * over-length input here is not an error.
 */
function fc_mascot_sanitize_entry($raw, array $extras = []): ?array {
    if (!is_array($raw)) return null;

    $png = esc_url_raw(trim((string) ($raw['png'] ?? '')));
    if ($png === '') return null;

    $dirs = fc_mascot_directions();
    $dir  = (string) ($raw['direction'] ?? 'forward');
    if (!isset($dirs[$dir])) $dir = 'forward';

    [$b_default, $b_min, $b_max] = fc_mascot_bounce_field();
    $bounce = is_numeric($raw['bounce'] ?? null)
        ? max($b_min, min($b_max, (float) $raw['bounce']))
        : $b_default;

    $out = [
        'name'      => sanitize_text_field((string) ($raw['name'] ?? '')),
        'png'       => $png,
        'direction' => $dir,
        // Kept as the value a frame falls back to when it has none of its own.
        // It used to be the ONLY bounciness there was; per-frame values now sit
        // beside the durations in the timeline, and this is what an animation
        // saved before that keeps using until its frames are given their own.
        'bounce'    => $bounce,
        'frames'    => fc_mascot_sanitize_frames($raw['frames'] ?? []),
        'bounces'   => fc_mascot_sanitize_bounces($raw['bounces'] ?? [], $bounce),
        'particle'  => fc_mascot_sanitize_particle($raw['particle'] ?? null),
    ];

    // Per-animation extras declared by the state (Random's chance and timing).
    foreach ($extras as $key => $def) {
        $out[$key] = fc_mascot_sanitize_extra($raw[$key] ?? null, $def);
    }

    return $out;
}

/**
 * Per-frame durations.
 *
 * The floor is 16ms because a browser cannot hold a frame for less than one
 * display refresh, so anything below it is a number that silently does nothing.
 * The ceiling is ten minutes, which is well past "a pose he holds".
 */
function fc_mascot_sanitize_frames($raw): array {
    $frames = [];
    foreach ((array) $raw as $ms) {
        $frames[] = max(16, min(600000, (int) $ms));
    }
    return $frames;
}

/**
 * Per-frame bounciness, index-aligned to the frames above.
 *
 * A blank box means "whatever the animation's own bounciness is", NOT zero —
 * zero is rigid, and is a thing somebody might deliberately want on one frame of
 * a walk. So an empty value inherits `$fallback` rather than being clamped to
 * the bottom of the range.
 */
function fc_mascot_sanitize_bounces($raw, float $fallback): array {
    [, $min, $max] = fc_mascot_bounce_field();
    $out = [];
    foreach ((array) $raw as $value) {
        $out[] = is_numeric($value)
            ? max($min, min($max, (float) $value))
            : $fallback;
    }
    return $out;
}

/** One `entry_extra` value, either a clamped number or a whitelisted option. */
function fc_mascot_sanitize_extra($value, array $def) {
    if (($def['type'] ?? 'number') === 'select') {
        $options = (array) ($def['options'] ?? []);
        $v = (string) $value;
        return isset($options[$v]) ? $v : (string) ($def['default'] ?? '');
    }
    if (!is_numeric($value)) return $def['default'] ?? 0;
    $step = $def['step'] ?? 1;
    $n = ($step >= 1) ? (int) round((float) $value) : (float) $value;
    return max($def['min'] ?? 0, min($def['max'] ?? 0, $n));
}

/** Validate one particle block. No art = no particle, stored as defaults. */
function fc_mascot_sanitize_particle($raw): array {
    $out = fc_mascot_particle_defaults();
    if (!is_array($raw)) return $out;

    $png = esc_url_raw(trim((string) ($raw['png'] ?? '')));
    if ($png === '') return $out;
    $out['png'] = $png;

    $motions = fc_mascot_particle_motions();
    $motion  = (string) ($raw['motion'] ?? 'rise');
    $out['motion'] = isset($motions[$motion]) ? $motion : 'rise';

    $out['frames'] = fc_mascot_sanitize_frames($raw['frames'] ?? []);

    foreach (fc_mascot_particle_fields() as $key => $def) {
        [$default, $min, $max, $step] = $def;
        $v = $raw[$key] ?? null;
        if (!is_numeric($v)) { $out[$key] = $default; continue; }
        $n = ($step >= 1) ? (int) round((float) $v) : (float) $v;
        $out[$key] = max($min, min($max, $n));
    }

    return $out;
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE OPTION
   ───────────────────────────────────────────────────────────────────────────*/

/** Everything, at its default. Also the shape a never-saved install runs on. */
function fc_mascot_defaults(): array {
    $states = [];
    foreach (fc_mascot_states() as $key => $def) {
        $rules = [];
        foreach ((array) ($def['rules'] ?? []) as $rkey => $rdef) {
            $rules[$rkey] = $rdef[0];
        }
        $states[$key] = ['rules' => $rules, 'entries' => []];
    }

    return [
        'enabled'           => true,
        'debug'             => false,
        'scale_desktop'     => 3.0,
        'scale_mobile'      => 2.0,
        'mobile_breakpoint' => FC_MASCOT_BREAKPOINT,
        'art_facing'        => 'left',
        'physics'           => fc_mascot_physics_defaults(),
        'states'            => $states,
        'sections'          => [],
    ];
}

/**
 * Saved settings merged over the defaults, one level deep where it matters.
 *
 * Memoised, because a single request asks for this many times. Pass $fresh after
 * writing the option — the admin page saves during its render, which is AFTER
 * admin_enqueue_scripts has already read this, and without the re-read the
 * preview spends that request running the configuration you just replaced.
 */
function fc_mascot_settings(bool $fresh = false): array {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;

    $saved = get_option(FC_MASCOT_OPTION, []);
    if (!is_array($saved)) $saved = [];

    $out = array_merge(fc_mascot_defaults(), $saved);

    // array_merge is shallow, so the three nested branches are merged by hand —
    // otherwise a saved `physics` missing one key would drop that key's default
    // instead of inheriting it.
    $out['physics'] = array_merge(fc_mascot_physics_defaults(), (array) ($saved['physics'] ?? []));

    $states = fc_mascot_defaults()['states'];
    foreach ((array) ($saved['states'] ?? []) as $key => $state) {
        if (!isset($states[$key]) || !is_array($state)) continue;
        $states[$key]['rules']   = array_merge($states[$key]['rules'], (array) ($state['rules'] ?? []));
        $states[$key]['entries'] = array_values(array_filter((array) ($state['entries'] ?? []), 'is_array'));
    }
    $out['states'] = $states;

    $out['sections'] = array_values(array_filter((array) ($saved['sections'] ?? []), 'is_array'));

    $cache = $out;
    return $out;
}

/** Single source of truth for the enabled flag. */
function fc_mascot_is_enabled(): bool {
    $s = fc_mascot_settings();
    return !empty($s['enabled']);
}

/**
 * Sprite scale. Fractional is allowed (1.3, 2.6) so he can be sized exactly.
 *
 * The cost of a fractional value: only whole numbers map one art pixel onto a
 * whole number of screen pixels, so a fraction renders some pixels a step wider
 * than others and the edges read slightly uneven. Rounded to one decimal so a
 * stray float can't produce a 17-digit CSS value.
 */
function fc_mascot_clamp_scale($n): float {
    $f = is_numeric($n) ? (float) $n : 3.0;
    return max(0.5, min(12.0, round($f, 1)));
}

/** Trim a scale for output: 3 rather than 3.0, but 2.6 kept. */
function fc_mascot_scale_str($n): string {
    return rtrim(rtrim(number_format(fc_mascot_clamp_scale($n), 1, '.', ''), '0'), '.');
}

/* ─────────────────────────────────────────────────────────────────────────────
   FRONT-END PAYLOAD
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * The whole mascot, as the browser receives it.
 *
 * States with no entries are still sent: the machine treats a state with no art
 * as "pass straight through", which is what keeps a half-configured mascot
 * working instead of stuck. Physics keys are sent flat and grouped by prefix —
 * config.js splits them back apart.
 */
function fc_mascot_js_config(?array $s = null): array {
    $s = $s ?? fc_mascot_settings();

    $physics = [];
    foreach (array_keys(fc_mascot_physics_defaults()) as $key) {
        $physics[$key] = fc_mascot_clamp_physics($key, $s['physics'][$key] ?? null);
    }

    $states = [];
    foreach (fc_mascot_states() as $key => $def) {
        $stored  = $s['states'][$key] ?? [];
        $rules   = (array) ($stored['rules'] ?? []);
        $extras  = (array) ($def['entry_extra'] ?? []);
        $entries = [];
        foreach ((array) ($stored['entries'] ?? []) as $entry) {
            $clean = fc_mascot_sanitize_entry($entry, $extras);
            if ($clean !== null) $entries[] = $clean;
        }
        $states[$key] = [
            'rules'   => $rules,
            'entries' => $entries,
        ];
    }

    $sections = [];
    foreach ($s['sections'] as $row) {
        $selector = trim((string) ($row['selector'] ?? ''));
        $entry    = fc_mascot_sanitize_entry($row);
        if ($selector === '' || $entry === null) continue;
        $sections[] = array_merge($entry, [
            'selector' => $selector,
            'loops'    => max(1, min(50, (int) ($row['loops'] ?? 1))),
        ]);
    }

    return [
        'debug'            => !empty($s['debug']),
        'scale'            => fc_mascot_clamp_scale($s['scale_desktop']),
        'scaleMobile'      => fc_mascot_clamp_scale($s['scale_mobile']),
        'mobileBreakpoint' => max(0, min(3000, (int) $s['mobile_breakpoint'])),
        'artFacing'        => ($s['art_facing'] ?? 'left') === 'right' ? 'right' : 'left',
        'physics'          => $physics,
        'states'           => $states,
        'sections'         => $sections,
    ];
}

/* ─────────────────────────────────────────────────────────────────────────────
   ENQUEUE + MOUNT
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * ES modules, so each concern is a real file with real imports.
 *
 * WordPress has no first-class module enqueue on every supported version, so the
 * tag is rewritten by filter. Only OUR handle is touched — main.js is the single
 * entry point and pulls the rest in by relative path, which never reaches
 * wp_enqueue_script at all.
 */
add_action('wp_enqueue_scripts', 'fc_mascot_enqueue');
function fc_mascot_enqueue() {
    if (!fc_mascot_is_enabled()) return;

    wp_enqueue_style('fc-mascot', FC_THEME_URI . '/assets/mascot/mascot.css', [], FC_THEME_VERSION);
    // boot.js, not main.js: main.js is the factory both the front end and the
    // admin preview import, and only this one starts a mascot by itself.
    wp_enqueue_script('fc-mascot', FC_THEME_URI . '/assets/mascot/js/boot.js', [], FC_THEME_VERSION, true);
    wp_localize_script('fc-mascot', 'FC_MASCOT', fc_mascot_js_config());
}

/**
 * Cache-bust the module GRAPH, which nothing else can reach.
 *
 * boot.js is enqueued, so WordPress puts a `?ver=` on it — but it imports
 * `./main.js`, which imports `./physics.js`, and a relative import inherits
 * neither the query string nor WordPress's involvement. Those files are fetched
 * at a URL that has never changed in the theme's life, so a browser that saw
 * them once keeps serving its copy forever. Editing physics.js and reloading did
 * nothing on a phone; that is not a stale cache to be cleared, it is a URL with
 * no version in it.
 *
 * An import map fixes it at the resolver: a bare `./physics.js` still appears in
 * the source, and the map rewrites it to the same file plus this build's mtime.
 * Written from a glob so a module added later is covered without anyone
 * remembering this exists.
 *
 * Emitted at priority 1 — an import map must precede the first module script,
 * and ours is in the footer. Safari below 16.4 ignores the map entirely and
 * simply gets the unversioned URLs it gets today: no worse, never broken.
 */
add_action('wp_head', 'fc_mascot_import_map', 1);
add_action('admin_head', 'fc_mascot_import_map', 1);
function fc_mascot_import_map() {
    static $done = false;
    if ($done) return;

    $dir = FC_THEME_DIR . '/assets/mascot/js';
    $uri = FC_THEME_URI . '/assets/mascot/js';
    $files = glob($dir . '/*.js');
    if (!$files) return;

    $imports = [];
    foreach ($files as $file) {
        $url = $uri . '/' . basename($file);
        $imports[$url] = add_query_arg('ver', (string) filemtime($file), $url);
    }

    $done = true;
    printf(
        '<script type="importmap">%s</script>' . "\n",
        wp_json_encode(['imports' => $imports])
    );
}

add_filter('script_loader_tag', 'fc_mascot_module_tag', 10, 3);
function fc_mascot_module_tag($tag, $handle, $src) {
    if ($handle !== 'fc-mascot') return $tag;
    // wp_localize_script prints its own <script> before this one; only the tag
    // carrying our src becomes a module, so the FC_MASCOT global stays classic
    // and is therefore already defined by the time the module evaluates.
    return '<script type="module" src="' . esc_url($src) . '" id="fc-mascot-js"></script>' . "\n";
}

/**
 * The mount point.
 *
 * Empty on purpose — view.js builds everything inside it. The scale rides along
 * as an attribute so the very first paint is already the right size; there is no
 * flash from that because the sprite has no background image until a state is
 * entered, well after the scale has settled.
 */
add_action('wp_footer', 'fc_mascot_mount', 99);
function fc_mascot_mount() {
    if (!fc_mascot_is_enabled()) return;

    $s = fc_mascot_settings();
    printf(
        '<div id="fosscomm-mascot" data-scale="%s" aria-hidden="true"></div>',
        esc_attr(fc_mascot_scale_str($s['scale_desktop']))
    );
}
