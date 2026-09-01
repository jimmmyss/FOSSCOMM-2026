<?php
/**
 * Venue — MapLibre map + editions section.
 * Left: venue card. Right: the MapLibre globe map (assets/venue-map.js).
 * Editions: a vertical sticky panel on desktop + a sticky horizontal bar on
 * mobile (both below), which drive the map (hover-to-move, click-to-open).
 * Below the map: travel cards (how to get here).
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title       = fc_bi($data, 'title');
$uni_title   = fc_bi($data, 'university_title');
// No $hover_text. It fed a scramble on the old single-line title; the name is
// set one line per line now, hovering moves the outline colour instead, and the
// dashboard field is gone. Any stored value is simply no longer read.
$maps_url    = (string) ($data['google_maps_url'] ?? '');
$address     = fc_bi($data, 'address');
$info_rows   = (array)  ($data['info_rows']       ?? []);
$travel_cards = (array) ($data['travel_cards'] ?? []);
$cluster_label = (string) ($data['cluster_label'] ?? 'FOSSCOMM');
$pin_sprite      = (string) ($data['pin_sprite'] ?? '');
$spotlight_sprite = (string) ($data['spotlight_sprite'] ?? '');
$pin_scale       = (float) ($data['pin_scale'] ?? 1.0);       if ($pin_scale <= 0)       $pin_scale = 1.0;
$spotlight_scale = (float) ($data['spotlight_scale'] ?? 1.0); if ($spotlight_scale <= 0) $spotlight_scale = 1.0;
$getting_here  = fc_bi($data, 'getting_here');

/* Render one editions-sidebar item (shared by the mobile bar + desktop panel).
   Rows with an archive URL render as a real <a target="_blank"> (hover moves
   the map, click opens the archive instantly); link-less rows render as a
   <button> that shows a sass message on click. assets/venue-map.js wires the
   hover-to-move + highlight + mobile scroll-select off the data-* attributes. */
$render_edition_item = function (array $ed, string $extra_class) {
    $yr  = (int) ($ed['year'] ?? 0);
    $ct  = (string) ($ed['city'] ?? '');
    $url = (string) ($ed['url'] ?? '');
    $tag = $url !== '' ? 'a' : 'button';
    $attrs = 'class="fc-year-btn no-underline transition-colors text-ink-muted ' . esc_attr($extra_class) . '"'
        . ' data-fc-edition-year="' . esc_attr($yr) . '"'
        . ' data-fc-edition-lat="' . esc_attr($ed['lat'] ?? '') . '"'
        . ' data-fc-edition-lon="' . esc_attr($ed['lon'] ?? '') . '"'
        . ' data-fc-edition-city="' . esc_attr($ct) . '"'
        . ' data-fc-edition-url="' . esc_attr($url) . '"';
    if ($tag === 'a') {
        $attrs .= ' href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"';
    } else {
        $attrs = 'type="button" ' . $attrs;
    }
    echo '<' . $tag . ' ' . $attrs . '>';
    echo '<span class="fc-edition-text">' . esc_html($yr . ' / ' . ucfirst($ct)) . '</span>';
    echo '</' . $tag . '>';
};

// Editions data — serves as BOTH the year browser AND the globe pins.
// Normalised centrally so the desktop sidebar list (section-nav.php) and the
// mobile bar below stay in sync with the globe. See fc_venue_editions().
$editions_json_arr = fc_venue_editions();
$editions_json     = wp_json_encode($editions_json_arr);

/* Open the section manually (not using fc_section_open) so we can place the year browser
   INSIDE the <section> but OUTSIDE the max-w container. */
$id          = (string) $section['key'];
$eyebrow     = fc_section_eyebrow($section);
?>
<section id="<?php echo esc_attr($id); ?>" class="bg-paper relative border-t border-border">
    <?php if (!empty($editions_json_arr)) : ?>
        <!-- Editions bar (MOBILE ONLY — lg:hidden). A sticky horizontal nav shown
             while the venue section is on screen. On mobile the blue FOSSCOMM
             bar slides away after Home, so the persistent top chrome is the
             section nav (sticky top-0, 40px) — this sticks just below it at
             top-10. The desktop equivalent is the vertical sticky panel right
             below this. "Editions:" stays pinned far left; scrolling auto-selects
             the leftmost item (assets/venue-map.js). -->
        <nav
            aria-label="<?php echo esc_attr(fc_t('editions_label')); ?>"
            data-fc-editions-mobile
            class="
                lg:hidden
                sticky fc-bar-sub z-40 fc-bar
                bg-paper border-b border-border
                font-mono text-[11px] uppercase tracking-widest text-ink-muted
                overflow-x-auto whitespace-nowrap fc-nav-no-scrollbar flex items-center
            "
        >
            <span class="fc-editions-label shrink-0 sticky left-0 z-20 flex items-center gap-4 h-full bg-paper pl-4 pr-4 text-ink">
                <span><?php echo esc_html(fc_t('editions_label')); ?></span>
                <span class="opacity-50">//</span>
            </span>
            <?php foreach (array_reverse($editions_json_arr) as $ed) :
                $render_edition_item($ed, 'fc-edition-mobile-btn shrink-0 h-full flex items-center pr-4 whitespace-nowrap');
            endforeach; ?>
            <!-- Trailing spacer so the LAST item can scroll left far enough to
                 reach the highlight edge (assets/venue-map.js selectLeftmost). -->
            <span class="fc-edition-spacer shrink-0" aria-hidden="true"></span>
        </nav>

        <!-- Editions panel (DESKTOP ONLY — hidden lg:block). Same position:sticky
             mechanism as the mobile bar above: an absolute, section-tall rail
             (so it never displaces the venue content) pulled into the sidebar
             column at z-50, ON TOP OF the fixed section-nav. Inside, the list is
             position:sticky top-10 — bounded by the venue <section>, so it
             enters aligned to the section's top, locks, then releases at the
             section's bottom: the exact lifecycle the mobile bar has. -->
        <div class="hidden lg:block lg:absolute lg:-top-px lg:-bottom-px lg:-left-[200px] lg:w-[200px] z-40 pointer-events-none">
            <nav
                aria-label="<?php echo esc_attr(fc_t('editions_label')); ?>"
                data-fc-editions-desktop
                class="
                    sticky pointer-events-auto
                    bg-paper border-t border-r border-b border-border
                    px-5 py-8
                    font-mono text-[11px] uppercase tracking-widest text-ink-muted
                "
            >
                <div class="flex items-center gap-2 mb-6 text-ink">
                    <span><?php echo esc_html(fc_t('editions_label')); ?></span>
                    <span class="opacity-50">//</span>
                </div>
                <ul class="flex flex-col gap-y-2">
                    <?php foreach (array_reverse($editions_json_arr) as $ed) : ?>
                        <li><?php $render_edition_item($ed, 'block w-full text-left p-0'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
        <?php if ($eyebrow !== '') : ?>
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6">
                <?php echo esc_html($eyebrow); ?>
            </div>
        <?php endif; ?>
        <?php $title_text = fc_one($title); if ($title_text !== '') : ?>
            <h2 class="font-display text-4xl md:text-6xl leading-[1.0] tracking-tight mb-16"><?php echo fc_format($title_text); ?></h2>
        <?php endif; ?>

        <!-- Main content: left text | right globe. On md the right column stretches to
             the row height so the globe (justify-end) stays pinned to the bottom line
             instead of floating in the middle when it shrinks. -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start md:items-stretch">

            <!-- Left: venue card — hollow-last-word name + Google Maps link + address + info rows.
                 No `space-y-6`: the name/address pair is deliberately tighter than
                 the gap before the info table, and a uniform 24px between all three
                 is what made them read as three unrelated blocks. Each child sets
                 its own top margin in the <style> below. -->
            <div class="pb-6 md:pb-20">

                <?php
                /* The venue name, set the way a speaker's name is: uppercase, one
                 * word per line, the last word drawn hollow in the accent. The two
                 * places the site names something at size now share one treatment,
                 * which is the point.
                 *
                 * Replaced a single-line title over an address in a blue-ruled box.
                 * The rule read as a blockquote — a convention for quoted speech,
                 * and the only left-rule in the section — and the address under it
                 * was small AND mono AND muted, three de-emphases at once, in the
                 * same visual language as the info table 24px below it but a
                 * different shape. That similarity-without-sameness is what made it
                 * look accidental.
                 *
                 * NB: no hover-scramble. The old title scrambled into the
                 * dashboard's hover text; that cannot survive one-word-per-line,
                 * because the two strings have different word counts ("University
                 * of West Attica" is four, "Πανεπιστήμιο Δυτικής Αττικής" is three)
                 * and there is nothing sensible to scramble into what. The hover
                 * response is the outline changing colour instead, exactly as a
                 * speaker card does. The hover-text field is now unused here.
                 */
                $uni = fc_one($uni_title);
                // MANUAL line breaks, and the *starred* part drawn hollow.
                //
                // Both are the author's call. Where a venue name breaks is not
                // guessable — it is a phrase, and "University / Of / West /
                // Attica" is four lines of which two are noise. Neither is which
                // part should be outlined: it was automatically the LAST line,
                // which is only ever right by luck. Same convention as the
                // speakers' name, the CFP heading and the stat numbers.
                $uni_lines = fc_hollow_split($uni, '/\R/u');

                // The size is chosen so the LONGEST line fits the column, then
                // every line is set at that size — names at different sizes read
                // as an accident rather than as fitting. Measured in characters,
                // which is what the budget in the CSS below is denominated in.
                //
                // Measured on the PLAIN text: the asterisks are markup, and
                // counting them would set a marked name smaller than an unmarked
                // one saying exactly the same thing.
                $uni_longest = 1;
                foreach (fc_lines(fc_hollow_plain($uni)) as $line) {
                    $len = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
                    if ($len > $uni_longest) $uni_longest = $len;
                }

                $address_text = fc_one($address);
                $addr_lines   = fc_lines($address_text);
                $has_maps     = $maps_url !== '';
                $vstyle       = fc_venue_style();
                ?>

                <?php if (!empty($uni_lines) || !empty($addr_lines)) : ?>
                    <?php
                    /* ONE element wrapping the name AND the address, so hovering
                     * either moves the outline — exactly the speakers' card, where
                     * the name, the roles and the photo are all one hover target
                     * and one link.
                     *
                     * They were siblings before, and only the name reacted. */
                    ?>
                    <?php
                    /* data-fc-centre: on touch there is no pointer, so
                     * assets/centre-highlight.js lights this while its centre is
                     * inside the middle 30% of the screen, and puts it out again
                     * as it scrolls away. No `-item` needed — a container with
                     * nothing marked inside it is itself the item, which is right
                     * here: the link is both the hover target and the box worth
                     * measuring, and splitting the two would mean measuring
                     * something other than the thing that lights up. */
                    ?>
                    <?php if ($has_maps) : ?>
                        <a class="fc-venue-name-link block no-underline text-inherit"
                           href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noreferrer"
                           data-fc-centre
                    <?php else : ?>
                        <div class="fc-venue-name-link block" data-fc-centre
                    <?php endif; ?>
                           style="--fc-venue-rim: <?php echo esc_attr($vstyle['rim']); ?>;
                                  --fc-venue-rim-hover: <?php echo esc_attr($vstyle['rim_hover']); ?>;
                                  --fc-venue-longest: <?php echo (int) $uni_longest; ?>;">

                        <?php if (!empty($uni_lines)) : ?>
                            <h3 class="fc-venue-name m-0">
                                <?php // Each line is already HTML from
                                      // fc_hollow_split(): escaped, with any
                                      // *starred* part wrapped in .is-outline.
                                      // Uppercased in CSS (text-transform), not
                                      // here — mb_strtoupper() would have had to
                                      // run over the markup and would uppercase
                                      // the tag names too. ?>
                                <?php foreach ($uni_lines as $line) : ?>
                                    <span><?php echo $line; ?></span>
                                <?php endforeach; ?>
                            </h3>
                        <?php endif; ?>

                        <?php if (!empty($addr_lines)) : ?>
                            <!-- No Tailwind type classes: .fc-venue-addr sets the
                                 face, the size, the tracking and the case, all
                                 copied from .fc-spk-roles. `font-mono text-sm`
                                 here would have been a second opinion on two of
                                 them — 14px against the rule's 12px — settled only
                                 by which stylesheet the browser read last, which is
                                 not a thing to leave to chance. -->
                            <div class="fc-venue-addr">
                                <?php foreach ($addr_lines as $line) : ?>
                                    <span class="block"><?php echo esc_html($line); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php echo $has_maps ? '</a>' : '</div>'; ?>
                <?php endif; ?>

                <?php if (!empty($info_rows)) : ?>
                    <dl class="fc-venue-info border-t border-border m-0 p-0">
                        <?php foreach ($info_rows as $row) :
                            $rlabel = fc_bi($row, 'label');
                            $rvalue = fc_bi($row, 'value');
                            if ($rlabel['en'] === '' && $rlabel['el'] === '' && $rvalue['en'] === '' && $rvalue['el'] === '') continue;
                            ?>
                            <div class="grid grid-cols-[1fr_2fr] gap-4 items-center py-3 border-b border-border">
                                <dt class="font-mono text-[11px] uppercase tracking-widest text-ink-muted m-0">
                                    <?php echo fc_bi_inline($rlabel['el'], $rlabel['en']); ?>
                                </dt>
                                <dd class="font-mono text-sm text-ink m-0 text-right tabular-nums">
                                    <?php echo fc_bi_inline($rvalue['el'], $rvalue['en'], ' / '); ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>

            <!-- Right: MapLibre venue map (assets/venue-map.js). Editions are map
                 pins; the year browser lives in the desktop panel + mobile bar. -->
            <div class="relative flex flex-col justify-end h-full">
                <div class="w-full"
                     data-fc-island="venue-map"
                     data-fc-cluster-label="<?php echo esc_attr($cluster_label); ?>"
                     data-fc-pin-sprite="<?php echo esc_attr($pin_sprite); ?>"
                     data-fc-spotlight-sprite="<?php echo esc_attr($spotlight_sprite); ?>"
                     data-fc-pin-scale="<?php echo esc_attr((string) $pin_scale); ?>"
                     data-fc-spotlight-scale="<?php echo esc_attr((string) $spotlight_scale); ?>"
                     data-fc-editions="<?php echo esc_attr($editions_json); ?>">
                    <noscript>
                        <div class="ascii text-xs text-ink-faint border border-border p-6 text-center">[ Map requires JavaScript ]</div>
                    </noscript>
                </div>
            </div>
        </div>

        <?php if (!empty($travel_cards)) : ?>
            <div class="grid grid-cols-12 gap-8 border-t border-border pt-12 mt-0">
                <?php $gh = fc_one($getting_here); if ($gh === '') $gh = fc_t('getting_here'); ?>
                <div class="col-span-12 md:col-span-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                    <div><?php echo esc_html($gh); ?></div>
                </div>
                <div class="col-span-12 md:col-span-9 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8 text-base leading-relaxed">
                    <?php foreach ($travel_cards as $card) :
                        $ct = fc_one(fc_bi($card, 'title'));
                        $cb = fc_one(fc_bi($card, 'body'));
                        if ($ct === '') continue;
                        ?>
                        <div>
                            <div class="font-display text-2xl mb-2"><?php echo fc_format($ct); ?></div>
                            <?php if ($cb !== '') : ?>
                                <div class="mt-3">
                                    <p class="text-ink-muted mt-1"><?php echo fc_format($cb); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div><!-- end .max-w container -->
</section>

<style>
/* Whole editions browser is always uppercase (years, cities, arrows). The
   descendant rule is required because Tailwind Preflight resets
   button{text-transform:none}, which would otherwise win over an inherited
   .uppercase. Applies to both the mobile bar and the desktop sticky panel. */
[data-fc-editions-mobile], [data-fc-editions-mobile] *,
[data-fc-editions-desktop], [data-fc-editions-desktop] * { text-transform: uppercase; }

/* Desktop panel locks where the section-nav text ends, with the same gap below
   it as there is between the top bar and the first section link. That Y is
   measured by assets/section-nav.js (separate fixed element — CSS can't read
   it) into --fc-sections-end; 2.5rem (top-10, the sidebar's own top) is the
   pre-JS fallback. The sticky lifecycle itself stays pure CSS.
   The +1px offset keeps the panel's own border-t one pixel below the section
   nav's bottom edge so the line is visible (rather than hidden flush against
   the section nav's bg-paper). */
[data-fc-editions-desktop] { top: calc(var(--fc-sections-end, var(--fc-bar-h)) + 1px); }

/* Editions sidebar items behave like normal links now: a real :hover (mouse
   only) OR a JS-set .is-hovered (from a map-pin hover, or the mobile bar's
   scroll-select) turns the item accent and shows the pointer cursor. Rows with
   an archive URL are <a target="_blank">; link-less rows are <button>s that
   show a sass message on click (assets/venue-map.js). */
/* var(), not a bare `pointer` — see the note in inc/bootstrap.php: this <style>
   is printed inside the body, so it outranks the custom-cursor CSS on document
   order and a literal value here would beat it. */
.fc-year-btn { cursor: var(--fc-cur-pointer, pointer); }
@media (hover: hover) {
    .fc-year-btn:hover { color: var(--accent) !important; }
}
.fc-year-btn.is-hovered { color: var(--accent) !important; }

/* Mobile bar trailing spacer — lets the last item scroll left to the highlight
   edge so it can be auto-selected like the rest. */
.fc-edition-spacer { width: 70vw; }

/* ── Venue name: one word per line, last one hollow ──────────────────────────
   The speakers' signature treatment (.fc-spk-name), applied to the venue. Two
   details carried over because both are easy to get wrong:

     • the stroke is in em, so it stays the same PROPORTION of the letters at
       every size instead of looking heavier as the name shrinks;
     • the outline colour is set OUTSIDE the @supports, so an engine without
       -webkit-text-stroke shows a SOLID accent word rather than an invisible
       one. `color: transparent` with nothing drawn behind it is a missing word,
       and the fallback in each var() matters for the same reason: an invalid
       `color` INHERITS, and the parent here is ink, so a failed variable would
       silently render the last word solid black. */
.fc-venue-name {
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    /* THE SPEAKERS' SIZE, by the speakers' rule: the size at which the longest
       line exactly fills the column, floored at 18px and capped at 82px — the
       same three numbers as .fc-spk-name.
       The coefficient is 1.45 rather than the speakers' 1.52, and that is not a
       taste decision. A capital costs about 0.6em in this face once the -0.05em
       tracking comes off, so the size at which n characters exactly fill a
       column of width w is w / (0.6n) — a coefficient of 1.667. The speakers
       use 1.52 (≈9% spare) because they know their column exactly: it is a CSS
       variable they set themselves. Here the column is a grid track this
       stylesheet can only ESTIMATE (below), so the extra margin covers the
       estimate being a little optimistic. Overflowing into the map is the
       failure worth spending 4% to avoid. */
    font-size: clamp(
        18px,
        calc(var(--fc-venue-col, 24rem) * 1.45 / var(--fc-venue-longest, 12)),
        82px
    );
    line-height: 0.86;
    letter-spacing: -0.05em;
    text-transform: uppercase;
    color: var(--color-ink, #0A0A0A);
}
/* The column this name has to fit, reconstructed from the layout above it:
   fc_section_open() wraps everything in max-w-[1440px] with px-4 (1rem a side)
   and px-8 (2rem a side) from md, and the venue grid is one column below md and
   two with gap-12 (3rem) from md. */
.fc-venue-name { --fc-venue-col: calc(100vw - 2rem); }
@media (min-width: 768px) {
    .fc-venue-name { --fc-venue-col: calc((min(1440px, 100vw) - 4rem - 3rem) / 2); }
}
/* fit-content, not the full column. A block-level line stretches the whole
   width, so the empty strip beside a short word would be part of the link's
   box — clickable, and hoverable, while the pointer is plainly beside the
   word. Same reason the speakers' name does it. */
/* DIRECT children only. Each line is now one outer span with an inner
   .is-outline span around whatever part of it was starred — a descendant
   selector would make that inner span a block too, and a line with only part of
   it marked ("University *of*") would break in the middle. */
.fc-venue-name > span { display: block; width: fit-content; }
/* ONE variable, --fc-venue-rim-now, is what the outline is actually drawn in.
   It starts at the dashboard's resting colour (set on the link below) and the
   hover moves it — so the colour is declared here once and the hover rules are
   two lines instead of a copy of this block inside every @supports. */
.fc-venue-name span.is-outline { color: var(--fc-venue-rim-now, #0033FF); }
@supports (-webkit-text-stroke: 1px black) {
    .fc-venue-name span.is-outline {
        color: transparent;
        -webkit-text-stroke: 0.035em var(--fc-venue-rim-now, #0033FF);
        /* Hollow letters at tight tracking run into each other — the stroke
           adds width the solid weight does not have. A touch looser. */
        letter-spacing: -0.028em;
    }
}
/* Hover moves the outline, and only the outline: the solid lines stay ink, so
   the last line reads as the accent rather than as the odd one out in a name
   that is already entirely coloured.
 *
 * Driven through one variable so there is a single place the hover writes and
 * both the fill and the stroke read it — otherwise every hover rule has to be
 * duplicated inside the @supports for the stroke as well. */
.fc-venue-name-link {
    cursor: var(--fc-cur-pointer, pointer);
    --fc-venue-rim-now: var(--fc-venue-rim, #0033FF);
}

/* OUTSIDE the hover query, both of these, because a phone has to have them.
   There is no pointer on touch, so assets/centre-highlight.js sets .is-hot on
   this link while it is near the middle of the screen — and the transition has
   to live out here too, or the mobile highlight would snap while the desktop one
   faded. Putting a highlight rule inside @media (hover: hover) is the mistake
   that made the speakers' mobile highlight do nothing at all. */
.fc-venue-name-link.is-hot { --fc-venue-rim-now: var(--fc-venue-rim-hover, #EE8101); }
.fc-venue-name span.is-outline {
    transition: color 50ms ease, -webkit-text-stroke-color 50ms ease;
}

@media (hover: hover) {
    /* THE WORDS, not the box.
     *
     * The link is a block, so its box is the whole column — mostly empty air to
     * the right of a short line. `:hover` on it would light the name while the
     * pointer was plainly beside the words, which is the exact fault the
     * speakers row went to the trouble of alpha-testing its way out of.
     * `:has()` narrows the trigger to a line of the name or a line of the
     * address, both of which are `width: fit-content`, so the target hugs the
     * type. Name and address together, as one thing. */
    @supports selector(:has(*)) {
        a.fc-venue-name-link:has(.fc-venue-name span:hover),
        a.fc-venue-name-link:has(.fc-venue-addr span:hover) {
            --fc-venue-rim-now: var(--fc-venue-rim-hover, #EE8101);
        }
    }
    /* Without :has() there is no way to ask "is the pointer on the text", so the
       box is the best available answer. Over-triggering beats not working. */
    @supports not selector(:has(*)) {
        a.fc-venue-name-link:hover {
            --fc-venue-rim-now: var(--fc-venue-rim-hover, #EE8101);
        }
    }
}

/* The address, set exactly as a speaker's ROLES are — same face, same 12px,
   same tracking, same uppercase, same 5px tuck under the name. It is the same
   thing in both places: a caption on the name above it, not a block in its own
   right. Every value here is lifted from .fc-spk-roles.
   The wrapper's Tailwind `space-y-6` was removed for this: it sets margin-top
   on every sibling through a selector these rules cannot outrank, so the 5px
   would silently have been 24px and the address would have gone on floating. */
.fc-venue-addr {
    margin-top: 5px;
    font-family: var(--font-mono, "JetBrains Mono"), ui-monospace, monospace;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1.6;
    color: var(--color-ink-muted, #6B6B66);
}
/* Same reason as the name's lines: the box is the line of type, not the column
   it sits in. */
.fc-venue-addr span { width: fit-content; }
.fc-venue-info { margin-top: 2rem; }

/* The title-scramble CSS that used to live here is gone with the effect it
   styled (.fc-venue-title-link / -en / -el, and the coordinates sub-line). The
   name is set one line per line now and hovering moves the outline colour
   instead; the "hover text" dashboard field went with it. */
</style>

<?php /* The title-scramble script that used to close this file is gone too.
   It bound to .fc-venue-title-link, which no longer exists: the venue name is
   set one line per line and a scramble cannot cross that — the name and the
   hover text had different line counts, so there was nothing sensible to
   scramble into what. Hovering the name or the address moves the outline
   colour instead, which is what a speaker card does, and it is pure CSS. */ ?>
