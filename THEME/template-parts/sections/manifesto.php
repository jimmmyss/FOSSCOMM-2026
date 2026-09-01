<?php
/**
 * Manifesto — desktop: body (active language) on the left half, stats stacked on
 * the right half with a vertical centre line between them. Mobile: body on top,
 * a horizontal line, then the stats stacked. Consecutive stats are divided by a
 * line in both layouts.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title   = fc_bi($data, 'title');
$body    = fc_bi($data, 'body');
$stats   = (array) ($data['stats'] ?? []);
$mf      = fc_manifesto_style();

/**
 * Which part of a stat number is drawn hollow: whatever is *starred*.
 *
 * It used to be every "+", found automatically. Marking it is better for the
 * same reason the CFP heading marks its own: an automatic rule has to guess, and
 * a guess is wrong the moment somebody wants "*500*+" or a stat with no plus in
 * it at all. One convention across all four hollow headings, and the author says
 * where.
 *
 * `data-fc-island="scramble"` goes on EVERY run, which is why fc_hollow_markup()
 * takes the attribute rather than the template wrapping things itself:
 * assets/dist/fc.js animates by assigning `el.textContent`, which destroys child
 * elements on the first frame. A hollow span nested inside a scrambled box loses
 * its class immediately and never gets it back, so nothing may nest — each run
 * is its own island and scrambles independently.
 *
 * Nothing is aria-hidden. The runs are adjacent inline spans, so a screen reader
 * reads "500 plus", which is the number. An earlier attempt hid the plus and put
 * an aria-label on the wrapping <div>; aria-label on a plain div with no role is
 * not reliably announced.
 */
$fc_stat_number = static function (string $number): string {
    return fc_hollow_markup($number, 'fc-mf-hollow', 'data-fc-island="scramble"');
};

fc_section_open($section, [
    'title_el' => $title['el'],
    'title_en' => $title['en'],
]);

$body_text = fc_one($body);
?>
<!-- Desktop: paragraph (left half) │ vertical centre line │ stats (right half),
     vertically centred. Mobile: paragraph on top, a horizontal line, then the
     stats stacked one-per-row. In BOTH, consecutive stats are separated by a
     divider line (N stats → N−1 lines). -->
<style>
/* ── Stat numbers ───────────────────────────────────────────────────────────
   The number is set exactly as a speaker's name is: the same display face at
   700, the same tight tracking. It was already font-display, so what was
   missing was the WEIGHT and the -0.05em — a heading at the default weight
   beside a name at 700 reads as a different font even though it is the same
   one.

   --fc-mf-plus / --fc-mf-plus-hover are written inline on the wrapper below,
   from the dashboard colours. */
.fc-mf-stat {
    --fc-mf-plus-now: var(--fc-mf-plus, #0033FF);
}
.fc-mf-num {
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    /* 700 — the speaker name's weight exactly, which is the point of the change. */
    font-weight: 700;
    letter-spacing: -0.05em;
    /* A step down from the original 60/72px.
     *
     * Not a taste tweak on its own: at 700 the number carries noticeably more
     * ink than it did at .font-display's default 500, so the SAME size read
     * heavier and larger than before the weight changed. Taking about 10% off
     * lands it back where it sat visually while keeping the new weight. */
    font-size: 3.375rem;              /* 54px, was 3.75rem */
    /* Plain ink, and it stays ink — on hover too. Only the *starred* run takes a
       colour, which is what makes it read as the accent rather than as the odd
       one out in a number that is already entirely coloured. */
    color: var(--color-ink, #0A0A0A);
}
/* Tailwind's md. The original stepped 3.75rem → 4.5rem here; this keeps the
   same proportion between the two, one step down. */
@media (min-width: 768px) {
    .fc-mf-num { font-size: 4rem; }   /* 64px, was 4.5rem */
}

/* The *starred* run: hollow, in the accent, exactly the speakers' last-line
 * treatment. Which part that is comes from asterisks in the field — it used to
 * be every "+", found automatically, and an automatic rule has to guess.
 *
 * The colour is set OUTSIDE the @supports deliberately. Where text stroking
 * works the rule below paints the fill transparent and this is irrelevant;
 * where it does not, the run stays solid but still coloured — so it is never
 * invisible text, which `color: transparent` with no stroke behind it would be.
 *
 * Note the literal fallback in every var(). `color: var(--x)` with no fallback
 * is invalid-at-computed-value-time if the variable fails to resolve, and an
 * invalid `color` INHERITS — which here is ink, so a missing variable would
 * silently render it solid black rather than accent. Same trap the speakers'
 * hollow name documents. */
.fc-mf-hollow {
    color: var(--fc-mf-plus-now, #0033FF);
    /* inline-block so the stroke is not clipped by the line box. Every run is
       its own scramble island now, this one included, so it types in with the
       rest rather than sitting there from the first paint. */
    display: inline-block;
}
@supports (-webkit-text-stroke: 1px black) {
    .fc-mf-hollow {
        color: transparent;
        /* In em, so the outline stays in proportion as the number scales with
           the viewport. A fixed pixel width looked wiry at the large end. */
        -webkit-text-stroke: 0.035em var(--fc-mf-plus-now, #0033FF);
    }
}

/* The highlighted state, however it was reached.
 *
 * DELIBERATELY OUTSIDE the @media (hover: hover) below, because a phone has to
 * have it: there is no pointer there, so assets/centre-highlight.js puts .is-hot
 * on whichever stat is nearest the middle of the screen and moves it as you
 * scroll. One at a time, the same rule the speakers row uses for the card
 * nearest the centre of the belt. Putting this inside the hover query is the
 * mistake that made the speakers' mobile highlight do nothing. */
.fc-mf-stat.is-hot {
    --fc-mf-plus-now: var(--fc-mf-plus-hover, #EE8101);
}

/* Pointer devices only. On a phone :hover latches after a tap and the plus
   would stay on its hover colour for good — which is why the positional
   highlight above exists instead. Hovering the whole stat rather than the glyph
   itself: a "+" is a few pixels of ink, and the row is the thing a reader is
   actually looking at. */
@media (hover: hover) {
    .fc-mf-stat:hover {
        --fc-mf-plus-now: var(--fc-mf-plus-hover, #EE8101);
    }
}
/* Outside the query too: the touch highlight slides from stat to stat as you
   scroll, and without a transition the colour would snap. */
.fc-mf-hollow {
    transition: color 50ms ease, -webkit-text-stroke-color 50ms ease;
}
</style>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-0 items-stretch">
    <div class="text-lg leading-relaxed space-y-3 md:pr-12">
        <?php if ($body_text !== '') echo wp_kses_post(fc_format_block($body_text)); ?>
    </div>
    <?php if (!empty($stats)) : ?>
        <div class="border-t md:border-t-0 md:border-l border-border pt-8 md:pt-0 md:pl-12 flex flex-col justify-center"
             style="--fc-mf-plus: <?php echo esc_attr($mf['plus']); ?>;
                    --fc-mf-plus-hover: <?php echo esc_attr($mf['plus_hover']); ?>;">
            <div class="divide-y divide-border" data-fc-centre>
                <?php foreach ($stats as $stat) :
                    $number = (string) ($stat['number'] ?? '');
                    $label  = fc_bi($stat, 'label');
                    if ($number === '' && $label['el'] === '' && $label['en'] === '') continue;
                    ?>
                    <div class="fc-mf-stat py-8 first:pt-0 last:pb-0" data-fc-centre-item>
                        <!-- Size, face, weight and tracking all live in
                             .fc-mf-num now. It was on the Tailwind classes while
                             the size was unchanged; now that it is deliberately
                             a step down, it belongs beside the reason. -->
                        <div class="fc-mf-num leading-none">
                            <?php echo $fc_stat_number($number); ?>
                        </div>
                        <div class="mt-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                            <?php echo fc_bi_inline($label['el'], $label['en']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
fc_section_close();
