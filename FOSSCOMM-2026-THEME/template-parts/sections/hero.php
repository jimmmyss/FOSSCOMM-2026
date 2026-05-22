<?php
/**
 * Hero — paper-on-paper split, vertical rule down the middle.
 *
 *   • Left half: massive FOSSCOMM (display) + accent-blue year, scaled to vh
 *     so it reads big on desktop and stays legible on phones. "00 / HOME"
 *     eyebrow at the top-left.
 *
 *     Mobile vs desktop layout for the left panel:
 *       — Desktop: HOME eyebrow at top, FOSSCOMM mark vertically centered
 *         (flex-1), bottom strip carries the 19th-Panhellenic top label on
 *         the LEFT and socials + email on the RIGHT.
 *       — Mobile:  HOME eyebrow at top, then a flex-1 spacer that pushes
 *         the 19th-Panhellenic label and the FOSSCOMM mark to the BOTTOM
 *         of the panel. The 19th text sits 24px above the logo (matching
 *         its old gap from HOME), and the panel ends 24px under the logo.
 *         The bottom strip is hidden on mobile — its contents (socials +
 *         email) are rendered inside fc-hero-right instead.
 *
 *   • Right half: When / Where / How much info rows + the CTA list.
 *       — Desktop: three flex-1 spacers (top, between info and CTA, bottom)
 *         keep the blocks at symmetric distances regardless of value height.
 *       — Mobile:  explicit margins. 24px (p-6) from the border line, info
 *         block, +48px (mt-12) gap, CTA block, +96px (mt-24) gap, then the
 *         socials + email block, 24px from the panel's bottom edge.
 *
 * Stacks on mobile (single column). On lg+ it's a 5-col grid (3fr left /
 * 2fr right) with a 1px paper rule down the seam.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$top   = fc_bi($data, 'top_label');
$dates = fc_bi($data, 'dates');
$venue = fc_bi($data, 'venue');
$cost  = fc_bi($data, 'cost');
$brand = (string) ($data['brand'] ?? 'FOSSCOMM');
$year  = (string) ($data['year']  ?? '2026');
$email = (string) ($data['email'] ?? '');
$socials = (array) ($data['socials'] ?? []);

$cta_primary       = fc_bi($data, 'cta_primary');
$cta_primary_hover = fc_bi($data, 'cta_primary_hover');
$cta_primary_url   = (string) ($data['cta_primary_url'] ?? '#schedule');
$cta_secondary       = fc_bi($data, 'cta_secondary');
$cta_secondary_hover = fc_bi($data, 'cta_secondary_hover');
$cta_secondary_url   = (string) ($data['cta_secondary_url'] ?? '#volunteer');
$cta_tertiary       = fc_bi($data, 'cta_tertiary');
$cta_tertiary_hover = fc_bi($data, 'cta_tertiary_hover');
$cta_tertiary_url   = (string) ($data['cta_tertiary_url'] ?? '#sponsors');

$hero_ctas = array_values(array_filter([
    ['pair' => $cta_primary,   'hover' => $cta_primary_hover,   'url' => $cta_primary_url],
    ['pair' => $cta_secondary, 'hover' => $cta_secondary_hover, 'url' => $cta_secondary_url],
    ['pair' => $cta_tertiary,  'hover' => $cta_tertiary_hover,  'url' => $cta_tertiary_url],
], function ($c) {
    return ($c['pair']['en'] !== '' || $c['pair']['el'] !== '');
}));

$info_rows = array_values(array_filter([
    ['label_en' => 'When',     'label_el' => 'Πότε', 'value_en' => $dates['en'], 'value_el' => $dates['el']],
    ['label_en' => 'Where',    'label_el' => 'Πού',  'value_en' => $venue['en'], 'value_el' => $venue['el']],
    ['label_en' => 'How much', 'label_el' => 'Πόσο', 'value_en' => $cost['en'],  'value_el' => $cost['el']],
], function ($r) {
    return $r['value_en'] !== '' || $r['value_el'] !== '';
}));

$socials = array_values(array_filter($socials, function ($s) {
    return is_array($s) && (string) ($s['label'] ?? '') !== '';
}));

$has_top_label = ($top['en'] !== '' || $top['el'] !== '');
$has_bottom_right = (!empty($socials) || $email !== '');
$eyebrow_en = fc_section_eyebrow($section);
?>
<section id="<?php echo esc_attr((string) $section['key']); ?>" class="fc-hero relative">
    <!-- LEFT half · FOSSCOMM brand. bg-paper hides the global wave canvas
         under this half; the right half is .fc-section-dots so the canvas
         shows through it.
           Mobile padding+gap: py-24 (96px top/bottom) + gap-24 (96px between
           items) so HOME / 19th / FOSSCOMM all sit on the same rhythm —
           equal distance from each other AND from the top/bottom of the
           panel.
           Desktop: gap goes back to 0 and the FOSSCOMM container takes
           flex-1 so the mark sits centred between HOME and the bottom strip. -->
    <div class="fc-hero-left bg-paper relative flex flex-col px-6 sm:px-10 lg:px-12 pt-[88px] pb-12 lg:pt-20 lg:pb-10">
        <?php if ($eyebrow_en !== '') : ?>
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted" lang="en">
                <?php echo esc_html($eyebrow_en); ?>
            </div>
        <?php endif; ?>

        <?php if ($has_top_label) : ?>
            <!-- 19th label — mobile only. Sits 48px below HOME (mt-12),
                 matching the panel's 48px top padding so every gap in the
                 left column — section-bar → HOME, HOME → 19th, 19th →
                 FOSSCOMM, FOSSCOMM → bottom — is the same 48px. -->
            <div class="lg:hidden mt-12 lg:mt-0 font-mono text-[11px] uppercase tracking-widest text-ink-muted leading-[1.5]">
                <?php if ($top['en'] !== '') : ?>
                    <div lang="en" class="text-ink"><?php echo esc_html($top['en']); ?></div>
                <?php endif; ?>
                <?php if ($top['el'] !== '') : ?>
                    <div class="opacity-70 mt-1"><?php echo esc_html($top['el']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- FOSSCOMM mark.
               Mobile: mt-12 (48px) below the 19th label — same 48px rhythm
                       as the rest of the left column.
               Desktop: flex-1 + items-center → centred vertically in the
                       remaining space between HOME and the bottom strip. -->
        <div class="mt-6 lg:mt-0 lg:flex-1 lg:flex lg:items-center lg:justify-start lg:py-8">
            <h1 class="fc-hero-mark font-display font-bold leading-[0.85] tracking-tighter m-0 text-ink">
                <span class="block" <?php echo fc_island_attrs('scramble'); ?>><?php echo fc_format($brand); ?></span>
                <span class="block fc-hero-year-outline" <?php echo fc_island_attrs('scramble', ['delay' => 300]); ?>><?php echo fc_format($year); ?></span>
            </h1>
        </div>

        <!-- Bottom strip — DESKTOP ONLY. On mobile the 19th label moved up
             above the FOSSCOMM mark and the socials + email moved into the
             right panel, so this strip is empty on mobile and skipped. -->
        <?php if ($has_top_label || $has_bottom_right) : ?>
            <div class="hidden lg:grid lg:grid-cols-[1fr_auto] gap-y-6 gap-x-8 items-end font-mono text-[11px] uppercase tracking-widest text-ink-muted leading-[1.5]">
                <?php if ($has_top_label) : ?>
                    <div>
                        <?php if ($top['en'] !== '') : ?>
                            <div lang="en" class="text-ink"><?php echo esc_html($top['en']); ?></div>
                        <?php endif; ?>
                        <?php if ($top['el'] !== '') : ?>
                            <div class="opacity-70 mt-1"><?php echo esc_html($top['el']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($has_bottom_right) : ?>
                    <div class="lg:text-right">
                        <?php if (!empty($socials)) : ?>
                            <div class="flex flex-wrap lg:justify-end gap-x-4 gap-y-1">
                                <?php foreach ($socials as $s) :
                                    $label = (string) ($s['label'] ?? '');
                                    $url   = (string) ($s['url']   ?? '');
                                    if ($label === '') continue;
                                    ?>
                                    <a href="<?php echo esc_url($url !== '' ? $url : '#'); ?>"
                                       target="_blank" rel="noreferrer noopener"
                                       class="fc-hero-social text-ink hover:text-accent transition-colors">
                                        <?php echo esc_html($label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($email !== '') : ?>
                            <div class="mt-2 text-ink">
                                <a href="<?php echo esc_url('mailto:' . $email); ?>"
                                   class="hover:text-accent transition-colors no-underline">
                                    <?php echo esc_html($email); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT half · info rows + CTAs. fc-section-dots keeps it transparent
         so the global wave canvas shows through here.
           Mobile: same rhythm as the left panel — py-24 (96px top/bottom)
                   + gap-24 (96px between items) so info / CTA /
                   email-socials are spaced equally to each other AND to
                   the top/bottom edges.
           Desktop: gap collapses to 0 and three flex-1 spacers do the
                   vertical distribution. -->
    <div class="fc-hero-right fc-section-dots relative flex flex-col px-6 py-24 lg:p-6 gap-24 lg:gap-0">
        <!-- Desktop-only top spacer. -->
        <div class="hidden lg:block lg:flex-1" aria-hidden="true"></div>

        <div class="fc-hero-info-wrap">
            <?php if (!empty($info_rows)) : ?>
                <dl class="space-y-7 md:space-y-9 m-0">
                    <?php foreach ($info_rows as $row) : ?>
                        <div class="grid grid-cols-[88px_1fr] sm:grid-cols-[110px_1fr] gap-x-4 items-baseline">
                            <dt class="font-mono text-[10px] sm:text-[11px] uppercase tracking-widest leading-tight">
                                <span class="block" lang="en"><?php echo esc_html($row['label_en']); ?></span>
                                <span class="block opacity-50"><?php echo esc_html($row['label_el']); ?></span>
                            </dt>
                            <dd class="m-0 leading-tight">
                                <?php if ($row['value_en'] !== '') : ?>
                                    <div class="font-display text-xl md:text-2xl lg:text-[1.6rem]" lang="en"><?php echo fc_format($row['value_en']); ?></div>
                                <?php endif; ?>
                                <?php if ($row['value_el'] !== '') : ?>
                                    <div class="text-sm md:text-base text-ink-muted mt-0.5"><?php echo fc_format($row['value_el']); ?></div>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>

        <!-- Desktop-only middle spacer. -->
        <div class="hidden lg:block lg:flex-1" aria-hidden="true"></div>

        <?php if (!empty($hero_ctas)) : ?>
            <!-- CTA block. Spacing comes from the parent's gap-24 on mobile
                 and the flex-1 spacers on desktop. -->
            <div class="fc-hero-cta-wrap">
                <ul class="list-none p-0 m-0 space-y-3">
                    <?php foreach ($hero_ctas as $cta) :
                        $en       = (string) $cta['pair']['en'];
                        $el       = (string) $cta['pair']['el'];
                        $hover_en = (string) $cta['hover']['en'];
                        $hover_el = (string) $cta['hover']['el'];
                        $has_hover = ($hover_en !== '' || $hover_el !== '');
                        $strip = function (string $s): string { return (string) preg_replace('/[\s→]+$/u', '', $s); };
                        $en = $strip($en);
                        $el = $strip($el);
                        $hover_en = $strip($hover_en);
                        $hover_el = $strip($hover_el);
                        $el_default = $el !== '' ? (($en !== '' ? '/ ' : '') . $el) : '';
                        $el_alt     = $hover_el !== '' ? (($hover_en !== '' ? '/ ' : '') . $hover_el) : '';
                        ?>
                        <li>
                            <a href="<?php echo esc_url($cta['url']); ?>"
                               class="fc-hero-cta inline-flex items-baseline gap-2 text-ink hover:text-accent transition-colors"
                               <?php if ($has_hover) echo 'data-fc-hover-link'; ?>>
                                <span class="fc-cta-text font-display text-xl md:text-2xl">
                                    <?php if ($en !== '') : ?>
                                        <span lang="en"
                                              <?php if ($has_hover) : ?>data-fc-hover-default="<?php echo esc_attr($en); ?>" data-fc-hover-alt="<?php echo esc_attr($hover_en); ?>"<?php endif; ?>><?php echo fc_format($en); ?></span>
                                    <?php endif; ?>
                                    <?php if ($el !== '') : ?>
                                        <span class="text-sm md:text-base opacity-50"
                                              <?php if ($has_hover) : ?>data-fc-hover-default="<?php echo esc_attr($el_default); ?>" data-fc-hover-alt="<?php echo esc_attr($el_alt); ?>"<?php endif; ?>><?php echo fc_format($el_default); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span aria-hidden="true" class="font-display text-xl md:text-2xl">→</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Desktop-only bottom spacer. -->
        <div class="hidden lg:block lg:flex-1" aria-hidden="true"></div>

        <?php if ($has_bottom_right) : ?>
            <!-- MOBILE-ONLY socials + email block, anchored to the bottom of
                 the right panel. Spacing handled by parent gap-24. -->
            <div class="lg:hidden font-mono text-[11px] uppercase tracking-widest text-ink-muted leading-[1.5]">
                <?php if ($email !== '') : ?>
                    <div class="mb-2 text-ink">
                        <a href="<?php echo esc_url('mailto:' . $email); ?>"
                           class="hover:text-accent transition-colors no-underline">
                            <?php echo esc_html($email); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($socials)) : ?>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <?php foreach ($socials as $s) :
                            $label = (string) ($s['label'] ?? '');
                            $url   = (string) ($s['url']   ?? '');
                            if ($label === '') continue;
                            ?>
                            <a href="<?php echo esc_url($url !== '' ? $url : '#'); ?>"
                               target="_blank" rel="noreferrer noopener"
                               class="fc-hero-social text-ink hover:text-accent transition-colors">
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* Hero layout. Mobile-first stack. lg+ becomes a 5-col grid (3fr left /
   2fr right) with a 1px vertical rule down the seam (paper-on-paper needs
   a separator to avoid looking like one big block). */
.fc-hero {
    display: grid;
    grid-template-columns: 1fr;
    min-height: 100vh;
}
/* Mobile heights:
   • fc-hero-left: no min-height — the panel sizes to its content so
     FOSSCOMM ends flush with the bottom edge (no leftover empty space
     between the mark and the divider line).
   • fc-hero-right: keep a 50vh floor so the right panel reads as a
     proper hero half; its py-24 + gap-24 pin the inter-block rhythm at
     exactly 96 px (top / between info-CTA / between CTA-email / bottom). */
.fc-hero-right { min-height: 50vh; border-top: 1px solid var(--color-border, rgba(10, 10, 10, 0.12)); }

@media (min-width: 1024px) {
    .fc-hero {
        grid-template-columns: 3fr 2fr;
        min-height: 100vh;
    }
    .fc-hero-left,
    .fc-hero-right {
        min-height: 100vh;
    }
    .fc-hero-right {
        border-top: 0;
        border-left: 1px solid var(--color-border, rgba(10, 10, 10, 0.12));
    }
}

/* FOSSCOMM mark.
   Mobile: grows with vw so wider phones / tablets get a larger mark; the
           cap raised to 8rem so the brand keeps scaling up before
           topping out (previous 4rem cap meant the mark stalled on
           anything over a ~360px viewport).
   Desktop: unchanged. Scales with min(9vw, 22vh) up to a 15rem cap, so
           the brand keeps growing on big displays. */
.fc-hero-mark {
    font-size: clamp(2.5rem, 17vw, 8rem);
}
@media (min-width: 1024px) {
    .fc-hero-mark {
        font-size: clamp(3rem, min(9vw, 22vh), 15rem);
    }
}

/* Year: solid accent blue. Sits below the FOSSCOMM mark as a single
   accent slab so the brand reads black-on-paper and the year reads
   blue-on-paper — the page's signature contrast. */
.fc-hero-year-outline {
    color: var(--color-accent, #0033FF);
}

/* .fc-cta-text underline lives in assets/site.css — shared with fc_cta_link()
   so the hero / Get Involved / sponsor / footer CTAs all match. */

/* Socials line — small mono pills with a subtle colour-fade on hover. */
.fc-hero-social { transition: color 120ms ease; }
</style>
