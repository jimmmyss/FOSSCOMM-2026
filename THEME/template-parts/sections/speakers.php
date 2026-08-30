<?php
/**
 * Speakers — a row of cut-out portraits standing on the bottom of the section.
 *
 * No card, no panel, no background of its own: the section's paper dot-grid runs
 * straight through and each speaker is just three things stacked on it —
 *
 *   NAME    one word per line, in the display face. The LAST line is always
 *           outlined rather than filled, which is the whole signature of the
 *           layout: "GABE" solid, "NEWELL" hollow underneath it.
 *   ROLES   small mono lines under the name, one per line as entered.
 *   PHOTO   a cut-out with an outline around it, standing ON the section's
 *           bottom edge — not floating in the middle of the section's height.
 *           The section's bottom padding is cancelled for exactly this reason.
 *
 * The row behaves one of two ways, decided at runtime by
 * assets/speakers-carousel.js from the measured widths:
 *
 *   • Not enough speakers to overflow — they sit centred and still. No drag, no
 *     duplication. A three-speaker conference should not have a carousel
 *     limping along with a gap in it.
 *   • Wider than the screen — the list is duplicated and drifts continuously,
 *     and can be dragged on desktop. No arrow buttons: the row already moves on
 *     its own, and dragging is the control.
 *
 * The markup here is the STILL version. Everything the moving version needs is
 * added by the script, so the section renders correctly with JS off.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section  = $args['section'] ?? [];
$speakers = fc_section_data($section);
if (!is_array($speakers)) $speakers = [];

$meta = fc_section_meta('speakers', [
    'title_el' => 'Άνθρωποι που εμφανίστηκαν',
    'title_en' => 'People who showed up.',
]);
$style = fc_speakers_style();

/*
 * The outline's shape, as a blur followed by a hard threshold.
 *
 * Blurring the alpha turns the silhouette into a distance ramp: at d pixels
 * outside the edge the blurred alpha is about 0.5 * erfc(d / (sigma * sqrt(2))).
 * Slicing that ramp at a chosen height gives a ring of a chosen WIDTH, and
 * slicing it steeply gives a ring with a HARD edge — the threshold is what
 * removes the anti-aliasing, and the blur is what makes the offset Euclidean, so
 * corners come out round instead of square.
 *
 * feFuncA computes A' = slope * A + intercept, clamped to 0..1. The edge lands
 * where A' = 0.5, so:
 *
 *     intercept = 0.5 - slope * blurredAlphaAt(width)
 *
 * With sigma 3 and a 4.5px ring, blurredAlphaAt(4.5) is about 0.068, so a slope
 * of 60 gives an intercept of about -3.6. The slope is the sharpness dial: 60
 * turns roughly 1.7% of the alpha range into the whole transition, which at this
 * scale is a fraction of a pixel — effectively no anti-aliasing at all. Drop it
 * toward 20 if the curves ever look stair-stepped.
 *
 * This replaced feMorphology, which was crisp but dilates with a RECTANGULAR
 * kernel — fine at 2px, visibly chunky at the corners once the ring got fat.
 */
$rim_sigma     = 3;
$rim_slope     = 60;
$rim_intercept = -3.6;

// Rendered rows only — a nameless entry is skipped.
$cards = [];
foreach (array_values($speakers) as $sp) {
    $name = trim((string) ($sp['name'] ?? ''));
    if ($name === '') continue;
    // One word per line. PREG_SPLIT_NO_EMPTY so a double space does not become
    // a blank line, and the whole name is the fallback if the split yields
    // nothing at all.
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $words = $words ?: [$name];

    foreach ($words as $word) {
        $len = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
        // The longest word ACROSS THE WHOLE SECTION, not per speaker.
        //
        // Sizing each name to its own longest word made every name a different
        // size — "GABE" large, "STENBERG" noticeably smaller — which read as
        // inconsistent rather than as fitted. One number for the section means
        // one font size for the section: the longest name in the list decides
        // it, and every other name is set at that same size with room to spare.
        $longest = max($longest ?? 1, $len);
    }

    $cards[] = [
        'name'  => $name,
        'words' => $words,
        'photo' => (string) ($sp['photo'] ?? ''),
        'roles' => fc_lines(fc_one(fc_bi($sp, 'roles'))),
        'url'   => (string) ($sp['url'] ?? ''),
    ];
}
$longest = max(1, (int) ($longest ?? 1));

// `fc-section-flush` cancels the section's bottom padding so the portraits can
// stand on its edge. Only when there is something to stand there — the TBA
// placeholder still wants its normal breathing room.
$section_class = 'fc-section-dots' . ($cards ? ' fc-section-flush' : '');
fc_section_open($section, array_merge($meta, ['class' => $section_class]));
?>
    <?php if (empty($cards)) : ?>
        <?php fc_render_tba('speakers'); ?>
    <?php else : ?>
        <?php
        /*
         * The outline, as an SVG filter. Inline, and it has to be — Safari does
         * not resolve `filter: url()` against an external file, only against a
         * same-document fragment.
         *
         * This replaces eight chained CSS drop-shadows, which could not be made
         * crisp. `filter` functions are space-separated and each one filters the
         * PREVIOUS one's output, so the eight compounded three ways: the source's
         * anti-aliased alpha climbed (0.5 -> 0.75 -> 0.875 …) instead of ever
         * resolving to a hard edge; the offsets ADDED along aligned directions,
         * making the ring lumpy and thicker in some directions than others; and
         * the diagonal offsets were fractional pixels, so each of the eight
         * stages resampled and added half a pixel of blur. Mush, by construction.
         * (box-shadow and text-shadow take a comma list, all drawn from the same
         * source — that is why the equivalent text trick looks sharp.)
         *
         * What replaced them is a blur used as a SHAPE, not as a softening:
         * feGaussianBlur spreads SourceAlpha into a ramp, then feComponentTransfer
         * puts a very steep linear ramp through the alpha (slope 60) so everything
         * above the threshold snaps to 1 and everything below to 0. A hard edge,
         * at a true Euclidean distance from the silhouette — so it rounds corners
         * instead of squaring them off, which an feMorphology dilate does not: its
         * kernel is a rectangle and reads chunky past about 3px.
         *
         * THE PHOTO ITSELF IS NEVER BLURRED. The blur runs on SourceAlpha only,
         * and the feMerge at the end puts the untouched SourceGraphic back on top
         * of the ring. If a portrait looks soft, it is the file being upscaled —
         * the frame is 420px and doubles on a retina screen, so the media picker
         * is told to keep the original upload rather than WordPress's 300px
         * "medium" derivative (see the `full` flag in the Speakers admin).
         *
         * Two filters rather than one because `flood-color: currentColor`
         * resolves against the <feFlood> element itself, not the element
         * referencing the filter — a single shared filter cannot take a
         * per-speaker colour. One filter per colour is the documented answer.
         *
         * Notes on the attributes, each of which is load-bearing:
         *   color-interpolation-filters="sRGB" — SVG filters default to linearRGB,
         *     and the 8-bit round trip darkens and bands the anti-aliased edge.
         *   in="SourceAlpha" — blurring SourceGraphic would spread each channel
         *     independently and fringe the colour.
         *   the region — the default is a hard clip on the OUTPUT, and a ring
         *     drawn outside it is simply cut off; 120% clears the ring at every
         *     size the portrait is drawn.
         */
        ?>
        <svg class="fc-spk-defs" width="0" height="0" aria-hidden="true" focusable="false">
            <defs>
                <?php foreach (['rest' => $style['rim'], 'hot' => $style['rim_hover']] as $key => $colour) : ?>
                    <filter id="fc-spk-rim-<?php echo esc_attr($key); ?>"
                            x="-10%" y="-10%" width="120%" height="120%"
                            color-interpolation-filters="sRGB">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="<?php echo esc_attr($rim_sigma); ?>" result="spread"/>
                        <feComponentTransfer in="spread" result="ring">
                            <feFuncA type="linear"
                                     slope="<?php echo esc_attr($rim_slope); ?>"
                                     intercept="<?php echo esc_attr($rim_intercept); ?>"/>
                        </feComponentTransfer>
                        <feFlood flood-color="<?php echo esc_attr($colour); ?>" result="paint"/>
                        <feComposite in="paint" in2="ring" operator="in" result="outline"/>
                        <feMerge>
                            <feMergeNode in="outline"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                <?php endforeach; ?>
            </defs>
        </svg>

        <div class="fc-spk-wrap" data-fc-speakers
             style="--fc-spk-rim: <?php echo esc_attr($style['rim']); ?>;
                    --fc-spk-rim-hover: <?php echo esc_attr($style['rim_hover']); ?>;
                    --fc-spk-longest: <?php echo (int) $longest; ?>;">

            <div class="fc-spk-viewport" data-fc-spk-viewport>
                <ol class="fc-spk-rail" data-fc-spk-rail>
                    <?php foreach ($cards as $i => $card) :
                        $tag  = $card['url'] !== '' ? 'a' : 'div';
                        $last = count($card['words']) - 1;
                        ?>
                        <li class="fc-spk">
                            <<?php echo $tag; ?> class="fc-spk-card"
                                <?php if ($card['url'] !== '') : ?>
                                    href="<?php echo esc_url($card['url']); ?>"
                                <?php endif; ?>>
                                <?php // aria-label carries the whole name: split across
                                      // <span>s a screen reader would otherwise read it
                                      // as separate words on separate lines. ?>
                                <h3 class="fc-spk-name" aria-label="<?php echo esc_attr($card['name']); ?>">
                                    <?php foreach ($card['words'] as $wi => $word) : ?>
                                        <span<?php echo $wi === $last ? ' class="is-outline"' : ''; ?>
                                              aria-hidden="true"><?php echo esc_html($word); ?></span>
                                    <?php endforeach; ?>
                                </h3>

                                <?php if ($card['roles']) : ?>
                                    <ul class="fc-spk-roles">
                                        <?php foreach ($card['roles'] as $role) : ?>
                                            <li><?php echo fc_format($role); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="fc-spk-photo">
                                    <?php if ($card['photo'] !== '') : ?>
                                        <?php
                                        // ONE copy. There were briefly two, cross-faded with
                                        // opacity to animate the hover colour — and that is what
                                        // turned the outline muddy.
                                        //
                                        // The ring's outer edge is anti-aliased, so it is
                                        // semi-transparent in BOTH copies. Stacking them means
                                        // that fringe composites orange over blue and lands on
                                        // brown, at every opacity including 1. Two soft edges
                                        // instead of one, and a colour that was never chosen.
                                        //
                                        // The carousel needs the image to be same-origin and
                                        // readable: it samples the alpha into a mask so hovering
                                        // a transparent corner does not count as hovering the
                                        // speaker. Media-library uploads are.
                                        ?>
                                        <?php // draggable="false": an image's native drag-and-drop
                                              // competes with the carousel's own, and wins — grab a
                                              // photo and the belt does not move at all. ?>
                                        <img class="fc-spk-shot"
                                             src="<?php echo esc_url($card['photo']); ?>"
                                             alt="<?php echo esc_attr($card['name']); ?>"
                                             draggable="false"
                                             loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>"
                                             decoding="async">
                                    <?php endif; ?>
                                </div>
                            </<?php echo $tag; ?>>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    <?php endif; ?>
<?php
fc_section_close();
?>
<style>
/* ── Speakers row ───────────────────────────────────────────────────────────
   --fc-spk-rim / --fc-spk-rim-hover / --fc-spk-longest are written inline on
   .fc-spk-wrap above, from the dashboard colours and the section's longest
   name-word. */

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
    /* Width of the fixed section-nav rail that front-page.php reserves with
       `lg:pl-[200px]`. The row bleeds to the edge of the CONTENT area, not the
       edge of the window — bleeding the full 100vw ran the carousel underneath
       the sidebar, where cards were half-hidden behind it and the belt appeared
       to start off-screen. Zero below lg, where the nav is a horizontal bar. */
    --fc-rail: 0px;

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
    --fc-spk-pad: 24px;
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
    /* Full-bleed to the CONTENT area's edges, not the window's.
     *
     * `50% - 50vw` is the usual escape-the-container trick, and it assumes the
     * container is centred in the viewport. Here it is centred in the viewport
     * MINUS the rail, whose centre sits --fc-rail/2 to the right — so the shift
     * has to be corrected by exactly that, or the row starts under the sidebar.
     * html/body carry overflow-x: clip, which stops the vw maths from ever
     * producing a horizontal scrollbar. */
    margin-left: calc(50% - 50vw + var(--fc-rail) / 2);
    margin-right: calc(50% - 50vw + var(--fc-rail) / 2);
    width: calc(100vw - var(--fc-rail));
    max-width: none;
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
    .fc-spk-wrap { margin-top: 96px; }      /* 64 + 96 = 160, matching py-40 */
}

/* Matches front-page.php's `lg:pl-[200px]` and section-nav.php's `lg:w-[200px]`.
   Change one, change all three. */
@media (min-width: 1024px) {
    .fc-spk-wrap { --fc-rail: 200px; }
}

.fc-spk-viewport {
    overflow: hidden;
    /* The row is dragged, so the browser's own horizontal panning must not also
       claim the gesture. Vertical scrolling still works. */
    touch-action: pan-y;
    /* A drag across a row of names and photographs is a drag, not a selection.
       Without this the browser starts highlighting text the moment the pointer
       moves with the button down, and the blue selection follows the cursor
       across the whole section. */
    user-select: none;
    -webkit-user-select: none;
}

/* Images are natively draggable, and that gesture COMPETES with ours: grab a
   photo and the browser starts its own drag-and-drop, so the belt does not
   move at all. `draggable="false"` on the tag is the primary defence and this
   is the CSS half of it. */
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
    /* Centred until the script decides there is enough to scroll. */
    justify-content: center;
}

/* Set by the script once it knows the row overflows: it is a moving belt now,
   so it starts at the left — and only THEN is it worth a compositing layer.
   `will-change` on a rail that never moves is a layer held for nothing. */
.fc-spk-wrap.is-looping .fc-spk-rail {
    justify-content: flex-start;
    will-change: transform;
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
.fc-spk-name span {
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

/* ── Roles ──────────────────────────────────────────────────────────────── */
.fc-spk-roles {
    list-style: none;
    /* Tucked close under the name: it is a caption on it, not a separate block.
       At 16px it read as floating between two much larger things. */
    margin: 5px 0 0;
    padding: 0;
    font-family: var(--font-mono, "JetBrains Mono"), ui-monospace, monospace;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1.6;
    color: var(--color-ink-muted, #6B6B66);
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
    /* The colour corrections run as CSS functions, then the SVG filter takes
       their output as its SourceGraphic and draws the ring around it.
       The swap below is a straight substitution — one filter or the other, never
       both — so the outline is exactly one colour with exactly one edge. */
    filter: grayscale(1) contrast(1.06) brightness(1.02) url(#fc-spk-rim-rest);
}
/* Highlighted: the grey comes off and the photograph is in colour.
 *
 * No `transition`. `filter: url(#a)` cannot interpolate into `url(#b)`, so a
 * transition on this property would animate grayscale() and then jump the
 * outline colour at the end — two changes at two different times, which is the
 * exact fault that was chased out of here once already. Both switch in one
 * repaint instead, along with the name.
 *
 * The ring is unaffected either way: it comes from feFlood, not from the
 * photo's own pixels, so it stays the colour it was told to be. */
.fc-spk-card.is-hot .fc-spk-shot {
    filter: contrast(1.06) brightness(1.02) url(#fc-spk-rim-hot);
}

/* Hover swaps the rim by redefining the variable on the card, so the filter
   above and the name's colour are written once each and both follow. Pointer
   devices only: on a phone :hover latches after a tap and it would stay changed.
 *
 * INSTANT, and that is a deliberate retreat from a 200ms fade.
 *
 * The fade cost more than it was worth. `filter: url(#a)` cannot interpolate
 * into `url(#b)`, so the only way to animate the photo's outline was to stack
 * two pre-filtered copies and cross-fade them — and the ring's outer edge is
 * anti-aliased, so it is semi-transparent in BOTH copies. That fringe then
 * composited orange over blue and landed on brown, at every opacity including
 * 1: two soft edges instead of one, in a colour nobody chose. It also made the
 * first hover of each speaker stutter, because the second copy had never been
 * rasterised until then.
 *
 * One image and a straight filter substitution is one edge, one colour, no
 * first-hover cost, and identical on every card. The name switches in the same
 * repaint, so the two still move together.
 *
 * Getting the fade back properly means a per-card SVG filter whose flood-color
 * is a CSS variable — one image, animated colour, no compositing — which needs
 * unique filter ids rewritten on every clone. Worth doing; not worth guessing at
 * without a browser to check it in.
 */
/* Driven by a CLASS, not :hover.
 *
 * :hover fires anywhere in the card's box, and the box is mostly empty air —
 * the strip beside a narrow name, the transparent margin around a cut-out. That
 * meant the row stopped and recoloured while the pointer was nowhere near the
 * speaker. speakers-carousel.js sets this only for the name, the roles and the
 * photo, so "on the speaker" means on something you can actually see. */
.fc-spk-card.is-hot { --fc-spk-rim: var(--fc-spk-rim-hover, #FF6A2B); }

/* No arrows. Dragging is the whole control on desktop, and the row drifts on
   its own regardless — a pair of buttons over a moving belt was one affordance
   too many. */
/* `(hover: hover)` alone, NOT `and (pointer: fine)`.
 *
 * Those media features describe the PRIMARY pointer, and on a touchscreen
 * laptop the primary pointer is the finger — so a machine with both a
 * touchscreen and a mouse reports `pointer: coarse` and the whole block was
 * skipped. That is why none of the hover behaviour appeared: not the cursors
 * here, and not the JS that pauses the row, which was gated the same way.
 * `hover: hover` on its own is the honest question — can this input hover at
 * all — and a phone still answers no. */
@media (hover: hover) {
    /* Three states, decided by the script and declared on the WRAP, so one
       cursor covers the whole row and there is never a stale one inherited on
       some inner element.
     *
     * A CSS-only version cannot express this: the cursor has to change on the
     * transparent parts of a cut-out photo, and CSS has no idea where the pixels
     * are. speakers-carousel.js samples the alpha and sets .is-pointing only
     * when the pointer is genuinely on the person or on the text.
     *
     * Order matters — dragging is last so it wins over both. */
    .fc-spk-wrap.is-looping  .fc-spk-viewport { cursor: var(--fc-cur-grab, grab); }
    .fc-spk-wrap.is-pointing .fc-spk-viewport { cursor: var(--fc-cur-pointer, pointer); }
    .fc-spk-wrap.is-dragging .fc-spk-viewport { cursor: var(--fc-cur-grabbing, grabbing); }

    /* …and nothing inside may override it.
     *
     * This is why the cursor was inconsistent between speakers. The site-wide
     * rule in inc/bootstrap.php — `html a[href] { cursor: … pointer }` — applies
     * DIRECTLY to a card that has a link, and a directly-applied declaration
     * always beats an inherited one whatever the specificity. So a speaker with
     * a URL showed the pointer everywhere, including over empty air and mid-drag,
     * while a speaker without one inherited correctly. Same row, two behaviours,
     * decided by whether somebody had filled in the Link field.
     *
     * `inherit` puts every descendant back under the viewport's control, and
     * (0,2,0) clears the (0,1,1) of that global rule. */
    .fc-spk-wrap .fc-spk-card,
    .fc-spk-wrap .fc-spk-card * { cursor: inherit; }
}

/* No prefers-reduced-motion block here: the hover is an instant colour swap,
   which is not motion. The one thing that does move is the row's drift, and that
   checks the setting in speakers-carousel.js. */
</style>
