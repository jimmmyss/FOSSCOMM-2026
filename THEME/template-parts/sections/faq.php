<?php
/**
 * FAQ — nothing but the questions, edge to edge.
 *
 * No eyebrow and no section heading: the section IS the list. Each question is a
 * full-bleed band that fills blue on hover, with the question and its [+] marker
 * turning white; clicking one glitches the question text into its answer IN
 * PLACE (same element, so the answer inherits the question's face and size) and
 * the marker flips [+] → [-].
 *
 * Behaviour lives in assets/faq.js, driven by window.fcScramble (assets/
 * scramble.js). The island is named "faq-scramble" (not "faq-list") so the
 * legacy accordion in assets/dist/fc.js never binds to it.
 *
 * The marker is ASCII "[+]" / "[-]" on purpose. It is set in Qaroxe, which
 * carries ASCII only — a typographic minus (U+2212) has no glyph there and the
 * browser would quietly draw that one character in a different font.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$rows    = fc_section_data($section);

/* Empty eyebrow AND empty title, so fc_section_open() prints neither. The
   section's name still exists in the registry, so the sidebar nav is unchanged —
   this only removes the heading from the section itself. */
fc_section_open($section, [
    'eyebrow_el' => '',
    'eyebrow_en' => '',
    'title_el'   => '',
    'title_en'   => '',
    'class'      => 'fc-faq-section',
]);
?>
    <ul class="fc-faq-list" <?php echo fc_island_attrs('faq-scramble'); ?>>
        <?php foreach ($rows as $row) :
            // Active language only: one question line that scrambles into its
            // own answer in place (assets/faq.js binds to [data-fc-faq-line]).
            $q = fc_one(fc_bi($row, 'question'));
            $a = fc_one(fc_bi($row, 'answer'));
            if ($q === '') continue;
            /* Both halves of every line exist twice: the plain text the scramble
               animates, and the HTML to settle on. A question takes the site's
               markers now as an answer always has — *this* in the accent's pixel
               face, %this% as a small grey aside, [this](url) as a link — so it
               needs the same pair. */
            $q_plain = fc_format_plain($q);
            $q_html  = fc_format($q);
            $a_plain = fc_format_plain($a);
            $a_html  = fc_format($a);
            ?>
            <li class="fc-faq-item" data-fc-faq-item>
                <!-- Using a div with role="button" (not a <button>) so the answer HTML
                     can legally contain <a> tags after the scramble swap. -->
                <div role="button" tabindex="0" class="fc-faq-row fc-btn-size" data-fc-faq-toggle aria-expanded="false">
                    <?php // aria-hidden: aria-expanded on the row already says open/closed. ?>
                    <span class="fc-faq-marker fc-arrow" data-fc-faq-marker aria-hidden="true">[+]</span>
                    <span class="fc-faq-line fc-btn"
                          data-fc-faq-line
                          data-fc-q="<?php echo esc_attr($q_plain); ?>"
                          data-fc-q-html="<?php echo esc_attr($q_html); ?>"
                          data-fc-a="<?php echo esc_attr($a_plain); ?>"
                          data-fc-a-html="<?php echo esc_attr($a_html); ?>"><?php echo $q_html; ?></span>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (empty($rows)) : ?>
            <li><?php fc_render_tba('faq'); ?></li>
        <?php endif; ?>
    </ul>
<?php
fc_section_close();
?>
<style>
/* ── Full-bleed rows, text on the normal column ──────────────────────────────
   fc_section_open() wraps every section in `max-w-[1440px] mx-auto px-4 md:px-8
   py-24 md:py-40`. All of it is lifted here, so each row — its hairline and its
   blue fill — runs from one edge of the section to the other.

   The TEXT is put back where every other section's content sits by the row's own
   padding below, which reproduces that wrapper's arithmetic. So the lines and the
   fill are full width while the questions stay on the page's column.

   "Edge of the section", not of the window: on desktop the landing page holds a
   200px column for the nav rail (front-page.php), and bleeding into it would
   slide the questions under the sidebar. */
.fc-faq-section > div {
    max-width: none;
    padding-inline: 0;
    padding-block: 0;
}

.fc-faq-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
/* A hairline between rows. Not on the last one: the next section brings its own
   border-t, and the two together read as a double rule. */
.fc-faq-item {
    border-bottom: 1px solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
}
.fc-faq-item:last-child { border-bottom: 0; }

.fc-faq-row {
    position: relative;
    display: flex;
    /* Not baseline: the marker is centred on the question's first line — see
       .fc-faq-marker. */
    align-items: flex-start;
    gap: clamp(0.75rem, 2vw, 1.5rem);
    /* THE SIDE PADDING IS THE OTHER SECTIONS' GUTTER, REBUILT.
     *
     * The row is full-bleed, so this is what puts the text back on the column the
     * rest of the page uses — `max-w-[1440px] mx-auto px-4 md:px-8` says the
     * content starts one gutter in, plus half of whatever the viewport has over
     * 1440px. That is exactly the expression below: 100% is the section's width,
     * so the max() is 0 until the section is wider than the measure and takes over
     * the centring from there.
     *
     * No viewport units. The section is inset by the 200px nav rail on desktop,
     * so 50vw is not its width — the same trap the sponsors belt documents. */
    padding: clamp(1.25rem, 2.5vw, 2rem)
             calc(var(--fc-faq-gutter) + max(0px, (100% - var(--fc-faq-measure)) / 2));
    cursor: var(--fc-cur-pointer, pointer);
    color: var(--color-ink, #0A0A0A);
    /* Makes this element a stacking context, so the fill below can sit at
       z-index:-1 — behind the text, but still in front of the section. */
    isolation: isolate;
    /* The marker's size as a fraction of the row's, and the line-height of the
       type. Both are read back by the marker to work out its own box, so the two
       cannot drift apart. */
    --fc-faq-marker-scale: 0.54;
    --fc-faq-leading: 1.15;
    /* The page's measure and gutter — `max-w-[1440px]` and `px-4 md:px-8` on
       every other section. Keep these two in step with fc_section_open(). */
    --fc-faq-measure: 1440px;
    --fc-faq-gutter: 1rem;
}
@media (min-width: 768px) {
    .fc-faq-row { --fc-faq-gutter: 2rem; }
}
/* The SIZE is .fc-btn-size, in the markup — the buttons' size, from the same
   token the sponsor tier titles use. It is on the ROW, so the question, the
   answer that replaces it and the [+] marker are all set at it and share one
   baseline. Its line-height of 1 is a button's, meant for one short line; the
   two rules below give the wrapped lines of a long answer room to breathe
   without touching the size. Set on the children, so they beat the value
   inherited from .fc-btn-size rather than having to out-specify it. */
.fc-faq-line,
.fc-faq-marker { line-height: var(--fc-faq-leading, 1.15); }

/* The hover fill: a blue panel that grows from the edge OPPOSITE the one the
   pointer crossed — enter over the top and it rises from the bottom to meet you.
   Which edge that is gets written into --fc-faq-from by assets/faq.js on
   mouseenter, and again on mouseleave so the fill drops away from the pointer.
   Keyboard focus has no pointer, so it falls back to top.

   Transform only — no height/top animation — so it runs on the compositor and
   cannot reflow the page. ~150ms with a hard-out curve is the "snappy" part: it
   arrives almost immediately and settles rather than easing lazily in. */
.fc-faq-row::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    background: var(--color-accent, #0033FF);
    transform: scaleY(0);
    transform-origin: center var(--fc-faq-from, top);
    transition: transform 150ms cubic-bezier(0.2, 0, 0, 1);
}
/* Colour only. The text used to shift right as the fill landed; it holds still
   now, so the only thing that moves is the blue. */
.fc-faq-row > * {
    transition: color 100ms ease;
}
.fc-faq-marker {
    flex: 0 0 auto;
    /* Matches the ">" in the buttons — the DRAWN size, not the font-size.
     *
     * Both are Qaroxe, but the glyphs are not the same height in the em box:
     * ">" is 0.438em of ink and "[" is 0.812em, so at one font-size the bracket
     * comes out about 1.85x taller. 0.54em cancels that (0.54 x 0.812 = 0.438),
     * and being a ratio it holds at both the phone and desktop sizes rather than
     * needing a second number per breakpoint.
     *
     * Measured from assets/fonts/Qaroxe.ttf; re-measure if the file is replaced. */
    font-size: calc(1em * var(--fc-faq-marker-scale, 0.54));
    /* Both markers are three glyphs, but [+] and [-] need not be the same width
       in a bitmap face — a floor stops the question nudging sideways on toggle.
       In em, so it tracks the marker's own size. */
    min-width: 2em;
    /* CENTRED ON THE QUESTION'S FIRST LINE, not on the row: an opened answer is
       several lines tall, and a marker centred on all of them would drift down
       the block and no longer line up with anything.
       The box is made exactly one line of the QUESTION tall and the glyph is
       centred in it. One line is `leading x row-font-size`, and 1em here is the
       marker's own (smaller) size, so the row's em is 1em / scale. */
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    height: calc(1em * var(--fc-faq-leading, 1.15) / var(--fc-faq-marker-scale, 0.54));
}
.fc-faq-line {
    display: block;
    flex: 1 1 auto;
    /* A flex item will not shrink below its content without this, which is what
       lets a long answer wrap inside the row instead of overflowing it. */
    min-width: 0;
}

/* ── A link inside an answer: built like a button ────────────────────────────
   Ink words with the site's ">" after them, rather than the blue words
   .fc-link is everywhere else. An answer is set in the question's own face and
   size, so a link in it is reading as a line of the sentence — the two-colour
   button gesture (ink label, accent arrow) tells you it is clickable without
   colouring the words themselves.

   Only in the FAQ: the same helper prints links in the Code of Conduct and the
   venue cards, and those are body copy, where blue is right. */
.fc-faq-line .fc-link.fc-link {
    color: var(--color-ink, #0A0A0A);
    text-decoration: none;
}
/* The arrow, as a pseudo-element rather than a span, because the answer's HTML
   comes from fc_format_inline_links() — shared with those other sections — and
   this treatment is this section's alone. Nothing is underlined in either state:
   the arrow is what says the words are clickable. */
.fc-faq-line .fc-link.fc-link::after {
    content: ">";
    display: inline-block;
    margin-left: 0.3em;
    font-family: "Qaroxe", var(--font-mono, "JetBrains Mono"), ui-monospace, monospace;
    /* One weight in the face; a synthesised bold smears the pixel steps. */
    font-weight: 400;
    font-synthesis: none;
    letter-spacing: 0;
    color: var(--color-accent, #0033FF);
}

/* Pointer devices only: :hover latches after a tap on a phone, so a tapped row
   would stay blue for good. Touch gets the answer swap, which is the feedback
   that matters there. */
@media (hover: hover) {
    .fc-faq-row:hover::before { transform: scaleY(1); }
    .fc-faq-row:hover { color: #fff; }
    /* The marker goes to the house orange on the blue, like the ">" in a hovered
       button. Doubled class, to outrank .fc-arrow.fc-arrow's accent colour. */
    .fc-faq-row:hover .fc-faq-marker.fc-faq-marker { color: var(--fc-accent-hot, #EE8101); }
    /* A link inside an open answer is ink — invisible on the blue fill. White
       words, and its ">" to the house orange, exactly as a hovered button: no
       underline in either state, since the arrow is what says it is a link. */
    .fc-faq-row:hover .fc-link.fc-link { color: #fff; text-decoration: none; }
    .fc-faq-row:hover .fc-link.fc-link::after { color: var(--fc-accent-hot, #EE8101); }
    /* A *marked* run is accent blue too, and goes where the marker goes rather
       than to white: it is emphasis, like the [+], not part of the sentence the
       way a link is. Doubled class to clear .fc-accent.fc-accent's own colour. */
    .fc-faq-row:hover .fc-accent.fc-accent { color: var(--fc-accent-hot, #EE8101); }
}

/* Touch: no hover at all, so the OPEN question is the one that goes blue, and it
   stays blue while the answer is showing. The switch is (hover: none) — whether
   the device has a mouse — not the width, so a desktop window dragged narrow
   keeps hovering and a phone never does.

   The fill still rises from the edge --fc-faq-from names; nothing writes it here
   (there is no pointer to come in from), so it takes its default. */
@media (hover: none) {
    .fc-faq-row[aria-expanded="true"]::before { transform: scaleY(1); }
    .fc-faq-row[aria-expanded="true"] { color: #fff; }
    .fc-faq-row[aria-expanded="true"] .fc-faq-marker.fc-faq-marker { color: var(--fc-accent-hot, #EE8101); }
    .fc-faq-row[aria-expanded="true"] .fc-link.fc-link { color: #fff; text-decoration: none; }
    .fc-faq-row[aria-expanded="true"] .fc-link.fc-link::after { color: var(--fc-accent-hot, #EE8101); }
    .fc-faq-row[aria-expanded="true"] .fc-accent.fc-accent { color: var(--fc-accent-hot, #EE8101); }
}

/* Keyboard: the same state, so tabbing through the list shows where you are.
   Not inside the hover query — a keyboard is not a pointer. */
.fc-faq-row:focus-visible {
    outline: none;
    color: #fff;
}
.fc-faq-row:focus-visible::before { transform: scaleY(1); }
.fc-faq-row:focus-visible .fc-faq-marker.fc-faq-marker { color: var(--fc-accent-hot, #EE8101); }
.fc-faq-row:focus-visible .fc-link.fc-link { color: #fff; text-decoration: none; }
.fc-faq-row:focus-visible .fc-link.fc-link::after { color: var(--fc-accent-hot, #EE8101); }
.fc-faq-row:focus-visible .fc-accent.fc-accent { color: var(--fc-accent-hot, #EE8101); }

@media (prefers-reduced-motion: reduce) {
    .fc-faq-row::before,
    .fc-faq-row > * { transition: none; }
}
</style>
