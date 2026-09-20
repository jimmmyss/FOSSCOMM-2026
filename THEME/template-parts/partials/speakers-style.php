<?php
/**
 * The speakers' styles — the card, the name, the roles and the portrait's ring.
 *
 * Printed by whichever of the two places draws speakers comes first: the row on
 * the landing page (template-parts/sections/speakers.php) and the full list at
 * /speakers/ (template-parts/speakers-page.php). Both include this partial, and
 * the flag below means a page carrying both prints it once.
 *
 * The rules about the VIEWPORT and the RAIL belong to the row's carousel; the
 * list overrides the handful that assume a single line of cards. Everything
 * about the card itself is shared, which is the point of the file.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!empty($GLOBALS['fc_speakers_style_printed'])) {
    return;
}
$GLOBALS['fc_speakers_style_printed'] = true;
?>
<style>
/* ── Speakers row ───────────────────────────────────────────────────────────
   --fc-spk-rim / --fc-spk-rim-hover / --fc-spk-longest are written inline on
   whichever list is being drawn — .fc-spk-wrap for the row on the landing page,
   .fc-spk-grid for the full list at /speakers/ — from the dashboard colours and
   the longest name-word in the list. */

/* Registering the colour GUARANTEES IT RESOLVES.
 *
 * `syntax: '<color>'` plus an initial-value means `var(--fc-spk-rim)` can never
 * be invalid-at-computed-value-time — which matters because an invalid `color`
 * INHERITS, and the parent here is ink, so a failure renders the hollow name
 * solid black rather than accent. That was a real bug, and the literal fallbacks
 * in every var() below are the same defence for browsers that ignore this block.
 *
 * It was also registered to make the colour interpolatable for an animated
 * hover. That is gone — see the note on the hover rule — so this now earns its
 * place purely as a type guarantee. */
@property --fc-spk-rim {
    syntax: '<color>';
    inherits: true;
    initial-value: #0033FF;
}

/* The filter definitions carry no visual content; keep them out of the flow. */
.fc-spk-defs {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
}

.fc-spk-wrap {
    /* ONE height for every portrait, and a SQUARE box to put it in.
     *
     * Fixing the BOX rather than trusting the file is what makes the row
     * uniform: whatever shape someone actually uploads, it is fitted into the
     * same 1:1 frame, so every card is the same width and the spacing between
     * speakers is even by construction. It also means the column can be pure
     * arithmetic instead of something the script has to measure after the
     * images decode. */
    --fc-spk-photo-h: clamp(260px, 26vw, 420px);
    --fc-spk-photo-w: var(--fc-spk-photo-h);

    /* The content column: the photo's width, with a floor so a short row on a
       narrow screen still leaves the names somewhere to live. Everything else —
       card width, name size — is derived from this one number. */
    --fc-spk-col: max(200px, var(--fc-spk-photo-w));
    /* The air either side of a portrait — so the gap between two speakers is
       twice this. 12 on a desktop, 5 on a phone, where the row is tighter. */
    --fc-spk-pad: 5px;
    /* Outline thickness now lives on the SVG filters' `radius` (see the <defs>
       above), where it is a real measurement rather than eight compounding
       offsets. Keep it at or below 3px: feMorphology's kernel is a rectangle, so
       corners square off and read chunky above that. */
}

/* Cancels the section wrapper's bottom padding so the portraits reach the
   section's own bottom edge. Opt-in by class rather than by #speakers, so the
   intent is legible from the markup and any other section can ask for it. The
   `> div` is fc_section_open()'s single inner container. */
.fc-section-flush > div { padding-bottom: 0; }

.fc-spk-wrap {
    position: relative;
    /* Full-bleed to the edges of the SECTION, the way the sponsors' belt does
     * it — see the long note on .fc-belt in assets/site.css. This used to be
     * `calc(50% - 50vw + var(--fc-rail) / 2)`, which is exact on paper and
     * wrong in three ways at once: it hard-codes the sidebar's width, it
     * assumes the wrapper's 1440px cap, and it trusts 100vw to be the width a
     * block actually gets — but 100vw counts the scrollbar and percentages do
     * not, so with a 15px scrollbar the row sat 7.5px under the rail. That is
     * the overhang.
     *
     * Nothing is assumed here: a negative margin cancels the wrapper's own
     * padding, and `fc-section-bleed` on the section lifts the cap for this one
     * child. The row is then as wide as the section is, by construction. */
    margin-inline: -1rem;                   /* cancels the wrapper's px-4 */
    /* The gap under the title is made to MATCH the gap above it — the section's
       own top padding — so the title sits in equal air at both breakpoints
       instead of hanging closer to one side.
     *
     * The section wrapper is `py-24 md:py-40`, i.e. 96px then 160px, and the
     * <h2> already carries `mb-16` (64px) of its own. This makes up the
     * difference rather than adding to it. */
    margin-top: 32px;                       /* 64 + 32 = 96, matching py-24  */
}
@media (min-width: 768px) {
    .fc-spk-wrap {
        margin-top: 96px;                   /* 64 + 96 = 160, matching py-40 */
        margin-inline: -2rem;               /* cancels the wrapper's px-8 */
    }
}

/* ── The indicator ──────────────────────────────────────────────────────────
   One dot per speaker, above the names, filling as the belt travels one full
   set: the sponsors' dots part for part — 7px circles, 7px apart, the fill
   worked out in CSS from ONE number, --fc-spk-p — in the accent blue over ink.

   That number used to be written by the frame loop. It is animated now, on the
   belt's own clock: registered with @property so it has a type the browser can
   interpolate, and run from 0 to the number of speakers over exactly one lap.
   Two animations on one duration, so the dots and the row cannot drift apart,
   and neither of them costs a frame of JavaScript.

   ONLY IN CAROUSEL MODE, as theirs is. A row that fits on the screen does not
   move, so it has no progress to report, and a row of dots under a still line
   would be a control that does nothing. The decision is measured at runtime, so
   CSS reveals them off the class assets/speakers-belt.js sets.

   The three gaps: with the dots showing, the heading's own bottom margin is
   cancelled and the air above the dots becomes the wrap's margin-top, which is
   the same number as the dots' margin-bottom — equal air either side, and the
   same number the sponsors' dots sit on. :has() rather than a class from the
   script, so the spacing follows the same live loop/no-loop decision the dots
   themselves do; where it is unsupported the heading simply keeps its margin. */
@property --fc-spk-p {
    syntax: '<number>';
    inherits: true;
    initial-value: 0;
}
@keyframes fc-spk-progress {
    from { --fc-spk-p: 0; }
    to   { --fc-spk-p: var(--fc-belt-count, 0); }
}
.fc-spk-wrap { --fc-spk-dot-gap: clamp(1rem, 2vw, 1.5rem); }
.fc-spk-dots { display: none; }
.fc-spk-wrap.is-looping .fc-spk-dots {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin: 0 0 var(--fc-spk-dot-gap);
    animation: fc-spk-progress var(--fc-belt-dur, 60s) linear infinite;
}
/* The row is standing still, so the dots have nothing to report. */
@media (prefers-reduced-motion: reduce) {
    .fc-spk-wrap.is-looping .fc-spk-dots { animation: none; }
}
/* Beats the margin-top above, and its 768px override, on specificity alone. */
.fc-spk-wrap.is-looping { margin-top: var(--fc-spk-dot-gap); }
section:has(.fc-spk-wrap.is-looping) .fc-spk-title { margin-bottom: 0; }

.fc-spk-dot {
    position: relative;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    overflow: hidden;
    /* Not yet travelled: ink. */
    background: var(--color-ink, #0A0A0A);
}
.fc-spk-dot-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    /* (p − i), clamped — 0% until this dot's turn, 100% once it has passed. The
       circle's `overflow: hidden` crops the advancing edge, so a half-filled dot
       reads as a level rising through it, and nothing ever touches a dot. */
    width: clamp(0%, calc((var(--fc-spk-p, 0) - var(--fc-dot-i, 0)) * 100%), 100%);
    background: var(--color-accent, #0033FF);
}

/* The sidebar's width is no longer anybody's business here: the row is as wide
   as the section, and the section already sits beside the rail. */
@media (min-width: 1024px) {
    .fc-spk-wrap { --fc-spk-pad: 12px; }
}

.fc-spk-viewport {
    overflow: hidden;
    /* The row is dragged sideways, so the browser must not also claim the
       gesture — but a finger going UP the page still scrolls it. */
    touch-action: pan-y;
    /* A drag across a row of names and photographs is a drag, not a selection. */
    user-select: none;
    -webkit-user-select: none;
}


/* A portrait is a picture, not a thing to drag off the page. */
.fc-spk-shot {
    -webkit-user-drag: none;
    user-drag: none;
}

.fc-spk-rail {
    display: flex;
    /* Bottom-aligned, so every portrait stands on the section's edge whatever
       height the name above it took. */
    align-items: flex-end;
    list-style: none;
    margin: 0;
    padding: 0;
    /* Centred until the script finds the row too wide for the screen. */
    justify-content: center;
}

/* ── The belt ───────────────────────────────────────────────────────────────
   The whole of the movement: one CSS animation, sliding the rail by exactly one
   SET of speakers and starting over. assets/speakers-belt.js copies the set
   enough times to cover the screen twice and hands over the two numbers —
   --fc-belt-w, one set's width, and --fc-belt-dur, one set's worth of travel at
   the row's walking pace. At the moment it loops, copy 2 stands where copy 1
   was, so the seam is never seen.

   This is what the section used to do from JavaScript, a transform per frame
   while reading layout in the same pass. On the compositor it costs nothing per
   frame, which is the entire point of the rebuild.

   `will-change` only once it is moving: a layer held for a rail that never moves
   is a layer held for nothing. */
@keyframes fc-spk-belt {
    from { transform: translate3d(0, 0, 0); }
    to   { transform: translate3d(calc(-1 * var(--fc-belt-w, 0px)), 0, 0); }
}
.fc-spk-wrap.is-looping .fc-spk-rail {
    justify-content: flex-start;
    will-change: transform;
    animation: fc-spk-belt var(--fc-belt-dur, 60s) linear infinite;
}
/* Held still while a speaker is actually lit — not merely while the pointer is
   somewhere in the row — so a name can be read and a card clicked without
   chasing it, and the row keeps moving while you pass over the air between
   them. `.is-holding` comes from the same test that decides the highlight
   (assets/speakers-belt.js); `.is-dragging` is the hand on it. */
.fc-spk-wrap.is-looping.is-holding .fc-spk-rail,
.fc-spk-wrap.is-looping.is-holding .fc-spk-dots {
    animation-play-state: paused;
}
/* Nothing here for a drag: assets/belt-drag.js pauses those two animations
   itself, because it is also the thing scrubbing them. */
/* Asked for less movement: the row stands still, and the copies with it do no
   harm — they simply sit off the edge of the viewport. */
@media (prefers-reduced-motion: reduce) {
    .fc-spk-wrap.is-looping .fc-spk-rail { animation: none; }
}

.fc-spk { flex: 0 0 auto; }

/* The card takes its width FROM THE PHOTO, with a floor.
 *
 * This is the fix for portraits coming out at different sizes. Before, the card
 * was a fixed width and the photo was capped by `max-width: 100%` — so a
 * portrait crop reached the height it was asked for while a wider crop hit the
 * column first, shrank, and ended up visibly smaller than its neighbours. The
 * width was the constraint, and the width was the same for everyone regardless
 * of what shape their photo was.
 *
 * Now every photo gets the SAME HEIGHT unconditionally, and the card is as wide
 * as that makes it — `max-content` measures the photo's resulting width (or the
 * longest name line, whichever is wider). A wide crop gets a wide card instead
 * of a small photo. `min-width` keeps a narrow crop from producing a card too
 * cramped for its name.
 *
 * NB: this is why there is no `container-type` here any more. `container-type:
 * inline-size` implies `contain: inline-size`, which forbids the width from
 * depending on the contents — with it, `max-content` collapses to nothing.
 */
.fc-spk-card {
    display: flex;
    flex-direction: column;
    /* Positioned, so the card's own zero-size <svg class="fc-spk-defs"> anchors
       HERE. It is `position: absolute`, and without a positioned ancestor on the
       card it would resolve against whatever further-out element happens to be
       positioned — different for a card in the rail than for one being measured,
       and a thing that changes if the wrapper's positioning ever does. It has no
       size, so this is about keeping it predictable rather than about where it
       lands. */
    position: relative;
    /* Every card exactly the same width, derived rather than measured.
     *
     * Cards used to be `max-content` — each as wide as its own photo — so a
     * narrow crop sat closer to its neighbours than a wide one and the row was
     * visibly unevenly spaced. With the photo boxed to a fixed ratio there is
     * nothing left to measure: the column is arithmetic, identical for every
     * speaker, and correct before a single image has decoded. */
    width: calc(var(--fc-spk-col) + var(--fc-spk-pad) * 2);
    padding: 0 var(--fc-spk-pad);
    color: inherit;
    text-decoration: none;
    /* One compositing layer per speaker. Both outlines then rasterise once into
       it and the belt's transform simply moves the texture — without this,
       translating the rail repaints the subtree and every visible portrait
       re-runs its filter on every animation frame. */
    transform: translateZ(0);
    /* Everything shares one left edge — name, roles and portrait. Mixing a
       left-aligned name with a centred photo was most of why the block read as
       unresolved, and a shared edge is what the rest of the site does. */
    text-align: left;
}

/* ── Name: one word per line, last one hollow ───────────────────────────── */
.fc-spk-name {
    margin: 0;
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    /* ONE size for every name in the section.
     *
     * Sized so the section's LONGEST word fits the NARROWEST card. Everything
     * else is then set at the same size with room to spare, which is the point:
     * names at different sizes read as an accident, not as fitting.
     *
     * The 1.52 is a character-width budget. A capital costs about 0.6em in this
     * face once the -0.05em tracking comes off, so the size at which n
     * characters exactly fill a column of width w is w / (0.6 * n) — a
     * coefficient of 1.667. This uses 1.52, deliberately below it, leaving about
     * 9% spare. That is not slack for its own sake: 0.6em is an average, and a
     * name of wide capitals (W, M, O) costs more per character than one of
     * narrow ones (I, L, T). At 1.667 a name like "WILLIAM" overflows while
     * "LITTLE" does not — the sort of bug that only shows up on someone else's
     * name.
     *
     * Capped at 82px so a short list of short names is not a billboard; floored
     * at 24px so a very long one stays readable.
     */
    /* Floor of 18px, not 24. On the narrowest screens the column bottoms out at
       200px, and at 24px a long word — "ΚΩΝΣΤΑΝΤΙΝΟΠΟΥΛΟΣ" is seventeen
       characters — did not fit. That used to be harmless because the card grew
       to hold it; the card is a fixed width now, so it would simply spill over
       the next speaker. 18px fits eighteen characters in the narrowest column
       there is, and only gives out past nineteen. */
    font-size: clamp(
        18px,
        calc(var(--fc-spk-col) * 1.52 / var(--fc-spk-longest, 7)),
        82px
    );
    line-height: 0.82;
    letter-spacing: -0.05em;
    text-transform: uppercase;
    /* The SAME variable the photo's outline uses, so the name and the outline
       are one colour by construction rather than by two settings kept in step —
       and the hover rule below only has to change that one variable to move
       both together. */
    /* The solid lines are ink, and stay ink — including on hover. Only the
       hollow last line and the photo's outline carry the accent, which is what
       makes the last line read as the accent rather than as the odd one out in
       a name that is already entirely coloured. */
    color: var(--color-ink, #0A0A0A);
}
/* DIRECT children only. Each word is now one outer span holding the line, with
   an inner .is-outline span around whatever part of it was starred — so a
   descendant selector would make that inner span a block too, and a partly
   starred word ("Ne*well*") would break onto two lines. */
.fc-spk-name > span {
    display: block;
    white-space: nowrap;   /* the size above guarantees it fits; never hyphenate */
    /* Hugs the word. A block-level line stretches the full column width, so the
       empty strip to the right of a short name was part of its box — and the
       carousel, which hit-tests these boxes, counted the pointer as being on the
       name while it was plainly beside it. fit-content makes the box the word. */
    width: fit-content;
}

/* The signature: the final line carries the accent, and is drawn hollow.
 *
 * The colour is set OUTSIDE the @supports on purpose. Where text stroking works
 * the rule below paints the fill transparent and this becomes irrelevant; where
 * it does not, the last line stays solid but in the accent colour — so the
 * two-tone name survives either way instead of collapsing into one flat block of
 * ink. `color: transparent` with no stroke behind it would be an invisible name.
 */
/* NB the literal fallback in every var() below, here and in the stroke and the
   drop-shadows. It is not decoration.
   `color: var(--fc-spk-rim)` with NO fallback is invalid-at-computed-value-time
   if the variable ever fails to resolve — and an invalid `color` INHERITS. The
   parent .fc-spk-name is ink, so the hollow line silently rendered solid black
   instead of accent. That only became visible when the solid lines stopped
   being accent-coloured themselves; before that the bug was the same colour as
   the fix. Anywhere @property is unsupported, this is the only thing standing
   between a missing variable and a black name. */
.fc-spk-name span.is-outline { color: var(--fc-spk-rim, #0033FF); }

@supports (-webkit-text-stroke: 1px black) {
    .fc-spk-name span.is-outline {
        color: transparent;
        /* In em, so the outline stays in proportion at every name size. A fixed
           1.5px looked wiry next to the solid line above it once the name grew;
           this tracks it. Shares --fc-spk-rim with the photo's outline, so both
           move together on hover off one animated value. */
        -webkit-text-stroke: 0.035em var(--fc-spk-rim, #0033FF);
        /* Hollow letters at tight tracking run into each other, because the
           stroke adds width the solid weight does not have. A touch looser. */
        letter-spacing: -0.028em;
    }
}

/* ── "Appearing remotely" indicator ──────────────────────────────────────────
   `[ONLINE]` with a pulsing dot, sitting directly above the head.

   Exactly the ROLES' type — same face, size, weight, tracking and case — so it
   reads as one more line of the card's small print rather than a badge glued on.
   The only difference is the colour.

   --fc-spk-rim, so it moves with the rest of the card: the hollow line of the
   name, the ring round the portrait and this badge are one colour, and the
   highlight takes all three together. It was a fixed accent blue while the
   badge was meant to state a fact rather than answer the pointer. */
.fc-spk-online {
    display: flex;
    /* BASELINE, not center — and this is the third attempt, so it is worth saying
       why the first two failed.
     *
     * `align-items: center` centres the dot against the text item's BOX. That box
       is the line box, and where the glyphs sit inside it depends on the font's
       ascent and descent. All-caps type has no descenders, so its ink sits above
       the box's middle and a geometrically centred dot reads low. Tightening the
       line-height helped but did not remove the dependency: it still needed the
       font's metrics to come out the way I assumed, and they are not mine to
       assume — the mono face may not even have loaded.
     *
     * Baseline alignment needs no metrics. A flex item with no text of its own
       baselines on its bottom margin edge, so the dot sits ON the baseline and
       its centre is exactly its own radius above it — 4px. The centre of a
       capital is half the cap height above the baseline, and cap height is close
       to 0.70em in every monospace face, so 4.2px at 12px type. Within a fifth of
       a pixel, from arithmetic that cannot drift. */
    align-items: baseline;
    gap: 7px;
    /* Hugs the words, like the name's lines and the roles, so the empty strip
       beside it is not part of anything clickable or hoverable. */
    width: fit-content;
    /* Above the name, so the gap goes underneath it. */
    margin: 0 0 6px;
    font-family: var(--font-mono, "JetBrains Mono"), ui-monospace, monospace;
    /* The site's label size, not a number of its own: 11 on a phone and 12 from
       768, which is what .fc-label in assets/site.css sets for every eyebrow,
       every sidebar line and every empty section's placeholder. It used to be a
       flat 12, so on a phone this one label sat a pixel larger than the rest of
       the site's small print. */
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    /* The card's own colour: blue at rest, the hover colour once it is lit —
       the dot and its halo take it too, since both are `currentColor`. */
    color: var(--fc-spk-rim, #0033FF);
}
@media (min-width: 768px) {
    .fc-spk-online { font-size: 12px; }
}
/* A tight line box, so the label's own height is the type and nothing else. It
   no longer affects where the dot lands — baseline alignment does not care — but
   it keeps the block from carrying 1.6 lines' worth of leading above the name. */
.fc-spk-online > span { line-height: 1; }
.fc-spk-online i {
    /* Positioned, because the halo below is absolute with `inset: -3px` and needs
       this as its containing block. Removing it would let the halo escape to the
       nearest positioned ancestor and draw a ring round the whole card. */
    position: relative;
    flex: 0 0 auto;
    /* EVEN, so the radius and the baseline offset are both whole pixels. At 7px
       the centre fell on a half-pixel and the circle rendered a fraction soft. */
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}
/* The halo is a pseudo-element so the DOT itself never moves. Animating the dot
   would change its box and shift the text beside it a fraction of a pixel every
   frame, which reads as the label jittering. */
.fc-spk-online i::after {
    content: "";
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 1px solid currentColor;
    animation: fc-spk-pulse 2s ease-out infinite;
}
@keyframes fc-spk-pulse {
    0%   { transform: scale(0.6); opacity: 0.9; }
    70%  { transform: scale(1.5); opacity: 0; }
    100% { transform: scale(1.5); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
    /* Still a dot, still visibly a live indicator, just not moving. */
    .fc-spk-online i::after { animation: none; opacity: 0.45; }
}

/* ── Roles ──────────────────────────────────────────────────────────────── */
.fc-spk-roles {
    list-style: none;
    /* Tucked close under the name: it is a caption on it, not a separate block.
       At 16px it read as floating between two much larger things. */
    margin: 5px 0 0;
    padding: 0;
    font-family: var(--font-mono, "JetBrains Mono"), ui-monospace, monospace;
    /* The site's label size — see the note on .fc-spk-online above. 11 on a
       phone, 12 from 768, the same as every eyebrow, the sidebar's lines and
       the placeholder an empty section shows. */
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1.6;
    color: var(--color-ink-muted, #6B6B66);
}
@media (min-width: 768px) {
    .fc-spk-roles { font-size: 12px; }
}
/* Same reason as the name's lines: the box is the line of type, not the column
   it sits in, so pointing "at the roles" means pointing at the words. */
.fc-spk-roles li { margin: 0; width: fit-content; }

/* ── Photo ──────────────────────────────────────────────────────────────── */
/* Height is on the IMAGE, not on this box.
 *
 * The box used to be a fixed 240–360px and the image was fitted inside it. A
 * portrait cut-out is taller than it is wide, so it hit the box's WIDTH first,
 * came out small, and left a band of empty box above it — which is exactly the
 * small photo and the big gap that were reported, both from one cause.
 *
 * With the height on the image the box is only ever as tall as the picture, the
 * gap cannot exist, and every portrait is the same height, so they all stand on
 * the same line. `margin-top: auto` holds it to the bottom of the card, and the
 * section's bottom padding is cancelled, so that line IS the section's edge.
 */
.fc-spk-photo {
    margin-top: auto;
    /* Tight to the name deliberately: the name reads as a label ON the portrait
       rather than a separate block above it, and every pixel saved here comes
       straight off the section's height. */
    padding-top: 0;
    /* A row again. This was briefly a column to hold the "[ONLINE]" label above
       the image — but this box carries the ring filter, and a filter applies to
       everything inside its element, so the label came out with a blurred blue
       outline traced round its own letters. The label lives above the name now,
       outside the filter entirely, and the image is the only child again. */
    display: flex;
    justify-content: flex-start;   /* shares the name's left edge */
    align-items: flex-end;
}

.fc-spk-shot {
    display: block;
    /* The BOX is square and identical for everyone; the picture is fitted in.
     *
     * `contain` never crops and never stretches, so a file that is not quite
     * square simply sits a little smaller in its frame rather than being cut or
     * distorted — and `bottom left` keeps it standing on the floor line and on
     * the same left edge as the name, whatever its shape. */
    width: 100%;
    height: var(--fc-spk-photo-h);
    object-fit: contain;
    object-position: bottom left;
    /* The colour corrections, and NOTHING ELSE — no url() in this list.
     *
     * That is what lets it animate. A filter list containing a url() is not
     * interpolated at all; it switches discretely. With the ring moved up to
     * .fc-spk-photo, this list is three plain functions, so grayscale(1) fades
     * to grayscale(0) over the same 50ms the ring and the name take. */
    filter: grayscale(1) contrast(1.06) brightness(1.02);
    transition: filter 50ms ease;
}
/* Highlighted: the grey comes off and the photograph is in colour.
   `:hover` on a pointer device, `.is-hot` where something else decides — the
   /speakers/ page lights whichever card is in the middle of a touch screen. */
.fc-spk-card.is-hot .fc-spk-shot {
    filter: grayscale(0) contrast(1.06) brightness(1.02);
}

/* The RING, on the photo box rather than on the image.
 *
 * Order matters and this is the only order that works. Filters apply innermost
 * first: the image is greyscaled, then this box draws the ring around the result.
 * The other way round — both on the image, ring first — would put grayscale()
 * over the ring and drain the colour out of it.
 *
 * Two filters, defined once per page in
 * template-parts/partials/speaker-filters.php and flooded with the two colours
 * from the dashboard. Which one a card uses is the whole of the highlight: the
 * swap is instant, where it used to be a fade — and a fade meant re-running a
 * Gaussian blur and a threshold on every frame of it, per card, which is the
 * kind of thing a phone cannot afford while it is also scrolling. */
.fc-spk-photo {
    filter: url(#fc-spk-rim-rest);
}
.fc-spk-card.is-hot .fc-spk-photo {
    filter: url(#fc-spk-rim-hot);
}

/* THE HIGHLIGHT, in two declarations.
 *
 * --fc-spk-rim is what the hollow last line of the name is drawn in; the ring
 * around the portrait is the filter swap above. Both change together, and both
 * snap: the ring cannot fade (a filter reference does not interpolate) and a
 * name fading on its own would only look like the two had come apart.
 *
 * `:hover` on the card now, where the old carousel sampled each cut-out's alpha
 * channel through a canvas so that "on the speaker" meant on opaque pixels. The
 * card's box does include some empty air beside a short name, so the highlight
 * comes on a little early — which is the whole of what that machinery bought,
 * and it was costing a hit test per pointer move on a moving row.
 *
 * `(hover: hover)` alone, NOT `and (pointer: fine)`: those features describe the
 * PRIMARY pointer, so a laptop with a touchscreen reports `pointer: coarse` and
 * the whole block would be skipped. Can this input hover at all is the honest
 * question, and a phone still answers no.
 *
 * `.is-hot` stays for the list at /speakers/, where nothing moves and
 * assets/centre-highlight.js lights whichever card is in the middle of a touch
 * screen. */
.fc-spk-card.is-hot { --fc-spk-rim: var(--fc-spk-rim-hover, #EE8101); }

/* ── The cursors ────────────────────────────────────────────────────────────
   Declared on the VIEWPORT so one cursor covers the whole row and no inner
   element can be left holding a stale one, and ordered so the hand wins:
   a looping row can be grabbed, a lit speaker can be clicked, and a row being
   dragged says so whatever is under the pointer.

   `.fc-spk-wrap .fc-spk-card *` puts every descendant back under the viewport's
   control: the site-wide `html a[href] { cursor: pointer }` in inc/bootstrap.php
   applies DIRECTLY to a card that has a link, and a directly-applied declaration
   beats an inherited one whatever the specificity — so a speaker with a URL
   would otherwise show the pointer over empty air and mid-drag, while a speaker
   without one behaved. */
@media (hover: hover) {
    /* On the WRAP, not on the viewport inside it. While a drag is running the
       pointer is captured, and a captured pointer takes its cursor from the
       element holding the capture — which is the wrap (it carries
       data-fc-drag). With these a level lower, the grabbing hand never showed:
       the wrap had no cursor of its own, so the drag fell back to the arrow.
       `cursor` inherits, so one declaration here reaches the whole row. */
    /* The sponsors' belt exactly: an open hand anywhere over a row that loops,
       saying it can be taken hold of, and only there — a row too short to loop
       does not move, so it keeps the arrow. Over a lit speaker the pointer wins
       (it is a link), and once the hand is actually on the row the closed hand
       wins over both. Order is the cascade doing the work: same specificity,
       last match takes it. */
    .fc-spk-wrap.is-looping  { cursor: var(--fc-cur-grab, grab); }
    .fc-spk-wrap.is-holding  { cursor: var(--fc-cur-pointer, pointer); }
    .fc-spk-wrap.is-dragging { cursor: var(--fc-cur-grabbing, grabbing); }
    .fc-spk-wrap .fc-spk-card,
    .fc-spk-wrap .fc-spk-card * { cursor: inherit; }
}

/* The greyscale is the one thing that still fades, because it can: it is a
   plain filter function on the image (declared with it above), not a url(). Off
   on touch, where the highlight arrives with a scroll and a fade on top of that
   reads as lag, and off where movement is not wanted. */
@media (hover: none), (prefers-reduced-motion: reduce) {
    .fc-spk-shot { transition: none; }
}

</style>
