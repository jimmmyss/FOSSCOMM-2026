<?php
/**
 * All speakers — the standalone page at /speakers/ (routed by inc/speakers.php).
 *
 * The same cards as the landing page's row, in lines instead of on a belt: two
 * across on a phone, up to five on a wide screen. Same names, same roles, same
 * cut-outs with the same ring — template-parts/partials/speaker-card.php draws
 * them for both, and template-parts/partials/speakers-style.php styles them for
 * both. Everything below is the handful of rules that differ because these are
 * rows rather than one moving line.
 *
 * Content is the Speakers option (FOSSCOMM → Speakers): this page adds nothing
 * of its own to edit.
 */
if (!defined('ABSPATH')) {
    exit;
}

// The Speakers section's own option — the section reads it through
// fc_section_data(); here there is no section context to go through.
$speakers = get_option('fc_speakers', []);
if (!is_array($speakers)) $speakers = [];

$built   = fc_speaker_cards($speakers);
$cards   = $built['cards'];
$longest = $built['longest'];

// This page's OWN heading (FOSSCOMM → Speakers → "Page heading"), falling back
// to the section's when it has not been given one: the row on the landing page
// is a selection and can say so, while this is the whole list and usually wants
// to say something else.
$heading = fc_speakers_page_title();
$title   = fc_pick($heading['el'], $heading['en']);
$style   = fc_speakers_style();
?>
<!-- Outer wrapper carries min-h-screen so the page still fills the viewport with
     a short list. The <section> inside is what the sidebar nav and the mascot's
     section reactions both read to decide which section you are in
     (assets/section-nav.js and assets/mascot/js/sections.js), so keep it
     wrapping the real content. `fc-section-dots` lets the global wave canvas
     through, as the Speakers section itself does. -->
<div class="min-h-screen">
<section id="speakers" class="relative border-t border-border fc-section-dots">
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="fc-label text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <?php fc_back_link(home_url('/'), fc_t('back_home')); ?>
        </div>

        <?php if ($title !== '') : ?>
            <?php // The section's own heading, in the section's own face — this is
                  // the same list, so it says the same thing. ?>
            <h1 class="fc-section-heading"><?php echo fc_format($title); ?></h1>
        <?php endif; ?>

        <?php /* No progress indicator here. The row on the landing page has one,
                 because a belt that drifts on its own gives no other clue how far
                 round it has come; a list you scroll already says where you are. */ ?>

        <?php if (empty($cards)) : ?>
            <?php fc_render_tba('speakers'); ?>
        <?php else : ?>
            <?php /* data-fc-centre, NOT data-fc-speakers: the carousel must not
                     claim this list (it would duplicate the cards and set them
                     drifting), while assets/centre-highlight.js gives a touch
                     screen the same "whatever is in the middle is lit" behaviour
                     the belt has. On a pointer device it stands down and the
                     :hover rule below owns it. */ ?>
            <?php /* The two ring filters, once for the page — the same pair the
                     landing page's row uses. */ ?>
            <?php get_template_part('template-parts/partials/speaker-filters'); ?>
            <ol class="fc-spk-grid" data-fc-centre
                style="--fc-spk-rim: <?php echo esc_attr($style['rim']); ?>;
                       --fc-spk-rim-hover: <?php echo esc_attr($style['rim_hover']); ?>;
                       --fc-spk-longest: <?php echo (int) $longest; ?>;">
                <?php foreach ($cards as $i => $card) : ?>
                    <?php
                    /* `sizes`: the column is half the content width on a phone and
                       a fifth of it past 1280, where the content itself stops at
                       1440 — so about 220px there. The steps below match the
                       grid's own breakpoints. */
                    get_template_part('template-parts/partials/speaker-card', null, [
                        'card'        => $card,
                        'index'       => $i,
                        'sizes'       => '(min-width: 1280px) 260px, (min-width: 1024px) 23vw, (min-width: 640px) 30vw, 46vw',
                        'centre_item' => true,
                    ]);
                    ?>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
</div>
<?php
/* The shared card styles. Everything below overrides only what a grid needs. */
get_template_part('template-parts/partials/speakers-style');
?>
<style>
/* The way back is .fc-back in assets/site.css — shared with the Code of
   Conduct, the attributions and a news article, so all four read the same. */

/* ── All speakers: the same cards, in lines ─────────────────────────────────
   Two across on a phone and five at the widest, with the steps in between so a
   column never has to hold a name at an unreadable size. Fewer per row than the
   first draft on a laptop: four rather than five at 1024–1440 makes every card
   bigger and runs the list further down the page, which is the trade a list
   wants and a belt does not.

   FLEX, not grid, for one reason: an incomplete row is CENTRED. A grid puts a
   lone speaker in the first column with four empty tracks beside them; here one
   speaker sits in the middle of the page, two sit either side of the middle with
   the same air on the outside of each, and a full row fills the line exactly as
   a grid would. The width is the arithmetic a grid track would have done —
   (100% − the gaps) / the number in a row. */
.fc-spk-grid {
    --fc-spk-per-row: 2;
    /* The air either side of a speaker, doubled — half of this sits inside each
       cell, so a card keeps 5px of its own on a phone and 12px on a desktop,
       which is exactly what the landing page's row gives them. */
    --fc-spk-gx: 10px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    /* THE LINE. Every portrait in a row stands on the same one, exactly as the
       landing page's row stands on the section's bottom edge — the name and the
       roles rise from it rather than hanging from the top of a stretched cell.
       That is also why the cards are not stretched: at flex-end each is only as
       tall as it is, so .fc-spk-photo's `margin-top: auto` has nothing to push
       against and the block stays together. */
    align-items: flex-end;
    /* NO COLUMN GAP — the gutter is padding inside each cell instead, so the
       cells tile edge to edge and the rules on their bottoms meet to form ONE
       line across the row. With a real gap the line would come out dashed, a
       length of rule under each card with a hole between them. */
    gap: clamp(2rem, 4vw, 3.5rem) 0;
    list-style: none;
    margin: 0;
    padding: 0;
}
@media (min-width: 640px)  { .fc-spk-grid { --fc-spk-per-row: 3; } }
@media (min-width: 1024px) { .fc-spk-grid { --fc-spk-per-row: 4; --fc-spk-gx: 24px; } }
@media (min-width: 1440px) { .fc-spk-grid { --fc-spk-per-row: 5; } }

/* Each cell is a CONTAINER, which is what lets the card size its name to the
   column: --fc-spk-col becomes 94cqw below, and .fc-spk-name's clamp() reads
   it. The row's cards take that number from a fixed photo height instead —
   there, the column is arithmetic; here, it is whatever a quarter of the page
   comes to. The container-type goes on the CELL and never on the card itself:
   `container-type: inline-size` implies `contain: inline-size`, which would
   forbid the card's width from depending on its contents. */
.fc-spk-grid > .fc-spk {
    container-type: inline-size;
    flex: 0 0 calc(100% / var(--fc-spk-per-row));
    display: flex;
    /* Half the gutter either side: the space between two cards is the two
       halves added back together, and the cells still touch. */
    padding: 0 calc(var(--fc-spk-gx) / 2);
    /* Anchors the rule below, which is absolutely positioned. */
    position: relative;
}

/* ── THE LINE ─────────────────────────────────────────────────────────────
   The same line the landing page's speakers stand on: there it is the border
   between two sections, so it runs the full width of the SCREEN, not the width
   of the text column, and it is there whether or not a card happens to be above
   it. Drawn under every row here, so a list four rows deep is four lines of
   speakers rather than one line and three rows hanging in the air.

   ±100vw and let it be clipped. A cell only knows its own width, and with an
   incomplete row centred, cell borders would stop where the cards stop — which
   is the line not reaching the edges. Running it far past both sides instead
   makes every row's line full width by construction; html/body carry
   `overflow-x: clip` (assets/site.css), so nothing scrolls sideways.

   One per cell, overlapping along the row: they are the same 1px line at the
   same y, so they coincide exactly rather than adding up. */
.fc-spk-grid > .fc-spk::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: -100vw;
    right: -100vw;
    border-bottom: 1px solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
    pointer-events: none;
}

/* The portrait is CUT OFF at the line, not stood on top of it.
 *
 * The ring is drawn outside the silhouette — a blurred, thresholded copy of its
 * alpha, about 4.5px proud of it — so a cut-out standing exactly on the rule
 * hangs its outline over the far side. On the landing page that spill is hidden
 * by the next section painting over it; here nothing is underneath, so the card
 * cuts it off itself and the edge comes out the same.
 *
 * clip-path, and NOT `overflow: hidden`: the cut has to be on the bottom only.
 * Overflow clips all four sides, and a name is sized to fill its column with a
 * margin that is an average over letter widths — a line of wide Greek capitals
 * can run a few pixels past it, and hiding those few pixels turns a name that
 * merely touched its neighbour into a name with its last letter sliced off.
 * Negative insets leave the other three sides unclipped. */
.fc-spk-grid .fc-spk-card {
    clip-path: inset(-100vh -100vw 0 -100vw);
    /* 94, not 100. The name is sized to fill --fc-spk-col exactly and is never
       allowed to wrap, so a column-wide name runs to the very edge of its cell
       and sits against its neighbour across the gutter — which on two columns
       reads as one long run-on word. The belt buys the same clearance with its
       24px of card padding; here the column IS the cell, so the 6% comes off
       the budget instead. */
    --fc-spk-col: 94cqw;
    --fc-spk-pad: 0px;
    width: 100%;
}
/* The small print shrinks with the card, as the name does.
 *
 * A name here is sized from --fc-spk-col, so it comes out roughly half what the
 * same name is on the landing page's row; the roles and the [ONLINE] badge were
 * keeping the site's flat label size, which left them looking oversized against
 * it. Tying them to the same column measurement scales all three together.
 *
 * Clamped at both ends: 12px is the site's label size, which is where the
 * widest cards land, and 9px is as small as uppercase mono with this much
 * letter-spacing stays readable — a strictly proportional number would be about
 * 7px on a phone, where the cards are half the width again. */
.fc-spk-grid .fc-spk-roles,
.fc-spk-grid .fc-spk-online {
    font-size: clamp(9px, calc(var(--fc-spk-col) / 22), 12px);
}

/* The portrait is square to its COLUMN here rather than a fixed height: five
   columns on a wide screen are narrower than the belt's cards, and a fixed
   260px photo in a 220px column would be a tall picture in a narrow box.
   `object-fit: contain` still never crops, and `bottom left` still stands the
   cut-out on the floor line — both come from the shared stylesheet. */
.fc-spk-grid .fc-spk-shot {
    height: auto;
    aspect-ratio: 1 / 1;
}

/* THE HIGHLIGHT. The row's version is driven by a class its carousel sets,
   because there the pointer has to be tested against the cut-out's own pixels —
   a belt card is mostly transparent air. In a grid nothing moves and the cell
   is the speaker, so :hover is honest, and `(hover: hover)` keeps it off the
   phones where centre-highlight.js is lighting cards by position instead. */
@media (hover: hover) {
    .fc-spk-grid .fc-spk-card:hover { --fc-spk-rim: var(--fc-spk-rim-hover, #EE8101); }
    .fc-spk-grid .fc-spk-card:hover .fc-spk-shot {
        filter: grayscale(0) contrast(1.06) brightness(1.02);
    }
}
/* centre-highlight.js puts .is-hot on the ITEM it was given — the cell — so the
   card's own .is-hot rules in the shared stylesheet do not fire. Same two
   declarations, one level out. */
.fc-spk-grid > .fc-spk.is-hot .fc-spk-card { --fc-spk-rim: var(--fc-spk-rim-hover, #EE8101); }
.fc-spk-grid > .fc-spk.is-hot .fc-spk-shot {
    filter: grayscale(0) contrast(1.06) brightness(1.02);
}
</style>
