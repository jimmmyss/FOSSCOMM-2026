<?php
/**
 * Sponsors — one tier per block: a small description, a hollow-marked title, and
 * ONE continuous line of logos.
 *
 * Three things changed shape here and each is worth knowing before editing:
 *
 * 1. TIERS ARE DATA. `fc_sponsors` is a list of tiers, each owning its sponsors
 *    (see fc_sponsor_tiers()). There is no list of six slugs any more, so no
 *    per-tier sizing table, no per-tier row cap, and no fc_sponsor_split().
 *
 * 2. ONE LINE, NEVER TWO. The old layout balanced a tier across as many centered
 *    rows as it needed. A tier is now a single line that becomes a draggable belt
 *    when it overflows — the same behaviour as the speakers row, minus the hit
 *    testing (a logo is a rectangle; a cut-out portrait is not). See
 *    assets/logo-belt.js.
 *
 * 3. EVERY LOGO IS DRAWN AT THE SAME HEIGHT, and its box takes the width that
 *    shape needs. That is also what keeps the section a fixed height: the row is
 *    as tall as --fc-logo-box and nothing else, whatever is in it.
 *
 * A tier is otherwise unframed — one rule underneath it, in the tier's colour.
 * The framed, coloured, full-bleed band this replaced is gone: no outline, no
 * panel colour, no hatch, no breaking out of the section's own column.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$tiers   = fc_sponsor_tiers();

/* fc-section-bleed lifts the wrapper's 1440px cap so a tier can span the whole
   section — and hands it straight back to everything that is not the belt. See
   the rule in site.css.

   Empty title: the section's heading is the funding sentence below, which is
   built from four fields rather than one, so fc_section_open() must not print a
   second one above it. The eyebrow ("06 / Sponsors") stays. */
fc_section_open($section, [
    'title_el' => '',
    'title_en' => '',
    'class'    => 'fc-section-dots fc-section-bleed',
]);

/* Per-tier CSS collected while rendering and emitted once at the end: the shine
   gradient is built from that tier's colour, and a gradient cannot be written as
   a custom property that a single shared rule reads — a `background` built from
   var() is fine, but the STEPPED gradient is a dozen stops long and belongs in
   one place per tier rather than inline on every logo. */
$tier_css = [];

/* ── The funding headline ───────────────────────────────────────────────────
 * "<words> €<amount> <words>". All four pieces are edited in
 * FOSSCOMM → Sponsors: the two halves of the sentence, the amount raised, and
 * the goal it is measured against. Splitting the sentence in two is what lets
 * the amount sit anywhere in it — the words before and the words after are the
 * author's, in either language.
 *
 * The amount is the only part set in Qaroxe, and it is drawn as a glass filling
 * up: blue stands at amount/goal of the digits' own height with a wave rolling
 * across its surface. See .fc-fund-heading in assets/site.css.
 *
 * The € is deliberately NOT in Qaroxe — that face has no euro glyph at all, so
 * it would silently fall back to another font mid-number and sit at a different
 * size. It is its own span, in the heading's face, in the accent colour.
 */
$fund = get_option('fc_sponsors_funding', []);
if (!is_array($fund)) $fund = [];
$fund_amount = (int) ($fund['amount'] ?? 0);
$fund_goal   = (int) ($fund['goal'] ?? 0);
$fund_part1  = fc_pick((string) ($fund['part1_el'] ?? ''), (string) ($fund['part1_en'] ?? ''));
$fund_part2  = fc_pick((string) ($fund['part2_el'] ?? ''), (string) ($fund['part2_en'] ?? ''));
/* No goal, no fill: an empty glass rather than a full one, which is the honest
   reading of "we have not said what we are aiming at". */
$fund_fill   = $fund_goal > 0 ? max(0.0, min(100.0, ($fund_amount / $fund_goal) * 100)) : 0.0;
$has_fund_heading = $fund_amount > 0 || $fund_part1 !== '' || $fund_part2 !== '';
?>
    <?php if ($has_fund_heading) : ?>
        <h2 class="fc-section-heading" style="--fc-fund-fill: <?php echo esc_attr((string) round($fund_fill, 2)); ?>;"><?php
            if ($fund_part1 !== '') echo fc_format($fund_part1) . ' ';
            ?><span class="fc-fund-amount"><span class="fc-fund-cur">€</span><span class="fc-fund-digits"><?php echo esc_html(number_format($fund_amount, 0, ',', '.')); ?></span></span><?php
            if ($fund_part2 !== '') echo ' ' . fc_format($fund_part2);
        ?></h2>
    <?php endif; ?>

    <?php foreach ($tiers as $t_index => $tier) :
        $desc     = fc_one($tier['desc']);
        $title    = fc_one($tier['title']);
        $sponsors = $tier['sponsors'];
        if (!$sponsors && $title === '') continue;

        $colour = $tier['colour'] !== '' ? $tier['colour'] : FC_OUTLINE_REST;
        // Its own field, falling back to the tier colour — so a tier that wants
        // one colour says nothing, and a tier that wants two says so once.
        $line   = $tier['line'] !== '' ? $tier['line'] : $colour;
        $shine  = $tier['shine'] && $colour !== '';
        $belt_id = 'fc-tier-' . $t_index;
        $count  = count($sponsors);
        // The unfilled half of each progress dot: the same colour at 50%.
        // Resolved here rather than with color-mix(), which would take the whole
        // declaration down with it on an engine that does not have it — and the
        // track vanishing leaves the fills floating on nothing.
        $track  = fc_hex_rgba($colour, 0.5);

        if ($shine) {
            $gradient = fc_sponsor_shine_gradient($colour);
            if ($gradient !== '') {
                $tier_css[] = '#' . $belt_id . ' .fc-shine-sweep { background: ' . $gradient . '; }';
            }
        }

        /* The longest line, in characters, for the title's size formula — the
           same rule the speakers' names and the venue's name use. Measured
           WITHOUT the asterisks (fc_hollow_plain), or a marked title would set
           itself smaller than an identical unmarked one. */
        /* TWO markers, two treatments, and the author picks per run:
         *   *Gold*    fills that run in the tier colour
         *   **Gold**  outlines it in the same colour
         * fc_hollow_split() takes both class names for exactly this; the other
         * three hollow headings pass only the first and are unaffected. */
        $title_lines = fc_hollow_split($title, '/\R/u', 'is-accent', 'is-outline');
        $longest = 1;
        foreach (preg_split('/\R/u', fc_hollow_plain($title)) ?: [] as $line) {
            $line = trim($line);
            // function_exists, like the other three headings: mbstring is not
            // guaranteed, and an undefined mb_strlen() is a fatal on a page that
            // would otherwise only have been set at a slightly wrong size.
            $len = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
            if ($len > $longest) $longest = $len;
        }
        ?>
        <?php
        /* The tier's colour drives three things and no longer paints anything:
         * the rule under the tier, the hollow run in the title, and the shine.
         * There is no panel behind any of it. */
        ?>
        <div class="fc-tier" style="--fc-tier-colour: <?php echo esc_attr($colour); ?>;
                                    --fc-tier-line: <?php echo esc_attr($line); ?>;
                                    --fc-tier-track: <?php echo esc_attr($track); ?>;">

            <?php if ($desc !== '' || $title !== '') : ?>
                <div class="fc-tier-head">
                    <?php if ($desc !== '') : ?>
                        <p class="fc-tier-desc fc-label"><?php echo esc_html($desc); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '') : ?>
                        <?php
                        /* The ">" goes INSIDE the last line, not after the h3.
                           Each line is its own block (display:block, width:
                           fit-content — see .fc-tier-title > span), so an arrow
                           placed beside them would start a line of its own
                           instead of sitting next to the words. */
                        $last_line = count($title_lines) - 1;
                        ?>
                        <h3 class="fc-tier-title" style="--fc-tier-longest: <?php echo (int) $longest; ?>;">
                            <?php foreach ($title_lines as $line_i => $line) : ?>
                                <span><?php echo $line; ?><?php
                                    if ($line_i === $last_line) {
                                        echo '<span class="fc-arrow fc-tier-arrow" aria-hidden="true">&gt;</span>';
                                    }
                                ?></span>
                            <?php endforeach; ?>
                        </h3>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($sponsors) : ?>
                <div class="fc-belt<?php echo $shine ? ' has-shine' : ''; ?>"
                     id="<?php echo esc_attr($belt_id); ?>"
                     data-fc-belt>
                    <div class="fc-belt-viewport" data-fc-belt-viewport>
                        <ul class="fc-belt-rail" data-fc-belt-rail>
                            <?php foreach ($sponsors as $s_index => $sp) :
                                $name = $sp['name'];
                                $logo = $sp['logo'];
                                $alt  = $sp['logo_alt'];
                                $url  = $sp['url'];

                                $img = fc_media_img_attrs($logo);
                                $box = fc_logo_box((int) $img['width'], (int) $img['height']);

                                /* `sizes` per logo, because the boxes are not all
                                   the same width. --fc-logo-box runs from 78px on
                                   a phone to 124px on a desktop, and this logo's
                                   slot is that times its own width multiple — so
                                   the browser picks a source that fits rather than
                                   one sized for the widest logo in the section. */
                                if ($img['srcset'] !== '') {
                                    $img['sizes'] = sprintf(
                                        '(max-width: 767px) %dpx, %dpx',
                                        (int) round(78 * $box['w']),
                                        (int) round(124 * $box['w'])
                                    );
                                }

                                /* ONE sweep across the whole row.
                                 *
                                 * Every logo has its own sweep element on the same
                                 * 3s clock, so the phase offset is what decides
                                 * whether the row flashes in unison, jitters, or
                                 * reads as a single highlight travelling across it.
                                 *
                                 * Spreading a FULL cycle across the logos gives the
                                 * last: exactly one is mid-sweep at any moment, and
                                 * it moves along the row. The offsets run DOWN with
                                 * the index, because a negative delay means "already
                                 * this far in" — so the leftmost logo is furthest
                                 * along and sweeps first, and the rightmost last.
                                 *
                                 * Worked out here rather than as calc(i / n) in CSS:
                                 * dividing by a variable is newer than the rest of
                                 * this, and PHP knows both numbers already. */
                                $delay = $count > 0
                                    ? -(($count - 1 - $s_index) / $count) * FC_SHINE_CYCLE
                                    : 0.0;

                                $tag  = $url !== '' ? 'a' : 'div';
                                $attr = 'class="fc-belt-cell' . ($alt !== '' ? ' is-swap' : '') . '"';
                                // --fc-logo-w / -h size the box, as multiples of
                                // the row's base size. See fc_logo_box().
                                $attr .= ' style="--fc-logo-w: ' . esc_attr((string) $box['w']) . ';'
                                    . ' --fc-logo-h: ' . esc_attr((string) $box['h']) . ';'
                                    . ' --fc-cell-delay: ' . esc_attr(sprintf('%.4fs', $delay)) . ';"';
                                if ($url !== '') {
                                    $attr .= ' href="' . esc_url($url) . '" target="_blank" rel="noreferrer"'
                                        . ' title="' . esc_attr($name) . '"';
                                }
                                ?>
                                <li class="fc-belt-item">
                                    <?php echo "<{$tag} {$attr}>"; ?>
                                        <span class="fc-belt-box">
                                            <?php if ($logo !== '') : ?>
                                                <img class="fc-belt-logo"
                                                     src="<?php echo esc_url($img['src']); ?>"
                                                     <?php if ($img['srcset'] !== '') : ?>
                                                     srcset="<?php echo esc_attr($img['srcset']); ?>"
                                                     sizes="<?php echo esc_attr($img['sizes']); ?>"
                                                     <?php endif; ?>
                                                     alt="<?php echo esc_attr($name); ?>"
                                                     draggable="false"
                                                     loading="lazy" decoding="async">
                                                <?php if ($alt !== '') : ?>
                                                    <img class="fc-belt-logo fc-belt-logo-alt"
                                                         src="<?php echo esc_url($alt); ?>"
                                                         alt="" aria-hidden="true"
                                                         draggable="false"
                                                         loading="lazy" decoding="async">
                                                <?php endif; ?>
                                                <?php if ($shine) :
                                                    /* The sweep is masked by the logo's own alpha, so
                                                       it paints on the mark and not on the rectangle
                                                       around it. The tile grid that turns it into a
                                                       mosaic is a second and third mask layer, in CSS
                                                       — see .fc-shine-mask in site.css. */ ?>
                                                    <span class="fc-shine-mask" aria-hidden="true"
                                                          style="--fc-logo-mask: url('<?php echo esc_url($img['src']); ?>');">
                                                        <span class="fc-shine-sweep"></span>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <span class="fc-belt-name font-display"><?php echo esc_html($name); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php echo "</{$tag}>"; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php
                    /* Carousel progress: one dot per sponsor, filling as the belt
                     * travels one full set.
                     *
                     * Rendered whether or not the belt ends up looping, and hidden
                     * by CSS until it does. The loop/no-loop decision is MEASURED
                     * at runtime and re-taken on every resize, so PHP cannot know
                     * it — building the dots here means a window drag never has to
                     * construct or destroy anything.
                     *
                     * aria-hidden: it reports the position of a decorative drift,
                     * and the sponsors themselves are already in the accessibility
                     * tree above it. */
                    ?>
                    <div class="fc-belt-dots" data-fc-belt-dots aria-hidden="true">
                        <?php for ($d = 0; $d < $count; $d++) : ?>
                            <span class="fc-belt-dot" style="--fc-dot-i: <?php echo (int) $d; ?>;">
                                <span class="fc-belt-dot-fill"></span>
                            </span>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php /* The rule is its own element, not a border on the tier: a
                     border wraps the whole box, which is exactly what stopped the
                     belt above from bleeding past it to the edge of the screen. */ ?>
            <div class="fc-tier-rule" aria-hidden="true"></div>
        </div>
    <?php endforeach; ?>

    <?php if (!$tiers) : ?>
        <?php fc_render_tba('sponsors'); ?>
    <?php endif; ?>

    <?php
    // "Become a sponsor" CTA — admin-managed in FOSSCOMM → Sponsors. Mirrors the
    // hero CTA styling (display-link with optional EL slash + arrow). The link
    // target is the uploaded PDF prospectus when present; falls back to a custom URL.
    $cta       = get_option('fc_sponsors_cta', []);
    $cta_arr   = is_array($cta) ? $cta : [];
    $cta_label = fc_bi($cta_arr, 'label');
    $cta_hover = fc_bi($cta_arr, 'hover_label');
    $cta_pdf   = (string) ($cta_arr['pdf'] ?? '');
    $cta_url   = (string) ($cta_arr['url'] ?? '');
    $cta_desc  = fc_bi($cta_arr, 'desc');
    $cta_href  = $cta_pdf !== '' ? $cta_pdf : $cta_url;

    // The second, quieter CTA: a donation link. Same shape as the one above, so
    // the pair reads as two doors into the same room.
    $donate_label = fc_bi($cta_arr, 'donate_label');
    $donate_hover = fc_bi($cta_arr, 'donate_hover_label');   // key matches the admin field
    $donate_desc  = fc_bi($cta_arr, 'donate_desc');
    $donate_url   = (string) ($cta_arr['donate_url'] ?? '');

    $has_sponsor_cta = ($cta_label['en'] !== ''    || $cta_label['el'] !== '')    && $cta_href   !== '';
    $has_donate_cta  = ($donate_label['en'] !== '' || $donate_label['el'] !== '') && $donate_url !== '';

    if ($has_sponsor_cta || $has_donate_cta) :
        // The bare divider rule that used to sit here is GONE. Every tier is a
        // framed band now, so a hairline between the last band and the CTA is a
        // fourth kind of line on a page that already has the section rule, the
        // band frames and their hatch — and it was the "bar hanging alone" when
        // there were no sponsors at all.
        //
        // The margin was sized to centre the CTA between that rule and the
        // section's bottom edge; with the rule gone it just needs normal
        // breathing room.
        ?>
        <?php /* TWO CTAs, SPREAD EVENLY. `space-evenly` is the whole point of the
                 row: it makes the gap at the left edge, the gap between the two
                 buttons and the gap at the right edge all equal — measured inside
                 the section's own padding, which is the box this sits in.

                 Each button keeps its description directly underneath, set like
                 the text under a Get Involved card title, centred under its own
                 button rather than under the row. */ ?>
        <div class="fc-sponsor-ctas mt-16 md:mt-24">
            <?php if ($has_sponsor_cta) : ?>
                <div class="fc-sponsor-cta">
                    <?php fc_cta_link([
                        'url'          => $cta_href,
                        'en'           => $cta_label['en'],
                        'el'           => $cta_label['el'],
                        'hover_en'     => $cta_hover['en'],
                        'hover_el'     => $cta_hover['el'],
                        'target_blank' => $cta_pdf !== '',
                    ]); ?>
                    <?php if ($cta_desc['en'] !== '' || $cta_desc['el'] !== '') : ?>
                        <div class="mt-3 fc-label text-ink-muted leading-relaxed space-y-3">
                            <p class="mt-1"><?php echo fc_bi_inline($cta_desc['el'], $cta_desc['en']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($has_donate_cta) : ?>
                <div class="fc-sponsor-cta">
                    <?php fc_cta_link([
                        'url'          => $donate_url,
                        'en'           => $donate_label['en'],
                        'el'           => $donate_label['el'],
                        'hover_en'     => $donate_hover['en'],
                        'hover_el'     => $donate_hover['el'],
                        'target_blank' => true,   // a payment page is somewhere else
                    ]); ?>
                    <?php if ($donate_desc['en'] !== '' || $donate_desc['el'] !== '') : ?>
                        <div class="mt-3 fc-label text-ink-muted leading-relaxed space-y-3">
                            <p class="mt-1"><?php echo fc_bi_inline($donate_desc['el'], $donate_desc['en']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php
fc_section_close();
?>
<?php
if ($tier_css) : ?>
<style>
<?php echo implode("\n", $tier_css); ?>
</style>
<?php endif; ?>
