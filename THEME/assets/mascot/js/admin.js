/**
 * wp-admin behaviour for FOSSCOMM → Mascot.
 *
 * Three jobs:
 *   1. the media picker, and cutting the chosen sheet into a per-frame timeline
 *   2. a live, scrubbable preview beside every animation — with its particle
 *      running in the same box, because a particle is only judgeable against the
 *      animation it belongs to
 *   3. the stage at the top, running the actual mascot
 *
 * All three import the SAME modules the front end runs on. The frame count a
 * timeline draws comes from sliceGrid(), the preview plays through Animator, the
 * particles are the real ParticleSystem, and the stage is createMascot().
 * Nothing about playback is re-implemented here, so the preview cannot drift
 * from the thing it previews.
 */

import { loadImage, sliceGrid, playbackOrder } from './sheet.js';
import { Animator, paintCell } from './animator.js';
import { ParticleSystem } from './particles.js';
import { squashDepth, squashPop, squashStack, SQUASH_MAX, FRAME_BOUNCE, ENTER_BOUNCE } from './view.js';
import { stepSquash } from './physics.js';
import { createMascot } from './main.js';

const DEFAULT_FRAME_MS = 120;

/**
 * Every preview renders at the SAME whole-number zoom, and the box grows to fit.
 *
 * It used to fit each sprite to a fixed 128px box, which meant a 32×32 was shown
 * at ×4 and a 64×64 at ×2 — so the bigger sprite appeared at half the pixel
 * density of the one above it and read as blurry next to its neighbours. Nothing
 * was actually wrong with the art; the preview was lying about it. One zoom for
 * everything makes them comparable.
 */
const PREVIEW_ZOOM = 3;
const PREVIEW_MAX = 208;    // px: only very large sheets are stepped down

/* ─────────────────────────────────────────────────────────────────────────────
   MEDIA PICKER
   ───────────────────────────────────────────────────────────────────────────*/

let frameLibrary = null;

function pickImage(onPick) {
    if (!window.wp || !window.wp.media) return;
    if (!frameLibrary) {
        frameLibrary = window.wp.media({
            title: 'Choose a sprite sheet',
            library: { type: 'image' },
            button: { text: 'Use this sheet' },
            multiple: false,
        });
    }
    // Rebound each open, or every previously-opened field also receives the pick
    // and they all change at once.
    frameLibrary.off('select');
    frameLibrary.on('select', () => {
        const item = frameLibrary.state().get('selection').first();
        if (item) onPick(item.toJSON().url);
    });
    frameLibrary.open();
}

function wireMedia(scope) {
    scope.querySelectorAll('[data-fcm-media]').forEach((box) => {
        if (box.dataset.fcmWired) return;
        box.dataset.fcmWired = '1';

        const input = box.querySelector('[data-fcm-sheet]');
        const pick = box.querySelector('[data-fcm-pick]');
        const clear = box.querySelector('[data-fcm-clear]');
        const filename = box.querySelector('[data-fcm-filename]');
        const noun = (pick.textContent.replace(/^(Replace|Select)\s+/, '') || 'sheet').trim();

        function apply(url) {
            input.value = url;
            filename.textContent = url ? url.split('/').pop().split('?')[0] : '';
            clear.hidden = !url;
            pick.textContent = `${url ? 'Replace' : 'Select'} ${noun}`;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        pick.addEventListener('click', () => pickImage(apply));
        clear.addEventListener('click', () => apply(''));
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   SHEET → CLIP
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Build the same clip shape sheet.js produces, from a live form field.
 * Returns null when there is no usable art.
 */
async function clipFromField(sheetInput, directionInput, durationsFor) {
    const url = sheetInput.value.trim();
    if (!url) return null;
    const img = await loadImage(url);
    if (!img) return null;

    const grid = sliceGrid(img.naturalWidth, img.naturalHeight);
    const durations = durationsFor(grid.count);
    const order = playbackOrder(grid.count, directionInput ? directionInput.value : 'forward');

    let totalMs = 0;
    for (const cell of order) totalMs += durations[cell] || DEFAULT_FRAME_MS;

    return {
        url, img,
        width: img.naturalWidth, height: img.naturalHeight,
        frameW: grid.frameW, frameH: grid.frameH,
        cols: grid.cols, rows: grid.rows, count: grid.count,
        order, durations, totalMs,
    };
}

/** The shared zoom, stepped down only if the frame would overflow the card. */
function fitScale(frameSize) {
    if (!frameSize) return PREVIEW_ZOOM;
    if (frameSize * PREVIEW_ZOOM <= PREVIEW_MAX) return PREVIEW_ZOOM;
    return Math.max(1, Math.floor(PREVIEW_MAX / frameSize));
}

/* ─────────────────────────────────────────────────────────────────────────────
   TIMELINE
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * The per-frame duration boxes.
 *
 * Rebuilt whenever the sheet changes; existing values are kept so retiming work
 * survives swapping the art. Clicking a cell scrubs the preview to it, the way
 * a frame strip works in any sprite editor.
 */
function createTimeline(el, onChange, onScrub) {
    const parse = (json) => { try { return JSON.parse(json || '[]') || []; } catch { return []; } };
    const saved = parse(el.dataset.fcmFrames);
    const savedBounce = parse(el.dataset.fcmBounces);
    // Only the animation timeline carries bounciness. The particle one reuses
    // this whole component and simply does not set the attribute.
    const bounceName = el.dataset.fcmBounceName || '';
    const bounceSeed = Number(el.dataset.fcmBounceSeed || 1);

    /** Read a column of boxes: whatever is typed now, then saved, then a default. */
    function column(count, selector, stored, fallback, valid) {
        const boxes = el.querySelectorAll(selector);
        const out = [];
        for (let i = 0; i < count; i++) {
            const live = boxes[i] ? Number(boxes[i].value) : NaN;
            if (Number.isFinite(live) && valid(live)) { out.push(live); continue; }
            const was = stored[i];
            out.push(Number.isFinite(was) && valid(was) ? was : fallback);
        }
        return out;
    }

    function durationsFor(count) {
        return column(count, '[data-fcm-ms]', saved, DEFAULT_FRAME_MS, (v) => v >= 16);
    }

    /** Per-frame bounciness. Empty when this timeline has no bounce column. */
    function bouncesFor(count) {
        if (!bounceName) return [];
        return column(count, '[data-fcm-frame-bounce]', savedBounce, bounceSeed, (v) => v >= 0);
    }

    function numberBox(opts) {
        const input = document.createElement('input');
        input.type = 'number';
        input.min = opts.min;
        input.step = opts.step;
        input.value = opts.value;
        input.name = opts.name;
        input.title = opts.title;
        input.setAttribute(opts.hook, '');
        input.addEventListener('change', onChange);
        // The number box is inside the clickable cell; typing in it must not
        // also scrub, or the caret jumps around as you edit.
        input.addEventListener('click', (e) => e.stopPropagation());
        return input;
    }

    function draw(count) {
        const durations = durationsFor(count);
        const bounces = bouncesFor(count);
        el.innerHTML = '';
        if (!count) {
            el.innerHTML = '<span class="fcm-empty">No frames yet — choose a sheet.</span>';
            return durations;
        }
        for (let i = 0; i < count; i++) {
            const cell = document.createElement('div');
            cell.className = 'fcm-frame';
            cell.dataset.cell = String(i);

            const tag = document.createElement('span');
            tag.textContent = `#${i + 1}`;
            cell.appendChild(tag);

            cell.appendChild(numberBox({
                // step=1, and no max. step=10 against min=16 put every round
                // number off the grid — typing 100 failed validation with "the
                // two nearest valid values are 96 and 106", which is a nonsense
                // thing to be told about how long to hold a drawing.
                min: '16', step: '1',
                value: String(durations[i]),
                name: `${el.dataset.fcmFrameName}[${i}]`,
                title: 'ms this frame is held (16 is one screen refresh)',
                hook: 'data-fcm-ms',
            }));

            if (bounceName) {
                cell.appendChild(numberBox({
                    min: el.dataset.fcmBounceMin || '0',
                    step: el.dataset.fcmBounceStep || '0.1',
                    value: String(bounces[i]),
                    name: `${bounceName}[${i}]`,
                    title: 'squash and stretch on this frame — 0 is rigid',
                    hook: 'data-fcm-frame-bounce',
                }));
            }

            cell.addEventListener('click', () => onScrub(i));
            el.appendChild(cell);
        }
        return durations;
    }

    return {
        draw,
        durationsFor,
        bouncesFor,
        /** Highlight the cell currently on screen. */
        mark(cell) {
            el.querySelectorAll('.fcm-frame').forEach((node) => {
                node.classList.toggle('is-current', Number(node.dataset.cell) === cell);
            });
        },
    };
}

/* ─────────────────────────────────────────────────────────────────────────────
   ONE ANIMATION'S PREVIEW
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Drives one entry row: the sprite clip, its particle, playback and scrubbing.
 *
 * The particle is rendered into the SAME box as the sprite and re-read from the
 * form on every change, so tuning a distance or a motion is visible immediately
 * against the animation it decorates rather than in a box of its own.
 */
function createEntryPreview(entry) {
    const spriteBlock = entry.querySelector('[data-fcm-sprite-block]');
    const particleBlock = entry.querySelector('[data-fcm-particle-block]');
    if (!spriteBlock) return;

    const box = entry.querySelector('[data-fcm-preview]');
    const inner = box.querySelector('.fcm-preview-inner');
    const spriteEl = entry.querySelector('[data-fcm-preview-sprite]');
    const particleLayer = entry.querySelector('[data-fcm-preview-particles]');
    const metaEl = entry.querySelector('[data-fcm-meta]');
    const playBtn = entry.querySelector('[data-fcm-play]');
    const frameLabel = entry.querySelector('[data-fcm-frame-label]');
    const demoBox = entry.querySelector('[data-fcm-bounce-demo]');
    const demoEntryBtn = entry.querySelector('[data-fcm-demo-entry]');
    const demoFramesBtn = entry.querySelector('[data-fcm-demo-frames]');

    const sheetInput = spriteBlock.querySelector('[data-fcm-sheet]');
    const directionInput = spriteBlock.querySelector('[data-fcm-direction]');
    const bounceInput = spriteBlock.querySelector('[data-fcm-bounce]');
    const timelineEl = spriteBlock.querySelector('[data-fcm-timeline]');

    let clip = null;
    let animator = null;
    let scale = 1;
    let playing = true;
    let raf = 0;
    let last = 0;
    let generation = 0;
    let particles = null;

    const timeline = createTimeline(timelineEl, () => rebuild(), (cell) => scrub(cell));

    /* particle ------------------------------------------------------------ */

    const particleTimelineEl = particleBlock && particleBlock.querySelector('[data-fcm-timeline]');
    const particleTimeline = particleTimelineEl
        ? createTimeline(particleTimelineEl, () => rebuildParticle(), () => {})
        : null;

    /** Read the particle form back into the shape ParticleSystem expects. */
    function readParticleSpec() {
        if (!particleBlock) return null;
        const png = particleBlock.querySelector('[data-fcm-sheet]');
        if (!png || !png.value.trim()) return null;
        const numOf = (role, fallback) => {
            const el = particleBlock.querySelector(`[data-fcm-p-${role}]`);
            const n = el ? parseFloat(el.value) : NaN;
            return Number.isFinite(n) ? n : fallback;
        };
        const motionEl = particleBlock.querySelector('[data-fcm-motion]');
        return {
            png: png.value.trim(),
            direction: 'forward',
            frames: particleTimeline ? particleTimeline.durationsFor(64) : [],
            motion: motionEl ? motionEl.value : 'rise',
            count: Math.max(1, numOf('count', 3)),
            distance: Math.max(0, numOf('distance', 7)),
            speedMs: Math.max(100, numOf('speed-ms', 1400)),
            offsetX: numOf('offset-x', 0),
            offsetY: numOf('offset-y', -10),
        };
    }

    async function rebuildParticle() {
        if (!particleBlock) return;
        const png = particleBlock.querySelector('[data-fcm-sheet]');
        const url = png ? png.value.trim() : '';

        if (particleTimeline) {
            if (url) {
                const img = await loadImage(url);
                particleTimeline.draw(img ? sliceGrid(img.naturalWidth, img.naturalHeight).count : 0);
            } else {
                particleTimeline.draw(0);
            }
        }

        const summary = particleBlock.closest('details');
        if (summary) {
            const label = summary.querySelector('summary');
            if (label) label.textContent = url ? 'Particle — on' : 'Particle — none';
        }

        if (!particles) particles = new ParticleSystem(particleLayer, scale);
        particles.setScale(scale);
        particles.use(readParticleSpec());
    }

    /* sprite -------------------------------------------------------------- */

    function stop() { if (raf) cancelAnimationFrame(raf); raf = 0; }

    /* Bounciness, shown rather than described ---------------------------- */

    // Mirrors the front end exactly — the same spring and the same depth curve,
    // imported rather than re-derived, so what you tune here is what you get.
    // Here it re-pops on every loop so the effect is on screen continuously
    // while you tune it, instead of once and gone.
    const spring = { squash: 0, squashVel: 0 };

    /**
     * The two bouncinesses, kept apart exactly as the front end keeps them.
     *
     *   entryBounce() — the animation's own field: the pop on ARRIVING.
     *   bounceValue(cell) — that frame's box: the give WHILE it is on screen,
     *     falling back to the animation's field when the box is empty.
     *
     * Read live from the DOM rather than cached, so typing in either shows in
     * the preview immediately.
     */
    const clamp = (n) => (Number.isFinite(n) ? Math.max(0, Math.min(10, n)) : null);
    const frameBox = (cell) => {
        const boxes = timelineEl ? timelineEl.querySelectorAll('[data-fcm-frame-bounce]') : [];
        return Number.isFinite(cell) ? clamp(parseFloat((boxes[cell] || {}).value)) : null;
    };
    const ownBounce = () => {
        const own = bounceInput ? clamp(parseFloat(bounceInput.value)) : null;
        return own === null ? 1 : own;
    };
    // Always the animation's own field, however many frames there are.
    //
    // A single-frame animation used to be a special case — the field was hidden
    // and the timeline's one box stood in for it — on the reasoning that one
    // drawing means one bounciness. It does not: arriving in an animation and
    // holding a pose are still two different moments even when they are drawn
    // the same, and hiding the field took the arrival away from anyone whose
    // animation happened to be one frame long.
    const entryBounce = () => ownBounce();
    const bounceValue = (cell) => {
        const at = frameBox(cell);
        return at === null ? ownBounce() : at;
    };

    /** The cell on screen right now, or null before the clip exists. */
    const currentCell = () => (animator ? animator.cell : null);

    /**
     * Bounce length, read LIVE off the physics field on this same page.
     *
     * Every preview on the settings screen sits below the Squash & stretch card,
     * so the number is right there in the DOM. Reading it each frame rather than
     * once at load means dragging it retimes every preview on the page as you
     * drag — the same immediacy the Bounciness boxes already have. Undefined
     * when the field is absent, which stepSquash reads as "leave it alone".
     */
    const squashMs = () => {
        const el = document.querySelector('[name="fc_mascot[physics][squash_ms]"]');
        if (!el) return undefined;
        const n = parseFloat(el.value);
        return Number.isFinite(n) && n > 0 ? n : undefined;
    };

    /**
     * Which bounciness the deformation on screen is being drawn at.
     *
     * It belongs to the POP that put the deformation there, and holds for the
     * whole life of that movement. null means nothing is in flight, so the frame
     * on screen can have it. Exactly tick()'s rule on the site, so the preview
     * cannot disagree with what he actually does.
     *
     * This used to hand back to the frame at the next frame CHANGE, which reached
     * back and rewrote a bounce that was still happening — and since
     * squashDepth(0) is exactly zero, a frame at 0 erased it outright rather than
     * simply not adding to it. On a 50ms sheet the arrival was on screen for one
     * frame out of the ~480ms it is worth.
     */
    let depthBounce = null;

    function applySquash() {
        if (!spriteEl) return;
        const b = depthBounce === null ? bounceValue(currentCell()) : depthBounce;
        // Clamped exactly as view.place() clamps it, so a preview can never show
        // a squeeze the site would not draw. Past 1 the sprite inverts.
        const raw = spring.squash * squashDepth(b);
        const amount = Math.max(-SQUASH_MAX, Math.min(SQUASH_MAX, raw));
        if (Math.abs(amount) < 0.002) { spriteEl.style.transform = ''; return; }
        spriteEl.style.transform =
            `scaleX(${(1 + amount * 0.8).toFixed(3)}) scaleY(${(1 - amount).toFixed(3)})`;
    }

    // The same weight and the same cap the front end uses on an animation
    // change — and, like the front end, scaled by the ENTRY value rather than
    // the frame's, because arriving is one moment that belongs to the animation.
    function pop() {
        const b = entryBounce();
        const p = squashPop(ENTER_BOUNCE, b);
        // An arrival worth nothing leaves the depth where it is, so it cannot
        // flatten a bounce that is still in the air.
        if (p > 0) {
            spring.squash = squashStack(spring.squash, p, b);
            depthBounce = b;
        }
        applySquash();
    }

    function paint(cell) {
        paintCell(spriteEl, clip, cell, scale);
        // paintCell sizes the element; the box follows so it hugs the frame.
        inner.style.width = `${clip.frameW * scale}px`;
        inner.style.height = `${clip.frameH * scale}px`;
        timeline.mark(cell);
        if (frameLabel) frameLabel.textContent = `frame ${cell + 1} / ${clip.count}`;
    }

    function scrub(cell) {
        if (!clip || !animator) return;
        playing = false;
        syncButton();
        // Park the animator ON that cell so pressing play resumes from there
        // rather than snapping back to wherever it had got to.
        const pos = clip.order.indexOf(cell);
        animator.pos = pos < 0 ? 0 : pos;
        animator.elapsed = 0;
        animator.done = false;
        // Picking a frame by hand hands the depth back to that frame, or typing
        // in a frame box after an arrival pop would still be drawn at the entry
        // value and the number you were editing would look wrong.
        depthBounce = null;
        paint(animator.cell);
    }

    function syncButton() {
        if (!playBtn) return;
        playBtn.textContent = playing ? '❚❚' : '▶';
        playBtn.title = playing ? 'Pause' : 'Play';
    }

    /**
     * Which bounciness the preview is demonstrating.
     *
     *   'loop'   the resting behaviour — one arrival pop per loop, so the effect
     *            is on screen continuously while you tune the number
     *   'entry'  the arrival pop ALONE, once, held on the first frame
     *   'frames' the timeline, popping on every frame change by that frame's own
     *            value, so a whole cycle can be judged at a glance
     */
    let demo = 'loop';

    function setDemo(mode) {
        demo = mode;
        if (demoEntryBtn) demoEntryBtn.classList.toggle('is-running', mode === 'entry');
        if (demoFramesBtn) demoFramesBtn.classList.toggle('is-running', mode === 'frames');
    }

    /** Show the arrival pop on its own: first frame, one pop, nothing moving. */
    function demoEntry() {
        if (!clip || !animator) return;
        playing = false;
        syncButton();
        setDemo('entry');
        scrub(clip.order[0]);
        spring.squash = 0;
        spring.squashVel = 0;
        pop();
    }

    /** Run the timeline, popping each frame by its own bounciness. */
    function demoFrames() {
        if (!clip || !animator) return;
        setDemo('frames');
        playing = true;
        syncButton();
    }

    function frame(ts) {
        raf = requestAnimationFrame(frame);
        const dt = last ? Math.min(200, ts - last) : 16;
        last = ts;
        if (!clip || !animator) return;
        if (playing) {
            const before = animator.loops;
            const was = animator.cell;
            const cell = animator.update(dt);
            paint(cell);
            if (demo === 'frames') {
                // Every frame change pops, scaled by THAT frame's value — which
                // is now literally what the site does in every state, not only
                // the walk.
                //
                // FRAME_BOUNCE, not the entry weight. This read 0.18 while the
                // site's per-frame pops used 0.06, so the preview showed every
                // timeline value three times as bouncy as it would ever be
                // drawn — you would tune a number here and get a third of it.
                if (cell !== was) {
                    const b = bounceValue(cell);
                    const p = squashPop(FRAME_BOUNCE, b);
                    // The depth travels with the pop. A frame worth nothing
                    // leaves both alone instead of flattening what is in flight.
                    if (p > 0) {
                        depthBounce = b;
                        spring.squash = squashStack(spring.squash, p, b);
                    }
                }
            } else if (animator.loops > before) {
                pop();   // re-pop each loop, so it is always visible
            }
        }
        stepSquash(spring, dt / 1000, squashMs());
        // Still: nothing is in flight, so the frame on screen may have the depth
        // back, ready for whatever pops next.
        if (spring.squash === 0 && !spring.squashVel) depthBounce = null;
        applySquash();
        if (particles) particles.update(dt);
    }

    /**
     * What the controls should look like for a sheet of this many frames.
     *
     * "Bounciness on entering" is shown for EVERY animation now, one frame or
     * twenty. It was hidden for single-frame sheets on the reasoning that one
     * drawing means one bounciness — but arriving and holding are two different
     * moments even when they look the same, and hiding it meant a one-frame
     * animation simply had no arrival pop to set.
     *
     * Only the frames demo is still conditional: there is no cycle to play
     * through when there is a single drawing.
     */
    function applyFrameCount(count) {
        const many = count > 1;

        // The field's whole labelled block, not just the input.
        const ownField = bounceInput ? bounceInput.closest('.fcm-num') : null;
        if (ownField) ownField.hidden = !count;

        if (demoBox) demoBox.hidden = !count;
        if (demoFramesBtn) demoFramesBtn.hidden = !many;
        if (demoEntryBtn) demoEntryBtn.textContent = 'Spawn bounce';

        // A mode that no longer has a button cannot stay selected.
        if (!many && demo === 'frames') setDemo('loop');
    }

    async function rebuild() {
        stop();
        const mine = ++generation;

        clip = await clipFromField(sheetInput, directionInput, (count) => timeline.draw(count));
        if (mine !== generation) return;   // a newer rebuild started while we waited

        if (!clip) {
            applyFrameCount(0);
            delete entry.dataset.fcmLoopMs;
            refreshLoopNotes(entry);
            metaEl.textContent = sheetInput.value.trim()
                ? 'That image could not be loaded.'
                : 'No sheet selected.';
            spriteEl.style.backgroundImage = '';
            inner.style.width = inner.style.height = '0px';
            if (frameLabel) frameLabel.textContent = '';
            if (particles) particles.clear();
            return;
        }

        // Publish the measured loop length so the state's "Play for" can check
        // itself against it. Retiming a frame lands here and flows straight out.
        entry.dataset.fcmLoopMs = String(Math.round(clip.totalMs));
        refreshLoopNotes(entry);

        applyFrameCount(clip.count);
        scale = fitScale(Math.max(clip.frameW, clip.frameH));
        metaEl.textContent =
            `${clip.width}×${clip.height} sheet · ${clip.frameW}×${clip.frameH} frames · `
            + `${clip.count} frame${clip.count === 1 ? '' : 's'} · ${Math.round(clip.totalMs)}ms per loop · `
            + `previewed at ×${scale}`;

        animator = new Animator(clip, true);
        paint(animator.cell);
        await rebuildParticle();

        last = 0;
        raf = requestAnimationFrame(frame);
    }

    /* wiring -------------------------------------------------------------- */

    sheetInput.addEventListener('change', rebuild);
    if (directionInput) directionInput.addEventListener('change', rebuild);
    // Typing a bounciness pops it immediately rather than waiting for the next
    // loop, so the number and the effect are connected while you drag the value.
    if (bounceInput) {
        bounceInput.addEventListener('input', pop);
        bounceInput.addEventListener('change', pop);
    }
    // The same for the per-frame boxes: typing one pops THAT frame's value, so
    // you feel the number you are editing rather than the animation's.
    if (timelineEl) {
        const popFrame = (e) => {
            const box = e.target.closest && e.target.closest('[data-fcm-frame-bounce]');
            if (!box) return;
            const cell = Array.prototype.indexOf.call(
                timelineEl.querySelectorAll('[data-fcm-frame-bounce]'), box
            );
            if (cell < 0) return;
            scrub(cell);
            // The per-frame weight, whatever the frame count. Single-frame
            // sheets used to pop at the heavier arrival weight here, because
            // their arrival had nowhere else to come from; now that the entry
            // field is back for them, a frame is a frame.
            const b = bounceValue(cell);
            const p = squashPop(FRAME_BOUNCE, b);
            if (p > 0) {
                depthBounce = b;
                spring.squash = squashStack(spring.squash, p, b);
            }
            applySquash();
        };
        timelineEl.addEventListener('input', popFrame);
    }
    if (playBtn) {
        playBtn.addEventListener('click', () => {
            playing = !playing;
            // Pressing play by hand leaves any demo mode: it is ordinary
            // playback again, not a demonstration of one number.
            if (playing && demo !== 'loop') setDemo('loop');
            syncButton();
        });
    }
    if (demoEntryBtn) demoEntryBtn.addEventListener('click', demoEntry);
    if (demoFramesBtn) demoFramesBtn.addEventListener('click', demoFrames);
    if (particleBlock) {
        particleBlock.addEventListener('change', rebuildParticle);
        particleBlock.addEventListener('input', () => {
            if (particles) particles.use(readParticleSpec());
        });
    }

    syncButton();
    rebuild();
}

/** Wire one entry row: media fields, preview, and the row tools. */
function wireEntry(entry) {
    if (entry.dataset.fcmWired) return;
    entry.dataset.fcmWired = '1';

    wireMedia(entry);
    createEntryPreview(entry);

    const row = entry.closest('.fcm-section-row') || entry;
    const remove = entry.querySelector('[data-fcm-remove]');
    if (remove) {
        remove.addEventListener('click', () => {
            if (!window.confirm('Delete this animation?')) return;
            row.remove();
            refreshCounts();
        });
    }
    entry.querySelectorAll('[data-fcm-move]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const dir = parseInt(btn.dataset.fcmMove, 10);
            const sibling = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            if (dir < 0) row.parentNode.insertBefore(row, sibling);
            else row.parentNode.insertBefore(sibling, row);
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   REPEATERS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Indices only have to be UNIQUE, not contiguous: the PHP re-indexes with
 * array_values on save, so a counter that never rewinds is simpler and safer
 * than renumbering every row on every delete.
 */
let nextIndex = Date.now() % 100000;

function wireRepeaters() {
    document.querySelectorAll('[data-fcm-entries]').forEach((list) => {
        const addBtn = list.parentNode.querySelector('[data-fcm-add]');
        if (!addBtn || addBtn.dataset.fcmWired) return;
        addBtn.dataset.fcmWired = '1';

        const isSection = list.hasAttribute('data-fcm-section');
        const stateKey = list.dataset.fcmStateKey || '';
        // A state whose animations carry extra fields has its own template; a
        // generic row would be missing those inputs and they'd save as defaults.
        const template = document.getElementById(
            isSection ? 'fcm-section-template'
                : (document.getElementById(`fcm-entry-template-${stateKey}`)
                    ? `fcm-entry-template-${stateKey}` : 'fcm-entry-template')
        );
        if (!template) return;

        addBtn.addEventListener('click', () => {
            const index = nextIndex++;
            const html = template.innerHTML
                .split('__PREFIX__').join(list.dataset.fcmPrefix)
                .split('__INDEX__').join(String(index));

            const holder = document.createElement('div');
            holder.innerHTML = html;
            const node = holder.firstElementChild;
            node.removeAttribute('data-fcm-template');
            const inner = node.matches('[data-fcm-entry]') ? node : node.querySelector('[data-fcm-entry]');
            if (inner) inner.removeAttribute('data-fcm-template');
            list.appendChild(node);
            if (inner) wireEntry(inner);
            refreshCounts();
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE STAGE
   ───────────────────────────────────────────────────────────────────────────*/

function stageStatus(text, isProblem) {
    const el = document.getElementById('fcm-stage-status');
    if (!el) return;
    el.textContent = text;
    el.style.color = isProblem ? '#b32d2e' : '';
}

async function bootStage() {
    const stage = document.getElementById('fcm-stage');
    if (!stage) return;

    // An empty stage is indistinguishable from a broken one, so say which.
    if (stage.dataset.fcmEntryCount === '0') {
        stageStatus('Nothing to preview yet — add an animation to Idle below, then save.', false);
        return;
    }

    const mount = document.createElement('div');
    mount.id = 'fosscomm-mascot';
    mount.setAttribute('aria-hidden', 'true');
    stage.appendChild(mount);

    const scaleInput = document.getElementById('fcm-preview-scale');
    const scale = () => Math.max(0.5, parseFloat(scaleInput.value) || 3);

    // The stage runs the saved config with the two size multipliers replaced by
    // the local one: a mascot at ×8 would be most of a 300px box, and the point
    // of the preview is the behaviour, not the size.
    const config = {
        ...(window.FC_MASCOT || {}),
        scale: scale(),
        scaleMobile: scale(),
        mobileBreakpoint: 0,
    };

    let mascot;
    try {
        mascot = await createMascot(mount, config);
    } catch (err) {
        // Without this the failure is an unhandled promise rejection: the box
        // stays empty and the only clue is in the console.
        stageStatus(`The preview could not start: ${err && err.message ? err.message : err}`, true);
        throw err;
    }
    window.fcMascotPreview = mascot;

    // Loaded art, not configured art — a sheet whose URL 404s is dropped during
    // loading, and that is exactly the case worth naming.
    const loaded = Object.keys(mascot.machine.clips)
        .filter((key) => mascot.machine.clips[key].length);
    if (!loaded.length) {
        stageStatus('None of the configured sheets could be loaded — check the images in the media library.', true);
    } else {
        stageStatus(`Showing ${loaded.join(', ')}. Preview size is local to this box — the real sizes are set below.`, false);
    }

    document.getElementById('fcm-respawn').addEventListener('click', () => mascot.respawn());
    scaleInput.addEventListener('change', () => {
        mascot.config.scale = scale();
        mascot.config.scaleMobile = scale();
        mascot.view.setScale(scale());
        mascot.respawn();
    });
}

/* ─────────────────────────────────────────────────────────────────────────────
   BOOT
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Say how long a state will actually run: the animation's own length times its
 * Loops count.
 *
 * The length is MEASURED from the timeline rather than typed, so it follows every
 * retiming on its own — which is the whole reason the control is a count now. It
 * used to be a duration in ms, and this note existed to police it: warn in red
 * when the number was not a whole multiple of a pass, and offer the nearest
 * multiple below. None of that can happen to a count, so the note has nothing
 * left to warn about and just reports the result.
 */
function refreshLoopNote(state) {
    const note = state.querySelector('[data-fcm-loop-note]');
    if (!note) return;

    const first = state.querySelector('[data-fcm-entry]:not([data-fcm-template])');
    const pass = first ? Number(first.dataset.fcmLoopMs || 0) : 0;
    if (!pass) { note.hidden = true; return; }

    const loopsInput = first.querySelector('[data-fcm-extra-loops]');
    const loops = Math.max(1, parseInt(loopsInput ? loopsInput.value : '1', 10) || 1);

    note.hidden = false;
    note.innerHTML = loops === 1
        ? `One pass of this animation is <strong>${pass}ms</strong>.`
        : `<strong>${loops} loops</strong> of ${pass}ms — <strong>${pass * loops}ms</strong> in total.`;
}

/** Re-run the loop note for the state containing `el`, or for all of them. */
export function refreshLoopNotes(el) {
    const scope = el && el.closest ? el.closest('[data-fcm-state]') : null;
    if (scope) refreshLoopNote(scope);
    else document.querySelectorAll('[data-fcm-state]').forEach(refreshLoopNote);
}

/**
 * Keep each state header honest: how many animations it holds, and — when it
 * holds none — a dimmed note saying what happens instead. The CSS reads
 * `is-empty` together with the `data-fcm-empty` the PHP already stamped, so the
 * skipped-vs-borrows-Idle rule lives in exactly one place.
 */
export function refreshCounts() {
    document.querySelectorAll('[data-fcm-state]').forEach((state) => {
        const n = state.querySelectorAll('[data-fcm-entry]:not([data-fcm-template])').length;
        const label = state.querySelector('.fcm-count');
        if (label) label.textContent = n === 1 ? '1 animation' : `${n} animations`;
        state.classList.toggle('is-empty', n === 0);
    });
}

/**
 * Keep the total-length note in step with the Loops box.
 *
 * Delegated, because entries are added and removed all the time and a listener
 * bound per input would have to be re-bound on every one. There is no longer a
 * "use this value instead" button to handle: a count cannot be a wrong number
 * the way a duration could.
 */
function wireLoops() {
    if (document.body.dataset.fcmLoopsWired) return;
    document.body.dataset.fcmLoopsWired = '1';
    const onEdit = (e) => {
        const input = e.target.closest && e.target.closest('[data-fcm-extra-loops]');
        if (input) refreshLoopNotes(input);
    };
    // `input` as well as `change`, so the note keeps up while typing rather than
    // only once the field is committed.
    document.body.addEventListener('input', onEdit);
    document.body.addEventListener('change', onEdit);
}

function init() {
    // Each row is wired independently. One malformed row throwing must not take
    // the rest of the page — and, in particular, the stage — down with it: the
    // symptom of that is a page that looks entirely dead for a local reason.
    document.querySelectorAll('[data-fcm-entry]:not([data-fcm-template])').forEach((entry) => {
        try { wireEntry(entry); } catch (err) { console.error('[mascot] row failed to wire', entry, err); }
    });
    try { wireRepeaters(); } catch (err) { console.error('[mascot] repeaters failed', err); }
    try { wireLoops(); } catch (err) { console.error('[mascot] loops note failed', err); }
    bootStage();
    refreshCounts();
    refreshLoopNotes();
    // refreshCounts is called from the add/delete handlers rather than watched
    // with a MutationObserver: the stage below is a live mascot whose particle
    // layer adds and removes children every frame, and a subtree observer on
    // <body> would run this on every one of them.
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
