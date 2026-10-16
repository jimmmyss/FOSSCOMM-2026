<?php
/**
 * The communities' styles — the row on the landing page, and the card that
 * /communities/ shares with it (template-parts/partials/community-card.php).
 *
 * The card's name and small print are the speakers' own classes, so this leans
 * on template-parts/partials/speakers-style.php for them and for the
 * section-flush rule; callers print that first. What is here is the belt
 * running the other way, and the logo's box.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!empty($GLOBALS['fc_communities_style_printed'])) {
    return;
}
$GLOBALS['fc_communities_style_printed'] = true;
?>
<style>
/* ── The row ────────────────────────────────────────────────────────────────
   The speakers' wrap, measure for measure: the same card sizes (so the names
   come out at the same size), full-bleed by cancelling the wrapper's padding
   (the section carries fc-section-bleed, and assets/site.css hands the cap back
   to everything else), and the same air under the heading. */
.fc-com-wrap {
    /* Smaller than the speakers' 260–420px: a logo is a supporting mark, not a
       person, and at portrait size the row outweighed the section above it. The
       name is sized from this column, so it shrinks with it. */
    --fc-spk-photo-h: clamp(195px, 17vw, 290px);
    --fc-spk-photo-w: var(--fc-spk-photo-h);
    --fc-spk-col: max(160px, var(--fc-spk-photo-w));
    --fc-spk-pad: 5px;
    --fc-com-dot-gap: clamp(1rem, 2vw, 1.5rem);
    position: relative;
    margin-inline: -1rem;
    margin-top: 32px;
}
@media (min-width: 768px) {
    .fc-com-wrap { margin-top: 96px; margin-inline: -2rem; }
}
@media (min-width: 1024px) { .fc-com-wrap { --fc-spk-pad: 12px; } }

.fc-com-viewport {
    overflow: hidden;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
}
.fc-com-rail {
    display: flex;
    align-items: flex-end;          /* everything stands on the section's edge */
    justify-content: center;        /* until the script finds it too wide */
    list-style: none;
    margin: 0;
    padding: 0;
}

/* ── The belt, reversed ─────────────────────────────────────────────────────
   Its own keyframe names so assets/belt-drag.js can find them, and the same
   travel as the speakers' — only played backwards, so the row walks to the
   RIGHT. The indicator's fill still runs forwards: it reports distance, not
   direction. */
@keyframes fc-com-belt {
    from { transform: translate3d(0, 0, 0); }
    to   { transform: translate3d(calc(-1 * var(--fc-belt-w, 0px)), 0, 0); }
}
.fc-com-wrap.is-looping .fc-com-rail {
    justify-content: flex-start;
    will-change: transform;
    animation: fc-com-belt var(--fc-belt-dur, 60s) linear infinite reverse;
}

/* ── The indicator — the speakers' dots exactly ─────────────────────────── */
@property --fc-com-p {
    syntax: '<number>';
    inherits: true;
    initial-value: 0;
}
@keyframes fc-com-progress {
    from { --fc-com-p: 0; }
    to   { --fc-com-p: var(--fc-belt-count, 0); }
}
.fc-com-dots { display: none; }
.fc-com-wrap.is-looping .fc-com-dots {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin: 0 0 var(--fc-com-dot-gap);
    animation: fc-com-progress var(--fc-belt-dur, 60s) linear infinite;
}
.fc-com-wrap.is-looping { margin-top: var(--fc-com-dot-gap); }
section:has(.fc-com-wrap.is-looping) .fc-com-title { margin-bottom: 0; }
.fc-com-dot {
    position: relative;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    overflow: hidden;
    background: #DDDDDA;
}
/* The row runs left to right, so its indicator does too, mirrored: the first
   dot is the RIGHTMOST, the dots fill from right to left, and each fills from
   its right edge — progress travels the way the row does. */
.fc-com-wrap.is-looping .fc-com-dots { flex-direction: row-reverse; }
.fc-com-dot-fill {
    position: absolute;
    inset: 0 0 0 auto;
    width: clamp(0%, calc((var(--fc-com-p, 0) - var(--fc-dot-i, 0)) * 100%), 100%);
    background: var(--color-accent, #0033FF);
}

/* ── The name ───────────────────────────────────────────────────────────────
   One line that wraps where it has to, where a speaker's name is one word per
   line. The speakers' rule makes each span a nowrap block the width of its
   word; here the one span is ordinary running text in the column. */
.fc-com-name > span {
    display: block;
    width: auto;
    white-space: normal;
}

/* ── Lit — the speakers' behaviour exactly ──────────────────────────────────
   assets/speakers-belt.js decides what the pointer is ON: the name, the small
   lines, or an opaque pixel of the logo. Only then is the card .is-hot (the
   hollow run turns orange), the row .is-holding (it stops), and the cursor the
   pointer. Over the card's empty air the row keeps moving under the open hand
   — and a click anywhere on the card still opens it, because the whole card is
   the link. */
.fc-com-card.is-hot { --fc-spk-rim: var(--fc-spk-rim-hover, #EE8101); }
@media (hover: hover) {
    .fc-com-wrap.is-looping.is-holding .fc-com-rail,
    .fc-com-wrap.is-looping.is-holding .fc-com-dots {
        animation-play-state: paused;
    }
    /* The cursors, in the speakers' order: open hand over a moving row, the
       pointer over a community's ink, the closed hand once it is held. */
    .fc-com-wrap.is-looping  { cursor: var(--fc-cur-grab, grab); }
    .fc-com-wrap.is-holding  { cursor: var(--fc-cur-pointer, pointer); }
    .fc-com-wrap.is-dragging { cursor: var(--fc-cur-grabbing, grabbing); }
    .fc-com-wrap .fc-spk,
    .fc-com-wrap .fc-spk *   { cursor: inherit; }
}
@media (prefers-reduced-motion: reduce) {
    .fc-com-wrap.is-looping .fc-com-rail,
    .fc-com-wrap.is-looping .fc-com-dots { animation: none; }
}

/* ── The logo ───────────────────────────────────────────────────────────────
   The portrait's square, standing on the floor, with nothing drawn round it.
   Every logo gets the SAME box, whatever shape the file is, and is fitted
   inside it with room to spare — a logo, unlike a cut-out, is not meant to
   touch its edges — so the row is even by construction. No filter at all:
   always in its own colours. */
.fc-com-logo-box {
    margin-top: auto;
    width: 100%;
    display: grid;
    place-items: center;
    /* Wide at the sides, tight above and below: the logo is a bit under half
       the column wide, and the box is only a little taller than the logo, so it
       sits close under the name and close to the floor. (Padding percentages are
       of the WIDTH, top and bottom included.) */
    padding: 6% 27.5%;
}
.fc-com-logo {
    display: block;
    width: 100%;
    /* Every logo in the same square, whatever the file's shape. */
    aspect-ratio: 1 / 1;
    height: auto;
    object-fit: contain;
    -webkit-user-drag: none;
}
</style>