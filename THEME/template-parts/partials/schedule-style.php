<?php
/**
 * The schedule's styles — template-parts/sections/schedule.php.
 *
 * Mobile first: every rule below is the phone, and the min-width blocks widen
 * it. Only the site's own tokens — ink, muted, the hairline, the accent blue,
 * the orange — and its two faces: the mono label (.fc-label) for everything
 * small, the display face for titles.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
.fc-sx {
    --sx-ink: var(--color-ink, #0A0A0A);
    --sx-muted: var(--color-ink-muted, #6B6B66);
    --sx-line: var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
    --sx-paper: var(--color-paper, #FAFAF7);
    --sx-accent: var(--color-accent, #0033FF);
    --sx-hot: var(--fc-accent-hot, #EE8101);
    /* A session's colour is its own (FOSSCOMM → Schedule → the session's
       Colour), written inline on its card as --tone; the details card is given
       the tapped session's by assets/schedule.js. This is the fallback. */
    --sx-entry: var(--sx-hot);
    /* The scrollbar's colour, from the same dashboard tab; blue by default. */
    --sx-scrollbar: var(--fc-sx-scrollbar, var(--sx-accent));
    --sx-display: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    /* ── TWEAK HERE: how strong the entries are. ──
       --sx-tint        how strong a session card's colour is, and its details
                        card's — set in FOSSCOMM → Schedule → Look (inline on
                        the section); this is the fallback
       --sx-tint-hover  the same card under the pointer (Look + 10)
       --sx-stripe      the grey of every other hour column
       --sx-stripe-card how much of that grey shows through a card that
                        crosses a grey hour — kept fainter than the column
       Each is how much of the colour is mixed into the paper, so 0% is paper
       and 100% is the full colour. */
    --sx-tint: var(--fc-sx-tint, 30%);
    --sx-tint-hover: var(--fc-sx-tint-hover, 40%);
    --sx-stripe: 5%;
    --sx-stripe-card: 2%;
    /* The site's gutter, so the room names and the days start on the same
       line as every other section's text. */
    --sx-gutter: 1rem;
    /* The section is the measuring stick for the day's width: 100cqw below is
       its width, which the hour bar and the rail both span. */
    container-type: inline-size;
    /* The sidebar scrolls a section's top edge to the very top of the window,
       where the site's own bar covers it — so the schedule asks to land one bar
       lower, and assets/section-nav.js honours scroll-margin-top. */
    scroll-margin-top: var(--fc-bar-h, 40px);
}
@media (min-width: 768px) { .fc-sx { --sx-gutter: 2rem; } }
.fc-sx button { font: inherit; color: inherit; }

/* ── The day's geometry, shared by its hour bar and its rail ────────────────
   Minutes are distance, --ppm pixels each: the LARGER of a readable minimum
   (6px, 8px from 1024 — half an hour is 180/240px) and whatever makes the day
   exactly fill the section. Declared on the panel
   so the hour bar and the rail read the same numbers and line up to the pixel. */
.fc-sx-panel {
    --room-w: calc(4.5rem + var(--sx-gutter));
    --lane-h: 128px;
    --ppm: max(6px, (100cqw - var(--room-w) - 1px) / var(--fc-sx-mins));
    --day-w: calc(var(--fc-sx-mins) * var(--ppm));
    /* One hour grey, the next white, from the first hour. */
    --zebra: color-mix(in oklab, var(--sx-ink) var(--sx-stripe), transparent);
    --zebra-bg: repeating-linear-gradient(to right,
        var(--zebra) 0 calc(60 * var(--ppm)),
        transparent calc(60 * var(--ppm)) calc(120 * var(--ppm)));
}
@media (min-width: 1024px) {
    .fc-sx-panel {
        --room-w: calc(7rem + var(--sx-gutter));
        --lane-h: 140px;
        --ppm: max(8px, (100cqw - var(--room-w) - 1px) / var(--fc-sx-mins));
    }
}

/* ── The bars ───────────────────────────────────────────────────────────────
   The site's bar: --fc-bar-h tall (.fc-bar), the mono label face (.fc-label),
   a hairline under it. The days lock one bar down, under the site's own bar;
   the hours lock one bar lower still, under the days. */
.fc-sx-days,
.fc-sx-hours {
    position: sticky;
    background: var(--sx-paper);
    border-bottom: 1px solid var(--sx-line);
    color: var(--sx-muted);
}
.fc-sx-days {
    top: var(--fc-bar-h, 40px);
    z-index: 31;
    display: flex;
    align-items: center;
    padding-inline: var(--sx-gutter);
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
}
.fc-sx-days::-webkit-scrollbar { display: none; }
/* The days as one inline run: real spaces between them, so the gaps are the
   same as the top bar's. */
.fc-sx-days-list { white-space: pre; }
.fc-sx-day {
    display: inline;
    padding: 0;
    background: none;
    border: 0;
    color: var(--sx-muted) !important;
    letter-spacing: inherit;
    text-transform: inherit;
    cursor: var(--fc-cur-pointer, pointer);
}
/* Each day is ONE colour, name, "/" and date together: grey, and blue under
   the pointer. The chosen day is blue, and yellow (the house orange) under
   the pointer. The "//" between days stays the sidebar's grey. Written against
   .fc-sx with !important to clear the bar's own grey on .fc-sx-day above. */
.fc-sx .fc-sx-day,
.fc-sx .fc-sx-day .fc-sx-day-name,
.fc-sx .fc-sx-day .fc-sx-day-date { color: var(--sx-muted) !important; }
.fc-sx .fc-sx-day.is-on,
.fc-sx .fc-sx-day.is-on .fc-sx-day-name,
.fc-sx .fc-sx-day.is-on .fc-sx-day-date { color: var(--sx-accent) !important; }
@media (hover: hover) {
    .fc-sx .fc-sx-day:hover,
    .fc-sx .fc-sx-day:hover .fc-sx-day-name,
    .fc-sx .fc-sx-day:hover .fc-sx-day-date { color: var(--sx-accent) !important; }
    .fc-sx .fc-sx-day.is-on:hover,
    .fc-sx .fc-sx-day.is-on:hover .fc-sx-day-name,
    .fc-sx .fc-sx-day.is-on:hover .fc-sx-day-date { color: var(--sx-hot) !important; }
}/* The "/" between a day and its date: grey always — chosen or not, hovered or
   not — except the CHOSEN day under the pointer, where it turns blue beside
   the yellow words. */
.fc-sx .fc-sx-day .fc-sx-day-sep { color: var(--sx-muted) !important; }
@media (hover: hover) {
    .fc-sx .fc-sx-day.is-on:hover .fc-sx-day-sep { color: var(--sx-accent) !important; }
}.fc-sx-day:focus-visible { outline: 1px solid currentColor; outline-offset: 3px; }

/* The hours: the time line, in the bar face. The room column is left empty,
   and the hours ride a track that assets/schedule.js slides in step with the
   rail underneath, hour for hour. */
.fc-sx-hours {
    top: calc(var(--fc-bar-h, 40px) * 2);
    z-index: 30;
    display: grid;
    grid-template-columns: var(--room-w) 1fr;
    user-select: none;
    -webkit-user-select: none;
}
.fc-sx-hours-corner { border-right: 1px solid var(--sx-line); }
.fc-sx-hours-track { position: relative; overflow: hidden; }
.fc-sx-hours-inner {
    position: absolute;
    inset: 0 auto 0 0;
    width: var(--day-w);
    background: var(--zebra-bg);
    will-change: transform;
}
/* Half-hour boxes: each half hour of the day is a cell of the bar with its
   time centred in it, and a hairline between every two. The grey/white still
   changes by the HOUR (the inner track's background), so a box pair — :00 and
   :30 — shares a shade. */
.fc-sx-tick {
    position: absolute;
    top: 0;
    bottom: 0;
    left: calc(var(--at) * var(--ppm));
    width: calc(30 * var(--ppm));
    display: flex;
    align-items: center;
    justify-content: center;
    border-left: 1px solid var(--sx-line);
    white-space: nowrap;
    color: var(--sx-ink);
}
/* ── The scrollbar ──────────────────────────────────────────────────────────
   Not the browser's: a blue bar lying on the hour bar's bottom hairline, so it
   is locked in view with the hours and needs no space of its own. 4px — the
   width of the solid edge on every session. It runs the full width of the
   day, over the outlines at both ends. Orange while under the pointer or held. */
.fc-sx-scrollbar {
    position: absolute;
    left: var(--room-w);
    right: 0;
    bottom: -1px;
    height: 4px;
    pointer-events: none;
}
.fc-sx-scrollbar[hidden] { display: none; }
.fc-sx-thumb {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: var(--thumb-w, 0px);
    transform: translate3d(var(--thumb-x, 0px), 0, 0);
    background: var(--sx-scrollbar);
    pointer-events: auto;
    cursor: var(--fc-cur-grab, grab);
}
.fc-sx-thumb::before { content: ""; position: absolute; inset: -9px 0 -6px; }
.fc-sx-thumb:hover,
.fc-sx-thumb.is-held { background: var(--sx-hot); }
.fc-sx .fc-sx-thumb.is-held { cursor: var(--fc-cur-grabbing, grabbing); }
/* ── The rail ───────────────────────────────────────────────────────────────
   Edge to edge and down to the section's bottom edge: the rooms, pinned on
   the left, and the day beside them. No scrollbar — it is dragged, or swiped. */
.fc-sx-rail-scroll {
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    user-select: none;
    -webkit-user-select: none;
}
.fc-sx-rail-scroll::-webkit-scrollbar { display: none; }
.fc-sx-rail {
    position: relative;
    display: grid;
    grid-template-columns: var(--room-w) var(--day-w);
    width: max-content;
}
.fc-sx-room {
    position: sticky;
    left: 0;
    z-index: 3;
    display: flex;
    align-items: flex-start;
    padding: 12px 10px 0 var(--sx-gutter);
    background: var(--sx-paper);
    border-right: 1px solid var(--sx-line);
    border-bottom: 1px solid var(--sx-line);
    color: var(--sx-ink);
}
.fc-sx-lane {
    position: relative;
    height: var(--lane-h);
    border-bottom: 1px solid var(--sx-line);
    /* A hairline at every hour — the half hours are marked in the bar only —
       over the grey/white hour stripes. */
    background:
        linear-gradient(to right, var(--sx-line) 1px, transparent 1px) 0 0 / calc(60 * var(--ppm)) 100% repeat-x,
        var(--zebra-bg);
}
/* The last room ends on the section's own bottom edge — the next section's
   rule — so it draws no line of its own. */
.fc-sx-rail > .fc-sx-room:nth-last-child(3),
.fc-sx-rail > .fc-sx-lane:nth-last-child(2) { border-bottom: 0; }

/* ── A session ──────────────────────────────────────────────────────────────
   It FILLS its slot: the whole height of the room's row, and exactly from the
   minute it starts to the minute it ends — no air around it. Two talks back to
   back meet edge to edge; the solid edge on each one's left is what tells
   them apart. A light tint of the orange, the edge in the orange itself. */
.fc-sx-block {
    position: absolute;
    top: 0;
    bottom: 0;
    left: calc(var(--at) * var(--ppm));
    width: calc(var(--len) * var(--ppm));
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    padding: 12px 12px 10px;
    overflow: hidden;
    text-align: left;
    border: 0;
    border-left: 4px solid var(--tone);
    --tint: color-mix(in oklab, var(--tone) var(--sx-tint), var(--sx-paper));
    /* The grey hours show THROUGH the card: the lane's own stripe, one hour
       grey and one clear, laid over the tint and shifted back by the card's own
       position — its minute offset plus the 4px edge the background starts
       after — so each stripe on the card lines up exactly with the column it
       sits in. A card across two hours is two shades of orange. */
    background:
        linear-gradient(to right, color-mix(in oklab, var(--sx-ink) var(--sx-stripe-card), transparent) 0 50%, transparent 50%)
            calc(-1 * (var(--at) * var(--ppm) + 4px)) 0 / calc(120 * var(--ppm)) 100% repeat-x,
        var(--tint);
}
.fc-sx-block:focus-visible { outline: 2px solid var(--sx-accent); outline-offset: -2px; }
@media (hover: hover) {
    .fc-sx-block:hover { --tint: color-mix(in oklab, var(--tone) var(--sx-tint-hover), var(--sx-paper)); }
}

/* The name, the speaker under it, and at the foot "60′ / GR". Everything but
   the name is the small mono face in the muted grey. */
.fc-sx-title {
    margin: 0;
    font-family: var(--sx-display);
    /* The section headings' weight. */
    font-weight: 700;
    font-size: 17px;
    line-height: 1.15;
    letter-spacing: -0.01em;
    color: var(--sx-ink);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
@media (min-width: 1024px) { .fc-sx-title { font-size: 20px; } }
.fc-sx-b-speaker,
.fc-sx-b-foot {
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--sx-muted);
}
.fc-sx-b-foot { margin-top: auto; }
.fc-sx-meta { margin: 0; color: var(--sx-muted); }
.fc-sx-dot { opacity: 0.6; }.fc-sx-block.is-short { padding: 12px 8px 10px; }
.fc-sx-block.is-short .fc-sx-title { font-size: 14px; -webkit-line-clamp: 3; }
/* ── Live and past ──────────────────────────────────────────────────────── */
.fc-sx-block.is-live { background: var(--sx-accent); border-left-color: var(--sx-ink); }
.fc-sx-block.is-live .fc-sx-title,
.fc-sx-block.is-live .fc-sx-b-speaker,
.fc-sx-block.is-live .fc-sx-b-foot { color: var(--sx-paper); }
.fc-sx-block.is-past { opacity: 0.4; }

/* The current minute: a line down through every room, in the accent. */
.fc-sx-nowline {
    position: absolute;
    z-index: 2;
    top: 0;
    bottom: 0;
    left: calc(var(--room-w) + var(--now, 0) * var(--ppm));
    width: 2px;
    margin-left: -1px;
    background: var(--sx-accent);
    pointer-events: none;
}

/* ── The cursors ────────────────────────────────────────────────────────────
   Pointer devices only — on touch the rail is a native swipe. An open hand
   over a rail (or its hour bar) with more day than fits, the pointer over a
   session, and the closed hand from the moment of the press, as on the
   hero's bands. The --fc-cur-* variables are the dashboard's uploaded cursors
   (inc/bootstrap.php), each carrying its own keyword fallback. */
@media (hover: hover) and (pointer: fine) {
    .is-draggable { cursor: var(--fc-cur-grab, grab); }
    .fc-sx-rail-scroll .fc-sx-block { cursor: var(--fc-cur-pointer, pointer); }
    .fc-sx .is-held,
    .fc-sx .is-held * { cursor: var(--fc-cur-grabbing, grabbing) !important; }
}

/* ── The details card ───────────────────────────────────────────────────────
   The session's own card, opened out: the same orange tint, the same solid
   bar down its left side. A sheet up from the bottom on a phone, where the
   thumb is; a card in the middle of a wide screen.

   THE SPACING IS MEASURED ON THE LETTERS. A line of text is taller than its
   letters — the line box adds room above the capitals and below the baseline,
   and more of it for the 26px title than for the 12px "BACK" — so equal
   margins used to look unequal. `text-box: trim-both cap alphabetic` trims
   every text block to its capitals and its baseline, and then one number,
   --sx-gap, is the real distance on screen: card edge to "BACK", "BACK" to
   the title, and the title's block to the line at the foot. (Where text-box
   is not supported the layout is the same with the old, slightly looser
   gaps.) */
.fc-sx-sheet {
    --sx-gap: 20px;
    width: 100%;
    max-width: 100%;
    max-height: 85vh;
    margin: auto 0 0;
    padding: var(--sx-gap) 20px 28px;
    border: 0;
    border-left: 4px solid var(--sx-entry);
    background: color-mix(in oklab, var(--sx-entry) var(--sx-tint), var(--sx-paper));
    color: var(--sx-ink);
    overflow-y: auto;
}
.fc-sx-sheet::backdrop { background: color-mix(in oklab, var(--sx-ink) 45%, transparent); }
/* On a phone the sheet SLIDES UP from the bottom edge as the backdrop fades
   in, and closing plays it backwards — assets/schedule.js adds .is-closing
   and only closes the dialog once the slide down has finished. On a wide
   screen the card simply appears, as before. No movement where it is not
   wanted. */
@keyframes fc-sx-sheet-up   { from { transform: translateY(100%); } to { transform: none; } }
@keyframes fc-sx-sheet-down { from { transform: none; } to { transform: translateY(100%); } }
@keyframes fc-sx-fade-in    { from { opacity: 0; } to { opacity: 1; } }
@keyframes fc-sx-fade-out   { from { opacity: 1; } to { opacity: 0; } }
@media (max-width: 767px) and (prefers-reduced-motion: no-preference) {
    .fc-sx-sheet[open] { animation: fc-sx-sheet-up 280ms cubic-bezier(0.2, 0.8, 0.2, 1); }
    .fc-sx-sheet[open]::backdrop { animation: fc-sx-fade-in 280ms ease-out; }
    .fc-sx-sheet[open].is-closing { animation: fc-sx-sheet-down 220ms cubic-bezier(0.4, 0, 1, 1) forwards; }
    .fc-sx-sheet[open].is-closing::backdrop { animation: fc-sx-fade-out 220ms ease-in forwards; }
}
@media (min-width: 768px) {
    .fc-sx-sheet {
        --sx-gap: 24px;
        width: min(560px, calc(100% - 4rem));
        margin: auto;
        padding: var(--sx-gap) 32px 32px;
    }
}
.fc-sx-sheet-close,
.fc-sx-sheet-title,
.fc-sx-sheet-speaker,
.fc-sx-sheet-where,
.fc-sx-prereq p {
    text-box: trim-both cap alphabetic;
}
[data-fc-sx-sheet-body]:focus { outline: none; }

/* "< / BACK", top left — the Code of Conduct's way back, in its colours
   (.fc-back): the words grey, the "<" blue, and on hover the words blue and
   the "<" orange. No padding of its own, so it sits exactly --sx-gap from the
   top; an invisible margin round it keeps it easy to hit. */
.fc-sx-sheet-close {
    position: relative;
    display: block;
    margin: 0 0 var(--sx-gap);
    padding: 0;
    line-height: 1;
    background: none;
    border: 0;
    cursor: var(--fc-cur-pointer, pointer);
}
.fc-sx-sheet-close::after { content: ""; position: absolute; inset: -12px -8px; }
.fc-sx-sheet-close:focus:not(:focus-visible) { outline: none; }
/* Against .fc-sx, because `.fc-sx button { color: inherit }` above would
   otherwise hand the words the card's ink. */
.fc-sx .fc-sx-sheet-close { color: var(--sx-muted); }
.fc-sx .fc-sx-sheet-close:hover,
.fc-sx .fc-sx-sheet-close:focus-visible { color: var(--sx-accent); }

/* Top to bottom: the title, the speaker, anything to prepare, and last the
   when-and-where line — "10:30 - 11:00 / SATURDAY / 17 OCT / ROOM 1 /
   GREEK" — under a hairline. */
.fc-sx-sheet-title {
    margin: 0;
    font-family: var(--sx-display);
    font-weight: 700;
    font-size: 26px;
    line-height: 1.15;
    letter-spacing: -0.02em;
}
.fc-sx-sheet-speaker { margin: 14px 0 0; line-height: 1; color: var(--sx-muted); }
.fc-sx-prereq {
    margin: var(--sx-gap) 0 0;
    padding-top: var(--sx-gap);
    border-top: 1px solid color-mix(in oklab, var(--sx-entry) 30%, transparent);
}
.fc-sx-prereq .fc-label { margin: 0 0 12px; line-height: 1; color: var(--sx-accent); }
.fc-sx-prereq p:last-child { margin: 0; font-size: 15px; line-height: 1.55; color: var(--sx-muted); }
.fc-sx-sheet-where {
    margin: var(--sx-gap) 0 0;
    padding-top: var(--sx-gap);
    border-top: 1px solid color-mix(in oklab, var(--sx-entry) 30%, transparent);
    line-height: 1.4;
    color: var(--sx-muted);
}</style>
