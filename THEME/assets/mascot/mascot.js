/* =========================================================================
   FOSSCOMM pixel mascot — sticky companion + dev-only debug panel.

   Vanilla IIFE, no build step (matches assets/wave-bg.js style). Sprite URLs
   arrive from PHP via window.FC_MASCOT (wp_localize_script in mascot.php).

   ── RUNTIME CONFIG ───────────────────────────────────────────────────────
   Behaviour is no longer hardcoded here. FC_MASCOT.config arrives from PHP
   (fc_mascot_js_config() in mascot.php, sourced from the `fc_mascot_settings`
   option) and carries: debug, scale, corner, startState, sponsorsSurprise,
   surpriseTarget, autoSleep, keyboardWalk. Edit those from
   wp-admin → FOSSCOMM → Mascot (the companion plugin), not from this file.
   Every read below falls back to a sane default, so the script still runs
   standalone if the config is absent.

   ── DEV DEBUG PANEL ──────────────────────────────────────────────────────
   The entire panel and its listeners are gated behind DEBUG.
     • OFF (production default): config.debug false and no ?mascotdebug=1 in
       the URL  →  buildDebugPanel() returns immediately: no panel DOM, no
       debug listeners. The mascot itself still runs normally.
     • ON: tick "Show debug panel" in wp-admin, OR append ?mascotdebug=1 to
       any page URL — a permanent escape hatch that works regardless of the
       saved setting, so you can always get the panel back on a live site.
   ========================================================================= */
(function () {
    'use strict';

    // FC_MASCOT is printed by wp_localize_script ABOVE this script tag, so it is
    // readable at IIFE scope. (The mount <div> is not — see the note below.)
    var CFG = (window.FC_MASCOT && window.FC_MASCOT.config) || {};
    var DEBUG = !!CFG.debug || /[?&]mascotdebug=1(?:&|$)/.test(window.location.search);

    // Looked up in start() (on DOMContentLoaded) — the mount <div> is printed on
    // wp_footer at priority 99, AFTER the footer scripts (priority 20), so it does
    // not exist yet while this plain (non-module) script first executes.
    var mount = null;
    var CATALOG = null;

    // State registry. Adding happy/celebrate later = one row here + a matching
    // { png, json } entry in mascot.php. No other changes needed.
    //
    // `surprise` is declared unconditionally, but mascot.php only ships its URLs
    // once the art is on disk — loadAll() skips any state with no catalog entry,
    // so this row is harmless while the sheet is still being drawn.
    // ── Drag / throw physics tuning ─────────────────────────────────────────
    // Declared ABOVE STATE_DEF because DIZZY_MS is read while that object literal
    // is built — `var` hoisting would otherwise hand it undefined and the dizzy
    // pose would hold forever.
    var SWING_MAX = 32;          // deg, hardest tilt he'll reach
    var SWING_PER_VX = 0.035;    // deg per px/s of pointer speed
    var SWING_STIFFNESS = 90;    // spring constant (1/s²) — ~0.66s swing period
    var SWING_DAMPING = 9;       // well under 2*sqrt(90)≈19, so it oscillates
    var GRAVITY = 2600;          // px/s²
    var THROW_MAX = 1800;        // px/s, clamp on release velocity
    var BOUNCE = 0.32;           // vertical restitution on impact
    var WALL_BOUNCE = 0.45;      // horizontal restitution off the sides
    var LAND_VY_MIN = 90;        // px/s below which he stops bouncing and settles
    var SQUASH_MAX = 0.28;       // peak squash at a fast landing
    var FLOOR_PAD = 8;           // px gap kept under his feet
    var DRAG_THRESHOLD = 4;      // px of travel before a press counts as a drag
    var DIZZY_MS = 2200;         // how long the dizzy pose + stars hold
    var DIZZY_MIN_IMPACT = 300;  // px/s; gentler landings skip dizzy entirely
    var DIZZY_MIN_HEIGHT = 0.75; // must be dropped from the top 75% of the viewport
    var STAR_COUNT = 3;
    var STAR_ORBIT_MS = 1200;    // must match the fcm-star-orbit duration in mascot.css
    var SURPRISE_MS = 6000;      // the shipped surprise art is a single frame, so it
                                 // needs a timer to return to idle (see STATE_DEF)

    // ── Get-out-of-the-way dash ─────────────────────────────────────────────
    var FLEE_TRIGGER_FRAC = 0.70; // only runs if he's left of this much of the viewport
    var FLEE_TARGET_FRAC = 0.15;  // where he ends up: this far in from the right edge
    var FLEE_SPEED = 0.34;        // px/ms (~340px/s) — a dash, not his usual amble

    // ── Hover speech bubble ─────────────────────────────────────────────────
    var BUBBLE_SHOW_MS = 3000;      // how long it stays up
    var BUBBLE_COOLDOWN_MS = 20000; // earliest it may reappear after being shown.
                                    // Appearance itself is still instant — this only
                                    // rate-limits how often, so sweeping the cursor
                                    // over him repeatedly doesn't re-trigger it.

    // `hold` (ms) is for SINGLE-FRAME poses that should still time out. A one-frame
    // clip gets dur: 1e6 and never "completes", so the onComplete chain that drives
    // before_sleep → sleeping can never fire for it. Correct for `sleeping` (holds
    // until woken); wrong for `dizzy`, which needs a timer instead.
    var STATE_DEF = {
        idle:         { loop: true },
        before_sleep: { loop: false, next: 'sleeping' }, // one-shot → sleeping
        sleeping:     { loop: false, single: true },     // single held frame, no timeout
        walk:         { loop: true },
        // `hold` as well as `next`: the shipped surprise art is a SINGLE frame, and a
        // one-frame clip never completes, so without the timer he'd freeze on the
        // surprised face forever. `next` still covers it if a multi-frame strip is
        // ever drawn — whichever fires first wins, and setState guards re-entry.
        surprise:     { loop: false, next: 'idle', hold: SURPRISE_MS },
        held:         { loop: false, single: true },     // while grabbed
        falling:      { loop: false, single: true },     // while airborne
        dizzy:        { loop: false, single: true, hold: DIZZY_MS, next: 'idle' }
    };

    var IDLE_MS = 20000;       // inactivity before the auto-sleep sequence
    var IDLE_RETRY_MS = 1000;  // re-check gap when that lands while he's mid-move
    var WALK_SPEED = 0.1;      // px per ms (100px/s) for the debug walk-button hop
    var KEY_WALK_SPEED = 0.18; // px per ms (~180px/s) for hold-to-move arrow keys

    // Idle wander — the "he's alive" shuffle. Tuned to read as a small shift of
    // weight, not travel: a step is roughly one to two body-widths at 3× scale,
    // taken at half walking pace, several seconds apart.
    var WANDER_MIN_MS = 4500;  // shortest gap between steps
    var WANDER_MAX_MS = 11000; // longest gap between steps
    var WANDER_STEP_MIN = 10;  // px, shortest step
    var WANDER_STEP_MAX = 28;  // px, longest step
    var WANDER_RANGE = 48;     // px, max drift either side of where he started
    var WANDER_SPEED = 0.05;   // px per ms (50px/s) — an amble, not the walk hop
    // Section to watch for the scroll-into-view surprise. #sponsors is the id
    // emitted by fc_section_open() (inc/sections/render.php) from the registry key.
    var SURPRISE_TARGET = CFG.surpriseTarget || '#sponsors';

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ── DOM ────────────────────────────────────────────────────────────────
    // No dismiss button: he is part of the page furniture, not a banner to close.
    // dismiss() below still exists as a programmatic escape hatch via fcMascot.
    var mover, sprite, zzz, stars;

    function buildDom() {
        mover = document.createElement('div');
        mover.className = 'fcm-mover';

        sprite = document.createElement('div');
        sprite.className = 'fcm-sprite';

        zzz = document.createElement('div');
        zzz.className = 'fcm-zzz'; // spawn anchor; individual .fcm-z's are added while sleeping

        stars = document.createElement('div');
        stars.className = 'fcm-stars'; // orbit anchor above his head, used while dizzy

        mover.appendChild(sprite);
        mover.appendChild(zzz);
        mover.appendChild(stars);
        mount.appendChild(mover);
    }

    // ── Sprite loading (LibreSprite "Hash" format) ──────────────────────────
    var clips = {}; // name -> { png, cells, frames:[{cell,dur}], loop }

    function preloadImg(url) {
        if (!url) return;
        var img = new Image();
        img.src = url;
    }

    function parseSheet(name, png, json) {
        // frames is an OBJECT keyed by "<name> <i>.ase" — iterate in order.
        var arr = Object.keys(json.frames).map(function (k) { return json.frames[k]; });
        var frames = arr.map(function (f) {
            return {
                cell: Math.round(f.frame.x / f.frame.w),   // horizontal strip, w=32
                dur: Math.max(16, f.duration || 100)       // honour EACH frame's own duration
            };
        });
        var cells = (json.meta && json.meta.size)
            ? Math.round(json.meta.size.w / 32)
            : frames.length;
        return {
            png: png,
            cells: cells,
            frames: frames,
            loop: !!(STATE_DEF[name] && STATE_DEF[name].loop)
        };
    }

    function loadAll() {
        var names = Object.keys(STATE_DEF);
        return Promise.all(names.map(function (name) {
            var cat = CATALOG[name];
            if (!cat) return Promise.resolve();
            preloadImg(cat.png);
            if (!cat.json) {
                // Single held frame (sleeping) — no atlas, synthesise one frame.
                clips[name] = { png: cat.png, cells: 1, frames: [{ cell: 0, dur: 1e6 }], loop: false };
                return Promise.resolve();
            }
            return fetch(cat.json)
                .then(function (r) { return r.json(); })
                .then(function (json) { clips[name] = parseSheet(name, cat.png, json); });
        }));
    }

    // ── Player: one rAF loop, honours per-frame durations, pauses when hidden ─
    var state = null;
    var clip = null;      // { frames, loop, done, onComplete }
    var holdTimer = null; // times out single-frame poses that shouldn't hold forever
    var frameIdx = 0;
    var acc = 0;
    var lastTs = 0;

    function renderFrame() {
        if (clip) sprite.style.setProperty('--fcm-frame', clip.frames[frameIdx].cell);
    }

    function tick(ts) {
        requestAnimationFrame(tick);
        if (!clip) { lastTs = ts; return; }
        if (document.hidden) { lastTs = ts; return; }        // pause on hidden tab
        if (!lastTs) lastTs = ts;
        var dt = ts - lastTs;
        lastTs = ts;

        // Physics MUST run before the early-return below: held/falling/dizzy are all
        // single-frame clips, so `frames.length <= 1` would skip the exact states the
        // physics exists for. Seconds, clamped so a frame hitch can't tunnel him
        // through the floor.
        updatePhysics(Math.min(dt, 33) / 1000);

        if (reducedMotion || clip.frames.length <= 1 || clip.done) return; // static hold

        acc += dt;
        var guard = 0;
        while (acc >= clip.frames[frameIdx].dur && guard++ < 480) {
            acc -= clip.frames[frameIdx].dur;
            if (frameIdx + 1 >= clip.frames.length) {
                if (clip.loop) {
                    frameIdx = 0;
                    renderFrame();
                } else {
                    clip.done = true;               // hold on the last frame
                    var cb = clip.onComplete;
                    clip.onComplete = null;         // fire once
                    if (cb) cb();
                    break;
                }
            } else {
                frameIdx++;
                renderFrame();
            }
        }
    }

    // ── State machine — the single entry point buttons AND triggers both call ─
    function setState(name) {
        var def = clips[name];
        if (!def) return;
        state = name;
        mount.dataset.state = name;

        sprite.style.backgroundImage = 'url("' + def.png + '")';
        sprite.style.setProperty('--fcm-sheet-cells', def.cells);

        clip = { frames: def.frames, loop: def.loop, done: false, onComplete: null };
        frameIdx = 0;
        acc = 0;
        lastTs = 0;

        var sdef = STATE_DEF[name];
        if (sdef && sdef.next && !def.loop) {
            clip.onComplete = function () {
                setState(sdef.next);
                // Landing back in idle off a one-shot (surprise → idle) must restart
                // the sleep countdown; otherwise he can sit awake forever after a
                // trigger that fired without any accompanying scroll activity.
                if (sdef.next === 'idle') armIdleTimer();
            };
        }

        // Timed single-frame poses (dizzy, and surprise as currently drawn).
        // onComplete above can never fire for a one-frame clip, so these need a
        // wall-clock timer instead. Restricted to one-frame clips on purpose: if a
        // multi-frame strip is drawn later it ends itself via onComplete, and this
        // timer would otherwise cut the animation short.
        clearTimeout(holdTimer);
        if (sdef && sdef.hold && sdef.next && def.frames.length <= 1) {
            holdTimer = setTimeout(function () {
                if (state !== name) return;   // something else took over meanwhile
                setState(sdef.next);
                if (sdef.next === 'idle') armIdleTimer();
            }, sdef.hold);
        }

        if (name === 'sleeping') {
            crossfade();  // hide the offset-pose jump
            startZzz();   // begin emitting sleepy z's
            hideBubble(); // he has nothing to say while asleep
        } else {
            stopZzz();
        }

        if (name === 'dizzy') startStars(); else stopStars();

        renderFrame();
    }

    function crossfade() {
        sprite.style.opacity = '0';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { sprite.style.opacity = '1'; });
        });
    }

    // ── Sleepy "z"s: emit one at a time from inside him; each drifts up to the
    //    top-LEFT with the classic swaying wave (CSS keyframes), then self-removes.
    var zzzTimer = null;
    function startZzz() {
        stopZzz();
        if (reducedMotion) return; // no drifting z's under reduced motion
        spawnZ();
        zzzTimer = setInterval(spawnZ, 700);
    }
    function stopZzz() {
        if (zzzTimer) { clearInterval(zzzTimer); zzzTimer = null; }
        // Clear any z's still in flight so they vanish the instant he wakes.
        while (zzz && zzz.firstChild) zzz.removeChild(zzz.firstChild);
    }
    function spawnZ() {
        var z = document.createElement('span');
        z.className = 'fcm-z';
        z.textContent = 'z';
        z.addEventListener('animationend', function () {
            if (z.parentNode) z.parentNode.removeChild(z);
        });
        zzz.appendChild(z);
    }

    // ── Dizzy stars: the same trick as the z's, but they orbit instead of drifting
    //    away, so they're spawned once on entering `dizzy` and cleared on leaving.
    //    Each star runs the same keyframes with a negative delay, which starts it
    //    already part-way round the ring — three stars evenly spaced, no wrapper to
    //    spin and no tumbling glyphs.
    function startStars() {
        stopStars();
        if (reducedMotion) return; // no orbiting stars under reduced motion
        for (var i = 0; i < STAR_COUNT; i++) {
            var s = document.createElement('span');
            s.className = 'fcm-star';
            s.textContent = '✦';
            s.style.animationDelay = (-(i / STAR_COUNT) * STAR_ORBIT_MS) + 'ms';
            stars.appendChild(s);
        }
    }
    function stopStars() {
        while (stars && stars.firstChild) stars.removeChild(stars.firstChild);
    }

    // ── Position / facing (walk travel) ─────────────────────────────────────
    var currentMove = 0;
    var walkTimer = null;

    function resetPosition() {
        // Also cancel any in-progress arrow-key walk so he truly stops + snaps home.
        keyWalk.left = keyWalk.right = false;
        if (keyWalk.raf) { cancelAnimationFrame(keyWalk.raf); keyWalk.raf = null; }
        currentMove = 0;
        mover.style.setProperty('--fcm-walk-dur', '0ms');
        mover.style.setProperty('--fcm-move', '0px');
        sprite.style.setProperty('--fcm-face', 1);
    }

    // Clamp a proposed horizontal move so the sprite never leaves the viewport.
    // Measures the sprite's live rect, so it works for any corner/scale/margin.
    function clampMoveDelta(delta) {
        var rect = sprite.getBoundingClientRect();
        var vw = window.innerWidth || document.documentElement.clientWidth;
        var pad = 2;
        if (delta > 0) return Math.max(0, Math.min(delta, (vw - pad) - rect.right));
        if (delta < 0) return Math.min(0, Math.max(delta, -(rect.left - pad)));
        return 0;
    }

    // Walk callable method + data-attribute (dir defaults to data-walk on the mount).
    //
    // `ambient` marks a move he made on his own (the idle wander below) rather
    // than one the user asked for. Ambient moves deliberately do NOT restart the
    // sleep countdown — otherwise he'd wander every few seconds forever and never
    // doze off, which is the opposite of looking alive.
    function walk(dir, dist, ambient, speed) {
        dir = dir || mount.dataset.walk || 'right';
        dist = dist || 200;
        var sign = (dir === 'right') ? 1 : -1;

        setState('walk');
        // Default art faces LEFT; flip (scaleX -1) to face travel when going right.
        sprite.style.setProperty('--fcm-face', sign > 0 ? -1 : 1);

        var delta = clampMoveDelta(sign * dist);       // keep him inside the window
        var duration = Math.round(Math.abs(delta) / (speed || (ambient ? WANDER_SPEED : WALK_SPEED)));
        currentMove += delta;
        mover.style.setProperty('--fcm-walk-dur', duration + 'ms');
        requestAnimationFrame(function () {
            mover.style.setProperty('--fcm-move', currentMove + 'px');
        });

        clearTimeout(walkTimer);
        walkTimer = setTimeout(function () {
            fleeing = false;
            setState('idle');   // settle to idle; holds at the displaced offset
            if (!ambient) armIdleTimer();
        }, duration + 40);
    }

    // ── Get out of the way when the user starts reading ─────────────────────
    // Scrolling means they want the text, not him. If he's sitting over the left
    // 70% of the viewport he dashes right and parks 15% in from the edge — clear of
    // the column but still visible. Already right of the trigger line? He stays put,
    // so this doesn't fire on every scroll of every page.
    var fleeing = false;

    function maybeFleeRight() {
        if (CFG.scrollFlee === false || dismissed || reducedMotion) return;
        if (fleeing || drag.active || airborne) return;
        if (state === 'walk' || state === 'held' || state === 'falling') return;
        // Reaching #sponsors means scrolling, and scrolling is exactly what triggers
        // this dash — without these two he'd walk out of his own surprise the very
        // frame it started, and the reaction would never be seen.
        if (state === 'surprise' || state === 'dizzy') return;
        if (keyWalk.left || keyWalk.right) return;   // they're driving him themselves

        var rect = sprite.getBoundingClientRect();
        var vw = window.innerWidth || document.documentElement.clientWidth;
        if (!vw || !rect.width) return;

        // Judge by his centre so a wide sprite near the line behaves predictably.
        if ((rect.left + rect.width / 2) > vw * FLEE_TRIGGER_FRAC) return;

        var delta = (vw - vw * FLEE_TARGET_FRAC) - rect.right;
        if (delta < 8) return;                        // already close enough

        fleeing = true;
        walk('right', delta, false, FLEE_SPEED);
    }

    // ── Idle wander ─────────────────────────────────────────────────────────
    // Small, occasional steps so he reads as alive rather than as a static
    // sticker — a couple of paces every several seconds, never a trek across the
    // page. Drift is bounded to ±WANDER_RANGE around wherever he started, so he
    // always stays in his corner.
    var wanderTimer = null;

    function nextWanderDelay() {
        return WANDER_MIN_MS + Math.random() * (WANDER_MAX_MS - WANDER_MIN_MS);
    }

    function scheduleWander() {
        clearTimeout(wanderTimer);
        if (CFG.wander === false) return;   // switched off in wp-admin
        if (reducedMotion) return;          // no unprompted motion under reduced-motion
        wanderTimer = setTimeout(wanderStep, nextWanderDelay());
    }

    function stopWander() {
        clearTimeout(wanderTimer);
        wanderTimer = null;
    }

    // How much further he may drift in `dir` before hitting the leash.
    function wanderRoom(dir) {
        return (dir === 'right') ? (WANDER_RANGE - currentMove)
                                 : (WANDER_RANGE + currentMove);
    }

    function wanderStep() {
        // Only step when he is genuinely idle and nothing else is driving him.
        // Any other state (asleep, walking, surprised) just waits for the next tick,
        // so the wander never interrupts something the user is watching.
        if (dismissed || state !== 'idle' || document.hidden ||
            keyWalk.left || keyWalk.right) {
            scheduleWander();
            return;
        }

        // Pick a direction, then turn around if that side is out of room. Trimming
        // the step to the remaining room (rather than just forcing a direction at
        // the boundary) is what keeps drift hard-bounded to ±WANDER_RANGE — with a
        // fixed step he could overshoot by up to a full stride.
        var dir = (Math.random() < 0.5) ? 'left' : 'right';
        if (wanderRoom(dir) < WANDER_STEP_MIN) dir = (dir === 'left') ? 'right' : 'left';

        var dist = WANDER_STEP_MIN + Math.round(Math.random() * (WANDER_STEP_MAX - WANDER_STEP_MIN));
        dist = Math.min(dist, wanderRoom(dir));

        if (dist >= 1) walk(dir, dist, true); // ambient: doesn't touch the sleep countdown
        scheduleWander();
    }

    // ── Arrow-key hold-to-move ──────────────────────────────────────────────
    // Left/Right arrows walk him continuously while held; on release he settles
    // to idle where he stopped. Driven by its own rAF so translation tracks the
    // key 1:1 (no CSS easing) while the walk cycle loops via the normal player.
    var keyWalk = { left: false, right: false, raf: null, last: 0 };

    function isTypingTarget(t) {
        if (!t) return false;
        var tag = t.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || t.isContentEditable;
    }

    function onKeyDown(e) {
        var dir = e.key === 'ArrowLeft' ? 'left' : (e.key === 'ArrowRight' ? 'right' : null);
        if (!dir || dismissed || isTypingTarget(e.target)) return;
        e.preventDefault();            // don't let arrows scroll the page
        keyWalk[dir] = true;
        startKeyWalk();
    }

    function onKeyUp(e) {
        if (e.key === 'ArrowLeft') keyWalk.left = false;
        else if (e.key === 'ArrowRight') keyWalk.right = false;
        else return;
        if (!keyWalk.left && !keyWalk.right) stopKeyWalk();
    }

    function startKeyWalk() {
        if (keyWalk.raf) return;       // already stepping
        clearTimeout(walkTimer);       // cancel any debug-walk settle
        if (state !== 'walk') setState('walk'); // also wakes him from sleep
        keyWalk.last = 0;
        mover.style.setProperty('--fcm-walk-dur', '0ms'); // instant tracking
        keyWalk.raf = requestAnimationFrame(keyWalkTick);
    }

    function keyWalkTick(ts) {
        if (!keyWalk.left && !keyWalk.right) { keyWalk.raf = null; return; }
        if (!keyWalk.last) keyWalk.last = ts;
        var dt = ts - keyWalk.last;
        keyWalk.last = ts;
        var dir = (keyWalk.right ? 1 : 0) - (keyWalk.left ? 1 : 0);
        if (dir !== 0) {
            // Art faces LEFT by default; flip (scaleX -1) to face travel going right.
            sprite.style.setProperty('--fcm-face', dir > 0 ? -1 : 1);
            currentMove += clampMoveDelta(dir * KEY_WALK_SPEED * dt); // stop at window edge
            mover.style.setProperty('--fcm-move', currentMove + 'px');
        }
        keyWalk.raf = requestAnimationFrame(keyWalkTick);
    }

    function stopKeyWalk() {
        if (keyWalk.raf) { cancelAnimationFrame(keyWalk.raf); keyWalk.raf = null; }
        keyWalk.last = 0;
        if (state === 'walk') {        // settle where he stopped, keep facing
            setState('idle');
            armIdleTimer();
        }
    }

    // ── Sleep / wake ────────────────────────────────────────────────────────
    var idleTimer = null;

    function armIdleTimer(delay) {
        clearTimeout(idleTimer);
        if (CFG.autoSleep === false) return;     // auto-sleep switched off in wp-admin
        if (reducedMotion) return;               // reduced motion never auto-sleeps
        idleTimer = setTimeout(onIdleTimeout, delay || IDLE_MS);
    }

    function onIdleTimeout() {
        // He may be busy when the countdown lands — most often mid ambient step,
        // since those deliberately don't restart it. Look again shortly instead of
        // dropping the countdown, which would leave him awake forever.
        if (state !== 'idle') { armIdleTimer(IDLE_RETRY_MS); return; }
        goToSleep();
    }

    function goToSleep() {
        clearTimeout(walkTimer);
        if (reducedMotion) { setState('sleeping'); return; }
        setState('before_sleep'); // one-shot → chains to sleeping via onComplete
    }

    function toIdle() {
        clearTimeout(walkTimer);
        resetToHome();   // deliberate "put him back": clears walk travel AND any drop
        setState('idle');
        armIdleTimer();
    }

    function onScroll() {
        onActivity();
        maybeFleeRight();   // after onActivity, so a sleeping mascot wakes before dashing
    }

    function onActivity() {
        if (drag.active || airborne) return;   // he's busy being thrown about
        if (state === 'sleeping' || state === 'before_sleep') {
            // NB: resetPosition() clears walk travel but deliberately NOT the drag
            // offset — waking must not teleport him back out of wherever he was
            // dropped. resetToHome() is the one that clears both.
            resetPosition();
            setState('idle');
        }
        armIdleTimer();
    }

    function onSpriteClick() {
        // A drag ends with a click event too; swallow that one so dropping him
        // doesn't also count as a poke.
        if (drag.didDrag) { drag.didDrag = false; return; }
        onActivity();
    }

    function bindActivity() {
        // He wakes / stays awake ONLY on a scroll, or a click on the mascot himself.
        // (Plain mouse movement / typing no longer disturbs him.)
        window.addEventListener('scroll', onScroll, { passive: true });
        sprite.addEventListener('click', onSpriteClick);
        if (CFG.drag !== false) bindDrag();
        if (CFG.bubble !== false) bindBubble();
        // Left/Right arrows walk him (also wakes him). Non-passive: we preventDefault.
        // Opt-out exists because the admin preview embeds him inside a page whose
        // own arrow-key scrolling must keep working.
        if (CFG.keyboardWalk !== false) {
            window.addEventListener('keydown', onKeyDown);
            window.addEventListener('keyup', onKeyUp);
        }
    }

    // ── Dismiss (session only — NO localStorage) ────────────────────────────
    var dismissed = false;
    function dismiss() {
        dismissed = true;
        clearTimeout(idleTimer);
        clearTimeout(walkTimer);
        clearTimeout(holdTimer);
        stopWander();
        stopZzz();
        stopStars();
        hideBubble();
        mount.style.display = 'none';
    }

    // ── Grab, swing, throw ──────────────────────────────────────────────────
    // Pick him up and he hangs from the grab point: the body trails your hand,
    // overshoots, and swings. Let go and he's a projectile — gravity, a bounce,
    // then a squash on landing and a few dizzy seconds.
    //
    // The swing is NOT sprite animation. It's a rotate() about a pivot at his
    // scruff, re-solved every frame from pointer velocity, so it responds
    // continuously to how fast you move rather than snapping between poses.
    var drag = {
        active: false,     // pointer is down and past the movement threshold
        pointerId: null,
        grabX: 0, grabY: 0,   // pointer position at grab, in drag-space
        startX: 0, startY: 0, // for the click-vs-drag threshold
        moved: false,
        didDrag: false     // consumed by the click handler to suppress a stray wake
    };

    var dragX = 0, dragY = 0;      // current offset from his anchored home
    var velX = 0, velY = 0;        // px/s — pointer velocity while held, then throw velocity
    var swing = 0, swingVel = 0;   // degrees / degrees-per-second
    var squash = 0;                // 0 = round, → SQUASH_MAX at a hard landing
    var airborne = false;
    // Hardest floor contact of the current flight. He settles only once a bounce
    // drops below LAND_VY_MIN, so the impact at the FINAL contact is always tiny —
    // judging "was that a heavy landing?" by it would mean never dizzy, never a
    // real squash. The peak is what the fall actually felt like.
    var peakImpact = 0;
    // Where in the viewport he was let go, 0 = top, 1 = bottom. Dropping him from
    // down near the floor shouldn't leave him seeing stars however hard he hits.
    var releaseFrac = 0;

    function applyDrag() {
        mover.style.setProperty('--fcm-drag-x', dragX + 'px');
        mover.style.setProperty('--fcm-drag-y', dragY + 'px');
    }
    function applySwing() {
        mover.style.setProperty('--fcm-swing', swing.toFixed(2) + 'deg');
    }
    function applySquash() {
        // Volume-preserving-ish: wider as he flattens.
        sprite.style.setProperty('--fcm-squash-y', (1 - squash).toFixed(3));
        sprite.style.setProperty('--fcm-squash-x', (1 + squash * 0.6).toFixed(3));
    }

    // The --fcm-drag-* values at which he meets each edge.
    //
    // Measured off the MOUNT, not the sprite, for two reasons. The mount carries no
    // transform — drag and swing both live on .fcm-mover inside it — so its rect is
    // his anchored home position regardless of where he's been thrown, and it stays
    // valid even though the physics updates dragX/dragY before applyDrag() has
    // written them to the DOM. Reading the sprite instead would be stale by one
    // frame AND inflated by rotation: getBoundingClientRect returns the
    // axis-aligned box of the ROTATED sprite, up to ~38% taller at full swing, so
    // he'd "land" well above the floor whenever he was swinging.
    //
    // Corner, scale, margin and safe-area insets all fall out of the mount rect, so
    // none of them need special-casing. --fcm-move (walk travel) shares the mover's
    // translate with the drag offset, hence the currentMove terms.
    function bounds() {
        var m = mount.getBoundingClientRect();
        var vw = window.innerWidth || document.documentElement.clientWidth;
        var vh = window.innerHeight || document.documentElement.clientHeight;
        return {
            left:   FLOOR_PAD - m.left - currentMove,
            right:  (vw - FLOOR_PAD) - m.right - currentMove,
            top:    FLOOR_PAD - m.top,
            bottom: (vh - FLOOR_PAD) - m.bottom
        };
    }

    // Called from tick() every frame, BEFORE the frame-stepping early-return —
    // all three drag poses are single-frame clips, which that return skips.
    function updatePhysics(dt) {
        if (!drag.active && !airborne && swing === 0 && squash === 0) return;

        if (airborne) {
            velY += GRAVITY * dt;
            dragX += velX * dt;
            dragY += velY * dt;

            var b = bounds();   // one layout read per frame
            if (dragX < b.left) { dragX = b.left; velX = -velX * WALL_BOUNCE; }
            else if (dragX > b.right) { dragX = b.right; velX = -velX * WALL_BOUNCE; }

            if (dragY < b.top) { dragY = b.top; velY = Math.abs(velY) * WALL_BOUNCE; }

            if (dragY >= b.bottom) {
                dragY = b.bottom;
                var impact = Math.abs(velY);
                if (impact > peakImpact) peakImpact = impact;
                if (impact > LAND_VY_MIN) {
                    velY = -impact * BOUNCE;          // bounce, stay airborne
                    velX *= 0.8;                       // friction on contact
                    squash = Math.min(SQUASH_MAX, impact / 4000);
                } else {
                    land();
                }
            }
            applyDrag();
        }

        // Swing: damped spring toward an angle proportional to horizontal speed.
        // Underdamped on purpose — the overshoot IS the effect.
        var target = 0;
        if (drag.active) {
            // Pointer velocity is only refreshed by pointermove, so a hand that
            // stops moving would otherwise leave him hanging at the last angle
            // forever. Decay it here so he settles upright while still held.
            var decay = Math.min(1, dt * 6);
            velX -= velX * decay;
            velY -= velY * decay;

            target = -velX * SWING_PER_VX;
            if (target > SWING_MAX) target = SWING_MAX;
            else if (target < -SWING_MAX) target = -SWING_MAX;
        }
        swingVel += ((target - swing) * SWING_STIFFNESS - swingVel * SWING_DAMPING) * dt;
        swing += swingVel * dt;
        if (!drag.active && !airborne && Math.abs(swing) < 0.05 && Math.abs(swingVel) < 0.5) {
            swing = 0; swingVel = 0;   // settled — stop writing the property
        }
        applySwing();

        if (squash > 0) {
            squash -= dt * 2.2;
            if (squash < 0) squash = 0;
            applySquash();
        }
    }

    function land() {
        airborne = false;
        velX = 0; velY = 0;

        var peak = peakImpact;   // how hard the fall actually was, not the last tap
        peakImpact = 0;

        squash = Math.min(SQUASH_MAX, Math.max(0.08, peak / 4000));
        applySquash();
        // Two gates: it has to have been a real drop from a real height. A gentle
        // lift-and-set-down fails the first; letting go just above the floor fails
        // the second, however fast he was flicked downward.
        var fellFarEnough = releaseFrac <= DIZZY_MIN_HEIGHT;
        if (peak >= DIZZY_MIN_IMPACT && fellFarEnough && clips.dizzy && !reducedMotion) {
            setState('dizzy');           // its `hold` chains back to idle + re-arms sleep
        } else {
            setState('idle');
            armIdleTimer();
        }
        scheduleWander();
    }

    function onPointerDown(e) {
        if (dismissed || drag.pointerId !== null) return;
        if (e.button !== undefined && e.button !== 0) return; // left button / touch only

        drag.pointerId = e.pointerId;
        drag.startX = e.clientX; drag.startY = e.clientY;
        drag.grabX = e.clientX - dragX;
        drag.grabY = e.clientY - dragY;
        drag.moved = false;
        drag.active = false;
        velX = 0; velY = 0;
        lastPointerX = e.clientX; lastPointerY = e.clientY; lastPointerTs = 0;

        try { sprite.setPointerCapture(e.pointerId); } catch (err) { /* not fatal */ }
    }

    var lastPointerX = 0, lastPointerY = 0, lastPointerTs = 0;

    function onPointerMove(e) {
        if (drag.pointerId !== e.pointerId) return;

        if (!drag.moved) {
            var dx = e.clientX - drag.startX, dy = e.clientY - drag.startY;
            if ((dx * dx + dy * dy) < (DRAG_THRESHOLD * DRAG_THRESHOLD)) return;
            drag.moved = true;
            drag.active = true;
            drag.didDrag = true;
            beginHold();
        }

        e.preventDefault();
        dragX = e.clientX - drag.grabX;
        dragY = e.clientY - drag.grabY;
        clampToViewport();
        applyDrag();

        // Smooth the pointer velocity — raw per-event deltas are far too jittery
        // to drive an angle, and produce visible stutter in the swing. Vertical is
        // tracked too so an upward flick actually tosses him up.
        var now = e.timeStamp || performance.now();
        if (lastPointerTs) {
            var pdt = (now - lastPointerTs) / 1000;
            if (pdt > 0.001) {
                velX += (((e.clientX - lastPointerX) / pdt) - velX) * 0.35;
                velY += (((e.clientY - lastPointerY) / pdt) - velY) * 0.35;
            }
        }
        lastPointerX = e.clientX; lastPointerY = e.clientY; lastPointerTs = now;
    }

    function onPointerUp(e) {
        if (drag.pointerId !== e.pointerId) return;
        try { sprite.releasePointerCapture(e.pointerId); } catch (err) { /* fine */ }
        drag.pointerId = null;

        if (!drag.moved) { drag.active = false; return; }  // a click, not a drag
        drag.active = false;

        // A stale velocity from a pointer that stopped moving before release should
        // not launch him — decay it if the last move is old.
        var age = ((e.timeStamp || performance.now()) - lastPointerTs);
        if (age > 120) velX = 0;

        if (reducedMotion) { dragY = bounds().bottom; applyDrag(); peakImpact = 0; land(); return; }

        velX = Math.max(-THROW_MAX, Math.min(THROW_MAX, velX));
        velY = Math.max(-THROW_MAX, Math.min(THROW_MAX, velY));
        if (age > 120) velY = 0;
        peakImpact = 0;

        // Record the drop height for the dizzy gate, measured from his feet.
        var rr = sprite.getBoundingClientRect();
        var vh = window.innerHeight || document.documentElement.clientHeight;
        releaseFrac = vh ? (rr.bottom / vh) : 1;

        airborne = true;
        if (clips.falling) setState('falling');
    }

    function beginHold() {
        clearTimeout(idleTimer);
        clearTimeout(walkTimer);
        stopWander();
        keyWalk.left = keyWalk.right = false;
        if (keyWalk.raf) { cancelAnimationFrame(keyWalk.raf); keyWalk.raf = null; }
        airborne = false;
        peakImpact = 0;   // fresh flight
        fleeing = false;
        hideBubble();     // don't drag a speech bubble around with him
        mover.style.setProperty('--fcm-walk-dur', '0ms'); // track the cursor 1:1
        if (clips.held) setState('held');
    }

    function clampToViewport() {
        var b = bounds();
        if (dragX < b.left) dragX = b.left; else if (dragX > b.right) dragX = b.right;
        if (dragY < b.top) dragY = b.top; else if (dragY > b.bottom) dragY = b.bottom;
    }

    function bindDrag() {
        sprite.addEventListener('pointerdown', onPointerDown);
        sprite.addEventListener('pointermove', onPointerMove);
        sprite.addEventListener('pointerup', onPointerUp);
        sprite.addEventListener('pointercancel', onPointerUp);
    }

    // ── Hover speech bubble ─────────────────────────────────────────────────
    // Pops up when you hover him, holds for BUBBLE_SHOW_MS, then hides. Rate
    // limited to once per BUBBLE_COOLDOWN_MS so sweeping the cursor past him
    // repeatedly doesn't make it flicker on and off.
    var bubbleEl = null;
    var bubbleTimer = null;
    var bubbleLastShown = -Infinity;

    function showBubble() {
        if (!bubbleEl || dismissed) return;
        // Not while he's mid-interaction or asleep — a bubble from a sleeping
        // mascot reads as a bug, and one during a throw just follows him around.
        if (drag.active || airborne) return;
        if (state === 'sleeping' || state === 'before_sleep') return;

        if (BUBBLE_COOLDOWN_MS > 0) {
            var now = (window.performance && performance.now) ? performance.now() : Date.now();
            if (now - bubbleLastShown < BUBBLE_COOLDOWN_MS) return;
            bubbleLastShown = now;
        }

        bubbleEl.classList.add('is-visible');
        clearTimeout(bubbleTimer);
        // Restarted on every hover, so re-entering keeps it up rather than letting
        // an earlier timer cut the new appearance short.
        bubbleTimer = setTimeout(hideBubble, BUBBLE_SHOW_MS);
    }

    function hideBubble() {
        clearTimeout(bubbleTimer);
        bubbleTimer = null;
        if (bubbleEl) bubbleEl.classList.remove('is-visible');
    }

    function bindBubble() {
        var url = window.FC_MASCOT && window.FC_MASCOT.bubble;
        if (!url) return;   // art not present — feature simply absent

        bubbleEl = document.createElement('div');
        bubbleEl.className = 'fcm-bubble';
        bubbleEl.style.backgroundImage = 'url("' + url + '")';
        bubbleEl.setAttribute('aria-hidden', 'true');
        mover.appendChild(bubbleEl);

        // pointerenter (not mouseenter) so a touch tap raises it too.
        sprite.addEventListener('pointerenter', showBubble);
    }

    // ── Runtime scale / corner (debug drives these) ─────────────────────────
    var scaleValue = 3;
    function setScale(n) {
        n = Math.max(2, Math.min(5, parseInt(n, 10) || 3));
        scaleValue = n;
        mount.style.setProperty('--mascot-scale', n);
        mount.dataset.scale = n;
        updateDebugStatus();
    }
    function setCorner(c) {
        if (['bl', 'br', 'tl', 'tr'].indexOf(c) === -1) return;
        mount.dataset.corner = c;
        resetToHome(); // drop walk AND drag offsets so he snaps flush to the new corner
        updateDebugStatus();
    }

    // Full reset: walk travel plus wherever he was dragged/thrown to. Only the
    // deliberate "put him back" paths call this — the corner switch and the debug
    // Idle/Wake buttons. Ordinary waking uses resetPosition() so a drop sticks.
    function resetToHome() {
        resetPosition();
        dragX = 0; dragY = 0;
        velX = 0; velY = 0;
        swing = 0; swingVel = 0;
        squash = 0;
        airborne = false;
        peakImpact = 0;
        applyDrag();
        applySwing();
        applySquash();
    }

    // ── Scroll-reaction hook: he reacts to the sponsors section arriving ──────
    // Fires ONCE per page load — the observer unobserves itself on the first hit,
    // so scrolling back up and down again does not replay it.
    // Returns whether he actually reacted, so the observer below only spends its
    // one shot on a reaction the user really saw.
    function surprise() {
        if (!clips.surprise || dismissed) return false;   // no art / he's been dismissed
        if (keyWalk.left || keyWalk.right) return false;  // don't yank control mid-walk
        setState('surprise');
        return true;
    }
    function initSurpriseObserver() {
        if (CFG.sponsorsSurprise === false) return;               // switched off in wp-admin
        if (!SURPRISE_TARGET || !clips.surprise) return;          // dormant until the art lands
        if (!('IntersectionObserver' in window)) return;
        // Absent on every page that doesn't render the section — nothing to do.
        var el = document.querySelector(SURPRISE_TARGET);
        if (!el) return;
        // A percentage threshold is WRONG for full-page sections and silently never
        // fires: #sponsors is ~1950px tall, so on a 900px viewport its intersection
        // ratio maxes out around 0.46 and can never reach 0.5. Use a band instead —
        // threshold 0 against a shrunken root, so it triggers when the section
        // overlaps the middle 30% of the screen regardless of how tall it is.
        // Same idiom the nav highlighter uses in assets/dist/fc.js.
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                // Stay observing if he declined (mid-walk), so the reaction isn't
                // silently burned — it fires on the next entry instead.
                if (en.isIntersecting && surprise()) io.unobserve(en.target);
            });
        }, { rootMargin: '-35% 0px -35% 0px', threshold: 0 });
        io.observe(el);
    }

    // ── Public API — buttons and future triggers reuse the SAME functions ────
    window.fcMascot = {
        setState: setState,
        walk: walk,
        wander: wanderStep,   // take one ambient step now
        surprise: surprise,
        sleep: goToSleep,
        wake: toIdle,
        setScale: setScale,
        setCorner: setCorner,
        dismiss: dismiss      // no UI for this any more; still callable
    };

    // ── Dev debug panel (ONLY built when DEBUG is truthy) ───────────────────
    var statusEl = null, scaleBtns = [], cornerBtns = [];

    function updateDebugStatus() {
        if (!statusEl) return;
        statusEl.textContent = 'scale: ' + scaleValue + '×   corner: ' +
            (mount.dataset.corner || 'bl').toUpperCase();
        scaleBtns.forEach(function (b) {
            b.classList.toggle('is-active', parseInt(b.dataset.val, 10) === scaleValue);
        });
        cornerBtns.forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.val === mount.dataset.corner);
        });
    }

    function mkBtn(label, onClick, val) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        if (val != null) b.dataset.val = val;
        b.addEventListener('click', onClick);
        return b;
    }

    function buildDebugPanel() {
        if (!DEBUG) return; // ← the single gate: off = no panel, no listeners

        var panel = document.createElement('div');
        panel.className = 'fcm-debug';

        var title = document.createElement('div');
        title.className = 'fcm-debug-title';
        title.textContent = 'mascot debug';
        panel.appendChild(title);

        // Row 1 — state controls.
        var row1 = document.createElement('div');
        row1.className = 'fcm-debug-row';
        row1.appendChild(mkBtn('Idle', toIdle));
        row1.appendChild(mkBtn('Walk ◀', function () { walk('left'); }));   // ◀
        row1.appendChild(mkBtn('Walk ▶', function () { walk('right'); }));  // ▶
        row1.appendChild(mkBtn('Sleep', goToSleep));
        row1.appendChild(mkBtn('Wake', toIdle));
        // Replays the sponsors reaction without having to scroll. No-ops (and
        // stays visibly inert) until surprise.png / surprise.json exist.
        row1.appendChild(mkBtn('Surprise', surprise));
        // Takes one ambient step immediately instead of waiting out the timer.
        row1.appendChild(mkBtn('Wander', wanderStep));
        // Shows the dizzy pose + orbiting stars without having to throw him.
        row1.appendChild(mkBtn('Dizzy', function () { setState('dizzy'); }));
        panel.appendChild(row1);

        // Row 2 — live scale tuning.
        var row2 = document.createElement('div');
        row2.className = 'fcm-debug-row';
        var scaleLabel = document.createElement('span');
        scaleLabel.textContent = 'Scale';
        row2.appendChild(scaleLabel);
        [2, 3, 4, 5].forEach(function (n) {
            var b = mkBtn(n + '×', function () { setScale(n); }, n);
            scaleBtns.push(b);
            row2.appendChild(b);
        });
        panel.appendChild(row2);

        // Row 3 — live corner switching.
        var row3 = document.createElement('div');
        row3.className = 'fcm-debug-row';
        var cornerLabel = document.createElement('span');
        cornerLabel.textContent = 'Corner';
        row3.appendChild(cornerLabel);
        ['bl', 'br', 'tl', 'tr'].forEach(function (c) {
            var b = mkBtn(c.toUpperCase(), function () { setCorner(c); }, c);
            cornerBtns.push(b);
            row3.appendChild(b);
        });
        panel.appendChild(row3);

        // Live readout of the current scale + corner (read your final choice off this).
        statusEl = document.createElement('div');
        statusEl.className = 'fcm-debug-status';
        panel.appendChild(statusEl);

        // The admin preview provides its own host so the panel sits inline in the
        // page instead of floating over wp-admin. Front end: falls back to body.
        var host = document.getElementById('fcm-debug-host') || document.body;
        host.appendChild(panel);
        updateDebugStatus();
    }

    // ── Boot ────────────────────────────────────────────────────────────────
    function start() {
        mount = document.getElementById('fosscomm-mascot');
        var cfg = window.FC_MASCOT;
        if (!mount || !cfg || !cfg.sprites) return; // nothing to do without art or a mount point

        // Desktop only. PHP already skips the enqueue for mobile user agents, but
        // that check can't be trusted behind a page cache serving desktop HTML to a
        // phone — so measure the actual viewport before doing anything. Also covers
        // a desktop browser sized down to a phone-width window.
        var bp = cfg.config && cfg.config.mobileBreakpoint;
        var vw = window.innerWidth || document.documentElement.clientWidth || 0;
        if (vw && vw < (bp || 768)) return;

        CATALOG = cfg.sprites;

        buildDom();
        loadAll().then(function () {
            if (dismissed) return;
            setScale(mount.dataset.scale || CFG.scale || 3);
            requestAnimationFrame(tick);
            setState(CFG.startState === 'sleeping' ? 'sleeping' : 'idle');
            bindActivity();
            armIdleTimer();
            scheduleWander();
            initSurpriseObserver();
            buildDebugPanel();
        }).catch(function (err) {
            if (window.console) console.warn('[fc-mascot] sprite load failed:', err);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
