<?php
/**
 * Hero — sponsor-cover layout (see SPONSOR-BROCHURE/1.html).
 *
 *   • LEFT (1/3 on lg, top on mobile): solid accent-blue wordmark panel.
 *       — top-stamp: ■ blip + the 19th-Panhellenic EN line (mono caps).
 *       — wordmark : hard-coded FOSS / COMM / outlined "/26" (Space Grotesk
 *         700, line-height .84, letter-spacing -.05em, white). NOT editable —
 *         matches 1.html exactly. The admin brand/year fields are no longer
 *         used here.
 *       — foot     : the 19th-Panhellenic EL line (mono caps).
 *
 *   • RIGHT (2/3 on lg, below on mobile): paper panel carrying the functional
 *     landing content — When / Where / How-much rows, the CTA list, and
 *     socials + email — as ONE block centered in the middle of the panel.
 *     .fc-section-dots keeps it transparent so the global wave canvas shows
 *     through.
 *
 * The hero breaks out of front-page.php's lg:pl-[200px] gutter (see the
 * margin/width rule in the <style> block) so it spans the full viewport width;
 * the section-nav sidebar is hidden over the hero and only appears at Manifesto
 * (assets/section-nav.js + assets/site.css).
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
$email = (string) ($data['email'] ?? '');
$socials = (array) ($data['socials'] ?? []);

// CTAs — dynamic repeater (Home admin). Each row: label + optional hover label
// + url. Falls back to the legacy fixed primary/secondary/tertiary fields for
// installs that haven't re-saved the Home section yet.
$hero_ctas = [];
foreach ((array) ($data['ctas'] ?? []) as $row) {
    if (!is_array($row)) continue;
    $pair = fc_bi($row, 'label');
    if ($pair['en'] === '' && $pair['el'] === '') continue;
    $hero_ctas[] = [
        'pair'  => $pair,
        'hover' => fc_bi($row, 'label_hover'),
        'url'   => (string) ($row['url'] ?? '#'),
    ];
}
if (empty($hero_ctas)) {
    $legacy = [
        ['base' => 'cta_primary',   'url' => (string) ($data['cta_primary_url']   ?? '#schedule')],
        ['base' => 'cta_secondary', 'url' => (string) ($data['cta_secondary_url'] ?? '#volunteer')],
        ['base' => 'cta_tertiary',  'url' => (string) ($data['cta_tertiary_url']  ?? '#sponsors')],
    ];
    foreach ($legacy as $l) {
        $pair = fc_bi($data, $l['base']);
        if ($pair['en'] === '' && $pair['el'] === '') continue;
        $hero_ctas[] = [
            'pair'  => $pair,
            'hover' => fc_bi($data, $l['base'] . '_hover'),
            'url'   => $l['url'] !== '' ? $l['url'] : '#',
        ];
    }
}

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

$has_top_en = $top['en'] !== '';
$has_top_el = $top['el'] !== '';
$has_bottom_right = (!empty($socials) || $email !== '');
$eyebrow_en = fc_section_eyebrow($section);
?>
<section id="<?php echo esc_attr((string) $section['key']); ?>" class="fc-hero relative">

    <!-- LEFT · solid accent-blue wordmark panel. -->
    <div class="fc-hero-blue relative flex flex-col justify-between px-8 sm:px-12 lg:px-12 pt-16 pb-10 lg:pt-14 lg:pb-12">
        <!-- top-left eyebrow: 00 / HOME -->
        <?php if ($eyebrow_en !== '') : ?>
            <div class="fc-hero-stamp font-mono text-[11px] sm:text-[13px] uppercase tracking-[0.22em]" lang="en">
                <?php echo esc_html($eyebrow_en); ?>
            </div>
        <?php endif; ?>

        <!-- wordmark -->
        <div class="fc-hero-wordmark-wrap py-8">
            <h1 class="fc-hero-wordmark font-display leading-[0.84] m-0" lang="en">
                <span class="block">FOSS</span>
                <span class="block">COMM</span>
                <span class="block fc-hero-outline">/26</span>
            </h1>
        </div>

        <!-- foot: 19th-Panhellenic label — English line + Greek line together,
             on both desktop and mobile (no leading square). -->
        <?php if ($has_top_en || $has_top_el) : ?>
            <div class="fc-hero-foot font-mono text-[11px] sm:text-[13px] uppercase tracking-[0.22em] leading-[1.6]">
                <?php if ($has_top_en) : ?>
                    <div lang="en"><?php echo esc_html($top['en']); ?></div>
                <?php endif; ?>
                <?php if ($has_top_el) : ?>
                    <div class="<?php echo $has_top_en ? 'mt-1 opacity-80' : ''; ?>"><?php echo esc_html($top['el']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT · paper panel. Two containers (info + CTAs) spread with
         justify-evenly — equal gaps top / between / bottom; email + socials are
         pinned at the bottom corners, same font + edge distance as the
         19th-Panhellenic label on the blue panel. Symmetric py padding. -->
    <div class="fc-hero-paper fc-section-dots relative flex flex-col px-8 sm:px-12 lg:px-12 py-10 lg:py-12">
        <div class="flex-1 flex flex-col justify-evenly w-full max-w-lg mx-auto">

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

            <?php if (!empty($hero_ctas)) : ?>
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
            <?php endif; ?>

            <?php if ($has_bottom_right) : ?>
                <!-- email + socials, pinned to the panel's bottom corners on every
                     breakpoint (absolute → out of the flex flow), so only the info
                     + CTA blocks share the even spacing above. Same font + edge
                     distance as the 19th-Panhellenic label on the blue panel. -->
                <div class="absolute inset-x-0 bottom-10 lg:bottom-12 px-8 sm:px-12 lg:px-12 flex items-end justify-between gap-4 font-mono text-[11px] sm:text-[13px] uppercase tracking-[0.22em] text-ink-muted leading-[1.6]">
                    <div class="min-w-0">
                        <?php if ($email !== '') : ?>
                            <a href="<?php echo esc_url('mailto:' . $email); ?>"
                               class="text-ink hover:text-accent transition-colors no-underline break-all"><?php echo esc_html($email); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($socials)) : ?>
                        <div class="flex flex-wrap justify-end gap-x-4 gap-y-1">
                            <?php foreach ($socials as $s) :
                                $label = (string) ($s['label'] ?? '');
                                $url   = (string) ($s['url']   ?? '');
                                if ($label === '') continue;
                                ?>
                                <a href="<?php echo esc_url($url !== '' ? $url : '#'); ?>"
                                   target="_blank" rel="noreferrer noopener"
                                   class="fc-hero-social text-ink hover:text-accent transition-colors no-underline"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div><!-- /.flex-1 (info / CTAs / email — evenly spread on mobile) -->
    </div>
</section>

<style>
/* Sponsor-cover hero. Mobile-first stack (blue on top); lg+ becomes a 1fr/2fr
   grid — blue wordmark 1/3 on the left, paper content 2/3 on the right —
   matching SPONSOR-BROCHURE/1.html. */
.fc-hero {
    display: grid;
    grid-template-columns: 1fr;
    min-height: 100vh;
}
.fc-hero-blue {
    background: var(--color-accent, #0033FF);
    color: #fff;
    overflow: hidden;
    min-height: 58vh;
}
/* Mobile height tuned so the info + CTA blocks get evenly-distributed spacing
   (justify-evenly) — but ~70vh, roughly half the gaps a full 100vh produced.
   Desktop keeps the full 100vh via the lg override below. */
.fc-hero-paper { min-height: 70vh; }

@media (min-width: 1024px) {
    .fc-hero {
        grid-template-columns: 1fr 2fr;
        min-height: 100vh;
    }
    .fc-hero-blue,
    .fc-hero-paper { min-height: 100vh; }
}

/* Eyebrow (00 / HOME) + foot (19th-Panhellenic) on the blue panel. */
.fc-hero-stamp,
.fc-hero-foot { color: rgba(255, 255, 255, 0.72); }

/* Wordmark — same treatment as 1.html .mark: Space Grotesk weight 700, tight
   leading and negative tracking, white. Font-size scales with the viewport so
   it fills the (narrower) 1/3 column on desktop and keeps shrinking on smaller
   phones (low clamp floor). 700 is set explicitly so it always matches the HTML
   weight regardless of .font-display's default 500. */
.fc-hero-wordmark {
    font-weight: 700;
    letter-spacing: -0.05em;
    color: #fff;
    /* Fills the full-width mobile panel the same PROPORTION desktop fills its
       1/3-width column: ~24vw ≈ 3 × desktop's 8.5vw (the panel is ~3× wider), so
       the mark scales with the screen and stays big (~90px on a 375px phone),
       a tiny smaller than desktop (cap 10rem vs 11rem). */
    font-size: clamp(3rem, 24vw, 10rem);
}
@media (min-width: 1024px) {
    .fc-hero-wordmark { font-size: clamp(3rem, 8.5vw, 11rem); }
}
/* Outlined year — hollow white stroke, transparent fill (1.html .outline). */
.fc-hero-outline {
    -webkit-text-stroke: 3px #fff;
    color: transparent;
}

/* .fc-cta-text underline lives in assets/site.css — shared with fc_cta_link()
   so the hero / Get Involved / sponsor / footer CTAs all match. */
.fc-hero-social { transition: color 120ms ease; }
</style>
